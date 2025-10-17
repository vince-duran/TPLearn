<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is authenticated and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['program_id']) || !isset($input['session_date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: program_id, session_date']);
    exit;
}

$program_id = intval($input['program_id']);
$session_date = $input['session_date'];
$tutor_user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->autocommit(false);
    
    // Verify tutor has access to this program
    $stmt = $conn->prepare("
        SELECT p.id, p.start_time, p.end_time
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
    
    $program = $result->fetch_assoc();
    
    // Get all enrolled students for this program
    $stmt = $conn->prepare("
        SELECT e.id as enrollment_id, e.student_user_id
        FROM enrollments e
        WHERE e.program_id = ? AND e.status IN ('active', 'paused')
    ");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $enrollments_result = $stmt->get_result();
    
    $sessions_created = 0;
    
    // Create a session for each enrolled student for this date
    while ($enrollment = $enrollments_result->fetch_assoc()) {
        // Check if session already exists for this enrollment and date
        $stmt = $conn->prepare("
            SELECT id FROM sessions 
            WHERE enrollment_id = ? AND DATE(session_date) = ?
        ");
        $stmt->bind_param('is', $enrollment['enrollment_id'], $session_date);
        $stmt->execute();
        $existing_session = $stmt->get_result();
        
        if ($existing_session->num_rows === 0) {
            // Create new session
            $stmt = $conn->prepare("
                INSERT INTO sessions (enrollment_id, session_date, start_time, end_time, status, tutor_user_id, student_attended)
                VALUES (?, ?, ?, ?, 'scheduled', ?, 0)
            ");
            $stmt->bind_param('isssi', 
                $enrollment['enrollment_id'], 
                $session_date,
                $program['start_time'], 
                $program['end_time'],
                $tutor_user_id
            );
            $stmt->execute();
            $sessions_created++;
        }
    }
    
    // Commit transaction
    $conn->commit();
    $conn->autocommit(true);
    
    echo json_encode([
        'success' => true,
        'sessions_created' => $sessions_created,
        'message' => "Created $sessions_created sessions for $session_date"
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    $conn->autocommit(true);
    
    error_log("Error in create-session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>