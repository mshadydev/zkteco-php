<?php

namespace App\Services;

use MshadyDev\ZKTeco\ZKTeco;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Laravel Service Class for ZKTeco Integration
 * 
 * This service handles ZKTeco device communication within Laravel applications.
 * It provides methods for connecting to devices, extracting data, and handling
 * Laravel-specific features like caching, logging, and configuration.
 */
class ZKTecoService
{
    private $device_ip;
    private $port;
    private $password;
    private $timeout;
    private $zk;

    public function __construct()
    {
        // Load configuration from Laravel config
        $this->device_ip = config('zkteco.device_ip', '192.168.1.100');
        $this->port = config('zkteco.port', 4370);
        $this->password = config('zkteco.password', 0);
        $this->timeout = config('zkteco.timeout', 60);
    }

    /**
     * Connect to ZKTeco device
     * 
     * @return bool
     * @throws Exception
     */
    public function connect(): bool
    {
        try {
            $this->zk = new ZKTeco($this->device_ip, $this->port, $this->timeout, $this->password);
            
            if ($this->zk->connect()) {
                Log::info('ZKTeco device connected successfully', [
                    'device_ip' => $this->device_ip,
                    'port' => $this->port
                ]);
                return true;
            }
            
            throw new Exception('Failed to connect to ZKTeco device');
            
        } catch (Exception $e) {
            Log::error('ZKTeco connection failed', [
                'device_ip' => $this->device_ip,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get device information with caching
     * 
     * @param int $cacheDuration Cache duration in minutes (default: 60)
     * @return array
     */
    public function getDeviceInfo(int $cacheDuration = 60): array
    {
        $cacheKey = "zkteco_device_info_{$this->device_ip}";
        
        return Cache::remember($cacheKey, $cacheDuration * 60, function () {
            $this->connect();
            
            try {
                $deviceInfo = $this->zk->getDeviceInfo();
                $this->zk->disconnect();
                
                Log::info('Device information retrieved', [
                    'device_ip' => $this->device_ip,
                    'platform' => $deviceInfo['platform'] ?? 'Unknown'
                ]);
                
                return $deviceInfo;
                
            } catch (Exception $e) {
                $this->zk->disconnect();
                throw $e;
            }
        });
    }

    /**
     * Extract users from device
     * 
     * @return array
     */
    public function extractUsers(): array
    {
        $this->connect();
        
        try {
            $users = $this->zk->getUsers();
            $this->zk->disconnect();
            
            Log::info('Users extracted from ZKTeco device', [
                'device_ip' => $this->device_ip,
                'user_count' => count($users)
            ]);
            
            return $users;
            
        } catch (Exception $e) {
            $this->zk->disconnect();
            Log::error('Failed to extract users', [
                'device_ip' => $this->device_ip,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract attendance records from device
     * 
     * @return array
     */
    public function extractAttendance(): array
    {
        $this->connect();
        
        try {
            $attendance = $this->zk->getAttendance();
            $this->zk->disconnect();
            
            Log::info('Attendance records extracted from ZKTeco device', [
                'device_ip' => $this->device_ip,
                'record_count' => count($attendance)
            ]);
            
            return $attendance;
            
        } catch (Exception $e) {
            $this->zk->disconnect();
            Log::error('Failed to extract attendance records', [
                'device_ip' => $this->device_ip,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync users to Laravel database
     * 
     * @param array $users
     * @return int Number of users synced
     */
    public function syncUsersToDatabase(array $users = null): int
    {
        if ($users === null) {
            $users = $this->extractUsers();
        }

        $syncedCount = 0;

        foreach ($users as $userData) {
            try {
                // Using updateOrCreate to handle existing users
                \App\Models\User::updateOrCreate(
                    ['zkteco_user_id' => $userData['user_id']], // Matching criteria
                    [
                        'name' => $userData['name'],
                        'zkteco_uid' => $userData['uid'],
                        'zkteco_privilege' => $userData['privilege'],
                        'zkteco_group_id' => $userData['group_id'],
                        'zkteco_card' => $userData['card'],
                        'last_sync_at' => now()
                    ]
                );
                
                $syncedCount++;
                
            } catch (Exception $e) {
                Log::warning('Failed to sync user to database', [
                    'user_id' => $userData['user_id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Users synced to database', [
            'total_users' => count($users),
            'synced_count' => $syncedCount
        ]);

        return $syncedCount;
    }

    /**
     * Sync attendance records to Laravel database
     * 
     * @param array $attendanceRecords
     * @return int Number of records synced
     */
    public function syncAttendanceToDatabase(array $attendanceRecords = null): int
    {
        if ($attendanceRecords === null) {
            $attendanceRecords = $this->extractAttendance();
        }

        $syncedCount = 0;

        foreach ($attendanceRecords as $record) {
            try {
                // Create unique identifier for attendance record
                $recordHash = md5($record['user_id'] . $record['timestamp'] . $record['status']);
                
                \App\Models\Attendance::updateOrCreate(
                    ['record_hash' => $recordHash], // Prevent duplicates
                    [
                        'user_id' => $record['user_id'],
                        'zkteco_uid' => $record['uid'],
                        'timestamp' => $record['timestamp'],
                        'status' => $record['status'],
                        'punch_type' => $record['punch'],
                        'date' => $record['date'],
                        'time' => $record['time'],
                        'last_sync_at' => now()
                    ]
                );
                
                $syncedCount++;
                
            } catch (Exception $e) {
                Log::warning('Failed to sync attendance record to database', [
                    'user_id' => $record['user_id'],
                    'timestamp' => $record['timestamp'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Attendance records synced to database', [
            'total_records' => count($attendanceRecords),
            'synced_count' => $syncedCount
        ]);

        return $syncedCount;
    }

    /**
     * Test device connectivity
     * 
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $this->connect();
            $this->zk->disconnect();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get device status with health check
     * 
     * @return array
     */
    public function getDeviceStatus(): array
    {
        try {
            $isConnected = $this->testConnection();
            
            if ($isConnected) {
                $deviceInfo = $this->getDeviceInfo(5); // 5 minute cache
                
                return [
                    'status' => 'online',
                    'connected' => true,
                    'device_info' => $deviceInfo,
                    'last_check' => now()->toDateTimeString()
                ];
            }
            
            return [
                'status' => 'offline',
                'connected' => false,
                'error' => 'Cannot connect to device',
                'last_check' => now()->toDateTimeString()
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'connected' => false,
                'error' => $e->getMessage(),
                'last_check' => now()->toDateTimeString()
            ];
        }
    }
}