<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is authenticated
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo 'Method not allowed';
    exit();
}

try {
    $assessment_id = $_GET['assessment_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    if (!$assessment_id) {
        throw new Exception('Assessment ID is required');
    }
    
    // Get assessment with permission check
    $stmt = $conn->prepare("
        SELECT 
            a.file_path,
            a.file_name,
            pm.title as material_title,
            p.tutor_id
        FROM assessments a
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        WHERE a.id = ?
    ");
    
    $stmt->bind_param('i', $assessment_id);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();
    
    if (!$assessment) {
        throw new Exception('Assessment not found');
    }
    
    // Check permissions
    if ($user_role === 'tutor' && $assessment['tutor_id'] != $user_id) {
        throw new Exception('Access denied - not your assessment');
    } else if ($user_role === 'student') {
        // Check if student is enrolled in the program
        $enrollment_stmt = $conn->prepare("
            SELECT e.id FROM enrollments e
            INNER JOIN program_materials pm ON pm.program_id = e.program_id
            INNER JOIN assessments a ON a.material_id = pm.id
            WHERE e.student_id = ? AND a.id = ?
        ");
        $enrollment_stmt->bind_param('ii', $user_id, $assessment_id);
        $enrollment_stmt->execute();
        $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
        
        if (!$enrollment) {
            throw new Exception('Access denied - not enrolled in this program');
        }
    }
    
    // Build file path
    $file_path = '../uploads/assessments/' . $assessment['file_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('Assessment file not found on server');
    }
    
    // Get file info
    $file_size = filesize($file_path);
    $file_name = $assessment['file_name'];
    
    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($file_name) . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($file_path);
    exit();
    
} catch (Exception $e) {
    error_log("Assessment download error: " . $e->getMessage());
    http_response_code(404);
    echo 'File not found: ' . $e->getMessage();
    exit();
}
?>