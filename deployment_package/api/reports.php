<?php
// API for reporting and analytics
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
    case 'dashboard_overview':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $overview = [];

      // User statistics
      $overview['users'] = [
        'total_students' => $db->getRow("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'],
        'total_tutors' => $db->getRow("SELECT COUNT(*) as count FROM users WHERE role = 'tutor'")['count'],
        'active_users' => $db->getRow("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
        'new_this_month' => $db->getRow(
          "SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"
        )['count']
      ];

      // Program statistics
      $overview['programs'] = [
        'total_programs' => $db->getRow("SELECT COUNT(*) as count FROM programs")['count'],
        'active_programs' => $db->getRow("SELECT COUNT(*) as count FROM programs WHERE status = 'active'")['count'],
        'total_enrollments' => $db->getRow("SELECT COUNT(*) as count FROM enrollments")['count'],
        'active_enrollments' => $db->getRow("SELECT COUNT(*) as count FROM enrollments WHERE status = 'active'")['count']
      ];

      // Financial statistics
      $totalRevenue = $db->getRow("SELECT SUM(amount) as total FROM payments WHERE status = 'validated'");
      $monthlyRevenue = $db->getRow(
        "SELECT SUM(amount) as total FROM payments 
                 WHERE status = 'validated' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())"
      );
      $pendingPayments = $db->getRow("SELECT COUNT(*) as count, SUM(amount) as total FROM payments WHERE status = 'pending'");

      $overview['finance'] = [
        'total_revenue' => $totalRevenue['total'] ?? 0,
        'monthly_revenue' => $monthlyRevenue['total'] ?? 0,
        'pending_payments_count' => $pendingPayments['count'],
        'pending_payments_amount' => $pendingPayments['total'] ?? 0
      ];

      // Recent activity (last 7 days)
      $overview['recent_activity'] = [
        'new_enrollments' => $db->getRow(
          "SELECT COUNT(*) as count FROM enrollments WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        )['count'],
        'new_payments' => $db->getRow(
          "SELECT COUNT(*) as count FROM payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        )['count'],
        'new_users' => $db->getRow(
          "SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )['count']
      ];

      echo json_encode(['success' => true, 'overview' => $overview]);
      break;

    case 'enrollment_trends':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $period = $_GET['period'] ?? 'monthly'; // daily, weekly, monthly, yearly

      switch ($period) {
        case 'daily':
          $sql = "SELECT DATE(enrollment_date) as period, COUNT(*) as count 
                            FROM enrollments 
                            WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                            GROUP BY DATE(enrollment_date) 
                            ORDER BY period";
          break;

        case 'weekly':
          $sql = "SELECT YEARWEEK(enrollment_date) as period, COUNT(*) as count 
                            FROM enrollments 
                            WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                            GROUP BY YEARWEEK(enrollment_date) 
                            ORDER BY period";
          break;

        case 'yearly':
          $sql = "SELECT YEAR(enrollment_date) as period, COUNT(*) as count 
                            FROM enrollments 
                            GROUP BY YEAR(enrollment_date) 
                            ORDER BY period";
          break;

        default: // monthly
          $sql = "SELECT DATE_FORMAT(enrollment_date, '%Y-%m') as period, COUNT(*) as count 
                            FROM enrollments 
                            WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                            GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m') 
                            ORDER BY period";
      }

      $trends = $db->getRows($sql);

      echo json_encode(['success' => true, 'trends' => $trends, 'period' => $period]);
      break;

    case 'revenue_analysis':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $analysis = [];

      // Monthly revenue for the last 12 months
      $monthlyRevenue = $db->getRows(
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as revenue
                 FROM payments 
                 WHERE status = 'validated' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                 ORDER BY month"
      );

      // Revenue by program
      $programRevenue = $db->getRows(
        "SELECT p.name, SUM(pay.amount) as revenue, COUNT(e.id) as enrollments
                 FROM programs p
                 LEFT JOIN enrollments e ON p.id = e.program_id
                 LEFT JOIN payments pay ON e.id = pay.enrollment_id AND pay.status = 'validated'
                 GROUP BY p.id
                 ORDER BY revenue DESC"
      );

      // Payment method breakdown
      $paymentMethods = $db->getRows(
        "SELECT payment_method, COUNT(*) as count, SUM(amount) as total
                 FROM payments 
                 WHERE status = 'validated'
                 GROUP BY payment_method
                 ORDER BY total DESC"
      );

      $analysis['monthly_revenue'] = $monthlyRevenue;
      $analysis['program_revenue'] = $programRevenue;
      $analysis['payment_methods'] = $paymentMethods;

      echo json_encode(['success' => true, 'analysis' => $analysis]);
      break;

    case 'program_performance':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $performance = $db->getRows(
        "SELECT p.name, p.fee,
                        COUNT(e.id) as total_enrollments,
                        SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
                        SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments,
                        SUM(CASE WHEN e.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_enrollments,
                        ROUND(
                            (SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) / 
                             NULLIF(COUNT(e.id), 0)) * 100, 2
                        ) as completion_rate,
                        SUM(pay.amount) as total_revenue,
                        AVG(DATEDIFF(e.end_date, e.start_date)) as avg_duration_days
                 FROM programs p
                 LEFT JOIN enrollments e ON p.id = e.program_id
                 LEFT JOIN payments pay ON e.id = pay.enrollment_id AND pay.status = 'validated'
                 GROUP BY p.id
                 ORDER BY total_enrollments DESC"
      );

      echo json_encode(['success' => true, 'performance' => $performance]);
      break;

    case 'student_analytics':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $analytics = [];

      // Student enrollment status
      $enrollmentStatus = $db->getRows(
        "SELECT status, COUNT(*) as count
                 FROM enrollments
                 GROUP BY status
                 ORDER BY count DESC"
      );

      // Top students by enrollments
      $topStudents = $db->getRows(
        "SELECT CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                        u.username as student_id,
                        COUNT(e.id) as total_enrollments,
                        SUM(pay.amount) as total_paid
                 FROM users u
                 JOIN student_profiles sp ON u.id = sp.user_id
                 LEFT JOIN enrollments e ON u.id = e.student_user_id
                 LEFT JOIN payments pay ON e.id = pay.enrollment_id AND pay.status = 'validated'
                 WHERE u.role = 'student'
                 GROUP BY u.id
                 HAVING total_enrollments > 0
                 ORDER BY total_enrollments DESC, total_paid DESC
                 LIMIT 10"
      );

      // Student age distribution
      $ageDistribution = $db->getRows(
        "SELECT 
                    CASE 
                        WHEN age < 12 THEN 'Under 12'
                        WHEN age BETWEEN 12 AND 15 THEN '12-15'
                        WHEN age BETWEEN 16 AND 18 THEN '16-18'
                        WHEN age > 18 THEN 'Over 18'
                        ELSE 'Unknown'
                    END as age_group,
                    COUNT(*) as count
                 FROM student_profiles
                 GROUP BY age_group
                 ORDER BY count DESC"
      );

      $analytics['enrollment_status'] = $enrollmentStatus;
      $analytics['top_students'] = $topStudents;
      $analytics['age_distribution'] = $ageDistribution;

      echo json_encode(['success' => true, 'analytics' => $analytics]);
      break;

    case 'tutor_performance':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $performance = $db->getRows(
        "SELECT CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                        u.username as tutor_id,
                        tp.specializations,
                        tp.hourly_rate,
                        COUNT(e.id) as total_assignments,
                        SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_assignments,
                        SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_assignments,
                        ROUND(
                            (SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) / 
                             NULLIF(COUNT(e.id), 0)) * 100, 2
                        ) as completion_rate
                 FROM users u
                 JOIN tutor_profiles tp ON u.id = tp.user_id
                 LEFT JOIN enrollments e ON u.id = e.tutor_user_id
                 WHERE u.role = 'tutor'
                 GROUP BY u.id
                 ORDER BY total_assignments DESC"
      );

      echo json_encode(['success' => true, 'performance' => $performance]);
      break;

    case 'export_report':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $report_type = $_GET['type'] ?? 'overview';
      $format = $_GET['format'] ?? 'csv';

      // This would generate CSV/Excel exports
      // For now, return the data that would be exported

      switch ($report_type) {
        case 'enrollments':
          $data = $enrollmentManager->getAllEnrollments();
          break;

        case 'payments':
          $data = $paymentManager->getAllPayments();
          break;

        case 'users':
          $data = $db->getRows("SELECT * FROM users ORDER BY created_at DESC");
          break;

        default:
          throw new Exception('Invalid report type');
      }

      echo json_encode(['success' => true, 'data' => $data, 'type' => $report_type, 'format' => $format]);
      break;

    case 'get_recent_activities':
      $limit = $_GET['limit'] ?? 10;

      // Get recent activities based on user role
      if ($_SESSION['role'] === 'admin') {
        $activities = $db->getRows(
          "SELECT 'enrollment' as type, e.created_at, 
                            CONCAT('New enrollment: ', CONCAT(sp.first_name, ' ', sp.last_name), ' enrolled in ', p.name) as description
                     FROM enrollments e
                     JOIN users u ON e.student_user_id = u.id
                     JOIN student_profiles sp ON u.id = sp.user_id
                     JOIN programs p ON e.program_id = p.id
                     WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     
                     UNION ALL
                     
                     SELECT 'payment' as type, pay.created_at,
                            CONCAT('Payment received: ₱', pay.amount, ' from ', CONCAT(sp.first_name, ' ', sp.last_name)) as description
                     FROM payments pay
                     JOIN enrollments e ON pay.enrollment_id = e.id
                     JOIN users u ON e.student_user_id = u.id
                     JOIN student_profiles sp ON u.id = sp.user_id
                     WHERE pay.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     
                     UNION ALL
                     
                     SELECT 'user' as type, u.created_at,
                            CONCAT('New ', u.role, ' registered: ', u.username) as description
                     FROM users u
                     WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     
                     ORDER BY created_at DESC
                     LIMIT ?",
          [$limit],
          "i"
        );
      } elseif ($_SESSION['role'] === 'student') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $activities = $db->getRows(
          "SELECT 'enrollment' as type, e.created_at,
                            CONCAT('You enrolled in ', p.name) as description
                     FROM enrollments e
                     JOIN programs p ON e.program_id = p.id
                     WHERE e.student_user_id = ? AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     
                     UNION ALL
                     
                     SELECT 'payment' as type, pay.created_at,
                            CONCAT('Payment made: ₱', pay.amount, ' (', pay.status, ')') as description
                     FROM payments pay
                     JOIN enrollments e ON pay.enrollment_id = e.id
                     WHERE e.student_user_id = ? AND pay.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     
                     ORDER BY created_at DESC
                     LIMIT ?",
          [$currentUser['id'], $currentUser['id'], $limit],
          "iii"
        );
      } else {
        $activities = [];
      }

      echo json_encode(['success' => true, 'activities' => $activities]);
      break;

    case 'recent_activity':
      // Get recent activity for current user based on role
      $user_id = $_SESSION['user_id'];
      $role = $_SESSION['role'];
      
      $activities = [];
      
      if ($role === 'student') {
        // Get student's recent enrollments and program activities
        $stmt = $conn->prepare("
          SELECT 
            'enrollment' as type,
            p.name as title,
            CONCAT('Enrolled in ', p.name) as description,
            e.enrollment_date as date
          FROM enrollments e
          JOIN programs p ON e.program_id = p.id
          WHERE e.student_user_id = ?
          ORDER BY e.enrollment_date DESC
          LIMIT 10
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
          $activities[] = $row;
        }
      }
      
      echo json_encode(['success' => true, 'data' => $activities]);
      break;

    case 'my_activity':
      // Get user's personal activity log
      $user_id = $_SESSION['user_id'];
      
      $activities = [];
      // Add basic activity tracking - can be expanded later
      
      echo json_encode(['success' => true, 'data' => $activities]);
      break;

    case 'tutor_activity':
      if ($_SESSION['role'] !== 'tutor') {
        throw new Exception('Tutor access required');
      }
      
      $user_id = $_SESSION['user_id'];
      
      // Get tutor's activity
      $activities = [];
      
      echo json_encode(['success' => true, 'data' => $activities]);
      break;

    default:
      throw new Exception('Invalid action');
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
