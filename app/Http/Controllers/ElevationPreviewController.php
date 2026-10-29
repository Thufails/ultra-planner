<?php

namespace App\Http\Controllers;

use App\Services\ElevationProfileService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ElevationPreviewController extends Controller
{
    public function __invoke(Request $request, ElevationProfileService $elevation): JsonResponse
    {
        $validated = $request->validate([
            'route_points' => ['required', 'array', 'min:2', 'max:10000'],
            'route_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'route_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'route_points.*.track_segment_index' => ['sometimes', 'integer', 'min:0'],
        ]);

        try {
            return response()->json($elevation->build($validated['route_points']));
        } catch (ConnectionException|RequestException) {
            return response()->json([
                'message' => 'Layanan estimasi elevasi sedang tidak tersedia. Rute tetap bisa disimpan tanpa data elevasi.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
