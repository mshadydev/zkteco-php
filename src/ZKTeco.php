<?php

namespace MshadyDev\ZKTeco;

use Exception;
use DateTime;

/**
 * ZKTeco PHP Library
 * 
 * A comprehensive PHP library for connecting to and extracting data from ZKTeco fingerprint 
 * attendance devices. This library provides complete functionality for user management, 
 * attendance records extraction, and device information retrieval.
 * 
 * Based on comprehensive analysis of the pyzk Python library protocol and successfully 
 * tested with real ZKTeco devices.
 * 
 * @author Mohamed Shady <support@itechnologyeg.com>
 * @copyright 2025 Mohamed Shady (iTechnology)
 * @license MIT
 * @version 1.0.0
 * @link https://github.com/mshadydev/zkteco-php
 */
class ZKTeco {
    // Protocol Constants (from pyzk const.py)
    const CMD_CONNECT       = 1000;
    const CMD_EXIT          = 1001;
    const CMD_ENABLEDEVICE  = 1002;
    const CMD_DISABLEDEVICE = 1003;
    const CMD_RESTART       = 1004;
    const CMD_POWEROFF      = 1005;
    const CMD_AUTH          = 1102;
    const CMD_GET_VERSION   = 1100;
    const CMD_GET_TIME      = 201;
    const CMD_SET_TIME      = 202;
    
    // Data Commands
    const CMD_DB_RRQ        = 7;      // Read data from machine
    const CMD_USER_WRQ      = 8;      // Upload user information
    const CMD_USERTEMP_RRQ  = 9;      // Read user templates/data (Python equivalent)
    const CMD_ATTLOG_RRQ    = 13;     // Read attendance records
    const CMD_CLEAR_DATA    = 14;     // Clear data
    const CMD_CLEAR_ATTLOG  = 15;     // Clear attendance records
    const CMD_OPTIONS_RRQ   = 11;     // Read configuration parameter
    const CMD_GET_FREE_SIZES= 50;     // Get machine status
    
    // Response Codes
    const CMD_ACK_OK        = 2000;
    const CMD_ACK_ERROR     = 2001;
    const CMD_ACK_DATA      = 2002;
    const CMD_ACK_RETRY     = 2003;
    const CMD_ACK_REPEAT    = 2004;
    const CMD_ACK_UNAUTH    = 2005;
    
    // Data preparation
    const CMD_PREPARE_DATA  = 1500;
    const CMD_DATA          = 1501;
    const CMD_FREE_DATA     = 1502;
    
    // TCP Constants
    const MACHINE_PREPARE_DATA_1 = 20560; // 0x5050
    const MACHINE_PREPARE_DATA_2 = 32130; // 0x7282
    
    const USHRT_MAX         = 65535;
    
    // Connection properties
    private $ip;
    private $port;
    private $timeout;
    private $password;
    private $force_udp;
    private $omit_ping;
    private $verbose;
    private $encoding;
    
    // Socket and session
    private $socket;
    private $is_connect = false;
    private $is_enabled = true;
    private $session_id = 0;
    private $reply_id;
    private $data_recv = null;
    private $data = null;
    private $tcp = true;
    
    // Device capabilities
    private $users = 0;
    private $records = 0;
    private $user_packet_size = 28; // default for ZK6
    
    /**
     * Constructor
     */
    public function __construct($ip, $port = 4370, $timeout = 60, $password = 0, $force_udp = false, $omit_ping = false, $verbose = false, $encoding = 'UTF-8') {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->password = $password;
        $this->force_udp = $force_udp;
        $this->omit_ping = $omit_ping;
        $this->verbose = $verbose;
        $this->encoding = $encoding;
        $this->reply_id = self::USHRT_MAX - 1;
        $this->tcp = !$force_udp;
    }
    
    /**
     * Test ping connectivity
     */
    private function testPing() {
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "ping -n 1 -w 5000 {$this->ip}";
        } else {
            $cmd = "ping -c 1 -W 5 {$this->ip}";
        }
        
        exec($cmd, $output, $return_code);
        return $return_code === 0;
    }
    
    /**
     * Test TCP connectivity
     */
    private function testTcp() {
        $connection = @fsockopen($this->ip, $this->port, $errno, $errstr, 10);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }
    
    /**
     * Create socket connection
     */
    private function createSocket() {
        if ($this->tcp) {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
            $result = socket_connect($this->socket, $this->ip, $this->port);
            if (!$result) {
                throw new Exception("TCP connection failed: " . socket_strerror(socket_last_error()));
            }
        } else {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        }
    }
    
    /**
     * Create packet checksum
     */
    private function createChecksum($packet) {
        $checksum = 0;
        $length = strlen($packet);
        
        // Process pairs of bytes
        for ($i = 0; $i < $length - 1; $i += 2) {
            $checksum += ord($packet[$i]) + (ord($packet[$i + 1]) << 8);
            if ($checksum > self::USHRT_MAX) {
                $checksum -= self::USHRT_MAX;
            }
        }
        
        // Handle odd byte
        if ($length % 2) {
            $checksum += ord($packet[$length - 1]);
        }
        
        while ($checksum > self::USHRT_MAX) {
            $checksum -= self::USHRT_MAX;
        }
        
        $checksum = ~$checksum;
        
        while ($checksum < 0) {
            $checksum += self::USHRT_MAX;
        }
        
        return pack('v', $checksum);
    }
    
    /**
     * Create packet header
     */
    private function createHeader($command, $command_string = '', $session_id = null, $reply_id = null) {
        if ($session_id === null) $session_id = $this->session_id;
        if ($reply_id === null) $reply_id = $this->reply_id;
        
        // Create initial buffer
        $buf = pack('vvvv', $command, 0, $session_id, $reply_id) . $command_string;
        
        // Calculate checksum
        $checksum_data = '';
        for ($i = 0; $i < strlen($buf); $i++) {
            $checksum_data .= chr(ord($buf[$i]));
        }
        
        $checksum_bytes = $this->createChecksum($checksum_data);
        $checksum = unpack('v', $checksum_bytes)[1];
        
        // Increment reply_id
        $this->reply_id++;
        if ($this->reply_id >= self::USHRT_MAX) {
            $this->reply_id -= self::USHRT_MAX;
        }
        
        // Create final header
        $header = pack('vvvv', $command, $checksum, $session_id, $this->reply_id);
        
        return $header . $command_string;
    }
    
    /**
     * Create TCP top header
     */
    private function createTcpTop($packet) {
        $length = strlen($packet);
        return pack('vvV', self::MACHINE_PREPARE_DATA_1, self::MACHINE_PREPARE_DATA_2, $length) . $packet;
    }
    
    /**
     * Test TCP top header
     */
    private function testTcpTop($packet) {
        if (strlen($packet) <= 8) return 0;
        
        $header = unpack('vv1/vv2/VV', substr($packet, 0, 8));
        if ($header['v1'] == self::MACHINE_PREPARE_DATA_1 && $header['v2'] == self::MACHINE_PREPARE_DATA_2) {
            return $header['V'];
        }
        return 0;
    }
    
    /**
     * Send command to device
     */
    private function sendCommand($command, $command_string = '', $response_size = 1024) {
        if (!in_array($command, [self::CMD_CONNECT, self::CMD_AUTH]) && !$this->is_connect) {
            throw new Exception("Device is not connected");
        }
        
        $buf = $this->createHeader($command, $command_string, $this->session_id, $this->reply_id);
        
        try {
            if ($this->tcp) {
                $top = $this->createTcpTop($buf);
                socket_write($this->socket, $top, strlen($top));
                
                $this->data_recv = socket_read($this->socket, $response_size + 8);
                if ($this->data_recv === false) {
                    throw new Exception("Failed to receive TCP data: " . socket_strerror(socket_last_error()));
                }
                
                $tcp_length = $this->testTcpTop($this->data_recv);
                if ($tcp_length == 0) {
                    throw new Exception("Invalid TCP packet received");
                }
                
                $header_data = substr($this->data_recv, 8, 8);
                $this->data_recv = substr($this->data_recv, 8);
            } else {
                socket_sendto($this->socket, $buf, strlen($buf), 0, $this->ip, $this->port);
                $this->data_recv = socket_read($this->socket, $response_size);
                
                if ($this->data_recv === false) {
                    throw new Exception("Failed to receive UDP data: " . socket_strerror(socket_last_error()));
                }
                
                $header_data = substr($this->data_recv, 0, 8);
            }
            
            // Parse header
            $header = unpack('v4', $header_data);
            $response_code = $header[1];
            $this->reply_id = $header[4];
            $this->data = substr($this->data_recv, 8);
            
            if ($this->verbose) {
                echo "Command: $command, Response: $response_code\n";
            }
            
            if (in_array($response_code, [self::CMD_ACK_OK, self::CMD_PREPARE_DATA, self::CMD_DATA])) {
                return ['status' => true, 'code' => $response_code];
            }
            
            return ['status' => false, 'code' => $response_code];
            
        } catch (Exception $e) {
            throw new Exception("Network error: " . $e->getMessage());
        }
    }
    
    /**
     * Create communication key for authentication
     */
    private function makeCommKey($key, $session_id, $ticks = 50) {
        $key = intval($key);
        $session_id = intval($session_id);
        
        $k = 0;
        for ($i = 0; $i < 32; $i++) {
            if ($key & (1 << $i)) {
                $k = ($k << 1) | 1;
            } else {
                $k = $k << 1;
            }
        }
        
        $k += $session_id;
        $k = pack('V', $k);
        $k = unpack('C4', $k);
        
        $k = pack('C4',
            $k[1] ^ ord('Z'),
            $k[2] ^ ord('K'),
            $k[3] ^ ord('S'),
            $k[4] ^ ord('O')
        );
        
        $k = unpack('v2', $k);
        $k = pack('vv', $k[2], $k[1]);
        
        $B = 0xFF & $ticks;
        $k = unpack('C4', $k);
        
        $result = pack('C4',
            $k[1] ^ $B,
            $k[2] ^ $B,
            $B,
            $k[4] ^ $B
        );
        
        return $result;
    }
    
    /**
     * Connect to the device
     */
    public function connect() {
        $this->is_connect = false;
        
        // Test connectivity
        if (!$this->omit_ping && !$this->testPing()) {
            throw new Exception("Cannot reach device (ping failed): " . $this->ip);
        }
        
        if (!$this->force_udp && $this->testTcp()) {
            $this->user_packet_size = 72; // ZK8 default
        }
        
        $this->createSocket();
        $this->session_id = 0;
        $this->reply_id = self::USHRT_MAX - 1;
        
        // Send connect command
        $response = $this->sendCommand(self::CMD_CONNECT);
        
        // Extract session ID from response header regardless of auth status
        $header = unpack('v4', substr($this->data_recv, 0, 8));
        $this->session_id = $header[3];
        
        if ($this->verbose) {
            echo "Response code: {$response['code']}, Session ID: {$this->session_id}\n";
        }
        
        // Check if authentication is required
        if ($response['code'] == self::CMD_ACK_UNAUTH) {
            if ($this->verbose) {
                echo "Authentication required...\n";
                echo "Session ID: {$this->session_id}, Password: {$this->password}\n";
            }
            
            $auth_key = $this->makeCommKey($this->password, $this->session_id);
            
            if ($this->verbose) {
                echo "Auth key generated, sending CMD_AUTH...\n";
            }
            
            $auth_response = $this->sendCommand(self::CMD_AUTH, $auth_key);
            
            if ($this->verbose) {
                echo "Auth response code: {$auth_response['code']}\n";
            }
            
            if (!$auth_response['status']) {
                throw new Exception("Authentication failed - Response code: " . $auth_response['code']);
            }
            
            if ($this->verbose) {
                echo "✅ Authentication successful!\n";
            }
        }
        
        if ($response['status'] || ($response['code'] == self::CMD_ACK_UNAUTH && isset($auth_response) && $auth_response['status'])) {
            $this->is_connect = true;
            return true;
        } else {
            if ($response['code'] == self::CMD_ACK_UNAUTH) {
                throw new Exception("Authentication failed - Invalid password");
            }
            throw new Exception("Connection failed - Response code: " . $response['code']);
        }
    }
    
    /**
     * Disconnect from device
     */
    public function disconnect() {
        if (!$this->is_connect) return true;
        
        try {
            $response = $this->sendCommand(self::CMD_EXIT);
            $this->is_connect = false;
            
            if ($this->socket) {
                socket_close($this->socket);
            }
            
            return $response['status'];
        } catch (Exception $e) {
            if ($this->verbose) {
                echo "Disconnect error: " . $e->getMessage() . "\n";
            }
            return false;
        }
    }
    
    /**
     * Enable device (unlock for user interaction)
     */
    public function enableDevice() {
        $response = $this->sendCommand(self::CMD_ENABLEDEVICE);
        if ($response['status']) {
            $this->is_enabled = true;
            return true;
        }
        throw new Exception("Cannot enable device");
    }
    
    /**
     * Disable device (lock for maintenance)
     */
    public function disableDevice() {
        $response = $this->sendCommand(self::CMD_DISABLEDEVICE);
        if ($response['status']) {
            $this->is_enabled = false;
            return true;
        }
        throw new Exception("Cannot disable device");
    }
    
    /**
     * Get firmware version
     */
    public function getFirmwareVersion() {
        $response = $this->sendCommand(self::CMD_GET_VERSION, '', 1024);
        if ($response['status']) {
            $version = explode("\x00", $this->data)[0];
            return $version;
        }
        throw new Exception("Cannot read firmware version");
    }
    
    /**
     * Get device time
     */
    public function getTime() {
        $response = $this->sendCommand(self::CMD_GET_TIME, '', 1024);
        if ($response['status']) {
            $time_data = unpack('V', substr($this->data, 0, 4))[1];
            return $this->decodeTime($time_data);
        }
        throw new Exception("Cannot read device time");
    }
    
    /**
     * Set device time
     */
    public function setTime($datetime = null) {
        if ($datetime === null) {
            $datetime = new DateTime();
        } elseif (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }
        
        $time_data = $this->encodeTime($datetime);
        $command_string = pack('V', $time_data);
        
        $response = $this->sendCommand(self::CMD_SET_TIME, $command_string, 1024);
        if ($response['status']) {
            return true;
        }
        throw new Exception("Cannot set device time");
    }
    
    /**
     * Clear attendance records from device
     */
    public function clearAttendance() {
        $response = $this->sendCommand(self::CMD_CLEAR_ATTLOG, '', 1024);
        if ($response['status']) {
            return true;
        }
        throw new Exception("Cannot clear attendance records");
    }
    
    /**
     * Get device configuration option
     */
    public function getOption($option) {
        $command_string = "~{$option}\x00";
        $response = $this->sendCommand(self::CMD_OPTIONS_RRQ, $command_string, 1024);
        if ($response['status']) {
            $parts = explode('=', $this->data, 2);
            if (count($parts) > 1) {
                return trim(str_replace("\x00", '', $parts[1]));
            }
        }
        throw new Exception("Cannot read option: $option");
    }
    
    /**
     * Get serial number
     */
    public function getSerialNumber() {
        return $this->getOption('SerialNumber');
    }
    
    /**
     * Get platform name
     */
    public function getPlatform() {
        return $this->getOption('Platform');
    }
    
    /**
     * Get device name
     */
    public function getDeviceName() {
        return $this->getOption('DeviceName');
    }
    
    /**
     * Get MAC address
     */
    public function getMac() {
        return $this->getOption('MAC');
    }
    
    /**
     * Decode ZKTeco timestamp format
     */
    private function decodeTime($time_data) {
        if (is_string($time_data)) {
            $time_data = unpack('V', $time_data)[1];
        }
        
        $second = $time_data % 60;
        $time_data = intval($time_data / 60);
        
        $minute = $time_data % 60;
        $time_data = intval($time_data / 60);
        
        $hour = $time_data % 24;
        $time_data = intval($time_data / 24);
        
        $day = $time_data % 31 + 1;
        $time_data = intval($time_data / 31);
        
        $month = $time_data % 12 + 1;
        $time_data = intval($time_data / 12);
        
        $year = $time_data + 2000;
        
        return new DateTime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second));
    }
    
    /**
     * Encode DateTime to ZKTeco time format (reverse of decodeTime)
     */
    private function encodeTime($datetime) {
        $year = (int)$datetime->format('Y');
        $month = (int)$datetime->format('m');
        $day = (int)$datetime->format('d');
        $hour = (int)$datetime->format('H');
        $minute = (int)$datetime->format('i');
        $second = (int)$datetime->format('s');
        
        // Reverse the decoding process
        $time_data = $year - 2000;
        $time_data = ($time_data * 12) + ($month - 1);
        $time_data = ($time_data * 31) + ($day - 1);
        $time_data = ($time_data * 24) + $hour;
        $time_data = ($time_data * 60) + $minute;
        $time_data = ($time_data * 60) + $second;
        
        return $time_data;
    }
    
    /**
     * Get device status and capacity information
     */
    public function readSizes() {
        $response = $this->sendCommand(self::CMD_GET_FREE_SIZES, '', 1024);
        if ($response['status']) {
            if ($this->verbose) {
                echo "Raw size data length: " . strlen($this->data) . "\n";
                echo "Raw size data (hex): " . bin2hex($this->data) . "\n";
            }
            
            // Handle empty or insufficient data
            if (strlen($this->data) < 4) {
                if ($this->verbose) {
                    echo "Insufficient size data returned, using defaults\n";
                }
                // Set reasonable defaults based on device capabilities
                $this->users = 0; // Will be determined during actual data read
                $this->records = 0; // Will be determined during actual data read
                return [
                    'users' => $this->users,
                    'records' => $this->records,
                    'fingers' => 0,
                    'templates' => 0,
                    'passwords' => 0,
                    'op_records' => 0,
                ];
            }
            
            $data = unpack('V*', $this->data);
            
            if ($this->verbose) {
                echo "Unpacked data count: " . count($data) . "\n";
                for ($i = 1; $i <= count($data); $i++) {
                    echo "  data[$i] = " . (isset($data[$i]) ? $data[$i] : 'unset') . "\n";
                }
            }
            
            if (count($data) >= 10) {
                // Based on the debug output, the correct positions are:
                // data[5] = users count
                // data[9] = records count
                $this->users = $data[5];
                $this->records = $data[9];
                
                if ($this->verbose) {
                    echo "Final: users = {$this->users}, records = {$this->records}\n";
                }
                
                return [
                    'users' => $this->users,
                    'records' => $this->records,
                    'fingers' => isset($data[2]) ? $data[2] : 0,
                    'templates' => isset($data[3]) ? $data[3] : 0,
                    'passwords' => isset($data[4]) ? $data[4] : 0,
                    'op_records' => isset($data[5]) ? $data[5] : 0,
                ];
            }
        }
        throw new Exception("Cannot read device sizes");
    }
    
    /**
     * Check if connected
     */
    public function isConnected() {
        return $this->is_connect;
    }
    
    /**
     * Get verbose mode status
     */
    public function isVerbose() {
        return $this->verbose;
    }
    
    /**
     * Set verbose mode
     */
    public function setVerbose($verbose) {
        $this->verbose = $verbose;
    }
    
    /**
     * Free data buffer on device
     */
    public function freeData() {
        $response = $this->sendCommand(self::CMD_FREE_DATA);
        return $response['status'];
    }
    
    /**
     * Read data with buffer support
     */
    private function readWithBuffer($command, $fct = 0) {
        if ($this->verbose) {
            echo "readWithBuffer: command=$command, fct=$fct\n";
        }
        
        $response = $this->sendCommand($command, pack('V', $fct), 1024);
        
        if ($this->verbose) {
            echo "readWithBuffer response: status=" . ($response['status'] ? 'true' : 'false') . ", code={$response['code']}\n";
        }
        
        if (!$response['status']) {
            throw new Exception("Failed to read data with buffer - Response code: {$response['code']}");
        }
        
        if ($response['code'] == self::CMD_DATA) {
            // Single packet response
            return [$this->data, strlen($this->data)];
        }
        
        if ($response['code'] == self::CMD_PREPARE_DATA) {
            // Multi-packet response - get size
            $size = unpack('V', substr($this->data, 0, 4))[1];
            
            if ($size == 0) {
                return ['', 0];
            }
            
            // Acknowledge and prepare to receive data
            $this->ackOk();
            
            // Collect all data packets
            $bytes_recv = 0;
            $bytes_data = '';
            
            while ($bytes_recv < $size) {
                $data_chunk = '';
                
                if ($this->tcp) {
                    // For TCP, read in larger chunks and handle packet boundaries
                    $remaining = $size - $bytes_recv;
                    $read_size = min(8192, $remaining + 16); // Read more data at once
                    
                    $raw_data = socket_read($this->socket, $read_size);
                    
                    if ($raw_data !== false) {
                        // Check if this is a TCP packet with header
                        $tcp_length = $this->testTcpTop($raw_data);
                        if ($tcp_length > 0) {
                            $data_chunk = substr($raw_data, 16); // Skip TCP top + command headers  
                        } else {
                            // This might be continuation data without TCP header
                            $header = unpack('v4', substr($raw_data, 0, 8));
                            if (isset($header[1]) && $header[1] == self::CMD_DATA) {
                                $data_chunk = substr($raw_data, 8); // Skip command header only
                            } else {
                                $data_chunk = $raw_data; // Raw data continuation
                            }
                        }
                    }
                } else {
                    $chunk_size = min(1024, $size - $bytes_recv);
                    $raw_data = socket_read($this->socket, $chunk_size + 8);
                    
                    if ($raw_data !== false) {
                        $data_chunk = substr($raw_data, 8); // Skip command header
                    }
                }
                
                if (empty($data_chunk)) {
                    break;
                }
                
                $bytes_data .= $data_chunk;
                $bytes_recv += strlen($data_chunk);
                
                if ($this->verbose) {
                    echo "Received " . strlen($data_chunk) . " bytes, total: $bytes_recv/$size\n";
                }
            }
            
            return [$bytes_data, $bytes_recv];
        }
        
        return ['', 0];
    }
    
    /**
     * Send ACK OK response
     */
    private function ackOk() {
        $buf = $this->createHeader(self::CMD_ACK_OK, '', $this->session_id, self::USHRT_MAX - 1);
        
        try {
            if ($this->tcp) {
                socket_write($this->socket, $buf, strlen($buf));
            } else {
                socket_sendto($this->socket, $buf, strlen($buf), 0, $this->ip, $this->port);
            }
        } catch (Exception $e) {
            if ($this->verbose) {
                echo "ACK error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Get users from device
     */
    public function getUsers() {
        // Read device sizes first (like Python's read_sizes())
        $this->readSizes();
        
        if ($this->users == 0) {
            if ($this->verbose) {
                echo "Device reports 0 users\n";
            }
            return [];
        }
        
        if ($this->verbose) {
            echo "Device reports {$this->users} users\n";
        }
        
        // Try CMD_USERTEMP_RRQ first (Python method), fallback to CMD_DB_RRQ if no data
        list($userData, $size) = $this->readWithBuffer(self::CMD_USERTEMP_RRQ, 5);
        
        if ($size <= 4) {
            if ($this->verbose) {
                echo "CMD_USERTEMP_RRQ returned no data, trying CMD_DB_RRQ fallback...\n";
            }
            // Fallback to older method
            list($userData, $size) = $this->readWithBuffer(self::CMD_DB_RRQ, 5);
        }
        
        if ($this->verbose) {
            echo "User data size: $size bytes\n";
        }
        
        if ($size <= 4) {
            if ($this->verbose) {
                echo "Missing user data\n";
            }
            return [];
        }
        
        // Get total size from first 4 bytes
        $totalSize = unpack('V', substr($userData, 0, 4))[1];
        $userData = substr($userData, 4);
        
        // Calculate packet size like Python
        $userPacketSize = intval($totalSize / $this->users);
        
        if ($this->verbose) {
            echo "Total size: $totalSize, User packet size: $userPacketSize\n";
        }
        
        if (!in_array($userPacketSize, [28, 72])) {
            if ($this->verbose) {
                echo "Warning: unexpected packet size $userPacketSize\n";
            }
        }
        
        $users = [];
        $maxUid = 0;
        
        if ($userPacketSize == 28) {
            // 28-byte format - older devices
            $offset = 0;
            while ($offset + 28 <= strlen($userData)) {
                $record = substr($userData, $offset, 28);
                
                // Python format: '<HB5s8sIxBhI' = uid(2), privilege(1), password(5), name(8), card(4), x(1), group_id(1), timezone(2), user_id(4)
                $unpacked = unpack('vuid/Cprivilege/a5password/a8name/Vcard/x1/Cgroup_id/vtimezone/Vuser_id', $record);
                
                $uid = $unpacked['uid'];
                $privilege = $unpacked['privilege'];
                $password = rtrim($unpacked['password'], "\x00");
                $name = rtrim($unpacked['name'], "\x00");
                $card = $unpacked['card'];
                $group_id = $unpacked['group_id'];
                $user_id = $unpacked['user_id'];
                
                if ($uid > $maxUid) $maxUid = $uid;
                
                if (!$name) {
                    $name = "NN-$user_id";
                }
                
                $users[] = [
                    'uid' => $uid,
                    'user_id' => (string)$user_id,
                    'name' => $name,
                    'privilege' => $privilege,
                    'password' => $password,
                    'group_id' => (string)$group_id,
                    'card' => $card
                ];
                
                if ($this->verbose && count($users) <= 5) {
                    echo "User {$uid}: $name (ID: $user_id, Privilege: $privilege)\n";
                }
                
                $offset += 28;
            }
        } else {
            // 72-byte format - newer devices (this is what we should get)
            $offset = 0;
            while ($offset + 72 <= strlen($userData)) {
                $record = substr($userData, $offset, 72);
                
                // Python format: '<HB8s24sIx7sx24s' = uid(2), privilege(1), password(8), name(24), card(4), x(1), group_id(7), x(1), user_id(24)
                $unpacked = unpack('vuid/Cprivilege/a8password/a24name/Vcard/x1/a7group_id/x1/a24user_id', $record);
                
                $uid = $unpacked['uid'];
                $privilege = $unpacked['privilege'];
                $password = rtrim($unpacked['password'], "\x00");
                $name = rtrim($unpacked['name'], "\x00");
                $card = $unpacked['card'];
                $group_id = rtrim($unpacked['group_id'], "\x00");
                $user_id = rtrim($unpacked['user_id'], "\x00");
                
                if ($uid > $maxUid) $maxUid = $uid;
                
                if (!$name) {
                    $name = "NN-$user_id";
                }
                
                $users[] = [
                    'uid' => $uid,
                    'user_id' => $user_id,
                    'name' => $name,
                    'privilege' => $privilege,
                    'password' => $password,
                    'group_id' => $group_id,
                    'card' => $card
                ];
                
                if ($this->verbose && count($users) <= 5) {
                    echo "User {$uid}: $name (ID: $user_id, Privilege: $privilege)\n";
                }
                
                $offset += 72;
            }
        }
        
        if ($this->verbose) {
            echo "Parsed " . count($users) . " users\n";
        }
        
        return $users;
    }

    public function parseUsersWithSize($recordSize) {
        // This method is no longer used - replaced by new Python-compatible getUsers()
        return [];
    }

    public function getAttendance() {
        // Read device sizes first (like Python's read_sizes())
        $this->readSizes();
        
        if ($this->records == 0) {
            if ($this->verbose) {
                echo "Device reports 0 records\n";
            }
            return [];
        }
        
        if ($this->verbose) {
            echo "Device reports {$this->records} records\n";
        }
        
        // Get users first for user ID mapping (like Python does)
        $users = $this->getUsers();
        $userLookup = [];
        foreach ($users as $user) {
            $userLookup[$user['uid']] = $user['user_id'];
        }
        
        // Use Python's exact command: CMD_ATTLOG_RRQ
        list($attendanceData, $size) = $this->readWithBuffer(self::CMD_ATTLOG_RRQ);
        
        if ($this->verbose) {
            echo "Attendance data size: $size bytes\n";
        }
        
        if ($size < 4) {
            if ($this->verbose) {
                echo "CMD_ATTLOG_RRQ returned no data, trying CMD_DB_RRQ fallback with buffer 1...\n";
            }
            // Try alternative command with buffer ID 1 for attendance logs
            list($attendanceData, $size) = $this->readWithBuffer(self::CMD_DB_RRQ, 1);
            
            if ($this->verbose) {
                echo "CMD_DB_RRQ attendance data size: $size bytes\n";
            }
            
            if ($size < 4) {
                if ($this->verbose) {
                    echo "No attendance data\n";
                }
                return [];
            }
        }
        
        // Get total size from first 4 bytes
        $totalSize = unpack('V', substr($attendanceData, 0, 4))[1];
        $attendanceData = substr($attendanceData, 4);
        
        // Calculate record size like Python
        $recordSize = intval($totalSize / $this->records);
        
        if ($this->verbose) {
            echo "Total size: $totalSize, Record size: $recordSize\n";
        }
        
        $attendances = [];
        
        if ($recordSize == 8) {
            // 8-byte format
            $offset = 0;
            while ($offset + 8 <= strlen($attendanceData)) {
                $record = substr($attendanceData, $offset, 8);
                
                // Python format: 'HB4sB' = uid(2), status(1), timestamp(4), punch(1)
                $unpacked = unpack('vuid/Cstatus/Vtimestamp/Cpunch', $record);
                
                $uid = $unpacked['uid'];
                $status = $unpacked['status'];
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                $punch = $unpacked['punch'];
                
                // User ID lookup like Python does
                $user_id = isset($userLookup[$uid]) ? $userLookup[$uid] : (string)$uid;
                
                $attendances[] = [
                    'uid' => count($attendances) + 1,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'status' => $status,
                    'punch' => $punch,
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s')
                ];
                
                $offset += 8;
            }
        } elseif ($recordSize == 16) {
            // 16-byte format  
            $offset = 0;
            while ($offset + 16 <= strlen($attendanceData)) {
                $record = substr($attendanceData, $offset, 16);
                
                // Python format: '<I4sBB2sI' = user_id(4), timestamp(4), status(1), punch(1), reserved(2), workcode(4)
                $unpacked = unpack('Vuser_id/Vtimestamp/Cstatus/Cpunch/a2reserved/Vworkcode', $record);
                
                $user_id = (string)$unpacked['user_id'];
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                $status = $unpacked['status'];
                $punch = $unpacked['punch'];
                
                $attendances[] = [
                    'uid' => count($attendances) + 1,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'status' => $status,
                    'punch' => $punch,
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s')
                ];
                
                $offset += 16;
            }
        } else {
            // 40-byte format
            $offset = 0;
            while ($offset + 40 <= strlen($attendanceData)) {
                $record = substr($attendanceData, $offset, 40);
                
                // Python format: '<H24sB4sB8s' = uid(2), user_id(24), status(1), timestamp(4), punch(1), space(8)
                $unpacked = unpack('vuid/a24user_id/Cstatus/Vtimestamp/Cpunch/a8space', $record);
                
                $uid = $unpacked['uid'];
                $user_id = rtrim($unpacked['user_id'], "\x00");
                $status = $unpacked['status'];
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                $punch = $unpacked['punch'];
                
                if (empty($user_id)) {
                    $user_id = (string)$uid;
                }
                
                $attendances[] = [
                    'uid' => count($attendances) + 1,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'status' => $status,
                    'punch' => $punch,
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s')
                ];
                
                $offset += 40;
            }
        }
        
        if ($this->verbose) {
            echo "Parsed " . count($attendances) . " attendance records\n";
        }
        
        return $attendances;
    }
    
    /**
     * Get attendance records without calling getUsers() - for use in extractAllData()
     */
    public function getAttendanceOnly($users = []) {
        // Don't call readSizes again, assume device state is already known
        if ($this->records == 0) {
            if ($this->verbose) {
                echo "Device reports 0 records (using cached values)\n";
            }
            return [];
        }
        
        if ($this->verbose) {
            echo "Device reports {$this->records} records (cached)\n";
        }
        
        // Build user lookup from provided users (don't call getUsers again)
        $userLookup = [];
        foreach ($users as $user) {
            $userLookup[$user['uid']] = $user['user_id'];
        }
        
        // Use Python's exact command: CMD_ATTLOG_RRQ
        list($attendanceData, $size) = $this->readWithBuffer(self::CMD_ATTLOG_RRQ);
        
        if ($this->verbose) {
            echo "Attendance data size: $size bytes\n";
        }
        
        if ($size < 4) {
            if ($this->verbose) {
                echo "CMD_ATTLOG_RRQ returned no data, trying CMD_DB_RRQ fallback with buffer 1...\n";
            }
            // Try alternative command with buffer ID 1 for attendance logs
            list($attendanceData, $size) = $this->readWithBuffer(self::CMD_DB_RRQ, 1);
            
            if ($this->verbose) {
                echo "CMD_DB_RRQ attendance data size: $size bytes\n";
            }
            
            if ($size < 4) {
                if ($this->verbose) {
                    echo "No attendance data\n";
                }
                return [];
            }
        }
        
        // Get total size from first 4 bytes
        $totalSize = unpack('V', substr($attendanceData, 0, 4))[1];
        $attendanceData = substr($attendanceData, 4);
        
        // Calculate record size like Python
        $recordSize = intval($totalSize / $this->records);
        
        if ($this->verbose) {
            echo "Total size: $totalSize, Record size: $recordSize\n";
        }
        
        $attendances = [];
        
        if ($recordSize == 8) {
            // 8-byte format
            $offset = 0;
            while ($offset + 8 <= strlen($attendanceData)) {
                $record = substr($attendanceData, $offset, 8);
                
                // Python format: 'HB4sB' = uid(2), status(1), timestamp(4), punch(1)
                $unpacked = unpack('vuid/Cstatus/Vtimestamp/Cpunch', $record);
                
                $uid = $unpacked['uid'];
                $status = $unpacked['status'];
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                $punch = $unpacked['punch'];
                
                // User ID lookup like Python does
                $user_id = isset($userLookup[$uid]) ? $userLookup[$uid] : (string)$uid;
                
                $attendances[] = [
                    'uid' => count($attendances) + 1,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'status' => $status,
                    'punch' => $punch,
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s')
                ];
                
                $offset += 8;
            }
        } elseif ($recordSize == 16) {
            // 16-byte format  
            $offset = 0;
            while ($offset + 16 <= strlen($attendanceData)) {
                $record = substr($attendanceData, $offset, 16);
                
                // Python format: '<I4sBB2sI' = user_id(4), timestamp(4), status(1), punch(1), reserved(2), workcode(4)
                $unpacked = unpack('Vuser_id/Vtimestamp/Cstatus/Cpunch/a2reserved/Vworkcode', $record);
                
                $user_id = (string)$unpacked['user_id'];
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                $status = $unpacked['status'];
                $punch = $unpacked['punch'];
                
                $attendances[] = [
                    'uid' => count($attendances) + 1,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'status' => $status,
                    'punch' => $punch,
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s')
                ];
                
                $offset += 16;
            }
        } else {
            // 40-byte format
            $offset = 0;
            while ($offset + 40 <= strlen($attendanceData)) {
                $record = substr($attendanceData, $offset, 40);
                
                // Python format: '<H24sB4sB8s' = uid(2), user_id(24), status(1), timestamp(4), punch(1), space(8)
                $unpacked = unpack('vuid/a24user_id/Cstatus/Vtimestamp/Cpunch/a8space', $record);
                
                $uid = $unpacked['uid'];
                $user_id = rtrim($unpacked['user_id'], "\x00");
                $status = $unpacked['status'];
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                $punch = $unpacked['punch'];
                
                if (empty($user_id)) {
                    $user_id = (string)$uid;
                }
                
                $attendances[] = [
                    'uid' => count($attendances) + 1,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'status' => $status,
                    'punch' => $punch,
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s')
                ];
                
                $offset += 40;
            }
        }
        
        if ($this->verbose) {
            echo "Parsed " . count($attendances) . " attendance records\n";
        }
        
        return $attendances;
    }
    
    /**
     * Get comprehensive device information
     */
    public function getDeviceInfo() {
        $info = [];
        
        try {
            $info['firmware_version'] = $this->getFirmwareVersion();
        } catch (Exception $e) {
            $info['firmware_version'] = 'Unknown';
        }
        
        try {
            $info['serial_number'] = $this->getSerialNumber();
        } catch (Exception $e) {
            $info['serial_number'] = 'Unknown';
        }
        
        try {
            $info['platform'] = $this->getPlatform();
        } catch (Exception $e) {
            $info['platform'] = 'Unknown';
        }
        
        try {
            $info['device_name'] = $this->getDeviceName();
        } catch (Exception $e) {
            $info['device_name'] = 'Unknown';
        }
        
        try {
            $info['time'] = $this->getTime()->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $info['time'] = 'Unknown';
        }
        
        try {
            $info['mac'] = $this->getMac();
        } catch (Exception $e) {
            $info['mac'] = 'Unknown';
        }
        
        try {
            $sizes = $this->readSizes();
            $info = array_merge($info, $sizes);
        } catch (Exception $e) {
            // Sizes not available
        }
        
        return $info;
    }
    
    /**
     * Extract all data from the device
     */
    public function extractAllData() {
        echo "🚀 Starting ZKTeco PHP data extraction...\n";
        echo str_repeat('=', 60) . "\n";
        
        // Connect to device
        if (!$this->connect()) {
            echo "❌ Failed to connect to device\n";
            return false;
        }
        
        try {
            // Get device information
            echo "\n📱 DEVICE INFORMATION:\n";
            echo str_repeat('-', 30) . "\n";
            
            $device_info = $this->getDeviceInfo();
            
            foreach ($device_info as $key => $value) {
                echo "   " . ucwords(str_replace('_', ' ', $key)) . ": $value\n";
            }
            
            // Extract users first
            echo "\n� USERS EXTRACTION:\n";
            echo str_repeat('-', 30) . "\n";
            
            $users = [];
            try {
                $users = $this->getUsers();
                
                if (!empty($users)) {
                    echo "   Total Users: " . count($users) . "\n";
                    echo "   Sample users:\n";
                    
                    for ($i = 0; $i < min(3, count($users)); $i++) {
                        $user = $users[$i];
                        echo "     " . ($i + 1) . ". ID: {$user['user_id']}, Name: {$user['name']}\n";
                    }
                    
                    // Save users to files
                    $this->saveToCSV($users, 'zk_users_php');
                    $this->saveToJSON($users, 'zk_users_php');
                } else {
                    echo "   ⚠️  No users found\n";
                }
            } catch (Exception $e) {
                echo "   ⚠️  Users extraction failed: " . $e->getMessage() . "\n";
            }
            
            // Extract attendance separately (without calling getUsers again)
            echo "\n📊 ATTENDANCE EXTRACTION:\n";
            echo str_repeat('-', 30) . "\n";
            
            $attendance = [];
            try {
                $attendance = $this->getAttendanceOnly($users);
                
                if (!empty($attendance)) {
                    echo "   Total Records: " . count($attendance) . "\n";
                    
                    // Show date range
                    $dates = array_column($attendance, 'date');
                    if (!empty($dates)) {
                        echo "   Date Range: " . min($dates) . " to " . max($dates) . "\n";
                    }
                    
                    // Show sample records
                    echo "   Sample records:\n";
                    for ($i = 0; $i < min(3, count($attendance)); $i++) {
                        $att = $attendance[$i];
                        echo "     " . ($i + 1) . ". User: {$att['user_id']}, Time: {$att['timestamp']}, Status: {$att['status']}\n";
                    }
                    
                    // Save attendance to files
                    $this->saveToCSV($attendance, 'zk_attendance_php');
                    $this->saveToJSON($attendance, 'zk_attendance_php');
                    
                    // Generate summary report
                    $this->generateSummaryReport($attendance, $users, $device_info);
                } else {
                    echo "   ⚠️  No attendance records found\n";
                }
            } catch (Exception $e) {
                echo "   ⚠️  Attendance extraction failed: " . $e->getMessage() . "\n";
            }
            
            echo "\n🎉 EXTRACTION COMPLETED SUCCESSFULLY!\n";
            echo str_repeat('=', 60) . "\n";
            
            // Return true if we got device info (proves connection works)
            return true;
            
        } catch (Exception $e) {
            echo "❌ Extraction failed: " . $e->getMessage() . "\n";
            return false;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Save data to CSV file
     */
    private function saveToCSV($data, $filename_prefix) {
        if (empty($data)) {
            echo "❌ No data to save\n";
            return false;
        }
        
        // Create export directory if it doesn't exist
        $export_dir = 'export';
        if (!is_dir($export_dir)) {
            mkdir($export_dir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $ip_safe = str_replace('.', '_', $this->ip);
        $filename = "{$export_dir}/{$filename_prefix}_{$ip_safe}_{$timestamp}.csv";
        
        try {
            $fp = fopen($filename, 'w');
            
            // Write headers
            fputcsv($fp, array_keys($data[0]));
            
            // Write data with additional cleaning
            foreach ($data as $record) {
                $clean_record = [];
                foreach ($record as $key => $value) {
                    // Clean each field for CSV export
                    if (is_string($value)) {
                        // Remove any remaining non-printable characters
                        $clean_value = preg_replace('/[^\x20-\x7E]/', '', $value);
                        // Remove problematic quotes that aren't properly escaped
                        $clean_value = str_replace(['"', "'"], '', $clean_value);
                        $clean_record[$key] = $clean_value;
                    } else {
                        $clean_record[$key] = $value;
                    }
                }
                fputcsv($fp, $clean_record);
            }
            
            fclose($fp);
            
            echo "✅ Saved " . count($data) . " records to: $filename\n";
            return $filename;
            
        } catch (Exception $e) {
            echo "❌ Failed to save CSV: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Save data to JSON file
     */
    private function saveToJSON($data, $filename_prefix) {
        if (empty($data)) {
            echo "❌ No data to save\n";
            return false;
        }
        
        // Create export directory if it doesn't exist
        $export_dir = 'export';
        if (!is_dir($export_dir)) {
            mkdir($export_dir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $ip_safe = str_replace('.', '_', $this->ip);
        $filename = "{$export_dir}/{$filename_prefix}_{$ip_safe}_{$timestamp}.json";
        
        try {
            // Clean data for JSON encoding
            $clean_data = [];
            foreach ($data as $record) {
                $clean_record = [];
                foreach ($record as $key => $value) {
                    // Ensure all string values are UTF-8 clean
                    if (is_string($value)) {
                        $clean_record[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    } else {
                        $clean_record[$key] = $value;
                    }
                }
                $clean_data[] = $clean_record;
            }
            
            $json = json_encode($clean_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if ($json === false) {
                echo "❌ JSON encoding failed: " . json_last_error_msg() . "\n";
                return false;
            }
            
            file_put_contents($filename, $json);
            
            echo "✅ Saved " . count($data) . " records to: $filename\n";
            return $filename;
            
        } catch (Exception $e) {
            echo "❌ Failed to save JSON: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Generate summary report
     */
    private function generateSummaryReport($attendance, $users, $device_info) {
        // Create export directory if it doesn't exist
        $export_dir = 'export';
        if (!is_dir($export_dir)) {
            mkdir($export_dir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $ip_safe = str_replace('.', '_', $this->ip);
        $filename = "{$export_dir}/zk_summary_report_php_{$ip_safe}_{$timestamp}.txt";
        
        try {
            $report = "ZK ATTENDANCE SYSTEM SUMMARY REPORT (PHP)\n";
            $report .= str_repeat('=', 50) . "\n";
            $report .= "Generated: " . date('Y-m-d H:i:s') . "\n";
            $report .= "Device IP: {$this->ip}:{$this->port}\n\n";
            
            // Device Information
            $report .= "DEVICE INFORMATION:\n";
            $report .= str_repeat('-', 20) . "\n";
            foreach ($device_info as $key => $value) {
                $report .= ucwords(str_replace('_', ' ', $key)) . ": $value\n";
            }
            $report .= "\n";
            
            // Users Summary
            $report .= "USERS SUMMARY:\n";
            $report .= str_repeat('-', 15) . "\n";
            $report .= "Total Users: " . count($users) . "\n";
            
            if (!empty($users)) {
                // Group by privilege
                $privileges = [];
                foreach ($users as $user) {
                    $priv = $user['privilege'] ?? 'Unknown';
                    $privileges[$priv] = ($privileges[$priv] ?? 0) + 1;
                }
                
                $report .= "Users by Privilege:\n";
                foreach ($privileges as $priv => $count) {
                    $report .= "  $priv: $count\n";
                }
            }
            $report .= "\n";
            
            // Attendance Summary
            $report .= "ATTENDANCE SUMMARY:\n";
            $report .= str_repeat('-', 20) . "\n";
            $report .= "Total Records: " . count($attendance) . "\n";
            
            if (!empty($attendance)) {
                // Date range
                $dates = array_column($attendance, 'date');
                if (!empty($dates)) {
                    $report .= "Date Range: " . min($dates) . " to " . max($dates) . "\n";
                }
                
                // Daily counts (last 7 days)
                $daily_counts = [];
                foreach ($attendance as $att) {
                    $date = $att['date'] ?? 'Unknown';
                    $daily_counts[$date] = ($daily_counts[$date] ?? 0) + 1;
                }
                
                $report .= "Records by Date (last 7 days):\n";
                $sorted_dates = array_keys($daily_counts);
                rsort($sorted_dates);
                $recent_dates = array_slice($sorted_dates, 0, 7);
                
                foreach ($recent_dates as $date) {
                    $report .= "  $date: {$daily_counts[$date]} records\n";
                }
                
                // Status summary
                $status_counts = [];
                foreach ($attendance as $att) {
                    $status = $att['status'] ?? 'Unknown';
                    $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
                }
                
                $report .= "Records by Status:\n";
                foreach ($status_counts as $status => $count) {
                    $report .= "  Status $status: $count records\n";
                }
            }
            
            file_put_contents($filename, $report);
            echo "✅ Summary report saved to: $filename\n";
            
        } catch (Exception $e) {
            echo "❌ Failed to generate summary report: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Clean and extract readable text from binary name data
     */
    private function cleanNameData($raw_data) {
        if (empty($raw_data)) return '';
        
        // Remove null terminators and clean
        $cleaned = rtrim($raw_data, "\x00");
        if (empty($cleaned)) return '';
        
        $result = '';
        $len = strlen($cleaned);
        
        // Extract only printable characters
        for ($i = 0; $i < $len; $i++) {
            $char = $cleaned[$i];
            $ord = ord($char);
            
            // Accept printable ASCII characters (32-126)
            if ($ord >= 32 && $ord <= 126) {
                // Keep alphanumeric, space, hyphen, underscore, period, apostrophe
                if (($ord >= 48 && $ord <= 57) ||  // 0-9
                    ($ord >= 65 && $ord <= 90) ||   // A-Z  
                    ($ord >= 97 && $ord <= 122) ||  // a-z
                    $ord == 32 || $ord == 45 || $ord == 95 || // space, hyphen, underscore
                    $ord == 46 || $ord == 39) {              // period, apostrophe
                    $result .= $char;
                } else if ($ord >= 33 && $ord <= 47) {
                    // Convert some punctuation to space
                    $result .= ' ';
                }
            }
        }
        
        // Clean up the result
        $result = trim($result);
        $result = preg_replace('/\s+/', ' ', $result); // Normalize spaces
        $result = preg_replace('/[^a-zA-Z0-9\s_.-]/', '', $result); // Final cleanup
        
        return $result;
    }

    /**
     * Extract name candidates from a user record
     * Used for testing record size quality during dynamic detection
     */
    private function extractNameCandidates($record) {
        $name_candidates = [];
        $record_size = strlen($record);
        
        // Scan the entire record for readable text segments
        for ($start = 8; $start < $record_size - 8; $start += 4) {
            $segment_size = min(32, $record_size - $start);
            $name_segment = substr($record, $start, $segment_size);
            $cleaned = $this->cleanNameData($name_segment);
            
            if (!empty($cleaned) && strlen($cleaned) >= 3 && preg_match('/[a-zA-Z]/', $cleaned)) {
                $name_candidates[] = $cleaned;
            }
        }
        
        return $name_candidates;
    }
    
    private function isRealName($name) {
        $name = trim($name);
        
        // Reject obvious generic patterns
        if (preg_match('/^User_\d+$/i', $name)) return false;
        if (preg_match('/^\d+$/', $name)) return false; // Pure numbers
        if (strlen($name) < 3) return false;
        
        // Accept names that look human-like
        // Contains letters, may have spaces/dots/hyphens, not just "User_XXX" pattern
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9\s._-]*[a-zA-Z]$/', $name)) {
            // Extra check: prefer names with spaces or mixed case (more human-like)
            if (preg_match('/[\s]/', $name) || preg_match('/[a-z].*[A-Z]|[A-Z].*[a-z]/', $name)) {
                return true; // Names with spaces or mixed case are very likely real
            }
            // Also accept single names that aren't generic patterns
            if (!preg_match('/^(user|admin|test|guest|default)\d*$/i', $name)) {
                return true;
            }
        }
        
        return false;
    }
}
?>