<?php
/**
 * API Endpoint: Get Saved Sessions
 * Returns a list of session dates that have saved attendance for a program
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Check if user is authenticated and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get parameters
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;

if (!$program_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameter: program_id']);
    exit;
}

$tutor_user_id = $_SESSION['user_id'];

try {
    // Verify tutor has access to this program
    $stmt = $conn->prepare("
        SELECT p.id
        FROM programs p 
        INNER JOIN tutor_profiles tp ON p.tutor_id = tp.user_id 
        WHERE p.id = ? AND tp.user_id = ?
    ");
    $stmt->bind_param('ii', $program_id, $tutor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Access denied to this program');
    }
    
    // Get session dates that have attendance records
    $stmt = $conn->prepare("
        SELECT DISTINCT DATE(s.session_date) as session_date
        FROM sessions s
        INNER JOIN enrollments e ON s.enrollment_id = e.id
        INNER JOIN attendance a ON s.id = a.session_id
        WHERE e.program_id = ?
        ORDER BY s.session_date DESC
    ");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $saved_sessions = [];
    while ($row = $result->fetch_assoc()) {
        $saved_sessions[] = $row['session_date'];
    }
    
    echo json_encode([
        'success' => true,
        'program_id' => $program_id,
        'saved_sessions' => $saved_sessions
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-saved-sessions.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>