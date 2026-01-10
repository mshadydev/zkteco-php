<?php

namespace MshadyDev\ZKTeco;

use Exception;
use DateTime;

/**
 * ZKTeco PHP Library - Port of Python pyzk library
 * 
 * This is a faithful PHP port of the fananimi/pyzk Python library
 * 
 * @author Mohamed Shady <support@itechnologyeg.com>
 * @version 2.0.0
 */
class ZKTeco {
    // Protocol Constants - matching pyzk const.py
    const USHRT_MAX = 65535;
    
    const CMD_DB_RRQ          = 7;
    const CMD_USER_WRQ        = 8;
    const CMD_USERTEMP_RRQ    = 9;
    const CMD_USERTEMP_WRQ    = 10;
    const CMD_OPTIONS_RRQ     = 11;
    const CMD_OPTIONS_WRQ     = 12;
    const CMD_ATTLOG_RRQ      = 13;
    const CMD_CLEAR_DATA      = 14;
    const CMD_CLEAR_ATTLOG    = 15;
    const CMD_DELETE_USER     = 18;
    const CMD_DELETE_USERTEMP = 19;
    const CMD_CLEAR_ADMIN     = 20;
    
    const CMD_GET_FREE_SIZES  = 50;
    const CMD_ENABLE_CLOCK    = 57;
    const CMD_STARTVERIFY     = 60;
    const CMD_STARTENROLL     = 61;
    const CMD_CANCELCAPTURE   = 62;
    const CMD_STATE_RRQ       = 64;
    
    const CMD_GET_TIME        = 201;
    const CMD_SET_TIME        = 202;
    const CMD_REG_EVENT       = 500;
    
    const CMD_CONNECT         = 1000;
    const CMD_EXIT            = 1001;
    const CMD_ENABLEDEVICE    = 1002;
    const CMD_DISABLEDEVICE   = 1003;
    const CMD_RESTART         = 1004;
    const CMD_POWEROFF        = 1005;
    
    const CMD_GET_VERSION     = 1100;
    const CMD_CHANGE_SPEED    = 1101;
    const CMD_AUTH            = 1102;
    
    const CMD_PREPARE_DATA    = 1500;
    const CMD_DATA            = 1501;
    const CMD_FREE_DATA       = 1502;
    const CMD_PREPARE_BUFFER  = 1503;  // (UNDOCUMENTED) initialize buffer for partial reads
    const CMD_READ_BUFFER     = 1504;  // (UNDOCUMENTED) read a partial chunk from buffer
    
    const CMD_ACK_OK          = 2000;
    const CMD_ACK_ERROR       = 2001;
    const CMD_ACK_DATA        = 2002;
    const CMD_ACK_RETRY       = 2003;
    const CMD_ACK_REPEAT      = 2004;
    const CMD_ACK_UNAUTH      = 2005;
    
    const FCT_ATTLOG          = 1;
    const FCT_WORKCODE        = 8;
    const FCT_FINGERTMP       = 2;
    const FCT_OPLOG           = 4;
    const FCT_USER            = 5;
    const FCT_SMS             = 6;
    const FCT_UDATA           = 7;
    
    const MACHINE_PREPARE_DATA_1 = 20560;  // 0x5050
    const MACHINE_PREPARE_DATA_2 = 32130;  // 0x7d82

    // Instance variables
    private $ip;
    private $port;
    private $timeout;
    private $password;
    private $force_udp;
    private $verbose;
    private $encoding;
    
    private $socket;
    private $is_connect = false;
    private $session_id = 0;
    private $reply_id;
    private $tcp = true;
    
    // Response data
    private $data_recv;
    private $tcp_data_recv;
    private $tcp_length;
    private $header;
    private $data;
    private $response;
    
    // Device info
    public $users = 0;
    public $fingers = 0;
    public $records = 0;
    public $cards = 0;
    public $users_cap = 0;
    public $fingers_cap = 0;
    public $rec_cap = 0;
    public $faces = 0;
    public $faces_cap = 0;
    public $user_packet_size = 72;
    public $next_uid = 1;
    public $next_user_id = '1';
    
    public function __construct($ip, $port = 4370, $timeout = 60, $password = 0, $force_udp = false, $verbose = false, $encoding = 'UTF-8') {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->password = $password;
        $this->force_udp = $force_udp;
        $this->verbose = $verbose;
        $this->encoding = $encoding;
        $this->reply_id = self::USHRT_MAX - 1;
        $this->tcp = !$force_udp;
    }
    
    /**
     * Create socket connection
     */
    private function createSocket() {
        if ($this->tcp) {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($this->socket === false) {
                throw new Exception("Failed to create socket: " . socket_strerror(socket_last_error()));
            }
            
            // Set socket options
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, 1048576);  // 1MB buffer
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, 1048576);
            
            $result = @socket_connect($this->socket, $this->ip, $this->port);
            if ($result === false) {
                throw new Exception("TCP connection failed: " . socket_strerror(socket_last_error($this->socket)));
            }
        } else {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        }
    }
    
    /**
     * Create TCP top header - matches pyzk __create_tcp_top
     */
    private function createTcpTop($packet) {
        $length = strlen($packet);
        return pack('vvV', self::MACHINE_PREPARE_DATA_1, self::MACHINE_PREPARE_DATA_2, $length) . $packet;
    }
    
    /**
     * Test TCP top header - matches pyzk __test_tcp_top
     * Returns size if valid, 0 otherwise
     */
    private function testTcpTop($packet) {
        if ($packet === null || strlen($packet) <= 8) {
            return 0;
        }
        $tcp_header = unpack('v2machine/Vsize', $packet);
        if ($tcp_header['machine1'] == self::MACHINE_PREPARE_DATA_1 && 
            $tcp_header['machine2'] == self::MACHINE_PREPARE_DATA_2) {
            return $tcp_header['size'];
        }
        return 0;
    }
    
    /**
     * Create packet header - matches pyzk __create_header exactly
     */
    private function createHeader($command, $command_string, $session_id, $reply_id) {
        // First, create the buffer with checksum = 0 for checksum calculation
        $buf = pack('v4', $command, 0, $session_id, $reply_id) . $command_string;
        
        // Convert to array of bytes for checksum calculation
        $bytes = array_values(unpack('C*', $buf));
        
        // Calculate checksum
        $checksum_bytes = $this->createChecksum($bytes);
        $checksum = unpack('v', $checksum_bytes)[1];
        
        // Increment reply_id for the actual packet
        $reply_id++;
        if ($reply_id >= self::USHRT_MAX) {
            $reply_id -= self::USHRT_MAX;
        }
        
        // Create final packet with proper checksum
        return pack('v4', $command, $checksum, $session_id, $reply_id) . $command_string;
    }
    
    /**
     * Create checksum - matches pyzk __create_checksum exactly
     */
    private function createChecksum($p) {
        $l = count($p);
        $checksum = 0;
        
        $i = 0;
        while ($l > 1) {
            // Pack two bytes and unpack as unsigned short
            $checksum += unpack('v', pack('C2', $p[$i], $p[$i + 1]))[1];
            $i += 2;
            if ($checksum > self::USHRT_MAX) {
                $checksum -= self::USHRT_MAX;
            }
            $l -= 2;
        }
        
        if ($l) {
            $checksum += $p[$i];
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
     * Send command to device - matches pyzk __send_command
     */
    private function sendCommand($command, $command_string = '', $response_size = 8) {
        if (!in_array($command, [self::CMD_CONNECT, self::CMD_AUTH]) && !$this->is_connect) {
            throw new Exception("Not connected to device");
        }
        
        $buf = $this->createHeader($command, $command_string, $this->session_id, $this->reply_id);
        
        try {
            if ($this->tcp) {
                $top = $this->createTcpTop($buf);
                $sent = socket_send($this->socket, $top, strlen($top), 0);
                
                if ($this->verbose) {
                    echo "Sent $sent bytes for cmd $command\n";
                    echo "Packet sent: " . bin2hex($top) . "\n";
                }
                
                // Receive response - use plain recv without MSG_WAITALL like Python does
                $this->tcp_data_recv = '';
                $recv_size = $response_size + 8;  // TCP header + response
                $bytes = @socket_recv($this->socket, $this->tcp_data_recv, $recv_size, 0);
                
                if ($bytes === false) {
                    $error = socket_strerror(socket_last_error($this->socket));
                    throw new Exception("Socket recv failed: $error");
                }
                
                if ($this->verbose) {
                    echo "Received $bytes bytes\n";
                    if ($this->tcp_data_recv) {
                        echo "Recv hex: " . bin2hex($this->tcp_data_recv) . "\n";
                    }
                }
                
                $this->tcp_length = $this->testTcpTop($this->tcp_data_recv);
                if ($this->tcp_length == 0) {
                    throw new Exception("TCP packet invalid (received $bytes bytes, expected TCP header 5050xx7d)");
                }
                
                // Skip TCP header (8 bytes) to get to packet data
                $this->header = unpack('v4', substr($this->tcp_data_recv, 8, 8));
                $this->data_recv = substr($this->tcp_data_recv, 8);
            } else {
                socket_sendto($this->socket, $buf, strlen($buf), 0, $this->ip, $this->port);
                $this->data_recv = '';
                socket_recv($this->socket, $this->data_recv, $response_size, 0);
                $this->header = unpack('v4', substr($this->data_recv, 0, 8));
            }
        } catch (Exception $e) {
            throw new Exception("Network error: " . $e->getMessage());
        }
        
        $this->response = $this->header[1];
        $this->reply_id = $this->header[4];
        $this->data = substr($this->data_recv, 8);
        
        if ($this->verbose) {
            echo "Command: $command, Response: {$this->response}\n";
        }
        
        if (in_array($this->response, [self::CMD_ACK_OK, self::CMD_PREPARE_DATA, self::CMD_DATA])) {
            return ['status' => true, 'code' => $this->response];
        }
        
        return ['status' => false, 'code' => $this->response];
    }
    
    /**
     * Send ACK OK response - matches pyzk __ack_ok
     */
    private function ackOk() {
        $buf = $this->createHeader(self::CMD_ACK_OK, '', $this->session_id, self::USHRT_MAX - 1);
        
        if ($this->tcp) {
            $top = $this->createTcpTop($buf);
            socket_send($this->socket, $top, strlen($top), 0);
        } else {
            socket_sendto($this->socket, $buf, strlen($buf), 0, $this->ip, $this->port);
        }
    }
    
    /**
     * Get data size from CMD_PREPARE_DATA response - matches pyzk __get_data_size
     */
    private function getDataSize() {
        if ($this->response == self::CMD_PREPARE_DATA) {
            return unpack('V', substr($this->data, 0, 4))[1];
        }
        return 0;
    }
    
    /**
     * Decode time from 4-byte timestamp - matches pyzk __decode_time exactly
     */
    private function decodeTime($t) {
        // Handle binary string input
        if (is_string($t) && strlen($t) >= 4) {
            $t = unpack('V', substr($t, 0, 4))[1];
        }
        
        $t = intval($t);
        
        $second = $t % 60;
        $t = intval($t / 60);
        
        $minute = $t % 60;
        $t = intval($t / 60);
        
        $hour = $t % 24;
        $t = intval($t / 24);
        
        $day = ($t % 31) + 1;
        $t = intval($t / 31);
        
        $month = ($t % 12) + 1;
        $t = intval($t / 12);
        
        $year = $t + 2000;
        
        // Validate date components
        $month = max(1, min(12, $month));
        $day = max(1, min(31, $day));
        
        // Fix invalid day for month
        while (!checkdate($month, $day, $year) && $day > 1) {
            $day--;
        }
        
        return new DateTime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second));
    }
    
    /**
     * Encode time for device - matches pyzk __encode_time
     */
    private function encodeTime($t) {
        if ($t instanceof DateTime) {
            $year = (int)$t->format('Y');
            $month = (int)$t->format('m');
            $day = (int)$t->format('d');
            $hour = (int)$t->format('H');
            $minute = (int)$t->format('i');
            $second = (int)$t->format('s');
        } else {
            $year = (int)date('Y', $t);
            $month = (int)date('m', $t);
            $day = (int)date('d', $t);
            $hour = (int)date('H', $t);
            $minute = (int)date('i', $t);
            $second = (int)date('s', $t);
        }
        
        return ((($year % 100) * 12 * 31 + (($month - 1) * 31) + $day - 1) * (24 * 60 * 60) + ($hour * 60 + $minute) * 60 + $second);
    }
    
    /**
     * Make communication key - matches pyzk make_commkey exactly
     */
    private function makeCommkey($key, $session_id, $ticks = 50) {
        $key = intval($key);
        $session_id = intval($session_id);
        
        $k = 0;
        for ($i = 0; $i < 32; $i++) {
            if (($key & (1 << $i))) {
                $k = ($k << 1) | 1;
            } else {
                $k = $k << 1;
            }
        }
        $k += $session_id;
        
        // Pack as unsigned 32-bit int, unpack as 4 bytes
        $k = pack('V', $k);
        $k = array_values(unpack('C4', $k));
        
        // XOR with 'ZKSO'
        $k = pack('C4', 
            $k[0] ^ ord('Z'),
            $k[1] ^ ord('K'),
            $k[2] ^ ord('S'),
            $k[3] ^ ord('O')
        );
        
        // Swap the two 16-bit halves
        $k = unpack('v2', $k);
        $k = pack('v2', $k[2], $k[1]);
        
        // XOR with ticks
        $B = 0xFF & $ticks;
        $k = array_values(unpack('C4', $k));
        $k = pack('C4',
            $k[0] ^ $B,
            $k[1] ^ $B,
            $B,
            $k[3] ^ $B
        );
        
        return $k;
    }
    
    /**
     * Connect to device - matches pyzk connect
     */
    public function connect() {
        $this->createSocket();
        $this->session_id = 0;
        $this->reply_id = self::USHRT_MAX - 1;
        
        $cmd_response = $this->sendCommand(self::CMD_CONNECT);
        $this->session_id = $this->header[3];
        
        if ($cmd_response['code'] == self::CMD_ACK_UNAUTH) {
            if ($this->verbose) {
                echo "Authentication required...\n";
            }
            $command_string = $this->makeCommkey($this->password, $this->session_id);
            $cmd_response = $this->sendCommand(self::CMD_AUTH, $command_string);
        }
        
        if ($cmd_response['status']) {
            $this->is_connect = true;
            if ($this->verbose) {
                echo "✅ Authentication successful!\n";
            }
            return true;
        }
        
        throw new Exception("Connection failed");
    }
    
    /**
     * Disconnect from device - matches pyzk disconnect
     */
    public function disconnect() {
        if ($this->is_connect) {
            try {
                $this->sendCommand(self::CMD_EXIT);
            } catch (Exception $e) {
                // Ignore disconnect errors
            }
            $this->is_connect = false;
        }
        
        if ($this->socket) {
            @socket_close($this->socket);
            $this->socket = null;
        }
        
        return true;
    }
    
    /**
     * Enable device - matches pyzk enable_device
     */
    public function enableDevice() {
        $cmd_response = $this->sendCommand(self::CMD_ENABLEDEVICE);
        return $cmd_response['status'];
    }
    
    /**
     * Disable device - matches pyzk disable_device
     */
    public function disableDevice() {
        $cmd_response = $this->sendCommand(self::CMD_DISABLEDEVICE);
        return $cmd_response['status'];
    }
    
    /**
     * Read device sizes - matches pyzk read_sizes
     */
    public function readSizes() {
        $cmd_response = $this->sendCommand(self::CMD_GET_FREE_SIZES, '', 1024);
        
        if ($cmd_response['status']) {
            $size = strlen($this->data);
            
            if ($size >= 80) {
                $fields = unpack('V20', $this->data);
                $this->users = $fields[5];
                $this->fingers = $fields[7];
                $this->records = $fields[9];
                $this->cards = $fields[13];
                $this->fingers_cap = $fields[15];
                $this->users_cap = $fields[16];
                $this->rec_cap = $fields[17];
                
                if ($this->verbose) {
                    echo "Device reports {$this->records} records\n";
                }
            }
            
            if ($size >= 92) {
                $face_fields = unpack('V3', substr($this->data, 80, 12));
                $this->faces = $face_fields[1];
                $this->faces_cap = $face_fields[3];
            }
            
            return true;
        }
        
        throw new Exception("Can't read sizes");
    }
    
    /**
     * Free data buffer - matches pyzk free_data
     */
    public function freeData() {
        $cmd_response = $this->sendCommand(self::CMD_FREE_DATA);
        return $cmd_response['status'];
    }
    
    /**
     * Receive raw data - matches pyzk __recieve_raw_data
     */
    private function receiveRawData($size) {
        $data = [];
        
        if ($this->verbose) {
            echo "Expecting $size bytes raw data\n";
        }
        
        while ($size > 0) {
            $chunk = '';
            $received = socket_recv($this->socket, $chunk, $size, MSG_WAITALL);
            
            if ($received === false || $received === 0) {
                break;
            }
            
            $data[] = $chunk;
            $size -= $received;
            
            if ($this->verbose) {
                echo "Partial recv: $received bytes, still need: $size\n";
            }
        }
        
        return implode('', $data);
    }
    
    /**
     * Receive TCP data - matches pyzk __recieve_tcp_data
     */
    private function receiveTcpData($data_recv, $size) {
        $data = [];
        
        $tcp_length = $this->testTcpTop($data_recv);
        
        if ($this->verbose) {
            echo "tcp_length: $tcp_length, size: $size\n";
        }
        
        if ($tcp_length <= 0) {
            if ($this->verbose) {
                echo "Incorrect tcp packet\n";
            }
            return [null, ''];
        }
        
        if (($tcp_length - 8) < $size) {
            if ($this->verbose) {
                echo "tcp length too small... retrying\n";
            }
            list($resp, $bh) = $this->receiveTcpData($data_recv, $tcp_length - 8);
            $data[] = $resp;
            $size -= strlen($resp);
            
            if ($this->verbose) {
                echo "new tcp DATA packet to fill missing $size\n";
            }
            
            $more = '';
            socket_recv($this->socket, $more, $size + 16, MSG_WAITALL);
            $data_recv = $bh . $more;
            
            list($resp, $bh) = $this->receiveTcpData($data_recv, $size);
            $data[] = $resp;
            
            return [implode('', $data), $bh];
        }
        
        $received = strlen($data_recv);
        
        if ($this->verbose) {
            echo "received: $received, size: $size\n";
        }
        
        $response = unpack('v4', substr($data_recv, 8, 8))[1];
        
        if ($received >= ($size + 32)) {
            if ($response == self::CMD_DATA) {
                $resp = substr($data_recv, 16, $size);
                return [$resp, substr($data_recv, $size + 16)];
            } else {
                if ($this->verbose) {
                    echo "incorrect response: $response\n";
                }
                return [null, ''];
            }
        } else {
            if ($response == self::CMD_DATA) {
                $resp = substr($data_recv, 16);
                $need = $size - strlen($resp);
                $resp .= $this->receiveRawData($need);
                
                $broken_header = '';
                socket_recv($this->socket, $broken_header, 16, MSG_WAITALL);
                
                return [$resp, $broken_header];
            } else {
                if ($this->verbose) {
                    echo "incorrect response: $response\n";
                }
                return [null, ''];
            }
        }
    }
    
    /**
     * Receive chunk - matches pyzk __recieve_chunk
     */
    private function receiveChunk() {
        if ($this->response == self::CMD_DATA) {
            if ($this->tcp) {
                if ($this->verbose) {
                    echo "_rc_DATA! is " . strlen($this->data) . " bytes, tcp length is {$this->tcp_length}\n";
                }
                
                if (strlen($this->data) < ($this->tcp_length - 8)) {
                    $need = ($this->tcp_length - 8) - strlen($this->data);
                    if ($this->verbose) {
                        echo "need more data: $need\n";
                    }
                    $more_data = $this->receiveRawData($need);
                    return $this->data . $more_data;
                } else {
                    return $this->data;
                }
            } else {
                return $this->data;
            }
        } elseif ($this->response == self::CMD_PREPARE_DATA) {
            $data = [];
            $size = $this->getDataSize();
            
            if ($this->verbose) {
                echo "receive chunk: prepare data size is $size\n";
            }
            
            if ($this->tcp) {
                if (strlen($this->data) >= (8 + $size)) {
                    $data_recv = substr($this->data, 8);
                } else {
                    $more = '';
                    socket_recv($this->socket, $more, $size + 32, MSG_WAITALL);
                    $data_recv = substr($this->data, 8) . $more;
                }
                
                list($resp, $broken_header) = $this->receiveTcpData($data_recv, $size);
                $data[] = $resp;
                
                // Get CMD_ACK_OK
                if (strlen($broken_header) < 16) {
                    $more = '';
                    socket_recv($this->socket, $more, 16, MSG_WAITALL);
                    $data_recv = $broken_header . $more;
                } else {
                    $data_recv = $broken_header;
                }
                
                if (!$this->testTcpTop($data_recv)) {
                    if ($this->verbose) {
                        echo "invalid chunk tcp ACK OK\n";
                    }
                    return null;
                }
                
                $response = unpack('v4', substr($data_recv, 8, 8))[1];
                if ($response == self::CMD_ACK_OK) {
                    if ($this->verbose) {
                        echo "chunk tcp ACK OK!\n";
                    }
                    return implode('', $data);
                }
                
                if ($this->verbose) {
                    echo "bad response: $response\n";
                }
                return null;
            } else {
                // UDP mode
                while ($size > 0) {
                    $chunk = '';
                    socket_recv($this->socket, $chunk, 1024 + 8, 0);
                    $response = unpack('v4', $chunk)[1];
                    
                    if ($response == self::CMD_DATA) {
                        $data[] = substr($chunk, 8);
                        $size -= 1024;
                    } elseif ($response == self::CMD_ACK_OK) {
                        break;
                    } else {
                        break;
                    }
                }
                return implode('', $data);
            }
        } else {
            if ($this->verbose) {
                echo "invalid response: {$this->response}\n";
            }
            return null;
        }
    }
    
    /**
     * Read chunk from buffer - matches pyzk __read_chunk
     */
    private function readChunk($start, $size) {
        for ($retries = 0; $retries < 3; $retries++) {
            $command_string = pack('VV', $start, $size);
            
            if ($this->tcp) {
                $response_size = $size + 32;
            } else {
                $response_size = 1024 + 8;
            }
            
            $cmd_response = $this->sendCommand(self::CMD_READ_BUFFER, $command_string, $response_size);
            $data = $this->receiveChunk();
            
            if ($data !== null) {
                return $data;
            }
        }
        
        throw new Exception("Can't read chunk at $start:$size");
    }
    
    /**
     * Read with buffer - matches pyzk read_with_buffer
     * This is the key method for reading large datasets
     */
    public function readWithBuffer($command, $fct = 0, $ext = 0) {
        if ($this->tcp) {
            $MAX_CHUNK = 0xFFC0;  // ~65KB for TCP
        } else {
            $MAX_CHUNK = 16 * 1024;  // 16KB for UDP
        }
        
        $command_string = pack('cvVV', 1, $command, $fct, $ext);
        
        if ($this->verbose) {
            echo "rwb command_string: " . bin2hex($command_string) . "\n";
        }
        
        $response_size = 1024;
        $data = [];
        $start = 0;
        
        $cmd_response = $this->sendCommand(self::CMD_PREPARE_BUFFER, $command_string, $response_size);
        
        if (!$cmd_response['status']) {
            throw new Exception("RWB Not supported");
        }
        
        if ($cmd_response['code'] == self::CMD_DATA) {
            if ($this->tcp) {
                if ($this->verbose) {
                    echo "DATA! is " . strlen($this->data) . " bytes, tcp length is {$this->tcp_length}\n";
                }
                
                if (strlen($this->data) < ($this->tcp_length - 8)) {
                    $need = ($this->tcp_length - 8) - strlen($this->data);
                    if ($this->verbose) {
                        echo "need more data: $need\n";
                    }
                    $more_data = $this->receiveRawData($need);
                    return [$this->data . $more_data, strlen($this->data) + strlen($more_data)];
                } else {
                    if ($this->verbose) {
                        echo "Enough data\n";
                    }
                    return [$this->data, strlen($this->data)];
                }
            } else {
                return [$this->data, strlen($this->data)];
            }
        }
        
        // CMD_ACK_OK with buffer info
        $size = unpack('V', substr($this->data, 1, 4))[1];
        
        if ($this->verbose) {
            echo "size will be $size\n";
        }
        
        $remain = $size % $MAX_CHUNK;
        $packets = intval(($size - $remain) / $MAX_CHUNK);
        
        if ($this->verbose) {
            echo "rwb: #$packets packets of max $MAX_CHUNK bytes, and extra $remain bytes remain\n";
        }
        
        for ($i = 0; $i < $packets; $i++) {
            $data[] = $this->readChunk($start, $MAX_CHUNK);
            $start += $MAX_CHUNK;
        }
        
        if ($remain) {
            $data[] = $this->readChunk($start, $remain);
            $start += $remain;
        }
        
        $this->freeData();
        
        if ($this->verbose) {
            echo "_read w/chunk $start bytes\n";
        }
        
        return [implode('', $data), $start];
    }
    
    /**
     * Get attendance records - matches pyzk get_attendance exactly
     */
    public function getAttendance() {
        $this->readSizes();
        
        if ($this->records == 0) {
            return [];
        }
        
        if ($this->verbose) {
            echo "Reading {$this->records} attendance records...\n";
        }
        
        $attendances = [];
        
        list($attendance_data, $size) = $this->readWithBuffer(self::CMD_ATTLOG_RRQ);
        
        if ($size < 4) {
            if ($this->verbose) {
                echo "WRN: no attendance data\n";
            }
            return [];
        }
        
        $total_size = unpack('V', substr($attendance_data, 0, 4))[1];
        $record_size = intval($total_size / $this->records);
        
        if ($this->verbose) {
            echo "record_size is $record_size\n";
        }
        
        $attendance_data = substr($attendance_data, 4);
        
        // Parse based on record size - exactly matching pyzk
        if ($record_size == 8) {
            // 8-byte record format
            while (strlen($attendance_data) >= 8) {
                $record = substr($attendance_data, 0, 8);
                $unpacked = unpack('vuid/Cstatus/a4timestamp/Cpunch', $record);
                
                $uid = $unpacked['uid'];
                $user_id = (string)$uid;
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                
                $attendances[] = [
                    'uid' => $uid,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s'),
                    'status' => $unpacked['status'],
                    'punch' => $unpacked['punch']
                ];
                
                $attendance_data = substr($attendance_data, 8);
            }
        } elseif ($record_size == 16) {
            // 16-byte record format
            while (strlen($attendance_data) >= 16) {
                $record = substr($attendance_data, 0, 16);
                $unpacked = unpack('Vuser_id/a4timestamp/Cstatus/Cpunch/a2reserved/Vworkcode', $record);
                
                $user_id = (string)$unpacked['user_id'];
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                
                $attendances[] = [
                    'uid' => $unpacked['user_id'],
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s'),
                    'status' => $unpacked['status'],
                    'punch' => $unpacked['punch'],
                    'workcode' => $unpacked['workcode']
                ];
                
                $attendance_data = substr($attendance_data, 16);
            }
        } else {
            // 40-byte record format (most common for newer devices)
            while (strlen($attendance_data) >= 40) {
                $record = str_pad(substr($attendance_data, 0, 40), 40, "\x00");
                $unpacked = unpack('vuid/a24user_id/Cstatus/a4timestamp/Cpunch/a8reserved', $record);
                
                $uid = $unpacked['uid'];
                $user_id = rtrim($unpacked['user_id'], "\x00");
                
                if (empty($user_id)) {
                    $user_id = (string)$uid;
                }
                
                $timestamp = $this->decodeTime($unpacked['timestamp']);
                
                $attendances[] = [
                    'uid' => $uid,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s'),
                    'status' => $unpacked['status'],
                    'punch' => $unpacked['punch']
                ];
                
                $attendance_data = substr($attendance_data, $record_size);
            }
        }
        
        if ($this->verbose) {
            echo "✅ Parsed " . count($attendances) . " attendance records\n";
            if (count($attendances) > 0) {
                echo "Sample: {$attendances[0]['user_id']} at {$attendances[0]['timestamp']}\n";
            }
        }
        
        return $attendances;
    }
    
    /**
     * Get users - matches pyzk get_users
     */
    public function getUsers() {
        $this->readSizes();
        
        if ($this->users == 0) {
            $this->next_uid = 1;
            $this->next_user_id = '1';
            return [];
        }
        
        $users = [];
        $max_uid = 0;
        
        list($userdata, $size) = $this->readWithBuffer(self::CMD_USERTEMP_RRQ, self::FCT_USER);
        
        if ($this->verbose) {
            echo "user size $size (= " . strlen($userdata) . ")\n";
        }
        
        if ($size <= 4) {
            if ($this->verbose) {
                echo "WRN: missing user data\n";
            }
            return [];
        }
        
        $total_size = unpack('V', substr($userdata, 0, 4))[1];
        $this->user_packet_size = intval($total_size / $this->users);
        
        if (!in_array($this->user_packet_size, [28, 72])) {
            if ($this->verbose) {
                echo "WRN packet size would be {$this->user_packet_size}\n";
            }
        }
        
        $userdata = substr($userdata, 4);
        
        if ($this->user_packet_size == 28) {
            while (strlen($userdata) >= 28) {
                $record = substr($userdata, 0, 28);
                $unpacked = unpack('vuid/Cprivilege/a8password/a8name/Vcard/Cgroup/vtimezone/a4slot', $record);
                
                $uid = $unpacked['uid'];
                $user_id = (string)$uid;
                $name = rtrim($unpacked['name'], "\x00");
                $password = rtrim($unpacked['password'], "\x00");
                
                if ($uid > $max_uid) {
                    $max_uid = $uid;
                }
                
                $users[] = [
                    'uid' => $uid,
                    'user_id' => $user_id,
                    'name' => $name,
                    'privilege' => $unpacked['privilege'],
                    'password' => $password,
                    'group_id' => (string)$unpacked['group'],
                    'card' => $unpacked['card']
                ];
                
                $userdata = substr($userdata, 28);
            }
        } else {
            // 72-byte record format
            while (strlen($userdata) >= 72) {
                $record = substr($userdata, 0, 72);
                $unpacked = unpack('vuid/Cprivilege/a8password/a24name/Vcard/Cgroup/vtimezone/a9user_id/a15slot', $record);
                
                $uid = $unpacked['uid'];
                $user_id = rtrim($unpacked['user_id'], "\x00");
                
                if (empty($user_id)) {
                    $user_id = (string)$uid;
                }
                
                $name = rtrim($unpacked['name'], "\x00");
                $password = rtrim($unpacked['password'], "\x00");
                
                if ($uid > $max_uid) {
                    $max_uid = $uid;
                }
                
                $users[] = [
                    'uid' => $uid,
                    'user_id' => $user_id,
                    'name' => $name,
                    'privilege' => $unpacked['privilege'],
                    'password' => $password,
                    'group_id' => (string)$unpacked['group'],
                    'card' => $unpacked['card']
                ];
                
                $userdata = substr($userdata, 72);
            }
        }
        
        $this->next_uid = $max_uid + 1;
        $this->next_user_id = (string)($max_uid + 1);
        
        if ($this->verbose) {
            echo "✅ Found " . count($users) . " users\n";
        }
        
        return $users;
    }
    
    /**
     * Get device time - matches pyzk get_time
     */
    public function getTime() {
        $cmd_response = $this->sendCommand(self::CMD_GET_TIME, '', 1032);
        
        if ($cmd_response['status']) {
            return $this->decodeTime(substr($this->data, 0, 4));
        }
        
        throw new Exception("Can't get time");
    }
    
    /**
     * Set device time - matches pyzk set_time
     */
    public function setTime($timestamp = null) {
        if ($timestamp === null) {
            $timestamp = new DateTime();
        }
        
        $command_string = pack('V', $this->encodeTime($timestamp));
        $cmd_response = $this->sendCommand(self::CMD_SET_TIME, $command_string);
        
        if ($cmd_response['status']) {
            return true;
        }
        
        throw new Exception("Can't set time");
    }
    
    /**
     * Get firmware version - matches pyzk get_firmware_version
     */
    public function getFirmwareVersion() {
        $cmd_response = $this->sendCommand(self::CMD_GET_VERSION, '', 1024);
        
        if ($cmd_response['status']) {
            $version = explode("\x00", $this->data)[0];
            return $version;
        }
        
        return null;
    }
    
    /**
     * Get serial number - matches pyzk get_serialnumber
     */
    public function getSerialNumber() {
        $cmd_response = $this->sendCommand(self::CMD_OPTIONS_RRQ, "~SerialNumber\x00", 1024);
        
        if ($cmd_response['status']) {
            $parts = explode('=', $this->data, 2);
            if (count($parts) > 1) {
                return rtrim($parts[1], "\x00");
            }
            return rtrim($this->data, "\x00");
        }
        
        throw new Exception("Can't read serial number");
    }
    
    /**
     * Get device name - matches pyzk get_device_name
     */
    public function getDeviceName() {
        $cmd_response = $this->sendCommand(self::CMD_OPTIONS_RRQ, "~DeviceName\x00", 1024);
        
        if ($cmd_response['status']) {
            $parts = explode('=', $this->data, 2);
            if (count($parts) > 1) {
                return rtrim($parts[1], "\x00");
            }
            return rtrim($this->data, "\x00");
        }
        
        return "";
    }
    
    /**
     * Get platform - matches pyzk get_platform
     */
    public function getPlatform() {
        $cmd_response = $this->sendCommand(self::CMD_OPTIONS_RRQ, "~Platform\x00", 1024);
        
        if ($cmd_response['status']) {
            $parts = explode('=', $this->data, 2);
            if (count($parts) > 1) {
                return rtrim($parts[1], "\x00=");
            }
            return rtrim($this->data, "\x00=");
        }
        
        throw new Exception("Can't read platform");
    }
    
    /**
     * Get MAC address - matches pyzk get_mac
     */
    public function getMac() {
        $cmd_response = $this->sendCommand(self::CMD_OPTIONS_RRQ, "MAC\x00", 1024);
        
        if ($cmd_response['status']) {
            $parts = explode('=', $this->data, 2);
            if (count($parts) > 1) {
                return rtrim($parts[1], "\x00");
            }
            return rtrim($this->data, "\x00");
        }
        
        throw new Exception("Can't read MAC address");
    }
    
    /**
     * Clear all data - matches pyzk clear_data
     */
    public function clearData() {
        $cmd_response = $this->sendCommand(self::CMD_CLEAR_DATA);
        
        if ($cmd_response['status']) {
            $this->next_uid = 1;
            return true;
        }
        
        throw new Exception("Can't clear data");
    }
    
    /**
     * Clear attendance records - matches pyzk clear_attendance
     */
    public function clearAttendance() {
        $cmd_response = $this->sendCommand(self::CMD_CLEAR_ATTLOG);
        
        if ($cmd_response['status']) {
            return true;
        }
        
        throw new Exception("Can't clear attendance");
    }
    
    /**
     * Restart device - matches pyzk restart
     */
    public function restart() {
        $cmd_response = $this->sendCommand(self::CMD_RESTART);
        return $cmd_response['status'];
    }
    
    /**
     * Power off device - matches pyzk poweroff
     */
    public function poweroff() {
        $cmd_response = $this->sendCommand(self::CMD_POWEROFF);
        return $cmd_response['status'];
    }
    
    /**
     * Unlock door - matches pyzk unlock
     */
    public function unlock($time = 3) {
        $command_string = pack('V', $time * 10);
        $cmd_response = $this->sendCommand(31, $command_string);  // CMD_UNLOCK = 31
        return $cmd_response['status'];
    }
    
    /**
     * Test voice - matches pyzk test_voice
     */
    public function testVoice($index = 0) {
        $command_string = pack('V', $index);
        $cmd_response = $this->sendCommand(1017, $command_string);  // CMD_TESTVOICE = 1017
        return $cmd_response['status'];
    }
    
    /**
     * Refresh data - matches pyzk refresh_data
     */
    public function refreshData() {
        $cmd_response = $this->sendCommand(1013);  // CMD_REFRESHDATA = 1013
        return $cmd_response['status'];
    }
    
    /**
     * Export attendance data to CSV
     */
    public function exportAttendanceToCsv($filename) {
        $attendance = $this->getAttendance();
        
        $fp = fopen($filename, 'w');
        fputcsv($fp, ['UserID', 'Timestamp', 'Status', 'UID']);
        
        foreach ($attendance as $record) {
            fputcsv($fp, [
                $record['user_id'],
                $record['timestamp'],
                $record['status'],
                $record['uid']
            ]);
        }
        
        fclose($fp);
        
        return count($attendance);
    }
    
    /**
     * Export users to CSV
     */
    public function exportUsersToCsv($filename) {
        $users = $this->getUsers();
        
        $fp = fopen($filename, 'w');
        fputcsv($fp, ['UID', 'UserID', 'Name', 'Privilege', 'Card']);
        
        foreach ($users as $user) {
            fputcsv($fp, [
                $user['uid'],
                $user['user_id'],
                $user['name'],
                $user['privilege'],
                $user['card']
            ]);
        }
        
        fclose($fp);
        
        return count($users);
    }
    
    /**
     * Get all device info
     */
    public function getDeviceInfo() {
        return [
            'firmware_version' => $this->getFirmwareVersion(),
            'serial_number' => $this->getSerialNumber(),
            'device_name' => $this->getDeviceName(),
            'platform' => $this->getPlatform(),
            'mac' => $this->getMac(),
            'users' => $this->users,
            'fingers' => $this->fingers,
            'records' => $this->records,
            'cards' => $this->cards,
            'faces' => $this->faces
        ];
    }
    
    /**
     * Check connection status
     */
    public function isConnected() {
        return $this->is_connect;
    }
}
