<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('brand')->nullable();
            $table->string('android_version')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->integer('battery_level')->nullable();
            $table->timestamps();
            
            // Add indexes
            $table->index('device_id');
            $table->index('last_seen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
