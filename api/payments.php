<?php
// API for payment management operations
session_start();

// Prevent any HTML output before JSON
ob_start();

// Suppress PHP notices/warnings that could contaminate JSON
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data-helpers.php';
require_once __DIR__ . '/config.php';

// Clear any previous output
ob_clean();

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Helper function to get current user safely
function getCurrentUser() {
  global $userManager;
  
  if (!isset($userManager)) {
    throw new Exception('User management system not available');
  }
  
  $currentUser = $userManager->getUserByLogin($_SESSION['username']);
  if (!$currentUser) {
    throw new Exception('User not found');
  }
  
  return $currentUser;
}

try {
  switch ($action) {
    case 'record_payment':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $enrollment_id = $_POST['enrollment_id'] ?? 0;
      $amount = $_POST['amount'] ?? 0;
  $payment_method = isset($_POST['payment_method']) ? trim(strtolower($_POST['payment_method'])) : 'cash';
      $reference_number = $_POST['reference_number'] ?? null;
      $notes = $_POST['notes'] ?? null;

      if ($enrollment_id <= 0 || $amount <= 0) {
        throw new Exception('Enrollment and amount are required');
      }

      // Validate against supported payment methods defined in API config
      if (!in_array($payment_method, APIConfig::SUPPORTED_PAYMENT_METHODS)) {
        throw new Exception('Invalid payment method');
      }

      // Check if user has access to this enrollment
      if ($_SESSION['role'] === 'student') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $enrollment = $db->getRow(
          "SELECT student_user_id FROM enrollments WHERE id = ?",
          [$enrollment_id],
          "i"
        );

        if (!$enrollment || $enrollment['student_user_id'] != $currentUser['id']) {
          throw new Exception('Access denied');
        }
      }

      $payment_id = $paymentManager->recordPayment($enrollment_id, $amount, $payment_method, $reference_number, $notes);

      echo json_encode(['success' => true, 'payment_id' => $payment_id, 'message' => 'Payment recorded successfully']);
      break;

    case 'validate_payment':
      // Enhanced error logging
      error_log("Validate payment request: " . json_encode($_POST));

      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required - current role: ' . ($_SESSION['role'] ?? 'none'));
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required - received: ' . $method);
      }

      $payment_id = $_POST['payment_id'] ?? '';
      $status = $_POST['status'] ?? 'validated';
      $notes = $_POST['notes'] ?? null;

      error_log("Validation parameters: payment_id=$payment_id, status=$status, notes=$notes");

      if (empty($payment_id)) {
        throw new Exception('Payment ID is required - received empty value');
      }

      if (!in_array($status, ['validated', 'rejected'])) {
        throw new Exception("Invalid status '$status' - must be 'validated' or 'rejected'");
      }

      // Get current user ID directly from database
      $current_user_id = null;
      try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
          throw new Exception('Database prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("s", $_SESSION['username']);
        if (!$stmt->execute()) {
          throw new Exception('Database execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
          $current_user_id = $row['id'];
        }
        $stmt->close();

        if (!$current_user_id) {
          throw new Exception('Current user not found in database: ' . $_SESSION['username']);
        }

        error_log("Found user ID: $current_user_id for username: " . $_SESSION['username']);
      } catch (Exception $e) {
        error_log("Error getting user ID: " . $e->getMessage());
        throw new Exception('Failed to get user information: ' . $e->getMessage());
      }

      // Validate the payment
      try {
        $affected = validatePayment($payment_id, $current_user_id, $status, $notes);
        error_log("Validation result: affected rows = $affected");

        // Ensure changes are committed to database (remove the autocommit check)
        // In MySQLi, autocommit is on by default, so we don't need to manually commit
        // unless we're in a transaction

        if ($affected > 0) {
          echo json_encode([
            'success' => true,
            'message' => 'Payment ' . $status . ' successfully',
            'payment_id' => $payment_id,
            'affected_rows' => $affected
          ]);
        } else {
          throw new Exception("No rows affected - payment '$payment_id' not found or already processed");
        }
      } catch (Exception $e) {
        error_log("validatePayment() error: " . $e->getMessage());
        throw new Exception('Validation failed: ' . $e->getMessage());
      }
      break;

    case 'reject_payment':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $payment_id = $_POST['payment_id'] ?? '';
      $rejection_reason = $_POST['rejection_reason'] ?? '';

      if (empty($payment_id)) {
        throw new Exception('Payment ID is required');
      }

      if (empty($rejection_reason)) {
        throw new Exception('Rejection reason is required');
      }

      // Get current user info
      $current_user = getCurrentUser();

      // Use the validatePayment function with rejected status
      $affected = validatePayment($payment_id, $current_user['id'], 'rejected', $rejection_reason);

      if ($affected > 0) {
        echo json_encode([
          'success' => true,
          'message' => 'Payment rejected successfully',
          'payment_id' => $payment_id
        ]);
      } else {
        throw new Exception('Failed to reject payment - payment not found or already processed');
      }
      break;

    case 'get_payments':
      if ($_SESSION['role'] === 'admin') {
        // Admin can see all payments
        $payments = $paymentManager->getAllPayments();
      } elseif ($_SESSION['role'] === 'student') {
        // Student can only see their own payments
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $payments = $db->getRows(
          "SELECT p.*, e.id as enrollment_id, pr.name as program_name
                     FROM payments p
                     JOIN enrollments e ON p.enrollment_id = e.id
                     JOIN programs pr ON e.program_id = pr.id
                     WHERE e.student_user_id = ?
                     ORDER BY p.created_at DESC",
          [$currentUser['id']],
          "i"
        );
      } else {
        throw new Exception('Access denied');
      }

      echo json_encode(['success' => true, 'payments' => $payments]);
      break;

    case 'get_payment_details':
      $payment_raw = $_GET['id'] ?? 0;

      // Accept both numeric IDs and formatted IDs like PAY-YYYYMMDD-001
      $payment_id = 0;
      if (is_numeric($payment_raw)) {
        $payment_id = intval($payment_raw);
      } elseif (is_string($payment_raw) && preg_match('/PAY-\d{8}-0*(\d+)$/i', $payment_raw, $m)) {
        $payment_id = intval($m[1]);
      } else {
        // Try to coerce common formatted patterns
        if (preg_match('/(\d+)$/', $payment_raw, $m2)) {
          $payment_id = intval($m2[1]);
        }
      }

      if ($payment_id <= 0) {
        throw new Exception('Invalid payment ID');
      }

      // Use LEFT JOIN for related tables so we still return the payment row even if related records
      // (enrollments, programs, profiles) are missing or were not restored correctly.
      $payment = $db->getRow(
        "SELECT p.*, p.enrollment_id as enrollment_id, 
                        COALESCE(pr.name, '') as program_name,
                        CONCAT(IFNULL(sp.first_name, ''), ' ', IFNULL(sp.last_name, '')) as student_name,
                        COALESCE(u.user_id, u.id) as student_id,
                        u.username as student_username,
                        CONCAT(IFNULL(vsp.first_name, ''), ' ', IFNULL(vsp.last_name, '')) as validator_name
                 FROM payments p
                 LEFT JOIN enrollments e ON p.enrollment_id = e.id
                 LEFT JOIN programs pr ON e.program_id = pr.id
                 LEFT JOIN users u ON e.student_user_id = u.id
                 LEFT JOIN student_profiles sp ON e.student_user_id = sp.user_id
                 LEFT JOIN users v ON p.validated_by = v.id
                 LEFT JOIN student_profiles vsp ON v.id = vsp.user_id
                 WHERE p.id = ?",
        [$payment_id],
        "i"
      );

      if (!$payment) {
        throw new Exception('Payment not found');
      }

      // Check access permissions
      if ($_SESSION['role'] === 'student') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $enrollment = $db->getRow(
          "SELECT student_user_id FROM enrollments WHERE id = ?",
          [$payment['enrollment_id']],
          "i"
        );

        if (!$enrollment || $enrollment['student_user_id'] != $currentUser['id']) {
          throw new Exception('Access denied');
        }
      } elseif ($_SESSION['role'] !== 'admin') {
        // Only students and admins are allowed to access payment details
        throw new Exception('Access denied');
      }

      echo json_encode(['success' => true, 'payment' => $payment]);
      break;

    case 'get_pending_payments':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $payments = $db->getRows(
        "SELECT p.*, e.id as enrollment_id, pr.name as program_name,
                        CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                        u.username as student_id
                 FROM payments p
                 JOIN enrollments e ON p.enrollment_id = e.id
                 JOIN programs pr ON e.program_id = pr.id
                 JOIN users u ON e.student_user_id = u.id
                 JOIN student_profiles sp ON e.student_user_id = sp.user_id
                 WHERE p.status = 'pending'
                 ORDER BY p.created_at ASC"
      );

      echo json_encode(['success' => true, 'payments' => $payments]);
      break;

    case 'get_payment_stats':
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      $stats = [];

      // Total revenue (validated payments)
      $revenue = $db->getRow("SELECT SUM(amount) as total FROM payments WHERE status = 'validated'");
      $stats['total_revenue'] = $revenue['total'] ?? 0;

      // Pending payments
      $pending = $db->getRow("SELECT COUNT(*) as count, SUM(amount) as total FROM payments WHERE status = 'pending'");
      $stats['pending_payments_count'] = $pending['count'];
      $stats['pending_payments_amount'] = $pending['total'] ?? 0;

      // This month's revenue
      $monthlyRevenue = $db->getRow(
        "SELECT SUM(amount) as total FROM payments 
                 WHERE status = 'validated' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())"
      );
      $stats['monthly_revenue'] = $monthlyRevenue['total'] ?? 0;

      // Payment methods breakdown
      $methods = $db->getRows(
        "SELECT payment_method, COUNT(*) as count, SUM(amount) as total 
                 FROM payments WHERE status = 'validated' 
                 GROUP BY payment_method 
                 ORDER BY total DESC"
      );
      $stats['payment_methods'] = $methods;

      // Outstanding balances
      $outstanding = $db->getRow(
        "SELECT SUM(e.total_fee - IFNULL(paid.total, 0)) as total
                 FROM enrollments e
                 LEFT JOIN (
                     SELECT enrollment_id, SUM(amount) as total 
                     FROM payments 
                     WHERE status = 'validated' 
                     GROUP BY enrollment_id
                 ) paid ON e.id = paid.enrollment_id
                 WHERE e.status IN ('pending', 'active') AND (e.total_fee - IFNULL(paid.total, 0)) > 0"
      );
      $stats['outstanding_balance'] = $outstanding['total'] ?? 0;

      echo json_encode(['success' => true, 'stats' => $stats]);
      break;

    case 'get_enrollment_balance':
      $enrollment_id = $_GET['enrollment_id'] ?? 0;

      $balance = $db->getRow(
        "SELECT e.total_fee, IFNULL(SUM(p.amount), 0) as total_paid,
                        (e.total_fee - IFNULL(SUM(p.amount), 0)) as balance
                 FROM enrollments e
                 LEFT JOIN payments p ON e.id = p.enrollment_id AND p.status = 'validated'
                 WHERE e.id = ?
                 GROUP BY e.id",
        [$enrollment_id],
        "i"
      );

      if (!$balance) {
        throw new Exception('Enrollment not found');
      }

      // Check access permissions
      if ($_SESSION['role'] === 'student') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $enrollment = $db->getRow(
          "SELECT student_user_id FROM enrollments WHERE id = ?",
          [$enrollment_id],
          "i"
        );

        if (!$enrollment || $enrollment['student_user_id'] != $currentUser['id']) {
          throw new Exception('Access denied');
        }
      }

      echo json_encode(['success' => true, 'balance' => $balance]);
      break;

    case 'generate_receipt':
      $payment_id = $_GET['payment_id'] ?? 0;

      if (!$payment_id) {
        throw new Exception('Payment ID is required');
      }

      // Handle both formatted payment IDs (PAY-YYYYMMDD-XXX) and numeric IDs
      $numeric_payment_id = $payment_id;
      if (is_string($payment_id) && preg_match('/PAY-\d{8}-(\d+)/', $payment_id, $matches)) {
        $numeric_payment_id = intval($matches[1]);
      } elseif (is_string($payment_id) && is_numeric($payment_id)) {
        $numeric_payment_id = intval($payment_id);
      } elseif (is_numeric($payment_id)) {
        $numeric_payment_id = intval($payment_id);
      }

      if ($numeric_payment_id <= 0) {
        throw new Exception('Invalid Payment ID format');
      }

      // Get payment details with all necessary information - only for validated payments
      $payment = $db->getRow(
        "SELECT p.*, e.id as enrollment_id, e.student_user_id, pr.name as program_name,
                pr.fee as program_fee,
                CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                u.username as student_id, u.email as student_email,
                CONCAT(vsp.first_name, ' ', vsp.last_name) as validator_name
         FROM payments p
         JOIN enrollments e ON p.enrollment_id = e.id
         JOIN programs pr ON e.program_id = pr.id
         JOIN student_profiles sp ON e.student_user_id = sp.user_id
         JOIN users u ON sp.user_id = u.id
         LEFT JOIN student_profiles vsp ON p.validated_by = vsp.user_id
         WHERE p.id = ? AND p.status = 'validated'",
        [$numeric_payment_id],
        "i"
      );

      if (!$payment) {
        throw new Exception('Payment not found or not validated. Only validated payments can have receipts generated.');
      }

      // Check access permissions
      if ($_SESSION['role'] === 'student') {
        // For students, get their user ID from session
        require_once __DIR__ . '/../includes/data-helpers.php';
        
        // Get student user ID from session username
        $student_user_query = $db->getRow(
          "SELECT id FROM users WHERE username = ?",
          [$_SESSION['username']],
          "s"
        );
        
        if (!$student_user_query || $payment['student_user_id'] != $student_user_query['id']) {
          throw new Exception('Access denied - You can only view your own payment receipts');
        }
      }

      // Generate receipt HTML
      $receipt_html = generateReceiptHTML($payment);

      // Set headers for HTML display
      header('Content-Type: text/html; charset=utf-8');

      // Display the receipt in the browser window
      echo $receipt_html;
      exit;

    case 'get_receipt_attachment':
      $payment_id = $_GET['payment_id'] ?? 0;
      
      // Debug logging
      error_log("get_receipt_attachment called with payment_id: " . $payment_id);

      if (!$payment_id) {
        echo json_encode([
          'success' => false,
          'error' => 'Payment ID is required',
          'message' => 'No payment ID provided in the request'
        ]);
        exit;
      }

      // Extract numeric payment ID from formatted payment ID (PAY-YYYYMMDD-XXX)
      $numeric_payment_id = $payment_id;
      if (preg_match('/PAY-\d{8}-0*(\d+)/', $payment_id, $matches)) {
        $numeric_payment_id = intval($matches[1]); // Convert to integer to remove leading zeros
        error_log("Extracted numeric payment_id: " . $numeric_payment_id . " from formatted ID: " . $payment_id);
      } else if (is_numeric($payment_id)) {
        $numeric_payment_id = intval($payment_id);
        error_log("Using numeric payment_id: " . $numeric_payment_id);
      } else {
        error_log("Invalid payment_id format: " . $payment_id);
        echo json_encode([
          'success' => false,
          'error' => 'Invalid payment ID format',
          'message' => 'Payment ID must be numeric or in PAY-YYYYMMDD-XXX format',
          'provided_id' => $payment_id
        ]);
        exit;
      }

      // First check if payment_attachments table exists
      $table_check = $conn->query("SHOW TABLES LIKE 'payment_attachments'");
      if (!$table_check || $table_check->num_rows == 0) {
        error_log("payment_attachments table does not exist");
        throw new Exception('Payment attachments table not found. Please create the database table first.');
      }

      // Get the payment proof attachment for this payment from payment_attachments table
      $attachment = $db->getRow(
        "SELECT pa.*, p.id as payment_id, p.enrollment_id
         FROM payment_attachments pa 
         JOIN payments p ON pa.payment_id = p.id
         WHERE p.id = ?
         ORDER BY pa.created_at DESC
         LIMIT 1",
        [$numeric_payment_id],
        "i"
      );

      error_log("Query result for numeric payment_id $numeric_payment_id: " . ($attachment ? json_encode($attachment) : 'No attachment found'));

      if (!$attachment) {
        echo json_encode([
          'success' => false,
          'error' => 'No payment proof attachment found for this payment',
          'message' => 'This payment does not have an uploaded proof of payment.',
          'payment_id' => $payment_id,
          'numeric_payment_id' => $numeric_payment_id
        ]);
        exit;
      }

      // Check access permissions
      if ($_SESSION['role'] === 'student') {
        // Students can only view their own payment proof
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $enrollment = $db->getRow(
          "SELECT student_user_id FROM enrollments WHERE id = ?",
          [$attachment['enrollment_id']],
          "i"
        );

        if (!$enrollment || $enrollment['student_user_id'] != $currentUser['id']) {
          throw new Exception('Access denied');
        }
      }

      // Get file data for base64 encoding
      $file_path = __DIR__ . '/../uploads/payment_receipts/' . $attachment['filename'];
      $base64_data = null;
      
      if (file_exists($file_path)) {
        $file_data = file_get_contents($file_path);
        $base64_data = base64_encode($file_data);
      }

      echo json_encode([
        'success' => true,
        'attachment' => [
          'id' => $attachment['id'],
          'filename' => $attachment['filename'],
          'original_name' => $attachment['original_filename'],
          'file_path' => 'uploads/payment_receipts/' . $attachment['filename'],
          'mime_type' => $attachment['mime_type'],
          'file_size' => $attachment['file_size'],
          'uploaded_at' => $attachment['created_at'],
          'base64_data' => $base64_data
        ]
      ]);
      break;

    case 'get_rejection_reason':
      $payment_id = $_GET['payment_id'] ?? 0;

      if (!$payment_id) {
        throw new Exception('Payment ID is required');
      }

      // Get payment details including rejection reason
      $payment = $db->getRow(
        "SELECT p.*, pr.name as program_name, pr.fee as program_fee,
                        CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                        u.username as student_id,
                        p.notes as rejection_reason, p.notes,
                        e.total_fee as enrollment_fee,
                        CONCAT('PAY-', DATE_FORMAT(p.created_at, '%Y%m%d'), '-', LPAD(p.id, 6, '0')) as formatted_payment_id,
                        (SELECT IFNULL(SUM(amount), 0) FROM payments p2 WHERE p2.enrollment_id = e.id AND p2.status = 'validated' AND p2.id != p.id) as paid_amount
                 FROM payments p
                 JOIN enrollments e ON p.enrollment_id = e.id
                 JOIN programs pr ON e.program_id = pr.id
                 JOIN users u ON e.student_user_id = u.id
                 JOIN student_profiles sp ON e.student_user_id = sp.user_id
                 WHERE p.id = ?",
        [$payment_id],
        "i"
      );

      if (!$payment) {
        throw new Exception('Payment not found');
      }

      // Check access permissions
      if ($_SESSION['role'] === 'student') {
        $currentUser = $userManager->getUserByLogin($_SESSION['username']);
        $enrollment = $db->getRow(
          "SELECT student_user_id FROM enrollments WHERE id = ?",
          [$payment['enrollment_id']],
          "i"
        );

        if (!$enrollment || $enrollment['student_user_id'] != $currentUser['id']) {
          throw new Exception('Access denied');
        }
      } elseif ($_SESSION['role'] !== 'admin') {
        throw new Exception('Access denied');
      }

      echo json_encode([
        'success' => true, 
        'rejection_reason' => $payment['notes'] ?: 'Payment was rejected. Please resubmit with correct details.',
        'payment_details' => [
          'payment_id' => $payment['formatted_payment_id'] ?: $payment['payment_id'],
          'program_name' => $payment['program_name'],
          'amount' => $payment['amount'],
          'student_name' => $payment['student_name'],
          'student_id' => $payment['student_id'],
          'status' => $payment['status'],
          'notes' => $payment['notes'],
          'enrollment_fee' => $payment['enrollment_fee'],
          'program_fee' => $payment['program_fee'],
          'paid_amount' => $payment['paid_amount'],
          'current_balance' => $payment['enrollment_fee'] - $payment['paid_amount'],
          'balance_after_payment' => $payment['enrollment_fee'] - $payment['paid_amount'] - $payment['amount']
        ]
      ]);
      break;

    default:
      throw new Exception('Invalid action');
  }
} catch (Exception $e) {
  // Enhanced error logging
  error_log("API Error: " . $e->getMessage());
  error_log("API Error Stack Trace: " . $e->getTraceAsString());
  error_log("Request data: " . json_encode($_POST));
  error_log("Session data: " . json_encode($_SESSION));

  http_response_code(400);
  echo json_encode([
    'error' => $e->getMessage(),
    'success' => false,
    'debug_info' => [
      'action' => $action,
      'method' => $method,
      'session_role' => $_SESSION['role'] ?? 'none',
      'session_username' => $_SESSION['username'] ?? 'none'
    ]
  ]);
}

// Flush output buffer to ensure clean JSON response
ob_end_flush();

// Function to generate receipt HTML
function generateReceiptHTML($payment)
{
  $receipt_date = date('F j, Y');
  $payment_date = date('F j, Y', strtotime($payment['payment_date']));

  return "
  <!DOCTYPE html>
  <html>
  <head>
    <title>Payment Receipt - {$payment['payment_id']}</title>
    <style>
      body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
      .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
      .company-name { font-size: 24px; font-weight: bold; color: #2563eb; }
      .receipt-title { font-size: 18px; margin-top: 10px; }
      .details { display: flex; justify-content: space-between; margin-bottom: 30px; }
      .details div { width: 48%; }
      .label { font-weight: bold; color: #555; }
      .value { margin-bottom: 10px; }
      .amount-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
      .total-amount { font-size: 24px; font-weight: bold; color: #059669; text-align: center; }
      .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
      .status { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
      .status.validated { background: #d1fae5; color: #065f46; }
      .status.pending { background: #fef3c7; color: #92400e; }
      .status.rejected { background: #fee2e2; color: #991b1b; }
    </style>
  </head>
  <body>
    <div class='header'>
      <div class='company-name'>TPLearn</div>
      <div class='receipt-title'>Payment Receipt</div>
    </div>
    
    <div class='details'>
      <div>
        <div class='label'>Receipt No:</div>
        <div class='value'>REC-{$payment['id']}</div>
        
        <div class='label'>Payment ID:</div>
        <div class='value'>{$payment['payment_id']}</div>
        
        <div class='label'>Date Issued:</div>
        <div class='value'>{$receipt_date}</div>
        
        <div class='label'>Payment Method:</div>
        <div class='value'>" . ucfirst($payment['payment_method']) . "</div>
      </div>
      
      <div>
        <div class='label'>Student Name:</div>
        <div class='value'>{$payment['student_name']}</div>
        
        <div class='label'>Student ID:</div>
        <div class='value'>{$payment['student_id']}</div>
        
        <div class='label'>Program:</div>
        <div class='value'>{$payment['program_name']}</div>
        
        <div class='label'>Payment Date:</div>
        <div class='value'>{$payment_date}</div>
      </div>
    </div>
    
    <div class='amount-section'>
      <div class='total-amount'>Total Amount Paid: ₱" . number_format($payment['amount'], 2) . "</div>
    </div>
    
    <div style='text-align: center; margin: 20px 0;'>
      <span class='status " . strtolower($payment['status']) . "'>" . ucfirst($payment['status']) . "</span>
    </div>
    
    <div class='footer'>
      <p><strong>TPLearn Learning Management System</strong></p>
      <p>📧 support@tplearn.com | 📞 (02) 8123-4567</p>
      <p>🌐 www.tplearn.com</p>
      <p style='margin-top: 20px;'><em>Thank you for your payment!</em></p>
    </div>
  </body>
  </html>";
}
