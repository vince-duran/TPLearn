<?php
require_once '../includes/data-helpers.php';

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['year']) || !isset($input['month'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing year and month parameters']);
    exit();
}

$year = intval($input['year']);
$month = intval($input['month']);

// TEMPORARY: Use known tutor ID for testing (Sarah Cruz - ID 8)
$tutor_user_id = 8;

// Validate year and month
if ($year < 2020 || $year > 2030 || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid year or month']);
    exit();
}

try {
    // Get monthly schedule
    $monthly_sessions = getTutorMonthlySchedule($tutor_user_id, $year, $month);
    
    // Get upcoming sessions for sidebar
    $upcoming_sessions = getTutorUpcomingSessions($tutor_user_id, 10);
    
    // Get tutor's programs for filter
    $programs = getTutorAssignedPrograms($tutor_user_id);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'monthly_sessions' => $monthly_sessions,
            'upcoming_sessions' => $upcoming_sessions,
            'programs' => $programs,
            'year' => $year,
            'month' => $month,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year)),
            'tutor_id' => $tutor_user_id // For debugging
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get-calendar-data-test.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>