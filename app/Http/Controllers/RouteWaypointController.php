<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\Waypoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RouteWaypointController extends Controller
{
    private const TYPES = [
        'water_refill',
        'food_stop',
        'turnaround',
        'exit_point',
        'pickup_point',
        'custom',
    ];

    public function index(Route $route): JsonResponse
    {
        return response()->json($route->waypoints);
    }

    public function store(Request $request, Route $route): JsonResponse
    {
        $validated = $request->validate($this->rules($route));

        $waypoint = $route->waypoints()->create([
            ...$validated,
            'sequence' => ((int) $route->waypoints()->max('sequence')) + 1,
        ]);

        return response()->json($waypoint, 201);
    }

    public function update(Request $request, Route $route, Waypoint $waypoint): JsonResponse
    {
        abort_unless($waypoint->route_id === $route->id, 404);

        $waypoint->update($request->validate($this->rules($route, true)));

        return response()->json($waypoint->refresh());
    }

    public function destroy(Route $route, Waypoint $waypoint): Response
    {
        abort_unless($waypoint->route_id === $route->id, 404);

        $waypoint->delete();

        return response()->noContent();
    }

    private function rules(Route $route, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'name' => [$required, 'string', 'max:120'],
            'waypoint_type' => [$required, 'string', 'in:' . implode(',', self::TYPES)],
            'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'],
            'distance_meters' => [
                'nullable',
                'integer',
                'min:0',
                'max:' . max(0, (int) $route->distance_meters),
            ],
            'elevation_meters' => ['nullable', 'numeric', 'between:-12000,10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
