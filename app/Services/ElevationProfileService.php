<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElevationProfileService
{
    private const SAMPLE_SPACING_METERS = 50;

    private const MAX_PROFILE_SAMPLES = 3000;

    private const MAX_ROUTE_DISTANCE_METERS = 250_000;

    private const API_CHUNK_SIZE = 100;

    public function build(array $routePoints): array
    {
        $points = collect($routePoints)
            ->map(fn (array $point): array => [
                'latitude' => (float) $point['latitude'],
                'longitude' => (float) $point['longitude'],
                'track_segment_index' => (int) ($point['track_segment_index'] ?? 0),
            ])
            ->values()
            ->all();
        $geometry = $this->withDistances($points);
        $distanceMeters = (int) round(end($geometry)['distance_from_start_meters']);

        if ($distanceMeters <= 0) {
            throw new RuntimeException('Tambahkan jarak antar titik agar profil elevasi bisa dihitung.');
        }

        if ($distanceMeters > self::MAX_ROUTE_DISTANCE_METERS) {
            throw new RuntimeException('Rute lebih dari 250 km belum bisa diproses untuk estimasi elevasi.');
        }

        $sampleSpacing = max(
            self::SAMPLE_SPACING_METERS,
            (int) ceil($distanceMeters / (self::MAX_PROFILE_SAMPLES - 1)),
        );
        $sampleCount = max(2, (int) ceil($distanceMeters / $sampleSpacing) + 1);

        $sampleDistance = $distanceMeters / ($sampleCount - 1);
        $sampleCoordinates = collect(range(0, $sampleCount - 1))
            ->map(fn (int $index): array => [
                ...$this->coordinateAtDistance($geometry, min($distanceMeters, $index * $sampleDistance)),
                'distance_from_start_meters' => (int) round(min($distanceMeters, $index * $sampleDistance)),
            ])
            ->all();
        $routeCacheKey = 'route-elevation:srtm30m:'.hash(
            'sha256',
            json_encode(collect($points)->map(fn (array $point): array => [
                round($point['latitude'], 7),
                round($point['longitude'], 7),
                $point['track_segment_index'],
            ])->all()).'|'.$sampleCount,
        );
        $samples = Cache::remember($routeCacheKey, now()->addDays(30), fn (): array =>
            $this->fetchElevations($sampleCoordinates)
        );

        if (count($samples) !== $sampleCount) {
            throw new RuntimeException('Layanan SRTM tidak mengembalikan sampel elevasi yang cukup.');
        }

        $profile = collect($samples)
            ->map(fn (?float $elevation, int $index): array => [
                'distance_from_start_meters' => (int) round($index * $sampleDistance),
                'elevation_meters' => $elevation === null ? null : round($elevation, 1),
            ])
            ->all();

        $enrichedPoints = collect($geometry)
            ->map(function (array $point) use ($samples, $sampleDistance): array {
                $samplePosition = min(count($samples) - 1, $point['distance_from_start_meters'] / $sampleDistance);
                $leftIndex = (int) floor($samplePosition);
                $rightIndex = min(count($samples) - 1, $leftIndex + 1);
                $left = $samples[$leftIndex] ?? null;
                $right = $samples[$rightIndex] ?? null;
                $fraction = $samplePosition - $leftIndex;

                $elevation = is_numeric($left) && is_numeric($right)
                    ? (float) $left + (((float) $right - (float) $left) * $fraction)
                    : (is_numeric($left) ? (float) $left : (is_numeric($right) ? (float) $right : null));

                return [
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                    'elevation_meters' => $elevation === null ? null : round($elevation, 1),
                    'distance_from_start_meters' => (int) round($point['distance_from_start_meters']),
                    'track_segment_index' => $point['track_segment_index'],
                ];
            })
            ->all();

        // Keep the original route geometry intact and add evenly spaced samples for
        // direct-drawn routes, which may otherwise contain only two control points.
        $trackPoints = collect($enrichedPoints)
            ->concat(collect($profile)->map(function (array $sample, int $index) use ($geometry, $sampleDistance): array {
                $position = $index * $sampleDistance;
                $coordinate = $this->coordinateAtDistance($geometry, $position);

                return [
                    ...$coordinate,
                    'elevation_meters' => $sample['elevation_meters'],
                    'distance_from_start_meters' => $sample['distance_from_start_meters'],
                    'track_segment_index' => 0,
                ];
            }))
            ->sortBy('distance_from_start_meters')
            ->unique(fn (array $point): string => implode(':', [
                $point['track_segment_index'],
                round($point['latitude'], 7),
                round($point['longitude'], 7),
            ]))
            ->values()
            ->all();

        [$gain, $loss] = $this->calculateGainLoss($trackPoints);

        return [
            'route_points' => $trackPoints,
            'profile' => $profile,
            'distance_meters' => $distanceMeters,
            'elevation_gain_meters' => $gain,
            'elevation_loss_meters' => $loss,
            'elevation_source' => 'SRTM 30m',
        ];
    }

    public function calculateGainLoss(array $profile): array
    {
        $elevations = collect($profile)
            ->pluck('elevation_meters')
            ->filter(fn ($elevation): bool => is_numeric($elevation))
            ->map(fn ($elevation): float => (float) $elevation)
            ->values();

        if ($elevations->count() < 2) {
            return [null, null];
        }

        $noiseThresholdMeters = 3.0;
        $anchor = $elevations->first();
        $gain = 0.0;
        $loss = 0.0;

        foreach ($elevations->slice(1) as $elevation) {
            $change = $elevation - $anchor;

            if ($change >= $noiseThresholdMeters) {
                $gain += $change;
                $anchor = $elevation;
            } elseif ($change <= -$noiseThresholdMeters) {
                $loss += abs($change);
                $anchor = $elevation;
            }
        }

        return [(int) round($gain), (int) round($loss)];
    }

    private function fetchElevations(array $coordinates): array
    {
        $values = [];

        foreach (array_chunk($coordinates, self::API_CHUNK_SIZE) as $chunk) {
            $chunkKey = 'route-elevation:srtm30m:chunk:'.hash('sha256', json_encode(collect($chunk)
                ->map(fn (array $point): array => [round($point['latitude'], 7), round($point['longitude'], 7)])
                ->all()));

            $chunkValues = Cache::remember($chunkKey, now()->addDays(30), function () use ($chunk): array {
                try {
                    return Cache::lock('route-elevation:srtm30m:public-api-lock', 10)->block(35, function () use ($chunk): array {
                        $lastRequestAt = Cache::get('route-elevation:srtm30m:last-request-at');
                        if ($lastRequestAt !== null) {
                            $waitMicroseconds = (int) ceil((1.05 - (microtime(true) - (float) $lastRequestAt)) * 1_000_000);
                            if ($waitMicroseconds > 0) {
                                usleep($waitMicroseconds);
                            }
                        }

                        Cache::put('route-elevation:srtm30m:last-request-at', microtime(true), now()->addMinutes(5));
                        $locations = collect($chunk)
                            ->map(fn (array $point): string => $point['latitude'].','.$point['longitude'])
                            ->implode('|');
                        $response = Http::withUserAgent(config('services.elevation.user_agent'))
                            ->acceptJson()
                            ->connectTimeout(5)
                            ->timeout(20)
                            ->post(config('services.elevation.endpoint'), [
                                'locations' => $locations,
                                'interpolation' => 'bilinear',
                            ])
                            ->throw();

                        $payload = $response->json();
                        $results = $payload['results'] ?? [];
                        if (($payload['status'] ?? null) !== 'OK' || count($results) !== count($chunk)) {
                            throw new RuntimeException('Layanan SRTM tidak mengembalikan seluruh sampel elevasi.');
                        }

                        return collect($results)
                            ->map(fn (array $result): ?float => isset($result['elevation']) && is_numeric($result['elevation'])
                                ? (float) $result['elevation']
                                : null)
                            ->all();
                    });
                } catch (LockTimeoutException) {
                    throw new RuntimeException('Antrean layanan elevasi sedang penuh. Coba lagi sebentar.');
                }
            });

            array_push($values, ...$chunkValues);
        }

        return $values;
    }

    private function withDistances(array $points): array
    {
        $distance = 0.0;
        $previous = null;
        $geometry = [];

        foreach ($points as $point) {
            if ($previous !== null && $point['track_segment_index'] === $previous['track_segment_index']) {
                $distance += $this->distanceBetween(
                    $previous['latitude'],
                    $previous['longitude'],
                    $point['latitude'],
                    $point['longitude'],
                );
            }

            $geometry[] = [...$point, 'distance_from_start_meters' => $distance];
            $previous = $point;
        }

        return $geometry;
    }

    private function coordinateAtDistance(array $geometry, float $distance): array
    {
        for ($index = 1; $index < count($geometry); $index++) {
            $start = $geometry[$index - 1];
            $end = $geometry[$index];
            $startDistance = $start['distance_from_start_meters'];
            $endDistance = $end['distance_from_start_meters'];

            if ($end['track_segment_index'] !== $start['track_segment_index'] || $distance > $endDistance) {
                continue;
            }

            $length = $endDistance - $startDistance;
            $fraction = $length > 0 ? max(0, min(1, ($distance - $startDistance) / $length)) : 0;

            return [
                'latitude' => $start['latitude'] + (($end['latitude'] - $start['latitude']) * $fraction),
                'longitude' => $start['longitude'] + (($end['longitude'] - $start['longitude']) * $fraction),
            ];
        }

        $last = end($geometry);

        return ['latitude' => $last['latitude'], 'longitude' => $last['longitude']];
    }

    private function distanceBetween(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadiusMeters = 6_371_000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA))
            * cos(deg2rad($latitudeB))
            * sin($longitudeDelta / 2) ** 2;

        return 2 * $earthRadiusMeters * asin(sqrt(min(1, $a)));
    }
}
