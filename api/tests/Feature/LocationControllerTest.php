<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\LocationPing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a valid location ping is stored successfully.
     */
    public function test_store_valid_location_ping(): void
    {
        $payload = [
            'device_id' => 'test-device-001',
            'device_name' => 'Test POS Device',
            'device_model' => 'SM-G991B',
            'device_brand' => 'Samsung',
            'android_version' => '13',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10.5,
            'speed' => 0.0,
            'battery_level' => 85,
            'is_charging' => false,
            'timestamp' => '2024-01-15 10:30:00',
        ];

        $response = $this->postJson('/api/location', $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Location ping received successfully',
                     'device_id' => 'test-device-001',
                 ]);

        // Verify device was created
        $this->assertDatabaseHas('devices', [
            'device_id' => 'test-device-001',
            'name' => 'Test POS Device',
            'model' => 'SM-G991B',
            'brand' => 'Samsung',
            'android_version' => '13',
            'battery_level' => 85,
        ]);

        // Verify location ping was stored
        $this->assertDatabaseHas('location_pings', [
            'device_id' => 'test-device-001',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10.5,
            'speed' => 0.0,
            'battery_level' => 85,
        ]);
    }

    /**
     * Test that device record is updated on subsequent pings.
     */
    public function test_store_updates_existing_device(): void
    {
        // Create initial device
        Device::create([
            'device_id' => 'test-device-002',
            'name' => 'Old Name',
            'model' => 'Old Model',
            'brand' => 'Old Brand',
            'android_version' => '12',
            'last_seen' => '2024-01-15 09:00:00',
            'battery_level' => 50,
        ]);

        $payload = [
            'device_id' => 'test-device-002',
            'device_name' => 'Updated Name',
            'device_model' => 'New Model',
            'device_brand' => 'New Brand',
            'android_version' => '13',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'battery_level' => 75,
            'timestamp' => '2024-01-15 10:30:00',
        ];

        $response = $this->postJson('/api/location', $payload);

        $response->assertStatus(200);

        // Verify device was updated
        $this->assertDatabaseHas('devices', [
            'device_id' => 'test-device-002',
            'name' => 'Updated Name',
            'model' => 'New Model',
            'brand' => 'New Brand',
            'android_version' => '13',
            'battery_level' => 75,
        ]);

        // Verify only one device record exists
        $this->assertEquals(1, Device::where('device_id', 'test-device-002')->count());
    }

    /**
     * Test that last_seen timestamp is updated.
     */
    public function test_store_updates_last_seen_timestamp(): void
    {
        $timestamp = '2024-01-15 10:30:00';
        
        $payload = [
            'device_id' => 'test-device-003',
            'device_name' => 'Test Device',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'timestamp' => $timestamp,
        ];

        $this->postJson('/api/location', $payload);

        $device = Device::where('device_id', 'test-device-003')->first();
        $this->assertEquals($timestamp, $device->last_seen->format('Y-m-d H:i:s'));
    }

    /**
     * Test validation error for missing required fields.
     */
    public function test_store_returns_422_for_missing_required_fields(): void
    {
        $payload = [
            'device_name' => 'Test Device',
            // Missing device_id, latitude, longitude, timestamp
        ];

        $response = $this->postJson('/api/location', $payload);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'message',
                     'errors' => [
                         'device_id',
                         'latitude',
                         'longitude',
                         'timestamp',
                     ]
                 ]);
    }

    /**
     * Test validation error for invalid latitude.
     */
    public function test_store_returns_422_for_invalid_latitude(): void
    {
        $payload = [
            'device_id' => 'test-device-004',
            'device_name' => 'Test Device',
            'latitude' => 95.0, // Invalid: must be between -90 and 90
            'longitude' => -74.0060,
            'timestamp' => '2024-01-15 10:30:00',
        ];

        $response = $this->postJson('/api/location', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['latitude']);
    }

    /**
     * Test validation error for invalid longitude.
     */
    public function test_store_returns_422_for_invalid_longitude(): void
    {
        $payload = [
            'device_id' => 'test-device-005',
            'device_name' => 'Test Device',
            'latitude' => 40.7128,
            'longitude' => 185.0, // Invalid: must be between -180 and 180
            'timestamp' => '2024-01-15 10:30:00',
        ];

        $response = $this->postJson('/api/location', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['longitude']);
    }

    /**
     * Test validation error for invalid battery level.
     */
    public function test_store_returns_422_for_invalid_battery_level(): void
    {
        $payload = [
            'device_id' => 'test-device-006',
            'device_name' => 'Test Device',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'battery_level' => 150, // Invalid: must be between 0 and 100
            'timestamp' => '2024-01-15 10:30:00',
        ];

        $response = $this->postJson('/api/location', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['battery_level']);
    }

    /**
     * Test that optional fields can be omitted.
     */
    public function test_store_accepts_minimal_payload(): void
    {
        $payload = [
            'device_id' => 'test-device-007',
            'device_name' => 'Minimal Device',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'timestamp' => '2024-01-15 10:30:00',
        ];

        $response = $this->postJson('/api/location', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('devices', [
            'device_id' => 'test-device-007',
            'name' => 'Minimal Device',
        ]);

        $this->assertDatabaseHas('location_pings', [
            'device_id' => 'test-device-007',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
    }
}
