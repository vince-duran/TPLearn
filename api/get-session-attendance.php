<?php
/**
 * API Endpoint: Get Session Attendance
 * Retrieves attendance data for a specific session
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
$session_date = isset($_GET['session_date']) ? $_GET['session_date'] : '';

if (!$program_id || !$session_date) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters: program_id and session_date']);
    exit;
}

$tutor_user_id = $_SESSION['user_id'];

try {
    // Verify tutor has access to this program
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.start_time, p.end_time
        FROM programs p 
        INNER JOIN tutor_profiles tp ON p.tutor_id = tp.user_id 
        WHERE p.id = ? AND tp.user_id = ?
    ");
    $stmt->bind_param('ii', $program_id, $tutor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this program']);
        exit;
    }
    
    $program = $result->fetch_assoc();
    
    // Get attendance data for this program and session date
    $stmt = $conn->prepare("
        SELECT DISTINCT
            e.student_user_id,
            u.user_id as student_user_id_string,
            sp.first_name,
            sp.last_name,
            u.email,
            s.id as session_id,
            s.student_attended,
            s.notes as session_notes,
            COALESCE(a.status, 
                CASE 
                    WHEN s.student_attended = 1 THEN 'present'
                    WHEN s.student_attended = 0 THEN 'absent'
                    ELSE 'absent'
                END
            ) as attendance_status,
            COALESCE(a.notes, s.notes, '') as attendance_notes,
            COALESCE(a.recorded_at, s.created_at) as marked_at
        FROM enrollments e
        INNER JOIN users u ON e.student_user_id = u.id
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN sessions s ON e.id = s.enrollment_id AND DATE(s.session_date) = ?
        LEFT JOIN attendance a ON s.id = a.session_id AND a.student_user_id = u.user_id
        WHERE e.program_id = ? AND e.status IN ('active', 'paused')
        GROUP BY e.student_user_id, u.user_id, sp.first_name, sp.last_name, u.email, s.id, s.student_attended, s.notes, a.status, a.notes, a.recorded_at, s.created_at
        ORDER BY sp.first_name, sp.last_name
    ");
    $stmt->bind_param('si', $session_date, $program_id);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    
    $attendance_data = [];
    while ($row = $attendance_result->fetch_assoc()) {
        $attendance_data[] = [
            'student_user_id' => $row['student_user_id'],
            'student_user_id_string' => $row['student_user_id_string'],
            'full_name' => $row['first_name'] . ' ' . $row['last_name'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'status' => $row['attendance_status'],
            'notes' => $row['attendance_notes'],
            'marked_at' => $row['marked_at'],
            'session_id' => $row['session_id']
        ];
    }
    
    // Get all enrolled students for comparison
    $stmt = $conn->prepare("
        SELECT DISTINCT
            u.id as user_id,
            sp.first_name,
            sp.last_name,
            u.email
        FROM enrollments e
        INNER JOIN users u ON e.student_user_id = u.id
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        WHERE e.program_id = ? AND e.status IN ('active', 'paused')
        GROUP BY u.id, sp.first_name, sp.last_name, u.email
        ORDER BY sp.first_name, sp.last_name
    ");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $students_result = $stmt->get_result();
    
    $all_students = [];
    while ($row = $students_result->fetch_assoc()) {
        $all_students[] = [
            'user_id' => $row['user_id'],
            'full_name' => $row['first_name'] . ' ' . $row['last_name'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'program' => $program,
        'session_date' => $session_date,
        'session_info' => [
            'date' => $session_date,
            'start_time' => $program['start_time'],
            'end_time' => $program['end_time'],
            'status' => 'completed'
        ],
        'students' => $all_students,  // Changed from 'all_students' to 'students'
        'attendance_data' => $attendance_data
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-session-attendance.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
