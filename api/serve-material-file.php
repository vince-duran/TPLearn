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
    $material_id = $_GET['material_id'] ?? null;
    $action = $_GET['action'] ?? 'view'; // 'view' or 'download'
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    if (!$material_id) {
        throw new Exception('Material ID is required');
    }

    // Get material information and verify access
    $stmt = $conn->prepare("
        SELECT 
            fu.*,
            pm.id as material_id,
            pm.title,
            pm.program_id,
            p.tutor_id
        FROM program_materials pm
        JOIN file_uploads fu ON pm.file_upload_id = fu.id
        JOIN programs p ON pm.program_id = p.id
        WHERE pm.id = ?
    ");
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    $material = $stmt->get_result()->fetch_assoc();

    if (!$material) {
        throw new Exception('Material not found');
    }

    // Check access permissions
    $has_access = false;
    
    if ($user_role === 'tutor' && $material['tutor_id'] == $user_id) {
        // Tutor can access their own program materials
        $has_access = true;
    } elseif ($user_role === 'student') {
        // Check if student is enrolled in the program
        $stmt = $conn->prepare("
            SELECT 1 FROM enrollments 
            WHERE student_user_id = ? AND program_id = ? AND status = 'active'
        ");
        $stmt->bind_param('ii', $user_id, $material['program_id']);
        $stmt->execute();
        $enrollment = $stmt->get_result()->fetch_assoc();
        if ($enrollment) {
            $has_access = true;
        }
    } elseif ($user_role === 'admin') {
        // Admin can access all materials
        $has_access = true;
    }

    if (!$has_access) {
        throw new Exception('Access denied');
    }

    // Get file path and verify it exists
    $file_path = $material['file_path'];
    
    // Debug logging
    error_log("Serve Material File Debug:");
    error_log("Material ID: " . $material_id);
    error_log("File path from DB: " . $file_path);
    error_log("Action: " . $action);
    
    // Handle different path formats
    if (strpos($file_path, '../') === 0) {
        $full_path = __DIR__ . '/' . $file_path;
    } elseif (strpos($file_path, '/') === 0) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
    } else {
        $full_path = __DIR__ . '/../' . $file_path;
    }

    error_log("Full path: " . $full_path);
    error_log("File exists: " . (file_exists($full_path) ? 'YES' : 'NO'));
    if (file_exists($full_path)) {
        error_log("File size: " . filesize($full_path));
        error_log("MIME type: " . ($material['mime_type'] ?: 'unknown'));
    }

    if (!file_exists($full_path)) {
        error_log("File not found error - Material: " . $material_id . ", Path: " . $full_path);
        throw new Exception('File not found on server');
    }

    // Set headers based on action and file type
    $filename = $material['original_filename'] ?: $material['filename'];
    $mime_type = $material['mime_type'] ?: 'application/octet-stream';
    
    error_log("Action received: '" . $action . "'");
    error_log("Filename: " . $filename);
    error_log("MIME type: " . $mime_type);
    
    if ($action === 'download') {
        error_log("Setting headers for DOWNLOAD");
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        error_log("Setting headers for VIEW");
        
        // Check if file type can be viewed inline in browser
        $viewable_types = [
            'application/pdf',
            'text/plain',
            'text/html',
            'text/css',
            'text/javascript',
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ];
        
        if (in_array(strtolower($mime_type), $viewable_types)) {
            error_log("File type can be viewed inline");
            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: inline; filename="' . $filename . '"');
        } else {
            error_log("File type cannot be viewed inline, forcing download");
            // For non-viewable files, force download even on view action
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
    }

    header('Content-Length: ' . filesize($full_path));
    header('Cache-Control: private, max-age=3600');
    
    // Output file
    readfile($full_path);

} catch (Exception $e) {
    http_response_code(400);
    echo 'Error: ' . $e->getMessage();
} catch (Error $e) {
    http_response_code(500);
    echo 'Server error: ' . $e->getMessage();
}
?>