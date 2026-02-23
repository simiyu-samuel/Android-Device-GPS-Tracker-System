<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\LocationPing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_requires_authentication_for_device_list()
    {
        $response = $this->getJson('/api/devices');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_list_of_all_devices_with_status()
    {
        // Create devices with different statuses
        $onlineDevice = Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now()->subMinutes(2),
            'battery_level' => 85,
        ]);

        $offlineDevice = Device::create([
            'device_id' => 'device-002',
            'name' => 'POS Device 2',
            'model' => 'Pixel 7',
            'brand' => 'Google',
            'android_version' => '14',
            'last_seen' => now()->subMinutes(10),
            'battery_level' => 45,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'device_id' => 'device-001',
                'name' => 'POS Device 1',
                'status' => 'online',
            ])
            ->assertJsonFragment([
                'device_id' => 'device-002',
                'name' => 'POS Device 2',
                'status' => 'offline',
            ]);
    }

    /** @test */
    public function it_includes_latest_location_in_device_list()
    {
        $device = Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now(),
            'battery_level' => 85,
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10.5,
            'speed' => 0.0,
            'battery_level' => 85,
            'timestamp' => now()->subMinutes(5),
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'accuracy' => 8.2,
            'speed' => 5.5,
            'battery_level' => 85,
            'timestamp' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'device_id' => 'device-001',
                'latitude' => 40.7580,
                'longitude' => -73.9855,
            ]);
    }

    /** @test */
    public function it_computes_online_status_for_devices_with_recent_last_seen()
    {
        Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now()->subMinutes(3),
            'battery_level' => 85,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'device_id' => 'device-001',
                'status' => 'online',
            ]);
    }

    /** @test */
    public function it_computes_offline_status_for_devices_with_old_last_seen()
    {
        Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now()->subMinutes(6),
            'battery_level' => 85,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'device_id' => 'device-001',
                'status' => 'offline',
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_device_details()
    {
        $response = $this->getJson('/api/devices/device-001');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_single_device_details()
    {
        $device = Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now()->subMinutes(2),
            'battery_level' => 85,
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10.5,
            'speed' => 0.0,
            'battery_level' => 85,
            'timestamp' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices/device-001');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'device_id',
                'name',
                'model',
                'brand',
                'android_version',
                'last_seen',
                'battery_level',
                'status',
                'latitude',
                'longitude',
            ])
            ->assertJsonFragment([
                'device_id' => 'device-001',
                'name' => 'POS Device 1',
                'model' => 'SM-G991B',
                'brand' => 'Samsung',
                'android_version' => '13',
                'battery_level' => 85,
                'status' => 'online',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_device()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices/non-existent-device');

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'Device not found',
            ]);
    }

    /** @test */
    public function it_includes_all_required_fields_in_device_list()
    {
        Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now(),
            'battery_level' => 85,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'device_id',
                    'name',
                    'model',
                    'brand',
                    'android_version',
                    'last_seen',
                    'battery_level',
                    'status',
                ]
            ]);
    }
}
