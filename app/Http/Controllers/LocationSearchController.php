<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LocationSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $query = preg_replace('/\s+/u', ' ', trim($validated['q']));
        $cacheKey = 'location-search:nominatim:' . hash('sha256', mb_strtolower($query));

        if (Cache::has($cacheKey)) {
            return response()->json(['results' => Cache::get($cacheKey)]);
        }

        try {
            $results = Cache::lock('location-search:nominatim:request-lock', 20)->block(15, function () use ($cacheKey, $query): array {
                if (Cache::has($cacheKey)) {
                    return Cache::get($cacheKey);
                }

                $lastRequestAt = Cache::get('location-search:nominatim:last-request-at');

                if ($lastRequestAt !== null) {
                    $waitMicroseconds = (int) ceil((1 - (microtime(true) - (float) $lastRequestAt)) * 1_000_000);

                    if ($waitMicroseconds > 0) {
                        usleep($waitMicroseconds);
                    }
                }

                Cache::put('location-search:nominatim:last-request-at', microtime(true), now()->addMinute());

                $response = Http::withUserAgent(config('services.nominatim.user_agent'))
                    ->acceptJson()
                    ->timeout(8)
                    ->get(config('services.nominatim.endpoint'), [
                        'q' => $query,
                        'format' => 'jsonv2',
                        'limit' => 5,
                        'addressdetails' => 0,
                        'accept-language' => 'id',
                    ])
                    ->throw();

                $places = $response->json();
                $results = collect(is_array($places) ? $places : [])
                    ->filter(fn (array $place): bool => isset($place['lat'], $place['lon'], $place['display_name']))
                    ->map(function (array $place): array {
                        $boundingBox = array_map('floatval', $place['boundingbox'] ?? []);

                        return [
                            'id' => isset($place['osm_type'], $place['osm_id'])
                                ? $place['osm_type'] . '-' . $place['osm_id']
                                : 'place-' . ($place['place_id'] ?? uniqid()),
                            'label' => (string) $place['display_name'],
                            'latitude' => (float) $place['lat'],
                            'longitude' => (float) $place['lon'],
                            'bounding_box' => count($boundingBox) === 4 ? $boundingBox : null,
                        ];
                    })
                    ->values()
                    ->all();

                Cache::put($cacheKey, $results, now()->addHours(24));

                return $results;
            });

            return response()->json(['results' => $results]);
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Pencarian lokasi sedang antre. Coba lagi sebentar.',
            ], 429);
        } catch (ConnectionException|RequestException) {
            return response()->json([
                'message' => 'Layanan pencarian lokasi sedang tidak tersedia. Coba lagi nanti.',
            ], 502);
        }
    }
}
