<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get file ID from request
$file_id = $_GET['file_id'] ?? null;
$action = $_GET['action'] ?? 'download'; // 'download' or 'view'

if (!$file_id || !is_numeric($file_id)) {
    http_response_code(400);
    exit('Invalid file ID');
}

try {
    // Get file information from database
    $stmt = $conn->prepare("
        SELECT 
            f.*,
            pm.program_id,
            pm.title as material_title
        FROM file_uploads f
        LEFT JOIN program_materials pm ON f.id = pm.file_upload_id
        WHERE f.id = ?
    ");
    $stmt->bind_param('i', $file_id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if (!$file) {
        http_response_code(404);
        exit('File not found');
    }

    // Check access permissions
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    $has_access = false;

    if ($user_role === 'admin') {
        $has_access = true;
    } elseif ($user_role === 'tutor') {
        // Tutor can access files from their programs
        if ($file['program_id']) {
            $stmt = $conn->prepare("SELECT id FROM programs WHERE id = ? AND tutor_id = ?");
            $stmt->bind_param('ii', $file['program_id'], $user_id);
            $stmt->execute();
            $has_access = $stmt->get_result()->num_rows > 0;
        } else {
            // Or files they uploaded
            $has_access = ($file['user_id'] == $user_id);
        }
    } elseif ($user_role === 'student') {
        // Student can access files from programs they're enrolled in
        if ($file['program_id']) {
            $stmt = $conn->prepare("
                SELECT e.id 
                FROM enrollments e 
                WHERE e.program_id = ? AND e.student_user_id = ? AND e.status = 'active'
            ");
            $stmt->bind_param('ii', $file['program_id'], $user_id);
            $stmt->execute();
            $has_access = $stmt->get_result()->num_rows > 0;
        }
    }

    if (!$has_access) {
        http_response_code(403);
        exit('Access denied');
    }

    // Fix file path - remove ../ prefix and construct correct path
    $clean_path = str_replace('../', '', $file['file_path']);
    $full_path = dirname(__DIR__) . '/' . $clean_path;
    
    // Check if file exists on disk
    if (!file_exists($full_path)) {
        http_response_code(404);
        exit('File not found on disk: ' . $full_path);
    }

    // Set appropriate headers
    $mime_type = $file['mime_type'] ?: 'application/octet-stream';
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . $file['file_size']);

    if ($action === 'download') {
        header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    } else {
        // For viewing, set inline disposition
        header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
    }

    // Prevent caching for sensitive content
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output the file
    readfile($full_path);

} catch (Exception $e) {
    error_log("File serve error: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
}
?>