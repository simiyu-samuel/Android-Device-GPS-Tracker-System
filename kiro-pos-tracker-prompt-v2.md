# Kiro Build Prompt: POS Device GPS Tracker System (v2)

## Project Overview

Build a complete **POS Device Location Tracking System** with three parts:

1. **React Native Android APK** — runs silently in background, starts on device boot, sends GPS location to Laravel API every 60 seconds
2. **Laravel 11 REST API Backend** — receives location pings, stores in MySQL, serves JSON to the dashboard, includes owner authentication
3. **React Web Dashboard** — real-time **Google Maps** interface showing all tracked POS devices, route history, device details, battery status, and online/offline state. Owner logs in via the dashboard to view their devices.

The APK is **sideloaded** directly onto Android POS devices via ADB — NOT uploaded to Play Store. Produce a final signed `.apk` ready to transfer to the device.

---

## Tech Stack Summary

| Layer | Technology |
|---|---|
| Mobile APK | React Native 0.73+, `react-native-background-geolocation`, `react-native-device-info` |
| Backend API | Laravel 11, MySQL, Laravel Sanctum (auth), PHP 8.2+ |
| Dashboard | React 18 + TypeScript, Google Maps JavaScript API (`@react-google-maps/api`) |
| Auth | Laravel Sanctum token-based auth for dashboard owner login |

---

## Part 1: React Native Android APK

### Project Setup

```
Project name: POSTracker
Package name: com.postracker.device
React Native version: 0.73+
Target: Android only
Min SDK: 23
Target SDK: 34
```

### NPM Dependencies

```bash
npm install @react-native-async-storage/async-storage
npm install react-native-background-geolocation
npm install react-native-device-info
npm install axios
npm install react-native-keep-awake
```

### Native Android: Boot Receiver

Create file at `android/app/src/main/java/com/postracker/device/BootReceiver.java`:

```java
package com.postracker.device;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;

public class BootReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction()) ||
            "android.intent.action.LOCKED_BOOT_COMPLETED".equals(intent.getAction())) {
            Intent serviceIntent = new Intent(context, MainActivity.class);
            serviceIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            context.startActivity(serviceIntent);
        }
    }
}
```

### AndroidManifest.xml — Full Permissions

Add inside `<manifest>` tag (before `<application>`):

```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION" />
<uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED" />
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.REQUEST_IGNORE_BATTERY_OPTIMIZATIONS" />
<uses-permission android:name="android.permission.READ_PHONE_STATE" />
<uses-permission android:name="android.permission.WAKE_LOCK" />
```

Add inside `<application>` block:

```xml
<receiver
    android:name=".BootReceiver"
    android:enabled="true"
    android:exported="true">
    <intent-filter android:priority="999">
        <action android:name="android.intent.action.BOOT_COMPLETED" />
        <action android:name="android.intent.action.LOCKED_BOOT_COMPLETED" />
        <action android:name="android.intent.action.QUICKBOOT_POWERON" />
    </intent-filter>
</receiver>
```

### App.tsx — Main Application

```tsx
import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import BackgroundGeolocation from 'react-native-background-geolocation';
import DeviceInfo from 'react-native-device-info';
import axios from 'axios';

// ⚠️ Replace with your actual Laravel API URL before building APK
const API_URL = 'https://YOUR_LARAVEL_DOMAIN.com';

export default function App() {
  const [status, setStatus] = useState('Initializing...');
  const [lastPing, setLastPing] = useState('Never');
  const [coords, setCoords] = useState({ lat: 0, lng: 0 });
  const [battery, setBattery] = useState(0);

  useEffect(() => {
    initTracker();
  }, []);

  const initTracker = async () => {
    const deviceId = await DeviceInfo.getUniqueId();
    const deviceName = await DeviceInfo.getDeviceName();
    const model = await DeviceInfo.getModel();
    const brand = await DeviceInfo.getBrand();
    const androidVersion = await DeviceInfo.getSystemVersion();

    const sendLocation = async (location: any) => {
      const { latitude, longitude, accuracy, speed } = location.coords;
      const batteryLevel = Math.round((location.battery?.level || 0) * 100);

      setCoords({ lat: latitude, lng: longitude });
      setBattery(batteryLevel);
      setLastPing(new Date().toLocaleTimeString());
      setStatus('Tracking Active ✓');

      try {
        await axios.post(`${API_URL}/api/location`, {
          device_id: deviceId,
          device_name: deviceName,
          model: model,
          brand: brand,
          android_version: androidVersion,
          lat: latitude,
          lng: longitude,
          accuracy: accuracy,
          speed: speed || 0,
          battery: batteryLevel,
          timestamp: new Date().toISOString(),
        }, {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          timeout: 15000,
        });
      } catch (err) {
        setStatus('API Error — will retry...');
        console.error('[Location Send Error]', err);
      }
    };

    BackgroundGeolocation.onLocation(sendLocation);

    BackgroundGeolocation.onHeartbeat(async (event) => {
      // Force a location fetch on heartbeat even if stationary
      const location = await BackgroundGeolocation.getCurrentPosition({
        timeout: 30,
        maximumAge: 5000,
        desiredAccuracy: 10,
      });
      sendLocation(location);
    });

    await BackgroundGeolocation.ready({
      desiredAccuracy: BackgroundGeolocation.DESIRED_ACCURACY_HIGH,
      distanceFilter: 10,
      stopTimeout: 5,
      debug: false,
      logLevel: BackgroundGeolocation.LOG_LEVEL_ERROR,
      stopOnTerminate: false,
      startOnBoot: true,
      foregroundService: true,
      notification: {
        title: 'POS Service Running',
        text: 'Background service active',
        smallIcon: 'drawable/ic_launcher_foreground',
        priority: BackgroundGeolocation.NOTIFICATION_PRIORITY_MIN,
        sticky: true,
      },
      locationAuthorizationRequest: 'Always',
      backgroundPermissionRationale: {
        title: 'Allow background location',
        message: 'Required for POS device tracking service',
        positiveAction: 'Allow',
        negativeAction: 'Cancel',
      },
      heartbeatInterval: 60,
      preventSuspend: true,
      enableHeadless: true,
      persistMode: BackgroundGeolocation.PERSIST_MODE_ALL,
    });

    await BackgroundGeolocation.start();
    setStatus('Tracker Started');
  };

  return (
    <View style={styles.container}>
      <View style={styles.card}>
        <Text style={styles.title}>🟢 POS Tracker</Text>
        <Text style={styles.status}>{status}</Text>
        <View style={styles.divider} />
        <Text style={styles.label}>Latitude</Text>
        <Text style={styles.value}>{coords.lat.toFixed(6)}</Text>
        <Text style={styles.label}>Longitude</Text>
        <Text style={styles.value}>{coords.lng.toFixed(6)}</Text>
        <Text style={styles.label}>Battery</Text>
        <Text style={styles.value}>{battery}%</Text>
        <Text style={styles.label}>Last Ping</Text>
        <Text style={styles.value}>{lastPing}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1, backgroundColor: '#0d1117',
    justifyContent: 'center', alignItems: 'center',
  },
  card: {
    backgroundColor: '#161b22', borderRadius: 16,
    padding: 24, width: '80%', borderWidth: 1, borderColor: '#30363d',
  },
  title: { color: '#3fb950', fontSize: 20, fontWeight: 'bold', marginBottom: 8 },
  status: { color: '#8b949e', fontSize: 13, marginBottom: 16 },
  divider: { height: 1, backgroundColor: '#30363d', marginBottom: 16 },
  label: { color: '#8b949e', fontSize: 11, textTransform: 'uppercase', letterSpacing: 1 },
  value: { color: '#e6edf3', fontSize: 15, fontWeight: '600', marginBottom: 12 },
});
```

### HeadlessTask.ts — Background Processing When App is Killed

Create `HeadlessTask.ts` in project root:

```ts
import BackgroundGeolocation from 'react-native-background-geolocation';
import DeviceInfo from 'react-native-device-info';
import axios from 'axios';

const API_URL = 'https://YOUR_LARAVEL_DOMAIN.com';

const HeadlessTask = async (event: any) => {
  if (event.name === 'heartbeat' || event.name === 'location') {
    try {
      const deviceId = await DeviceInfo.getUniqueId();
      const deviceName = await DeviceInfo.getDeviceName();
      const model = await DeviceInfo.getModel();

      let location = event.params;
      if (event.name === 'heartbeat') {
        location = await BackgroundGeolocation.getCurrentPosition({ timeout: 20 });
      }

      await axios.post(`${API_URL}/api/location`, {
        device_id: deviceId,
        device_name: deviceName,
        model,
        lat: location?.coords?.latitude,
        lng: location?.coords?.longitude,
        accuracy: location?.coords?.accuracy,
        speed: location?.coords?.speed || 0,
        battery: Math.round((location?.battery?.level || 0) * 100),
        timestamp: new Date().toISOString(),
      }, { timeout: 15000 });
    } catch (e) {
      console.error('[HeadlessTask Error]', e);
    }
  }
};

BackgroundGeolocation.registerHeadlessTask(HeadlessTask);
export default HeadlessTask;
```

Register in `index.js`:

```js
import { AppRegistry } from 'react-native';
import App from './App';
import { name as appName } from './app.json';
import './HeadlessTask';

AppRegistry.registerComponent(appName, () => App);
```

### Build the Signed APK

```bash
# Step 1: Generate keystore (run once from /android folder)
cd android
keytool -genkeypair -v -storetype PKCS12 \
  -keystore postracker.keystore \
  -alias postracker \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -storepass postracker123 -keypass postracker123 \
  -dname "CN=POSTracker, OU=Dev, O=Company, L=City, S=State, C=US"

# Step 2: Add to android/gradle.properties
echo "MYAPP_UPLOAD_STORE_FILE=postracker.keystore" >> gradle.properties
echo "MYAPP_UPLOAD_KEY_ALIAS=postracker" >> gradle.properties
echo "MYAPP_UPLOAD_STORE_PASSWORD=postracker123" >> gradle.properties
echo "MYAPP_UPLOAD_KEY_PASSWORD=postracker123" >> gradle.properties

# Step 3: Add signing config to android/app/build.gradle
# Inside android { signingConfigs { release { ... } } }
# and buildTypes { release { signingConfig signingConfigs.release } }

# Step 4: Build
cd android
./gradlew assembleRelease

# Final APK location:
# android/app/build/outputs/apk/release/app-release.apk
```

The `android/app/build.gradle` signing block:

```groovy
android {
    signingConfigs {
        release {
            storeFile file(MYAPP_UPLOAD_STORE_FILE)
            storePassword MYAPP_UPLOAD_STORE_PASSWORD
            keyAlias MYAPP_UPLOAD_KEY_ALIAS
            keyPassword MYAPP_UPLOAD_KEY_PASSWORD
        }
    }
    buildTypes {
        release {
            signingConfig signingConfigs.release
            minifyEnabled false
            proguardFiles getDefaultProguardFile('proguard-android.txt'), 'proguard-rules.pro'
        }
    }
}
```

---

## Part 2: Laravel 11 API Backend

### Project Setup

```bash
composer create-project laravel/laravel pos-tracker-api
cd pos-tracker-api
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### Folder Structure

```
/pos-tracker-api
  app/
    Http/
      Controllers/
        Api/
          LocationController.php
          DeviceController.php
          AuthController.php
          StatsController.php
      Middleware/
        ApiKeyMiddleware.php
    Models/
      Device.php
      LocationPing.php
      User.php
  database/
    migrations/
      xxxx_create_devices_table.php
      xxxx_create_location_pings_table.php
  routes/
    api.php
  .env
```

### Environment Variables (.env)

```env
APP_NAME="POS Tracker API"
APP_ENV=production
APP_KEY=                          # generated by artisan key:generate
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_tracker
DB_USERNAME=root
DB_PASSWORD=your_db_password

FRONTEND_URL=https://your-dashboard-domain.com
SANCTUM_STATEFUL_DOMAINS=your-dashboard-domain.com
```

### Database Migrations

**Migration 1: devices table**

```php
// database/migrations/xxxx_create_devices_table.php
Schema::create('devices', function (Blueprint $table) {
    $table->id();
    $table->string('device_id')->unique();
    $table->string('device_name')->default('Unknown Device');
    $table->string('model')->nullable();
    $table->string('brand')->nullable();
    $table->string('android_version')->nullable();
    $table->decimal('last_lat', 10, 7)->nullable();
    $table->decimal('last_lng', 10, 7)->nullable();
    $table->unsignedTinyInteger('last_battery')->default(0);
    $table->decimal('last_accuracy', 8, 2)->default(0);
    $table->decimal('last_speed', 8, 2)->default(0);
    $table->timestamp('last_seen')->nullable();
    $table->boolean('is_online')->default(false);
    $table->unsignedBigInteger('total_pings')->default(0);
    $table->timestamps();
});
```

**Migration 2: location_pings table**

```php
// database/migrations/xxxx_create_location_pings_table.php
Schema::create('location_pings', function (Blueprint $table) {
    $table->id();
    $table->string('device_id');
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->decimal('accuracy', 8, 2)->default(0);
    $table->decimal('speed', 8, 2)->default(0);
    $table->unsignedTinyInteger('battery')->default(0);
    $table->timestamp('pinged_at')->nullable();
    $table->timestamps();

    $table->index('device_id');
    $table->index('pinged_at');
    $table->foreign('device_id')->references('device_id')->on('devices')->onDelete('cascade');
});
```

Run migrations:
```bash
php artisan migrate
```

### Models

**app/Models/Device.php**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Device extends Model
{
    protected $fillable = [
        'device_id', 'device_name', 'model', 'brand', 'android_version',
        'last_lat', 'last_lng', 'last_battery', 'last_accuracy', 'last_speed',
        'last_seen', 'is_online', 'total_pings',
    ];

    protected $casts = [
        'last_lat'     => 'float',
        'last_lng'     => 'float',
        'last_seen'    => 'datetime',
        'is_online'    => 'boolean',
        'total_pings'  => 'integer',
        'last_battery' => 'integer',
    ];

    public function pings()
    {
        return $this->hasMany(LocationPing::class, 'device_id', 'device_id');
    }

    // Dynamically compute online status based on last_seen
    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen && $this->last_seen->greaterThan(Carbon::now()->subMinutes(5));
    }

    public function getGoogleMapsUrlAttribute(): string
    {
        if ($this->last_lat && $this->last_lng) {
            return "https://www.google.com/maps?q={$this->last_lat},{$this->last_lng}&z=17";
        }
        return '';
    }
}
```

**app/Models/LocationPing.php**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationPing extends Model
{
    protected $fillable = [
        'device_id', 'lat', 'lng', 'accuracy', 'speed', 'battery', 'pinged_at',
    ];

    protected $casts = [
        'lat'       => 'float',
        'lng'       => 'float',
        'pinged_at' => 'datetime',
    ];

    public function getGoogleMapsUrlAttribute(): string
    {
        return "https://www.google.com/maps?q={$this->lat},{$this->lng}&z=17";
    }
}
```

### Controllers

**app/Http/Controllers/Api/LocationController.php**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\LocationPing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LocationController extends Controller
{
    /**
     * POST /api/location
     * Called by the Android APK every 60 seconds
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id'   => 'required|string|max:255',
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
            'battery'     => 'nullable|integer|between:0,100',
            'accuracy'    => 'nullable|numeric',
            'speed'       => 'nullable|numeric',
            'device_name' => 'nullable|string|max:255',
            'model'       => 'nullable|string|max:255',
            'brand'       => 'nullable|string|max:255',
            'android_version' => 'nullable|string|max:50',
            'timestamp'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $pingTime = Carbon::now();
        if ($request->filled('timestamp')) {
            try {
                $pingTime = Carbon::parse($request->timestamp);
            } catch (\Exception $e) {
                $pingTime = Carbon::now();
            }
        }

        // Save the ping to history
        LocationPing::create([
            'device_id' => $request->device_id,
            'lat'       => $request->lat,
            'lng'       => $request->lng,
            'accuracy'  => $request->accuracy ?? 0,
            'speed'     => $request->speed ?? 0,
            'battery'   => $request->battery ?? 0,
            'pinged_at' => $pingTime,
        ]);

        // Upsert device record with latest data
        Device::updateOrCreate(
            ['device_id' => $request->device_id],
            [
                'device_name'     => $request->device_name ?? 'Unknown Device',
                'model'           => $request->model,
                'brand'           => $request->brand,
                'android_version' => $request->android_version,
                'last_lat'        => $request->lat,
                'last_lng'        => $request->lng,
                'last_battery'    => $request->battery ?? 0,
                'last_accuracy'   => $request->accuracy ?? 0,
                'last_speed'      => $request->speed ?? 0,
                'last_seen'       => Carbon::now(),
                'is_online'       => true,
                'total_pings'     => \DB::raw('total_pings + 1'),
            ]
        );

        return response()->json(['status' => 'ok', 'message' => 'Location received'], 200);
    }
}
```

**app/Http/Controllers/Api/DeviceController.php**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\LocationPing;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeviceController extends Controller
{
    /**
     * GET /api/devices
     * Returns all devices with online status computed
     */
    public function index()
    {
        $devices = Device::orderByDesc('last_seen')->get()->map(function ($device) {
            $data = $device->toArray();
            $data['is_online'] = $device->is_online; // uses dynamic attribute
            $data['google_maps_url'] = $device->google_maps_url;
            $data['google_maps_directions_url'] = $device->last_lat
                ? "https://www.google.com/maps/dir/?api=1&destination={$device->last_lat},{$device->last_lng}"
                : '';
            return $data;
        });

        return response()->json($devices);
    }

    /**
     * GET /api/devices/{device_id}
     * Single device details
     */
    public function show(string $deviceId)
    {
        $device = Device::where('device_id', $deviceId)->firstOrFail();
        $data = $device->toArray();
        $data['is_online'] = $device->is_online;
        $data['google_maps_url'] = $device->google_maps_url;
        return response()->json($data);
    }

    /**
     * GET /api/devices/{device_id}/history
     * Location history (route trail) for a device
     * Query params: limit (default 100), from (ISO date), to (ISO date)
     */
    public function history(Request $request, string $deviceId)
    {
        $query = LocationPing::where('device_id', $deviceId)
            ->orderByDesc('pinged_at');

        if ($request->filled('from')) {
            $query->where('pinged_at', '>=', Carbon::parse($request->from));
        }
        if ($request->filled('to')) {
            $query->where('pinged_at', '<=', Carbon::parse($request->to));
        }

        $limit = min((int) $request->get('limit', 100), 500);
        $pings = $query->limit($limit)->get()->map(function ($ping) {
            return [
                'lat'       => $ping->lat,
                'lng'       => $ping->lng,
                'battery'   => $ping->battery,
                'accuracy'  => $ping->accuracy,
                'speed'     => $ping->speed,
                'pinged_at' => $ping->pinged_at,
                'google_maps_url' => $ping->google_maps_url,
            ];
        });

        return response()->json($pings);
    }
}
```

**app/Http/Controllers/Api/StatsController.php**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\LocationPing;
use Carbon\Carbon;

class StatsController extends Controller
{
    public function index()
    {
        $fiveMinutesAgo = Carbon::now()->subMinutes(5);

        return response()->json([
            'total_devices'   => Device::count(),
            'online_devices'  => Device::where('last_seen', '>=', $fiveMinutesAgo)->count(),
            'offline_devices' => Device::where(function ($q) use ($fiveMinutesAgo) {
                $q->where('last_seen', '<', $fiveMinutesAgo)->orWhereNull('last_seen');
            })->count(),
            'total_pings'     => LocationPing::count(),
            'pings_today'     => LocationPing::whereDate('pinged_at', today())->count(),
        ]);
    }
}
```

**app/Http/Controllers/Api/AuthController.php**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('dashboard-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
```

### Routes (routes/api.php)

```php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\AuthController;

// ── Public route: APK posts location here (no auth needed) ──
Route::post('/location', [LocationController::class, 'store']);

// ── Auth routes ──
Route::post('/auth/login',  [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // ── Protected dashboard routes ──
    Route::get('/devices',                        [DeviceController::class, 'index']);
    Route::get('/devices/{device_id}',            [DeviceController::class, 'show']);
    Route::get('/devices/{device_id}/history',    [DeviceController::class, 'history']);
    Route::get('/stats',                          [StatsController::class, 'index']);
});
```

### CORS Configuration (config/cors.php)

```php
return [
    'paths'               => ['api/*'],
    'allowed_methods'     => ['*'],
    'allowed_origins'     => [env('FRONTEND_URL', '*')],
    'allowed_headers'     => ['*'],
    'exposed_headers'     => [],
    'max_age'             => 0,
    'supports_credentials'=> true,
];
```

### Seed Owner Account

```bash
php artisan tinker
# In tinker:
\App\Models\User::create([
    'name'     => 'POS Owner',
    'email'    => 'owner@yourdomain.com',
    'password' => bcrypt('your_secure_password_here'),
]);
exit
```

### Laravel Deployment

```bash
# On your server (Ubuntu/Debian)
sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl php8.2-mbstring
sudo apt install mysql-server nginx composer

# Create DB
mysql -u root -p
CREATE DATABASE pos_tracker;
CREATE USER 'pos_user'@'localhost' IDENTIFIED BY 'strongpassword';
GRANT ALL PRIVILEGES ON pos_tracker.* TO 'pos_user'@'localhost';
FLUSH PRIVILEGES;

# Deploy
cd /var/www
git clone <your-repo> pos-api
cd pos-api
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env with your DB credentials
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# Permissions
chown -R www-data:www-data /var/www/pos-api
chmod -R 775 storage bootstrap/cache

# Nginx config (save to /etc/nginx/sites-available/pos-api)
server {
    listen 80;
    server_name your-api-domain.com;
    root /var/www/pos-api/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}

# Enable site and reload
sudo ln -s /etc/nginx/sites-available/pos-api /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# Setup SSL with Certbot
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-api-domain.com
```

---

## Part 3: React Dashboard with Google Maps

### Project Setup

```bash
npx create-react-app pos-dashboard --template typescript
cd pos-dashboard
npm install @react-google-maps/api axios date-fns
npm install @types/google.maps
```

### Environment Variables (.env)

```env
REACT_APP_API_URL=https://your-api-domain.com
REACT_APP_GOOGLE_MAPS_API_KEY=YOUR_GOOGLE_MAPS_API_KEY_HERE
```

> **Google Maps API Key Setup:**
> 1. Go to https://console.cloud.google.com
> 2. Create a new project → Enable "Maps JavaScript API"
> 3. Credentials → Create API Key → Restrict to your dashboard domain
> 4. Paste key into `.env` above

### File Structure

```
/pos-dashboard/src
  api/
    client.ts
    devices.ts
    auth.ts
  components/
    Login.tsx
    Dashboard.tsx
    DeviceMap.tsx
    DeviceSidebar.tsx
    DeviceInfoPanel.tsx
    StatsBar.tsx
  hooks/
    useDevices.ts
    useAuth.ts
  types/
    index.ts
  App.tsx
  App.css
```

### Types (src/types/index.ts)

```ts
export interface Device {
  id: number;
  device_id: string;
  device_name: string;
  model: string;
  brand: string;
  android_version: string;
  last_lat: number;
  last_lng: number;
  last_battery: number;
  last_accuracy: number;
  last_speed: number;
  last_seen: string;
  is_online: boolean;
  total_pings: number;
  google_maps_url: string;
  google_maps_directions_url: string;
}

export interface LocationPing {
  lat: number;
  lng: number;
  battery: number;
  accuracy: number;
  speed: number;
  pinged_at: string;
  google_maps_url: string;
}

export interface Stats {
  total_devices: number;
  online_devices: number;
  offline_devices: number;
  total_pings: number;
  pings_today: number;
}
```

### API Client (src/api/client.ts)

```ts
import axios from 'axios';

const client = axios.create({
  baseURL: process.env.REACT_APP_API_URL,
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  withCredentials: true,
});

// Attach Bearer token if present
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 → redirect to login
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default client;
```

### Auth API (src/api/auth.ts)

```ts
import client from './client';

export const login = async (email: string, password: string) => {
  const res = await client.post('/api/auth/login', { email, password });
  localStorage.setItem('auth_token', res.data.token);
  return res.data;
};

export const logout = async () => {
  await client.post('/api/auth/logout');
  localStorage.removeItem('auth_token');
};

export const getMe = async () => {
  const res = await client.get('/api/auth/me');
  return res.data;
};
```

### Devices API (src/api/devices.ts)

```ts
import client from './client';

export const getDevices = async () => {
  const res = await client.get('/api/devices');
  return res.data;
};

export const getStats = async () => {
  const res = await client.get('/api/stats');
  return res.data;
};

export const getDeviceHistory = async (deviceId: string, limit = 100) => {
  const res = await client.get(`/api/devices/${deviceId}/history?limit=${limit}`);
  return res.data;
};
```

### Login Component (src/components/Login.tsx)

```tsx
import React, { useState } from 'react';
import { login } from '../api/auth';

interface Props {
  onLogin: () => void;
}

export default function Login({ onLogin }: Props) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      await login(email, password);
      onLogin();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Invalid credentials');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={styles.wrapper}>
      <div style={styles.card}>
        <h1 style={styles.title}>🗺 POS Device Tracker</h1>
        <p style={styles.subtitle}>Sign in to your owner dashboard</p>
        <form onSubmit={handleSubmit}>
          <input
            style={styles.input}
            type="email"
            placeholder="Email address"
            value={email}
            onChange={e => setEmail(e.target.value)}
            required
          />
          <input
            style={styles.input}
            type="password"
            placeholder="Password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            required
          />
          {error && <p style={styles.error}>{error}</p>}
          <button style={styles.button} type="submit" disabled={loading}>
            {loading ? 'Signing in...' : 'Sign In'}
          </button>
        </form>
      </div>
    </div>
  );
}

const styles: Record<string, React.CSSProperties> = {
  wrapper:  { display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh', background: '#0d1117' },
  card:     { background: '#161b22', border: '1px solid #30363d', borderRadius: 16, padding: 40, width: 380 },
  title:    { color: '#58a6ff', fontSize: 22, fontWeight: 'bold', marginBottom: 6, textAlign: 'center' },
  subtitle: { color: '#8b949e', fontSize: 14, textAlign: 'center', marginBottom: 28 },
  input:    { width: '100%', padding: '10px 14px', marginBottom: 14, background: '#21262d', border: '1px solid #30363d', borderRadius: 8, color: '#e6edf3', fontSize: 14, boxSizing: 'border-box' },
  button:   { width: '100%', padding: '12px', background: '#238636', border: 'none', borderRadius: 8, color: '#fff', fontWeight: 'bold', fontSize: 15, cursor: 'pointer' },
  error:    { color: '#f85149', fontSize: 13, marginBottom: 12 },
};
```

### DeviceMap Component (src/components/DeviceMap.tsx)

This is the **core Google Maps component** with all device markers and route history:

```tsx
import React, { useCallback, useRef } from 'react';
import {
  GoogleMap,
  useJsApiLoader,
  Marker,
  InfoWindow,
  Polyline,
  OverlayView,
} from '@react-google-maps/api';
import { Device, LocationPing } from '../types';
import { formatDistanceToNow } from 'date-fns';

interface Props {
  devices: Device[];
  selectedDevice: Device | null;
  history: LocationPing[];
  onDeviceClick: (device: Device) => void;
  onClose: () => void;
}

const containerStyle = { width: '100%', height: '100%' };
const defaultCenter = { lat: 0, lng: 0 };

// Custom SVG marker icons
const onlineMarkerIcon = {
  path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
  fillColor: '#3fb950',
  fillOpacity: 1,
  strokeColor: '#ffffff',
  strokeWeight: 2,
  scale: 1.8,
  anchor: { x: 12, y: 22 } as google.maps.Point,
};

const offlineMarkerIcon = {
  ...onlineMarkerIcon,
  fillColor: '#f85149',
};

export default function DeviceMap({ devices, selectedDevice, history, onDeviceClick, onClose }: Props) {
  const mapRef = useRef<google.maps.Map | null>(null);

  const { isLoaded } = useJsApiLoader({
    id: 'google-map-script',
    googleMapsApiKey: process.env.REACT_APP_GOOGLE_MAPS_API_KEY!,
  });

  const onMapLoad = useCallback((map: google.maps.Map) => {
    mapRef.current = map;
    // Fit bounds to show all devices if there are any
    if (devices.length > 0) {
      const bounds = new google.maps.LatLngBounds();
      devices.forEach(d => {
        if (d.last_lat && d.last_lng) {
          bounds.extend({ lat: d.last_lat, lng: d.last_lng });
        }
      });
      map.fitBounds(bounds);
    }
  }, [devices]);

  const openInGoogleMaps = (device: Device) => {
    window.open(device.google_maps_url, '_blank');
  };

  const getDirections = (device: Device) => {
    window.open(device.google_maps_directions_url, '_blank');
  };

  const streetView = (device: Device) => {
    const url = `https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${device.last_lat},${device.last_lng}`;
    window.open(url, '_blank');
  };

  if (!isLoaded) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100%', background: '#0d1117', color: '#8b949e' }}>
        Loading Google Maps...
      </div>
    );
  }

  return (
    <GoogleMap
      mapContainerStyle={containerStyle}
      center={defaultCenter}
      zoom={2}
      onLoad={onMapLoad}
      options={{
        styles: darkMapStyles,       // Dark mode map styles (see below)
        disableDefaultUI: false,
        zoomControl: true,
        mapTypeControl: true,
        streetViewControl: true,
        fullscreenControl: true,
        mapTypeControlOptions: {
          style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
          mapTypeIds: ['roadmap', 'satellite', 'hybrid'],
        },
      }}
    >
      {/* Device Markers */}
      {devices.map(device => (
        device.last_lat && device.last_lng ? (
          <Marker
            key={device.device_id}
            position={{ lat: device.last_lat, lng: device.last_lng }}
            icon={device.is_online ? onlineMarkerIcon : offlineMarkerIcon}
            title={device.device_name}
            onClick={() => onDeviceClick(device)}
          />
        ) : null
      ))}

      {/* Selected Device Info Window */}
      {selectedDevice && selectedDevice.last_lat && (
        <InfoWindow
          position={{ lat: selectedDevice.last_lat, lng: selectedDevice.last_lng }}
          onCloseClick={onClose}
          options={{ maxWidth: 320 }}
        >
          <div style={infoWindowStyles.container}>
            <div style={infoWindowStyles.header}>
              <span style={{ ...infoWindowStyles.dot, background: selectedDevice.is_online ? '#3fb950' : '#f85149' }} />
              <strong style={infoWindowStyles.name}>{selectedDevice.device_name}</strong>
            </div>

            <div style={infoWindowStyles.grid}>
              <div style={infoWindowStyles.item}>
                <span style={infoWindowStyles.label}>Model</span>
                <span style={infoWindowStyles.value}>{selectedDevice.model || 'Unknown'} ({selectedDevice.brand})</span>
              </div>
              <div style={infoWindowStyles.item}>
                <span style={infoWindowStyles.label}>Battery</span>
                <span style={{
                  ...infoWindowStyles.value,
                  color: selectedDevice.last_battery > 20 ? '#3fb950' : '#f85149'
                }}>
                  🔋 {selectedDevice.last_battery}%
                </span>
              </div>
              <div style={infoWindowStyles.item}>
                <span style={infoWindowStyles.label}>Coordinates</span>
                <span style={infoWindowStyles.value}>
                  {selectedDevice.last_lat.toFixed(6)}, {selectedDevice.last_lng.toFixed(6)}
                </span>
              </div>
              <div style={infoWindowStyles.item}>
                <span style={infoWindowStyles.label}>Accuracy</span>
                <span style={infoWindowStyles.value}>±{selectedDevice.last_accuracy}m</span>
              </div>
              <div style={infoWindowStyles.item}>
                <span style={infoWindowStyles.label}>Status</span>
                <span style={infoWindowStyles.value}>
                  {selectedDevice.is_online ? '🟢 Online' : '🔴 Offline'}
                </span>
              </div>
              <div style={infoWindowStyles.item}>
                <span style={infoWindowStyles.label}>Last Seen</span>
                <span style={infoWindowStyles.value}>
                  {formatDistanceToNow(new Date(selectedDevice.last_seen))} ago
                </span>
              </div>
              <div style={infoWindowStyles.item}>
                <span style={infoWindowStyles.label}>Total Pings</span>
                <span style={infoWindowStyles.value}>{selectedDevice.total_pings}</span>
              </div>
            </div>

            {/* Google Maps Action Buttons */}
            <div style={infoWindowStyles.actions}>
              <button
                style={infoWindowStyles.btnPrimary}
                onClick={() => openInGoogleMaps(selectedDevice)}
              >
                📍 Open in Google Maps
              </button>
              <button
                style={infoWindowStyles.btnSecondary}
                onClick={() => getDirections(selectedDevice)}
              >
                🧭 Get Directions
              </button>
              <button
                style={infoWindowStyles.btnSecondary}
                onClick={() => streetView(selectedDevice)}
              >
                🏙 Street View
              </button>
            </div>
          </div>
        </InfoWindow>
      )}

      {/* Route History Trail (Polyline) */}
      {history.length > 1 && (
        <Polyline
          path={history.map(p => ({ lat: p.lat, lng: p.lng }))}
          options={{
            strokeColor: '#58a6ff',
            strokeOpacity: 0.8,
            strokeWeight: 3,
            geodesic: true,
          }}
        />
      )}

      {/* History Dots */}
      {history.map((ping, idx) => (
        idx % 5 === 0 ? (  // Show every 5th ping to avoid clutter
          <Marker
            key={`hist-${idx}`}
            position={{ lat: ping.lat, lng: ping.lng }}
            icon={{
              path: google.maps.SymbolPath.CIRCLE,
              scale: 4,
              fillColor: '#58a6ff',
              fillOpacity: 0.7,
              strokeColor: '#ffffff',
              strokeWeight: 1,
            }}
          />
        ) : null
      ))}
    </GoogleMap>
  );
}

// InfoWindow Inline Styles
const infoWindowStyles: Record<string, React.CSSProperties> = {
  container:  { fontFamily: 'system-ui, sans-serif', minWidth: 260 },
  header:     { display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12, paddingBottom: 10, borderBottom: '1px solid #e5e7eb' },
  dot:        { width: 10, height: 10, borderRadius: '50%', flexShrink: 0 },
  name:       { fontSize: 15, color: '#111827' },
  grid:       { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, marginBottom: 14 },
  item:       { display: 'flex', flexDirection: 'column', gap: 2 },
  label:      { fontSize: 10, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em' },
  value:      { fontSize: 12, color: '#374151', fontWeight: 600 },
  actions:    { display: 'flex', flexDirection: 'column', gap: 6 },
  btnPrimary: { padding: '8px 12px', background: '#1a73e8', color: '#fff', border: 'none', borderRadius: 6, cursor: 'pointer', fontSize: 13, fontWeight: 600 },
  btnSecondary: { padding: '8px 12px', background: '#f3f4f6', color: '#374151', border: '1px solid #d1d5db', borderRadius: 6, cursor: 'pointer', fontSize: 13 },
};

// Google Maps Dark Mode Styles
const darkMapStyles: google.maps.MapTypeStyle[] = [
  { elementType: 'geometry',           stylers: [{ color: '#212121' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#212121' }] },
  { elementType: 'labels.text.fill',   stylers: [{ color: '#757575' }] },
  { featureType: 'road',               elementType: 'geometry',           stylers: [{ color: '#2c2c2c' }] },
  { featureType: 'road',               elementType: 'labels.text.fill',   stylers: [{ color: '#8a8a8a' }] },
  { featureType: 'water',              elementType: 'geometry',           stylers: [{ color: '#000000' }] },
  { featureType: 'water',              elementType: 'labels.text.fill',   stylers: [{ color: '#3d3d3d' }] },
  { featureType: 'poi',                elementType: 'geometry',           stylers: [{ color: '#1c1c1c' }] },
  { featureType: 'poi.park',           elementType: 'geometry',           stylers: [{ color: '#181818' }] },
  { featureType: 'transit',            elementType: 'geometry',           stylers: [{ color: '#2f2f2f' }] },
  { featureType: 'administrative',     elementType: 'geometry',           stylers: [{ color: '#333333' }] },
];
```

### StatsBar Component (src/components/StatsBar.tsx)

```tsx
import React from 'react';
import { Stats } from '../types';

interface Props {
  stats: Stats | null;
  lastUpdate: string;
  onLogout: () => void;
  userName: string;
}

export default function StatsBar({ stats, lastUpdate, onLogout, userName }: Props) {
  return (
    <header style={s.header}>
      <div style={s.left}>
        <h1 style={s.title}>🗺 POS Device Tracker</h1>
      </div>
      <div style={s.center}>
        {stats && (
          <>
            <div style={s.stat}>
              <span style={s.statNum}>{stats.total_devices}</span>
              <span style={s.statLabel}>Total</span>
            </div>
            <div style={{ ...s.stat, ...s.online }}>
              <span style={s.statNum}>{stats.online_devices}</span>
              <span style={s.statLabel}>Online</span>
            </div>
            <div style={{ ...s.stat, ...s.offline }}>
              <span style={s.statNum}>{stats.offline_devices}</span>
              <span style={s.statLabel}>Offline</span>
            </div>
            <div style={s.stat}>
              <span style={s.statNum}>{stats.pings_today}</span>
              <span style={s.statLabel}>Pings Today</span>
            </div>
          </>
        )}
      </div>
      <div style={s.right}>
        <span style={s.update}>🔄 {lastUpdate}</span>
        <span style={s.user}>👤 {userName}</span>
        <button style={s.logout} onClick={onLogout}>Logout</button>
      </div>
    </header>
  );
}

const s: Record<string, React.CSSProperties> = {
  header:    { background: '#161b22', padding: '10px 20px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderBottom: '1px solid #30363d', height: 60, flexShrink: 0 },
  left:      { flex: '0 0 auto' },
  title:     { color: '#58a6ff', fontSize: 18, fontWeight: 'bold', margin: 0 },
  center:    { display: 'flex', gap: 8, alignItems: 'center' },
  stat:      { background: '#21262d', borderRadius: 8, padding: '4px 14px', textAlign: 'center', minWidth: 70 },
  online:    { borderTop: '2px solid #3fb950' },
  offline:   { borderTop: '2px solid #f85149' },
  statNum:   { display: 'block', fontSize: 18, fontWeight: 'bold', color: '#e6edf3' },
  statLabel: { display: 'block', fontSize: 10, color: '#8b949e', textTransform: 'uppercase', letterSpacing: 1 },
  right:     { display: 'flex', alignItems: 'center', gap: 14 },
  update:    { color: '#8b949e', fontSize: 12 },
  user:      { color: '#e6edf3', fontSize: 13 },
  logout:    { padding: '6px 14px', background: 'transparent', border: '1px solid #30363d', borderRadius: 6, color: '#8b949e', cursor: 'pointer', fontSize: 13 },
};
```

### DeviceSidebar Component (src/components/DeviceSidebar.tsx)

```tsx
import React, { useState } from 'react';
import { Device } from '../types';
import { formatDistanceToNow } from 'date-fns';

interface Props {
  devices: Device[];
  selectedDevice: Device | null;
  onSelect: (device: Device) => void;
}

export default function DeviceSidebar({ devices, selectedDevice, onSelect }: Props) {
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<'all' | 'online' | 'offline'>('all');

  const filtered = devices.filter(d => {
    const matchSearch =
      d.device_name.toLowerCase().includes(search.toLowerCase()) ||
      d.device_id.toLowerCase().includes(search.toLowerCase()) ||
      d.model?.toLowerCase().includes(search.toLowerCase());
    const matchFilter =
      filter === 'all' ? true :
      filter === 'online' ? d.is_online :
      !d.is_online;
    return matchSearch && matchFilter;
  });

  return (
    <aside style={s.sidebar}>
      <div style={s.controls}>
        <input
          style={s.search}
          placeholder="🔍 Search devices..."
          value={search}
          onChange={e => setSearch(e.target.value)}
        />
        <div style={s.filters}>
          {(['all', 'online', 'offline'] as const).map(f => (
            <button
              key={f}
              style={{ ...s.filter, ...(filter === f ? s.filterActive : {}) }}
              onClick={() => setFilter(f)}
            >
              {f === 'online' ? '🟢' : f === 'offline' ? '🔴' : '📱'} {f}
            </button>
          ))}
        </div>
      </div>

      <div style={s.list}>
        {filtered.length === 0 && (
          <p style={s.empty}>No devices found</p>
        )}
        {filtered.map(device => (
          <div
            key={device.device_id}
            style={{
              ...s.card,
              ...(selectedDevice?.device_id === device.device_id ? s.selected : {}),
              ...(!device.is_online ? s.offline : {}),
            }}
            onClick={() => onSelect(device)}
          >
            <div style={s.cardTop}>
              <span style={{ ...s.dot, background: device.is_online ? '#3fb950' : '#f85149' }} />
              <span style={s.name}>{device.device_name}</span>
              <span style={s.battery}>🔋{device.last_battery}%</span>
            </div>
            <div style={s.model}>{device.model} · {device.brand}</div>
            <div style={s.coords}>
              📍 {device.last_lat?.toFixed(4)}, {device.last_lng?.toFixed(4)}
            </div>
            <div style={s.time}>
              🕐 {device.last_seen ? formatDistanceToNow(new Date(device.last_seen)) + ' ago' : 'Never'}
            </div>
          </div>
        ))}
      </div>
    </aside>
  );
}

const s: Record<string, React.CSSProperties> = {
  sidebar:      { width: 300, background: '#161b22', borderRight: '1px solid #30363d', display: 'flex', flexDirection: 'column', overflow: 'hidden', flexShrink: 0 },
  controls:     { padding: '12px 12px 8px', borderBottom: '1px solid #30363d' },
  search:       { width: '100%', padding: '8px 12px', background: '#21262d', border: '1px solid #30363d', borderRadius: 8, color: '#e6edf3', fontSize: 13, boxSizing: 'border-box', marginBottom: 8 },
  filters:      { display: 'flex', gap: 4 },
  filter:       { flex: 1, padding: '5px 4px', background: '#21262d', border: '1px solid #30363d', borderRadius: 6, color: '#8b949e', cursor: 'pointer', fontSize: 11, textTransform: 'capitalize' },
  filterActive: { background: '#1c2d40', borderColor: '#58a6ff', color: '#58a6ff' },
  list:         { flex: 1, overflowY: 'auto', padding: 10 },
  empty:        { color: '#8b949e', textAlign: 'center', marginTop: 40, fontSize: 13 },
  card:         { padding: 12, marginBottom: 8, background: '#21262d', borderRadius: 10, cursor: 'pointer', border: '1px solid transparent', transition: 'all 0.15s' },
  selected:     { borderColor: '#58a6ff', background: '#1c2d40' },
  offline:      { opacity: 0.55 },
  cardTop:      { display: 'flex', alignItems: 'center', gap: 6, marginBottom: 5 },
  dot:          { width: 8, height: 8, borderRadius: '50%', flexShrink: 0 },
  name:         { color: '#e6edf3', fontSize: 14, fontWeight: 600, flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
  battery:      { color: '#8b949e', fontSize: 12, flexShrink: 0 },
  model:        { color: '#58a6ff', fontSize: 11, marginBottom: 3 },
  coords:       { color: '#8b949e', fontSize: 11, marginBottom: 2 },
  time:         { color: '#8b949e', fontSize: 11 },
};
```

### Dashboard Component (src/components/Dashboard.tsx)

```tsx
import React, { useEffect, useState, useCallback } from 'react';
import DeviceMap from './DeviceMap';
import DeviceSidebar from './DeviceSidebar';
import StatsBar from './StatsBar';
import { getDevices, getStats, getDeviceHistory } from '../api/devices';
import { logout } from '../api/auth';
import { Device, LocationPing, Stats } from '../types';

interface Props {
  user: { name: string; email: string };
  onLogout: () => void;
}

export default function Dashboard({ user, onLogout }: Props) {
  const [devices, setDevices] = useState<Device[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [selectedDevice, setSelectedDevice] = useState<Device | null>(null);
  const [history, setHistory] = useState<LocationPing[]>([]);
  const [lastUpdate, setLastUpdate] = useState('');

  const fetchAll = useCallback(async () => {
    try {
      const [devData, statsData] = await Promise.all([getDevices(), getStats()]);
      setDevices(devData);
      setStats(statsData);
      setLastUpdate(new Date().toLocaleTimeString());
    } catch (err) {
      console.error('Fetch error', err);
    }
  }, []);

  useEffect(() => {
    fetchAll();
    const interval = setInterval(fetchAll, 30000); // Auto-refresh every 30s
    return () => clearInterval(interval);
  }, [fetchAll]);

  const handleDeviceSelect = async (device: Device) => {
    setSelectedDevice(device);
    try {
      const hist = await getDeviceHistory(device.device_id, 100);
      setHistory(hist);
    } catch {}
  };

  const handleLogout = async () => {
    await logout();
    onLogout();
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100vh', background: '#0d1117' }}>
      <StatsBar
        stats={stats}
        lastUpdate={lastUpdate}
        onLogout={handleLogout}
        userName={user.name}
      />
      <div style={{ display: 'flex', flex: 1, overflow: 'hidden' }}>
        <DeviceSidebar
          devices={devices}
          selectedDevice={selectedDevice}
          onSelect={handleDeviceSelect}
        />
        <main style={{ flex: 1, position: 'relative' }}>
          <DeviceMap
            devices={devices}
            selectedDevice={selectedDevice}
            history={history}
            onDeviceClick={handleDeviceSelect}
            onClose={() => { setSelectedDevice(null); setHistory([]); }}
          />
        </main>
      </div>
    </div>
  );
}
```

### App.tsx — Root with Auth Guard

```tsx
import React, { useState, useEffect } from 'react';
import Login from './components/Login';
import Dashboard from './components/Dashboard';
import { getMe } from './api/auth';

export default function App() {
  const [user, setUser] = useState<{ name: string; email: string } | null>(null);
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      getMe()
        .then(setUser)
        .catch(() => localStorage.removeItem('auth_token'))
        .finally(() => setChecking(false));
    } else {
      setChecking(false);
    }
  }, []);

  if (checking) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh', background: '#0d1117', color: '#8b949e', fontSize: 16 }}>
        Loading...
      </div>
    );
  }

  if (!user) {
    return <Login onLogin={() => getMe().then(setUser)} />;
  }

  return <Dashboard user={user} onLogout={() => setUser(null)} />;
}
```

### index.css (global reset)

```css
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0d1117; }
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #161b22; }
::-webkit-scrollbar-thumb { background: #30363d; border-radius: 3px; }
input:focus { outline: none; border-color: #58a6ff !important; }
```

### Dashboard Deployment

```bash
cd pos-dashboard
npm run build
# Deploy /build folder to Netlify, Vercel, Nginx static server, or any CDN

# Or with Nginx:
# Copy /build to /var/www/pos-dashboard/
# Nginx config:
server {
    listen 80;
    server_name your-dashboard-domain.com;
    root /var/www/pos-dashboard;
    index index.html;
    location / {
        try_files $uri /index.html;    # SPA fallback
    }
}
```

---

## ADB: Sideload APK onto POS Device

```bash
# Enable Developer Options: tap "Build Number" 7x in Settings > About Phone
# Enable USB Debugging in Developer Options
# Connect POS device via USB

adb devices                          # Confirm device appears
adb install -r app-release.apk      # Install APK (-r = replace if exists)

# Grant background location permission programmatically
adb shell pm grant com.postracker.device android.permission.ACCESS_BACKGROUND_LOCATION

# Launch app once so it can request remaining permissions
adb shell am start -n com.postracker.device/.MainActivity

# Verify it's running
adb shell dumpsys activity services | grep postracker
```

---

## Final Deliverables Kiro Must Produce

| # | Deliverable | Description |
|---|---|---|
| 1 | `/POSTracker/` | Full React Native project source |
| 2 | `/POSTracker/android/app/build/outputs/apk/release/app-release.apk` | **Signed APK — ready to sideload** |
| 3 | `/pos-tracker-api/` | Full Laravel 11 project |
| 4 | `/pos-dashboard/` | Full React dashboard with Google Maps |
| 5 | `/pos-dashboard/build/` | Production build of dashboard |
| 6 | `/README.md` | Full setup, deployment, and sideload guide |

---

## Critical Notes for Kiro

- **API URL**: Replace all `YOUR_LARAVEL_DOMAIN.com` instances in the APK with the actual deployed Laravel URL before building the APK — this must be a live HTTPS URL
- **Google Maps Key**: Replace `YOUR_GOOGLE_MAPS_API_KEY_HERE` in the dashboard `.env` — enable "Maps JavaScript API" in Google Cloud Console, restrict to dashboard domain
- **Laravel Sanctum**: The dashboard uses Sanctum Bearer token auth — make sure `EnsureFrontendRequestsAreStateful` middleware is applied, and CORS is configured for the dashboard domain
- **Foreground Service**: APK must use foreground service (`stopOnTerminate: false`, `startOnBoot: true`, `enableHeadless: true`) so it is never killed by Android
- **Keystore**: Do NOT lose the `postracker.keystore` file — it is required for all future APK updates
- **Google Maps Info Window**: The InfoWindow popup must include working "Open in Google Maps", "Get Directions", and "Street View" buttons that open in a new tab
- **Dark Map Theme**: Apply the dark map style array to `GoogleMap options.styles` so the map matches the dark dashboard theme
- **Route Trail**: When a device is selected from the sidebar, fetch its history from `/api/devices/{id}/history` and draw a blue Polyline on the map showing its route
- **Auto-refresh**: Dashboard must poll the API every 30 seconds automatically to show live device positions without manual page reload
- **Online/Offline Logic**: A device is considered offline if its `last_seen` timestamp is more than 5 minutes ago — this is computed server-side in Laravel and returned in the API response
