<?php

namespace App\Services;

class RouteGeometryService
{
    /**
     * Project a coordinate onto the closest route segment and return its
     * cumulative distance from the route start.
     *
     * @param array<int, array{latitude: float|int|string, longitude: float|int|string, track_segment_index?: int}> $points
     */
    public function distanceAlongTrackToCoordinate(array $points, float $latitude, float $longitude): ?int
    {
        $distanceBeforeSegment = 0.0;
        $bestDistanceFromStart = null;
        $bestOffset = INF;

        for ($index = 1; $index < count($points); $index++) {
            $start = $points[$index - 1];
            $end = $points[$index];

            if (($start['track_segment_index'] ?? 0) !== ($end['track_segment_index'] ?? 0)) {
                continue;
            }

            $startLatitude = (float) $start['latitude'];
            $startLongitude = (float) $start['longitude'];
            $endLatitude = (float) $end['latitude'];
            $endLongitude = (float) $end['longitude'];
            $segmentLength = $this->distanceBetween($startLatitude, $startLongitude, $endLatitude, $endLongitude);
            $latitudeScale = cos(deg2rad(($startLatitude + $endLatitude + $latitude) / 3));
            $startX = $startLongitude * $latitudeScale;
            $startY = $startLatitude;
            $endX = $endLongitude * $latitudeScale;
            $endY = $endLatitude;
            $targetX = $longitude * $latitudeScale;
            $targetY = $latitude;
            $deltaX = $endX - $startX;
            $deltaY = $endY - $startY;
            $lengthSquared = ($deltaX ** 2) + ($deltaY ** 2);
            $fraction = $lengthSquared === 0.0
                ? 0.0
                : max(0.0, min(1.0, ((($targetX - $startX) * $deltaX) + (($targetY - $startY) * $deltaY)) / $lengthSquared));
            $projectedLatitude = $startLatitude + (($endLatitude - $startLatitude) * $fraction);
            $projectedLongitude = $startLongitude + (($endLongitude - $startLongitude) * $fraction);
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
