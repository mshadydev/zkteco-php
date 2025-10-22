<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MshadyDev\ZKTeco\ZKTeco;

/**
 * Attendance Data Extraction Example
 * 
 * This example demonstrates how to extract attendance records from a ZKTeco device.
 */

// Device configuration
$device_ip = "192.168.1.100";  // Replace with your device IP
$password = 0;                 // Device password

echo "ZKTeco PHP Library - Attendance Data Extraction Example\n";
echo str_repeat('=', 60) . "\n";

try {
    // Initialize and connect
    $zk = new ZKTeco($device_ip, 4370, 60, $password);
    
    echo "Connecting to {$device_ip}...\n";
    
    if ($zk->connect()) {
        echo "✅ Connected successfully!\n\n";
        
        // Extract attendance records
        echo "📊 Extracting attendance records...\n";
        $attendance = $zk->getAttendance();
        
        if (empty($attendance)) {
            echo "⚠️  No attendance records found on the device.\n";
        } else {
            echo "✅ Found " . count($attendance) . " attendance records\n\n";
            
            // Get date range
            $dates = array_column($attendance, 'date');
            $minDate = min($dates);
            $maxDate = max($dates);
            echo "📅 Date Range: {$minDate} to {$maxDate}\n\n";
            
            // Display sample records
            echo "📋 Sample Attendance Records (first 10):\n";
            echo str_repeat('-', 85) . "\n";
            printf("%-8s %-12s %-20s %-8s %-8s %-12s\n", 
                "User ID", "Date", "Time", "Status", "Punch", "Full Time");
            echo str_repeat('-', 85) . "\n";
            
            $displayRecords = array_slice($attendance, 0, 10);
            foreach ($displayRecords as $record) {
                $statusText = getStatusText($record['status']);
                printf("%-8s %-12s %-20s %-8s %-8s %-12s\n",
                    $record['user_id'],
                    $record['date'],
                    $record['time'],
                    $statusText,
                    $record['punch'],
                    $record['timestamp']
                );
            }
            
            if (count($attendance) > 10) {
                echo "\n... and " . (count($attendance) - 10) . " more records\n";
            }
            
            // Daily summary
            echo "\n📈 Daily Attendance Summary (last 7 days):\n";
            echo str_repeat('-', 40) . "\n";
            
            $dailyCounts = [];
            foreach ($attendance as $record) {
                $date = $record['date'];
                $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + 1;
            }
            
            // Sort by date and show last 7 days
            krsort($dailyCounts);
            $recentDays = array_slice($dailyCounts, 0, 7, true);
            
            foreach ($recentDays as $date => $count) {
                $dayName = date('l', strtotime($date));
                echo sprintf("%s (%s): %d records\n", $date, $dayName, $count);
            }
            
            // Status breakdown
            echo "\n📊 Records by Status:\n";
            echo str_repeat('-', 25) . "\n";
            
            $statusCounts = [];
            foreach ($attendance as $record) {
                $status = $record['status'];
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
            
            foreach ($statusCounts as $status => $count) {
                $statusText = getStatusText($status);
                echo sprintf("Status %d (%s): %d records\n", $status, $statusText, $count);
            }
            
            // Option to save data
            echo "\n💾 Save attendance to file? (y/n): ";
            $handle = fopen("php://stdin", "r");
            $save = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($save) === 'y' || strtolower($save) === 'yes') {
                $timestamp = date('Ymd_His');
                $filename = "attendance_export_{$timestamp}.csv";
                
                $fp = fopen($filename, 'w');
                fputcsv($fp, ['UID', 'User ID', 'Date', 'Time', 'Timestamp', 'Status', 'Punch']);
                
                foreach ($attendance as $record) {
                    fputcsv($fp, [
                        $record['uid'],
                        $record['user_id'],
                        $record['date'],
                        $record['time'], 
                        $record['timestamp'],
                        $record['status'],
                        $record['punch']
                    ]);
                }
                
                fclose($fp);
                echo "✅ Attendance records saved to: {$filename}\n";
            }
        }
        
        // Disconnect
        $zk->disconnect();
        echo "\n🔌 Disconnected from device.\n";
        
    } else {
        echo "❌ Failed to connect to the device.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

/**
 * Get human-readable status text
 */
function getStatusText($status) {
    $statuses = [
        0 => 'Check-Out',
        1 => 'Check-In',
        2 => 'Break-Out', 
        3 => 'Break-In',
        4 => 'OT-In',
        5 => 'OT-Out'
    ];
    
    return $statuses[$status] ?? 'Unknown';
}