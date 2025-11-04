<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    // Get material ID from query parameter
    $material_id = $_GET['material_id'] ?? null;
    
    // Validate required fields
    if (!$material_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: material_id'
        ]);
        exit();
    }

    // Get current student ID (from session)
    $student_id = $_SESSION['user_id'] ?? null;

    // Get attached assessments for this material
    $stmt = $conn->prepare("
        SELECT
            a.id,
            a.material_id,
            a.title,
            a.description,
            a.instructions,
            a.total_points as max_score,
            a.due_date,
            a.file_name,
            a.file_path,
            a.created_at,
            asub.id as submission_id,
            asub.status as submission_status,
            asub.grade as submission_grade,
            asub.submitted_at,
            asub.is_late
        FROM assessments a
        LEFT JOIN assessment_submissions asub ON a.id = asub.assessment_id AND asub.student_id = ?
        WHERE a.material_id = ?
        ORDER BY a.created_at DESC
    ");
    
    $stmt->bind_param('ii', $student_id, $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assessments = [];
    while ($row = $result->fetch_assoc()) {
        $assessments[] = [
            'id' => $row['id'],
            'assessment_id' => $row['id'],
            'material_id' => $row['material_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'instructions' => $row['instructions'],
            'due_date' => $row['due_date'],
            'max_score' => $row['max_score'],
            'file_name' => $row['file_name'],
            'file_path' => $row['file_path'],
            'created_at' => $row['created_at'],
            // Submission information
            'submission_id' => $row['submission_id'],
            'submission_status' => $row['submission_status'],
            'submission_grade' => $row['submission_grade'],
            'submitted_at' => $row['submitted_at'],
            'is_late' => $row['is_late'],
            'has_submitted' => !is_null($row['submission_id'])
        ];
    }

    echo json_encode([
        'success' => true,
        'assessments' => $assessments,
        'count' => count($assessments)
    ]);

} catch (Exception $e) {
    error_log("Error in get-attached-assessments.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
