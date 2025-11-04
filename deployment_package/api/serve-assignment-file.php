<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized access';
    exit();
}

try {
    $file_id = $_GET['file_id'] ?? null;
    $action = $_GET['action'] ?? 'view'; // 'view' or 'download'
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    // Get file information and verify access
    $stmt = $conn->prepare("
        SELECT 
            fu.*,
            asub.assignment_id,
            asub.student_user_id as student_id,
            a.material_id,
            pm.program_id,
            p.tutor_id
        FROM file_uploads fu
        JOIN assignment_submissions asub ON asub.file_upload_id = fu.id
        JOIN assignments a ON asub.assignment_id = a.id
        JOIN program_materials pm ON a.material_id = pm.id
        JOIN programs p ON pm.program_id = p.id
        WHERE fu.id = ?
    ");
    $stmt->bind_param('i', $file_id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if (!$file) {
        throw new Exception('File not found');
    }

    // Check access permissions
    $has_access = false;
    
    if ($user_role === 'student' && $file['student_id'] == $user_id) {
        // Student can access their own submission
        $has_access = true;
    } elseif ($user_role === 'tutor' && $file['tutor_id'] == $user_id) {
        // Tutor can access submissions for their assignments
        $has_access = true;
    } elseif ($user_role === 'admin') {
        // Admin can access all files
        $has_access = true;
    }

    if (!$has_access) {
        throw new Exception('Access denied');
    }

    // Check if file exists on disk
    $file_path = $file['file_path'];
    
    // Handle different path formats
    if (strpos($file_path, '../') === 0) {
        $full_path = __DIR__ . '/' . $file_path;
    } elseif (strpos($file_path, '/') === 0) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
    } else {
        $full_path = __DIR__ . '/../' . $file_path;
    }
    
    if (!file_exists($full_path)) {
        throw new Exception('File not found on server: ' . $full_path);
    }

    // Set appropriate headers
    $mime_type = $file['mime_type'] ?: 'application/octet-stream';
    
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($full_path));

    if ($action === 'download') {
        // Force download
        header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    } else {
        // Display inline (for viewing)
        header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
    }

    // Prevent caching
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output the file
    readfile($full_path);

} catch (Exception $e) {
    http_response_code(404);
    echo 'Error: ' . $e->getMessage();
}
?>
