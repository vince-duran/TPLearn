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
    // Get file information and verify it's a submission file the user can access
    // First try assessment submissions (since that's what we're testing)
    $stmt = $conn->prepare("
        SELECT 
            f.*,
            aa.student_user_id,
            aa.assessment_id,
            a.title as assessment_title,
            pm.title as material_title,
            p.tutor_id
        FROM file_uploads f
        INNER JOIN assessment_attempts aa ON f.id = aa.submission_file_id
        INNER JOIN assessments a ON aa.assessment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        WHERE f.id = ?
    ");
    $stmt->bind_param('i', $file_id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    
    // If not found in assessments, try assignments
    if (!$file) {
        $stmt = $conn->prepare("
            SELECT 
                f.*,
                asub.student_id as student_user_id,
                asub.assignment_id,
                a.title as assignment_title,
                pm.title as material_title,
                p.tutor_id
            FROM file_uploads f
            INNER JOIN assignment_submissions asub ON f.id = asub.file_upload_id
            INNER JOIN assignments a ON asub.assignment_id = a.id
            INNER JOIN program_materials pm ON a.material_id = pm.id
            INNER JOIN programs p ON pm.program_id = p.id
            WHERE f.id = ?
        ");
        $stmt->bind_param('i', $file_id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
    }

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
    } elseif ($user_role === 'tutor' && $file['tutor_id'] == $user_id) {
        // Tutor can access submission files from their programs
        $has_access = true;
    } elseif ($user_role === 'student' && $file['student_user_id'] == $user_id) {
        // Student can only access their own submission files
        $has_access = true;
    }

    if (!$has_access) {
        http_response_code(403);
        exit('Access denied');
    }

    // Check if file exists on disk
    $file_path = '../' . $file['file_path'];
    if (!file_exists($file_path)) {
        http_response_code(404);
        exit('File not found on disk');
    }

    // Get file info
    $file_size = filesize($file_path);
    $file_extension = strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION));
    
    // Determine MIME type based on extension
    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg'
    ];
    
    $content_type = $mime_types[$file_extension] ?? 'application/octet-stream';

    // Set headers based on action
    if ($action === 'view' && in_array($file_extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'])) {
        // View in browser (inline)
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
    } else {
        // Force download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    }
    
    header('Content-Length: ' . $file_size);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Clear any output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Output the file
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log("Error serving submission file: " . $e->getMessage());
    http_response_code(500);
    exit('Internal server error');
}
?>
