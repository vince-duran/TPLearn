<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in and is a tutor
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'error' => 'Not logged in. Please log in first.'
        ]);
        exit();
    }
    
    if ($_SESSION['role'] !== 'tutor') {
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized. Only tutors can access this endpoint.'
        ]);
        exit();
    }

    // Get material ID from query parameter
    $material_id = $_GET['material_id'] ?? null;
    
    // Validate required fields
    if (!$material_id) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing required parameter: material_id'
        ]);
        exit();
    }

    // Get attached assessments for this material with submission statistics
    $stmt = $conn->prepare("
        SELECT
            a.id as assessment_id,
            a.material_id,
            a.title,
            a.description,
            a.instructions,
            a.total_points as max_score,
            a.due_date,
            a.file_name,
            a.file_path,
            a.created_at,
            pm.program_id,
            COUNT(DISTINCT aa.id) as total_submissions,
            SUM(CASE WHEN aa.status = 'graded' THEN 1 ELSE 0 END) as graded_count,
            SUM(CASE WHEN aa.status IN ('submitted', 'late_submission') THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN aa.status = 'late_submission' THEN 1 ELSE 0 END) as late_count,
            AVG(CASE WHEN aa.status = 'graded' AND aa.score IS NOT NULL THEN aa.score END) as average_grade
        FROM assessments a
        INNER JOIN program_materials pm ON a.material_id = pm.id
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.submitted_at IS NOT NULL
        WHERE a.material_id = ?
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ");
    
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assessments = [];
    while ($row = $result->fetch_assoc()) {
        // Format due date
        $due_date_formatted = null;
        if ($row['due_date'] && $row['due_date'] !== '0000-00-00 00:00:00') {
            try {
                $due_date_obj = new DateTime($row['due_date']);
                $due_date_formatted = $due_date_obj->format('M j, Y g:i A');
            } catch (Exception $e) {
                $due_date_formatted = 'Invalid date';
            }
        }
        
        // Get total enrolled students for submission rate
        $stmt2 = $conn->prepare("
            SELECT COUNT(*) as enrolled_count
            FROM enrollments
            WHERE program_id = ? AND status = 'active'
        ");
        $stmt2->bind_param('i', $row['program_id']);
        $stmt2->execute();
        $enrollment_result = $stmt2->get_result();
        $enrollment_data = $enrollment_result->fetch_assoc();
        $enrolled_count = $enrollment_data['enrolled_count'] ?? 0;
        
        $submission_rate = $enrolled_count > 0 ? round(($row['total_submissions'] / $enrolled_count) * 100, 1) : 0;
        
        $assessments[] = [
            'id' => $row['assessment_id'],
            'assessment_id' => $row['assessment_id'],
            'material_id' => $row['material_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'instructions' => $row['instructions'],
            'due_date' => $row['due_date'],
            'due_date_formatted' => $due_date_formatted,
            'max_score' => $row['max_score'],
            'file_name' => $row['file_name'],
            'file_path' => $row['file_path'],
            'program_id' => $row['program_id'],
            'created_at' => $row['created_at'],
            // Submission statistics for tutors
            'statistics' => [
                'total_submissions' => intval($row['total_submissions']),
                'graded_count' => intval($row['graded_count']),
                'pending_count' => intval($row['pending_count']),
                'late_count' => intval($row['late_count']),
                'average_grade' => $row['average_grade'] ? round(floatval($row['average_grade']), 2) : null,
                'enrolled_students' => $enrolled_count,
                'submission_rate' => $submission_rate
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'assessments' => $assessments,
        'count' => count($assessments),
        'material_id' => $material_id
    ]);

} catch (Exception $e) {
    error_log("Error in get-attached-assessments-tutor.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
}
?>
