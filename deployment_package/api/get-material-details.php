<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('tutor');

header('Content-Type: application/json');

try {
    // Get material ID from query parameter
    $material_id = $_GET['material_id'] ?? null;
    $program_id = $_GET['program_id'] ?? null;
    $tutor_id = $_SESSION['user_id'] ?? null;

    // Validate required fields
    if (!$material_id || !$program_id || !$tutor_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: material_id, program_id, or tutor not authenticated'
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
            'message' => 'Access denied: You do not have permission to access materials from this program'
        ]);
        exit();
    }

    // Get material details
    $stmt = $conn->prepare("
        SELECT 
            pm.id as material_id,
            pm.title,
            pm.description,
            pm.material_type,
            f.original_name as file_name,
            f.filename as stored_filename,
            f.file_size,
            pm.created_at as upload_date,
            u.username as uploaded_by
        FROM program_materials pm
        INNER JOIN file_uploads f ON pm.file_upload_id = f.id
        INNER JOIN users u ON f.user_id = u.id
        WHERE pm.id = ? AND pm.program_id = ?
    ");
    $stmt->bind_param('ii', $material_id, $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Material not found or does not belong to this program'
        ]);
        exit();
    }

    $material = $result->fetch_assoc();
    
    // Format file size
    if ($material['file_size']) {
        $file_size_mb = round($material['file_size'] / (1024 * 1024), 1);
        $material['file_size_formatted'] = $file_size_mb . ' MB';
    } else {
        $material['file_size_formatted'] = 'Unknown size';
    }
    
    // Format upload date
    if ($material['upload_date']) {
        $material['upload_date_formatted'] = date('M j, Y, g:i A', strtotime($material['upload_date']));
    } else {
        $material['upload_date_formatted'] = 'Unknown date';
    }

    echo json_encode([
        'success' => true,
        'data' => $material
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>