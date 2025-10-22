# Laravel Integration for ZKTeco PHP Library

This directory contains comprehensive examples for integrating the ZKTeco PHP library into Laravel applications.

## 📋 Files Overview

### Core Integration Files

- **`ZKTecoService.php`** - Laravel Service class with caching, logging, and database sync
- **`ZKTecoController.php`** - HTTP Controller for web and API endpoints
- **`ZKTecoSyncCommand.php`** - Artisan command for CLI operations
- **`config_zkteco.php`** - Complete configuration file

### Database Files

- **`add_zkteco_fields_to_users_table.php`** - Migration to add ZKTeco fields to users table
- **`create_attendance_records_table.php`** - Migration for attendance records table
- **`AttendanceRecord.php`** - Eloquent model for attendance records

## 🚀 Installation & Setup

### 1. Install the Library

```bash
composer require mshadydev/zkteco-php
```

### 2. Set up Configuration

Copy `config_zkteco.php` to your Laravel `config` directory:

```bash
cp examples/laravel/config_zkteco.php config/zkteco.php
```

### 3. Environment Variables

Add to your `.env` file:

```env
# ZKTeco Device Configuration
ZKTECO_DEVICE_IP=192.168.1.100
ZKTECO_PORT=4370
ZKTECO_PASSWORD=0
ZKTECO_TIMEOUT=60

# Multiple Device IPs (if needed)
ZKTECO_MAIN_IP=192.168.1.100
ZKTECO_WAREHOUSE_IP=192.168.1.101
ZKTECO_HR_IP=192.168.1.102

# Sync Settings
ZKTECO_SYNC_INTERVAL=30
ZKTECO_AUTO_SYNC=true
ZKTECO_RETRY_ATTEMPTS=3

# Cache Settings
ZKTECO_CACHE_ENABLED=true
ZKTECO_CACHE_DEVICE_INFO=60

# Logging
ZKTECO_LOGGING_ENABLED=true
ZKTECO_LOG_LEVEL=info
```

### 4. Run Migrations

Copy migration files to your `database/migrations` directory:

```bash
cp examples/laravel/add_zkteco_fields_to_users_table.php database/migrations/2025_01_01_000001_add_zkteco_fields_to_users_table.php
cp examples/laravel/create_attendance_records_table.php database/migrations/2025_01_01_000002_create_attendance_records_table.php
```

Then run:

```bash
php artisan migrate
```

### 5. Register Service Provider

Add to your `app/Providers/AppServiceProvider.php`:

```php
public function register()
{
    $this->app->singleton(ZKTecoService::class, function ($app) {
        return new ZKTecoService();
    });
}
```

### 6. Copy Models and Controllers

Copy the example files to your Laravel app:

```bash
# Service
cp examples/laravel/ZKTecoService.php app/Services/

# Controller
cp examples/laravel/ZKTecoController.php app/Http/Controllers/

# Model
cp examples/laravel/AttendanceRecord.php app/Models/

# Artisan Command
cp examples/laravel/ZKTecoSyncCommand.php app/Console/Commands/
```

### 7. Register Routes

Add to your `routes/web.php`:

```php
use App\Http\Controllers\ZKTecoController;

Route::prefix('zkteco')->name('zkteco.')->group(function () {
    Route::get('/', [ZKTecoController::class, 'index'])->name('dashboard');
    Route::get('/test-connection', [ZKTecoController::class, 'testConnection'])->name('test');
    Route::get('/device-info', [ZKTecoController::class, 'getDeviceInfo'])->name('device-info');
    Route::get('/users', [ZKTecoController::class, 'extractUsers'])->name('users');
    Route::get('/attendance', [ZKTecoController::class, 'extractAttendance'])->name('attendance');
    Route::post('/sync/users', [ZKTecoController::class, 'syncUsers'])->name('sync.users');
    Route::post('/sync/attendance', [ZKTecoController::class, 'syncAttendance'])->name('sync.attendance');
    Route::post('/sync/full', [ZKTecoController::class, 'fullSync'])->name('sync.full');
    Route::get('/download/users', [ZKTecoController::class, 'downloadUsersCSV'])->name('download.users');
    Route::get('/download/attendance', [ZKTecoController::class, 'downloadAttendanceCSV'])->name('download.attendance');
});
```

## 🎯 Usage Examples

### Basic Service Usage

```php
<?php

use App\Services\ZKTecoService;

// In a controller or service
public function __construct(ZKTecoService $zkTecoService)
{
    $this->zkTecoService = $zkTecoService;
}

// Test connection
if ($this->zkTecoService->testConnection()) {
    echo "Device is online!";
}

// Get device info (cached for 60 minutes)
$deviceInfo = $this->zkTecoService->getDeviceInfo();

// Extract and sync users
$users = $this->zkTecoService->extractUsers();
$syncedUsers = $this->zkTecoService->syncUsersToDatabase($users);

// Extract and sync attendance
$attendance = $this->zkTecoService->extractAttendance();
$syncedAttendance = $this->zkTecoService->syncAttendanceToDatabase($attendance);
```

### Artisan Commands

```bash
# Test device connection
php artisan zkteco:sync --test-connection

# Sync users only
php artisan zkteco:sync --users-only

# Sync attendance only  
php artisan zkteco:sync --attendance-only

# Full sync (users + attendance)
php artisan zkteco:sync

# Force sync without confirmation
php artisan zkteco:sync --force
```

### API Endpoints

```javascript
// Test connection
fetch('/zkteco/test-connection')
  .then(response => response.json())
  .then(data => console.log(data));

// Get device info
fetch('/zkteco/device-info')
  .then(response => response.json())
  .then(data => console.log(data));

// Sync users
fetch('/zkteco/sync/users', { method: 'POST' })
  .then(response => response.json())
  .then(data => console.log(data));

// Full sync
fetch('/zkteco/sync/full', { method: 'POST' })
  .then(response => response.json())
  .then(data => console.log(data));
```

### Database Queries

```php
use App\Models\AttendanceRecord;

// Get today's attendance
$todayAttendance = AttendanceRecord::today()->get();

// Get user's attendance for a date range
$userAttendance = AttendanceRecord::forUser('1001')
    ->dateRange('2025-01-01', '2025-01-31')
    ->get();

// Get check-ins only
$checkIns = AttendanceRecord::checkIns()->today()->get();

// Get working hours for a user
$workingHours = AttendanceRecord::getWorkingHours('1001', '2025-01-15');

// Get daily summary
$summary = AttendanceRecord::getDailySummary('1001', '2025-01-15');
```

## 🔧 Advanced Features

### Multiple Device Support

Configure multiple devices in `config/zkteco.php`:

```php
'devices' => [
    'main_office' => [
        'ip' => '192.168.1.100',
        'password' => 0,
        'description' => 'Main Office Entrance',
        'enabled' => true,
    ],
    'warehouse' => [
        'ip' => '192.168.1.101', 
        'password' => 123456,
        'description' => 'Warehouse Entry',
        'enabled' => true,
    ],
],
```

### Automatic Synchronization

Set up a scheduled task in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync every 30 minutes
    $schedule->command('zkteco:sync --force')
             ->everyThirtyMinutes()
             ->withoutOverlapping();
}
```

### Event Handling

Create event listeners for ZKTeco operations:

```php
// Create events
php artisan make:event ZKTecoSyncCompleted
php artisan make:event ZKTecoConnectionFailed

// In ZKTecoService.php
use App\Events\ZKTecoSyncCompleted;

// After successful sync
event(new ZKTecoSyncCompleted($syncedCount, $deviceInfo));
```

## 🛠️ Troubleshooting

### Common Issues

1. **Connection Failed**
   - Check device IP in `.env` file
   - Verify network connectivity: `ping [device_ip]`
   - Ensure device is powered on

2. **Authentication Failed**
   - Try different passwords in config
   - Check device password settings

3. **Migration Errors**
   - Ensure your users table exists
   - Check database connection
   - Verify column names don't conflict

4. **Service Not Found**
   - Register service in `AppServiceProvider`
   - Check namespace imports
   - Run `php artisan config:clear`

### Debug Mode

Enable verbose logging in config:

```php
'logging' => [
    'enabled' => true,
    'level' => 'debug',
    'log_connections' => true,
    'log_extractions' => true,
],
```

## 📊 Performance Tips

1. **Use Caching**: Enable device info caching to reduce device load
2. **Batch Processing**: Configure appropriate batch sizes for large datasets
3. **Queue Jobs**: Use Laravel queues for large sync operations
4. **Database Indexing**: The migrations include proper indexes for performance

## 🔐 Security Considerations

1. **Environment Variables**: Store sensitive data in `.env`
2. **IP Whitelisting**: Configure IP restrictions in config
3. **Rate Limiting**: Enable API rate limiting
4. **Validation**: Always validate input data
5. **Logging**: Monitor access patterns and failures

## 📝 Example Blade Templates

Create views for the dashboard:

```php
{{-- resources/views/zkteco/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>ZKTeco Device Dashboard</h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Device Status</div>
                <div class="card-body">
                    <p>Status: <span class="badge badge-{{ $deviceStatus['connected'] ? 'success' : 'danger' }}">
                        {{ $deviceStatus['status'] }}
                    </span></p>
                    
                    @if($deviceStatus['connected'])
                        <p><strong>Platform:</strong> {{ $deviceStatus['device_info']['platform'] ?? 'Unknown' }}</p>
                        <p><strong>Users:</strong> {{ $deviceStatus['device_info']['users'] ?? 'Unknown' }}</p>
                        <p><strong>Records:</strong> {{ $deviceStatus['device_info']['records'] ?? 'Unknown' }}</p>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Quick Actions</div>
                <div class="card-body">
                    <button class="btn btn-primary btn-sync" data-type="full">Full Sync</button>
                    <button class="btn btn-info btn-sync" data-type="users">Sync Users</button>
                    <button class="btn btn-warning btn-sync" data-type="attendance">Sync Attendance</button>
                    <a href="{{ route('zkteco.download.users') }}" class="btn btn-secondary">Download Users</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$('.btn-sync').click(function() {
    const type = $(this).data('type');
    fetch(`/zkteco/sync/${type}`, { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Sync completed successfully!');
                location.reload();
            } else {
                alert('Sync failed: ' + data.message);
            }
        });
});
</script>
@endsection
```

This comprehensive Laravel integration provides everything you need to use the ZKTeco library in a production Laravel application with proper architecture, caching, logging, and database management!