<?php
/**
 * Railway Path Configuration
 * Automatically detects and configures file paths for Railway deployment
 */

// Detect if running on Railway
$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['RAILWAY_PUBLIC_DOMAIN']) || isset($_ENV['PORT']);

if ($isRailway) {
    // Railway deployment paths
    define('APP_ROOT', '/app');
    define('INCLUDES_PATH', '/app/includes');
    define('CONFIG_PATH', '/app/config');
    define('ASSETS_PATH', '/app/assets');
    define('API_PATH', '/app/api');
} else {
    // Local development paths
    define('APP_ROOT', __DIR__);
    define('INCLUDES_PATH', __DIR__ . '/includes');
    define('CONFIG_PATH', __DIR__ . '/config');
    define('ASSETS_PATH', __DIR__ . '/assets');
    define('API_PATH', __DIR__ . '/api');
}

/**
 * Safe include function that works on both Railway and local environments
 */
function safe_include($relativePath) {
    $fullPath = APP_ROOT . '/' . ltrim($relativePath, '/');
    
    if (file_exists($fullPath)) {
        return include_once $fullPath;
    } else {
        // Fallback to relative path for local development
        $localPath = __DIR__ . '/' . ltrim($relativePath, '/');
        if (file_exists($localPath)) {
            return include_once $localPath;
        } else {
            throw new Exception("Required file not found: $relativePath (tried: $fullPath, $localPath)");
        }
    }
}

/**
 * Safe require function that works on both Railway and local environments
 */
function safe_require($relativePath) {
    $fullPath = APP_ROOT . '/' . ltrim($relativePath, '/');
    
    if (file_exists($fullPath)) {
        return require_once $fullPath;
    } else {
        // Fallback to relative path for local development
        $localPath = __DIR__ . '/' . ltrim($relativePath, '/');
        if (file_exists($localPath)) {
            return require_once $localPath;
        } else {
            throw new Exception("Required file not found: $relativePath (tried: $fullPath, $localPath)");
        }
    }
}
?>