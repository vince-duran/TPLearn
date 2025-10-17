<?php
/**
 * API Endpoint: Save Attendance Data
 * Saves attendance records for a specific session
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
if (!isset($input['program_id']) || !isset($input['session_date']) || !isset($input['attendance_data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: program_id, session_date, attendance_data']);
    exit;
}

$program_id = intval($input['program_id']);
$session_date = $input['session_date'];
$attendance_data = $input['attendance_data'];
$tutor_user_id = $_SESSION['user_id'];

// Log the incoming data for debugging
error_log("Save attendance request: program_id=$program_id, session_date=$session_date, tutor_user_id=$tutor_user_id");
error_log("Attendance data: " . json_encode($attendance_data));

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
    
    // Get enrollments for this program
    $stmt = $conn->prepare("
        SELECT e.id as enrollment_id, e.student_user_id, u.user_id as student_user_id_string
        FROM enrollments e
        INNER JOIN users u ON e.student_user_id = u.id
        WHERE e.program_id = ? AND e.status IN ('active', 'paused')
    ");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $enrollments_result = $stmt->get_result();
    
    $enrollments = [];
    while ($row = $enrollments_result->fetch_assoc()) {
        $enrollments[$row['student_user_id']] = [
            'enrollment_id' => $row['enrollment_id'],
            'user_id_string' => $row['student_user_id_string'] ?: $row['student_user_id'] // fallback to numeric if string is null
        ];
    }
    
    error_log("Found enrollments: " . json_encode($enrollments));
    
    // Save attendance data
    $saved_count = 0;
    foreach ($attendance_data as $attendance) {
        if (!isset($attendance['student_user_id']) || !isset($attendance['status'])) {
            error_log("Skipping attendance record - missing student_user_id or status");
            continue;
        }
        
        $student_user_id = intval($attendance['student_user_id']);
        $status = $attendance['status'];
        $notes = isset($attendance['notes']) ? $attendance['notes'] : '';
        
        error_log("Processing attendance for student_user_id: $student_user_id, status: $status");
        
        // Skip if student is not enrolled in this program
        if (!isset($enrollments[$student_user_id])) {
            error_log("Student $student_user_id not found in enrollments");
            continue;
        }
        
        $enrollment_id = $enrollments[$student_user_id]['enrollment_id'];
        $student_user_id_string = $enrollments[$student_user_id]['user_id_string'];
        
        // Create or get session for this enrollment and date
        $stmt = $conn->prepare("
            SELECT id FROM sessions 
            WHERE enrollment_id = ? AND DATE(session_date) = ?
        ");
        $stmt->bind_param('is', $enrollment_id, $session_date);
        $stmt->execute();
        $session_result = $stmt->get_result();
        
        if ($session_result->num_rows === 0) {
            // Create new session
            $stmt = $conn->prepare("
                INSERT INTO sessions (enrollment_id, session_date, start_time, end_time, status, tutor_user_id, student_attended)
                VALUES (?, ?, ?, ?, 'scheduled', ?, ?)
            ");
            $attended = ($status === 'present') ? 1 : 0;
            $stmt->bind_param('isssii', 
                $enrollment_id, 
                $session_date,
                $program['start_time'], 
                $program['end_time'],
                $tutor_user_id,
                $attended
            );
            $stmt->execute();
            $session_id = $conn->insert_id;
            error_log("Created new session $session_id for enrollment $enrollment_id");
        } else {
            // Update existing session
            $session = $session_result->fetch_assoc();
            $session_id = $session['id'];
            
            $attended = ($status === 'present') ? 1 : 0;
            $stmt = $conn->prepare("
                UPDATE sessions 
                SET student_attended = ?, notes = ?, status = 'completed'
                WHERE id = ?
            ");
            $stmt->bind_param('isi', $attended, $notes, $session_id);
            $stmt->execute();
            error_log("Updated existing session $session_id");
        }
        
        // Insert or update attendance record
        $stmt = $conn->prepare("
            INSERT INTO attendance (session_id, student_user_id, status, notes)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            status = VALUES(status), 
            notes = VALUES(notes),
            updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param('isss', $session_id, $student_user_id_string, $status, $notes);
        $stmt->execute();
        
        error_log("Saved attendance record for session $session_id, student $student_user_id_string, status $status");
        $saved_count++;
    }
    
    // Commit transaction
    $conn->commit();
    $conn->autocommit(true);
    
    error_log("Successfully saved attendance for $saved_count students");
    
    echo json_encode([
        'success' => true,
        'message' => "Attendance saved for {$saved_count} students",
        'saved_count' => $saved_count,
        'session_date' => $session_date
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    $conn->autocommit(true);
    
    error_log("Error in save-attendance.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>