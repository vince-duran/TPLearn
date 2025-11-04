<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is authenticated and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $assessment_id = $input['assessment_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    
    if (!$assessment_id) {
        throw new Exception('Assessment ID is required');
    }
    
    // Get assessment details and verify access
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            pm.title as material_title,
            p.tutor_id
        FROM assessments a
        INNER JOIN program_materials pm ON a.material_id = pm.id
        INNER JOIN programs p ON pm.program_id = p.id
        WHERE a.id = ?
    ");
    
    $stmt->bind_param('i', $assessment_id);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();
    
    if (!$assessment) {
        throw new Exception('Assessment not found');
    }
    
    // Check if student is enrolled in the program
    $enrollment_stmt = $conn->prepare("
        SELECT e.id FROM enrollments e
        INNER JOIN program_materials pm ON pm.program_id = e.program_id
        INNER JOIN assessments a ON a.material_id = pm.id
        WHERE e.student_user_id = ? AND a.id = ? AND e.status = 'active'
    ");
    $enrollment_stmt->bind_param('ii', $user_id, $assessment_id);
    $enrollment_stmt->execute();
    $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
    
    if (!$enrollment) {
        throw new Exception('Access denied - not enrolled in this program');
    }
    
    // Note: We allow late submissions but will tag them appropriately
    // No deadline restriction here - handled during submission
    
    // Check if student has already submitted this assessment (only one submission allowed)
    $previous_submission_stmt = $conn->prepare("
        SELECT id, status, submitted_at
        FROM assessment_attempts
        WHERE assessment_id = ? AND student_user_id = ?
    ");
    $previous_submission_stmt->bind_param('ii', $assessment_id, $user_id);
    $previous_submission_stmt->execute();
    $previous_submission = $previous_submission_stmt->get_result()->fetch_assoc();
    
    // If there's already a submitted attempt, don't allow another one
    if ($previous_submission && $previous_submission['submitted_at']) {
        throw new Exception('Assessment already submitted. Only one submission is allowed per assessment.');
    }
    
    // Check if there's an active (unsubmitted) attempt
    if ($previous_submission && !$previous_submission['submitted_at']) {
        // There's an active attempt - get its details
        $active_attempt_stmt = $conn->prepare("
            SELECT id, started_at, time_limit_end
            FROM assessment_attempts
            WHERE id = ?
        ");
        $active_attempt_stmt->bind_param('i', $previous_submission['id']);
        $active_attempt_stmt->execute();
        $active_attempt = $active_attempt_stmt->get_result()->fetch_assoc();
        
        if ($active_attempt) {
            // Check if the active attempt has expired
            if ($active_attempt['time_limit_end'] && strtotime($active_attempt['time_limit_end']) < time()) {
                // Auto-submit the expired attempt
                $auto_submit_stmt = $conn->prepare("
                    UPDATE assessment_attempts 
                    SET submitted_at = NOW(), status = 'expired'
                    WHERE id = ?
                ");
                $auto_submit_stmt->bind_param('i', $active_attempt['id']);
                $auto_submit_stmt->execute();
                
                throw new Exception('Previous assessment attempt expired. Please contact your instructor.');
            } else {
                // Return the existing active attempt
                echo json_encode([
                    'success' => true,
                    'attempt_id' => $active_attempt['id'],
                    'started_at' => $active_attempt['started_at'],
                    'time_limit_end' => $active_attempt['time_limit_end'],
                    'assessment' => $assessment,
                    'message' => 'Resumed existing attempt'
                ]);
                exit();
            }
        }
    }
    
    // Create new attempt
    $time_limit_end = null;
    if ($assessment['time_limit'] && $assessment['time_limit'] > 0) {
        $time_limit_end = date('Y-m-d H:i:s', time() + ($assessment['time_limit'] * 60));
    }
    
    $insert_stmt = $conn->prepare("
        INSERT INTO assessment_attempts (
            assessment_id, 
            student_user_id, 
            started_at, 
            time_limit_end,
            status
        ) VALUES (?, ?, NOW(), ?, 'in_progress')
    ");
    $insert_stmt->bind_param('iis', $assessment_id, $user_id, $time_limit_end);
    
    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to create assessment attempt');
    }
    
    $attempt_id = $conn->insert_id;
    
    // Log the start of the assessment
    error_log("Assessment attempt started - User: $user_id, Assessment: $assessment_id, Attempt: $attempt_id");
    
    echo json_encode([
        'success' => true,
        'attempt_id' => $attempt_id,
        'started_at' => date('Y-m-d H:i:s'),
        'time_limit_end' => $time_limit_end,
        'assessment' => $assessment,
        'message' => 'Assessment started successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Start assessment error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>