<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\LocationPing;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Get list of all devices with computed status and latest location.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $devices = Device::with('latestLocation')->get();

        $devicesData = $devices->map(function ($device) {
            $data = [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'name' => $device->name,
                'model' => $device->model,
                'brand' => $device->brand,
                'android_version' => $device->android_version,
                'last_seen' => $device->last_seen?->toISOString(),
                'battery_level' => $device->battery_level,
                'status' => $device->status,
            ];

            // Include latest location if available
            if ($device->latestLocation) {
                $data['latitude'] = $device->latestLocation->latitude;
                $data['longitude'] = $device->latestLocation->longitude;
            }

            return $data;
        });

        return response()->json($devicesData);
    }

    /**
     * Get single device details by device_id.
     *
     * @param string $deviceId
     * @return JsonResponse
     */
    public function show(string $deviceId): JsonResponse
    {
        $device = Device::where('device_id', $deviceId)
            ->with('latestLocation')
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'Device not found'
            ], 404);
        }

        $data = [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'name' => $device->name,
            'model' => $device->model,
            'brand' => $device->brand,
            'android_version' => $device->android_version,
            'last_seen' => $device->last_seen?->toISOString(),
            'battery_level' => $device->battery_level,
            'status' => $device->status,
        ];

        // Include latest location if available
        if ($device->latestLocation) {
            $data['latitude'] = $device->latestLocation->latitude;
            $data['longitude'] = $device->latestLocation->longitude;
        }

        return response()->json($data);
    }

    /**
     * Get location history for a device with optional filters.
     *
     * @param string $deviceId
     * @return JsonResponse
     */
    public function history(string $deviceId): JsonResponse
    {
        // Verify device exists
        $device = Device::where('device_id', $deviceId)->first();
        
        if (!$device) {
            return response()->json([
                'message' => 'Device not found'
            ], 404);
        }

        // Build query with filters
        $query = LocationPing::where('device_id', $deviceId);

        // Apply start_date filter if provided
        if (request()->has('start_date')) {
            $query->where('timestamp', '>=', request('start_date'));
        }

        // Apply end_date filter if provided
        if (request()->has('end_date')) {
            $query->where('timestamp', '<=', request('end_date'));
        }

        // Get limit parameter with default of 100
        $limit = request('limit', 100);

        // Order by timestamp descending and apply limit
        $locationPings = $query->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();

        // Format response with all required fields
        $data = $locationPings->map(function ($ping) {
            return [
                'id' => $ping->id,
                'device_id' => $ping->device_id,
                'latitude' => $ping->latitude,
                'longitude' => $ping->longitude,
                'accuracy' => $ping->accuracy,
                'speed' => $ping->speed,
                'battery_level' => $ping->battery_level,
                'timestamp' => $ping->timestamp->toISOString(),
            ];
        });

        return response()->json($data);
    }
}
