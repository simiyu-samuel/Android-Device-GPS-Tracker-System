<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\LocationPing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * Store a new location ping from a tracking device.
     * 
     * This endpoint receives location data from Android tracking APKs,
     * creates or updates device records, and stores location pings.
     * No authentication required.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate incoming location ping payload
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:255',
            'device_name' => 'required|string|max:255',
            'device_model' => 'nullable|string|max:255',
            'device_brand' => 'nullable|string|max:255',
            'android_version' => 'nullable|string|max:50',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'battery_level' => 'nullable|integer|between:0,100',
            'is_charging' => 'nullable|boolean',
            'timestamp' => 'required|date',
        ]);

        // Return 422 validation error if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Create or update device record based on device_id
        $device = Device::updateOrCreate(
            ['device_id' => $validated['device_id']],
            [
                'name' => $validated['device_name'],
                'model' => $validated['device_model'] ?? null,
                'brand' => $validated['device_brand'] ?? null,
                'android_version' => $validated['android_version'] ?? null,
                'last_seen' => $validated['timestamp'],
                'battery_level' => $validated['battery_level'] ?? null,
            ]
        );

        // Store location ping in location_pings table
        LocationPing::create([
            'device_id' => $validated['device_id'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'battery_level' => $validated['battery_level'] ?? null,
            'timestamp' => $validated['timestamp'],
        ]);

        // Return 200 success response
        return response()->json([
            'message' => 'Location ping received successfully',
            'device_id' => $validated['device_id'],
        ], 200);
    }
}
