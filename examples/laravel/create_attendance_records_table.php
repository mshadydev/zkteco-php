<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create attendance_records table
 * 
 * This migration creates a dedicated table for storing ZKTeco attendance records.
 * Run with: php artisan migrate
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            
            // User identification
            $table->string('user_id')->index();
            $table->unsignedInteger('zkteco_uid')->nullable();
            
            // Attendance details
            $table->timestamp('timestamp');
            $table->date('date')->index();
            $table->time('time');
            $table->tinyInteger('status')->comment('0=Check-Out, 1=Check-In, 2=Break-Out, 3=Break-In, 4=OT-In, 5=OT-Out');
            $table->tinyInteger('punch_type')->default(1);
            
            // Duplicate prevention
            $table->string('record_hash', 32)->unique()->comment('MD5 hash to prevent duplicate records');
            
            // Device identification
            $table->string('device_ip')->nullable();
            $table->string('device_name')->nullable();
            
            // Synchronization tracking
            $table->timestamp('last_sync_at')->nullable();
            $table->boolean('is_processed')->default(false);
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'date']);
            $table->index(['timestamp']);
            $table->index(['status']);
            $table->index(['is_processed']);
            $table->index(['last_sync_at']);
            
            // Foreign key constraint (optional - depends on your users table structure)
            // $table->foreign('user_id')->references('zkteco_user_id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};