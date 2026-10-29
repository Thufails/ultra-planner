<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->unsignedInteger('distance_meters')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('elevation_meters', 8, 2)->nullable();
            $table->string('name', 120);
            $table->string('waypoint_type', 40)->default('custom');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['route_id', 'distance_meters']);
            $table->index(['route_id', 'waypoint_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waypoints');
    }
};
