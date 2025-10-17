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

if (!isset($input['session_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing session_id parameter']);
    exit();
}

$session_id = intval($input['session_id']);
$tutor_user_id = $_SESSION['user_id'];

try {
    // Get session details
    $session = getSessionDetails($session_id, $tutor_user_id);
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session not found or access denied']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'session' => $session
    ]);

} catch (Exception $e) {
    error_log("Error in get-session-details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>