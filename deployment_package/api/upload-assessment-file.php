<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is authenticated and is a tutor
requireRole('tutor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get and validate input
    $material_id = $_POST['material_id'] ?? null;
    $program_id = $_POST['program_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $total_points = (int)($_POST['total_points'] ?? 100);
    $due_date = $_POST['due_date'] ?? null;
    $instructions = trim($_POST['instructions'] ?? '');
    $allow_multiple_attempts = isset($_POST['allow_multiple_attempts']) ? 1 : 0;
    $tutor_id = $_SESSION['user_id'];
    
    // Format due date for database
    $formatted_due_date = null;
    if ($due_date) {
        $formatted_due_date = date('Y-m-d H:i:s', strtotime($due_date));
    }
    
    // Validate required fields
    if (!$material_id || !$program_id || !$title) {
        throw new Exception('Missing required fields');
    }
    
    // Validate file upload
    if (!isset($_FILES['assessment_file']) || $_FILES['assessment_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }
    
    $file = $_FILES['assessment_file'];
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_type = $file['type'];
    
    // Validate file size (max 10MB)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file_size > $max_size) {
        throw new Exception('File size exceeds 10MB limit');
    }
    
    // Validate file type
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_type = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);
    
    if (!in_array($detected_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only PDF, DOC, DOCX, and images are allowed');
    }
    
    // Verify tutor has permission for this program and material
    $stmt = $conn->prepare("
        SELECT p.id 
        FROM programs p 
        INNER JOIN program_materials m ON m.program_id = p.id 
        WHERE p.id = ? AND p.tutor_id = ? AND m.id = ?
    ");
    $stmt->bind_param('iii', $program_id, $tutor_id, $material_id);
    $stmt->execute();
    $program = $stmt->get_result()->fetch_assoc();
    
    if (!$program) {
        throw new Exception('Access denied - Invalid program or material');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Generate unique filename
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_filename = 'assessment_' . uniqid() . '_' . time() . '.' . $file_extension;
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/assessments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file_tmp, $file_path)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Check if assessment already exists for this material
        $check_stmt = $conn->prepare("SELECT id FROM assessments WHERE material_id = ?");
        $check_stmt->bind_param('i', $material_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing assessment
            $stmt = $conn->prepare("
                UPDATE assessments 
                SET title = ?, total_points = ?, due_date = ?, instructions = ?, 
                    allow_multiple_attempts = ?, file_name = ?, file_path = ?, 
                    updated_at = NOW()
                WHERE material_id = ?
            ");
            $stmt->bind_param('sdssissi', $title, $total_points, $formatted_due_date, $instructions, 
                             $allow_multiple_attempts, $file_name, $unique_filename, $material_id);
        } else {
            // Insert new assessment
            $stmt = $conn->prepare("
                INSERT INTO assessments (material_id, title, total_points, due_date, instructions, 
                                       allow_multiple_attempts, file_name, file_path, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('isdsisssi', $material_id, $title, $total_points, $formatted_due_date, 
                             $instructions, $allow_multiple_attempts, $file_name, $unique_filename, $tutor_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save assessment to database');
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Assessment file uploaded successfully',
            'data' => [
                'title' => $title,
                'file_name' => $file_name,
                'total_points' => $total_points,
                'due_date' => $due_date
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        // Clean up uploaded file if it exists
        if (isset($file_path) && file_exists($file_path)) {
            unlink($file_path);
        }
        
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Assessment upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>