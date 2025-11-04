<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole('tutor');

header('Content-Type: application/json');

try {
    // Get form data
    $material_id = $_POST['material_id'] ?? null;
    $program_id = $_POST['program_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
    $total_points = (float)($_POST['total_points'] ?? 100);
    $max_attempts = (int)($_POST['max_attempts'] ?? 1);
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $questions = json_decode($_POST['questions'] ?? '[]', true);

    // Validate required fields
    if (!$material_id || !$program_id || !$title || empty($questions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: material_id, program_id, title, or questions'
        ]);
        exit();
    }

    $tutor_id = $_SESSION['user_id'];

    // Verify tutor owns this program and material
    $stmt = $conn->prepare("
        SELECT pm.id 
        FROM program_materials pm 
        INNER JOIN programs p ON pm.program_id = p.id 
        WHERE pm.id = ? AND p.id = ? AND p.tutor_id = ?
    ");
    $stmt->bind_param('iii', $material_id, $program_id, $tutor_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Access denied: You do not have permission to create assessments for this material'
        ]);
        exit();
    }

    // Check if assessment already exists for this material
    $stmt = $conn->prepare("SELECT id FROM assessments WHERE material_id = ?");
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'An assessment already exists for this material. Please edit the existing assessment instead.'
        ]);
        exit();
    }

    // Calculate total points from questions
    $calculated_total_points = 0;
    foreach ($questions as $question) {
        $calculated_total_points += (float)($question['points'] ?? 10);
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Create assessment
        $stmt = $conn->prepare("
            INSERT INTO assessments (material_id, title, description, time_limit, total_points, max_attempts, shuffle_questions, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issiidii', $material_id, $title, $description, $time_limit, $calculated_total_points, $max_attempts, $shuffle_questions, $tutor_id);
        $stmt->execute();

        $assessment_id = $conn->insert_id;

        // Create questions
        $stmt = $conn->prepare("
            INSERT INTO assessment_questions (assessment_id, question_text, question_type, options, correct_answer, model_answer, points, order_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($questions as $index => $question) {
            $question_text = $question['question_text'] ?? '';
            $question_type = $question['question_type'] ?? 'multiple_choice';
            $points = (float)($question['points'] ?? 10);
            $order_number = $index + 1;

            $options = null;
            $correct_answer = null;
            $model_answer = null;

            if ($question_type === 'multiple_choice') {
                $options = json_encode($question['options'] ?? []);
                $correct_answer = $question['correct_answer'] ?? '';
            } elseif ($question_type === 'true_false') {
                $correct_answer = $question['correct_answer'] ?? '';
            } elseif ($question_type === 'short_answer') {
                $model_answer = $question['model_answer'] ?? '';
            }

            $stmt->bind_param('isssssdi', $assessment_id, $question_text, $question_type, $options, $correct_answer, $model_answer, $points, $order_number);
            $stmt->execute();
        }

        // Commit transaction
        $conn->commit();

        // Log successful creation
        error_log("Assessment created successfully - ID: {$assessment_id}, Material ID: {$material_id}, Title: {$title}");

        echo json_encode([
            'success' => true,
            'message' => 'Assessment created successfully',
            'data' => [
                'assessment_id' => $assessment_id,
                'material_id' => $material_id,
                'title' => $title,
                'total_questions' => count($questions),
                'total_points' => $calculated_total_points
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Create assessment error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create assessment: ' . $e->getMessage()
    ]);
}
?>