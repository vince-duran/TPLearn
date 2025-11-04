<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Check if user is authenticated and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $submission_id = $_POST['submission_id'] ?? null;
    $score = $_POST['score'] ?? null;
    $feedback = $_POST['feedback'] ?? '';
    $tutor_id = $_SESSION['user_id'];

    if (!$submission_id || $score === null) {
        throw new Exception('Submission ID and score are required');
    }

    // Validate score is numeric and within valid range
    if (!is_numeric($score) || $score < 0 || $score > 100) {
        throw new Exception('Score must be a number between 0 and 100');
    }

    // Verify the submission exists and belongs to an assignment in a program owned by this tutor
    $stmt = $conn->prepare("
        SELECT 
            asub.id,
            asub.student_user_id,
            a.id as assignment_id,
            a.title as assignment_title,
            pm.id as material_id,
            p.tutor_id,
            u.username as student_name
        FROM assignment_submissions asub
        INNER JOIN assignments a ON asub.assignment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        INNER JOIN users u ON asub.student_user_id = u.id
        WHERE asub.id = ? AND p.tutor_id = ?
    ");
    
    $stmt->bind_param('ii', $submission_id, $tutor_id);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();

    if (!$submission) {
        throw new Exception('Submission not found or access denied');
    }

    // Update the assignment submission with grade and feedback
    $stmt = $conn->prepare("
        UPDATE assignment_submissions 
        SET score = ?, feedback = ?, graded_at = NOW(), graded_by = ?, status = 'graded'
        WHERE id = ?
    ");
    
    $stmt->bind_param('dsii', $score, $feedback, $tutor_id, $submission_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to update grade');
    }

    // Get grade letter
    $grade_letter = calculateGradeLetter($score);

    // Log the grading activity
    $activity_description = "Graded {$submission['assignment_title']} for {$submission['student_name']} - Score: {$score}%";
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, details, created_at) 
        VALUES (?, 'grading', ?, NOW())
    ");
    $stmt->bind_param('is', $tutor_id, $activity_description);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Grade saved successfully',
        'data' => [
            'submission_id' => $submission_id,
            'score' => $score,
            'grade_letter' => $grade_letter,
            'feedback' => $feedback,
            'graded_at' => date('Y-m-d H:i:s'),
            'student_name' => $submission['student_name'],
            'assignment_title' => $submission['assignment_title']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function calculateGradeLetter($score) {
    if ($score >= 97) return 'A+';
    if ($score >= 93) return 'A';
    if ($score >= 90) return 'A-';
    if ($score >= 87) return 'B+';
    if ($score >= 83) return 'B';
    if ($score >= 80) return 'B-';
    if ($score >= 77) return 'C+';
    if ($score >= 73) return 'C';
    if ($score >= 70) return 'C-';
    if ($score >= 67) return 'D+';
    if ($score >= 63) return 'D';
    if ($score >= 60) return 'D-';
    return 'F';
}
?>