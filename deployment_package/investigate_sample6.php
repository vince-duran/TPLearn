<?php
// Check what happened to Sample 6 program
require_once 'includes/db.php';

try {
    echo "=== INVESTIGATING SAMPLE 6 PROGRAM ===\n\n";
    
    // Check if Sample 6 still exists
    $stmt = $conn->prepare("SELECT * FROM programs WHERE name LIKE '%Sample 6%' OR id = 38");
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "Programs matching 'Sample 6' or ID 38:\n";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: {$row['id']}\n";
            echo "Name: {$row['name']}\n";
            echo "Status: {$row['status']}\n";
            echo "Cover Image: " . ($row['cover_image'] ?? 'NULL') . "\n";
            echo "Created: {$row['created_at']}\n";
            echo "Updated: {$row['updated_at']}\n";
            echo "---\n";
        }
    } else {
        echo "❌ No programs found matching Sample 6 or ID 38\n";
    }
    
    // Check recent programs
    echo "\nRecent programs (last 10):\n";
    $stmt = $conn->prepare("SELECT id, name, status, created_at, updated_at FROM programs ORDER BY updated_at DESC LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Name: {$row['name']}, Status: {$row['status']}, Updated: {$row['updated_at']}\n";
    }
    
    // Check if there are any programs with cover images
    echo "\nPrograms with cover images:\n";
    $stmt = $conn->prepare("SELECT id, name, cover_image FROM programs WHERE cover_image IS NOT NULL AND cover_image != ''");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: {$row['id']}, Name: {$row['name']}, Cover: {$row['cover_image']}\n";
        }
    } else {
        echo "No programs with cover images found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>