<?php
// Start output buffering to prevent any unexpected output
ob_start();

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is authenticated
if (!isLoggedIn()) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Clean any output buffer and start fresh
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $assessment_id = $_GET['assessment_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    // Debug logging
    error_log("Assessment API - User ID: $user_id, Role: $user_role, Assessment ID: $assessment_id");
    
    if (!$assessment_id) {
        throw new Exception('Assessment ID is required');
    }
    
    // Get assessment details with material and program info
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            pm.title as material_title,
            pm.description as material_description,
            p.name as program_name,
            p.tutor_id,
            u.username as tutor_username
        FROM assessments a
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        INNER JOIN users u ON p.tutor_id = u.id
        WHERE a.id = ?
    ");
    
    $stmt->bind_param('i', $assessment_id);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();
    
    if (!$assessment) {
        throw new Exception('Assessment not found');
    }
    
    // Check permissions
    if ($user_role === 'tutor' && $assessment['tutor_id'] != $user_id) {
        error_log("Permission denied - User ID: $user_id, Assessment Tutor ID: {$assessment['tutor_id']}");
        throw new Exception("Access denied - not your assessment (User: $user_id, Expected: {$assessment['tutor_id']})");
    } else if ($user_role === 'student') {
        // Check if student is enrolled in the program
        $enrollment_stmt = $conn->prepare("
            SELECT e.id FROM enrollments e
            INNER JOIN program_materials pm ON pm.program_id = e.program_id
            INNER JOIN assessments a ON a.material_id = pm.id
            WHERE e.student_user_id = ? AND a.id = ?
        ");
        $enrollment_stmt->bind_param('ii', $user_id, $assessment_id);
        $enrollment_stmt->execute();
        $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
        
        if (!$enrollment) {
            throw new Exception('Access denied - not enrolled in this program');
        }
    }
    
    // Format due date
    if ($assessment['due_date'] && $assessment['due_date'] !== '0000-00-00 00:00:00' && strtotime($assessment['due_date'])) {
        $assessment['due_date_formatted'] = date('M j, Y g:i A', strtotime($assessment['due_date']));
        $assessment['is_overdue'] = strtotime($assessment['due_date']) < time();
    } else {
        $assessment['due_date_formatted'] = null;
        $assessment['is_overdue'] = false;
        $assessment['due_date'] = null; // Set to null for better frontend handling
    }
    
    // Improve placeholder content
    if (!$assessment['description'] || trim($assessment['description']) === 'Blah' || trim($assessment['description']) === '') {
        $assessment['description'] = 'Please complete this assessment by following the instructions and uploading your submission.';
    }
    
    if (!$assessment['instructions'] || trim($assessment['instructions']) === 'Blah' || trim($assessment['instructions']) === '') {
        $assessment['instructions'] = 'Download the assessment document, complete all required tasks, and submit your work before the deadline.';
    }
    
    // Get attempt statistics if user is a tutor
    if ($user_role === 'tutor') {
        $stats_stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT aa.student_user_id) as total_attempts,
                COUNT(DISTINCT CASE WHEN aa.submitted_at IS NOT NULL THEN aa.student_user_id END) as completed_attempts,
                AVG(CASE WHEN aa.submitted_at IS NOT NULL THEN aa.score END) as average_score
            FROM assessment_attempts aa
            WHERE aa.assessment_id = ?
        ");
        $stats_stmt->bind_param('i', $assessment_id);
        $stats_stmt->execute();
        $stats = $stats_stmt->get_result()->fetch_assoc();
        $assessment['attempt_stats'] = $stats;
    }
    
    // Get user's attempt history if user is a student
    if ($user_role === 'student') {
        $attempts_stmt = $conn->prepare("
            SELECT 
                id,
                score,
                started_at,
                submitted_at,
                time_taken
            FROM assessment_attempts
            WHERE assessment_id = ? AND student_user_id = ?
            ORDER BY started_at DESC
        ");
        $attempts_stmt->bind_param('ii', $assessment_id, $user_id);
        $attempts_stmt->execute();
        $attempts_result = $attempts_stmt->get_result();
        
        $user_attempts = [];
        while ($attempt = $attempts_result->fetch_assoc()) {
            if ($attempt['started_at']) {
                $attempt['started_at_formatted'] = date('M j, Y g:i A', strtotime($attempt['started_at']));
            }
            if ($attempt['submitted_at']) {
                $attempt['submitted_at_formatted'] = date('M j, Y g:i A', strtotime($attempt['submitted_at']));
            }
            $user_attempts[] = $attempt;
        }
        $assessment['user_attempts'] = $user_attempts;
        $assessment['attempts_used'] = count($user_attempts);
        $assessment['can_attempt'] = (
            ($assessment['max_attempts'] == -1 || count($user_attempts) < $assessment['max_attempts']) &&
            (!$assessment['due_date'] || !$assessment['is_overdue'])
        );
    }
    
    echo json_encode([
        'success' => true,
        'assessment' => $assessment
    ]);
    
} catch (Exception $e) {
    // Clean output buffer in case of errors
    ob_clean();
    error_log("Get assessment error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffering and flush
ob_end_flush();
?>