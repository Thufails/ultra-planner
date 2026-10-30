<?php

use App\Http\Controllers\RouteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ElevationPreviewController;
use App\Http\Controllers\RouteGpxExportController;
use App\Http\Controllers\LocationSearchController;
use App\Http\Controllers\RoutePreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('routes/create', [RouteController::class, 'create'])->name('routes.create');
Route::get('routes/location-search', LocationSearchController::class)
    ->middleware('throttle:30,1')
    ->name('routes.location-search');
Route::post('routes/elevation-preview', ElevationPreviewController::class)
    ->middleware('throttle:30,1')
    ->name('routes.elevation-preview');
Route::post('routes/preview', RoutePreviewController::class)
    ->middleware('throttle:30,1')
    ->name('routes.preview');
Route::get('routes/{route}/export/gpx', RouteGpxExportController::class)
    ->name('routes.export.gpx');
Route::get('routes/{route}/edit', [RouteController::class, 'edit'])->name('routes.edit');

Route::apiResource('routes', RouteController::class);
