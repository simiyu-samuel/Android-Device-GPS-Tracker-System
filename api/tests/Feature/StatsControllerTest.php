<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\LocationPing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_requires_authentication_for_stats()
    {
        $response = $this->getJson('/api/stats');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_aggregate_statistics()
    {
        // Create online devices (last_seen within 5 minutes)
        Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now()->subMinutes(2),
            'battery_level' => 85,
        ]);

        Device::create([
            'device_id' => 'device-002',
            'name' => 'POS Device 2',
            'model' => 'Pixel 7',
            'brand' => 'Google',
            'android_version' => '14',
            'last_seen' => now()->subMinutes(3),
            'battery_level' => 70,
        ]);

        // Create offline device (last_seen older than 5 minutes)
        Device::create([
            'device_id' => 'device-003',
            'name' => 'POS Device 3',
            'model' => 'Galaxy S21',
            'brand' => 'Samsung',
            'android_version' => '12',
            'last_seen' => now()->subMinutes(10),
            'battery_level' => 45,
        ]);

        // Create location pings
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
            'device_id' => 'device-002',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'accuracy' => 8.2,
            'speed' => 5.5,
            'battery_level' => 70,
            'timestamp' => now()->subHours(1),
        ]);

        LocationPing::create([
            'device_id' => 'device-003',
            'latitude' => 40.7489,
            'longitude' => -73.9680,
            'accuracy' => 12.0,
            'speed' => 3.2,
            'battery_level' => 45,
            'timestamp' => now()->subHours(30),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_devices',
                'online_devices',
                'offline_devices',
                'total_pings',
                'pings_24h',
            ])
            ->assertJson([
                'total_devices' => 3,
                'online_devices' => 2,
                'offline_devices' => 1,
                'total_pings' => 3,
                'pings_24h' => 2,
            ]);
    }

    /** @test */
    public function it_calculates_total_device_count_correctly()
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

        Device::create([
            'device_id' => 'device-002',
            'name' => 'POS Device 2',
            'model' => 'Pixel 7',
            'brand' => 'Google',
            'android_version' => '14',
            'last_seen' => now(),
            'battery_level' => 70,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJson([
                'total_devices' => 2,
            ]);
    }

    /** @test */
    public function it_calculates_online_devices_count_correctly()
    {
        // Online device (last_seen within 5 minutes)
        Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now()->subMinutes(4),
            'battery_level' => 85,
        ]);

        // Offline device (last_seen older than 5 minutes)
        Device::create([
            'device_id' => 'device-002',
            'name' => 'POS Device 2',
            'model' => 'Pixel 7',
            'brand' => 'Google',
            'android_version' => '14',
            'last_seen' => now()->subMinutes(6),
            'battery_level' => 70,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJson([
                'online_devices' => 1,
            ]);
    }

    /** @test */
    public function it_calculates_offline_devices_count_correctly()
    {
        // Online device
        Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => now()->subMinutes(2),
            'battery_level' => 85,
        ]);

        // Offline devices
        Device::create([
            'device_id' => 'device-002',
            'name' => 'POS Device 2',
            'model' => 'Pixel 7',
            'brand' => 'Google',
            'android_version' => '14',
            'last_seen' => now()->subMinutes(10),
            'battery_level' => 70,
        ]);

        Device::create([
            'device_id' => 'device-003',
            'name' => 'POS Device 3',
            'model' => 'Galaxy S21',
            'brand' => 'Samsung',
            'android_version' => '12',
            'last_seen' => now()->subMinutes(15),
            'battery_level' => 45,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJson([
                'offline_devices' => 2,
            ]);
    }

    /** @test */
    public function it_calculates_total_pings_count_correctly()
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
            'timestamp' => now()->subDays(2),
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7489,
            'longitude' => -73.9680,
            'accuracy' => 12.0,
            'speed' => 3.2,
            'battery_level' => 75,
            'timestamp' => now()->subHours(5),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJson([
                'total_pings' => 3,
            ]);
    }

    /** @test */
    public function it_calculates_pings_in_last_24_hours_correctly()
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

        // Ping older than 24 hours
        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10.5,
            'speed' => 0.0,
            'battery_level' => 85,
            'timestamp' => now()->subHours(30),
        ]);

        // Pings within last 24 hours
        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'accuracy' => 8.2,
            'speed' => 5.5,
            'battery_level' => 80,
            'timestamp' => now()->subHours(12),
        ]);

        LocationPing::create([
            'device_id' => 'device-001',
            'latitude' => 40.7489,
            'longitude' => -73.9680,
            'accuracy' => 12.0,
            'speed' => 3.2,
            'battery_level' => 75,
            'timestamp' => now()->subHours(5),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJson([
                'pings_24h' => 2,
            ]);
    }

    /** @test */
    public function it_returns_zero_counts_when_no_data_exists()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJson([
                'total_devices' => 0,
                'online_devices' => 0,
                'offline_devices' => 0,
                'total_pings' => 0,
                'pings_24h' => 0,
            ]);
    }

    /** @test */
    public function it_handles_devices_with_null_last_seen_as_offline()
    {
        Device::create([
            'device_id' => 'device-001',
            'name' => 'POS Device 1',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'last_seen' => null,
            'battery_level' => 85,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stats');

        $response->assertStatus(200)
            ->assertJson([
                'total_devices' => 1,
                'online_devices' => 0,
                'offline_devices' => 1,
            ]);
    }
}
