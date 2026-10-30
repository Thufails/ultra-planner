<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\Waypoint;
use App\Services\RouteGeometryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RouteWaypointController extends Controller
{
    private const TYPES = [
        'water_refill',
        'checkpoint',
        'food_stop',
    ];

    public function index(Route $route): JsonResponse
    {
        return response()->json($route->waypoints);
    }

    public function store(Request $request, Route $route, RouteGeometryService $geometry): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $validated['distance_meters'] = $geometry->distanceAlongTrackToCoordinate(
            $this->trackPoints($route),
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        $waypoint = $route->waypoints()->create([
            ...$validated,
            'sequence' => ((int) $route->waypoints()->max('sequence')) + 1,
        ]);

        return response()->json($waypoint, 201);
    }

    public function update(Request $request, Route $route, Waypoint $waypoint, RouteGeometryService $geometry): JsonResponse
    {
        abort_unless($waypoint->route_id === $route->id, 404);

        $validated = $request->validate($this->rules(true, $waypoint->waypoint_type));
        $coordinatesChanged = array_key_exists('latitude', $validated) || array_key_exists('longitude', $validated);
        $waypoint->fill($validated);

        if ($coordinatesChanged) {
            $waypoint->distance_meters = $geometry->distanceAlongTrackToCoordinate(
                $this->trackPoints($route),
                (float) $waypoint->latitude,
                (float) $waypoint->longitude,
            );
        }

        $waypoint->save();

        return response()->json($waypoint->refresh());
    }

    public function destroy(Route $route, Waypoint $waypoint): Response
    {
        abort_unless($waypoint->route_id === $route->id, 404);

        $waypoint->delete();

        return response()->noContent();
    }

    private function rules(bool $partial = false, ?string $existingType = null): array
    {
        $required = $partial ? 'sometimes|required' : 'required';
        $allowedTypes = self::TYPES;

        if ($partial && $existingType !== null && ! in_array($existingType, $allowedTypes, true)) {
            $allowedTypes[] = $existingType;
        }

        return [
            'name' => [$required, 'string', 'max:120'],
            'waypoint_type' => [$required, 'string', 'in:' . implode(',', $allowedTypes)],
            'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'],
            'elevation_meters' => ['nullable', 'numeric', 'between:-12000,10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<int, array{latitude: float, longitude: float, track_segment_index: int}>
     */
    private function trackPoints(Route $route): array
    {
        return $route->routePoints
            ->map(fn ($point): array => [
                'latitude' => (float) $point->latitude,
                'longitude' => (float) $point->longitude,
                'track_segment_index' => (int) $point->track_segment_index,
            ])
            ->all();
    }
}
