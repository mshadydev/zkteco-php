<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MshadyDev\ZKTeco\ZKTeco;

/**
 * Basic ZKTeco Connection Example
 * 
 * This example demonstrates how to establish a basic connection to a ZKTeco device
 * and retrieve basic device information.
 */

// Device configuration
$device_ip = "192.168.1.100";  // Replace with your device IP
$port = 4370;                  // Default ZKTeco port
$timeout = 60;                 // Connection timeout in seconds
$password = 0;                 // Device password (0 = no password)

echo "ZKTeco PHP Library - Basic Connection Example\n";
echo str_repeat('=', 50) . "\n";

try {
    // Initialize ZKTeco instance
    $zk = new ZKTeco($device_ip, $port, $timeout, $password);
    
    // Enable verbose mode for detailed output
    $zk->setVerbose(true);
    
    echo "Attempting to connect to {$device_ip}:{$port}...\n";
    
    // Connect to the device
    if ($zk->connect()) {
        echo "✅ Connected successfully!\n\n";
        
        // Get device information
        echo "📱 Device Information:\n";
        echo str_repeat('-', 25) . "\n";
        
        $deviceInfo = $zk->getDeviceInfo();
        
        foreach ($deviceInfo as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            echo sprintf("%-20s: %s\n", $label, $value);
        }
        
        echo "\n✅ Basic connection test completed successfully!\n";
        
        // Disconnect from the device
        $zk->disconnect();
        echo "🔌 Disconnected from device.\n";
        
    } else {
        echo "❌ Failed to connect to the device.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Troubleshooting tips:\n";
    echo "- Verify the device IP address is correct\n";
    echo "- Check network connectivity (ping the device)\n";
    echo "- Ensure the device is powered on and network-enabled\n";
    echo "- Try different passwords if authentication fails\n";
    echo "- Check if PHP sockets extension is enabled\n";
}