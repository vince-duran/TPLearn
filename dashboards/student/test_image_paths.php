<!DOCTYPE html>
<html>
<head>
    <title>Student Image Path Test</title>
</head>
<body>
    <h2>Testing Image Paths from Student Directory</h2>
    
    <?php
    require_once '../../includes/data-helpers.php';
    
    $programs = getProgramsWithCalculatedStatus();
    $sample6 = null;
    
    foreach ($programs as $program) {
        if ($program['name'] === 'Sample 6') {
            $sample6 = $program;
            break;
        }
    }
    
    if ($sample6 && !empty($sample6['cover_image'])) {
        $fileName = basename($sample6['cover_image']);
        echo "<h3>Sample 6 Cover Image Test</h3>";
        echo "<p><strong>Original path:</strong> " . htmlspecialchars($sample6['cover_image']) . "</p>";
        echo "<p><strong>File name:</strong> " . htmlspecialchars($fileName) . "</p>";
        
        // Test different paths
        $testPaths = [
            "../../serve_image.php?file=" . urlencode($fileName) => "../../serve_image.php (should work)",
            "../serve_image.php?file=" . urlencode($fileName) => "../serve_image.php (wrong level)",
            "serve_image.php?file=" . urlencode($fileName) => "serve_image.php (wrong level)"
        ];
        
        foreach ($testPaths as $path => $description) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
            echo "<h4>$description</h4>";
            echo "<p><strong>URL:</strong> $path</p>";
            echo "<img src='$path' alt='Sample 6' style='max-width: 200px; border: 2px solid green;' ";
            echo "onerror=\"this.style.border='2px solid red'; this.alt='FAILED';\">";
            echo "</div>";
        }
        
        // Test if serve_image.php exists
        echo "<h3>File System Check</h3>";
        $serveImagePath = '../../serve_image.php';
        echo "<p><strong>serve_image.php exists:</strong> " . (file_exists($serveImagePath) ? '✅ YES' : '❌ NO') . "</p>";
        
        if (file_exists($serveImagePath)) {
            echo "<p><strong>Absolute path:</strong> " . realpath($serveImagePath) . "</p>";
        }
        
        // Test direct image file access
        $imagePath = '../../uploads/program_covers/' . $fileName;
        echo "<p><strong>Image file exists:</strong> " . (file_exists($imagePath) ? '✅ YES' : '❌ NO') . "</p>";
        
        if (file_exists($imagePath)) {
            echo "<p><strong>Image absolute path:</strong> " . realpath($imagePath) . "</p>";
            echo "<p><strong>Image size:</strong> " . filesize($imagePath) . " bytes</p>";
        }
    } else {
        echo "<p>Sample 6 not found or has no cover image</p>";
    }
    ?>
    
    <h3>Current Working Directory Info</h3>
    <p><strong>Current script:</strong> <?= __FILE__ ?></p>
    <p><strong>Current directory:</strong> <?= __DIR__ ?></p>
    <p><strong>Document root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?? 'Not set' ?></p>
</body>
</html>