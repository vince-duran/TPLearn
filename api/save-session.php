<?php
require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if user is a tutor
if (!hasRole('tutor')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['program_id', 'session_datetime', 'duration', 'session_type'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$tutor_user_id = $_SESSION['user_id'];
$session_id = isset($input['session_id']) ? intval($input['session_id']) : null;
$program_id = intval($input['program_id']);
$session_datetime = $input['session_datetime'];
$duration = intval($input['duration']);
$session_type = $input['session_type'];
$location = $input['location'] ?? '';
$notes = $input['notes'] ?? '';
$repeat_session = isset($input['repeat_session']) && $input['repeat_session'] === 'true';
$repeat_frequency = $input['repeat_frequency'] ?? 'weekly';

try {
    // Verify tutor has access to this program
    $tutor_programs = getTutorAssignedPrograms($tutor_user_id);
    $has_access = false;
    foreach ($tutor_programs as $program) {
        if ($program['id'] == $program_id) {
            $has_access = true;
            break;
        }
    }
    
    if (!$has_access) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied to this program']);
        exit();
    }

    // If editing existing session, verify access
    if ($session_id) {
        $existing_session = getSessionDetails($session_id, $tutor_user_id);
        if (!$existing_session) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Session not found or access denied']);
            exit();
        }
    }

    // Save or update session
    if ($session_id) {
        $result = updateSession($session_id, $program_id, $session_datetime, $duration, $session_type, $location, $notes);
        $message = 'Session updated successfully';
    } else {
        $result = createSession($program_id, $session_datetime, $duration, $session_type, $location, $notes, $repeat_session, $repeat_frequency);
        $message = 'Session created successfully';
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save session'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in save-session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>