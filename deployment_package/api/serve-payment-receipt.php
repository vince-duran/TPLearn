<?php
// Suppress all output and errors before serving the file
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any stray output
ob_start();

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Clean any buffered output before serving file
ob_clean();

// Check if user is authenticated
if (!isLoggedIn()) {
  http_response_code(401);
  exit('Unauthorized');
}

$attachment_id = $_GET['id'] ?? null;
$payment_id = $_GET['payment_id'] ?? null;

if (!$attachment_id && !$payment_id) {
  http_response_code(400);
  exit('Attachment ID or Payment ID is required');
}

// Handle different parameter types
$numeric_attachment_id = null;

if ($attachment_id) {
  // Handle both numeric IDs and formatted payment IDs
  $numeric_attachment_id = $attachment_id;
  if (preg_match('/PAY-\d{8}-(\d+)/', $attachment_id, $matches)) {
    // If it's a formatted payment ID, we need to get the attachment ID from the payment
    $payment_id = $matches[1];
    $attachment_query = $conn->query("SELECT id FROM payment_attachments WHERE payment_id = $payment_id ORDER BY created_at DESC LIMIT 1");
    if ($attachment_query && $row = $attachment_query->fetch_assoc()) {
      $numeric_attachment_id = $row['id'];
    }
  }
} elseif ($payment_id) {
  // If payment_id is provided, get the attachment_id
  $attachment_query = $conn->query("SELECT id FROM payment_attachments WHERE payment_id = " . intval($payment_id) . " ORDER BY created_at DESC LIMIT 1");
  if ($attachment_query && $row = $attachment_query->fetch_assoc()) {
    $numeric_attachment_id = $row['id'];
  } else {
    http_response_code(404);
    exit('No attachment found for this payment');
  }
}

if (!$numeric_attachment_id) {
  http_response_code(404);
  exit('Attachment not found');
}

try {
  $db = new Database($conn);

  // Get attachment information from payment_attachments table
  $attachment = $db->getRow(
    "SELECT pa.*, p.enrollment_id 
     FROM payment_attachments pa
     JOIN payments p ON pa.payment_id = p.id
     WHERE pa.id = ?",
    [$numeric_attachment_id],
    "i"
  );

  if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found');
  }

  // Check access permissions
  if ($_SESSION['role'] === 'student') {
    // Students can only access their own payment proof
    global $userManager;
    $currentUser = $userManager->getUserByLogin($_SESSION['username']);
    $enrollment = $db->getRow(
      "SELECT student_user_id FROM enrollments WHERE id = ?",
      [$attachment['enrollment_id']],
      "i"
    );

    if (!$enrollment || $enrollment['student_user_id'] != $currentUser['id']) {
      http_response_code(403);
      exit('Access denied');
    }
  } elseif ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
  }

  // Build file path
  $file_path = __DIR__ . '/../uploads/payment_receipts/' . $attachment['filename'];

  if (!file_exists($file_path)) {
    http_response_code(404);
    exit('File not found on server');
  }

  // Get file info
  $file_size = filesize($file_path);
  $mime_type = $attachment['mime_type'] ?: 'application/octet-stream';

  // Clean any output buffer before sending headers and file
  if (ob_get_level()) {
    ob_end_clean();
  }

  // Set appropriate headers
  header('Content-Type: ' . $mime_type);
  header('Content-Length: ' . $file_size);
  header('Content-Disposition: inline; filename="' . addslashes($attachment['original_filename']) . '"');
  header('Cache-Control: private, max-age=3600');
  header('Pragma: cache');

  // Output file
  if ($file_size > 0) {
    readfile($file_path);
  }

} catch (Exception $e) {
  error_log('Error serving payment proof: ' . $e->getMessage());
  http_response_code(500);
  exit('Server error');
}
?>