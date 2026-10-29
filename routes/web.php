<?php

use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::apiResource('routes', RouteController::class);
