<?php
// API for session management
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

class SessionManager
{
  private $db;

  public function __construct($database)
  {
    $this->db = $database;
  }

  public function createSession($enrollmentId, $sessionDate, $duration, $sessionType, $description = '', $sessionMode = 'virtual')
  {
    // Validate enrollment exists and is active
    $enrollment = $this->db->getRow(
      "SELECT e.*, p.name as program_name 
             FROM enrollments e 
             JOIN programs p ON e.program_id = p.id 
             WHERE e.id = ? AND e.status = 'active'",
      [$enrollmentId],
      "i"
    );

    if (!$enrollment) {
      throw new Exception('Enrollment not found or not active');
    }

    // Check for scheduling conflicts for the tutor
    $conflicts = $this->db->getRow(
      "SELECT COUNT(*) as count FROM sessions 
             WHERE tutor_user_id = ? 
             AND session_date = ? 
             AND status != 'cancelled'
             AND (
                 (session_date <= ? AND DATE_ADD(session_date, INTERVAL duration MINUTE) > ?) OR
                 (? <= session_date AND DATE_ADD(?, INTERVAL ? MINUTE) > session_date)
             )",
      [
        $enrollment['tutor_user_id'],
        $sessionDate,
        $sessionDate,
        $sessionDate,
        $sessionDate,
        $sessionDate,
        $duration
      ],
      "isssssi"
    );

    if ($conflicts['count'] > 0) {
      throw new Exception('Tutor has a scheduling conflict at this time');
    }

    $sessionId = $this->db->insert(
      "INSERT INTO sessions (enrollment_id, tutor_user_id, student_user_id, session_date, duration, session_type, description, session_mode, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())",
      [
        $enrollmentId,
        $enrollment['tutor_user_id'],
        $enrollment['student_user_id'],
        $sessionDate,
        $duration,
        $sessionType,
        $description,
        $sessionMode
      ],
      "iisssisss"
    );

    return $sessionId;
  }

  public function getSessionsByUser($userId, $role, $status = null, $limit = 50)
  {
    $conditions = [];
    $params = [];
    $types = "";

    if ($role === 'tutor') {
      $conditions[] = "s.tutor_user_id = ?";
      $params[] = $userId;
      $types .= "i";
    } elseif ($role === 'student') {
      $conditions[] = "s.student_user_id = ?";
      $params[] = $userId;
      $types .= "i";
    }

    if ($status) {
      $conditions[] = "s.status = ?";
      $params[] = $status;
      $types .= "s";
    }

    $whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

    return $this->db->getRows(
      "SELECT s.*, 
                    p.name as program_name,
                    CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                    CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                    tp.hourly_rate
             FROM sessions s
             JOIN enrollments e ON s.enrollment_id = e.id
             JOIN programs p ON e.program_id = p.id
             JOIN users stu ON s.student_user_id = stu.id
             JOIN student_profiles sp ON stu.id = sp.user_id
             JOIN users tut ON s.tutor_user_id = tut.id
             JOIN tutor_profiles tp ON tut.id = tp.user_id
             $whereClause
             ORDER BY s.session_date DESC
             LIMIT ?",
      [...$params, $limit],
      $types . "i"
    );
  }

  public function updateSessionStatus($sessionId, $status, $notes = '')
  {
    $validStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled', 'rescheduled'];

    if (!in_array($status, $validStatuses)) {
      throw new Exception('Invalid session status');
    }

    $updateFields = ["status = ?"];
    $params = [$status];
    $types = "s";

    if ($notes) {
      $updateFields[] = "notes = ?";
      $params[] = $notes;
      $types .= "s";
    }

    if ($status === 'completed') {
      $updateFields[] = "completed_at = NOW()";
    }

    $params[] = $sessionId;
    $types .= "i";

    return $this->db->query(
      "UPDATE sessions SET " . implode(", ", $updateFields) . ", updated_at = NOW() WHERE id = ?",
      $params,
      $types
    );
  }

  public function rescheduleSession($sessionId, $newDateTime)
  {
    $session = $this->db->getRow(
      "SELECT * FROM sessions WHERE id = ? AND status IN ('scheduled', 'rescheduled')",
      [$sessionId],
      "i"
    );

    if (!$session) {
      throw new Exception('Session not found or cannot be rescheduled');
    }

    // Check for conflicts
    $conflicts = $this->db->getRow(
      "SELECT COUNT(*) as count FROM sessions 
             WHERE tutor_user_id = ? 
             AND session_date = ? 
             AND id != ?
             AND status != 'cancelled'",
      [$session['tutor_user_id'], $newDateTime, $sessionId],
      "isi"
    );

    if ($conflicts['count'] > 0) {
      throw new Exception('Tutor has a scheduling conflict at the new time');
    }

    return $this->db->query(
      "UPDATE sessions SET session_date = ?, status = 'rescheduled', updated_at = NOW() WHERE id = ?",
      [$newDateTime, $sessionId],
      "si"
    );
  }

  public function getSessionStats($userId = null, $role = null)
  {
    $conditions = [];
    $params = [];
    $types = "";

    if ($userId && $role) {
      if ($role === 'tutor') {
        $conditions[] = "s.tutor_user_id = ?";
      } elseif ($role === 'student') {
        $conditions[] = "s.student_user_id = ?";
      }
      $params[] = $userId;
      $types .= "i";
    }

    $whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

    return $this->db->getRow(
      "SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                AVG(duration) as avg_duration
             FROM sessions s
             $whereClause",
      $params,
      $types
    );
  }
}

try {
  $sessionManager = new SessionManager($db);
  $currentUser = $userManager->getUserByLogin($_SESSION['username']);

  switch ($action) {
    case 'create_session':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $enrollmentId = $_POST['enrollment_id'] ?? '';
      $sessionDate = $_POST['session_date'] ?? '';
      $duration = $_POST['duration'] ?? 60;
      $sessionType = $_POST['session_type'] ?? 'regular';
      $description = $_POST['description'] ?? '';
      $sessionMode = $_POST['session_mode'] ?? 'virtual';

      if (!$enrollmentId || !$sessionDate) {
        throw new Exception('Enrollment ID and session date are required');
      }

      // Check if user has permission to create sessions for this enrollment
      if ($_SESSION['role'] === 'tutor') {
        $enrollment = $db->getRow(
          "SELECT * FROM enrollments WHERE id = ? AND tutor_user_id = ?",
          [$enrollmentId, $currentUser['id']],
          "ii"
        );
        if (!$enrollment) {
          throw new Exception('You can only create sessions for your assigned students');
        }
      } elseif ($_SESSION['role'] !== 'admin') {
        throw new Exception('Only tutors and admins can create sessions');
      }

      $sessionId = $sessionManager->createSession(
        $enrollmentId,
        $sessionDate,
        $duration,
        $sessionType,
        $description,
        $sessionMode
      );

      echo json_encode(['success' => true, 'session_id' => $sessionId, 'message' => 'Session created successfully']);
      break;

    case 'get_sessions':
      $status = $_GET['status'] ?? null;
      $limit = $_GET['limit'] ?? 50;

      if ($_SESSION['role'] === 'admin') {
        // Admin can see all sessions
        $sessions = $sessionManager->getSessionsByUser(null, null, $status, $limit);
      } else {
        $sessions = $sessionManager->getSessionsByUser($currentUser['id'], $_SESSION['role'], $status, $limit);
      }

      echo json_encode(['success' => true, 'sessions' => $sessions]);
      break;

    case 'update_session_status':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $sessionId = $_POST['session_id'] ?? '';
      $status = $_POST['status'] ?? '';
      $notes = $_POST['notes'] ?? '';

      if (!$sessionId || !$status) {
        throw new Exception('Session ID and status are required');
      }

      // Check if user has permission to update this session
      if ($_SESSION['role'] !== 'admin') {
        $session = $db->getRow(
          "SELECT * FROM sessions WHERE id = ? AND (tutor_user_id = ? OR student_user_id = ?)",
          [$sessionId, $currentUser['id'], $currentUser['id']],
          "iii"
        );
        if (!$session) {
          throw new Exception('You can only update your own sessions');
        }
      }

      $result = $sessionManager->updateSessionStatus($sessionId, $status, $notes);

      echo json_encode(['success' => true, 'message' => 'Session status updated successfully']);
      break;

    case 'reschedule_session':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $sessionId = $_POST['session_id'] ?? '';
      $newDateTime = $_POST['new_date_time'] ?? '';

      if (!$sessionId || !$newDateTime) {
        throw new Exception('Session ID and new date/time are required');
      }

      // Check if user has permission to reschedule this session
      if ($_SESSION['role'] !== 'admin') {
        $session = $db->getRow(
          "SELECT * FROM sessions WHERE id = ? AND tutor_user_id = ?",
          [$sessionId, $currentUser['id']],
          "ii"
        );
        if (!$session && $_SESSION['role'] !== 'student') {
          throw new Exception('Only the assigned tutor or admin can reschedule sessions');
        }
      }

      $result = $sessionManager->rescheduleSession($sessionId, $newDateTime);

      echo json_encode(['success' => true, 'message' => 'Session rescheduled successfully']);
      break;

    case 'get_session_stats':
      if ($_SESSION['role'] === 'admin') {
        $stats = $sessionManager->getSessionStats();
      } else {
        $stats = $sessionManager->getSessionStats($currentUser['id'], $_SESSION['role']);
      }

      echo json_encode(['success' => true, 'stats' => $stats]);
      break;

    case 'get_upcoming_sessions':
      $limit = $_GET['limit'] ?? 10;

      $conditions = ["s.session_date >= NOW()", "s.status IN ('scheduled', 'rescheduled')"];
      $params = [];
      $types = "";

      if ($_SESSION['role'] === 'tutor') {
        $conditions[] = "s.tutor_user_id = ?";
        $params[] = $currentUser['id'];
        $types .= "i";
      } elseif ($_SESSION['role'] === 'student') {
        $conditions[] = "s.student_user_id = ?";
        $params[] = $currentUser['id'];
        $types .= "i";
      }

      $params[] = $limit;
      $types .= "i";

      $upcoming = $db->getRows(
        "SELECT s.*, 
                        p.name as program_name,
                        CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                        CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name
                 FROM sessions s
                 JOIN enrollments e ON s.enrollment_id = e.id
                 JOIN programs p ON e.program_id = p.id
                 JOIN users stu ON s.student_user_id = stu.id
                 JOIN student_profiles sp ON stu.id = sp.user_id
                 JOIN users tut ON s.tutor_user_id = tut.id
                 JOIN tutor_profiles tp ON tut.id = tp.user_id
                 WHERE " . implode(" AND ", $conditions) . "
                 ORDER BY s.session_date ASC
                 LIMIT ?",
        $params,
        $types
      );

      echo json_encode(['success' => true, 'upcoming_sessions' => $upcoming]);
      break;

    case 'get_session_calendar':
      $month = $_GET['month'] ?? date('Y-m');

      $conditions = ["DATE_FORMAT(s.session_date, '%Y-%m') = ?"];
      $params = [$month];
      $types = "s";

      if ($_SESSION['role'] === 'tutor') {
        $conditions[] = "s.tutor_user_id = ?";
        $params[] = $currentUser['id'];
        $types .= "i";
      } elseif ($_SESSION['role'] === 'student') {
        $conditions[] = "s.student_user_id = ?";
        $params[] = $currentUser['id'];
        $types .= "i";
      }

      $calendar = $db->getRows(
        "SELECT s.*, 
                        p.name as program_name,
                        CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                        CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name
                 FROM sessions s
                 JOIN enrollments e ON s.enrollment_id = e.id
                 JOIN programs p ON e.program_id = p.id
                 JOIN users stu ON s.student_user_id = stu.id
                 JOIN student_profiles sp ON stu.id = sp.user_id
                 JOIN users tut ON s.tutor_user_id = tut.id
                 JOIN tutor_profiles tp ON tut.id = tp.user_id
                 WHERE " . implode(" AND ", $conditions) . "
                 ORDER BY s.session_date ASC",
        $params,
        $types
      );

      echo json_encode(['success' => true, 'calendar' => $calendar, 'month' => $month]);
      break;

    default:
      throw new Exception('Invalid action');
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
