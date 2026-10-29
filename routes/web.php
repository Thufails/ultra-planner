<?php

use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('routes.index'));

Route::get('routes/create', [RouteController::class, 'create'])->name('routes.create');
Route::get('routes/{route}/edit', [RouteController::class, 'edit'])->name('routes.edit');

Route::apiResource('routes', RouteController::class);
