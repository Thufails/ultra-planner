<?php

namespace App\Http\Controllers;

use App\Models\Route;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    public function index(): JsonResponse
    {
        $routes = Route::query()
            ->select([
                'id',
                'name',
                'distance_meters',
                'track_points_count',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return response()->json($routes);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->storeRules());

        $route = DB::transaction(function () use ($validated): Route {
            $route = Route::create([
                'name' => $validated['name'],
            ]);

            $this->replaceControlPoints($route, $validated['control_points']);

            if (array_key_exists('route_points', $validated)) {
                $this->replaceTrackPoints($route, $validated['route_points']);
            }

            return $route;
        });

        return response()->json(
            $route->load(['routeControlPoints', 'routePoints']),
            201,
        );
    }

    public function show(Route $route): JsonResponse
    {
        return response()->json(
            $route->load(['routeControlPoints', 'routePoints']),
        );
    }

    public function update(Request $request, Route $route): JsonResponse
    {
        $validated = $request->validate($this->updateRules());

        DB::transaction(function () use ($validated, $route): void {
            if (array_key_exists('name', $validated)) {
                $route->name = $validated['name'];
                $route->save();
            }

            $controlPointsChanged = array_key_exists('control_points', $validated);
            $trackPointsProvided = array_key_exists('route_points', $validated);

            if ($controlPointsChanged) {
                $this->replaceControlPoints($route, $validated['control_points']);
            }

            if ($trackPointsProvided) {
                $this->replaceTrackPoints($route, $validated['route_points']);
            } elseif ($controlPointsChanged) {
                // The old generated track no longer matches the edited control points.
                $this->replaceTrackPoints($route, []);
            }

            $route->touch();
        });

        return response()->json(
            $route->refresh()->load(['routeControlPoints', 'routePoints']),
        );
    }

    public function destroy(Route $route): Response
    {
        DB::transaction(fn () => $route->delete());

        return response()->noContent();
    }

    private function storeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'control_points' => ['required', 'array', 'min:2'],
            'control_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'control_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'route_points' => ['sometimes', 'array', 'min:2'],
            'route_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'route_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'route_points.*.elevation_meters' => ['nullable', 'numeric', 'between:-12000,10000'],
            'route_points.*.track_segment_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'control_points' => ['sometimes', 'required', 'array', 'min:2'],
            'control_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'control_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'route_points' => ['sometimes', 'required', 'array', 'min:2'],
            'route_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'route_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'route_points.*.elevation_meters' => ['nullable', 'numeric', 'between:-12000,10000'],
            'route_points.*.track_segment_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function replaceControlPoints(Route $route, array $points): void
    {
        $route->routeControlPoints()->delete();

        $route->routeControlPoints()->createMany(
            collect($points)
                ->values()
                ->map(fn (array $point, int $index): array => [
                    'sequence' => $index + 1,
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                ])
                ->all(),
        );
    }

    private function replaceTrackPoints(Route $route, array $points): void
    {
        $route->routePoints()->delete();

        if ($points === []) {
            $route->forceFill([
                'distance_meters' => null,
                'track_points_count' => 0,
            ])->save();

            return;
        }

        $distanceFromStart = 0.0;
        $previousPoint = null;
        $normalizedPoints = [];

        foreach (array_values($points) as $index => $point) {
            $segmentIndex = (int) ($point['track_segment_index'] ?? 0);

            if ($previousPoint !== null && $segmentIndex === $previousPoint['segment_index']) {
                $distanceFromStart += $this->distanceBetween(
                    (float) $previousPoint['latitude'],
                    (float) $previousPoint['longitude'],
                    (float) $point['latitude'],
                    (float) $point['longitude'],
                );
            }

            $normalizedPoints[] = [
                'sequence' => $index + 1,
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'elevation_meters' => $point['elevation_meters'] ?? null,
                'distance_from_start_meters' => (int) round($distanceFromStart),
                'track_segment_index' => $segmentIndex,
            ];

            $previousPoint = [
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'segment_index' => $segmentIndex,
            ];
        }

        $route->routePoints()->createMany($normalizedPoints);
        $route->forceFill([
            'distance_meters' => (int) round($distanceFromStart),
            'track_points_count' => count($normalizedPoints),
        ])->save();
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
