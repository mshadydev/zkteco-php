# ZKTeco PHP Library

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-blue.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Packagist](https://img.shields.io/packagist/v/mshadydev/zkteco-php.svg)](https://packagist.org/packages/mshadydev/zkteco-php)
[![Downloads](https://img.shields.io/packagist/dt/mshadydev/zkteco-php.svg)](https://packagist.org/packages/mshadydev/zkteco-php)

A comprehensive PHP library for connecting to and extracting data from ZKTeco fingerprint attendance devices. This library provides a complete implementation of the ZKTeco protocol, enabling you to retrieve user data, attendance records, and device information.

## ✨ Features

- 🔌 **Full Protocol Support** - Complete ZKTeco TCP/UDP communication protocol
- 👥 **User Management** - Extract user information, privileges, and access cards
- 📊 **Attendance Records** - Retrieve detailed attendance logs with timestamps
- 🔐 **Authentication** - Support for password-protected devices
- 📁 **Multiple Export Formats** - CSV, JSON export capabilities  
- 🌐 **Cross-Platform** - Works on Windows, Linux, and macOS
- 🚀 **Production Ready** - Tested with real ZKTeco devices

## 📋 Requirements

- PHP >= 7.0
- PHP Sockets extension (`ext-sockets`)
- Network access to ZKTeco device

## 📦 Installation

### Via Composer (Recommended)

```bash
composer require mshadydev/zkteco-php
```

### Manual Installation

1. Download the library files
2. Include the ZKTeco class in your project:

```php
require_once 'src/ZKTeco.php';
```

## 🚀 Quick Start

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use MshadyDev\ZKTeco\ZKTeco;

// Initialize connection
$zk = new ZKTeco('192.168.1.100', 4370, 60, 0);

try {
    // Connect to device
    $zk->connect();
    
    // Get device information
    $deviceInfo = $zk->getDeviceInfo();
    echo "Device: " . $deviceInfo['platform'] . "\n";
    
    // Extract users
    $users = $zk->getUsers();
    echo "Found " . count($users) . " users\n";
    
    // Extract attendance records
    $attendance = $zk->getAttendance();
    echo "Found " . count($attendance) . " attendance records\n";
    
    // Disconnect
    $zk->disconnect();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
```

### Complete Data Extraction

```php
<?php
use MshadyDev\ZKTeco\ZKTeco;

$device_ip = "192.168.1.100";
$password = 0; // Device password (0 = no password)

$zk = new ZKTeco($device_ip, 4370, 60, $password);

// Extract all data with automatic file export
if ($zk->extractAllData()) {
    echo "✅ Data extraction completed!\n";
    echo "📁 Check 'export/' directory for CSV/JSON files\n";
} else {
    echo "❌ Extraction failed\n";
}
?>
```

### Running Examples

The library includes several example scripts in the `examples/` directory:

```bash
# Basic connection test
php examples/basic_connection.php

# Extract users only
php examples/extract_users.php

# Extract attendance only  
php examples/extract_attendance.php

# Complete extraction with all features
php examples/complete_extraction.php
```

## � API Documentation

### Class Methods

#### Connection Methods
- `connect()` - Establish connection to the device
- `disconnect()` - Close the connection
- `isConnected()` - Check connection status

#### Data Extraction Methods
- `getUsers()` - Retrieve all user records
- `getAttendance()` - Retrieve attendance records
- `getDeviceInfo()` - Get device information and status
- `extractAllData()` - Complete data extraction with file export

#### Device Control Methods
- `enableDevice()` - Enable the device
- `disableDevice()` - Disable the device
- `getDeviceTime()` - Get device current time
- `setDeviceTime()` - Set device time

### Data Structures

#### User Record
```php
[
    'uid' => 1,                    // User ID
    'user_id' => '1001',          // Badge number
    'name' => 'John Doe',         // User name
    'privilege' => 14,            // User privilege level
    'password' => '',             // User password
    'group_id' => 1,             // Group ID
    'card' => 0                  // Card number
]
```

#### Attendance Record
```php
[
    'uid' => 1,                           // User ID
    'user_id' => '1001',                 // Badge number  
    'timestamp' => '2025-10-22 09:15:30', // Date and time
    'status' => 1,                       // Check-in/out status
    'punch' => 1,                        // Punch type
    'date' => '2025-10-22',             // Date only
    'time' => '09:15:30'                // Time only
]
```

## 🔧 Configuration

### Device Settings

```php
// Basic configuration
$device_ip = "192.168.1.100";    // Device IP address
$port = 4370;                    // Default ZKTeco port
$timeout = 60;                   // Connection timeout (seconds)
$password = 0;                   // Device password (0 = no password)

$zk = new ZKTeco($device_ip, $port, $timeout, $password);
```

### Multiple Password Attempts

```php
$passwords = [0, 123456, 88888]; // Try multiple passwords

foreach ($passwords as $password) {
    $zk = new ZKTeco($device_ip, 4370, 60, $password);
    try {
        $zk->connect();
        echo "✅ Connected with password: $password\n";
        break;
    } catch (Exception $e) {
        echo "❌ Failed with password: $password\n";
    }
}
```

## � Export Formats

The library automatically generates files in multiple formats:

### CSV Files
- `attendance_DEVICE_TIMESTAMP.csv` - Attendance records
- `users_DEVICE_TIMESTAMP.csv` - User information  

### JSON Files
- `attendance_DEVICE_TIMESTAMP.json` - Attendance data
- `users_DEVICE_TIMESTAMP.json` - User data

### Summary Report
- `summary_DEVICE_TIMESTAMP.txt` - Complete extraction statistics

## 🔍 Tested Devices

This library has been successfully tested on:

- **ZKTeco K Series** (Various firmware versions)
- **ZKTeco F18** 
- **ZKTeco MA300**
- **Various TCP/IP models**

### Test Results
- ✅ 500+ users extracted
- ✅ 1,000+ attendance records
- ✅ Multiple firmware versions supported
- ✅ CSV/JSON export validated

## �️ Troubleshooting

### Common Issues

**Connection Failed**
- Verify device IP address and network connectivity
- Check if device is powered on and network-enabled
- Ensure PHP sockets extension is enabled: `php -m | grep sockets`

**Authentication Failed**
- Try different passwords: `0`, `123456`, `88888`
- Check if device requires specific authentication method

**No Data Retrieved**
- Verify device has users/attendance records
- Some devices may need specific timing between operations
- Try different connection methods (TCP/UDP)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`
4. Check code style: `composer analyse`

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍� Author

**Mohamed Shady** - *iTechnology*
- GitHub: [@mshadydev](https://github.com/mshadydev)
- Website: [itechnologyeg.com](https://itechnologyeg.com)
- Email: support@itechnologyeg.com

## 🙏 Acknowledgments

- Based on the excellent [pyzk](https://github.com/fananimi/pyzk) Python library
- ZKTeco for their device protocol documentation
- The PHP community for excellent development tools

