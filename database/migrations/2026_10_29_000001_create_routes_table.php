<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('gpx_path')->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('elevation_gain_meters')->nullable();
            $table->unsignedInteger('elevation_loss_meters')->nullable();
            $table->string('elevation_source', 32)->nullable();
            $table->unsignedInteger('track_points_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
