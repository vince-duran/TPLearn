<?php
// Only start session if not already started and not in CLI mode
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
  session_start();
}

function isLoggedIn()
{
  return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

function isAuthenticated()
{
  return isLoggedIn();
}

function hasRole($role)
{
  return isLoggedIn() && $_SESSION['role'] === $role;
}

function getCurrentUserId()
{
  return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole()
{
  return $_SESSION['role'] ?? null;
}

function requireRole($role)
{
  if (!isLoggedIn() || $_SESSION['role'] !== $role) {
    // Skip redirect if running from command line
    if (php_sapi_name() === 'cli') {
      throw new Exception("Access denied: Required role '$role', current role: " . ($_SESSION['role'] ?? 'none'));
    }

    // Determine the path to login.php based on current directory structure
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';

    // If we're in a subdirectory like /admin/, /student/, or /tutor/
    if (
      strpos($currentPath, '/dashboards/admin/') !== false ||
      strpos($currentPath, '/dashboards/student/') !== false ||
      strpos($currentPath, '/dashboards/tutor/') !== false
    ) {
      header('Location: ../../login.php');
    } else {
      // If we're in the root dashboards directory
      header('Location: ../login.php');
    }
    exit();
  }
}

function redirectToDashboard($role)
{
  switch ($role) {
    case 'admin':
      header('Location: dashboards/admin/admin.php');
      break;
    case 'tutor':
      header('Location: dashboards/tutor/tutor.php');
      break;
    case 'student':
      header('Location: dashboards/student/student.php');
      break;
    default:
      header('Location: login.php');
      break;
  }
  exit();
}
