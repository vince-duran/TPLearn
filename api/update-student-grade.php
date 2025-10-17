<?php
/**
 * Update Student Grade API
 * Updates or inserts a grade for a student in a specific program
 */

// Start session first
session_start();

require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if user is authenticated
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Check if user is a tutor
if (!hasRole('tutor')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Tutor role required.']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['program_id', 'student_user_id', 'grade'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

// Extract and validate input data
$program_id = intval($input['program_id']);
$student_user_id = $input['student_user_id'];
$grade = floatval($input['grade']);
$grade_type = isset($input['grade_type']) ? $input['grade_type'] : 'final';
$comments = isset($input['comments']) ? trim($input['comments']) : '';

// Additional validation
if ($program_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid program_id']);
    exit();
}

if ($grade < 0 || $grade > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Grade must be between 0 and 100']);
    exit();
}

$valid_grade_types = ['quiz', 'assignment', 'midterm', 'final', 'project', 'participation'];
if (!in_array($grade_type, $valid_grade_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid grade_type']);
    exit();
}

try {
    // Get current tutor's user ID
    $tutor_user_id = $_SESSION['user_id'];
    
    // Verify that the tutor is assigned to this program
    $tutor_programs = getTutorAssignedPrograms($tutor_user_id);
    $is_assigned = false;
    
    foreach ($tutor_programs as $program) {
        if ($program['id'] == $program_id) {
            $is_assigned = true;
            break;
        }
    }
    
    if (!$is_assigned) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this program']);
        exit();
    }
    
    // Update the student's grade
    $result = updateStudentGrade($program_id, $student_user_id, $grade, $grade_type, $comments);
    
    if ($result['success']) {
        // Get updated grade data for response
        $updated_grades = getProgramGrades($program_id);
        $updated_stats = calculateGradeStatistics($program_id);
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'program_id' => $program_id,
                'student_user_id' => $student_user_id,
                'grade' => $grade,
                'grade_type' => $grade_type,
                'comments' => $comments,
                'updated_at' => date('c')
            ],
            'updated_grades' => $updated_grades,
            'statistics' => $updated_stats
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in update-student-grade.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while updating the grade'
    ]);
}
?>
