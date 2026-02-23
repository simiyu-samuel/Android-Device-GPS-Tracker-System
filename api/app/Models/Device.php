<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'device_id',
        'name',
        'model',
        'brand',
        'android_version',
        'last_seen',
        'battery_level',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_seen' => 'datetime',
        'battery_level' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['status'];

    /**
     * Get the device status based on last_seen timestamp.
     * Device is online if last_seen is within 5 minutes.
     *
     * @return string
     */
    public function getStatusAttribute(): string
    {
        if (!$this->last_seen) {
            return 'offline';
        }
        
        $fiveMinutesAgo = now()->subMinutes(5);
        return $this->last_seen->greaterThan($fiveMinutesAgo) ? 'online' : 'offline';
    }

    /**
     * Get the location pings for the device.
     */
    public function locationPings()
    {
        return $this->hasMany(LocationPing::class, 'device_id', 'device_id');
    }

    /**
     * Get the latest location ping for the device.
     */
    public function latestLocation()
    {
        return $this->hasOne(LocationPing::class, 'device_id', 'device_id')
                    ->latest('timestamp');
    }
}
