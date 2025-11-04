<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

// Check assessment_submissions table
$result = $conn->query("
    SELECT 
        asub.*,
        u.full_name as student_name,
        pm.title as assessment_title
    FROM assessment_submissions asub
    LEFT JOIN users u ON asub.student_id = u.id
    LEFT JOIN program_materials pm ON asub.assessment_id = pm.id
    ORDER BY asub.submitted_at DESC
    LIMIT 20
");

$submissions = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'total_count' => count($submissions),
    'submissions' => $submissions
], JSON_PRETTY_PRINT);
?>
