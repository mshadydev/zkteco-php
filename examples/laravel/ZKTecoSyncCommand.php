<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZKTecoService;
use Exception;

/**
 * Laravel Artisan Command for ZKTeco Operations
 * 
 * This command provides CLI access to ZKTeco device operations.
 * Usage examples:
 * - php artisan zkteco:sync
 * - php artisan zkteco:sync --users-only
 * - php artisan zkteco:sync --attendance-only
 * - php artisan zkteco:test-connection
 */
class ZKTecoSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zkteco:sync 
                            {--users-only : Sync only users}
                            {--attendance-only : Sync only attendance records}
                            {--test-connection : Test device connection only}
                            {--force : Force sync without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize data from ZKTeco device to database';

    protected $zkTecoService;

    /**
     * Create a new command instance.
     */
    public function __construct(ZKTecoService $zkTecoService)
    {
        parent::__construct();
        $this->zkTecoService = $zkTecoService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 ZKTeco Synchronization Tool');
        $this->info('================================');

        // Test connection first
        if ($this->option('test-connection')) {
            return $this->testConnection();
        }

        // Test device connectivity
        if (!$this->testDeviceConnectivity()) {
            return 1;
        }

        // Get confirmation unless forced
        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with synchronization?')) {
                $this->info('Synchronization cancelled.');
                return 0;
            }
        }

        try {
            if ($this->option('users-only')) {
                return $this->syncUsers();
            } elseif ($this->option('attendance-only')) {
                return $this->syncAttendance();
            } else {
                return $this->fullSync();
            }
        } catch (Exception $e) {
            $this->error('❌ Synchronization failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test device connection
     */
    protected function testConnection(): int
    {
        $this->info('🔌 Testing device connection...');

        try {
            $isConnected = $this->zkTecoService->testConnection();
            
            if ($isConnected) {
                $this->info('✅ Device connection successful!');
                
                // Get device info
                $deviceInfo = $this->zkTecoService->getDeviceInfo();
                $this->displayDeviceInfo($deviceInfo);
                
                return 0;
            } else {
                $this->error('❌ Device connection failed!');
                return 1;
            }
        } catch (Exception $e) {
            $this->error('❌ Connection test failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test device connectivity (internal)
     */
    protected function testDeviceConnectivity(): bool
    {
        $this->info('🔌 Testing device connection...');

        try {
            $isConnected = $this->zkTecoService->testConnection();
            
            if ($isConnected) {
                $this->info('✅ Device is online and accessible');
                return true;
            } else {
                $this->error('❌ Cannot connect to ZKTeco device');
                $this->error('Please check:');
                $this->error('- Device IP address and network connectivity');
                $this->error('- Device is powered on and network-enabled');
                $this->error('- Firewall settings and port accessibility');
                return false;
            }
        } catch (Exception $e) {
            $this->error('❌ Device connectivity test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync users only
     */
    protected function syncUsers(): int
    {
        $this->info('👥 Synchronizing users...');
        
        $progressBar = $this->output->createProgressBar(2);
        $progressBar->setFormat('[%bar%] %current%/%max% %message%');
        
        try {
            $progressBar->setMessage('Extracting users from device...');
            $progressBar->advance();
            
            $users = $this->zkTecoService->extractUsers();
            
            $progressBar->setMessage('Syncing to database...');
            $progressBar->advance();
            
            $syncedCount = $this->zkTecoService->syncUsersToDatabase($users);
            
            $progressBar->finish();
            $this->newLine();
            
            $this->info("✅ Successfully synced {$syncedCount} users to database");
            $this->displaySyncSummary('Users', count($users), $syncedCount);
            
            return 0;
        } catch (Exception $e) {
            $progressBar->finish();
            $this->newLine();
            throw $e;
        }
    }

    /**
     * Sync attendance only
     */
    protected function syncAttendance(): int
    {
        $this->info('📊 Synchronizing attendance records...');
        
        $progressBar = $this->output->createProgressBar(2);
        $progressBar->setFormat('[%bar%] %current%/%max% %message%');
        
        try {
            $progressBar->setMessage('Extracting attendance from device...');
            $progressBar->advance();
            
            $attendance = $this->zkTecoService->extractAttendance();
            
            $progressBar->setMessage('Syncing to database...');
            $progressBar->advance();
            
            $syncedCount = $this->zkTecoService->syncAttendanceToDatabase($attendance);
            
            $progressBar->finish();
            $this->newLine();
            
            $this->info("✅ Successfully synced {$syncedCount} attendance records to database");
            $this->displaySyncSummary('Attendance Records', count($attendance), $syncedCount);
            
            return 0;
        } catch (Exception $e) {
            $progressBar->finish();
            $this->newLine();
            throw $e;
        }
    }

    /**
     * Full synchronization
     */
    protected function fullSync(): int
    {
        $this->info('🔄 Performing full synchronization...');
        
        $progressBar = $this->output->createProgressBar(4);
        $progressBar->setFormat('[%bar%] %current%/%max% %message%');
        
        try {
            // Sync Users
            $progressBar->setMessage('Extracting users...');
            $progressBar->advance();
            
            $users = $this->zkTecoService->extractUsers();
            
            $progressBar->setMessage('Syncing users to database...');
            $progressBar->advance();
            
            $usersSynced = $this->zkTecoService->syncUsersToDatabase($users);
            
            // Sync Attendance
            $progressBar->setMessage('Extracting attendance records...');
            $progressBar->advance();
            
            $attendance = $this->zkTecoService->extractAttendance();
            
            $progressBar->setMessage('Syncing attendance to database...');
            $progressBar->advance();
            
            $attendanceSynced = $this->zkTecoService->syncAttendanceToDatabase($attendance);
            
            $progressBar->finish();
            $this->newLine();
            
            $this->info('✅ Full synchronization completed successfully!');
            $this->newLine();
            
            // Display summary
            $this->displaySyncSummary('Users', count($users), $usersSynced);
            $this->displaySyncSummary('Attendance Records', count($attendance), $attendanceSynced);
            
            return 0;
        } catch (Exception $e) {
            $progressBar->finish();
            $this->newLine();
            throw $e;
        }
    }

    /**
     * Display device information
     */
    protected function displayDeviceInfo(array $deviceInfo): void
    {
        $this->newLine();
        $this->info('📱 Device Information:');
        $this->info('--------------------');
        
        foreach ($deviceInfo as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            $this->info("   {$label}: {$value}");
        }
    }

    /**
     * Display synchronization summary
     */
    protected function displaySyncSummary(string $type, int $total, int $synced): void
    {
        $this->info("📋 {$type} Summary:");
        $this->info("   Total extracted: {$total}");
        $this->info("   Successfully synced: {$synced}");
        
        if ($synced < $total) {
            $failed = $total - $synced;
            $this->warn("   Failed to sync: {$failed}");
        }
        
        $this->newLine();
    }
}