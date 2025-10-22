<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MshadyDev\ZKTeco\ZKTeco;

/**
 * Complete Data Extraction Example
 * 
 * This example demonstrates the complete data extraction workflow,
 * including users, attendance, and automatic file export.
 */

// Device configuration
$device_ip = "192.168.1.100";  // Replace with your device IP
$passwords = [0, 123456, 88888, 999999]; // Multiple passwords to try

echo "ZKTeco PHP Library - Complete Data Extraction Example\n";
echo str_repeat('=', 60) . "\n";

$successful_connection = false;
$zk = null;

// Try multiple passwords
foreach ($passwords as $password) {
    echo "🔑 Trying password: {$password}\n";
    
    try {
        $zk = new ZKTeco($device_ip, 4370, 60, $password);
        
        if ($zk->connect()) {
            echo "✅ Connected successfully with password: {$password}\n\n";
            $successful_connection = true;
            break;
        }
    } catch (Exception $e) {
        echo "❌ Failed with password {$password}: " . $e->getMessage() . "\n";
        continue;
    }
}

if (!$successful_connection) {
    echo "❌ Could not connect with any of the provided passwords.\n";
    exit(1);
}

try {
    // Method 1: Use the built-in extractAllData() method
    echo "🚀 Starting complete data extraction...\n";
    echo str_repeat('=', 50) . "\n";
    
    if ($zk->extractAllData()) {
        echo "\n🎉 Complete extraction finished successfully!\n";
        echo "📁 Check the 'export/' directory for generated files:\n";
        echo "   - CSV files (Excel compatible)\n";
        echo "   - JSON files (for programming use) \n";
        echo "   - Summary report (detailed statistics)\n\n";
        
        // List generated files
        $export_dir = 'export';
        if (is_dir($export_dir)) {
            echo "📋 Generated files:\n";
            $files = glob($export_dir . '/*');
            foreach ($files as $file) {
                $filesize = filesize($file);
                $filesize_kb = round($filesize / 1024, 2);
                echo "   - " . basename($file) . " ({$filesize_kb} KB)\n";
            }
        }
        
    } else {
        echo "❌ Complete extraction failed.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error during extraction: " . $e->getMessage() . "\n";
    
    // Method 2: Manual extraction as fallback
    echo "\n🔄 Attempting manual extraction as fallback...\n";
    
    try {
        // Get device info
        echo "📱 Getting device information...\n";
        $deviceInfo = $zk->getDeviceInfo();
        
        echo "Device: " . ($deviceInfo['platform'] ?? 'Unknown') . "\n";
        echo "Firmware: " . ($deviceInfo['firmware_version'] ?? 'Unknown') . "\n";
        echo "Users: " . ($deviceInfo['users'] ?? 'Unknown') . "\n";
        echo "Records: " . ($deviceInfo['records'] ?? 'Unknown') . "\n\n";
        
        // Extract users manually
        echo "👥 Extracting users manually...\n";
        $users = $zk->getUsers();
        echo "Found: " . count($users) . " users\n";
        
        // Extract attendance manually
        echo "📊 Extracting attendance manually...\n";
        $attendance = $zk->getAttendance(); 
        echo "Found: " . count($attendance) . " attendance records\n";
        
        // Save manually if extraction worked
        if (!empty($users) || !empty($attendance)) {
            echo "💾 Saving data manually...\n";
            
            if (!empty($users)) {
                $timestamp = date('Ymd_His');
                $filename = "manual_users_{$timestamp}.json";
                file_put_contents($filename, json_encode($users, JSON_PRETTY_PRINT));
                echo "✅ Users saved to: {$filename}\n";
            }
            
            if (!empty($attendance)) {
                $timestamp = date('Ymd_His');
                $filename = "manual_attendance_{$timestamp}.json";
                file_put_contents($filename, json_encode($attendance, JSON_PRETTY_PRINT));
                echo "✅ Attendance saved to: {$filename}\n";
            }
        }
        
    } catch (Exception $fallback_e) {
        echo "❌ Manual extraction also failed: " . $fallback_e->getMessage() . "\n";
    }
    
} finally {
    // Always disconnect
    if ($zk) {
        $zk->disconnect();
        echo "\n🔌 Disconnected from device.\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "💡 Tips for successful extraction:\n";
echo "- Ensure stable network connection to the device\n";
echo "- Use the correct device IP address\n"; 
echo "- Try different passwords if authentication fails\n";
echo "- Check that PHP sockets extension is enabled\n";
echo "- Some devices may need specific timing between operations\n";
echo "\nFor more examples, check the other files in this examples/ directory.\n";