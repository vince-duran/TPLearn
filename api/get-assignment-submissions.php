<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Suppress ALL output and ensure clean JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Start output buffering to catch any unwanted output
ob_start();

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Clear any accumulated output
ob_end_clean();

// Start fresh output buffering
ob_start();

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

// Check if user is authenticated and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get the material (assignment) ID
    $material_id = isset($_GET['material_id']) ? $_GET['material_id'] : null;
    $tutor_id = $_SESSION['user_id'];


    if (!$material_id) {
        throw new Exception('Material ID is required');
    }

    // Verify the assignment exists and belongs to the tutor
    $stmt = $conn->prepare("
        SELECT pm.*, p.name as program_name, p.tutor_id
        FROM program_materials pm 
        JOIN programs p ON pm.program_id = p.id
        WHERE pm.id = ? AND pm.material_type = 'assignment' AND p.tutor_id = ?
    ");
    $stmt->bind_param('ii', $material_id, $tutor_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();

    if (!$assignment) {
        throw new Exception('Assignment not found or you do not have access to it');
    }

    // Get the assignment ID from the assignments table
    $stmt = $conn->prepare("
        SELECT a.*, pm.title as material_title, pm.description as material_description, pm.due_date, pm.allow_late_submission
        FROM assignments a 
        JOIN program_materials pm ON a.material_id = pm.id
        WHERE a.material_id = ?
    ");
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    $assignment_result = $stmt->get_result();
    $assignment_record = $assignment_result->fetch_assoc();
    
    if (!$assignment_record) {
        throw new Exception('No assignment record found for this material');
    }
    
    $assignment_id = $assignment_record['id'];

    // Get all submissions for this assignment
    $stmt = $conn->prepare("
        SELECT 
            asub.id as submission_id,
            asub.submission_date,
            asub.score,
            asub.feedback,
            asub.status,
            asub.graded_at,
            asub.submission_text,
            u.id as student_numeric_id,
            u.id as student_user_id,
            u.username,
            u.email,
            sp.first_name,
            sp.last_name,
            fu.id as file_id,
            fu.original_filename,
            fu.file_size,
            fu.mime_type,
            grader.username as graded_by_username,
            pm.due_date,
            CASE 
                WHEN pm.due_date IS NOT NULL AND asub.submission_date > pm.due_date THEN 1
                ELSE 0
            END as is_late
        FROM assignment_submissions asub
        JOIN users u ON asub.student_user_id = u.id
        JOIN assignments a ON asub.assignment_id = a.id
        JOIN program_materials pm ON a.material_id = pm.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN file_uploads fu ON asub.file_upload_id = fu.id
        LEFT JOIN users grader ON asub.graded_by = grader.id
        WHERE asub.assignment_id = ?
        ORDER BY asub.submission_date DESC
    ");
    $stmt->bind_param('i', $assignment_id);
    $stmt->execute();
    $submissions_result = $stmt->get_result();

    $submissions = [];
    while ($row = $submissions_result->fetch_assoc()) {
        $student_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if (empty($student_name)) {
            $student_name = $row['username'];
        }

        $submissions[] = [
            'submission_id' => $row['submission_id'],
            'student_id' => $row['student_numeric_id'],
            'student_user_id' => $row['student_user_id'],
            'student_name' => $student_name,
            'username' => $row['username'],
            'email' => $row['email'],
            'submitted_at' => $row['submission_date'],
            'submitted_date_formatted' => date('M j, Y g:i A', strtotime($row['submission_date'])),
            'is_late' => (bool)$row['is_late'],
            'grade' => $row['score'],
            'score' => $row['score'],
            'feedback' => $row['feedback'],
            'status' => $row['status'],
            'graded_at' => $row['graded_at'],
            'graded_by' => $row['graded_by_username'],
            'submission_text' => $row['submission_text'],
            'file_id' => $row['file_id'],
            'file_name' => $row['original_filename'],
            'file_size' => $row['file_size'],
            'file_size_formatted' => $row['file_size'] ? formatFileSize($row['file_size']) : null,
            'mime_type' => $row['mime_type']
        ];
    }

    // Calculate statistics
    $total_submissions = count($submissions);
    $graded_submissions = array_filter($submissions, function($s) { return $s['status'] === 'graded' && $s['score'] !== null; });
    $average_score = 0;
    
    if (!empty($graded_submissions)) {
        $total_score = array_sum(array_column($graded_submissions, 'score'));
        $average_score = round($total_score / count($graded_submissions), 1);
    }

    // Format assignment due date
    $due_date_formatted = 'No due date';
    if ($assignment_record['due_date']) {
        $due_date_formatted = date('M j, Y g:i A', strtotime($assignment_record['due_date']));
    }

    // Send response
    echo json_encode([
        'success' => true,
        'assignment' => [
            'id' => $assignment_record['id'],
            'title' => $assignment_record['material_title'],
            'description' => $assignment_record['material_description'],
            'due_date' => $assignment_record['due_date'],
            'due_date_formatted' => $due_date_formatted,
            'max_score' => $assignment_record['max_score'],
            'allow_late_submission' => (bool)$assignment_record['allow_late_submission'],
            'program_name' => $assignment['program_name']
        ],
        'submissions' => $submissions,
        'statistics' => [
            'total_submissions' => $total_submissions,
            'graded_count' => count($graded_submissions),
            'pending_count' => $total_submissions - count($graded_submissions),
            'average_score' => $average_score
        ]
    ]);

} catch (Exception $e) {
    // Clear any output and log the error
    if (ob_get_level()) {
        ob_end_clean();
        ob_start();
    }
    
    error_log("API Error - Material ID: " . (isset($_GET['material_id']) ? $_GET['material_id'] : 'null'));
    error_log("API Error - Exception: " . $e->getMessage());
    error_log("API Error - Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Flush output buffer
if (ob_get_level()) {
    ob_end_flush();
}
?>
