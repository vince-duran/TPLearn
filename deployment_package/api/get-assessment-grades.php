<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get attempt ID from query parameters
$attempt_id = $_GET['attempt_id'] ?? null;

if (!$attempt_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Attempt ID is required']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get grades and feedback for the assessment attempt
    $sql = "SELECT 
                aa.id,
                aa.started_at,
                aa.submitted_at,
                aa.time_taken,
                aa.score,
                aa.percentage,
                aa.status,
                aa.comments,
                aa.submission_file_id,
                aa.created_at,
                aa.updated_at,
                a.title as assessment_title,
                a.total_points,
                a.max_score,
                f.original_filename
            FROM assessment_attempts aa 
            INNER JOIN assessments a ON aa.assessment_id = a.id
            LEFT JOIN file_uploads f ON aa.submission_file_id = f.id
            WHERE aa.id = ? 
            AND aa.student_user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $attempt_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $grades = $result->fetch_assoc();
    
    if (!$grades) {
        echo json_encode([
            'success' => false,
            'message' => 'Assessment attempt not found or access denied'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'grades' => $grades
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching assessment grades: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch assessment grades'
    ]);
}
?>