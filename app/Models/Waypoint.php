<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'route_id',
    'sequence',
    'distance_meters',
    'latitude',
    'longitude',
    'elevation_meters',
    'name',
    'waypoint_type',
    'notes',
])]
class Waypoint extends Model
{
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'distance_meters' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'elevation_meters' => 'decimal:2',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
