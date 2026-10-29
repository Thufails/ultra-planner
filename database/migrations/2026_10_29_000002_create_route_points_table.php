<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('elevation_meters', 8, 2)->nullable();
            $table->unsignedInteger('distance_from_start_meters')->nullable();
            $table->unsignedInteger('track_segment_index')->default(0);
            $table->timestamps();

            $table->unique(['route_id', 'sequence']);
            $table->index(['route_id', 'distance_from_start_meters']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_points');
    }
};
