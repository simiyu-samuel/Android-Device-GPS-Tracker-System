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

    /** @test */
    public function it_requires_authentication_for_location_history()
    {
        $response = $this->getJson('/api/devices/device-001/history');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_location_history_for_device()
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
            'timestamp' => now()->subHours(2),
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'accuracy' => 8.2,
            'speed' => 5.5,
            'battery_level' => 80,
            'timestamp' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices/device-001/history');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'device_id',
                    'latitude',
                    'longitude',
                    'accuracy',
                    'speed',
                    'battery_level',
                    'timestamp',
                ]
            ]);
    }

    /** @test */
    public function it_orders_location_history_by_timestamp_descending()
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

        $oldPing = LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10.5,
            'speed' => 0.0,
            'battery_level' => 85,
            'timestamp' => now()->subHours(2),
        ]);

        $newPing = LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'accuracy' => 8.2,
            'speed' => 5.5,
            'battery_level' => 80,
            'timestamp' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices/device-001/history');

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertEquals($newPing->id, $data[0]['id']);
        $this->assertEquals($oldPing->id, $data[1]['id']);
    }

    /** @test */
    public function it_filters_location_history_by_start_date()
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
            'timestamp' => now()->subDays(3),
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'accuracy' => 8.2,
            'speed' => 5.5,
            'battery_level' => 80,
            'timestamp' => now()->subDay(),
        ]);

        $startDate = now()->subDays(2)->toISOString();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/devices/device-001/history?start_date={$startDate}");

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    /** @test */
    public function it_filters_location_history_by_end_date()
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
            'timestamp' => now()->subDays(3),
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'accuracy' => 8.2,
            'speed' => 5.5,
            'battery_level' => 80,
            'timestamp' => now()->subDay(),
        ]);

        $endDate = now()->subDays(2)->toISOString();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/devices/device-001/history?end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    /** @test */
    public function it_filters_location_history_by_date_range()
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
            'timestamp' => now()->subDays(5),
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'accuracy' => 8.2,
            'speed' => 5.5,
            'battery_level' => 80,
            'timestamp' => now()->subDays(3),
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7489,
            'longitude' => -73.9680,
            'accuracy' => 12.0,
            'speed' => 3.2,
            'battery_level' => 75,
            'timestamp' => now()->subDay(),
        ]);

        $startDate = now()->subDays(4)->toISOString();
        $endDate = now()->subDays(2)->toISOString();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/devices/device-001/history?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    /** @test */
    public function it_applies_default_limit_of_100_records()
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

        // Create 150 location pings
        for ($i = 0; $i < 150; $i++) {
            LocationPing::create([
                'device_id' => 'device-001',
                'latitude' => 40.7128 + ($i * 0.001),
                'longitude' => -74.0060 + ($i * 0.001),
                'accuracy' => 10.5,
                'speed' => 0.0,
                'battery_level' => 85,
                'timestamp' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices/device-001/history');

        $response->assertStatus(200)
            ->assertJsonCount(100);
    }

    /** @test */
    public function it_respects_custom_limit_parameter()
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

        // Create 50 location pings
        for ($i = 0; $i < 50; $i++) {
            LocationPing::create([
                'device_id' => 'device-001',
                'latitude' => 40.7128 + ($i * 0.001),
                'longitude' => -74.0060 + ($i * 0.001),
                'accuracy' => 10.5,
                'speed' => 0.0,
                'battery_level' => 85,
                'timestamp' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices/device-001/history?limit=10');

        $response->assertStatus(200)
            ->assertJsonCount(10);
    }

    /** @test */
    public function it_returns_404_for_history_of_non_existent_device()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/devices/non-existent-device/history');

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'Device not found',
            ]);
    }
}

