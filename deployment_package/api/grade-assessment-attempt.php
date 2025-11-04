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
    $attempt_id = $_POST['attempt_id'] ?? null;
    $score = $_POST['score'] ?? null;
    $comments = $_POST['comments'] ?? '';
    $tutor_id = $_SESSION['user_id'];

    if (!$attempt_id || $score === null) {
        throw new Exception('Assessment attempt ID and score are required');
    }

    // Validate score is numeric and within valid range
    if (!is_numeric($score) || $score < 0 || $score > 100) {
        throw new Exception('Score must be a number between 0 and 100');
    }

    // Verify the assessment attempt exists and belongs to an assessment in a program owned by this tutor
    $stmt = $conn->prepare("
        SELECT 
            aa.id,
            aa.student_user_id,
            a.id as assessment_id,
            a.title as assessment_title,
            a.total_points,
            pm.id as material_id,
            p.tutor_id,
            u.username as student_name
        FROM assessment_attempts aa
        INNER JOIN assessments a ON aa.assessment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        INNER JOIN users u ON aa.student_user_id = u.id
        WHERE aa.id = ? AND p.tutor_id = ?
    ");
    
    $stmt->bind_param('ii', $attempt_id, $tutor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempt = $result->fetch_assoc();

    if (!$attempt) {
        throw new Exception('Assessment attempt not found or access denied');
    }

    // Calculate percentage based on total points
    $max_score = $attempt['total_points'] ?: 100;
    $percentage = ($score / $max_score) * 100;

    // Update the assessment attempt with the grade
    $stmt = $conn->prepare("
        UPDATE assessment_attempts 
        SET score = ?, 
            comments = ?, 
            status = 'graded',
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param('dsi', $score, $comments, $attempt_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to update assessment grade');
    }

    // Get grade letter
    $grade_letter = calculateGradeLetter($percentage);

    // Log the grading activity
    $activity_description = "Graded {$attempt['assessment_title']} for {$attempt['student_name']} - Score: {$score}/{$max_score} ({$percentage}%)";
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, details, created_at) 
        VALUES (?, 'assessment_grading', ?, NOW())
    ");
    $stmt->bind_param('is', $tutor_id, $activity_description);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Assessment grade saved successfully',
        'data' => [
            'attempt_id' => $attempt_id,
            'score' => $score,
            'percentage' => round($percentage, 2),
            'grade_letter' => $grade_letter,
            'comments' => $comments,
            'graded_at' => date('Y-m-d H:i:s'),
            'student_name' => $attempt['student_name'],
            'assessment_title' => $attempt['assessment_title'],
            'max_score' => $max_score
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function calculateGradeLetter($percentage) {
    if ($percentage >= 97) return 'A+';
    if ($percentage >= 93) return 'A';
    if ($percentage >= 90) return 'A-';
    if ($percentage >= 87) return 'B+';
    if ($percentage >= 83) return 'B';
    if ($percentage >= 80) return 'B-';
    if ($percentage >= 77) return 'C+';
    if ($percentage >= 73) return 'C';
    if ($percentage >= 70) return 'C-';
    if ($percentage >= 67) return 'D+';
    if ($percentage >= 63) return 'D';
    if ($percentage >= 60) return 'D-';
    return 'F';
}
?>