<?php
// Start output buffering to prevent any unexpected output
ob_start();

// Start session first before any includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Clean any output buffer and start fresh
ob_clean();
header('Content-Type: application/json');

// Enable error logging for debugging (disable display_errors to prevent breaking JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Ensure user is authenticated
if (!isLoggedIn()) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $attempt_id = $_GET['attempt_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    error_log("Get Assessment Attempt API - User ID: $user_id, Role: $user_role, Attempt ID: $attempt_id");
    error_log("GET parameters: " . print_r($_GET, true));
    error_log("Session data: " . print_r($_SESSION, true));
    
    if (!$attempt_id) {
        error_log("ERROR: No attempt_id provided");
        throw new Exception('Attempt ID is required');
    }
    
    if (!$user_id) {
        error_log("ERROR: No user_id in session");
        throw new Exception('User not authenticated');
    }
    
    if ($user_role !== 'tutor') {
        error_log("ERROR: User role is not tutor: $user_role");
        throw new Exception('Access denied - tutors only');
    }
    
    // Get assessment attempt with related data
    $stmt = $conn->prepare("
        SELECT 
            aa.id as attempt_id,
            aa.student_user_id,
            aa.started_at,
            aa.submitted_at,
            aa.time_taken,
            aa.score,
            aa.percentage,
            aa.status,
            aa.comments,
            aa.submission_file_id,
            u.username as student_username,
            CONCAT(COALESCE(sp.first_name, ''), ' ', COALESCE(sp.last_name, '')) as student_full_name,
            a.title as assessment_title,
            a.total_points as max_points,
            a.instructions,
            pm.title as material_title,
            p.name as program_name,
            p.tutor_id,
            fu.original_filename,
            fu.file_size
        FROM assessment_attempts aa
        INNER JOIN users u ON aa.student_user_id = u.id
        INNER JOIN assessments a ON aa.assessment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN file_uploads fu ON aa.submission_file_id = fu.id
        WHERE aa.id = ?
    ");
    $stmt->bind_param('i', $attempt_id);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    
    if (!$attempt) {
        throw new Exception('Assessment attempt not found');
    }
    
    // Check permissions - only tutors can grade and only for their assessments
    if ($user_role !== 'tutor' || $attempt['tutor_id'] != $user_id) {
        throw new Exception('Access denied - not authorized to grade this submission');
    }
    
    // Format the data
    $student_name = trim($attempt['student_full_name']);
    if (empty($student_name) || $student_name === ' ') {
        $student_name = $attempt['student_username'];
    }
    
    $submitted_at_formatted = 'Not submitted';
    if ($attempt['submitted_at']) {
        $submitted_at_formatted = date('M j, Y g:i A', strtotime($attempt['submitted_at']));
    }
    
    $response_data = [
        'success' => true,
        'attempt' => [
            'attempt_id' => $attempt['attempt_id'],
            'assessment_id' => $attempt['assessment_id'],
            'student_user_id' => $attempt['student_user_id'],
            'student_username' => $attempt['student_username'],
            'student_name' => $student_name,
            'assessment_title' => $attempt['assessment_title'],
            'material_title' => $attempt['material_title'],
            'program_name' => $attempt['program_name'],
            'submitted_at' => $attempt['submitted_at'],
            'submitted_at_formatted' => $submitted_at_formatted,
            'score' => $attempt['score'],
            'percentage' => $attempt['percentage'],
            'max_points' => $attempt['max_points'],
            'status' => $attempt['status'],
            'comments' => $attempt['comments'],
            'time_taken' => $attempt['time_taken'],
            'submission_file_id' => $attempt['submission_file_id'],
            'original_filename' => $attempt['original_filename'],
            'file_size' => $attempt['file_size'],
            'instructions' => $attempt['instructions']
        ]
    ];
    
    error_log("Successfully loaded assessment attempt data for attempt ID: $attempt_id");
    
    // Send response
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response_data);
    exit();
    
} catch (Exception $e) {
    // Clean output buffer in case of errors
    ob_clean();
    error_log("Get assessment attempt error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'attempt_id' => $attempt_id ?? 'not set',
            'user_id' => $user_id ?? 'not set',
            'user_role' => $user_role ?? 'not set'
        ]
    ]);
    exit();
}
?>