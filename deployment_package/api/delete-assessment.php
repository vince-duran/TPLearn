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
    // Get input from JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $assessment_id = $input['assessment_id'] ?? null;
    $tutor_id = $_SESSION['user_id'];
    
    // Validate required fields
    if (!$assessment_id) {
        throw new Exception('Assessment ID is required');
    }
    
    // Verify the assessment belongs to this tutor
    $stmt = $pdo->prepare("
        SELECT a.id, a.program_id, a.file_path, a.title
        FROM assessments a 
        JOIN programs p ON a.program_id = p.id 
        WHERE a.id = ? AND p.tutor_id = ?
    ");
    $stmt->execute([$assessment_id, $tutor_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assessment) {
        throw new Exception('Assessment not found or access denied');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete related assessment submissions first
        $stmt = $pdo->prepare("DELETE FROM assessment_submissions WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);
        
        // Delete the assessment file if it exists
        if ($assessment['file_path'] && file_exists('../' . $assessment['file_path'])) {
            unlink('../' . $assessment['file_path']);
        }
        
        // Remove assessment_id from program_materials if exists
        $stmt = $pdo->prepare("UPDATE program_materials SET assessment_id = NULL WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);
        
        // Delete the assessment
        $stmt = $pdo->prepare("DELETE FROM assessments WHERE id = ?");
        $result = $stmt->execute([$assessment_id]);
        
        if (!$result) {
            throw new Exception('Failed to delete assessment');
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Log the deletion
        error_log("Assessment deleted: ID $assessment_id ('{$assessment['title']}') by tutor $tutor_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Assessment deleted successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Delete assessment error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>