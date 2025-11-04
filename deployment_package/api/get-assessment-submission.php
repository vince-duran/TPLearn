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

// Get assessment ID or attempt ID from query parameters
$assessment_id = $_GET['assessment_id'] ?? null;
$attempt_id = $_GET['attempt_id'] ?? null;

if (!$assessment_id && !$attempt_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Assessment ID or Attempt ID is required']);
    exit;
}

$student_user_id = $_SESSION['user_id'];

try {
    // Get the assessment submission - either by attempt_id or assessment_id
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
                f.original_filename,
                f.file_size,
                f.mime_type,
                a.id as assessment_id,
                a.title as assessment_title,
                a.description as assessment_description,
                a.total_points,
                a.due_date,
                pm.due_date as material_due_date,
                COALESCE(a.due_date, pm.due_date) as effective_due_date,
                CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name
            FROM assessment_attempts aa 
            LEFT JOIN file_uploads f ON aa.submission_file_id = f.id
            INNER JOIN assessments a ON aa.assessment_id = a.id
            INNER JOIN program_materials pm ON a.material_id = pm.id
            LEFT JOIN tutor_profiles tp ON a.created_by = tp.user_id
            WHERE " . ($attempt_id ? "aa.id = ?" : "aa.assessment_id = ?") . "
            AND aa.student_user_id = ?
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $param_value = $attempt_id ?: $assessment_id;
    $stmt->bind_param('ii', $param_value, $student_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $submission = $result->fetch_assoc();
    
    // If submission has a file, structure the file data
    if ($submission && $submission['submission_file_id']) {
        $submission['file'] = [
            'id' => $submission['submission_file_id'],
            'original_filename' => $submission['original_filename'],
            'file_size' => $submission['file_size'],
            'mime_type' => $submission['mime_type']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'submission' => $submission,
        'has_submission' => !empty($submission)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching assessment submission: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch assessment submission'
    ]);
}
?>