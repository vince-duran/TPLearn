<?php
// API for program management operations
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

try {
  switch ($action) {
    case 'get_programs':
      $programs = $programManager->getAllPrograms();
      echo json_encode(['success' => true, 'programs' => $programs]);
      break;

    case 'get_program':
      $program_id = $_GET['id'] ?? 0;
      $program = $programManager->getProgramById($program_id);

      if (!$program) {
        throw new Exception('Program not found');
      }

      echo json_encode(['success' => true, 'program' => $program]);
      break;

    case 'create_program':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $name = $_POST['name'] ?? '';
      $description = $_POST['description'] ?? '';
      $duration_weeks = $_POST['duration_weeks'] ?? 0;
      $fee = $_POST['fee'] ?? 0;

      if (empty($name) || $duration_weeks <= 0 || $fee <= 0) {
        throw new Exception('Name, duration, and fee are required');
      }

      $program_id = $programManager->createProgram($name, $description, $duration_weeks, $fee);

      echo json_encode(['success' => true, 'program_id' => $program_id, 'message' => 'Program created successfully']);
      break;

    case 'update_program':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $program_id = $_POST['program_id'] ?? 0;
      $name = $_POST['name'] ?? '';
      $description = $_POST['description'] ?? '';
      $duration_weeks = $_POST['duration_weeks'] ?? 0;
      $fee = $_POST['fee'] ?? 0;
      $status = $_POST['status'] ?? 'active';

      if (empty($name) || $duration_weeks <= 0 || $fee <= 0) {
        throw new Exception('Name, duration, and fee are required');
      }

      if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Invalid status');
      }

      $affected = $programManager->updateProgram($program_id, $name, $description, $duration_weeks, $fee, $status);

      if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Program updated successfully']);
      } else {
        throw new Exception('Failed to update program');
      }
      break;

    case 'get_available_programs':
      // Get programs available for enrollment
      $programs = $db->getRows(
        "SELECT * FROM programs WHERE status = 'active' ORDER BY name"
      );

      echo json_encode(['success' => true, 'programs' => $programs]);
      break;

    case 'get_program_stats':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $program_id = $_GET['program_id'] ?? 0;

      if ($program_id > 0) {
        // Stats for specific program
        $stats = [];
        $stats['total_enrollments'] = $db->getRow(
          "SELECT COUNT(*) as count FROM enrollments WHERE program_id = ?",
          [$program_id],
          "i"
        )['count'];

        $stats['active_enrollments'] = $db->getRow(
          "SELECT COUNT(*) as count FROM enrollments WHERE program_id = ? AND status = 'active'",
          [$program_id],
          "i"
        )['count'];

        $stats['completed_enrollments'] = $db->getRow(
          "SELECT COUNT(*) as count FROM enrollments WHERE program_id = ? AND status = 'completed'",
          [$program_id],
          "i"
        )['count'];

        $revenue = $db->getRow(
          "SELECT SUM(p.amount) as total 
                     FROM payments p 
                     JOIN enrollments e ON p.enrollment_id = e.id 
                     WHERE e.program_id = ? AND p.status = 'validated'",
          [$program_id],
          "i"
        );
        $stats['total_revenue'] = $revenue['total'] ?? 0;
      } else {
        // Overall program stats
        $stats = [];
        $stats['total_programs'] = $db->getRow("SELECT COUNT(*) as count FROM programs")['count'];
        $stats['active_programs'] = $db->getRow("SELECT COUNT(*) as count FROM programs WHERE status = 'active'")['count'];

        // Most popular program
        $popular = $db->getRow(
          "SELECT p.name, COUNT(e.id) as enrollment_count 
                     FROM programs p 
                     LEFT JOIN enrollments e ON p.id = e.program_id 
                     GROUP BY p.id 
                     ORDER BY enrollment_count DESC 
                     LIMIT 1"
        );
        $stats['most_popular_program'] = $popular['name'] ?? 'N/A';
        $stats['most_popular_enrollments'] = $popular['enrollment_count'] ?? 0;
      }

      echo json_encode(['success' => true, 'stats' => $stats]);
      break;

    case 'my_programs':
      // Get programs for current tutor
      $user_id = $_SESSION['user_id'];
      $role = $_SESSION['role'];
      
      if ($role === 'tutor') {
        $programs = getTutorAssignedPrograms($user_id);
      } elseif ($role === 'student') {
        $programs = getStudentEnrolledPrograms($user_id);
      } else {
        throw new Exception('Access denied');
      }
      
      echo json_encode(['success' => true, 'data' => $programs]);
      break;

    default:
      throw new Exception('Invalid action');
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
