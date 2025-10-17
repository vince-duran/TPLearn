<?php
require_once '../includes/data-helpers.php';

header('Content-Type: application/json');

// Check if user is logged in and is a tutor
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$tutor_user_id = $_SESSION['user_id'];

// Get month and year from request (default to current month)
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n'); // n = 1-12

try {
    // Get sessions for the specified month
    $sql = "
        SELECT 
            s.id,
            s.session_date,
            s.duration,
            s.session_type,
            s.status,
            s.description,
            p.id as program_id,
            p.name as program_name,
            p.category,
            p.difficulty_level,
            p.session_type as program_session_type,
            p.video_call_link,
            COUNT(DISTINCT e.id) as student_count,
            TIME_FORMAT(TIME(s.session_date), '%h:%i %p') as session_time
        FROM sessions s
        INNER JOIN enrollments e ON s.enrollment_id = e.id
        INNER JOIN programs p ON e.program_id = p.id
        INNER JOIN tutor_profiles tp ON p.tutor_id = tp.id
        WHERE tp.user_id = ? 
            AND p.status = 'active'
            AND YEAR(s.session_date) = ?
            AND MONTH(s.session_date) = ?
            AND s.status IN ('scheduled', 'completed', 'in_progress')
        GROUP BY s.id, s.session_date, p.id
        ORDER BY s.session_date ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $tutor_user_id, $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    
    // Color assignment based on program category and name
    function getProgramColor($program_name, $category, $difficulty_level) {
        // Color mapping based on category
        $category_colors = [
            'Language Arts' => 'bg-green-500',
            'Mathematics' => 'bg-blue-500',
            'Science' => 'bg-purple-500',
            'Technology' => 'bg-indigo-500',
            'Arts' => 'bg-pink-500',
            'Social Studies' => 'bg-yellow-500',
            'Physical Education' => 'bg-red-500'
        ];
        
        // Special colors for specific programs based on real database programs
        $program_colors = [
            'English Literature' => 'bg-emerald-500',
            'Advanced Mathematics' => 'bg-blue-600',
            'Basic Science' => 'bg-purple-500',
            'New Prog' => 'bg-indigo-500',
            'Advanced Writing Workshop' => 'bg-green-600',
            'Reading Comprehension Mastery' => 'bg-green-400',
            'Public Speaking & Communication' => 'bg-yellow-500',
            'Filipino' => 'bg-red-400',
            'ARTE' => 'bg-pink-500',
            'Coloring' => 'bg-pink-300',
            'FISHING' => 'bg-blue-400',
            'Ended Science Workshop' => 'bg-gray-500',
            'Ongoing English Class' => 'bg-emerald-400'
        ];
        
        // Check for specific program first
        if (isset($program_colors[$program_name])) {
            return $program_colors[$program_name];
        }
        
        // Then check category
        if (isset($category_colors[$category])) {
            return $category_colors[$category];
        }
        
        // Default based on difficulty
        switch ($difficulty_level) {
            case 'beginner': return 'bg-green-400';
            case 'intermediate': return 'bg-yellow-500';
            case 'advanced': return 'bg-red-500';
            default: return 'bg-gray-500';
        }
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Determine color based on program details
            $color = getProgramColor($row['program_name'], $row['category'], $row['difficulty_level']);
            
            $sessions[] = [
                'id' => $row['id'],
                'date' => $row['session_date'],
                'title' => $row['program_name'],
                'time' => $row['session_time'] ?: date('g:i A', strtotime($row['session_date'])),
                'type' => $row['program_session_type'] ?: $row['session_type'],
                'color' => $color,
                'students' => (int)$row['student_count'],
                'status' => $row['status'],
                'duration' => $row['duration'],
                'description' => $row['description'],
                'program_id' => $row['program_id'],
                'category' => $row['category'],
                'difficulty' => $row['difficulty_level']
            ];
        }
    }
    
    // Also get upcoming sessions for the sidebar (next 7 days)
    $upcoming_sql = "
        SELECT 
            s.id,
            s.session_date,
            s.duration,
            s.session_type,
            s.status,
            s.description,
            p.id as program_id,
            p.name as program_name,
            p.category,
            p.difficulty_level,
            p.session_type as program_session_type,
            COUNT(DISTINCT e.id) as student_count,
            TIME_FORMAT(TIME(s.session_date), '%h:%i %p') as session_time
        FROM sessions s
        INNER JOIN enrollments e ON s.enrollment_id = e.id
        INNER JOIN programs p ON e.program_id = p.id
        INNER JOIN tutor_profiles tp ON p.tutor_id = tp.id
        WHERE tp.user_id = ? 
            AND p.status = 'active'
            AND s.session_date >= NOW()
            AND s.session_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            AND s.status = 'scheduled'
        GROUP BY s.id, s.session_date, p.id
        ORDER BY s.session_date ASC
        LIMIT 5
    ";
    
    $upcoming_stmt = $conn->prepare($upcoming_sql);
    $upcoming_stmt->bind_param('i', $tutor_user_id);
    $upcoming_stmt->execute();
    $upcoming_result = $upcoming_stmt->get_result();
    
    $upcoming_sessions = [];
    if ($upcoming_result) {
        while ($row = $upcoming_result->fetch_assoc()) {
            $color = getProgramColor($row['program_name'], $row['category'], $row['difficulty_level']);
            
            $upcoming_sessions[] = [
                'id' => $row['id'],
                'date' => $row['session_date'],
                'title' => $row['program_name'],
                'topic' => $row['description'] ?: 'Scheduled Session',
                'time' => $row['session_time'] ?: date('g:i A', strtotime($row['session_date'])),
                'type' => $row['program_session_type'] ?: $row['session_type'],
                'color' => $color,
                'students' => (int)$row['student_count'],
                'status' => $row['status'],
                'duration' => $row['duration'],
                'program_id' => $row['program_id'],
                'category' => $row['category'],
                'difficulty' => $row['difficulty_level']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'upcoming_sessions' => $upcoming_sessions,
        'month' => $month,
        'year' => $year
    ]);

} catch (Exception $e) {
    error_log("Error fetching tutor calendar sessions: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch calendar sessions',
        'message' => $e->getMessage()
    ]);
}
?>