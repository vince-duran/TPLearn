<?php
// Start output buffering to prevent any unexpected output
ob_start();

// Start session first before any includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Clean any output that might have been generated
ob_clean();
header('Content-Type: application/json');

// Check authentication first
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if user is a tutor
if ($_SESSION['role'] !== 'tutor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - tutors only']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

try {
    $attempt_id = $input['attempt_id'] ?? null;
    $grade = $input['grade'] ?? null;
    $feedback = $input['feedback'] ?? '';
    $user_id = $_SESSION['user_id'];

    error_log("Submit Assessment Grade API - User ID: $user_id, Attempt ID: $attempt_id, Grade: $grade");
    error_log("Input data: " . print_r($input, true));
    error_log("Session data: " . print_r($_SESSION, true));

    // Validate input
    if (!$attempt_id || $grade === null || $grade < 0) {
        error_log("ERROR: Invalid input parameters - attempt_id: $attempt_id, grade: $grade");
        throw new Exception('Invalid input parameters');
    }

    // Start transaction
    $conn->autocommit(false);
    
    // First, verify the attempt exists and get assessment details with authorization check
    $stmt = $conn->prepare("
        SELECT 
            aa.id as attempt_id,
            aa.assessment_id,
            aa.student_user_id,
            aa.score as current_score,
            aa.status as current_status,
            a.total_points as max_score,
            a.title,
            u.username as student_username,
            CONCAT(COALESCE(sp.first_name, ''), ' ', COALESCE(sp.last_name, '')) as student_name,
            p.tutor_id
        FROM assessment_attempts aa
        JOIN assessments a ON aa.assessment_id = a.id
        JOIN program_materials pm ON a.material_id = pm.id
        JOIN programs p ON pm.program_id = p.id
        JOIN users u ON aa.student_user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE aa.id = ?
    ");
    $stmt->bind_param('i', $attempt_id);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    
    error_log("Database query executed. Found attempt: " . ($attempt ? 'YES' : 'NO'));
    if ($attempt) {
        error_log("Attempt data: " . print_r($attempt, true));
    }
    
    if (!$attempt) {
        error_log("ERROR: Assessment attempt not found for ID: $attempt_id");
        throw new Exception('Assessment attempt not found');
    }
    
    // Check authorization - only tutors can grade their own assessments
    if ($attempt['tutor_id'] != $user_id) {
        error_log("ERROR: Authorization failed - Tutor ID: {$attempt['tutor_id']}, User ID: $user_id");
        throw new Exception('Access denied - not authorized to grade this submission');
    }
    
    // Validate grade doesn't exceed max score
    if ($grade > $attempt['max_score']) {
        throw new Exception('Grade cannot exceed maximum score of ' . $attempt['max_score']);
    }
    
    // Calculate percentage
    $percentage = ($grade / $attempt['max_score']) * 100;
    
    // Update the assessment attempt with grade, feedback, and percentage
    $stmt = $conn->prepare("
        UPDATE assessment_attempts 
        SET score = ?, 
            percentage = ?,
            comments = ?, 
            status = 'graded',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('ddsi', $grade, $percentage, $feedback, $attempt_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to update assessment grade');
    }

    // Commit transaction
    $conn->commit();
    
    // Format student name
    $student_name = trim($attempt['student_name']);
    if (empty($student_name) || $student_name === ' ') {
        $student_name = $attempt['student_username'];
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Grade submitted successfully',
        'data' => [
            'attempt_id' => $attempt_id,
            'grade' => $grade,
            'max_score' => $attempt['max_score'],
            'percentage' => round($percentage, 2),
            'status' => 'graded',
            'student_name' => $student_name,
            'assessment_title' => $attempt['title']
        ]
    ]);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    ob_clean();
    error_log("Assessment grading error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Error submitting grade: ' . $e->getMessage()
    ]);
    exit();
}
?>