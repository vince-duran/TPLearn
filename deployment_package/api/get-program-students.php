<?php
/**
 * API Endpoint: Get Program Students
 * Returns students enrolled in a specific program for attendance management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data-helpers.php';

// Check if user is authenticated and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get program ID from request
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;

if (!$program_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Program ID is required']);
    exit;
}

try {
    // Verify that this tutor is assigned to this program
    $tutor_user_id = $_SESSION['user_id'];
    $assigned_programs = getTutorAssignedPrograms($tutor_user_id);
    
    $program_found = false;
    $program_info = null;
    foreach ($assigned_programs as $program) {
        if ($program['id'] == $program_id) {
            $program_found = true;
            $program_info = $program;
            break;
        }
    }
    
    if (!$program_found) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this program']);
        exit;
    }
    
    // Get students for this program
    $students = getProgramStudents($program_id);
    
    // Format response
    $response = [
        'success' => true,
        'program' => [
            'id' => $program_info['id'],
            'name' => $program_info['name'],
            'session_type' => $program_info['session_type'],
            'start_date' => $program_info['start_date'],
            'end_date' => $program_info['end_date'],
            'start_time' => $program_info['start_time'],
            'end_time' => $program_info['end_time'],
            'days' => $program_info['days'],
            'duration_weeks' => $program_info['duration_weeks']
        ],
        'students' => $students,
        'total_students' => count($students)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get-program-students.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
