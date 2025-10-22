<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | ZKTeco Device Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for ZKTeco fingerprint devices.
    | You can configure multiple devices and set connection parameters.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Device Settings
    |--------------------------------------------------------------------------
    |
    | These are the default settings used when connecting to ZKTeco devices.
    | You can override these settings for individual devices.
    |
    */

    'device_ip' => env('ZKTECO_DEVICE_IP', '192.168.1.100'),
    'port' => env('ZKTECO_PORT', 4370),
    'password' => env('ZKTECO_PASSWORD', 0),
    'timeout' => env('ZKTECO_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Multiple Device Configuration
    |--------------------------------------------------------------------------
    |
    | You can configure multiple ZKTeco devices here. Each device should have
    | a unique identifier and its own connection parameters.
    |
    */

    'devices' => [
        'main_office' => [
            'ip' => env('ZKTECO_MAIN_IP', '192.168.1.100'),
            'port' => env('ZKTECO_MAIN_PORT', 4370),
            'password' => env('ZKTECO_MAIN_PASSWORD', 0),
            'timeout' => 60,
            'description' => 'Main Office Entrance',
            'enabled' => true,
        ],
        
        'warehouse' => [
            'ip' => env('ZKTECO_WAREHOUSE_IP', '192.168.1.101'),
            'port' => env('ZKTECO_WAREHOUSE_PORT', 4370),
            'password' => env('ZKTECO_WAREHOUSE_PASSWORD', 123456),
            'timeout' => 60,
            'description' => 'Warehouse Entry Point',
            'enabled' => true,
        ],
        
        'hr_department' => [
            'ip' => env('ZKTECO_HR_IP', '192.168.1.102'),
            'port' => env('ZKTECO_HR_PORT', 4370),
            'password' => env('ZKTECO_HR_PASSWORD', 0),
            'timeout' => 60,
            'description' => 'HR Department',
            'enabled' => false, // Disabled for maintenance
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization Settings
    |--------------------------------------------------------------------------
    |
    | Configure how often data should be synchronized and other sync-related
    | settings.
    |
    */

    'sync' => [
        // How often to sync data (in minutes)
        'interval' => env('ZKTECO_SYNC_INTERVAL', 30),
        
        // Enable automatic synchronization
        'auto_sync' => env('ZKTECO_AUTO_SYNC', true),
        
        // Retry attempts for failed connections
        'retry_attempts' => env('ZKTECO_RETRY_ATTEMPTS', 3),
        
        // Delay between retry attempts (in seconds)
        'retry_delay' => env('ZKTECO_RETRY_DELAY', 10),
        
        // Batch size for processing large datasets
        'batch_size' => env('ZKTECO_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for device information and other data to improve
    | performance and reduce device load.
    |
    */

    'cache' => [
        // Cache device information (in minutes)
        'device_info_ttl' => env('ZKTECO_CACHE_DEVICE_INFO', 60),
        
        // Cache connection status (in minutes)
        'connection_status_ttl' => env('ZKTECO_CACHE_CONNECTION', 5),
        
        // Enable/disable caching
        'enabled' => env('ZKTECO_CACHE_ENABLED', true),
        
        // Cache key prefix
        'prefix' => env('ZKTECO_CACHE_PREFIX', 'zkteco_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configure logging for ZKTeco operations. This helps with debugging
    | and monitoring device communication.
    |
    */

    'logging' => [
        // Enable detailed logging
        'enabled' => env('ZKTECO_LOGGING_ENABLED', true),
        
        // Log level (emergency, alert, critical, error, warning, notice, info, debug)
        'level' => env('ZKTECO_LOG_LEVEL', 'info'),
        
        // Log channel to use
        'channel' => env('ZKTECO_LOG_CHANNEL', 'daily'),
        
        // Log connection attempts
        'log_connections' => env('ZKTECO_LOG_CONNECTIONS', true),
        
        // Log data extraction operations
        'log_extractions' => env('ZKTECO_LOG_EXTRACTIONS', true),
        
        // Log sync operations
        'log_sync' => env('ZKTECO_LOG_SYNC', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    |
    | Configure how ZKTeco data should be stored in your Laravel database.
    |
    */

    'database' => [
        // Table names for storing ZKTeco data
        'tables' => [
            'users' => env('ZKTECO_USERS_TABLE', 'users'),
            'attendance' => env('ZKTECO_ATTENDANCE_TABLE', 'attendance_records'),
        ],
        
        // User table columns mapping
        'user_columns' => [
            'zkteco_user_id' => 'zkteco_user_id',
            'zkteco_uid' => 'zkteco_uid',
            'name' => 'name',
            'zkteco_privilege' => 'zkteco_privilege',
            'zkteco_group_id' => 'zkteco_group_id',
            'zkteco_card' => 'zkteco_card',
            'last_sync_at' => 'last_sync_at',
        ],
        
        // Attendance table columns mapping
        'attendance_columns' => [
            'user_id' => 'user_id',
            'zkteco_uid' => 'zkteco_uid',
            'timestamp' => 'timestamp',
            'date' => 'date',
            'time' => 'time',
            'status' => 'status',
            'punch_type' => 'punch_type',
            'record_hash' => 'record_hash',
            'last_sync_at' => 'last_sync_at',
        ],
        
        // Enable soft deletes for synced records
        'soft_deletes' => env('ZKTECO_SOFT_DELETES', true),
        
        // Update existing records during sync
        'update_existing' => env('ZKTECO_UPDATE_EXISTING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    |
    | Configure export functionality for ZKTeco data.
    |
    */

    'export' => [
        // Default export format
        'default_format' => env('ZKTECO_EXPORT_FORMAT', 'csv'),
        
        // Available export formats
        'formats' => ['csv', 'json', 'xlsx'],
        
        // Export file storage path
        'storage_path' => env('ZKTECO_EXPORT_PATH', 'exports/zkteco'),
        
        // Include headers in CSV exports
        'csv_headers' => env('ZKTECO_CSV_HEADERS', true),
        
        // Date format for exports
        'date_format' => env('ZKTECO_DATE_FORMAT', 'Y-m-d H:i:s'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related settings for ZKTeco operations.
    |
    */

    'security' => [
        // Encrypt stored passwords
        'encrypt_passwords' => env('ZKTECO_ENCRYPT_PASSWORDS', true),
        
        // IP whitelist for device access (empty array = allow all)
        'ip_whitelist' => explode(',', env('ZKTECO_IP_WHITELIST', '')),
        
        // Enable rate limiting for API calls
        'rate_limiting' => env('ZKTECO_RATE_LIMITING', true),
        
        // Maximum API calls per minute
        'rate_limit' => env('ZKTECO_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notifications for ZKTeco events and errors.
    |
    */

    'notifications' => [
        // Enable notifications
        'enabled' => env('ZKTECO_NOTIFICATIONS_ENABLED', false),
        
        // Notification channels
        'channels' => ['mail', 'slack'],
        
        // Events to notify about
        'events' => [
            'connection_failed' => true,
            'sync_completed' => false,
            'sync_failed' => true,
            'device_offline' => true,
        ],
        
        // Recipients for notifications
        'recipients' => [
            'mail' => explode(',', env('ZKTECO_NOTIFICATION_EMAILS', '')),
            'slack' => env('ZKTECO_SLACK_WEBHOOK', ''),
        ],
    ],

];