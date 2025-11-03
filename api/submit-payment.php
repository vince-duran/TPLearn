<?php
// Ensure clean JSON output - no whitespace before this tag
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0); // Turn off all error reporting for clean JSON
ini_set('log_errors', 1);

// Start output buffering immediately
ob_start();

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Manual auth check to avoid include issues
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

// Manual database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "tplearn";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
  $start_time = microtime(true);
  error_log("DEBUG: Starting payment submission process - " . date('Y-m-d H:i:s'));

  // Verify user is logged in
  if (!isset($_SESSION['user_id'])) {
    throw new Exception('User not authenticated');
  }

  error_log("DEBUG: User authenticated - ID: " . $_SESSION['user_id']);

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Invalid request method');
  }

  // Handle both JSON and form data (for file uploads)
  $is_resubmission = isset($_POST['is_resubmission']) && $_POST['is_resubmission'] === 'true';
  $has_form_data = !empty($_POST) || !empty($_FILES);
  
  // Debug logging
  error_log("DEBUG: is_resubmission = " . ($is_resubmission ? 'true' : 'false'));
  error_log("DEBUG: has_form_data = " . ($has_form_data ? 'true' : 'false'));
  error_log("DEBUG: POST data: " . print_r($_POST, true));
  error_log("DEBUG: FILES data: " . print_r($_FILES, true));
  
  if ($has_form_data) {
    // Handle form data submission (with or without file upload)
    $payment_id = isset($_POST['payment_id']) ? trim($_POST['payment_id']) : '';
    $reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    
    // Handle file upload
    $receipt_file = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
      $receipt_file = $_FILES['receipt'];
      error_log("DEBUG: File upload detected - name: " . $receipt_file['name'] . ", size: " . $receipt_file['size']);
    } else {
      error_log("DEBUG: No file upload detected or file upload error");
      if (isset($_FILES['receipt'])) {
        error_log("DEBUG: File upload error code: " . $_FILES['receipt']['error']);
      }
    }
  } else {
    // Handle regular JSON submission
    $input = json_decode(file_get_contents('php://input'), true);
    $payment_id = isset($input['payment_id']) ? trim($input['payment_id']) : '';
    $reference_number = isset($input['reference_number']) ? trim($input['reference_number']) : '';
    $payment_method = isset($input['payment_method']) ? trim($input['payment_method']) : '';
  }

  error_log("DEBUG: payment_id = '$payment_id', reference_number = '$reference_number', payment_method = '$payment_method'");

  if (!$payment_id || !$reference_number || !$payment_method) {
    throw new Exception('Missing required fields: payment_id, reference_number, payment_method');
  }

  // Map payment method values for consistency
  $payment_method_map = [
    // E-Wallet mappings (default to gcash for legacy compatibility)
    'ewallet' => 'gcash',    // E-Wallet from forms maps to gcash (most common)
    'gcash' => 'gcash',      // GCash remains gcash
    'maya' => 'maya',        // Maya remains maya
    
    // Bank mappings
    'bank' => 'bpi',         // Bank from forms maps to bpi (default)
    'bpi' => 'bpi',          // BPI remains bpi  
    'bdo' => 'bdo',          // BDO remains bdo
    'seabank' => 'seabank',  // SeaBank remains seabank (legacy)
    
    // Cash mapping
    'cash' => 'cash'         // Cash remains cash
  ];
  
  // Apply mapping if exists, otherwise keep original value
  if (isset($payment_method_map[$payment_method])) {
    $mapped_payment_method = $payment_method_map[$payment_method];
    error_log("DEBUG: Mapped payment method '$payment_method' to '$mapped_payment_method'");
    $payment_method = $mapped_payment_method;
  }

  // Extract numeric payment ID from formatted string (PAY-YYYYMMDD-XXX)
  if (preg_match('/PAY-\d{8}-(\d+)/', $payment_id, $matches)) {
    $numeric_payment_id = intval($matches[1]);
  } else {
    // If it's already numeric, use it directly
    $numeric_payment_id = intval($payment_id);
  }

  // Verify the payment belongs to the current user
  $verify_sql = "SELECT p.id, p.enrollment_id, p.amount, p.status, p.installment_number, p.reference_number,
                          e.student_user_id, pr.name as program_name
                   FROM payments p
                   JOIN enrollments e ON p.enrollment_id = e.id
                   JOIN programs pr ON e.program_id = pr.id
                   WHERE p.id = ? AND e.student_user_id = ?";

  $verify_stmt = $conn->prepare($verify_sql);
  $verify_stmt->bind_param('ii', $numeric_payment_id, $_SESSION['user_id']);
  $verify_stmt->execute();
  $payment_result = $verify_stmt->get_result();

  if (!$payment_result || $payment_result->num_rows === 0) {
    throw new Exception('Payment not found or does not belong to current user');
  }

  $payment_data = $payment_result->fetch_assoc();

  // Check if payment is in valid state for submission
  if ($is_resubmission) {
    // For resubmissions, allow rejected payments to be resubmitted
    if ($payment_data['status'] !== 'rejected') {
      throw new Exception('Only rejected payments can be resubmitted - status is: ' . $payment_data['status']);
    }
  } else {
    // For regular payments, only allow pending payments
    if ($payment_data['status'] !== 'pending') {
      throw new Exception('Payment cannot be submitted - status is: ' . $payment_data['status']);
    }
    
    // Check if payment already has a reference number (already submitted)
    if (!empty($payment_data['reference_number'])) {
      throw new Exception('Payment has already been submitted with reference: ' . $payment_data['reference_number']);
    }
  }

  // Handle file upload if present
  $receipt_filename = null;
  if ($receipt_file) {
    error_log("DEBUG: Starting file upload process");
    $file_start = microtime(true);
    
    $upload_dir = '../uploads/payment_receipts/';
    if (!is_dir($upload_dir)) {
      mkdir($upload_dir, 0777, true);
    }
    
    // Quick file validation (reduced checks for speed)
    $allowed_extensions = ['png', 'jpg', 'jpeg'];
    $extension = strtolower(pathinfo($receipt_file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions)) {
      throw new Exception('Invalid file type. Only PNG, JPG, and JPEG files are allowed.');
    }
    
    if ($receipt_file['size'] > 5 * 1024 * 1024) { // Reduced to 5MB for faster processing
      throw new Exception('File size too large. Maximum 5MB allowed.');
    }
    
    // Generate unique filename (faster method)
    $receipt_filename = 'receipt_' . $numeric_payment_id . '_' . uniqid() . '.' . $extension;
    $receipt_path = $upload_dir . $receipt_filename;
    
    if (!move_uploaded_file($receipt_file['tmp_name'], $receipt_path)) {
      throw new Exception('Failed to upload receipt file');
    }
    
    $file_time = microtime(true) - $file_start;
    error_log("DEBUG: File upload completed in {$file_time} seconds");
  }

  // Start transaction
  $conn->autocommit(false);

  // Update payment with reference number and status
  if ($is_resubmission) {
    // For resubmissions, reset to pending_validation status and clear notes
    $update_sql = "UPDATE payments 
                   SET reference_number = ?, payment_method = ?, status = 'pending', notes = NULL 
                   WHERE id = ?";
  } else {
    // For regular payments, update reference, method, and reset status to pending
    // This ensures overdue payments become pending_validation after submission
    $update_sql = "UPDATE payments 
                   SET reference_number = ?, payment_method = ?, status = 'pending' 
                   WHERE id = ?";
  }

  $update_stmt = $conn->prepare($update_sql);
  $update_stmt->bind_param('ssi', $reference_number, $payment_method, $numeric_payment_id);

  if (!$update_stmt->execute()) {
    throw new Exception('Failed to update payment: ' . $conn->error);
  }

  // Insert receipt attachment if file was uploaded (with complete fields)
  if ($receipt_filename) {
    error_log("DEBUG: Inserting receipt attachment for payment $numeric_payment_id");
    
    // Get file info
    $file_size = filesize($receipt_path);
    $mime_type = mime_content_type($receipt_path);
    
    // Complete attachment insert with all necessary fields
    $attachment_sql = "INSERT INTO payment_attachments (payment_id, filename, original_filename, mime_type, file_size, created_at) 
                       VALUES (?, ?, ?, ?, ?, NOW())";
    $attachment_stmt = $conn->prepare($attachment_sql);
    
    if ($attachment_stmt) {
      $attachment_stmt->bind_param('isssi', $numeric_payment_id, $receipt_filename, $receipt_file['name'], $mime_type, $file_size);
      
      if (!$attachment_stmt->execute()) {
        error_log("ERROR: Failed to insert attachment: " . $attachment_stmt->error);
        // Continue without attachment record for now
      } else {
        error_log("SUCCESS: Attachment inserted with mime_type: $mime_type, file_size: $file_size");
      }
      $attachment_stmt->close();
    }
  }

  // Payment history logging (temporarily disabled for performance)
  error_log("Payment submission completed for payment ID: $numeric_payment_id");
  
  // Create notification for payment submission
  try {
    require_once __DIR__ . '/../includes/notification-helpers.php';
    
    $title = 'Payment Submitted for Review';
    $message = "Your payment of ₱" . number_format($payment_data['amount'], 2) . " for {$payment_data['program_name']} has been submitted and is under review.";
    
    $notification_result = createNotification(
      $payment_data['student_user_id'], 
      $title, 
      $message, 
      'info', 
      'dashboards/student/student-payments.php'
    );
    
    if (!$notification_result['success']) {
      error_log("Failed to create payment submission notification: " . $notification_result['error']);
    } else {
      error_log("Payment submission notification sent successfully. Email sent: " . ($notification_result['email_sent'] ? 'Yes' : 'No'));
    }

    // Create admin notification for payment validation needed
    $student_name = $payment_data['student_first_name'] . ' ' . $payment_data['student_last_name'];
    $admin_notification_result = createAdminPaymentValidationNotification(
      $payment_id,
      $student_name,
      $payment_data['amount'],
      $payment_data['program_name']
    );

    if (!$admin_notification_result['success']) {
      error_log("Failed to create admin payment validation notification: " . $admin_notification_result['error']);
    } else {
      error_log("Admin payment validation notification sent successfully. Notifications created: " . 
        $admin_notification_result['notifications_created'] . 
        ", Admin emails sent: " . $admin_notification_result['admin_email_result']['emails_sent']);
    }

  } catch (Exception $e) {
    error_log("Error sending payment submission notification: " . $e->getMessage());
  }

  // Commit transaction
  $conn->commit();
  $conn->autocommit(true);

  $total_time = microtime(true) - $start_time;
  error_log("DEBUG: Payment submission completed in {$total_time} seconds");

  // Clean any unexpected output before sending JSON
  ob_clean();

  // Send success response
  header('Content-Type: application/json');
  $response = [
    'success' => true,
    'message' => $is_resubmission ? 'Payment resubmitted successfully' : 'Payment submitted successfully',
    'payment_id' => $payment_id,
    'program_name' => $payment_data['program_name'],
    'amount' => floatval($payment_data['amount']),
    'reference_number' => $reference_number,
    'status' => $is_resubmission ? 'pending' : $payment_data['status'],
    'receipt_uploaded' => !is_null($receipt_filename),
    'processing_time' => round($total_time, 3)
  ];

  echo json_encode($response);
  exit;
} catch (Exception $e) {
  // Clean output buffer and send error response
  ob_clean();

  // Rollback transaction on error
  if (isset($conn) && $conn) {
    $conn->rollback();
    $conn->autocommit(true);
  }

  error_log("Payment submission error: " . $e->getMessage());

  // Send error response
  header('Content-Type: application/json');
  http_response_code(400);
  
  $error_response = [
    'success' => false,
    'error' => $e->getMessage()
  ];

  echo json_encode($error_response);
  exit;
}
