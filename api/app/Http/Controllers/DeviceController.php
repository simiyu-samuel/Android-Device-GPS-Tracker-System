<?php

namespace App\Http\Controllers;

use App\Models\Device;
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
}
