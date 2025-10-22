<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add ZKTeco fields to users table
 * 
 * This migration adds ZKTeco-related columns to the existing users table.
 * Run with: php artisan migrate
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ZKTeco user identification
            $table->string('zkteco_user_id')->nullable()->unique()->after('id');
            $table->unsignedInteger('zkteco_uid')->nullable()->after('zkteco_user_id');
            
            // ZKTeco user attributes
            $table->tinyInteger('zkteco_privilege')->nullable()->after('name');
            $table->string('zkteco_group_id')->nullable()->after('zkteco_privilege');
            $table->unsignedBigInteger('zkteco_card')->nullable()->after('zkteco_group_id');
            
            // Synchronization tracking
            $table->timestamp('last_sync_at')->nullable()->after('updated_at');
            $table->boolean('is_zkteco_user')->default(false)->after('last_sync_at');
            
            // Indexes for better performance
            $table->index(['zkteco_user_id']);
            $table->index(['zkteco_uid']);
            $table->index(['is_zkteco_user']);
            $table->index(['last_sync_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_sync_at']);
            $table->dropIndex(['is_zkteco_user']);
            $table->dropIndex(['zkteco_uid']);
            $table->dropIndex(['zkteco_user_id']);
            
            $table->dropColumn([
                'zkteco_user_id',
                'zkteco_uid',
                'zkteco_privilege',
                'zkteco_group_id',
                'zkteco_card',
                'last_sync_at',
                'is_zkteco_user'
            ]);
        });
    }
};