<?php
// Temporarily enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Start session before requiring auth
session_start();

require_once '../includes/auth.php';
require_once '../includes/db.php';

try {
  // Verify user is logged in
  if (!isset($_SESSION['user_id'])) {
    throw new Exception('User not authenticated');
  }

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
    $upload_dir = '../uploads/payment_receipts/';
    if (!is_dir($upload_dir)) {
      mkdir($upload_dir, 0777, true);
    }
    
    // Validate file
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $file_type = finfo_file($file_info, $receipt_file['tmp_name']);
    finfo_close($file_info);
    
    if (!in_array($file_type, $allowed_types)) {
      throw new Exception('Invalid file type. Only PNG, JPG, and JPEG files are allowed.');
    }
    
    if ($receipt_file['size'] > 10 * 1024 * 1024) { // 10MB limit
      throw new Exception('File size too large. Maximum 10MB allowed.');
    }
    
    // Generate unique filename
    $extension = pathinfo($receipt_file['name'], PATHINFO_EXTENSION);
    $receipt_filename = 'receipt_' . $numeric_payment_id . '_' . time() . '.' . $extension;
    $receipt_path = $upload_dir . $receipt_filename;
    
    if (!move_uploaded_file($receipt_file['tmp_name'], $receipt_path)) {
      throw new Exception('Failed to upload receipt file');
    }
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
    // For regular payments, just update reference and method
    $update_sql = "UPDATE payments 
                   SET reference_number = ?, payment_method = ? 
                   WHERE id = ?";
  }

  $update_stmt = $conn->prepare($update_sql);
  $update_stmt->bind_param('ssi', $reference_number, $payment_method, $numeric_payment_id);

  if (!$update_stmt->execute()) {
    throw new Exception('Failed to update payment: ' . $conn->error);
  }

  // Insert receipt attachment if file was uploaded
  if ($receipt_filename) {
    error_log("DEBUG: Attempting to insert receipt attachment for payment $numeric_payment_id");
    error_log("DEBUG: receipt_filename = '$receipt_filename'");
    error_log("DEBUG: original filename = '" . $receipt_file['name'] . "'");
    error_log("DEBUG: file_size = " . $receipt_file['size']);
    error_log("DEBUG: file_type = '$file_type'");
    
    // Check if table exists before inserting
    $table_check = $conn->query("SHOW TABLES LIKE 'payment_attachments'");
    if ($table_check && $table_check->num_rows > 0) {
      error_log("DEBUG: payment_attachments table exists, proceeding with insert");
      $attachment_sql = "INSERT INTO payment_attachments (payment_id, filename, original_filename, file_size, mime_type, created_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())";
      $attachment_stmt = $conn->prepare($attachment_sql);
      $attachment_stmt->bind_param('issis', $numeric_payment_id, $receipt_filename, $receipt_file['name'], $receipt_file['size'], $file_type);
      
      if (!$attachment_stmt->execute()) {
        error_log("ERROR: Failed to insert attachment: " . $attachment_stmt->error);
        throw new Exception('Failed to save receipt attachment: ' . $conn->error);
      } else {
        error_log("SUCCESS: Attachment inserted with ID: " . $conn->insert_id);
      }
    } else {
      error_log("ERROR: payment_attachments table does not exist");
    }
    // If table doesn't exist, continue without saving attachment record but keep the file
  } else {
    error_log("DEBUG: No receipt file to process (receipt_filename is null)");
  }

  // Commit transaction
  $conn->commit();
  $conn->autocommit(true);

  // Return success response
  echo json_encode([
    'success' => true,
    'message' => $is_resubmission ? 'Payment resubmitted successfully' : 'Payment submitted successfully',
    'payment_id' => $payment_id,
    'program_name' => $payment_data['program_name'],
    'amount' => floatval($payment_data['amount']),
    'reference_number' => $reference_number,
    'status' => $is_resubmission ? 'pending' : $payment_data['status'],
    'receipt_uploaded' => !is_null($receipt_filename)
  ]);
} catch (Exception $e) {
  // Rollback transaction on error
  if ($conn) {
    $conn->rollback();
    $conn->autocommit(true);
  }

  http_response_code(400);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
