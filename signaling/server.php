<?php
/**
 * Simple WebSocket Signaling Server for WebRTC
 * This server coordinates WebRTC peer connections between participants
 */

class SignalingServer {
    private $sessions = [];
    private $connections = [];
    private $port;
    
    public function __construct($port = 8080) {
        $this->port = $port;
    }
    
    public function start() {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, 'localhost', $this->port);
        socket_listen($server);
        
        echo "Signaling server started on port {$this->port}\n";
        
        while (true) {
            $client = socket_accept($server);
            
            if ($client) {
                $this->handleNewConnection($client);
            }
        }
    }
    
    private function handleNewConnection($socket) {
        // Perform WebSocket handshake
        $request = socket_read($socket, 1024);
        $headers = $this->parseHeaders($request);
        
        if (isset($headers['Sec-WebSocket-Key'])) {
            $response = $this->createHandshakeResponse($headers['Sec-WebSocket-Key']);
            socket_write($socket, $response);
            
            $connectionId = uniqid();
            $this->connections[$connectionId] = [
                'socket' => $socket,
                'sessionId' => null,
                'userId' => null,
                'userRole' => null
            ];
            
            echo "New connection: {$connectionId}\n";
            
            // Start listening for messages from this client
            $this->listenToClient($connectionId);
        } else {
            socket_close($socket);
        }
    }
    
    private function parseHeaders($request) {
        $headers = [];
        $lines = explode("\r\n", $request);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return $headers;
    }
    
    private function createHandshakeResponse($key) {
        $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        return "HTTP/1.1 101 Switching Protocols\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";
    }
    
    private function listenToClient($connectionId) {
        $connection = $this->connections[$connectionId];
        $socket = $connection['socket'];
        
        // Set socket to non-blocking
        socket_set_nonblock($socket);
        
        while (true) {
            $data = @socket_read($socket, 1024);
            
            if ($data === false) {
                $error = socket_last_error($socket);
                if ($error === SOCKET_EWOULDBLOCK || $error === SOCKET_EAGAIN) {
                    // No data available, continue
                    usleep(10000); // 10ms
                    continue;
                } else {
                    // Connection closed or error
                    $this->handleDisconnection($connectionId);
                    break;
                }
            }
            
            if ($data) {
                $message = $this->decodeWebSocketFrame($data);
                if ($message) {
                    $this->handleMessage($connectionId, $message);
                }
            }
        }
    }
    
    private function decodeWebSocketFrame($data) {
        if (strlen($data) < 2) return false;
        
        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);
        
        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte & 0x80) === 0x80;
        $payloadLength = $secondByte & 0x7F;
        
        $offset = 2;
        
        if ($payloadLength === 126) {
            $payloadLength = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            $payloadLength = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }
        
        if ($masked) {
            $maskingKey = substr($data, $offset, 4);
            $offset += 4;
            $payload = substr($data, $offset, $payloadLength);
            
            // Unmask payload
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = $payload[$i] ^ $maskingKey[$i % 4];
            }
        } else {
            $payload = substr($data, $offset, $payloadLength);
        }
        
        return $payload;
    }
    
    private function encodeWebSocketFrame($payload) {
        $payloadLength = strlen($payload);
        
        if ($payloadLength < 126) {
            $frame = chr(0x81) . chr($payloadLength);
        } elseif ($payloadLength < 65536) {
            $frame = chr(0x81) . chr(126) . pack('n', $payloadLength);
        } else {
            $frame = chr(0x81) . chr(127) . pack('J', $payloadLength);
        }
        
        return $frame . $payload;
    }
    
    private function handleMessage($connectionId, $message) {
        $data = json_decode($message, true);
        
        if (!$data) return;
        
        echo "Message from {$connectionId}: " . json_encode($data) . "\n";
        
        switch ($data['type']) {
            case 'join':
                $this->handleJoin($connectionId, $data);
                break;
                
            case 'offer':
            case 'answer':
            case 'ice-candidate':
                $this->relayMessage($connectionId, $data);
                break;
                
            case 'leave':
                $this->handleLeave($connectionId);
                break;
        }
    }
    
    private function handleJoin($connectionId, $data) {
        $sessionId = $data['sessionId'];
        $userId = $data['userId'];
        $userRole = $data['userRole'];
        
        // Update connection info
        $this->connections[$connectionId]['sessionId'] = $sessionId;
        $this->connections[$connectionId]['userId'] = $userId;
        $this->connections[$connectionId]['userRole'] = $userRole;
        
        // Add to session
        if (!isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId] = [];
        }
        
        $this->sessions[$sessionId][$connectionId] = [
            'userId' => $userId,
            'userRole' => $userRole
        ];
        
        // Notify existing participants about new user
        $this->broadcastToSession($sessionId, [
            'type' => 'user-joined',
            'userId' => $userId,
            'userRole' => $userRole
        ], $connectionId);
        
        echo "User {$userId} ({$userRole}) joined session {$sessionId}\n";
    }
    
    private function relayMessage($fromConnectionId, $data) {
        $targetUserId = $data['targetUserId'] ?? null;
        $sessionId = $this->connections[$fromConnectionId]['sessionId'];
        
        if (!$sessionId) return;
        
        // Add sender info
        $data['fromUserId'] = $this->connections[$fromConnectionId]['userId'];
        unset($data['targetUserId']);
        
        if ($targetUserId) {
            // Send to specific user
            $this->sendToUser($sessionId, $targetUserId, $data);
        } else {
            // Broadcast to all users in session except sender
            $this->broadcastToSession($sessionId, $data, $fromConnectionId);
        }
    }
    
    private function sendToUser($sessionId, $targetUserId, $message) {
        if (!isset($this->sessions[$sessionId])) return;
        
        foreach ($this->sessions[$sessionId] as $connectionId => $info) {
            if ($info['userId'] === $targetUserId) {
                $this->sendMessage($connectionId, $message);
                break;
            }
        }
    }
    
    private function broadcastToSession($sessionId, $message, $excludeConnectionId = null) {
        if (!isset($this->sessions[$sessionId])) return;
        
        foreach ($this->sessions[$sessionId] as $connectionId => $info) {
            if ($connectionId !== $excludeConnectionId) {
                $this->sendMessage($connectionId, $message);
            }
        }
    }
    
    private function sendMessage($connectionId, $message) {
        if (!isset($this->connections[$connectionId])) return;
        
        $socket = $this->connections[$connectionId]['socket'];
        $frame = $this->encodeWebSocketFrame(json_encode($message));
        
        @socket_write($socket, $frame);
    }
    
    private function handleLeave($connectionId) {
        $this->handleDisconnection($connectionId);
    }
    
    private function handleDisconnection($connectionId) {
        if (!isset($this->connections[$connectionId])) return;
        
        $connection = $this->connections[$connectionId];
        $sessionId = $connection['sessionId'];
        $userId = $connection['userId'];
        
        // Remove from session
        if ($sessionId && isset($this->sessions[$sessionId][$connectionId])) {
            unset($this->sessions[$sessionId][$connectionId]);
            
            // Notify other participants
            $this->broadcastToSession($sessionId, [
                'type' => 'user-left',
                'userId' => $userId
            ]);
            
            // Clean up empty sessions
            if (empty($this->sessions[$sessionId])) {
                unset($this->sessions[$sessionId]);
            }
        }
        
        // Close socket and remove connection
        @socket_close($connection['socket']);
        unset($this->connections[$connectionId]);
        
        echo "Connection {$connectionId} disconnected\n";
    }
}

// Start the server
if (php_sapi_name() === 'cli') {
    $server = new SignalingServer(8080);
    $server->start();
} else {
    echo "This script must be run from command line\n";
}
?>