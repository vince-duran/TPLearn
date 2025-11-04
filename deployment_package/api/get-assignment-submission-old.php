<?php
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get assignment ID from query parameters
$assignment_id = $_GET['assignment_id'] ?? null;

if (!$assignment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // First verify the assignment exists and student has access
    $assignment_sql = "
        SELECT pm.*, p.name as program_title, p.id as program_id
        FROM program_materials pm
        JOIN programs p ON pm.program_id = p.id
        JOIN enrollments e ON p.id = e.program_id
        WHERE pm.id = ?
        AND pm.material_type = 'assignment'
        AND e.student_id = ?
        AND e.enrollment_status = 'active'
    ";
    
    $assignment_stmt = $conn->prepare($assignment_sql);
    $assignment_stmt->execute([$assignment_id, $user_id]);
    $assignment = $assignment_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
        exit;
    }
    
    // Get assignment submission details
    $submission_sql = "
        SELECT 
            s.*,
            f.id as submission_file_id,
            f.original_filename,
            f.file_size,
            f.mime_type,
            f.file_path
        FROM assignment_submissions s
        LEFT JOIN file_uploads f ON s.file_id = f.id
        WHERE s.assignment_id = ?
        AND s.student_id = ?
        ORDER BY s.id DESC 
        LIMIT 1
    ";
    
    $submission_stmt = $conn->prepare($submission_sql);
    $submission_stmt->execute([$assignment_id, $user_id]);
    $submission = $submission_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        // Convert timestamps to ISO format for JavaScript
        $submission['submitted_at'] = $submission['submitted_at'] ? 
            date('c', strtotime($submission['submitted_at'])) : null;
        $submission['created_at'] = $submission['created_at'] ? 
            date('c', strtotime($submission['created_at'])) : null;
        $submission['updated_at'] = $submission['updated_at'] ? 
            date('c', strtotime($submission['updated_at'])) : null;
        
        // Add assignment details to submission
        $submission['assignment_title'] = $assignment['title'];
        $submission['assignment_description'] = $assignment['description'];
        $submission['program_title'] = $assignment['program_title'];
        $submission['program_id'] = $assignment['program_id'];
        
        echo json_encode([
            'success' => true,
            'has_submission' => true,
            'submission' => $submission,
            'assignment' => $assignment
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_submission' => false,
            'assignment' => $assignment
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get-assignment-submission.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>