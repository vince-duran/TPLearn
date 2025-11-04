<?php
/**
 * Universal Bootstrap for TPLearn
 * Include this at the top of PHP files to handle Railway/Local compatibility
 */

// Prevent multiple includes
if (defined('TPLEARN_BOOTSTRAP_LOADED')) {
    return;
}
define('TPLEARN_BOOTSTRAP_LOADED', true);

// Detect environment
$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['PORT']) || strpos($_SERVER['HTTP_HOST'] ?? '', '.railway.app') !== false;
$isLocal = !$isRailway;

// Set up error reporting
if ($isLocal) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Define base paths
if ($isRailway) {
    define('APP_BASE', '/app');
} else {
    define('APP_BASE', dirname(__FILE__));
}

/**
 * Universal require function
 */
function tpl_require($file) {
    static $included = [];
    
    // Prevent duplicate includes
    if (isset($included[$file])) {
        return true;
    }
    
    $paths = [];
    
    // Add potential paths
    if (defined('APP_BASE')) {
        $paths[] = APP_BASE . '/' . ltrim($file, '/');
    }
    
    $paths[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . ltrim($file, '/');
    $paths[] = __DIR__ . '/' . ltrim($file, '/');
    $paths[] = $file; // Try as-is
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $included[$file] = true;
            return require_once $path;
        }
    }
    
    throw new Exception("Required file not found: $file");
}

// Set timezone
date_default_timezone_set('Asia/Manila');
?>