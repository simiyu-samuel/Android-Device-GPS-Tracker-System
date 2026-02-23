<?php

use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;

// Public endpoint - no authentication required
Route::post('/location', [LocationController::class, 'store']);
