<?php
// Suppress all output that could corrupt JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Clean any output buffer content that might have been generated
ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $material_id = $_GET['material_id'] ?? null;
    $student_id = $_SESSION['user_id'];

    if (!$material_id) {
        throw new Exception('Material ID is required');
    }

    // Get assignment submission details
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
            asub.file_upload_id,
            a.title as assignment_title,
            a.description as assignment_description,
            pm.due_date,
            fu.id as file_id,
            fu.original_filename,
            fu.file_size,
            fu.mime_type,
            grader.username as graded_by_username,
            CONCAT(tp.first_name, ' ', 
                   CASE WHEN tp.middle_name IS NOT NULL AND tp.middle_name != '' 
                        THEN CONCAT(tp.middle_name, ' ') 
                        ELSE '' END,
                   tp.last_name) as graded_by_fullname
        FROM assignment_submissions asub
        INNER JOIN assignments a ON asub.assignment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        LEFT JOIN file_uploads fu ON asub.file_upload_id = fu.id
        LEFT JOIN users grader ON asub.graded_by = grader.id
        LEFT JOIN tutor_profiles tp ON asub.graded_by = tp.user_id
        WHERE pm.id = ? AND asub.student_user_id = ?
        ORDER BY asub.submission_date DESC
        LIMIT 1
    ");
    
    $stmt->bind_param('ii', $material_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $submission = $result->fetch_assoc();

    if ($submission) {
        // Check if editing is allowed (before due date)
        $can_edit = false;
        if ($submission['due_date']) {
            $can_edit = time() < strtotime($submission['due_date']);
        }

        echo json_encode([
            'success' => true,
            'has_submission' => true,
            'submission' => [
                'id' => $submission['id'],
                'assignment_id' => $submission['assignment_id'],
                'assignment_title' => $submission['assignment_title'],
                'assignment_description' => $submission['assignment_description'],
                'submission_text' => $submission['submission_text'],
                'submission_date' => $submission['submission_date'],
                'status' => $submission['status'],
                'score' => $submission['score'],
                'feedback' => $submission['feedback'],
                'graded_at' => $submission['graded_at'],
                'graded_by_username' => $submission['graded_by_username'],
                'graded_by_fullname' => $submission['graded_by_fullname'],
                'due_date' => $submission['due_date'],
                'can_edit' => $can_edit,
                'file' => $submission['file_id'] ? [
                    'id' => $submission['file_id'],
                    'original_filename' => $submission['original_filename'],
                    'file_size' => $submission['file_size'],
                    'mime_type' => $submission['mime_type']
                ] : null
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_submission' => false,
            'message' => 'No submission found'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
