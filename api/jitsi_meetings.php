<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/data-helpers.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    switch ($action) {
        case 'create':
            if ($user_role !== 'tutor') {
                throw new Exception('Only tutors can create meetings');
            }
            createJitsiMeeting();
            break;
            
        case 'get_meetings':
            getMeetings();
            break;
            
        case 'get_meeting':
            getMeeting();
            break;
            
        case 'update_status':
            updateMeetingStatus();
            break;
            
        case 'join_meeting':
            joinMeeting();
            break;
            
        case 'leave_meeting':
            leaveMeeting();
            break;
            
        case 'delete':
            if ($user_role !== 'tutor') {
                throw new Exception('Only tutors can delete meetings');
            }
            deleteMeeting();
            break;
            
        case 'get_meeting_details':
            getMeetingDetails();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createJitsiMeeting() {
    global $conn, $user_id;
    
    $program_id = $_POST['program_id'] ?? null;
    $title = $_POST['title'] ?? null;
    $description = $_POST['description'] ?? '';
    $scheduled_date = $_POST['scheduled_date'] ?? null;
    $scheduled_time = $_POST['scheduled_time'] ?? null;
    $duration_minutes = $_POST['duration_minutes'] ?? 60;
    $max_participants = $_POST['max_participants'] ?? 50;
    $is_recorded = isset($_POST['is_recorded']) ? 1 : 0;
    
    if (!$program_id || !$title || !$scheduled_date || !$scheduled_time) {
        throw new Exception('Missing required fields');
    }
    
    // Verify tutor has access to this program
    $stmt = $conn->prepare("SELECT id, name FROM programs WHERE id = ? AND tutor_id = ?");
    $stmt->bind_param('ii', $program_id, $user_id);
    $stmt->execute();
    $program = $stmt->get_result()->fetch_assoc();
    
    if (!$program) {
        throw new Exception('Program not found or access denied');
    }
    
    // Generate unique meeting ID - more readable format
    $meeting_id = 'TPLearn-' . $program_id . '-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    
    // Create Jitsi meeting URL (using meet.jit.si as default)
    $jitsi_domain = 'meet.jit.si';
    $meeting_url = "https://$jitsi_domain/$meeting_id";
    
    // Insert meeting into database
    $stmt = $conn->prepare("
        INSERT INTO jitsi_meetings 
        (program_id, tutor_id, meeting_id, meeting_url, title, description, 
         scheduled_date, scheduled_time, duration_minutes, max_participants, is_recorded) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('iissssssiii', 
        $program_id, $user_id, $meeting_id, $meeting_url, $title, $description,
        $scheduled_date, $scheduled_time, $duration_minutes, $max_participants, $is_recorded
    );
    
    if ($stmt->execute()) {
        $meeting_db_id = $conn->insert_id;
        
        // Get the created meeting data
        $stmt = $conn->prepare("
            SELECT m.*, p.name as program_name 
            FROM jitsi_meetings m 
            JOIN programs p ON m.program_id = p.id 
            WHERE m.id = ?
        ");
        $stmt->bind_param('i', $meeting_db_id);
        $stmt->execute();
        $meeting_data = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Meeting created successfully',
            'meeting' => $meeting_data
        ]);
    } else {
        throw new Exception('Failed to create meeting');
    }
}

function getMeetings() {
    global $conn, $user_id, $user_role;
    
    $program_id = $_GET['program_id'] ?? null;
    
    if ($user_role === 'tutor') {
        // Tutors can see meetings for their programs
        $sql = "
            SELECT m.*, p.name as program_name,
                   COUNT(jp.id) as participant_count
            FROM jitsi_meetings m 
            JOIN programs p ON m.program_id = p.id 
            LEFT JOIN jitsi_participants jp ON m.id = jp.meeting_id AND jp.status = 'joined'
            WHERE m.tutor_id = ?
        ";
        $params = [$user_id];
        $types = 'i';
        
        if ($program_id) {
            $sql .= " AND m.program_id = ?";
            $params[] = $program_id;
            $types .= 'i';
        }
        
    } else {
        // Students can see meetings for programs they're enrolled in
        $sql = "
            SELECT m.*, p.name as program_name,
                   COUNT(jp.id) as participant_count
            FROM jitsi_meetings m 
            JOIN programs p ON m.program_id = p.id 
            JOIN enrollments e ON p.id = e.program_id
            LEFT JOIN jitsi_participants jp ON m.id = jp.meeting_id AND jp.status = 'joined'
            WHERE e.student_user_id = ? AND e.status = 'active'
        ";
        $params = [$user_id];
        $types = 'i';
        
        if ($program_id) {
            $sql .= " AND m.program_id = ?";
            $params[] = $program_id;
            $types .= 'i';
        }
    }
    
    $sql .= " GROUP BY m.id ORDER BY m.scheduled_date DESC, m.scheduled_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $meetings = [];
    while ($row = $result->fetch_assoc()) {
        // Use PST timezone functions for consistent handling
        $meetingStatus = getMeetingStatus($row['scheduled_date'], $row['scheduled_time'], $row['duration_minutes']);
        
        // Format datetime for display in PST
        $datetime = createPSTDateTimeFromDB($row['scheduled_date'], $row['scheduled_time']);
        $row['scheduled_datetime'] = $datetime->format('Y-m-d H:i:s');
        $row['formatted_date'] = $datetime->format('M j, Y');
        $row['formatted_time'] = $datetime->format('g:i A');
        
        // Set status based on PST calculations
        $row['is_live'] = $meetingStatus['is_live'];
        $row['is_upcoming'] = $meetingStatus['is_upcoming'];
        $row['is_past'] = $meetingStatus['is_past'];
        
        // Add debug info for timezone
        $row['timezone'] = 'PST (Asia/Manila)';
        $row['current_time_pst'] = $meetingStatus['current_time']->format('Y-m-d H:i:s T');
        
        // Add priority for sorting (lower number = higher priority)
        if ($row['is_live']) {
            $row['sort_priority'] = 0; // Live sessions first
        } elseif ($row['is_upcoming']) {
            $row['sort_priority'] = 1; // Upcoming sessions second  
        } else {
            $row['sort_priority'] = 2; // Past sessions last
        }
        
        $meetings[] = $row;
    }
    
    // For students, sort by priority (live first), then by date
    if ($user_role === 'student') {
        usort($meetings, function($a, $b) {
            // First sort by priority (live vs upcoming vs past)
            if ($a['sort_priority'] !== $b['sort_priority']) {
                return $a['sort_priority'] - $b['sort_priority'];
            }
            // Then sort by scheduled date/time (newest first)
            return strtotime($b['scheduled_datetime']) - strtotime($a['scheduled_datetime']);
        });
    }
    
    echo json_encode(['success' => true, 'meetings' => $meetings]);
}

function getMeeting() {
    global $conn, $user_id, $user_role;
    
    $meeting_id = $_GET['meeting_id'] ?? null;
    
    if (!$meeting_id) {
        throw new Exception('Meeting ID required');
    }
    
    if ($user_role === 'tutor') {
        $sql = "
            SELECT m.*, p.name as program_name 
            FROM jitsi_meetings m 
            JOIN programs p ON m.program_id = p.id 
            WHERE m.id = ? AND m.tutor_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $meeting_id, $user_id);
    } else {
        $sql = "
            SELECT m.*, p.name as program_name 
            FROM jitsi_meetings m 
            JOIN programs p ON m.program_id = p.id 
            JOIN enrollments e ON p.id = e.program_id 
            WHERE m.id = ? AND e.student_user_id = ? AND e.status = 'active'
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $meeting_id, $user_id);
    }
    
    $stmt->execute();
    $meeting = $stmt->get_result()->fetch_assoc();
    
    if (!$meeting) {
        throw new Exception('Meeting not found or access denied');
    }
    
    echo json_encode(['success' => true, 'meeting' => $meeting]);
}

function updateMeetingStatus() {
    global $conn, $user_id, $user_role;
    
    if ($user_role !== 'tutor') {
        throw new Exception('Only tutors can update meeting status');
    }
    
    $meeting_id = $_POST['meeting_id'] ?? null;
    $status = $_POST['status'] ?? null;
    
    if (!$meeting_id || !$status) {
        throw new Exception('Missing required fields');
    }
    
    // Verify tutor owns this meeting
    $stmt = $conn->prepare("SELECT id FROM jitsi_meetings WHERE id = ? AND tutor_id = ?");
    $stmt->bind_param('ii', $meeting_id, $user_id);
    $stmt->execute();
    
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception('Meeting not found or access denied');
    }
    
    // Update status
    $stmt = $conn->prepare("UPDATE jitsi_meetings SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $status, $meeting_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Meeting status updated']);
    } else {
        throw new Exception('Failed to update meeting status');
    }
}

function joinMeeting() {
    global $conn, $user_id;
    
    $meeting_id = $_POST['meeting_id'] ?? null;
    
    if (!$meeting_id) {
        throw new Exception('Meeting ID required');
    }
    
    // Record participation
    $stmt = $conn->prepare("
        INSERT INTO jitsi_participants (meeting_id, user_id, status, joined_at) 
        VALUES (?, ?, 'joined', NOW())
        ON DUPLICATE KEY UPDATE 
        status = 'joined', joined_at = NOW()
    ");
    
    $stmt->bind_param('ii', $meeting_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Joined meeting']);
    } else {
        throw new Exception('Failed to record meeting participation');
    }
}

function leaveMeeting() {
    global $conn, $user_id;
    
    $meeting_id = $_POST['meeting_id'] ?? null;
    
    if (!$meeting_id) {
        throw new Exception('Meeting ID required');
    }
    
    // Update participation record
    $stmt = $conn->prepare("
        UPDATE jitsi_participants 
        SET status = 'left', left_at = NOW(),
            duration_minutes = TIMESTAMPDIFF(MINUTE, joined_at, NOW())
        WHERE meeting_id = ? AND user_id = ?
    ");
    
    $stmt->bind_param('ii', $meeting_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Left meeting']);
    } else {
        throw new Exception('Failed to update meeting participation');
    }
}

function deleteMeeting() {
    global $conn, $user_id;
    
    $meeting_id = $_POST['meeting_id'] ?? null;
    
    if (!$meeting_id) {
        throw new Exception('Meeting ID required');
    }
    
    // Verify tutor owns this meeting
    $stmt = $conn->prepare("SELECT id FROM jitsi_meetings WHERE id = ? AND tutor_id = ?");
    $stmt->bind_param('ii', $meeting_id, $user_id);
    $stmt->execute();
    
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception('Meeting not found or access denied');
    }
    
    // Delete meeting (participants will be deleted due to cascade)
    $stmt = $conn->prepare("DELETE FROM jitsi_meetings WHERE id = ?");
    $stmt->bind_param('i', $meeting_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Meeting deleted']);
    } else {
        throw new Exception('Failed to delete meeting');
    }
}

function getMeetingDetails() {
    global $conn, $user_id, $user_role;
    
    $meeting_id = $_GET['meeting_id'] ?? null;
    
    if (!$meeting_id) {
        throw new Exception('Meeting ID required');
    }
    
    // Base query to get meeting details
    $query = "
        SELECT 
            m.*,
            p.name as program_name,
            u.username as tutor_name,
            COALESCE(participant_count.count, 0) as participant_count,
            DATE_FORMAT(m.scheduled_date, '%Y-%m-%d') as formatted_date,
            TIME_FORMAT(m.scheduled_time, '%h:%i %p') as formatted_time,
            TIMESTAMP(m.scheduled_date, m.scheduled_time) as scheduled_start,
            TIMESTAMPADD(MINUTE, m.duration_minutes, TIMESTAMP(m.scheduled_date, m.scheduled_time)) as scheduled_end
        FROM jitsi_meetings m
        LEFT JOIN programs p ON m.program_id = p.id
        LEFT JOIN users u ON m.tutor_id = u.user_id
        LEFT JOIN (
            SELECT meeting_id, COUNT(*) as count 
            FROM jitsi_participants 
            WHERE status = 'active' 
            GROUP BY meeting_id
        ) participant_count ON m.id = participant_count.meeting_id
        WHERE m.id = ?
    ";
    
    // Add authorization based on user role
    if ($user_role === 'tutor') {
        $query .= " AND m.tutor_id = ?";
    } else if ($user_role === 'student') {
        // Students can only view meetings for programs they're enrolled in
        $query .= " AND m.program_id IN (
            SELECT program_id 
            FROM enrollments 
            WHERE student_user_id = ? AND status = 'active'
        )";
    } else {
        throw new Exception('Access denied');
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $meeting_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $meeting = $result->fetch_assoc();
    
    if (!$meeting) {
        throw new Exception('Meeting not found or access denied');
    }
    
    // Use PST timezone for status calculation
    $meetingStatus = getMeetingStatus($meeting['scheduled_date'], $meeting['scheduled_time'], $meeting['duration_minutes']);
    
    if ($meetingStatus['is_upcoming']) {
        $meeting['status'] = 'scheduled';
        $meeting['is_live'] = false;
        $meeting['is_upcoming'] = true;
    } elseif ($meetingStatus['is_live']) {
        $meeting['status'] = 'active';
        $meeting['is_live'] = true;
        $meeting['is_upcoming'] = false;
    } else {
        $meeting['status'] = 'ended';
        $meeting['is_live'] = false;
        $meeting['is_upcoming'] = false;
    }
    
    echo json_encode([
        'success' => true,
        'meeting' => $meeting
    ]);
}
?>