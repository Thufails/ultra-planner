<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Services\CheckpointGenerator;
use Illuminate\Http\JsonResponse;

class RouteCheckpointController extends Controller
{
    public function __invoke(Route $route, CheckpointGenerator $generator): JsonResponse
    {
        return response()->json($generator->generate(
            $route->routePoints,
            $route->distance_meters,
        ));
    }
}
