<?php
// (Dev only) show errors; comment in prod
// ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// Start buffering to prevent any accidental output from breaking redirects
ob_start();

session_start();
require_once __DIR__ . '/includes/db.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
  try {
    $logSql = "INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'logout', 'User logged out successfully')";
    $logStmt = $conn->prepare($logSql);
    $logStmt->bind_param("i", $_SESSION['user_id']);
    $logStmt->execute();
    $logStmt->close();
  } catch (Exception $e) {
    // Log error but don't stop logout process
    error_log("Logout logging error: " . $e->getMessage());
  }
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}

// Destroy the session
session_destroy();

// Clear output buffer
ob_end_clean();

// Redirect to login page with a logout message
header("Location: login.php?logout=success");
exit;
?>