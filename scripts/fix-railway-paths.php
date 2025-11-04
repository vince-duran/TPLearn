#!/usr/bin/env php
<?php
/**
 * Railway Path Fixer
 * Updates all PHP files to use Railway-compatible include paths
 */

echo "🔧 Fixing Railway include paths...\n";

$projectRoot = dirname(__FILE__);
$filesToFix = [];

// Find all PHP files that need fixing
$phpFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($phpFiles as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Check if file has problematic include patterns
        if (preg_match('/require_once\s+__DIR__\s*\.\s*[\'"]\//', $content) ||
            preg_match('/include_once\s+__DIR__\s*\.\s*[\'"]\//', $content)) {
            $filesToFix[] = $file->getPathname();
        }
    }
}

echo "Found " . count($filesToFix) . " files to fix:\n";

foreach ($filesToFix as $file) {
    $relativePath = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $file);
    echo "  - $relativePath\n";
    
    $content = file_get_contents($file);
    
    // Add Railway path configuration at the top if not already present
    if (!strpos($content, 'railway-paths.php') && !strpos($content, 'safe_require')) {
        // Find the first require/include statement
        $lines = explode("\n", $content);
        $insertIndex = -1;
        
        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/^\s*(require|include)/', trim($lines[$i]))) {
                $insertIndex = $i;
                break;
            }
        }
        
        if ($insertIndex > -1) {
            // Insert Railway configuration before first include
            array_splice($lines, $insertIndex, 0, [
                '',
                '// Include Railway path configuration for deployment compatibility',
                'if (file_exists(__DIR__ . \'/config/railway-paths.php\')) {',
                '    require_once __DIR__ . \'/config/railway-paths.php\';',
                '} elseif (file_exists(dirname(__DIR__) . \'/config/railway-paths.php\')) {',
                '    require_once dirname(__DIR__) . \'/config/railway-paths.php\';',
                '}',
                ''
            ]);
            
            $content = implode("\n", $lines);
        }
    }
    
    // Replace problematic include patterns
    $patterns = [
        // Fix __DIR__ . '/path' patterns
        '/require_once\s+__DIR__\s*\.\s*([\'"])\/([^\'"]*)\\1/' => 'require_once (function_exists(\'safe_require\') ? safe_require(\'$2\') : __DIR__ . \'/$2\')',
        '/include_once\s+__DIR__\s*\.\s*([\'"])\/([^\'"]*)\\1/' => 'include_once (function_exists(\'safe_include\') ? safe_include(\'$2\') : __DIR__ . \'/$2\')',
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    file_put_contents($file, $content);
}

echo "✅ Fixed all files for Railway deployment!\n";
echo "🚀 Files are now compatible with both local and Railway environments.\n";
?>