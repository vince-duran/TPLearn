<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Check if user is authenticated and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $submission_id = $_GET['submission_id'] ?? null;
    $tutor_id = $_SESSION['user_id'];

    if (!$submission_id) {
        throw new Exception('Submission ID is required');
    }

    // Get submission details with all necessary information
    $stmt = $conn->prepare("
        SELECT 
            asub.id,
            asub.assignment_id,
            asub.student_user_id,
            asub.submission_text,
            asub.submission_date,
            asub.status,
            asub.score,
            asub.feedback,
            asub.graded_at,
            asub.graded_by,
            a.title as assignment_title,
            a.description as assignment_description,
            pm.title as material_title,
            pm.due_date,
            p.tutor_id,
            u.username as student_name,
            u.email as student_email,
            fu.id as file_id,
            fu.original_filename,
            fu.file_size,
            fu.mime_type
        FROM assignment_submissions asub
        INNER JOIN assignments a ON asub.assignment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        INNER JOIN users u ON asub.student_user_id = u.id
        LEFT JOIN file_uploads fu ON asub.file_upload_id = fu.id
        WHERE asub.id = ? AND p.tutor_id = ?
    ");
    
    $stmt->bind_param('ii', $submission_id, $tutor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $submission = $result->fetch_assoc();

    if (!$submission) {
        throw new Exception('Submission not found or access denied');
    }

    // Format the submission date
    $submitted_date = new DateTime($submission['submission_date']);
    $formatted_date = $submitted_date->format('M j, Y, g:i A');

    // Check if submission is late
    $is_late = false;
    if ($submission['due_date']) {
        $due_date = new DateTime($submission['due_date']);
        $is_late = $submitted_date > $due_date;
    }

    // Calculate max score (default to 100 if not specified)
    $max_score = 100; // You might want to add this to your assignments table

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $submission['id'],
            'assignment_id' => $submission['assignment_id'],
            'student_user_id' => $submission['student_user_id'],
            'student_name' => $submission['student_name'],
            'student_email' => $submission['student_email'],
            'assignment_title' => $submission['assignment_title'],
            'assignment_description' => $submission['assignment_description'],
            'material_title' => $submission['material_title'],
            'submission_text' => $submission['submission_text'],
            'submission_date' => $submission['submission_date'],
            'submitted_date_formatted' => $formatted_date,
            'due_date' => $submission['due_date'],
            'is_late' => $is_late,
            'status' => $submission['status'],
            'score' => $submission['score'],
            'feedback' => $submission['feedback'],
            'graded_at' => $submission['graded_at'],
            'graded_by' => $submission['graded_by'],
            'max_score' => $max_score,
            'file' => $submission['file_id'] ? [
                'id' => $submission['file_id'],
                'original_filename' => $submission['original_filename'],
                'file_size' => $submission['file_size'],
                'mime_type' => $submission['mime_type']
            ] : null
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
