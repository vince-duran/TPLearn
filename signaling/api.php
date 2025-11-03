<?php
/**
 * Simple HTTP-based Signaling API for WebRTC
 * This provides endpoints for coordinating WebRTC connections
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Session storage (in production, use database or Redis)
session_start();

if (!isset($_SESSION['signaling_sessions'])) {
    $_SESSION['signaling_sessions'] = [];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGetRequest();
        break;
        
    case 'POST':
        handlePostRequest($input);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGetRequest() {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'poll':
            pollMessages();
            break;
            
        case 'session':
            getSessionInfo();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePostRequest($input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'join':
            joinSession($input);
            break;
            
        case 'leave':
            leaveSession($input);
            break;
            
        case 'offer':
        case 'answer':
        case 'ice-candidate':
            relayMessage($input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function joinSession($data) {
    $sessionId = $data['sessionId'] ?? '';
    $userId = $data['userId'] ?? '';
    $userRole = $data['userRole'] ?? '';
    
    if (!$sessionId || !$userId || !$userRole) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        return;
    }
    
    // Initialize session if not exists
    if (!isset($_SESSION['signaling_sessions'][$sessionId])) {
        $_SESSION['signaling_sessions'][$sessionId] = [
            'participants' => [],
            'messages' => []
        ];
    }
    
    // Get existing participants before adding new one
    $existingParticipants = $_SESSION['signaling_sessions'][$sessionId]['participants'] ?? [];
    
    // Add participant
    $_SESSION['signaling_sessions'][$sessionId]['participants'][$userId] = [
        'userRole' => $userRole,
        'joinedAt' => time(),
        'lastSeen' => time()
    ];
    
    // Notify existing participants about the new user
    $newUserMessage = [
        'type' => 'user-joined',
        'userId' => $userId,
        'userRole' => $userRole,
        'timestamp' => time()
    ];
    
    // Use 'system' as fromUserId so it's not filtered as self-message
    addMessageToSession($sessionId, $newUserMessage, 'system');
    
    // Notify the new user about existing participants
    foreach ($existingParticipants as $existingUserId => $participant) {
        $existingUserMessage = [
            'type' => 'user-joined',
            'userId' => $existingUserId,
            'userRole' => $participant['userRole'],
            'timestamp' => time()
        ];
        
        // Send this message specifically to the new user, from the existing user
        addMessageToSession($sessionId, $existingUserMessage, 'system', $userId);
    }
    
    echo json_encode([
        'success' => true,
        'sessionId' => $sessionId,
        'participants' => $_SESSION['signaling_sessions'][$sessionId]['participants']
    ]);
}

function leaveSession($data) {
    $sessionId = $data['sessionId'] ?? '';
    $userId = $data['userId'] ?? '';
    
    if (!$sessionId || !$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        return;
    }
    
    if (isset($_SESSION['signaling_sessions'][$sessionId]['participants'][$userId])) {
        unset($_SESSION['signaling_sessions'][$sessionId]['participants'][$userId]);
        
        // Notify remaining participants
        $message = [
            'type' => 'user-left',
            'userId' => $userId,
            'timestamp' => time()
        ];
        
        addMessageToSession($sessionId, $message, $userId);
    }
    
    echo json_encode(['success' => true]);
}

function relayMessage($data) {
    $sessionId = $data['sessionId'] ?? '';
    $fromUserId = $data['fromUserId'] ?? '';
    $targetUserId = $data['targetUserId'] ?? null;
    
    if (!$sessionId || !$fromUserId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        return;
    }
    
    // Update last seen
    if (isset($_SESSION['signaling_sessions'][$sessionId]['participants'][$fromUserId])) {
        $_SESSION['signaling_sessions'][$sessionId]['participants'][$fromUserId]['lastSeen'] = time();
    }
    
    $message = [
        'type' => $data['type'],
        'fromUserId' => $fromUserId,
        'timestamp' => time()
    ];
    
    // Add type-specific data
    switch ($data['type']) {
        case 'offer':
            $message['offer'] = $data['offer'];
            break;
        case 'answer':
            $message['answer'] = $data['answer'];
            break;
        case 'ice-candidate':
            $message['candidate'] = $data['candidate'];
            break;
    }
    
    addMessageToSession($sessionId, $message, $fromUserId, $targetUserId);
    
    echo json_encode(['success' => true]);
}

function pollMessages() {
    $sessionId = $_GET['sessionId'] ?? '';
    $userId = $_GET['userId'] ?? '';
    $lastMessageId = $_GET['lastMessageId'] ?? '0_0000';
    
    if (!$sessionId || !$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        return;
    }
    
    // Update last seen
    if (isset($_SESSION['signaling_sessions'][$sessionId]['participants'][$userId])) {
        $_SESSION['signaling_sessions'][$sessionId]['participants'][$userId]['lastSeen'] = time();
    }
    
    $messages = [];
    
    if (isset($_SESSION['signaling_sessions'][$sessionId]['messages'])) {
        foreach ($_SESSION['signaling_sessions'][$sessionId]['messages'] as $id => $message) {
            // Compare timestamps for newer messages
            $lastTimestamp = (int)explode('_', $lastMessageId)[0];
            $messageTimestamp = (int)explode('_', $id)[0];
            
            $isNewer = $messageTimestamp > $lastTimestamp;
            $isForThisUser = (!isset($message['targetUserId']) || $message['targetUserId'] === $userId);
            $isNotFromSelf = $message['fromUserId'] !== $userId;
            
            if ($isNewer && $isForThisUser && $isNotFromSelf) {
                $messages[] = $message;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'participants' => $_SESSION['signaling_sessions'][$sessionId]['participants'] ?? []
    ]);
}

function getSessionInfo() {
    $sessionId = $_GET['sessionId'] ?? '';
    
    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing sessionId']);
        return;
    }
    
    $session = $_SESSION['signaling_sessions'][$sessionId] ?? null;
    
    if (!$session) {
        echo json_encode([
            'success' => true,
            'participants' => [],
            'messageCount' => 0
        ]);
        return;
    }
    
    // Clean up inactive participants (inactive for more than 30 seconds)
    $now = time();
    foreach ($session['participants'] as $userId => $participant) {
        if ($now - $participant['lastSeen'] > 30) {
            unset($_SESSION['signaling_sessions'][$sessionId]['participants'][$userId]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'participants' => $_SESSION['signaling_sessions'][$sessionId]['participants'] ?? [],
        'messageCount' => count($_SESSION['signaling_sessions'][$sessionId]['messages'] ?? [])
    ]);
}

function addMessageToSession($sessionId, $message, $fromUserId, $targetUserId = null) {
    if (!isset($_SESSION['signaling_sessions'][$sessionId]['messages'])) {
        $_SESSION['signaling_sessions'][$sessionId]['messages'] = [];
    }
    
    // Use current timestamp + random number for unique message ID
    $messageId = time() . '_' . rand(1000, 9999);
    
    $messageData = array_merge($message, [
        'fromUserId' => $fromUserId,
        'id' => $messageId,
        'timestamp' => time()
    ]);
    
    if ($targetUserId) {
        $messageData['targetUserId'] = $targetUserId;
    }
    
    $_SESSION['signaling_sessions'][$sessionId]['messages'][$messageId] = $messageData;
    
    // Keep only messages from last 5 minutes to prevent memory issues
    $fiveMinutesAgo = time() - 300;
    foreach ($_SESSION['signaling_sessions'][$sessionId]['messages'] as $id => $msg) {
        if ($msg['timestamp'] < $fiveMinutesAgo) {
            unset($_SESSION['signaling_sessions'][$sessionId]['messages'][$id]);
        }
    }
}
?>