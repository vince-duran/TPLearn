<?php
// Debug assignment API with proper error handling
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Debug information
$debug = [
    'session_user_id' => $_SESSION['user_id'] ?? null,
    'session_role' => $_SESSION['role'] ?? null,
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'material_id' => $_GET['material_id'] ?? null,
    'session_active' => session_status() === PHP_SESSION_ACTIVE
];

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - No session user_id',
        'debug' => $debug
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed',
        'debug' => $debug
    ]);
    exit();
}

try {
    $material_id = $_GET['material_id'] ?? null;
    $student_id = $_SESSION['user_id'];

    if (!$material_id) {
        throw new Exception('Material ID is required');
    }

    // Debug: Check if the material and assignment exist
    $check_stmt = $conn->prepare("
        SELECT 
            pm.id as material_id,
            pm.title as material_title,
            pm.material_type,
            a.id as assignment_id,
            a.title as assignment_title
        FROM program_materials pm
        LEFT JOIN assignments a ON pm.id = a.material_id
        WHERE pm.id = ? AND pm.material_type = 'assignment'
    ");
    $check_stmt->bind_param('i', $material_id);
    $check_stmt->execute();
    $material_check = $check_stmt->get_result()->fetch_assoc();
    
    if (!$material_check) {
        throw new Exception("No assignment found for material_id: $material_id");
    }
    
    if (!$material_check['assignment_id']) {
        throw new Exception("Material exists but no assignment record found for material_id: $material_id");
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
            a.title as assignment_title,
            a.description as assignment_description,
            pm.due_date,
            fu.id as file_id,
            fu.original_filename,
            fu.file_size,
            fu.mime_type,
            grader.username as graded_by_username
        FROM assignment_submissions asub
        INNER JOIN assignments a ON asub.assignment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        LEFT JOIN file_uploads fu ON asub.id = fu.related_id AND fu.upload_type = 'assignment'
        LEFT JOIN users grader ON asub.graded_by = grader.id
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
            'debug' => array_merge($debug, [
                'material_check' => $material_check,
                'found_submission' => true
            ]),
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
            'message' => 'No submission found',
            'debug' => array_merge($debug, [
                'material_check' => $material_check,
                'found_submission' => false,
                'student_id' => $student_id,
                'material_id' => $material_id
            ])
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $debug
    ]);
}
?>