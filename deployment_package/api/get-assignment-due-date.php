<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('tutor');

header('Content-Type: application/json');

try {
    // Get material ID from query parameter
    $material_id = $_GET['material_id'] ?? null;
    $tutor_id = $_SESSION['user_id'] ?? null;

    // Validate required fields
    if (!$material_id || !$tutor_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: material_id or tutor not authenticated'
        ]);
        exit();
    }

    // Verify tutor owns this material through the program
    $stmt = $conn->prepare("
        SELECT a.due_date 
        FROM assignments a
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        WHERE pm.id = ? AND p.tutor_id = ?
    ");
    $stmt->bind_param('ii', $material_id, $tutor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Assignment not found or access denied'
        ]);
        exit();
    }

    $assignment = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'due_date' => $assignment['due_date']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>