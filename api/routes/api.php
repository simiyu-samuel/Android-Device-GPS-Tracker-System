<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;

// Public endpoint - no authentication required
Route::post('/location', [LocationController::class, 'store']);

// Protected endpoints - require Sanctum authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/devices/{device_id}', [DeviceController::class, 'show']);
    Route::get('/devices/{device_id}/history', [DeviceController::class, 'history']);
});
