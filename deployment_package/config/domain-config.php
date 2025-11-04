<?php
// =============================
// Domain Configuration for tplearn.tech
// =============================

// Domain Settings
define('DOMAIN_NAME', 'tplearn.tech');
define('BASE_URL', 'https://tplearn.tech');
define('APP_URL', 'https://app.tplearn.tech');
define('API_URL', 'https://api.tplearn.tech');

// SSL Configuration
define('FORCE_SSL', true);
define('SSL_REDIRECT', true);

// Environment Settings
define('ENVIRONMENT', 'production'); // Change to 'development' for local testing
define('DEBUG_MODE', false); // Set to true only for debugging

// Email Configuration for Domain
define('SMTP_HOST', 'smtp.gmail.com'); // Update with your email provider
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@tplearn.tech'); // Your domain email
define('SMTP_PASSWORD', ''); // Add your email password
define('FROM_EMAIL', 'noreply@tplearn.tech');
define('FROM_NAME', 'TPLearn System');

// Database Configuration for Production
if (ENVIRONMENT === 'production') {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'tplearn_user'); // Create dedicated database user
    define('DB_PASS', ''); // Add secure password
    define('DB_NAME', 'tplearn_prod');
} else {
    // Development settings (your current XAMPP setup)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'tplearn');
}

// Security Settings
define('SESSION_SECURE', FORCE_SSL);
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// File Upload Paths
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// API Rate Limiting
define('API_RATE_LIMIT', 100); // requests per minute
define('API_RATE_WINDOW', 60); // seconds

// Cache Settings
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600); // 1 hour

// Backup Settings
define('BACKUP_PATH', $_SERVER['DOCUMENT_ROOT'] . '/backups/');
define('AUTO_BACKUP', true);
define('BACKUP_RETENTION_DAYS', 30);

// Logging
define('LOG_PATH', $_SERVER['DOCUMENT_ROOT'] . '/logs/');
define('LOG_LEVEL', 'ERROR'); // DEBUG, INFO, WARNING, ERROR

// Feature Flags
define('ENABLE_REGISTRATION', true);
define('ENABLE_EMAIL_VERIFICATION', true);
define('ENABLE_TWO_FACTOR', false);
define('ENABLE_SOCIAL_LOGIN', false);

// Version Information
define('APP_VERSION', '2.0.0');
define('APP_BUILD', date('YmdHis'));

// Timezone
date_default_timezone_set('Asia/Manila');

?>