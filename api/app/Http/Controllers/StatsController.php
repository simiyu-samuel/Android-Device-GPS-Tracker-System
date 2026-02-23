<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\LocationPing;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * Get aggregate statistics about tracked devices and location pings.
     * 
     * Returns:
     * - total_devices: Total count of all devices
     * - online_devices: Count of devices with last_seen within 5 minutes
     * - offline_devices: Count of devices with last_seen older than 5 minutes or null
     * - total_pings: Total count of all location pings
     * - pings_24h: Count of location pings received in the last 24 hours
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // Calculate total device count
        $totalDevices = Device::count();

        // Calculate online devices count (last_seen < 5 minutes)
        $fiveMinutesAgo = now()->subMinutes(5);
        $onlineDevices = Device::where('last_seen', '>', $fiveMinutesAgo)->count();

        // Calculate offline devices count
        $offlineDevices = $totalDevices - $onlineDevices;

        // Calculate total location pings count
        $totalPings = LocationPing::count();

        // Calculate pings in last 24 hours
        $twentyFourHoursAgo = now()->subHours(24);
        $pings24h = LocationPing::where('timestamp', '>', $twentyFourHoursAgo)->count();

        return response()->json([
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'offline_devices' => $offlineDevices,
            'total_pings' => $totalPings,
            'pings_24h' => $pings24h,
        ]);
    }
}
