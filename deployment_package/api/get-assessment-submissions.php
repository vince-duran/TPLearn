<?php
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get assessment ID from query parameters
$assessment_id = $_GET['assessment_id'] ?? null;

if (!$assessment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Assessment ID is required']);
    exit;
}

$tutor_user_id = $_SESSION['user_id'];

try {
    // First, verify that the tutor has access to this assessment
    $access_check_sql = "SELECT 
                            a.id, 
                            a.title, 
                            a.total_points, 
                            a.due_date,
                            pm.title as material_title,
                            p.name as program_name
                        FROM assessments a
                        INNER JOIN program_materials pm ON a.material_id = pm.id
                        INNER JOIN programs p ON pm.program_id = p.id
                        WHERE a.id = ? AND p.tutor_id = ?";
    
    $stmt = $conn->prepare($access_check_sql);
    $stmt->bind_param('ii', $assessment_id, $tutor_user_id);
    $stmt->execute();
    $assessment_result = $stmt->get_result();
    
    if ($assessment_result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied to this assessment']);
        exit;
    }
    
    $assessment = $assessment_result->fetch_assoc();
    
    // Get all students enrolled in the program and their assessment attempts
    $submissions_sql = "SELECT 
                            u.id as student_id,
                            CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                            u.username as student_username,
                            u.email as student_email,
                            aa.id as attempt_id,
                            aa.started_at,
                            aa.submitted_at,
                            aa.time_taken,
                            aa.score,
                            aa.status,
                            aa.comments,
                            f.id as file_id,
                            f.original_filename as file_name,
                            f.file_size,
                            f.mime_type,
                            aa.created_at,
                            aa.updated_at
                        FROM enrollments e
                        INNER JOIN users u ON e.student_user_id = u.id
                        INNER JOIN student_profiles sp ON u.id = sp.user_id
                        INNER JOIN programs p ON e.program_id = p.id
                        INNER JOIN program_materials pm ON pm.program_id = p.id
                        INNER JOIN assessments a ON a.material_id = pm.id
                        LEFT JOIN assessment_attempts aa ON (aa.assessment_id = a.id AND aa.student_user_id = u.id)
                        LEFT JOIN file_uploads f ON aa.submission_file_id = f.id
                        WHERE a.id = ? AND p.tutor_id = ?
                        ORDER BY sp.first_name, sp.last_name, aa.submitted_at DESC";
    
    $stmt = $conn->prepare($submissions_sql);
    $stmt->bind_param('ii', $assessment_id, $tutor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        // Only include students who have made an attempt or are enrolled
        $submissions[] = [
            'id' => $row['attempt_id'],
            'student_id' => $row['student_id'],
            'student_name' => $row['student_name'],
            'student_email' => $row['student_email'],
            'started_at' => $row['started_at'],
            'submitted_at' => $row['submitted_at'],
            'time_taken' => $row['time_taken'],
            'score' => $row['score'],
            'status' => $row['status'],
            'comments' => $row['comments'],
            'file_id' => $row['file_id'],
            'file_name' => $row['file_name'],
            'file_size' => $row['file_size'],
            'mime_type' => $row['mime_type'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'assessment' => [
            'id' => $assessment['id'],
            'title' => $assessment['title'],
            'total_points' => $assessment['total_points'],
            'due_date' => $assessment['due_date'],
            'material_title' => $assessment['material_title'],
            'program_name' => $assessment['program_name']
        ],
        'submissions' => $submissions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>