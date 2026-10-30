<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RoutePoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class RouteCheckpointController extends Controller
{
    private const INTERVAL_METERS = 5_000;

    public function __invoke(Route $route): JsonResponse
    {
        $points = $route->routePoints;
        $distance = (int) ($route->distance_meters ?? $points->last()?->distance_from_start_meters ?? 0);

        if ($points->count() < 2 || $distance <= 0) {
            return response()->json([
                'interval_meters' => self::INTERVAL_METERS,
                'distance_meters' => $distance,
                'checkpoints' => [],
            ]);
        }

        $checkpoints = [];

        for ($target = self::INTERVAL_METERS; $target < $distance; $target += self::INTERVAL_METERS) {
            $position = $this->interpolateAtDistance($points, $target);

            if ($position !== null) {
                $checkpoints[] = [
                    'sequence' => count($checkpoints) + 1,
                    'kind' => 'checkpoint',
                    'distance_meters' => $target,
                    ...$position,
                ];
            }
        }

        $finish = $points->last();
        $checkpoints[] = [
            'sequence' => count($checkpoints) + 1,
            'kind' => 'finish',
            'distance_meters' => $distance,
            'latitude' => (float) $finish->latitude,
            'longitude' => (float) $finish->longitude,
        ];

        return response()->json([
            'interval_meters' => self::INTERVAL_METERS,
            'distance_meters' => $distance,
            'checkpoints' => $checkpoints,
        ]);
    }

    /**
     * Find a checkpoint only within a real track segment. Never interpolate
     * across the gap between disconnected GPX segments.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    private function interpolateAtDistance(Collection $points, int $target): ?array
    {
        /** @var RoutePoint|null $previous */
        $previous = null;

        foreach ($points as $point) {
            if ($previous !== null
                && $point->track_segment_index === $previous->track_segment_index
                && $point->distance_from_start_meters !== null
                && $previous->distance_from_start_meters !== null
                && $target >= $previous->distance_from_start_meters
                && $target <= $point->distance_from_start_meters) {
                $segmentDistance = $point->distance_from_start_meters - $previous->distance_from_start_meters;

                if ($segmentDistance === 0) {
                    continue;
                }

                $fraction = ($target - $previous->distance_from_start_meters) / $segmentDistance;

                return [
                    'latitude' => (float) $previous->latitude
                        + ((float) $point->latitude - (float) $previous->latitude) * $fraction,
                    'longitude' => (float) $previous->longitude
                        + ((float) $point->longitude - (float) $previous->longitude) * $fraction,
                ];
            }

            $previous = $point;
        }

        return null;
    }
}
