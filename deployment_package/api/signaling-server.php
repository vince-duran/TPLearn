<?php
/**
 * Simple WebSocket-based Signaling Server for TPLearn Video Conferencing
 * This is a lightweight signaling server that can work without Socket.IO
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple in-memory storage (in production, use Redis or database)
$rooms_file = __DIR__ . '/../../data/video_rooms.json';
$connections_file = __DIR__ . '/../../data/video_connections.json';

// Ensure data directory exists
$data_dir = dirname($rooms_file);
if (!is_dir($data_dir)) {
    if (!mkdir($data_dir, 0755, true)) {
        error_log("Failed to create data directory: $data_dir");
        echo json_encode(['success' => false, 'error' => 'Server configuration error']);
        exit();
    }
}

// Ensure required files exist with proper structure
if (!file_exists($rooms_file)) {
    file_put_contents($rooms_file, '{}');
}
if (!file_exists($connections_file)) {
    file_put_contents($connections_file, '{}');
}

function loadRooms() {
    global $rooms_file;
    if (file_exists($rooms_file)) {
        $data = file_get_contents($rooms_file);
        return json_decode($data, true) ?: [];
    }
    return [];
}

function saveRooms($rooms) {
    global $rooms_file;
    file_put_contents($rooms_file, json_encode($rooms, JSON_PRETTY_PRINT));
}

function loadConnections() {
    global $connections_file;
    if (file_exists($connections_file)) {
        $data = file_get_contents($connections_file);
        return json_decode($data, true) ?: [];
    }
    return [];
}

function saveConnections($connections) {
    global $connections_file;
    file_put_contents($connections_file, json_encode($connections, JSON_PRETTY_PRINT));
}

function generatePeerId() {
    return 'peer_' . uniqid() . '_' . rand(1000, 9999);
}

function cleanupExpiredConnections() {
    $connections = loadConnections();
    $currentTime = time();
    $cleaned = false;
    
    foreach ($connections as $peerId => $data) {
        // Remove connections older than 5 minutes
        if (($currentTime - $data['timestamp']) > 300) {
            unset($connections[$peerId]);
            $cleaned = true;
        }
    }
    
    if ($cleaned) {
        saveConnections($connections);
    }
}

// Clean up expired connections periodically
cleanupExpiredConnections();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'error' => 'Unknown action'];

switch ($action) {
    case 'connect':
        $peerId = generatePeerId();
        $connections = loadConnections();
        $connections[$peerId] = [
            'timestamp' => time(),
            'room' => null,
            'status' => 'connected'
        ];
        saveConnections($connections);
        
        $response = [
            'success' => true,
            'peerId' => $peerId,
            'message' => 'Connected to signaling server'
        ];
        break;
        
    case 'join-room':
        $peerId = $_POST['peerId'] ?? '';
        $roomName = $_POST['room'] ?? '';
        
        if (empty($peerId) || empty($roomName)) {
            $response = ['success' => false, 'error' => 'Missing peerId or room name'];
            break;
        }
        
        $rooms = loadRooms();
        $connections = loadConnections();
        
        // Initialize room if it doesn't exist
        if (!isset($rooms[$roomName])) {
            $rooms[$roomName] = [
                'peers' => [],
                'created' => time()
            ];
        }
        
        // Add peer to room
        if (!in_array($peerId, $rooms[$roomName]['peers'])) {
            $rooms[$roomName]['peers'][] = $peerId;
        }
        
        // Update connection info
        if (isset($connections[$peerId])) {
            $connections[$peerId]['room'] = $roomName;
            $connections[$peerId]['timestamp'] = time();
        }
        
        saveRooms($rooms);
        saveConnections($connections);
        
        $response = [
            'success' => true,
            'room' => $roomName,
            'peers' => $rooms[$roomName]['peers'],
            'message' => "Joined room: $roomName"
        ];
        break;
        
    case 'leave-room':
        $peerId = $_POST['peerId'] ?? '';
        $roomName = $_POST['room'] ?? '';
        
        if (empty($peerId) || empty($roomName)) {
            $response = ['success' => false, 'error' => 'Missing peerId or room name'];
            break;
        }
        
        $rooms = loadRooms();
        $connections = loadConnections();
        
        // Remove peer from room
        if (isset($rooms[$roomName])) {
            $rooms[$roomName]['peers'] = array_values(
                array_filter($rooms[$roomName]['peers'], function($p) use ($peerId) {
                    return $p !== $peerId;
                })
            );
            
            // Remove empty room
            if (empty($rooms[$roomName]['peers'])) {
                unset($rooms[$roomName]);
            }
        }
        
        // Update connection info
        if (isset($connections[$peerId])) {
            $connections[$peerId]['room'] = null;
            $connections[$peerId]['timestamp'] = time();
        }
        
        saveRooms($rooms);
        saveConnections($connections);
        
        $response = [
            'success' => true,
            'message' => "Left room: $roomName"
        ];
        break;
        
    case 'get-room-peers':
        $roomName = $_GET['room'] ?? '';
        
        if (empty($roomName)) {
            $response = ['success' => false, 'error' => 'Missing room name'];
            break;
        }
        
        $rooms = loadRooms();
        $peers = isset($rooms[$roomName]) ? $rooms[$roomName]['peers'] : [];
        
        $response = [
            'success' => true,
            'room' => $roomName,
            'peers' => $peers
        ];
        break;
        
    case 'send-message':
        // Store signaling message for peer-to-peer communication
        $fromPeer = $_POST['from'] ?? '';
        $toPeer = $_POST['to'] ?? '';
        $messageType = $_POST['type'] ?? '';
        $messageData = $_POST['data'] ?? '';
        
        if (empty($fromPeer) || empty($toPeer) || empty($messageType)) {
            $response = ['success' => false, 'error' => 'Missing required message fields'];
            break;
        }
        
        // In a real implementation, you would use WebSockets or Server-Sent Events
        // For this simple implementation, we'll store messages temporarily
        $messages_file = __DIR__ . '/../../data/peer_messages.json';
        $messages = [];
        
        if (file_exists($messages_file)) {
            $data = file_get_contents($messages_file);
            $messages = json_decode($data, true) ?: [];
        }
        
        // Clean old messages (keep only last 100)
        if (count($messages) > 100) {
            $messages = array_slice($messages, -50);
        }
        
        $messages[] = [
            'id' => uniqid(),
            'from' => $fromPeer,
            'to' => $toPeer,
            'type' => $messageType,
            'data' => $messageData,
            'timestamp' => time()
        ];
        
        file_put_contents($messages_file, json_encode($messages, JSON_PRETTY_PRINT));
        
        $response = [
            'success' => true,
            'message' => 'Message sent'
        ];
        break;
        
    case 'get-messages':
        // Get messages for a specific peer
        $peerId = $_GET['peerId'] ?? '';
        $since = intval($_GET['since'] ?? 0);
        
        if (empty($peerId)) {
            $response = ['success' => false, 'error' => 'Missing peerId'];
            break;
        }
        
        $messages_file = __DIR__ . '/../../data/peer_messages.json';
        $messages = [];
        
        if (file_exists($messages_file)) {
            $data = file_get_contents($messages_file);
            $allMessages = json_decode($data, true) ?: [];
            
            // Filter messages for this peer since timestamp
            $messages = array_filter($allMessages, function($msg) use ($peerId, $since) {
                return $msg['to'] === $peerId && $msg['timestamp'] > $since;
            });
            
            $messages = array_values($messages);
        }
        
        $response = [
            'success' => true,
            'messages' => $messages,
            'timestamp' => time()
        ];
        break;
        
    case 'heartbeat':
        $peerId = $_POST['peerId'] ?? '';
        
        if (empty($peerId)) {
            $response = ['success' => false, 'error' => 'Missing peerId'];
            break;
        }
        
        $connections = loadConnections();
        if (isset($connections[$peerId])) {
            $connections[$peerId]['timestamp'] = time();
            saveConnections($connections);
            
            $response = [
                'success' => true,
                'message' => 'Heartbeat received'
            ];
        } else {
            $response = ['success' => false, 'error' => 'Peer not found'];
        }
        break;
        
    case 'get-status':
        $rooms = loadRooms();
        $connections = loadConnections();
        
        $response = [
            'success' => true,
            'status' => [
                'rooms' => count($rooms),
                'connections' => count($connections),
                'timestamp' => time()
            ],
            'rooms' => $rooms
        ];
        break;
        
    default:
        $response = ['success' => false, 'error' => 'Invalid action'];
        break;
}

echo json_encode($response);
?>