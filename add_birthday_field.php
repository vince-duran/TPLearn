<?php
require_once 'includes/db.php';
echo "Adding birthday field to tutor_profiles table...\n";
$sql = "ALTER TABLE tutor_profiles ADD COLUMN birthday DATE NULL AFTER gender";
if ($conn->query($sql)) {
    echo "✓ Birthday field added successfully\n";
} else {
    echo "✗ Error adding birthday field: " . $conn->error . "\n";
}
?>