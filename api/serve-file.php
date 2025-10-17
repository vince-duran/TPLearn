<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is authenticated
if (!isLoggedIn()) {
  http_response_code(401);
  exit('Unauthorized');
}

$file_id = $_GET['id'] ?? null;

if (!$file_id) {
  http_response_code(400);
  exit('File ID is required');
}

try {
  $db = new Database($conn);

  // Get file information
  $file = $db->getRow(
    "SELECT * FROM file_uploads WHERE id = ?",
    [$file_id],
    "i"
  );

  if (!$file) {
    http_response_code(404);
    exit('File not found');
  }

  // Check permissions based on user role and file type
  if ($_SESSION['role'] === 'student') {
    // For payment proofs, check if student owns the related payment
    if ($file['upload_purpose'] === 'payment_proof') {
      $userManager = new UserManager($conn);
      $currentUser = $userManager->getUserByLogin($_SESSION['username']);

      $payment = $db->getRow(
        "SELECT p.id, e.student_user_id 
         FROM payments p
         JOIN enrollments e ON p.enrollment_id = e.id
         WHERE p.id = ?",
        [$file['related_id']],
        "i"
      );

      if (!$payment || $payment['student_user_id'] != $currentUser['id']) {
        http_response_code(403);
        exit('Access denied');
      }
    } else {
      // For other files, check if it belongs to current user
      $userManager = new UserManager($conn);
      $currentUser = $userManager->getUserByLogin($_SESSION['username']);

      if ($file['user_id'] != $currentUser['id']) {
        http_response_code(403);
        exit('Access denied');
      }
    }
  }
  // Admins and tutors can view any file

  // Verify file exists on filesystem
  $full_path = __DIR__ . '/../' . $file['file_path'];

  if (!file_exists($full_path)) {
    http_response_code(404);
    exit('File not found on server');
  }

  // Set appropriate headers
  header('Content-Type: ' . $file['mime_type']);
  header('Content-Length: ' . filesize($full_path));
  header('Content-Disposition: inline; filename="' . $file['original_name'] . '"');

  // Cache headers for better performance
  header('Cache-Control: private, max-age=3600');
  header('ETag: "' . md5_file($full_path) . '"');

  // Output the file
  readfile($full_path);
} catch (Exception $e) {
  error_log("File serving error: " . $e->getMessage());
  http_response_code(500);
  exit('Server error');
}
