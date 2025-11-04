<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

try {
    $assessment_id = $_GET['assessment_id'] ?? null;
    $action = $_GET['action'] ?? 'view'; // 'view' or 'download'
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    if (!$assessment_id) {
        throw new Exception('Assessment ID is required');
    }
    
    // Get assessment details with file info
    $stmt = $conn->prepare("
        SELECT 
            a.*,
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
        http_response_code(404);
        exit('Assessment not found');
    }
    
    if (!$assessment['file_name'] || !$assessment['file_path']) {
        http_response_code(404);
        exit('No file available for this assessment');
    }
    
    // Check permissions
    if ($user_role === 'tutor' && $assessment['tutor_id'] != $user_id) {
        http_response_code(403);
        exit('Access denied');
    } else if ($user_role === 'student') {
        // Check if student is enrolled in the program
        $enrollment_stmt = $conn->prepare("
            SELECT e.id FROM enrollments e
            INNER JOIN program_materials pm ON pm.program_id = e.program_id
            INNER JOIN assessments a ON a.material_id = pm.id
            WHERE e.student_user_id = ? AND a.id = ?
        ");
        $enrollment_stmt->bind_param('ii', $user_id, $assessment_id);
        $enrollment_stmt->execute();
        $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
        
        if (!$enrollment) {
            http_response_code(403);
            exit('Access denied - not enrolled in this program');
        }
    }
    
    // Build the full file path
    $file_path = $assessment['file_path'];
    
    // Handle path resolution - if path starts with ../ then it's relative to API directory
    if (strpos($file_path, '../') === 0) {
        // Path already includes ../ prefix, use as is
        $full_path = $file_path;
    } else {
        // Path doesn't include ../ prefix, add it
        $full_path = '../' . $file_path;
    }
    
    // Check if file exists
    if (!file_exists($full_path)) {
        // Try alternative path without ../
        $alt_path = str_replace('../', '', $file_path);
        if (file_exists($alt_path)) {
            $full_path = $alt_path;
        } else {
            error_log("Assessment file not found at: " . $full_path . " or " . $alt_path);
            http_response_code(404);
            exit('File not found on server');
        }
    }
    
    // Get file info
    $file_size = filesize($full_path);
    $file_name = $assessment['file_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Set appropriate content type
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    $content_type = $content_types[$file_ext] ?? 'application/octet-stream';
    
    // Set headers
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . $file_size);
    
    if ($action === 'download') {
        header('Content-Disposition: attachment; filename="' . addslashes($file_name) . '"');
    } else {
        header('Content-Disposition: inline; filename="' . addslashes($file_name) . '"');
    }
    
    // Prevent caching of sensitive files
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Expires: 0');
    header('Pragma: no-cache');
    
    // Output the file
    readfile($full_path);
    exit();
    
} catch (Exception $e) {
    error_log("Serve assessment file error: " . $e->getMessage());
    http_response_code(400);
    exit('Error: ' . $e->getMessage());
}
?>