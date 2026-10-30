<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RoutePreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'control_points' => ['required', 'array', 'min:2', 'max:40'],
            'control_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'control_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $points = collect($validated['control_points'])
            ->map(fn (array $point): array => [
                'latitude' => round((float) $point['latitude'], 7),
                'longitude' => round((float) $point['longitude'], 7),
            ])
            ->all();
        $cacheKey = 'route-preview:fossgis:v2:'.hash('sha256', json_encode($points));

        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        try {
            $result = Cache::lock('route-preview:fossgis:request-lock', 20)->block(8, function () use ($cacheKey, $points): array {
                if (Cache::has($cacheKey)) {
                    return Cache::get($cacheKey);
                }

                $lastRequestAt = Cache::get('route-preview:fossgis:last-request-at');
                if ($lastRequestAt !== null) {
                    $waitMicroseconds = (int) ceil((1 - (microtime(true) - (float) $lastRequestAt)) * 1_000_000);
                    if ($waitMicroseconds > 0) {
                        usleep($waitMicroseconds);
                    }
                }

                Cache::put('route-preview:fossgis:last-request-at', microtime(true), now()->addMinute());

                $coordinates = collect($points)
                    ->map(fn (array $point): string => $point['longitude'].','.$point['latitude'])
                    ->implode(';');
                $response = Http::withUserAgent(config('services.routing.user_agent'))
                    ->withHeaders(['Referer' => config('app.url')])
                    ->acceptJson()
                    ->connectTimeout(4)
                    ->timeout(12)
                    ->get(rtrim(config('services.routing.endpoint'), '/').'/'.$coordinates, [
                        'alternatives' => 3,
                        'overview' => 'full',
                        'geometries' => 'geojson',
                        'steps' => 'false',
                    ])
                    ->throw();

                $payload = $response->json();
                $routes = collect($payload['routes'] ?? [])
                    ->filter(fn (array $route): bool => isset($route['geometry']['coordinates'])
                        && is_array($route['geometry']['coordinates'])
                        && count($route['geometry']['coordinates']) >= 2)
                    ->map(function (array $route): array {
                        $routePoints = collect($route['geometry']['coordinates'])
                            ->filter(fn (array $coordinate): bool => count($coordinate) >= 2
                                && is_numeric($coordinate[0])
                                && is_numeric($coordinate[1])
                                && abs((float) $coordinate[0]) <= 180
                                && abs((float) $coordinate[1]) <= 90)
                            ->map(fn (array $coordinate): array => [
                                'latitude' => (float) $coordinate[1],
                                'longitude' => (float) $coordinate[0],
                            ])
                            ->values();

                        return [
                            'distance_meters' => (int) round((float) ($route['distance'] ?? 0)),
                            'route_points' => $routePoints,
                        ];
                    })
                    ->filter(fn (array $route): bool => $route['distance_meters'] > 0 && $route['route_points']->count() >= 2)
                    ->values();

                if (($payload['code'] ?? null) !== 'Ok' || $routes->isEmpty()) {
                    abort(422, 'Tidak ada jalur jalan kaki yang tersambung untuk titik-titik ini. Geser atau tambahkan titik kontrol, lalu coba lagi.');
                }

                $result = [
                    'routes' => $routes->all(),
                    'snap_distances_meters' => collect($payload['waypoints'] ?? [])
                        ->map(fn (array $waypoint): int => (int) round((float) ($waypoint['distance'] ?? 0)))
                        ->values()
                        ->all(),
                ];

                Cache::put($cacheKey, $result, now()->addHours(2));

                return $result;
            });

            return response()->json($result);
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Layanan rute sedang sibuk. Tunggu sebentar, lalu coba lagi.',
            ], 429);
        } catch (ConnectionException|RequestException) {
            return response()->json([
                'message' => 'Layanan rute jalan kaki sedang tidak tersedia. Coba lagi sebentar atau matikan opsi Ikuti jalan.',
            ], 502);
        }
    }
}
