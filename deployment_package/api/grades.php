<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/data-helpers.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

$action = $_GET['action'] ?? '';
$program_id = intval($_GET['program_id'] ?? 0);

if (!$program_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Program ID required']);
    exit();
}

switch ($action) {
    case 'student_grades':
        // For students viewing their own grades
        requireRole('student');
        $student_username = $_SESSION['username'];
        
        $grades_data = getStudentProgramGrades($program_id, $student_username);
        echo json_encode($grades_data);
        break;
        
    case 'program_statistics':
        // For tutors viewing program grade statistics
        requireRole('tutor');
        
        $stats_data = getProgramGradeStatistics($program_id);
        echo json_encode($stats_data);
        break;
        
    case 'tutor_student_details':
        // For tutors viewing detailed grades of a specific student
        requireRole('tutor');
        $student_username = $_GET['student_username'] ?? '';
        
        if (!$student_username) {
            http_response_code(400);
            echo json_encode(['error' => 'Student username required']);
            exit();
        }
        
        $student_grades = getStudentProgramGrades($program_id, $student_username);
        echo json_encode($student_grades);
        break;
        
    case 'program_students':
        // For tutors viewing list of students enrolled in a program
        requireRole('tutor');
        
        $students = getProgramStudents($program_id);
        echo json_encode(['students' => $students]);
        break;
        
    case 'student_attendance':
        // For students viewing their own attendance
        requireRole('student');
        $student_user_id = $_SESSION['user_id'];
        
        $attendance_data = getStudentAttendance($program_id, $student_user_id);
        echo json_encode($attendance_data);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>