<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('tutor');

header('Content-Type: application/json');

try {
    // Get form data
    $material_id = $_POST['material_id'] ?? null;
    $program_id = $_POST['program_id'] ?? null;
    $tutor_id = $_SESSION['user_id'] ?? null;

    // Validate required fields
    if (!$material_id || !$program_id || !$tutor_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: material_id, program_id, or tutor_id'
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
            'message' => 'Access denied: You do not have permission to delete materials from this program'
        ]);
        exit();
    }

    // Get material details before deletion
    $stmt = $conn->prepare("
        SELECT f.file_path, f.filename as file_name, pm.material_type 
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
            'message' => 'Material not found or already deleted'
        ]);
        exit();
    }

    $material = $material_result->fetch_assoc();
    $file_path = $material['file_path'];
    $file_name = $material['file_name'];
    $material_type = $material['material_type'];

    // Get the file_upload_id to delete the physical file record
    $stmt = $conn->prepare("SELECT file_upload_id FROM program_materials WHERE id = ?");
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    $file_upload_result = $stmt->get_result();
    
    if ($file_upload_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'File upload record not found'
        ]);
        exit();
    }
    
    $file_upload_id = $file_upload_result->fetch_assoc()['file_upload_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // If it's an assignment, delete related submissions first
        if ($material_type === 'assignment') {
            // Find assignment record
            $stmt = $conn->prepare("SELECT id FROM assignments WHERE material_id = ?");
            $stmt->bind_param('i', $material_id);
            $stmt->execute();
            $assignment_result = $stmt->get_result();
            
            if ($assignment_result->num_rows > 0) {
                $assignment_id = $assignment_result->fetch_assoc()['id'];
                
                // Delete assignment submissions
                $stmt = $conn->prepare("DELETE FROM assignment_submissions WHERE assignment_id = ?");
                $stmt->bind_param('i', $assignment_id);
                $stmt->execute();
                
                // Delete assignment record
                $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
                $stmt->bind_param('i', $assignment_id);
                $stmt->execute();
                
                error_log("Deleted assignment and submissions for material ID: " . $material_id);
            }
        }

        // Delete from program_materials table first
        $stmt = $conn->prepare("DELETE FROM program_materials WHERE id = ? AND program_id = ?");
        $stmt->bind_param('ii', $material_id, $program_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete material from program_materials table');
        }

        // Check if any rows were affected
        if ($stmt->affected_rows === 0) {
            throw new Exception('No material found to delete');
        }

        // Delete from file_uploads table
        $stmt = $conn->prepare("DELETE FROM file_uploads WHERE id = ?");
        $stmt->bind_param('i', $file_upload_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete file record');
        }

        // Delete physical file if it exists
        $full_file_path = '../' . $file_path;
        if (file_exists($full_file_path)) {
            if (!unlink($full_file_path)) {
                error_log("Warning: Failed to delete physical file: " . $full_file_path);
                // Don't fail the entire operation if file deletion fails
            }
        }

        // Commit transaction
        $conn->commit();

        // Log successful deletion
        error_log("Material deleted successfully - ID: {$material_id}, File: {$file_name}, Type: {$material_type}");

        echo json_encode([
            'success' => true,
            'message' => 'Material deleted successfully',
            'data' => [
                'material_id' => $material_id,
                'file_name' => $file_name,
                'material_type' => $material_type
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Delete material error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete material: ' . $e->getMessage()
    ]);
}
?>
