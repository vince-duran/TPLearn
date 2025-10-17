<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('tutor');

header('Content-Type: application/json');

try {
    // Get form data
    $material_id = $_POST['material_id'] ?? null;
    $program_id = $_POST['program_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? null;
    $tutor_id = $_SESSION['user_id'] ?? null;

    // Validate required fields
    if (!$material_id || !$program_id || !$title || !$tutor_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: material_id, program_id, title, or tutor_id'
        ]);
        exit();
    }

    // Verify tutor owns this program
    $stmt = $conn->prepare("SELECT id FROM programs WHERE id = ? AND tutor_id = ?");
    $stmt->bind_param('ii', $program_id, $tutor_id);
    $stmt->execute();
    $program_result = $stmt->get_result();
    
    if ($program_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Access denied: You do not have permission to edit materials in this program'
        ]);
        exit();
    }

    // Verify material exists and belongs to this program
    $stmt = $conn->prepare("
        SELECT pm.id, f.filename as file_name, pm.file_upload_id
        FROM program_materials pm
        INNER JOIN file_uploads f ON pm.file_upload_id = f.id
        WHERE pm.id = ? AND pm.program_id = ?
    ");
    $stmt->bind_param('ii', $material_id, $program_id);
    $stmt->execute();
    $material_result = $stmt->get_result();
    
    if ($material_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Material not found or does not belong to this program'
        ]);
        exit();
    }

    $material = $material_result->fetch_assoc();
    $file_upload_id = $material['file_upload_id'];
    
    // Handle file replacement if new file is uploaded
    $file_updated = false;
    $new_file_name = $material['file_name']; // Default to existing file name
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/materials/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file = $_FILES['file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov'];
        
        // Validate file extension
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions)
            ]);
            exit();
        }
        
        // Generate unique filename
        $new_file_name = uniqid() . '_' . $file['name'];
        $file_path = $upload_dir . $new_file_name;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $file_updated = true;
            
            // TODO: Delete old file if needed
            // $old_file_path = $upload_dir . $material['file_name'];
            // if (file_exists($old_file_path)) {
            //     unlink($old_file_path);
            // }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to upload new file'
            ]);
            exit();
        }
    }

    // Update material in database
    // First update the program_materials table
    $stmt = $conn->prepare("
        UPDATE program_materials 
        SET title = ?, description = ?, due_date = ?
        WHERE id = ? AND program_id = ?
    ");
    $stmt->bind_param('sssii', $title, $description, $due_date, $material_id, $program_id);
    
    if (!$stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update material: ' . $stmt->error
        ]);
        exit();
    }
    
    // If file was updated, also update the file_uploads table
    if ($file_updated) {
        $stmt = $conn->prepare("
            UPDATE file_uploads 
            SET filename = ?, file_size = ?
            WHERE id = ?
        ");
        $file_size = filesize($file_path);
        $stmt->bind_param('sii', $new_file_name, $file_size, $file_upload_id);
        
        if (!$stmt->execute()) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update file record: ' . $stmt->error
            ]);
            exit();
        }
    }

    // Handle assignment due date update
    if ($due_date) {
        // Check if this is an assignment and update due date
        $stmt = $conn->prepare("
            SELECT a.id 
            FROM assignments a
            INNER JOIN program_materials pm ON a.material_id = pm.id
            WHERE pm.id = ?
        ");
        $stmt->bind_param('i', $material_id);
        $stmt->execute();
        $assignment_result = $stmt->get_result();
        
        if ($assignment_result->num_rows > 0) {
            $assignment_id = $assignment_result->fetch_assoc()['id'];
            
            // Update assignment due date
            $stmt = $conn->prepare("
                UPDATE assignments 
                SET due_date = ?
                WHERE id = ?
            ");
            $stmt->bind_param('si', $due_date, $assignment_id);
            
            if (!$stmt->execute()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update assignment due date: ' . $stmt->error
                ]);
                exit();
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Material updated successfully',
        'data' => [
            'material_id' => $material_id,
            'title' => $title,
            'description' => $description,
            'file_updated' => $file_updated,
            'file_name' => $new_file_name
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
