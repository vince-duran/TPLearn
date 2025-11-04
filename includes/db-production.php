<?php
// =============================
// Production Database Configuration
// =============================
// Copy this to includes/db.php after deployment and update credentials

// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Production environment - hide errors from public
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Log errors instead of displaying them
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Production database credentials - UPDATE THESE WITH YOUR HOSTING DETAILS
$host = "localhost";                    // Usually localhost for shared hosting
$user = "YOUR_DB_USERNAME";            // Database username from hosting panel
$pass = "YOUR_SECURE_PASSWORD";        // Database password from hosting panel  
$dbname = "YOUR_DATABASE_NAME";        // Database name from hosting panel

// Example for Hostinger:
// $host = "localhost";
// $user = "u123456789_tplearn";
// $pass = "SecurePassword123!";
// $dbname = "u123456789_tplearn";

// Create MySQLi connection with error handling
try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        // Log the error
        error_log("Database connection failed: " . $conn->connect_error);
        
        // Show user-friendly error
        die("Database connection error. Please try again later or contact support.");
    }
    
    // Set charset to handle special characters properly
    $conn->set_charset("utf8mb4");
    
    // Set SQL mode for better compatibility
    $conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    
} catch (Exception $e) {
    // Log the error
    error_log("Database error: " . $e->getMessage());
    
    // Show user-friendly error
    die("Database connection error. Please try again later or contact support.");
}

// Security settings for production
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.tplearn.tech',
        'secure' => true,           // HTTPS only
        'httponly' => true,         // Prevent XSS
        'samesite' => 'Strict'      // CSRF protection
    ]);
    session_start();
}

// Production security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Database helper functions for production
function executeQuery($query, $params = []) {
    global $conn;
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt;
        
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage());
        return false;
    }
}

function getLastInsertId() {
    global $conn;
    return $conn->insert_id;
}

function escapeString($string) {
    global $conn;
    return $conn->real_escape_string($string);
}

// Production constants
define('SITE_URL', 'https://tplearn.tech');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
define('LOG_PATH', $_SERVER['DOCUMENT_ROOT'] . '/logs/');
define('ENVIRONMENT', 'production');

// Create required directories if they don't exist
$dirs_to_create = [
    $_SERVER['DOCUMENT_ROOT'] . '/uploads',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/payment_receipts',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/assignments',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/program_materials',
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/program_covers',
    $_SERVER['DOCUMENT_ROOT'] . '/logs',
    $_SERVER['DOCUMENT_ROOT'] . '/cache'
];

foreach ($dirs_to_create as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Log successful connection for monitoring
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    error_log("Database connected successfully at " . date('Y-m-d H:i:s'));
}

?>