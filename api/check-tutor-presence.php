<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/data-helpers.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Only students should use this endpoint
if ($_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$meeting_id = $_GET['meeting_id'] ?? null;
$program_id = $_GET['program_id'] ?? null;

if (!$meeting_id || !$program_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

try {
    // First, verify the meeting exists and is active
    $stmt = $conn->prepare("
        SELECT m.*, p.tutor_id 
        FROM jitsi_meetings m 
        JOIN programs p ON m.program_id = p.id 
        WHERE m.id = ? AND m.program_id = ? AND m.status IN ('scheduled', 'active')
    ");
    $stmt->bind_param('ii', $meeting_id, $program_id);
    $stmt->execute();
    $meeting = $stmt->get_result()->fetch_assoc();
    
    if (!$meeting) {
        echo json_encode([
            'canJoin' => false,
            'reason' => 'meeting_not_found',
            'message' => 'Meeting not found or not active'
        ]);
        exit();
    }
    
    // Use PST timezone for meeting time calculations
    $meetingStatus = getMeetingStatus($meeting['scheduled_date'], $meeting['scheduled_time'], $meeting['duration_minutes']);
    
    if ($meetingStatus['is_upcoming']) {
        echo json_encode([
            'canJoin' => false,
            'reason' => 'meeting_not_started',
            'message' => 'Meeting has not started yet',
            'startTime' => $meetingStatus['start_time']->format('Y-m-d H:i:s T')
        ]);
        exit();
    }
    
    if ($meetingStatus['is_past']) {
        echo json_encode([
            'canJoin' => false,
            'reason' => 'meeting_ended',
            'message' => 'Meeting has already ended',
            'endTime' => $meetingStatus['end_time']->format('Y-m-d H:i:s T')
        ]);
        exit();
    }
    
    // Allow students to join anytime during the scheduled session window
    // No tutor presence check required - just verify session is within active time
    
    // Get tutor information
    $stmt = $conn->prepare("
        SELECT u.username as tutor_name
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->bind_param('i', $meeting['tutor_id']);
    $stmt->execute();
    $tutor_info = $stmt->get_result()->fetch_assoc();
    
    // Session is within active time window, student can join
    echo json_encode([
        'canJoin' => true,
        'reason' => 'session_active',
        'message' => 'Live session is active. You can join now.',
        'tutorName' => $tutor_info['tutor_name'] ?? 'Tutor',
        'meetingInfo' => [
            'title' => $meeting['title'],
            'description' => $meeting['description'],
            'scheduledTime' => $meetingStatus['start_time']->format('Y-m-d H:i:s T'),
            'endTime' => $meetingStatus['end_time']->format('Y-m-d H:i:s T'),
            'timezone' => 'PST (Asia/Manila)'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error checking tutor presence: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'canJoin' => false,
        'reason' => 'server_error',
        'message' => 'Unable to verify session status. Please try again later.'
    ]);
}
?>