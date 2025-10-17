<?php
// API for enrollment management operations
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
    case 'create_enrollment':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $student_user_id = $_POST['student_user_id'] ?? 0;
      $program_id = $_POST['program_id'] ?? 0;
      $tutor_user_id = $_POST['tutor_user_id'] ?? null;

      // If regular student, can only enroll themselves
      if ($_SESSION['role'] === 'student') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $student_user_id = $currentUser['id'];
      }

      if ($student_user_id <= 0 || $program_id <= 0) {
        throw new Exception('Student and program are required');
      }

      // Check if student is already enrolled in this program
      $existing = $db->getRow(
        "SELECT id FROM enrollments WHERE student_user_id = ? AND program_id = ? AND status IN ('pending', 'active')",
        [$student_user_id, $program_id],
        "ii"
      );

      if ($existing) {
        throw new Exception('Student is already enrolled in this program');
      }

      $enrollment_id = $enrollmentManager->createEnrollment($student_user_id, $program_id, $tutor_user_id);

      echo json_encode(['success' => true, 'enrollment_id' => $enrollment_id, 'message' => 'Enrollment created successfully']);
      break;

    case 'get_enrollments':
      if ($_SESSION['role'] === 'admin') {
        // Admin can see all enrollments
        $enrollments = $enrollmentManager->getAllEnrollments();
      } elseif ($_SESSION['role'] === 'student') {
        // Student can only see their own enrollments
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $enrollments = $enrollmentManager->getStudentEnrollments($currentUser['id']);
      } elseif ($_SESSION['role'] === 'tutor') {
        // Tutor can see their assigned enrollments
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $enrollments = $db->getRows(
          "SELECT e.*, p.name as program_name,
                            CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                            u.username as student_id,
                            SUM(pay.amount) as total_paid,
                            (e.total_fee - IFNULL(SUM(pay.amount), 0)) as balance
                     FROM enrollments e
                     JOIN programs p ON e.program_id = p.id
                     JOIN users u ON e.student_user_id = u.id
                     JOIN student_profiles sp ON e.student_user_id = sp.user_id
                     LEFT JOIN payments pay ON e.id = pay.enrollment_id AND pay.status = 'validated'
                     WHERE e.tutor_user_id = ?
                     GROUP BY e.id
                     ORDER BY e.created_at DESC",
          [$currentUser['id']],
          "i"
        );
      } else {
        throw new Exception('Access denied');
      }

      echo json_encode(['success' => true, 'enrollments' => $enrollments]);
      break;

    case 'get_enrollment_details':
      $enrollment_id = $_GET['id'] ?? 0;

      $enrollment = $db->getRow(
        "SELECT e.*, p.name as program_name, p.description as program_description,
                        CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                        CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                        u.username as student_id, u.email as student_email,
                        SUM(pay.amount) as total_paid,
                        (e.total_fee - IFNULL(SUM(pay.amount), 0)) as balance
                 FROM enrollments e
                 JOIN programs p ON e.program_id = p.id
                 JOIN users u ON e.student_user_id = u.id
                 JOIN student_profiles sp ON e.student_user_id = sp.user_id
                 LEFT JOIN tutor_profiles tp ON e.tutor_user_id = tp.user_id
                 LEFT JOIN payments pay ON e.id = pay.enrollment_id AND pay.status = 'validated'
                 WHERE e.id = ?
                 GROUP BY e.id",
        [$enrollment_id],
        "i"
      );

      if (!$enrollment) {
        throw new Exception('Enrollment not found');
      }

      // Check access permissions
      if ($_SESSION['role'] === 'student') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        if ($enrollment['student_user_id'] != $currentUser['id']) {
          throw new Exception('Access denied');
        }
      } elseif ($_SESSION['role'] === 'tutor') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        if ($enrollment['tutor_user_id'] != $currentUser['id']) {
          throw new Exception('Access denied');
        }
      }

      // Get payment history
      $payments = $db->getRows(
        "SELECT * FROM payments WHERE enrollment_id = ? ORDER BY payment_date DESC",
        [$enrollment_id],
        "i"
      );

      echo json_encode(['success' => true, 'enrollment' => $enrollment, 'payments' => $payments]);
      break;

    case 'update_enrollment_status':
      if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'tutor') {
        throw new Exception('Admin or tutor access required');
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $enrollment_id = $_POST['enrollment_id'] ?? 0;
      $status = $_POST['status'] ?? '';
      $notes = $_POST['notes'] ?? '';

      if (!in_array($status, ['pending', 'active', 'completed', 'cancelled'])) {
        throw new Exception('Invalid status');
      }

      // If tutor, check if they're assigned to this enrollment
      if ($_SESSION['role'] === 'tutor') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $enrollment = $db->getRow(
          "SELECT tutor_user_id FROM enrollments WHERE id = ?",
          [$enrollment_id],
          "i"
        );

        if (!$enrollment || $enrollment['tutor_user_id'] != $currentUser['id']) {
          throw new Exception('Access denied');
        }
      }

      $sql = "UPDATE enrollments SET status = ?";
      $params = [$status];
      $types = "s";

      if (!empty($notes)) {
        $sql .= ", notes = ?";
        $params[] = $notes;
        $types .= "s";
      }

      if ($status === 'active' && empty($_POST['skip_dates'])) {
        $sql .= ", start_date = CURDATE()";
      } elseif ($status === 'completed') {
        $sql .= ", end_date = CURDATE()";
      }

      $sql .= " WHERE id = ?";
      $params[] = $enrollment_id;
      $types .= "i";

      $affected = $db->execute($sql, $params, $types);

      if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Enrollment status updated']);
      } else {
        throw new Exception('Failed to update enrollment status');
      }
      break;

    case 'assign_tutor':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $enrollment_id = $_POST['enrollment_id'] ?? 0;
      $tutor_user_id = $_POST['tutor_user_id'] ?? null;

      $affected = $db->execute(
        "UPDATE enrollments SET tutor_user_id = ? WHERE id = ?",
        [$tutor_user_id, $enrollment_id],
        "ii"
      );

      if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Tutor assigned successfully']);
      } else {
        throw new Exception('Failed to assign tutor');
      }
      break;

    case 'get_available_tutors':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $tutors = $db->getRows(
        "SELECT u.id, u.username, CONCAT(tp.first_name, ' ', tp.last_name) as full_name,
                        tp.specializations, tp.hourly_rate,
                        COUNT(e.id) as active_enrollments
                 FROM users u
                 JOIN tutor_profiles tp ON u.id = tp.user_id
                 LEFT JOIN enrollments e ON u.id = e.tutor_user_id AND e.status = 'active'
                 WHERE u.role = 'tutor' AND u.status = 'active'
                 GROUP BY u.id
                 ORDER BY active_enrollments ASC, tp.first_name"
      );

      echo json_encode(['success' => true, 'tutors' => $tutors]);
      break;

    case 'student_stats':
      if ($method !== 'GET') {
        throw new Exception('GET method required');
      }

      $user_id = $_SESSION['user_id'];
      
      // Get student enrollment statistics
      $stmt = $conn->prepare("
        SELECT 
          COUNT(e.id) as total_enrollments,
          SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
          SUM(CASE WHEN p.end_date < CURDATE() THEN 1 ELSE 0 END) as completed_programs,
          COALESCE(AVG(CASE 
            WHEN p.start_date > CURDATE() THEN 0
            WHEN p.end_date < CURDATE() THEN 100
            ELSE GREATEST(0, LEAST(100, ROUND((DATEDIFF(CURDATE(), p.start_date) / NULLIF(DATEDIFF(p.end_date, p.start_date), 0)) * 100)))
          END), 0) as overall_progress
        FROM enrollments e
        JOIN programs p ON e.program_id = p.id
        WHERE e.student_user_id = ?
      ");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $stats = $result->fetch_assoc();

      echo json_encode(['success' => true, 'stats' => $stats]);
      break;

    case 'my_enrollments':
      if ($method !== 'GET') {
        throw new Exception('GET method required');
      }

      $user_id = $_SESSION['user_id'];
      
      // Get student's enrolled programs
      $enrollments = getStudentEnrolledPrograms($user_id);

      echo json_encode(['success' => true, 'enrollments' => $enrollments]);
      break;

    default:
      throw new Exception('Invalid action');
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
