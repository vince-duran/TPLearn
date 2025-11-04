<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    $file_id = $_GET['file_id'] ?? null;
    $tutor_id = $_SESSION['user_id'];

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    // First, try to find the file in assignment submissions
    $stmt = $conn->prepare("
        SELECT 
            fu.id,
            fu.filename,
            fu.original_filename,
            fu.file_path,
            fu.file_size,
            fu.mime_type,
            fu.created_at,
            asub.id as submission_id,
            u.username as student_username,
            pm.title as assignment_title,
            p.tutor_id,
            'assignment' as submission_type
        FROM file_uploads fu
        JOIN assignment_submissions asub ON fu.id = asub.file_upload_id
        JOIN assignments a ON asub.assignment_id = a.id
        JOIN program_materials pm ON a.material_id = pm.id
        JOIN programs p ON pm.program_id = p.id
        JOIN users u ON asub.student_id = u.id
        WHERE fu.id = ? AND p.tutor_id = ?
        
        UNION ALL
        
        SELECT 
            fu.id,
            fu.filename,
            fu.original_filename,
            fu.file_path,
            fu.file_size,
            fu.mime_type,
            fu.created_at,
            aa.id as submission_id,
            u.username as student_username,
            a.title as assignment_title,
            p.tutor_id,
            'assessment' as submission_type
        FROM file_uploads fu
        JOIN assessment_attempts aa ON fu.id = aa.submission_file_id
        JOIN assessments a ON aa.assessment_id = a.id
        JOIN program_materials pm ON a.material_id = pm.id
        JOIN programs p ON pm.program_id = p.id
        JOIN users u ON aa.student_user_id = u.id
        WHERE fu.id = ? AND p.tutor_id = ?
        LIMIT 1
    ");
    
    $stmt->bind_param('iiii', $file_id, $tutor_id, $file_id, $tutor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file_info = $result->fetch_assoc();

    if (!$file_info) {
        throw new Exception('File not found or you do not have access to it');
    }

    // Return file information
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $file_info['id'],
            'filename' => $file_info['filename'],
            'original_filename' => $file_info['original_filename'], // Use original_filename for consistency
            'file_path' => $file_info['file_path'],
            'file_size' => (int)$file_info['file_size'],
            'mime_type' => $file_info['mime_type'],
            'created_at' => $file_info['created_at'],
            'student_username' => $file_info['student_username'],
            'assignment_title' => $file_info['assignment_title'],
            'submission_type' => $file_info['submission_type'],
            'submission_id' => $file_info['submission_id']
        ]
    ]);

} catch (Exception $e) {
    error_log("Get file info error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>