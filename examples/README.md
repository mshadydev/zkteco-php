# ZKTeco PHP Library Examples

This directory contains example scripts demonstrating how to use the ZKTeco PHP library.

## Available Examples

### 1. `basic_connection.php`
Simple connection test to verify device connectivity and retrieve basic device information.

**Usage:**
```bash
php basic_connection.php
```

### 2. `extract_users.php`
Extract all user information from the device with detailed formatting and export options.

**Usage:**
```bash
php extract_users.php
```

### 3. `extract_attendance.php`
Extract attendance records with daily summaries and status breakdowns.

**Usage:**
```bash
php extract_attendance.php
```

### 4. `complete_extraction.php`
Complete workflow example with error handling, multiple password attempts, and fallback methods.

**Usage:**
```bash
php complete_extraction.php
```

## Sample Data Files

- `sample_users.csv` - Example user data format
- `sample_attendance.csv` - Example attendance record format

## Configuration

Before running the examples, update the device configuration in each file:

```php
$device_ip = "192.168.1.100";  // Your ZKTeco device IP
$password = 0;                 // Device password
```

## Common Device Passwords

If your device requires authentication, try these common passwords:
- `0` (no password - most common)
- `123456`
- `88888`
- `999999`

## Troubleshooting

1. **Connection Failed**: Verify device IP and network connectivity
2. **Authentication Error**: Try different passwords from the list above
3. **No Data**: Check if device has users/attendance records
4. **Socket Error**: Ensure PHP sockets extension is enabled

For more troubleshooting tips, see the main README.md file.