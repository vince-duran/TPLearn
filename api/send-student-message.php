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

if (!isset($input['student_id']) || !isset($input['subject']) || !isset($input['content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$student_id = intval($input['student_id']);
$message_type = $input['message_type'] ?? 'general';
$subject = trim($input['subject']);
$content = trim($input['content']);
$send_email = $input['send_email'] ?? true;
$save_to_history = $input['save_to_history'] ?? true;
$tutor_user_id = $_SESSION['user_id'];

try {
    // Verify tutor has access to this student
    if (!tutorHasAccessToStudentGeneral($tutor_user_id, $student_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied to this student']);
        exit();
    }

    // Send the message
    $message_result = sendStudentMessage($tutor_user_id, $student_id, $message_type, $subject, $content, $send_email, $save_to_history);
    
    if ($message_result) {
        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send message'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in send-student-message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>