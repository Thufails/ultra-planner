<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Services\ElevationProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    public function index(Request $request): JsonResponse|View
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

        if (! $request->expectsJson()) {
            return view('routes.index', compact('routes'));
        }

        return response()->json($routes);
    }

    public function create(): View
    {
        return view('routes.editor', ['route' => null]);
    }

    public function store(Request $request, ElevationProfileService $elevation): JsonResponse
    {
        $validated = $request->validate($this->storeRules());

        $route = DB::transaction(function () use ($validated, $elevation): Route {
            $route = Route::create([
                'name' => $validated['name'],
                'routing_mode' => $validated['routing_mode'] ?? 'direct',
            ]);

            $this->replaceControlPoints($route, $validated['control_points']);

            if (array_key_exists('route_points', $validated)) {
                $this->replaceTrackPoints($route, $validated['route_points'], $elevation);
            }

            return $route;
        });

        return response()->json(
            $route->load(['routeControlPoints', 'routePoints', 'waypoints']),
            201,
        );
    }

    public function show(Request $request, Route $route): JsonResponse|View
    {
        $route->load(['routeControlPoints', 'routePoints', 'waypoints']);

        if (! $request->expectsJson()) {
            return view('routes.show', compact('route'));
        }

        return response()->json($route);
    }

    public function edit(Route $route): View
    {
        $route->load(['routeControlPoints', 'routePoints', 'waypoints']);

        return view('routes.editor', compact('route'));
    }

    public function update(Request $request, Route $route, ElevationProfileService $elevation): JsonResponse
    {
        $validated = $request->validate($this->updateRules());

        DB::transaction(function () use ($validated, $route, $elevation): void {
            if (array_key_exists('name', $validated)) {
                $route->name = $validated['name'];
                $route->save();
            }

            if (array_key_exists('routing_mode', $validated)) {
                $route->routing_mode = $validated['routing_mode'];
                $route->save();
            }

            $controlPointsChanged = array_key_exists('control_points', $validated);
            $trackPointsProvided = array_key_exists('route_points', $validated);

            if ($controlPointsChanged) {
                $this->replaceControlPoints($route, $validated['control_points']);
            }

            if ($trackPointsProvided) {
                $this->replaceTrackPoints($route, $validated['route_points'], $elevation);
            } elseif ($controlPointsChanged) {
                // The old generated track no longer matches the edited control points.
                $this->replaceTrackPoints($route, [], $elevation);
            }

            $route->touch();
        });

        return response()->json(
            $route->refresh()->load(['routeControlPoints', 'routePoints', 'waypoints']),
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
            'routing_mode' => ['sometimes', 'required', 'in:direct,foot'],
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
            'routing_mode' => ['sometimes', 'required', 'in:direct,foot'],
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

    private function replaceTrackPoints(Route $route, array $points, ElevationProfileService $elevation): void
    {
        $route->routePoints()->delete();

        if ($points === []) {
            $route->forceFill([
                'distance_meters' => null,
                'elevation_gain_meters' => null,
                'elevation_loss_meters' => null,
                'elevation_source' => null,
                'track_points_count' => 0,
            ])->save();

            $route->waypoints()->update(['distance_meters' => null]);

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
        $elevationPointCount = collect($normalizedPoints)
            ->filter(fn (array $point): bool => $point['elevation_meters'] !== null)
            ->count();
        [$elevationGain, $elevationLoss] = $elevation->calculateGainLoss($normalizedPoints);
        $route->forceFill([
            'distance_meters' => (int) round($distanceFromStart),
            'elevation_gain_meters' => $elevationPointCount >= 2 ? $elevationGain : null,
            'elevation_loss_meters' => $elevationPointCount >= 2 ? $elevationLoss : null,
            'elevation_source' => $elevationPointCount >= 2 ? 'SRTM 30m' : null,
            'track_points_count' => count($normalizedPoints),
        ])->save();

        $route->waypoints->each(function ($waypoint) use ($normalizedPoints): void {
            $waypoint->distance_meters = $this->distanceAlongTrackToCoordinate(
                $normalizedPoints,
                (float) $waypoint->latitude,
                (float) $waypoint->longitude,
            );
            $waypoint->save();
        });
    }

    private function distanceAlongTrackToCoordinate(array $points, float $latitude, float $longitude): ?int
    {
        $distanceBeforeSegment = 0.0;
        $bestDistanceFromStart = null;
        $bestOffset = INF;

        for ($index = 1; $index < count($points); $index++) {
            $start = $points[$index - 1];
            $end = $points[$index];
            if ($start['track_segment_index'] !== $end['track_segment_index']) {
                continue;
            }

            $segmentLength = $this->distanceBetween(
                (float) $start['latitude'],
                (float) $start['longitude'],
                (float) $end['latitude'],
                (float) $end['longitude'],
            );
            $latitudeScale = cos(deg2rad(((float) $start['latitude'] + (float) $end['latitude'] + $latitude) / 3));
            $startX = (float) $start['longitude'] * $latitudeScale;
            $startY = (float) $start['latitude'];
            $endX = (float) $end['longitude'] * $latitudeScale;
            $endY = (float) $end['latitude'];
            $targetX = $longitude * $latitudeScale;
            $targetY = $latitude;
            $deltaX = $endX - $startX;
            $deltaY = $endY - $startY;
            $lengthSquared = ($deltaX ** 2) + ($deltaY ** 2);
            $fraction = $lengthSquared === 0.0
                ? 0.0
                : max(0.0, min(1.0, ((($targetX - $startX) * $deltaX) + (($targetY - $startY) * $deltaY)) / $lengthSquared));
            $projectedLatitude = (float) $start['latitude'] + (((float) $end['latitude'] - (float) $start['latitude']) * $fraction);
            $projectedLongitude = (float) $start['longitude'] + (((float) $end['longitude'] - (float) $start['longitude']) * $fraction);
            $offset = $this->distanceBetween($latitude, $longitude, $projectedLatitude, $projectedLongitude);

            if ($offset < $bestOffset) {
                $bestOffset = $offset;
                $bestDistanceFromStart = $distanceBeforeSegment + ($segmentLength * $fraction);
            }

            $distanceBeforeSegment += $segmentLength;
        }

        return $bestDistanceFromStart === null ? null : (int) round($bestDistanceFromStart);
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
