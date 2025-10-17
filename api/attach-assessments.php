<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('tutor');

header('Content-Type: application/json');

try {
    // Get form data
    $material_id = $_POST['material_id'] ?? null;
    $assessment_id = $_POST['assessment_id'] ?? null;
    $tutor_id = $_SESSION['user_id'] ?? null;

    // Validate required fields
    if (!$material_id || !$assessment_id || !$tutor_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: material_id, assessment_id, or tutor_id'
        ]);
        exit();
    }

    // Verify tutor owns both the material and assessment
    $stmt = $conn->prepare("
        SELECT pm1.id as material_exists, pm2.id as assessment_exists
        FROM program_materials pm1
        INNER JOIN programs p1 ON pm1.program_id = p1.id
        CROSS JOIN program_materials pm2
        INNER JOIN programs p2 ON pm2.program_id = p2.id
        WHERE pm1.id = ? AND pm2.id = ? 
        AND p1.tutor_id = ? AND p2.tutor_id = ?
    ");
    $stmt->bind_param('iiii', $material_id, $assessment_id, $tutor_id, $tutor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Access denied: You do not have permission to attach these materials'
        ]);
        exit();
    }

    // Check if attachment already exists
    $stmt = $conn->prepare("
        SELECT id FROM material_assessments 
        WHERE material_id = ? AND assessment_id = ?
    ");
    $stmt->bind_param('ii', $material_id, $assessment_id);
    $stmt->execute();
    $existing = $stmt->get_result();
    
    if ($existing->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This assessment is already attached to this material'
        ]);
        exit();
    }

    // Create the attachment
    $stmt = $conn->prepare("
        INSERT INTO material_assessments (material_id, assessment_id, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param('ii', $material_id, $assessment_id);
    
    if ($stmt->execute()) {
        $attachment_id = $conn->insert_id;
        
        // Get assessment details for response
        $stmt = $conn->prepare("
            SELECT title, description, due_date, max_score 
            FROM program_materials 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $assessment_id);
        $stmt->execute();
        $assessment = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Assessment attached successfully!',
            'data' => [
                'attachment_id' => $attachment_id,
                'material_id' => $material_id,
                'assessment_id' => $assessment_id,
                'assessment_title' => $assessment['title'],
                'assessment_description' => $assessment['description'],
                'due_date' => $assessment['due_date'],
                'max_score' => $assessment['max_score']
            ]
        ]);
    } else {
        throw new Exception('Failed to create attachment: ' . $stmt->error);
    }

} catch (Exception $e) {
    error_log("Error in attach-assessments.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
