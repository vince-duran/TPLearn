<?php
/**
 * Check the actual structure of the programs table
 */

require_once 'includes/db.php';

echo "<h2>Programs Table Structure</h2>";

try {
    // Get table structure
    $stmt = $pdo->prepare("DESCRIBE programs");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Columns:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show Sample 6 data with correct column names
    echo "<h3>Sample 6 Program Data:</h3>";
    $stmt = $pdo->prepare("SELECT * FROM programs WHERE id = 38");
    $stmt->execute();
    $sample6 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sample6) {
        echo "<pre>";
        foreach ($sample6 as $key => $value) {
            if ($key === 'cover_image') {
                echo "$key: " . substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '') . "\n";
                echo "cover_image_length: " . strlen($value) . " characters\n";
                echo "cover_image_is_binary: " . (ctype_print($value) ? 'No' : 'Yes') . "\n";
            } else {
                echo "$key: $value\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p>No program found with ID 38</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>