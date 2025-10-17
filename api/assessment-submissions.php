<?php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get assessment ID
$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Assessment ID required']);
    exit;
}

// Database connection
require_once '../includes/db.php';

try {
    // Get assessment details
    $assessment_query = "SELECT title, total_points FROM assessments WHERE assessment_id = ?";
    $stmt = $conn->prepare($assessment_query);
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $assessment_result = $stmt->get_result();
    $assessment = $assessment_result->fetch_assoc();
    
    if (!$assessment) {
        echo json_encode(['success' => false, 'message' => 'Assessment not found']);
        exit;
    }
    
    // Get submissions from assessment_attempts table
    $submissions_query = "SELECT 
        aa.user_id,
        aa.score,
        aa.percentage,
        aa.submitted_at,
        aa.status,
        u.username,
        u.email
    FROM assessment_attempts aa
    JOIN users u ON aa.user_id = u.user_id
    WHERE aa.assessment_id = ?
    ORDER BY aa.submitted_at DESC";
    
    $stmt = $conn->prepare($submissions_query);
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        $submissions[] = [
            'student_name' => $row['username'],
            'email' => $row['email'],
            'score' => $row['score'] ?? 0,
            'percentage' => $row['percentage'] ?? 0,
            'submitted_at' => $row['submitted_at'],
            'status' => $row['status'] ?? 'completed'
        ];
    }
    
    // Calculate statistics
    $total_submissions = count($submissions);
    $avg_score = 0;
    if ($total_submissions > 0) {
        $total_score = array_sum(array_column($submissions, 'percentage'));
        $avg_score = round($total_score / $total_submissions, 2);
    }
    
    echo json_encode([
        'success' => true,
        'assessment' => [
            'title' => $assessment['title'],
            'total_points' => $assessment['total_points']
        ],
        'submissions' => $submissions,
        'stats' => [
            'total_submissions' => $total_submissions,
            'average_score' => $avg_score
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>