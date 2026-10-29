<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'routing_mode',
    'gpx_path',
    'distance_meters',
    'elevation_gain_meters',
    'elevation_loss_meters',
    'elevation_source',
    'track_points_count',
])]
class Route extends Model
{
    protected function casts(): array
    {
        return [
            'routing_mode' => 'string',
            'distance_meters' => 'integer',
            'elevation_gain_meters' => 'integer',
            'elevation_loss_meters' => 'integer',
            'track_points_count' => 'integer',
        ];
    }

    public function routePoints(): HasMany
    {
        return $this->hasMany(RoutePoint::class)->orderBy('sequence');
    }

    public function routeControlPoints(): HasMany
    {
        return $this->hasMany(RouteControlPoint::class)->orderBy('sequence');
    }

    public function waypoints(): HasMany
    {
        return $this->hasMany(Waypoint::class)->orderBy('sequence');
    }
}
