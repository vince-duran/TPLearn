<?php
/**
 * Security Configuration for Public Access
 * Add this to your pages for basic protection
 */

// Start session for security
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers for public deployment
function addSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy (adjust as needed)
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https:; media-src 'self' blob:; connect-src 'self' wss: ws: https:;");
}

// Rate limiting (basic implementation)
function checkRateLimit($identifier, $max_requests = 100, $window = 3600) {
    $cache_file = sys_get_temp_dir() . '/rate_limit_' . md5($identifier);
    
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        
        if ($data['window_start'] > time() - $window) {
            if ($data['requests'] >= $max_requests) {
                return false; // Rate limit exceeded
            }
            $data['requests']++;
        } else {
            // Reset window
            $data = ['window_start' => time(), 'requests' => 1];
        }
    } else {
        $data = ['window_start' => time(), 'requests' => 1];
    }
    
    file_put_contents($cache_file, json_encode($data));
    return true;
}

// Input sanitization
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Basic authentication check
function requireAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
}

// Session validation
function validateSession($session_id, $user_id) {
    // Add your session validation logic here
    // This should check if the user has permission to access this session
    return true; // Placeholder - implement proper validation
}

// Environment-based configuration
function isProduction() {
    return isset($_SERVER['HTTPS']) || 
           strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false ||
           strpos($_SERVER['HTTP_HOST'], 'heroku') !== false;
}

// Apply security measures for public deployment
if (isProduction()) {
    addSecurityHeaders();
    
    // Check rate limiting for public access
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit($client_ip)) {
        http_response_code(429);
        echo 'Rate limit exceeded. Please try again later.';
        exit;
    }
}

// Log access for monitoring
function logAccess($action, $details = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'action' => $action,
        'details' => $details
    ];
    
    $log_file = __DIR__ . '/logs/access.log';
    
    // Create logs directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}
?>