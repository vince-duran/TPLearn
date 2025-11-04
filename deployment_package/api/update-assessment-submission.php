<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is authenticated and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $attempt_id = $_POST['attempt_id'] ?? null;
    $comments = $_POST['comments'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    if (!$attempt_id) {
        throw new Exception('Attempt ID is required');
    }
    
    // Get attempt details and verify ownership
    $stmt = $conn->prepare("
        SELECT 
            aa.*,
            a.title as assessment_title,
            a.due_date,
            pm.due_date as material_due_date,
            COALESCE(a.due_date, pm.due_date) as effective_due_date
        FROM assessment_attempts aa
        INNER JOIN assessments a ON aa.assessment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        WHERE aa.id = ? AND aa.student_user_id = ?
    ");
    
    $stmt->bind_param('ii', $attempt_id, $user_id);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    
    if (!$attempt) {
        throw new Exception('Assessment attempt not found or access denied');
    }
    
    // Check if already submitted but not graded yet
    if (!$attempt['submitted_at']) {
        throw new Exception('Cannot update - assessment not submitted yet');
    }
    
    // Check if already graded
    if ($attempt['status'] === 'graded') {
        throw new Exception('Cannot update - assessment already graded');
    }
    
    // Check if due date has passed
    if ($attempt['effective_due_date']) {
        $due_date = new DateTime($attempt['effective_due_date']);
        $now = new DateTime();
        
        if ($now > $due_date) {
            throw new Exception('Cannot update - due date has passed');
        }
    }
    
    // Handle file upload if provided
    $new_file_id = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['submission_file'];
        
        // Validate file
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            throw new Exception('File size exceeds 10MB limit');
        }
        
        // Allowed file types
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowed_types));
        }
        
        // Generate unique filename
        $upload_dir = '../uploads/assessment_submissions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $unique_filename = 'assessment_' . uniqid() . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $unique_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to upload file');
        }
        
        // Store file information in database
        $file_stmt = $conn->prepare("
            INSERT INTO file_uploads (
                user_id,
                filename, 
                original_filename, 
                file_path, 
                file_size, 
                mime_type,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $mime_type = mime_content_type($file_path);
        $relative_path = 'uploads/assessment_submissions/' . $unique_filename;
        
        $file_stmt->bind_param('isssss', 
            $user_id,
            $unique_filename,
            $file['name'],
            $relative_path,
            $file['size'],
            $mime_type
        );
        
        if (!$file_stmt->execute()) {
            // Clean up uploaded file if database insert fails
            unlink($file_path);
            throw new Exception('Failed to save file information');
        }
        
        $new_file_id = $conn->insert_id;
        
        // Delete old file if it exists
        if ($attempt['submission_file_id']) {
            $old_file_stmt = $conn->prepare("SELECT file_path FROM file_uploads WHERE id = ?");
            $old_file_stmt->bind_param('i', $attempt['submission_file_id']);
            $old_file_stmt->execute();
            $old_file = $old_file_stmt->get_result()->fetch_assoc();
            
            if ($old_file && file_exists('../' . $old_file['file_path'])) {
                unlink('../' . $old_file['file_path']);
            }
            
            // Delete old file record
            $delete_stmt = $conn->prepare("DELETE FROM file_uploads WHERE id = ?");
            $delete_stmt->bind_param('i', $attempt['submission_file_id']);
            $delete_stmt->execute();
        }
    } else {
        throw new Exception('New file is required for update');
    }
    
    // Update the assessment attempt with new file and comments
    $update_stmt = $conn->prepare("
        UPDATE assessment_attempts 
        SET submission_file_id = ?,
            comments = ?,
            updated_at = NOW()
        WHERE id = ? AND student_user_id = ?
    ");
    
    $update_stmt->bind_param('isii', $new_file_id, $comments, $attempt_id, $user_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update submission');
    }
    
    // Log the update
    error_log("Assessment submission updated - User: $user_id, Attempt: $attempt_id, New File: $new_file_id");
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'Submission updated successfully',
        'attempt_id' => $attempt_id,
        'new_file_id' => $new_file_id
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Update assessment submission error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>