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
    $attempt_id = $_GET['attempt_id'] ?? null;
    $tutor_id = $_SESSION['user_id'];

    if (!$attempt_id) {
        throw new Exception('Assessment attempt ID is required');
    }

    // Get assessment attempt details with all necessary information
    $stmt = $conn->prepare("
        SELECT 
            aa.id,
            aa.assessment_id,
            aa.student_user_id,
            aa.started_at,
            aa.submitted_at,
            aa.time_taken,
            aa.score,
            aa.comments,
            aa.status,
            a.title as assessment_title,
            a.total_points,
            a.due_date,
            pm.title as material_title,
            pm.due_date as material_due_date,
            p.tutor_id,
            u.username as student_name,
            u.email as student_email,
            f.id as file_id,
            f.original_filename,
            f.file_size,
            f.mime_type
        FROM assessment_attempts aa
        INNER JOIN assessments a ON aa.assessment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        INNER JOIN users u ON aa.student_user_id = u.id
        LEFT JOIN file_uploads f ON aa.submission_file_id = f.id
        WHERE aa.id = ? AND p.tutor_id = ?
    ");
    
    $stmt->bind_param('ii', $attempt_id, $tutor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempt = $result->fetch_assoc();

    if (!$attempt) {
        throw new Exception('Assessment attempt not found or access denied');
    }

    // Format the submission date
    $formatted_date = null;
    $is_late = false;
    if ($attempt['submitted_at']) {
        $submitted_date = new DateTime($attempt['submitted_at']);
        $formatted_date = $submitted_date->format('M j, Y, g:i A');
        
        // Check if submission is late based on status or date comparison
        $is_late = ($attempt['status'] === 'late_submission');
        
        // Fallback to date comparison if status is not set to late_submission
        if (!$is_late) {
            $due_date_str = $attempt['due_date'] ?: $attempt['material_due_date'];
            if ($due_date_str && $due_date_str !== '0000-00-00 00:00:00') {
                $due_date = new DateTime($due_date_str);
                $is_late = $submitted_date > $due_date;
            }
        }
    }

    // Calculate max score
    $max_score = $attempt['total_points'] ?: 100;

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $attempt['id'],
            'assessment_id' => $attempt['assessment_id'],
            'student_user_id' => $attempt['student_user_id'],
            'student_name' => $attempt['student_name'],
            'student_email' => $attempt['student_email'],
            'assessment_title' => $attempt['assessment_title'],
            'material_title' => $attempt['material_title'],
            'started_at' => $attempt['started_at'],
            'submitted_at' => $attempt['submitted_at'],
            'submitted_date_formatted' => $formatted_date,
            'time_taken' => $attempt['time_taken'],
            'due_date' => $attempt['due_date'],
            'material_due_date' => $attempt['material_due_date'],
            'is_late' => $is_late,
            'status' => $attempt['status'],
            'score' => $attempt['score'],
            'comments' => $attempt['comments'],
            'max_score' => $max_score,
            'file' => $attempt['file_id'] ? [
                'id' => $attempt['file_id'],
                'original_filename' => $attempt['original_filename'],
                'file_size' => $attempt['file_size'],
                'mime_type' => $attempt['mime_type']
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