<?php
// API for user management operations
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

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
    case 'get_users':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $role = $_GET['role'] ?? 'all';
      if ($role === 'all') {
        $users = $db->getRows(
          "SELECT u.*, 
                            CASE 
                                WHEN u.role = 'student' THEN CONCAT(sp.first_name, ' ', sp.last_name)
                                WHEN u.role = 'tutor' THEN CONCAT(tp.first_name, ' ', tp.last_name)
                                ELSE u.username
                            END as full_name
                     FROM users u 
                     LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                     LEFT JOIN tutor_profiles tp ON u.id = tp.user_id 
                     ORDER BY u.created_at DESC"
        );
      } else {
        $users = $userManager->getUsersByRole($role);
      }

      echo json_encode(['success' => true, 'users' => $users]);
      break;

    case 'get_user_details':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $user_id = $_GET['id'] ?? 0;
      $user = $userManager->getUserById($user_id);

      if (!$user) {
        throw new Exception('User not found');
      }

      // Get profile details based on role
      $profile = null;
      if ($user['role'] === 'student') {
        $profile = $db->getRow(
          "SELECT sp.*, pp.full_name as parent_name, pp.contact_number as parent_contact 
                     FROM student_profiles sp 
                     LEFT JOIN parent_profiles pp ON sp.user_id = pp.student_user_id 
                     WHERE sp.user_id = ?",
          [$user_id],
          "i"
        );
      } elseif ($user['role'] === 'tutor') {
        $profile = $db->getRow(
          "SELECT * FROM tutor_profiles WHERE user_id = ?",
          [$user_id],
          "i"
        );
      }

      echo json_encode(['success' => true, 'user' => $user, 'profile' => $profile]);
      break;

    case 'update_user_status':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $user_id = $_POST['user_id'] ?? 0;
      $status = $_POST['status'] ?? '';

      if (!in_array($status, ['active', 'inactive', 'pending'])) {
        throw new Exception('Invalid status');
      }

      $affected = $userManager->updateUserStatus($user_id, $status);

      if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'User status updated']);
      } else {
        throw new Exception('Failed to update user status');
      }
      break;

    case 'create_user':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $username = $_POST['username'] ?? '';
      $email = $_POST['email'] ?? '';
      $password = $_POST['password'] ?? '';
      $role = $_POST['role'] ?? 'student';

      if (empty($username) || empty($email) || empty($password)) {
        throw new Exception('Username, email, and password are required');
      }

      if (!in_array($role, ['admin', 'tutor', 'student'])) {
        throw new Exception('Invalid role');
      }

      // Use the new comprehensive duplicate checking system
      $userData = [
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'role' => $role
      ];
      
      $result = createUserWithDuplicateCheck($userData);
      
      if (!$result['success']) {
        throw new Exception($result['message']);
      }

      echo json_encode([
        'success' => true, 
        'user_id' => $result['user_id'],
        'internal_id' => $result['internal_id'],
        'message' => 'User created successfully'
      ]);
      break;

    case 'dashboard_stats':
    case 'get_dashboard_stats':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      // Get various statistics
      $stats = [];

      // Total users by role
      $stats['total_students'] = $db->getRow("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'];
      $stats['total_tutors'] = $db->getRow("SELECT COUNT(*) as count FROM users WHERE role = 'tutor'")['count'];
      $stats['total_admins'] = $db->getRow("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'];

      // Active enrollments
      $stats['active_enrollments'] = $db->getRow("SELECT COUNT(*) as count FROM enrollments WHERE status = 'active'")['count'];

      // Active programs (changed to match the field name being used)
      $stats['active_programs'] = $db->getRow("SELECT COUNT(*) as count FROM programs WHERE status = 'active'")['count'];

      // Pending payments
      $stats['pending_payments'] = $db->getRow("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")['count'];

      // Total revenue (validated payments)
      $revenue = $db->getRow("SELECT SUM(amount) as total FROM payments WHERE status = 'validated'");
      $stats['total_revenue'] = $revenue['total'] ?? 0;

      // Recent enrollments (last 30 days)
      $stats['recent_enrollments'] = $db->getRow(
        "SELECT COUNT(*) as count FROM enrollments WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
      )['count'];

      echo json_encode(['success' => true, 'data' => $stats]);
      break;

    case 'get_tutor_details':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $tutor_id = $_POST['tutor_id'] ?? $_GET['tutor_id'] ?? '';
      if (empty($tutor_id)) {
        throw new Exception('Tutor ID is required');
      }

      // Get tutor details with profile information
      $sql = "SELECT u.id, u.user_id, u.username, u.email, u.status, u.created_at,
                     tp.first_name, tp.last_name, tp.middle_name, tp.bachelor_degree,
                     tp.specializations, tp.bio, tp.contact_number, tp.address,
                     tp.cv_document_path, tp.diploma_document_path, tp.tor_document_path,
                     tp.lpt_csc_document_path, tp.other_documents_paths
              FROM users u
              LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
              WHERE u.id = ? AND u.role = 'tutor'";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param('i', $tutor_id);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows === 0) {
        throw new Exception('Tutor not found');
      }

      $tutor = $result->fetch_assoc();

      echo json_encode(['success' => true, 'tutor' => $tutor]);
      break;

    case 'get_tutor_programs':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $tutor_id = $_POST['tutor_id'] ?? $_GET['tutor_id'] ?? '';
      if (empty($tutor_id)) {
        throw new Exception('Tutor ID is required');
      }

      // Get tutor's assigned programs
      $programs = getTutorAssignedPrograms($tutor_id);

      echo json_encode(['success' => true, 'programs' => $programs]);
      break;

    case 'get_tutor_schedule':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $tutor_id = $_POST['tutor_id'] ?? $_GET['tutor_id'] ?? '';
      if (empty($tutor_id)) {
        throw new Exception('Tutor ID is required');
      }

      // Get tutor's upcoming sessions
      $limit = $_POST['limit'] ?? $_GET['limit'] ?? 10;
      $sessions = getTutorUpcomingSessions($tutor_id, $limit);

      echo json_encode(['success' => true, 'sessions' => $sessions]);
      break;

    case 'update_tutor':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $tutor_id = $_POST['tutor_id'] ?? '';
      if (empty($tutor_id)) {
        throw new Exception('Tutor ID is required');
      }

      // Get the fields to update
      $first_name = $_POST['first_name'] ?? '';
      $middle_name = $_POST['middle_name'] ?? '';
      $last_name = $_POST['last_name'] ?? '';
      $email = $_POST['email'] ?? '';
      $contact_number = $_POST['contact_number'] ?? '';
      $address = $_POST['address'] ?? '';
      $bachelor_degree = $_POST['bachelor_degree'] ?? '';
      $specializations = $_POST['specializations'] ?? '';
      $bio = $_POST['bio'] ?? '';
      $status = $_POST['status'] ?? 'active';

      // Validate required fields
      if (empty($first_name) || empty($last_name) || empty($email)) {
        throw new Exception('First name, last name, and email are required');
      }

      // Validate email format
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
      }

      // Validate status
      if (!in_array($status, ['active', 'inactive', 'on_leave', 'pending'])) {
        throw new Exception('Invalid status');
      }

      // Start transaction
      $conn->begin_transaction();

      try {
        // Update users table
        $sql = "UPDATE users SET email = ?, status = ? WHERE id = ? AND role = 'tutor'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $email, $status, $tutor_id);
        
        if (!$stmt->execute()) {
          throw new Exception('Failed to update user information');
        }

        // Update tutor_profiles table
        $sql = "UPDATE tutor_profiles SET 
                  first_name = ?, 
                  middle_name = ?, 
                  last_name = ?, 
                  contact_number = ?, 
                  address = ?, 
                  bachelor_degree = ?, 
                  specializations = ?, 
                  bio = ?
                WHERE user_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssssssi', 
          $first_name, 
          $middle_name, 
          $last_name, 
          $contact_number, 
          $address, 
          $bachelor_degree, 
          $specializations, 
          $bio, 
          $tutor_id
        );

        if (!$stmt->execute()) {
          throw new Exception('Failed to update tutor profile');
        }

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Tutor updated successfully']);

      } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
      }
      break;

    case 'notification_counts':
      // Get notification and message counts for the current user
      $user_id = $_SESSION['user_id'];
      
      $counts = [];
      
      // Check if notifications table exists and get count
      $tableExists = $db->getRow("SHOW TABLES LIKE 'notifications'");
      if ($tableExists) {
        $counts['notifications'] = $db->getRow(
          "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
          [$user_id],
          "i"
        )['count'] ?? 0;
      } else {
        $counts['notifications'] = 0;
      }
      
      // Check if messages table exists and get count
      $tableExists = $db->getRow("SHOW TABLES LIKE 'messages'");
      if ($tableExists) {
        $counts['messages'] = $db->getRow(
          "SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0",
          [$user_id],
          "i"
        )['count'] ?? 0;
      } else {
        $counts['messages'] = 0;
      }
      
      echo json_encode(['success' => true, 'data' => $counts]);
      break;

    case 'tutor_stats':
      if ($_SESSION['role'] !== 'tutor') {
        throw new Exception('Tutor access required');
      }
      
      $user_id = $_SESSION['user_id'];
      
      // Get tutor statistics
      $stmt = $conn->prepare("
        SELECT 
          COUNT(DISTINCT p.id) as total_programs,
          COUNT(DISTINCT e.student_user_id) as total_students,
          SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) as active_programs,
          AVG(CASE 
            WHEN p.start_date > CURDATE() THEN 0
            WHEN p.end_date < CURDATE() THEN 100
            ELSE GREATEST(0, LEAST(100, ROUND((DATEDIFF(CURDATE(), p.start_date) / NULLIF(DATEDIFF(p.end_date, p.start_date), 0)) * 100)))
          END) as avg_progress
        FROM programs p
        LEFT JOIN enrollments e ON p.id = e.program_id AND e.status = 'active'
        WHERE p.tutor_id = ?
      ");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $stats = $result->fetch_assoc();

      echo json_encode(['success' => true, 'data' => $stats]);
      break;

    default:
      throw new Exception('Invalid action');
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
