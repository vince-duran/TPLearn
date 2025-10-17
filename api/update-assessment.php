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
    $assessment_id = $_POST['assessment_id'] ?? null;
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
    if (!$assessment_id || !$title) {
        throw new Exception('Missing required fields');
    }
    
    // Verify the assessment belongs to this tutor
    // Join through program_materials to get the program_id and verify tutor ownership
    $stmt = $pdo->prepare("
        SELECT a.id, pm.program_id 
        FROM assessments a 
        JOIN program_materials pm ON a.material_id = pm.id
        JOIN programs p ON pm.program_id = p.id 
        WHERE a.id = ? AND p.tutor_id = ?
    ");
    $stmt->execute([$assessment_id, $tutor_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assessment) {
        throw new Exception('Assessment not found or access denied');
    }
    
    // Update assessment
    $stmt = $pdo->prepare("
        UPDATE assessments 
        SET title = ?, 
            total_points = ?, 
            due_date = ?, 
            instructions = ?, 
            allow_multiple_attempts = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $title,
        $total_points,
        $formatted_due_date,
        $instructions,
        $allow_multiple_attempts,
        $assessment_id
    ]);
    
    if (!$result) {
        throw new Exception('Failed to update assessment');
    }
    
    // Log the update
    error_log("Assessment updated: ID $assessment_id by tutor $tutor_id");
    
    echo json_encode([
        'success' => true,
        'message' => 'Assessment updated successfully',
        'assessment_id' => $assessment_id
    ]);
    
} catch (Exception $e) {
    error_log("Update assessment error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>