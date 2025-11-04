<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $file_id = $_GET['file_id'] ?? null;
    $download = isset($_GET['download']) && $_GET['download'] == '1';
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    // Get file information and verify access
    $stmt = $conn->prepare("
        SELECT 
            fu.id,
            fu.file_path,
            fu.original_filename,
            fu.file_size,
            fu.mime_type,
            asub.student_user_id,
            asub.assignment_id,
            a.material_id,
            pm.program_id,
            p.tutor_id
        FROM file_uploads fu
        INNER JOIN assignment_submissions asub ON asub.file_upload_id = fu.id
        INNER JOIN assignments a ON asub.assignment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        WHERE fu.id = ?
    ");
    
    $stmt->bind_param('i', $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();

    if (!$file) {
        throw new Exception('File not found');
    }

    // Check access permissions
    $has_access = false;
    
    if ($user_role === 'student' && $file['student_user_id'] == $user_id) {
        // Student can access their own submission
        $has_access = true;
    } elseif ($user_role === 'tutor' && $file['tutor_id'] == $user_id) {
        // Tutor can access submissions in their programs
        $has_access = true;
    } elseif ($user_role === 'admin') {
        // Admin can access all files
        $has_access = true;
    }

    if (!$has_access) {
        throw new Exception('Access denied');
    }

    // Check if file exists on disk
    $file_path = '../' . $file['file_path'];
    if (!file_exists($file_path)) {
        throw new Exception('File not found on disk');
    }

    // Set headers for file serving
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . $file['file_size']);
    
    if ($download) {
        header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
    }

    // Serve the file
    readfile($file_path);

} catch (Exception $e) {
    http_response_code(404);
    echo 'Error: ' . $e->getMessage();
}
?>
