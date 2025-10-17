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

if (!isset($input['student_id']) || !isset($input['program_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$student_id = intval($input['student_id']);
$program_id = intval($input['program_id']);
$tutor_user_id = $_SESSION['user_id'];

try {
    // Verify tutor has access to this program and student
    if (!tutorHasAccessToStudent($tutor_user_id, $student_id, $program_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied to this student']);
        exit();
    }

    // Get detailed student information
    $student_details = getDetailedStudentInfo($student_id, $program_id);
    
    if (!$student_details) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'student' => $student_details
    ]);

} catch (Exception $e) {
    error_log("Error in get-student-details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>