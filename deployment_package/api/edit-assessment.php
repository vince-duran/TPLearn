<?php
// Start output buffering to prevent any unexpected output
ob_start();

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is authenticated
if (!isLoggedIn()) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Clean any output buffer and start fresh
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $total_points = intval($_POST['total_points'] ?? 100);
    $due_date = $_POST['due_date'] ?? null;
    $allow_multiple_attempts = isset($_POST['allow_multiple_attempts']) ? 1 : 0;
    $max_attempts = intval($_POST['max_attempts'] ?? -1);
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    // Validate input
    if (!$assessment_id) {
        throw new Exception('Assessment ID is required');
    }
    
    if (empty($title)) {
        throw new Exception('Assessment title is required');
    }
    
    if ($total_points < 1 || $total_points > 1000) {
        throw new Exception('Total points must be between 1 and 1000');
    }
    
    // Verify assessment exists and user has permission to edit it
    $stmt = $conn->prepare("
        SELECT a.*, pm.program_id, p.tutor_id
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
    }
    
    // Handle file upload if new file is provided
    $file_name = $assessment['file_name'];
    $file_path = $assessment['file_path'];
    
    if (isset($_FILES['assessment_file']) && $_FILES['assessment_file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['assessment_file'];
        
        // Validate file
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($uploaded_file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only PDF, Word documents, and images are allowed.');
        }
        
        // Check file size (10MB limit)
        if ($uploaded_file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File size must be less than 10MB');
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/assessments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
        $file_name = $uploaded_file['name'];
        $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
            throw new Exception('Failed to upload file');
        }
        
        // Delete old file if it exists
        if ($assessment['file_path'] && file_exists($assessment['file_path'])) {
            unlink($assessment['file_path']);
        }
    }
    
    // Format due date for database
    $due_date_formatted = null;
    if ($due_date && $due_date !== '') {
        $due_date_formatted = date('Y-m-d H:i:s', strtotime($due_date));
    }
    
    // Update assessment in database
    $stmt = $conn->prepare("
        UPDATE assessments 
        SET title = ?, 
            instructions = ?, 
            total_points = ?, 
            due_date = ?, 
            allow_multiple_attempts = ?, 
            max_attempts = ?,
            file_name = ?,
            file_path = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->bind_param('ssisssssi', 
        $title, 
        $instructions, 
        $total_points, 
        $due_date_formatted, 
        $allow_multiple_attempts, 
        $max_attempts,
        $file_name,
        $file_path,
        $assessment_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update assessment: ' . $stmt->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Assessment updated successfully',
        'assessment_id' => $assessment_id
    ]);
    
} catch (Exception $e) {
    // Clean output buffer in case of errors
    ob_clean();
    error_log("Edit assessment error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffering and flush
ob_end_flush();
?>