<?php
require_once 'includes/db.php';
$result = $conn->query('DESCRIBE tutor_profiles');
echo "Tutor profiles table structure:\n";
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']}: {$row['Type']}\n";
}
?>