<?php
require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if user is a tutor
if (!hasRole('tutor')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
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
$tutor_user_id = $_SESSION['user_id'];

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
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get-calendar-data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>