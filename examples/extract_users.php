<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MshadyDev\ZKTeco\ZKTeco;

/**
 * User Data Extraction Example
 * 
 * This example demonstrates how to extract user information from a ZKTeco device.
 */

// Device configuration
$device_ip = "192.168.1.100";  // Replace with your device IP
$password = 0;                 // Device password

echo "ZKTeco PHP Library - User Data Extraction Example\n";
echo str_repeat('=', 55) . "\n";

try {
    // Initialize and connect
    $zk = new ZKTeco($device_ip, 4370, 60, $password);
    
    echo "Connecting to {$device_ip}...\n";
    
    if ($zk->connect()) {
        echo "✅ Connected successfully!\n\n";
        
        // Extract users
        echo "👥 Extracting users...\n";
        $users = $zk->getUsers();
        
        if (empty($users)) {
            echo "⚠️  No users found on the device.\n";
        } else {
            echo "✅ Found " . count($users) . " users\n\n";
            
            // Display sample users
            echo "📋 Sample Users (first 5):\n";
            echo str_repeat('-', 80) . "\n";
            printf("%-5s %-10s %-25s %-12s %-8s %-8s\n", 
                "UID", "User ID", "Name", "Privilege", "Group", "Card");
            echo str_repeat('-', 80) . "\n";
            
            $displayUsers = array_slice($users, 0, 5);
            foreach ($displayUsers as $user) {
                printf("%-5s %-10s %-25s %-12s %-8s %-8s\n",
                    $user['uid'],
                    $user['user_id'],
                    substr($user['name'], 0, 24),
                    $user['privilege'],
                    $user['group_id'],
                    $user['card']
                );
            }
            
            if (count($users) > 5) {
                echo "\n... and " . (count($users) - 5) . " more users\n";
            }
            
            // Group users by privilege
            echo "\n📊 Users by Privilege Level:\n";
            echo str_repeat('-', 30) . "\n";
            
            $privilegeCounts = [];
            foreach ($users as $user) {
                $priv = $user['privilege'];
                $privilegeCounts[$priv] = ($privilegeCounts[$priv] ?? 0) + 1;
            }
            
            foreach ($privilegeCounts as $privilege => $count) {
                $privilegeName = $this->getPrivilegeName($privilege);
                echo sprintf("Privilege %d (%s): %d users\n", $privilege, $privilegeName, $count);
            }
            
            // Option to save data
            echo "\n💾 Save users to file? (y/n): ";
            $handle = fopen("php://stdin", "r");
            $save = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($save) === 'y' || strtolower($save) === 'yes') {
                $timestamp = date('Ymd_His');
                $filename = "users_export_{$timestamp}.csv";
                
                $fp = fopen($filename, 'w');
                fputcsv($fp, ['UID', 'User ID', 'Name', 'Privilege', 'Password', 'Group ID', 'Card']);
                
                foreach ($users as $user) {
                    fputcsv($fp, [
                        $user['uid'],
                        $user['user_id'], 
                        $user['name'],
                        $user['privilege'],
                        $user['password'],
                        $user['group_id'],
                        $user['card']
                    ]);
                }
                
                fclose($fp);
                echo "✅ Users saved to: {$filename}\n";
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
 * Get human-readable privilege name
 */
function getPrivilegeName($privilege) {
    $privileges = [
        0 => 'User',
        2 => 'Enroller', 
        6 => 'Admin',
        14 => 'Super Admin'
    ];
    
    return $privileges[$privilege] ?? 'Unknown';
}