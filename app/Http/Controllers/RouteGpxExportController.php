<?php

namespace App\Http\Controllers;

use App\Models\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RouteGpxExportController extends Controller
{
    public function __invoke(Route $route): StreamedResponse
    {
        $route->load(['routePoints', 'routeControlPoints']);
        $points = $route->routePoints->count() >= 2
            ? $route->routePoints
            : $route->routeControlPoints;

        abort_if($points->count() < 2, 422, 'Rute membutuhkan sedikitnya dua titik untuk diekspor.');

        $segments = [];
        $currentSegmentIndex = null;

        foreach ($points as $point) {
            $segmentIndex = $point->track_segment_index ?? 0;

            if ($currentSegmentIndex !== $segmentIndex) {
                $segments[] = [];
                $currentSegmentIndex = $segmentIndex;
            }

            $latitude = (float) $point->latitude;
            $longitude = (float) $point->longitude;

            if (! is_finite($latitude) || ! is_finite($longitude)
                || $latitude < -90 || $latitude > 90
                || $longitude < -180 || $longitude > 180) {
                continue;
            }

            $segments[array_key_last($segments)][] = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'elevation' => $point->elevation_meters,
            ];
        }

        $segments = array_values(array_filter($segments, fn (array $segment): bool => $segment !== []));
        $pointCount = array_sum(array_map('count', $segments));
        abort_if($pointCount < 2, 422, 'Rute tidak memiliki cukup koordinat yang valid untuk diekspor.');

        $filename = Str::slug($route->name) ?: 'rute-' . $route->id;
        $gpx = $this->buildGpx($route, $segments);

        return response()->streamDownload(
            static function () use ($gpx): void {
                echo $gpx;
            },
            $filename . '.gpx',
            [
                'Content-Type' => 'application/gpx+xml; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function buildGpx(Route $route, array $segments): string
    {
        $name = $this->escapeXml($route->name);
        $createdAt = $route->created_at?->toIso8601String();
        $time = $createdAt === null ? '' : '    <time>' . $this->escapeXml($createdAt) . "</time>\n";
        $gpx = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $gpx .= "<gpx version=\"1.1\" creator=\"Ultra Planner\" xmlns=\"http://www.topografix.com/GPX/1/1\">\n";
        $gpx .= "  <metadata>\n    <name>{$name}</name>\n{$time}  </metadata>\n";
        $gpx .= "  <trk>\n    <name>{$name}</name>\n";

        foreach ($segments as $segment) {
            $gpx .= "    <trkseg>\n";

            foreach ($segment as $point) {
                $latitude = $this->formatCoordinate($point['latitude']);
                $longitude = $this->formatCoordinate($point['longitude']);
                $gpx .= "      <trkpt lat=\"{$latitude}\" lon=\"{$longitude}\">\n";

                if ($point['elevation'] !== null && is_numeric($point['elevation']) && is_finite((float) $point['elevation'])) {
                    $gpx .= '        <ele>' . $this->formatCoordinate((float) $point['elevation'], 2) . "</ele>\n";
                }

                $gpx .= "      </trkpt>\n";
            }

            $gpx .= "    </trkseg>\n";
        }

        return $gpx . "  </trk>\n</gpx>\n";
    }

    private function formatCoordinate(float $value, int $precision = 7): string
    {
        return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.');
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }
}
