<?php
/**
 * Debug script to test cover image loading on student side
 */

require_once 'includes/data-helpers.php';

echo "<h2>Student Cover Image Debug</h2>";

// Get programs data the same way student enrollment does
$programs = getProgramsWithCalculatedStatus();

echo "<h3>Debug Information for Student Page</h3>";

foreach ($programs as $program) {
    if ($program['name'] === 'Sample 6' || !empty($program['cover_image'])) {
        echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4>Program: " . htmlspecialchars($program['name']) . "</h4>";
        echo "<p><strong>Cover Image Field:</strong> " . htmlspecialchars($program['cover_image'] ?? 'NULL') . "</p>";
        
        if (!empty($program['cover_image'])) {
            $fileName = basename($program['cover_image']);
            echo "<p><strong>File Name:</strong> " . htmlspecialchars($fileName) . "</p>";
            
            // Test different path variations
            $paths = [
                "../../serve_image.php?file=" . urlencode($fileName),
                "../serve_image.php?file=" . urlencode($fileName),
                "serve_image.php?file=" . urlencode($fileName)
            ];
            
            foreach ($paths as $path) {
                echo "<p><strong>Testing path:</strong> $path</p>";
                echo "<img src='$path' alt='Test' style='max-width: 100px; max-height: 100px; border: 1px solid #ccc;' ";
                echo "onerror=\"this.style.border='2px solid red'; this.alt='FAILED: $path';\">";
                echo "<br><br>";
            }
            
            // Also test direct file access
            $directPath = 'uploads/program_covers/' . $fileName;
            if (file_exists($directPath)) {
                echo "<p><strong>Direct file exists:</strong> ✅ $directPath</p>";
                echo "<p><strong>File size:</strong> " . filesize($directPath) . " bytes</p>";
            } else {
                echo "<p><strong>Direct file:</strong> ❌ $directPath NOT FOUND</p>";
            }
        }
        echo "</div>";
    }
}

// Test the exact condition used in student enrollment
echo "<h3>Testing Condition Logic</h3>";
foreach ($programs as $program) {
    if ($program['name'] === 'Sample 6') {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
        echo "<p><strong>Sample 6 Program Data:</strong></p>";
        echo "<pre>";
        echo "cover_image: " . var_export($program['cover_image'], true) . "\n";
        echo "empty check: " . var_export(empty($program['cover_image']), true) . "\n";
        echo "!empty check: " . var_export(!empty($program['cover_image']), true) . "\n";
        echo "</pre>";
        echo "</div>";
        break;
    }
}
?>