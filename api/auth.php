<?php
// API for authentication and session management
session_start();
require_once '../includes/db.php';
require_once 'config.php';

header('Content-Type: application/json');
setCORSHeaders();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

class AuthManager
{
  private $db;

  public function __construct($database)
  {
    $this->db = $database;
  }

  public function login($username, $password)
  {
    // Check for rate limiting
    $this->checkLoginAttempts($username);

    // Get user by username or email
    $user = $this->db->getRow(
      "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'",
      [$username, $username],
      "ss"
    );

    if (!$user || !password_verify($password, $user['password'])) {
      $this->recordFailedAttempt($username);
      throw new Exception('Invalid username/email or password');
    }

    // Clear failed attempts on successful login
    $this->clearFailedAttempts($username);

    // Update last login
    $this->db->query(
      "UPDATE users SET last_login = NOW() WHERE id = ?",
      [$user['id']],
      "i"
    );

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();

    // Log activity
    APIConfig::logActivity($user['id'], 'login', 'User logged in');

    return [
      'id' => $user['id'],
      'username' => $user['username'],
      'email' => $user['email'],
      'role' => $user['role'],
      'status' => $user['status']
    ];
  }

  public function logout()
  {
    if (isset($_SESSION['user_id'])) {
      APIConfig::logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }

    session_destroy();
    return true;
  }

  public function checkSession()
  {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
      return false;
    }

    // Check session timeout
    if (time() - $_SESSION['login_time'] > APIConfig::SESSION_TIMEOUT) {
      $this->logout();
      return false;
    }

    // Verify user still exists and is active
    $user = $this->db->getRow(
      "SELECT * FROM users WHERE id = ? AND status = 'active'",
      [$_SESSION['user_id']],
      "i"
    );

    if (!$user) {
      $this->logout();
      return false;
    }

    return [
      'id' => $user['id'],
      'username' => $user['username'],
      'email' => $user['email'],
      'role' => $user['role'],
      'status' => $user['status'],
      'session_time_remaining' => APIConfig::SESSION_TIMEOUT - (time() - $_SESSION['login_time'])
    ];
  }

  public function changePassword($userId, $currentPassword, $newPassword)
  {
    $user = $this->db->getRow(
      "SELECT * FROM users WHERE id = ?",
      [$userId],
      "i"
    );

    if (!$user || !password_verify($currentPassword, $user['password'])) {
      throw new Exception('Current password is incorrect');
    }

    if (strlen($newPassword) < 8) {
      throw new Exception('New password must be at least 8 characters long');
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $this->db->query(
      "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
      [$hashedPassword, $userId],
      "si"
    );

    APIConfig::logActivity($userId, 'password_change', 'User changed password');

    return true;
  }

  public function requestPasswordReset($email)
  {
    $user = $this->db->getRow(
      "SELECT * FROM users WHERE email = ? AND status = 'active'",
      [$email],
      "s"
    );

    if (!$user) {
      // Don't reveal if email exists or not
      return true;
    }

    $token = generateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store reset token (you might want to create a password_resets table)
    $this->db->query(
      "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()",
      [$user['id'], $token, $expires],
      "iss"
    );

    // In a real application, you would send an email here
    // For now, we'll just log it
    APIConfig::logActivity($user['id'], 'password_reset_request', 'Password reset requested');

    return true;
  }

  public function resetPassword($token, $newPassword)
  {
    $reset = $this->db->getRow(
      "SELECT pr.*, u.id as user_id FROM password_resets pr 
             JOIN users u ON pr.user_id = u.id 
             WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL",
      [$token],
      "s"
    );

    if (!$reset) {
      throw new Exception('Invalid or expired reset token');
    }

    if (strlen($newPassword) < 8) {
      throw new Exception('Password must be at least 8 characters long');
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $this->db->query(
      "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
      [$hashedPassword, $reset['user_id']],
      "si"
    );

    // Mark reset token as used
    $this->db->query(
      "UPDATE password_resets SET used_at = NOW() WHERE token = ?",
      [$token],
      "s"
    );

    APIConfig::logActivity($reset['user_id'], 'password_reset', 'Password reset completed');

    return true;
  }

  private function checkLoginAttempts($username)
  {
    $attempts = $this->db->getRow(
      "SELECT * FROM login_attempts WHERE username = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
      [$username, APIConfig::LOCKOUT_DURATION],
      "si"
    );

    if ($attempts && $attempts['attempts'] >= APIConfig::MAX_LOGIN_ATTEMPTS) {
      throw new Exception('Account temporarily locked due to too many failed login attempts. Please try again later.');
    }
  }

  private function recordFailedAttempt($username)
  {
    $this->db->query(
      "INSERT INTO login_attempts (username, attempts, created_at) VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, created_at = NOW()",
      [$username],
      "s"
    );
  }

  private function clearFailedAttempts($username)
  {
    $this->db->query(
      "DELETE FROM login_attempts WHERE username = ?",
      [$username],
      "s"
    );
  }
}

try {
  $authManager = new AuthManager($db);

  switch ($action) {
    case 'login':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $username = $_POST['username'] ?? '';
      $password = $_POST['password'] ?? '';

      if (!$username || !$password) {
        throw new Exception('Username and password are required');
      }

      $user = $authManager->login($username, $password);

      echo APIConfig::formatResponse(true, $user, 'Login successful');
      break;

    case 'logout':
      $authManager->logout();
      echo APIConfig::formatResponse(true, null, 'Logout successful');
      break;

    case 'check_session':
      $session = $authManager->checkSession();

      if ($session) {
        echo APIConfig::formatResponse(true, $session, 'Session valid');
      } else {
        http_response_code(401);
        echo APIConfig::formatResponse(false, null, 'Session invalid or expired', 401);
      }
      break;

    case 'change_password':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
      }

      $currentPassword = $_POST['current_password'] ?? '';
      $newPassword = $_POST['new_password'] ?? '';
      $confirmPassword = $_POST['confirm_password'] ?? '';

      if (!$currentPassword || !$newPassword || !$confirmPassword) {
        throw new Exception('All password fields are required');
      }

      if ($newPassword !== $confirmPassword) {
        throw new Exception('New passwords do not match');
      }

      $authManager->changePassword($_SESSION['user_id'], $currentPassword, $newPassword);

      echo APIConfig::formatResponse(true, null, 'Password changed successfully');
      break;

    case 'request_password_reset':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $email = $_POST['email'] ?? '';

      if (!$email || !APIConfig::validateEmail($email)) {
        throw new Exception('Valid email address is required');
      }

      $authManager->requestPasswordReset($email);

      echo APIConfig::formatResponse(true, null, 'If the email exists, a reset link has been sent');
      break;

    case 'reset_password':
      if ($method !== 'POST') {
        throw new Exception('POST method required');
      }

      $token = $_POST['token'] ?? '';
      $newPassword = $_POST['new_password'] ?? '';
      $confirmPassword = $_POST['confirm_password'] ?? '';

      if (!$token || !$newPassword || !$confirmPassword) {
        throw new Exception('All fields are required');
      }

      if ($newPassword !== $confirmPassword) {
        throw new Exception('Passwords do not match');
      }

      $authManager->resetPassword($token, $newPassword);

      echo APIConfig::formatResponse(true, null, 'Password reset successfully');
      break;

    case 'get_permissions':
      if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
      }

      $role = $_SESSION['role'];
      $permissions = APIConfig::ROLE_PERMISSIONS[$role] ?? [];

      echo APIConfig::formatResponse(true, ['permissions' => $permissions]);
      break;

    case 'extend_session':
      if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
      }

      $_SESSION['login_time'] = time();

      echo APIConfig::formatResponse(true, ['new_expiry' => time() + APIConfig::SESSION_TIMEOUT], 'Session extended');
      break;

    default:
      throw new Exception('Invalid action');
  }
} catch (Exception $e) {
  $code = ($e->getMessage() === 'User not logged in' ||
    $e->getMessage() === 'Session invalid or expired') ? 401 : 400;

  echo APIConfig::formatResponse(false, null, $e->getMessage(), $code);
}
