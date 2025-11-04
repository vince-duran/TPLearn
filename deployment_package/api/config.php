<?php
// API Configuration and Settings
class APIConfig
{
  // Database settings
  const DB_HOST = 'localhost';
  const DB_USERNAME = 'root';
  const DB_PASSWORD = '';
  const DB_NAME = 'tplearn';

  // API Settings
  const API_VERSION = '1.0';
  const API_BASE_URL = 'http://localhost/TPLearn/api/';

  // Pagination defaults
  const DEFAULT_PAGE_SIZE = 20;
  const MAX_PAGE_SIZE = 100;

  // File upload settings
  const MAX_FILE_SIZE = 5242880; // 5MB
  const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
  const ALLOWED_DOCUMENT_TYPES = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

  // Security settings
  const SESSION_TIMEOUT = 3600; // 1 hour
  const MAX_LOGIN_ATTEMPTS = 5;
  const LOCKOUT_DURATION = 900; // 15 minutes

  // Email settings (for notifications)
  const SMTP_HOST = 'smtp.gmail.com';
  const SMTP_PORT = 587;
  const SMTP_USERNAME = '';
  const SMTP_PASSWORD = '';
  const FROM_EMAIL = 'tplearnph@gmail.com';
  const FROM_NAME = 'TPLearn System';

  // Payment settings
  const SUPPORTED_PAYMENT_METHODS = ['cash', 'bank_transfer', 'gcash', 'paypal', 'credit_card'];
  const DEFAULT_CURRENCY = 'PHP';

  // Session settings
  const SESSION_TYPES = ['regular', 'makeup', 'assessment', 'consultation'];
  const SESSION_MODES = ['virtual', 'in_person', 'hybrid'];
  const SESSION_DURATIONS = [30, 45, 60, 90, 120]; // minutes

  // User roles and permissions
  const USER_ROLES = ['admin', 'tutor', 'student'];
  const ROLE_PERMISSIONS = [
    'admin' => [
      'users.create',
      'users.read',
      'users.update',
      'users.delete',
      'programs.create',
      'programs.read',
      'programs.update',
      'programs.delete',
      'enrollments.create',
      'enrollments.read',
      'enrollments.update',
      'enrollments.delete',
      'payments.create',
      'payments.read',
      'payments.update',
      'payments.delete',
      'payments.validate',
      'sessions.create',
      'sessions.read',
      'sessions.update',
      'sessions.delete',
      'reports.read',
      'reports.export'
    ],
    'tutor' => [
      'programs.read',
      'enrollments.read',
      'enrollments.update',
      'sessions.create',
      'sessions.read',
      'sessions.update',
      'reports.read_own'
    ],
    'student' => [
      'programs.read',
      'enrollments.create',
      'enrollments.read_own',
      'payments.create',
      'payments.read_own',
      'sessions.read_own'
    ]
  ];

  // Status constants
  const USER_STATUSES = ['active', 'inactive', 'suspended'];
  const PROGRAM_STATUSES = ['active', 'inactive', 'draft'];
  const ENROLLMENT_STATUSES = ['pending', 'active', 'completed', 'cancelled', 'suspended'];
  const PAYMENT_STATUSES = ['pending', 'validated', 'rejected', 'refunded'];
  const SESSION_STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled', 'rescheduled'];

  // Response messages
  const MESSAGES = [
    'success' => [
      'created' => 'Record created successfully',
      'updated' => 'Record updated successfully',
      'deleted' => 'Record deleted successfully',
      'retrieved' => 'Data retrieved successfully'
    ],
    'error' => [
      'not_found' => 'Record not found',
      'unauthorized' => 'Unauthorized access',
      'validation_failed' => 'Validation failed',
      'server_error' => 'Internal server error',
      'invalid_request' => 'Invalid request'
    ]
  ];

  public static function hasPermission($role, $permission)
  {
    return in_array($permission, self::ROLE_PERMISSIONS[$role] ?? []);
  }

  public static function validateStatus($type, $status)
  {
    $validStatuses = [];

    switch ($type) {
      case 'user':
        $validStatuses = self::USER_STATUSES;
        break;
      case 'program':
        $validStatuses = self::PROGRAM_STATUSES;
        break;
      case 'enrollment':
        $validStatuses = self::ENROLLMENT_STATUSES;
        break;
      case 'payment':
        $validStatuses = self::PAYMENT_STATUSES;
        break;
      case 'session':
        $validStatuses = self::SESSION_STATUSES;
        break;
    }

    return in_array($status, $validStatuses);
  }

  public static function sanitizeInput($input)
  {
    if (is_array($input)) {
      return array_map([self::class, 'sanitizeInput'], $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
  }

  public static function validateEmail($email)
  {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
  }

  public static function validatePhone($phone)
  {
    // Philippine phone number validation
    return preg_match('/^(\+63|0)?[9]\d{9}$/', $phone);
  }

  public static function formatResponse($success, $data = null, $message = '', $code = 200)
  {
    http_response_code($code);

    $response = [
      'success' => $success,
      'timestamp' => date('Y-m-d H:i:s'),
      'api_version' => self::API_VERSION
    ];

    if ($success) {
      if ($data !== null) {
        $response['data'] = $data;
      }
      if ($message) {
        $response['message'] = $message;
      }
    } else {
      $response['error'] = $message ?: 'An error occurred';
    }

    return json_encode($response);
  }

  public static function logActivity($userId, $action, $details = '')
  {
    global $db;

    try {
      $db->insert(
        "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())",
        [
          $userId,
          $action,
          $details,
          $_SERVER['REMOTE_ADDR'] ?? '',
          $_SERVER['HTTP_USER_AGENT'] ?? ''
        ],
        "issss"
      );
    } catch (Exception $e) {
      // Log to file if database logging fails
      error_log("Activity log failed: " . $e->getMessage());
    }
  }
}

// Utility functions
function validateRequired($data, $required)
{
  $missing = [];
  foreach ($required as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
      $missing[] = $field;
    }
  }

  if (!empty($missing)) {
    throw new Exception('Missing required fields: ' . implode(', ', $missing));
  }
}

function paginateResults($query, $params, $types, $page = 1, $pageSize = null)
{
  global $db;

  $pageSize = $pageSize ?: APIConfig::DEFAULT_PAGE_SIZE;
  $pageSize = min($pageSize, APIConfig::MAX_PAGE_SIZE);
  $offset = ($page - 1) * $pageSize;

  // Get total count
  $countQuery = preg_replace('/SELECT.*?FROM/i', 'SELECT COUNT(*) as total FROM', $query);
  $countQuery = preg_replace('/ORDER BY.*$/i', '', $countQuery);

  $totalResult = $db->getRow($countQuery, $params, $types);
  $total = $totalResult['total'];

  // Get paginated results
  $query .= " LIMIT $offset, $pageSize";
  $results = $db->getRows($query, $params, $types);

  return [
    'data' => $results,
    'pagination' => [
      'current_page' => $page,
      'page_size' => $pageSize,
      'total_items' => $total,
      'total_pages' => ceil($total / $pageSize),
      'has_next' => $page < ceil($total / $pageSize),
      'has_prev' => $page > 1
    ]
  ];
}

function generateToken($length = 32)
{
  return bin2hex(random_bytes($length));
}

function validateImageFile($file)
{
  if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new Exception('File upload error');
  }

  if ($file['size'] > APIConfig::MAX_FILE_SIZE) {
    throw new Exception('File size exceeds maximum allowed size');
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mimeType, APIConfig::ALLOWED_IMAGE_TYPES)) {
    throw new Exception('Invalid file type');
  }

  return true;
}

// CORS Headers
function setCORSHeaders()
{
  header("Access-Control-Allow-Origin: http://localhost:3000"); // Adjust for your frontend URL
  header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
  header("Access-Control-Allow-Credentials: true");

  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
  }
}

// Rate limiting (simple implementation)
function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600)
{
  $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($identifier);

  if (file_exists($cacheFile)) {
    $data = json_decode(file_get_contents($cacheFile), true);
    $currentTime = time();

    if ($currentTime - $data['timestamp'] < $timeWindow) {
      if ($data['requests'] >= $maxRequests) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
      }
      $data['requests']++;
    } else {
      $data = ['timestamp' => $currentTime, 'requests' => 1];
    }
  } else {
    $data = ['timestamp' => time(), 'requests' => 1];
  }

  file_put_contents($cacheFile, json_encode($data));
}
