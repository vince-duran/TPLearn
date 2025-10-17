<?php
require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

// Set content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get program ID from query parameter
$program_id = $_GET['program_id'] ?? '';

if (!$program_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Program ID required']);
    exit();
}

try {
    // Get program details
    $program = getProgram($program_id);
    
    if (!$program) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Program not found']);
        exit();
    }
    
    // Calculate next session
    $nextSession = calculateNextSession($program);
    
    // Get enrolled students count
    $students = getProgramStudents($program_id);
    $studentCount = count($students);
    
    // Get program sessions for duration calculation
    $sessions = getProgramSessions($program_id, 1); // Get next session
    $duration = '90'; // Default duration
    
    if (!empty($sessions)) {
        $session = $sessions[0];
        if (isset($session['duration'])) {
            $duration = $session['duration'];
        } elseif (isset($program['start_time']) && isset($program['end_time'])) {
            // Calculate duration from start and end time
            $start = strtotime($program['start_time']);
            $end = strtotime($program['end_time']);
            $duration = round(($end - $start) / 60); // Duration in minutes
        }
    }
    
    // Use the program's stored session_id if available, otherwise generate one
    $sessionId = $program['session_id'] ?? null;
    
    if (!$sessionId) {
        // Fallback: generate session ID for programs that don't have one yet
        $sessionId = 'session_' . $program_id . '_' . date('Y-m-d');
        error_log("Program $program_id does not have a stored session_id, using fallback: $sessionId");
    } else {
        error_log("Using stored session_id for program $program_id: $sessionId");
    }
    
    // Determine session type (online/in-person)
    $sessionType = 'Interactive Lesson'; // Default
    $delivery_method = $program['session_type'] ?? '';
    if (stripos($delivery_method, 'online') !== false) {
        $sessionType = 'Online Interactive Lesson';
    } elseif (stripos($delivery_method, 'hybrid') !== false) {
        $sessionType = 'Hybrid Lesson';
    }
    
    // Format next session date and time
    $sessionDateTime = '';
    $isToday = false;
    
    if ($nextSession['status'] === 'scheduled' || $nextSession['status'] === 'upcoming') {
        $sessionDateTime = $nextSession['date'] . ' - ' . $nextSession['time'];
        
        // Check if session is today
        $today = date('l, M j');
        if (strpos($nextSession['date'], $today) !== false) {
            $isToday = true;
            $sessionDateTime = 'Today, ' . $nextSession['time'];
        }
    }
    
    // Check if session is currently active (within session time window)
    $isActive = false;
    if ($isToday) {
        $currentTime = time();
        $sessionStartTime = strtotime($nextSession['time']);
        $sessionEndTime = $sessionStartTime + ($duration * 60);
        
        // Session is active 15 minutes before start time until end time
        if ($currentTime >= ($sessionStartTime - 900) && $currentTime <= $sessionEndTime) {
            $isActive = true;
        }
    }
    
    $response = [
        'success' => true,
        'program' => [
            'id' => $program['id'],
            'name' => $program['name'],
            'description' => $program['description']
        ],
        'session' => [
            'id' => $sessionId,
            'datetime' => $sessionDateTime,
            'duration' => $duration . ' minutes',
            'type' => $sessionType,
            'status' => $nextSession['status'],
            'isActive' => $isActive,
            'studentsExpected' => $studentCount . ' students'
        ],
        'nextSession' => $nextSession
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching session data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to fetch session data: ' . $e->getMessage()
    ]);
}
?>