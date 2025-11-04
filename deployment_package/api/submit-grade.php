<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get required parameters
    $submission_id = $_POST['submission_id'] ?? null;
    $grade = $_POST['grade'] ?? null;
    $feedback = $_POST['feedback'] ?? '';
    $tutor_id = $_SESSION['user_id'];

    if (!$submission_id || !is_numeric($grade)) {
        throw new Exception('Submission ID and grade are required');
    }

    // Validate grade range
    if ($grade < 0 || $grade > 100) {
        throw new Exception('Grade must be between 0 and 100');
    }

    // Verify the submission exists and belongs to an assignment the tutor owns
    $stmt = $conn->prepare("
        SELECT asub.*, a.material_id, pm.program_id, p.tutor_id, u.username as student_username
        FROM assignment_submissions asub
        JOIN assignments a ON asub.assignment_id = a.id
        JOIN program_materials pm ON a.material_id = pm.id
        JOIN programs p ON pm.program_id = p.id
        JOIN users u ON asub.student_id = u.id
        WHERE asub.id = ? AND p.tutor_id = ?
    ");
    $stmt->bind_param('ii', $submission_id, $tutor_id);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();

    if (!$submission) {
        throw new Exception('Submission not found or you do not have access to grade it');
    }

    // Update the submission with grade and feedback
    $stmt = $conn->prepare("
        UPDATE assignment_submissions 
        SET grade = ?, feedback = ?, status = 'graded', graded_by = ?, graded_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('dsii', $grade, $feedback, $tutor_id, $submission_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save grade: ' . $conn->error);
    }

    // Log the grading action
    error_log("Grade submitted: Submission ID {$submission_id}, Grade: {$grade}%, Student: {$submission['student_username']}, Tutor ID: {$tutor_id}");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Grade submitted successfully',
        'data' => [
            'submission_id' => $submission_id,
            'grade' => (float)$grade,
            'feedback' => $feedback,
            'graded_at' => date('Y-m-d H:i:s'),
            'student_username' => $submission['student_username']
        ]
    ]);

} catch (Exception $e) {
    error_log("Grade submission error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
