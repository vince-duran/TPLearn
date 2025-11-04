<?php
// =============================
// Updated Database Connection with Domain Support
// =============================

// Load domain configuration
require_once __DIR__ . '/../config/domain-config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// SSL Redirect if required
if (FORCE_SSL && !isset($_SERVER['HTTPS'])) {
    $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirect_url", true, 301);
    exit();
}

// Database connection using domain config
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

// Create MySQLi connection
try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        if (ENVIRONMENT === 'development') {
            die("Database connection failed: " . $conn->connect_error);
        } else {
            error_log("Database connection failed: " . $conn->connect_error);
            die("Database connection error. Please try again later.");
        }
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
    // Set session security
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => DOMAIN_NAME,
            'secure' => SESSION_SECURE,
            'httponly' => SESSION_HTTPONLY,
            'samesite' => SESSION_SAMESITE
        ]);
        session_start();
    }
    
} catch (Exception $e) {
    if (ENVIRONMENT === 'development') {
        die("Database error: " . $e->getMessage());
    } else {
        error_log("Database error: " . $e->getMessage());
        die("System temporarily unavailable. Please try again later.");
    }
}

// Database helper functions
function executeQuery($query, $params = []) {
    global $conn;
    try {
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $types = str_repeat('s', count($params)); // Assume all strings, adjust as needed
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

// Log database connections for monitoring
if (LOG_LEVEL === 'DEBUG') {
    error_log("Database connected successfully to " . $dbname . " at " . date('Y-m-d H:i:s'));
}

?>