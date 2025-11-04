<?php
/**
 * API Endpoint: Get Program Grades
 * Returns students enrolled in a program with their assessment and assignment grades
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

// Get program ID from request
$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;

if (!$program_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Program ID is required']);
    exit;
}

$tutor_user_id = $_SESSION['user_id'];

try {
    // Verify tutor has access to this program
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.description
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
    
    // Get enrolled students for this program
    $stmt = $conn->prepare("
        SELECT 
            e.student_user_id,
            u.email,
            sp.first_name,
            sp.last_name,
            e.status as enrollment_status
        FROM enrollments e
        INNER JOIN users u ON e.student_user_id = u.id
        INNER JOIN student_profiles sp ON u.id = sp.user_id
        WHERE e.program_id = ? AND e.status IN ('active', 'paused')
        ORDER BY sp.first_name, sp.last_name
    ");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $students_result = $stmt->get_result();
    
    $students = [];
    while ($row = $students_result->fetch_assoc()) {
        $student_id = $row['student_user_id'];
        
        // Get assessment grades for this student
        $stmt = $conn->prepare("
            SELECT 
                a.id as assessment_id,
                a.title as assessment_title,
                a.total_points as max_points,
                aa.score,
                aa.submitted_at
            FROM assessments a
            LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_user_id = ?
            WHERE a.material_id IN (
                SELECT pm.id FROM program_materials pm 
                WHERE pm.program_id = ?
            )
            ORDER BY a.id
        ");
        $stmt->bind_param('ii', $student_id, $program_id);
        $stmt->execute();
        $assessments_result = $stmt->get_result();
        
        $assessments = [];
        $assessment_total = 0;
        $assessment_max_total = 0;
        $assessment_count = 0;
        
        while ($assessment = $assessments_result->fetch_assoc()) {
            $score = $assessment['score'] ?: 0;
            $max_points = $assessment['max_points'] ?: 100;
            
            $assessments[] = [
                'id' => $assessment['assessment_id'],
                'title' => $assessment['assessment_title'],
                'score' => $score,
                'max_points' => $max_points,
                'percentage' => $max_points > 0 ? round(($score / $max_points) * 100, 2) : 0,
                'submitted_at' => $assessment['submitted_at']
            ];
            
            if ($assessment['score'] !== null) {
                $assessment_total += $score;
                $assessment_max_total += $max_points;
                $assessment_count++;
            }
        }
        
        // Get assignment grades for this student
        $stmt = $conn->prepare("
            SELECT 
                a.id as assignment_id,
                a.title as assignment_title,
                a.max_score as max_points,
                asub.score,
                asub.submission_date as submitted_at
            FROM assignments a
            LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_user_id = ?
            WHERE a.program_id = ?
            ORDER BY a.id
        ");
        $stmt->bind_param('ii', $student_id, $program_id);
        $stmt->execute();
        $assignments_result = $stmt->get_result();
        
        $assignments = [];
        $assignment_total = 0;
        $assignment_max_total = 0;
        $assignment_count = 0;
        
        while ($assignment = $assignments_result->fetch_assoc()) {
            $score = $assignment['score'] ?: 0;
            $max_points = $assignment['max_points'] ?: 100;
            
            $assignments[] = [
                'id' => $assignment['assignment_id'],
                'title' => $assignment['assignment_title'],
                'score' => $score,
                'max_points' => $max_points,
                'percentage' => $max_points > 0 ? round(($score / $max_points) * 100, 2) : 0,
                'submitted_at' => $assignment['submitted_at']
            ];
            
            if ($assignment['score'] !== null) {
                $assignment_total += $score;
                $assignment_max_total += $max_points;
                $assignment_count++;
            }
        }
        
        // Calculate overall average
        $assessment_average = $assessment_max_total > 0 ? ($assessment_total / $assessment_max_total) * 100 : 0;
        $assignment_average = $assignment_max_total > 0 ? ($assignment_total / $assignment_max_total) * 100 : 0;
        
        // Overall average (50% assessments, 50% assignments)
        $overall_average = 0;
        if ($assessment_count > 0 && $assignment_count > 0) {
            $overall_average = ($assessment_average + $assignment_average) / 2;
        } elseif ($assessment_count > 0) {
            $overall_average = $assessment_average;
        } elseif ($assignment_count > 0) {
            $overall_average = $assignment_average;
        }
        
        // Determine letter grade
        $letter_grade = 'F';
        if ($overall_average >= 97) $letter_grade = 'A+';
        elseif ($overall_average >= 93) $letter_grade = 'A';
        elseif ($overall_average >= 90) $letter_grade = 'A-';
        elseif ($overall_average >= 87) $letter_grade = 'B+';
        elseif ($overall_average >= 83) $letter_grade = 'B';
        elseif ($overall_average >= 80) $letter_grade = 'B-';
        elseif ($overall_average >= 77) $letter_grade = 'C+';
        elseif ($overall_average >= 73) $letter_grade = 'C';
        elseif ($overall_average >= 70) $letter_grade = 'C-';
        elseif ($overall_average >= 67) $letter_grade = 'D+';
        elseif ($overall_average >= 65) $letter_grade = 'D';
        
        $students[] = [
            'student_id' => $student_id,
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'enrollment_status' => $row['enrollment_status'],
            'assessments' => $assessments,
            'assignments' => $assignments,
            'assessment_average' => round($assessment_average, 2),
            'assignment_average' => round($assignment_average, 2),
            'overall_average' => round($overall_average, 2),
            'letter_grade' => $letter_grade,
            'assessment_count' => $assessment_count,
            'assignment_count' => $assignment_count
        ];
    }
    
    // Calculate class statistics
    $class_averages = array_column($students, 'overall_average');
    $class_average = count($class_averages) > 0 ? array_sum($class_averages) / count($class_averages) : 0;
    $highest_grade = count($class_averages) > 0 ? max($class_averages) : 0;
    $lowest_grade = count($class_averages) > 0 ? min($class_averages) : 0;
    
    echo json_encode([
        'success' => true,
        'program' => $program,
        'students' => $students,
        'statistics' => [
            'total_students' => count($students),
            'class_average' => round($class_average, 2),
            'highest_grade' => $highest_grade,
            'lowest_grade' => $lowest_grade
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-program-grades.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
