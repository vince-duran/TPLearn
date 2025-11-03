<?php
// Fix corrupted cover image data again and check the multipart parsing issue
require_once 'includes/db.php';

try {
    echo "Fixing corrupted cover image data for Sample 6 and other programs...\n";
    
    // Fix Sample 6 (ID: 38)
    $correctPath = 'uploads/program_covers/cover_68ff8f0ca38b7_1761578764.png';
    $stmt = $conn->prepare("UPDATE programs SET cover_image = ? WHERE id = 38");
    $stmt->bind_param('s', $correctPath);
    
    if ($stmt->execute()) {
        echo "✓ Fixed Sample 6 cover image\n";
    }
    
    // Check and fix any other corrupted entries
    $stmt = $conn->prepare("SELECT id, name, cover_image FROM programs WHERE cover_image IS NOT NULL AND cover_image LIKE '%PNG%'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (strpos($row['cover_image'], 'PNG') === 1) { // Binary data starts with \x89PNG
            echo "Found corrupted entry for ID {$row['id']}: {$row['name']}\n";
            
            // Set to NULL for now since we don't know the original filename
            $stmt2 = $conn->prepare("UPDATE programs SET cover_image = NULL WHERE id = ?");
            $stmt2->bind_param('i', $row['id']);
            if ($stmt2->execute()) {
                echo "✓ Cleared corrupted data for ID {$row['id']}\n";
            }
        }
    }
    
    // Verify Sample 6 is fixed
    $stmt = $conn->prepare("SELECT id, name, cover_image FROM programs WHERE id = 38");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo "\nSample 6 status:\n";
    echo "ID: {$row['id']}\n";
    echo "Name: {$row['name']}\n";
    echo "Cover Image: {$row['cover_image']}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>