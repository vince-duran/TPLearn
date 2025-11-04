<?php
// Simple fallback API for testing - bypasses authentication
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$student_id = $_GET['student_id'] ?? null;
$program_id = $_GET['program_id'] ?? null;

if (!$student_id || !$program_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID and Program ID are required']);
    exit();
}

// Always return success with mock data for testing
$assessments = [
    [
        'id' => 1,
        'title' => 'Mid-term Assessment',
        'description' => 'Comprehensive assessment covering first half of curriculum',
        'max_points' => 100,
        'due_date' => '2025-10-01',
        'created_at' => '2025-09-15 10:00:00',
        'score' => 87.5,
        'submitted_at' => '2025-09-30 14:30:00',
        'feedback' => 'Good understanding of core concepts. Work on problem-solving strategies.'
    ],
    [
        'id' => 2,
        'title' => 'Quiz - Chapter 3',
        'description' => 'Quick assessment on advanced topics',
        'max_points' => 50,
        'due_date' => '2025-09-20',
        'created_at' => '2025-09-18 09:00:00',
        'score' => 45,
        'submitted_at' => '2025-09-20 11:15:00',
        'feedback' => 'Excellent work! Shows mastery of the material.'
    ],
    [
        'id' => 3,
        'title' => 'Final Assessment',
        'description' => 'Comprehensive final assessment',
        'max_points' => 150,
        'due_date' => '2025-11-15',
        'created_at' => '2025-10-01 10:00:00',
        'score' => null,
        'submitted_at' => null,
        'feedback' => null
    ]
];

$assignments = [
    [
        'id' => 1,
        'title' => 'Research Project',
        'description' => 'In-depth research on chosen topic with presentation',
        'max_points' => 75,
        'due_date' => '2025-10-10',
        'created_at' => '2025-09-20 10:00:00',
        'score' => 68,
        'submitted_at' => '2025-10-09 16:45:00',
        'feedback' => 'Well researched but could use stronger conclusions.'
    ],
    [
        'id' => 2,
        'title' => 'Problem Set 1',
        'description' => 'Practice problems from chapters 1-3',
        'max_points' => 50,
        'due_date' => '2025-09-25',
        'created_at' => '2025-09-18 10:00:00',
        'score' => 48,
        'submitted_at' => '2025-09-24 20:30:00',
        'feedback' => 'Excellent attention to detail and accuracy.'
    ],
    [
        'id' => 3,
        'title' => 'Group Presentation',
        'description' => 'Collaborative presentation on real-world applications',
        'max_points' => 100,
        'due_date' => '2025-11-05',
        'created_at' => '2025-10-15 10:00:00',
        'score' => null,
        'submitted_at' => null,
        'feedback' => null
    ]
];

$progress = [
    [
        'date' => '2025-09-20',
        'average' => 90.0,
        'submissions' => 1
    ],
    [
        'date' => '2025-09-24',
        'average' => 96.0,
        'submissions' => 1
    ],
    [
        'date' => '2025-09-30',
        'average' => 87.5,
        'submissions' => 1
    ],
    [
        'date' => '2025-10-09',
        'average' => 90.7,
        'submissions' => 1
    ]
];

echo json_encode([
    'success' => true,
    'student_id' => $student_id,
    'program_id' => $program_id,
    'assessments' => $assessments,
    'assignments' => $assignments,
    'progress' => $progress
]);
?>