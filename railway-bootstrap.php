<?php
/**
 * Railway Bootstrap - Auto-fixes include paths for Railway deployment
 * Include this at the top of any PHP file that has include issues
 */

// Only run path fixes on Railway
if (isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['PORT']) || strpos($_SERVER['REQUEST_URI'] ?? '', '.railway.app') !== false) {
    
    // Define Railway-specific paths
    if (!defined('RAILWAY_ROOT')) {
        define('RAILWAY_ROOT', '/app');
    }
    
    // Create symlinks or adjust include path for common directories
    $commonDirs = ['includes', 'config', 'assets', 'api'];
    
    foreach ($commonDirs as $dir) {
        $railwayPath = RAILWAY_ROOT . '/' . $dir;
        $localPath = __DIR__ . '/' . $dir;
        
        // Add to include path if directory exists
        if (is_dir($railwayPath)) {
            $currentPath = get_include_path();
            if (strpos($currentPath, $railwayPath) === false) {
                set_include_path($currentPath . PATH_SEPARATOR . $railwayPath);
            }
        }
    }
    
    // Override require_once and include_once for Railway
    if (!function_exists('railway_require')) {
        function railway_require($file) {
            // Try Railway path first
            $railwayFile = RAILWAY_ROOT . '/' . ltrim($file, './');
            if (file_exists($railwayFile)) {
                return require_once $railwayFile;
            }
            
            // Try relative to current directory
            $relativeFile = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $file;
            if (file_exists($relativeFile)) {
                return require_once $relativeFile;
            }
            
            // Try original path
            return require_once $file;
        }
        
        function railway_include($file) {
            // Try Railway path first
            $railwayFile = RAILWAY_ROOT . '/' . ltrim($file, './');
            if (file_exists($railwayFile)) {
                return include_once $railwayFile;
            }
            
            // Try relative to current directory
            $relativeFile = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $file;
            if (file_exists($relativeFile)) {
                return include_once $relativeFile;
            }
            
            // Try original path
            return include_once $file;
        }
    }
}
?>