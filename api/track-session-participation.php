<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$meeting_id = $input['meeting_id'] ?? null;
$action = $input['action'] ?? null; // 'join', 'leave', 'heartbeat'

if (!$meeting_id || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    // Verify the meeting exists
    $stmt = $conn->prepare("SELECT id, program_id FROM meetings WHERE id = ?");
    $stmt->bind_param('i', $meeting_id);
    $stmt->execute();
    $meeting = $stmt->get_result()->fetch_assoc();
    
    if (!$meeting) {
        http_response_code(404);
        echo json_encode(['error' => 'Meeting not found']);
        exit();
    }
    
    switch ($action) {
        case 'join':
            // Record user joining the session
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // First, mark any previous sessions as left (cleanup)
            $stmt = $conn->prepare("
                UPDATE meeting_participants 
                SET left_at = NOW() 
                WHERE meeting_id = ? AND user_id = ? AND left_at IS NULL
            ");
            $stmt->bind_param('ii', $meeting_id, $user_id);
            $stmt->execute();
            
            // Insert new join record
            $stmt = $conn->prepare("
                INSERT INTO meeting_participants 
                (meeting_id, user_id, joined_at, last_seen, ip_address, user_agent) 
                VALUES (?, ?, NOW(), NOW(), ?, ?)
            ");
            $stmt->bind_param('iiss', $meeting_id, $user_id, $ip_address, $user_agent);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Successfully joined session',
                'participantId' => $conn->insert_id
            ]);
            break;
            
        case 'leave':
            // Record user leaving the session
            $stmt = $conn->prepare("
                UPDATE meeting_participants 
                SET left_at = NOW(), last_seen = NOW() 
                WHERE meeting_id = ? AND user_id = ? AND left_at IS NULL
            ");
            $stmt->bind_param('ii', $meeting_id, $user_id);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Successfully left session'
            ]);
            break;
            
        case 'heartbeat':
            // Update last seen timestamp to show user is still active
            $stmt = $conn->prepare("
                UPDATE meeting_participants 
                SET last_seen = NOW() 
                WHERE meeting_id = ? AND user_id = ? AND left_at IS NULL
            ");
            $stmt->bind_param('ii', $meeting_id, $user_id);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                // User not found in active session, might need to rejoin
                echo json_encode([
                    'success' => false,
                    'message' => 'No active session found',
                    'action' => 'rejoin_required'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Heartbeat updated'
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Error tracking session participation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>