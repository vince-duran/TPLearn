<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('tutor');

header('Content-Type: application/json');

try {
    // Get program ID from query parameter
    $program_id = $_GET['program_id'] ?? null;
    $tutor_id = $_SESSION['user_id'] ?? null;
    
    // Validate required fields
    if (!$program_id || !$tutor_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: program_id or tutor not authenticated'
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
            'message' => 'Access denied: You do not have permission to view assessments for this program'
        ]);
        exit();
    }

    // Get all assessments in this program that can be attached
    $stmt = $conn->prepare("
        SELECT id, title, description, due_date, max_score, created_at
        FROM program_materials 
        WHERE program_id = ? 
        AND (material_type = 'assessment' OR material_type = 'assignment')
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assessments = [];
    while ($row = $result->fetch_assoc()) {
        $assessments[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'due_date' => $row['due_date'],
            'max_score' => $row['max_score'],
            'created_at' => $row['created_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'assessments' => $assessments,
        'count' => count($assessments)
    ]);

} catch (Exception $e) {
    error_log("Error in get-program-assessments.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
