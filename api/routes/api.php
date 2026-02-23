<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

// Public endpoints - no authentication required
Route::post('/location', [LocationController::class, 'store']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected endpoints - require Sanctum authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/devices/{device_id}', [DeviceController::class, 'show']);
    Route::get('/devices/{device_id}/history', [DeviceController::class, 'history']);
    Route::get('/stats', [StatsController::class, 'index']);
});
