<?php
/**
 * Simple test to load Sample 6 cover image from student directory
 */

// Test the exact same logic as student enrollment page
require_once '../../includes/data-helpers.php';

echo "<h2>Direct Sample 6 Cover Image Test</h2>";

$programs = getProgramsWithCalculatedStatus();

foreach ($programs as $program) {
    if ($program['name'] === 'Sample 6') {
        echo "<h3>Found Sample 6 Program</h3>";
        echo "<p><strong>Cover Image:</strong> " . htmlspecialchars($program['cover_image'] ?? 'NULL') . "</p>";
        echo "<p><strong>Empty Check:</strong> " . var_export(empty($program['cover_image']), true) . "</p>";
        echo "<p><strong>Not Empty Check:</strong> " . var_export(!empty($program['cover_image']), true) . "</p>";
        
        if (!empty($program['cover_image'])) {
            $fileName = basename($program['cover_image']);
            $imageUrl = "../../serve_image.php?file=" . htmlspecialchars($fileName);
            
            echo "<p><strong>Base Filename:</strong> " . htmlspecialchars($fileName) . "</p>";
            echo "<p><strong>Image URL:</strong> " . htmlspecialchars($imageUrl) . "</p>";
            
            echo "<h3>Image Test</h3>";
            echo "<div style='border: 2px solid blue; padding: 20px; background: #f0f0f0;'>";
            echo "<p>If you see an image below, the path is working:</p>";
            echo "<img src='$imageUrl' alt='Sample 6 Cover' style='max-width: 300px; border: 2px solid green;' ";
            echo "onload=\"this.style.border='2px solid green'; console.log('Image loaded successfully');\" ";
            echo "onerror=\"this.style.border='2px solid red'; this.alt='FAILED TO LOAD'; console.error('Image failed to load:', this.src);\">";
            echo "</div>";
            
            // Test file existence
            echo "<h3>File System Check</h3>";
            $localImagePath = "../../uploads/program_covers/" . $fileName;
            echo "<p><strong>Local image path:</strong> $localImagePath</p>";
            echo "<p><strong>File exists:</strong> " . (file_exists($localImagePath) ? '✅ YES' : '❌ NO') . "</p>";
            
            $serveImagePath = "../../serve_image.php";
            echo "<p><strong>serve_image.php exists:</strong> " . (file_exists($serveImagePath) ? '✅ YES' : '❌ NO') . "</p>";
            
        } else {
            echo "<p style='color: red;'>❌ No cover image found for Sample 6</p>";
        }
        break;
    }
}

echo "<h3>Current Context</h3>";
echo "<p><strong>Current file:</strong> " . __FILE__ . "</p>";
echo "<p><strong>Current directory:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Relative to TPLearn root:</strong> dashboards/student/</p>";

?>

<script>
// Add JavaScript to log any console errors
window.addEventListener('error', function(e) {
    console.error('Page error:', e.error);
});

// Log when page loads
console.log('Sample 6 cover image test page loaded');
</script>