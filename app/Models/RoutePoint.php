<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'route_id',
    'sequence',
    'latitude',
    'longitude',
    'elevation_meters',
    'distance_from_start_meters',
    'track_segment_index',
])]
class RoutePoint extends Model
{
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'elevation_meters' => 'decimal:2',
            'distance_from_start_meters' => 'integer',
            'track_segment_index' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
