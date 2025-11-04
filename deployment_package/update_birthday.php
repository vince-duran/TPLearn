<?php
require_once 'includes/db.php';

echo "Updating existing tutor profile with birthday...\n";

$user_id = 55; // Current tutor
$birthday = '1995-01-15'; // Sample birthday

$sql = "UPDATE tutor_profiles SET birthday = ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $birthday, $user_id);

if ($stmt->execute()) {
    echo "✓ Birthday updated successfully\n";
    
    // Verify the update
    $verify_sql = "SELECT first_name, last_name, gender, birthday, suffix, contact_number FROM tutor_profiles WHERE user_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param('i', $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo "\nUpdated profile data:\n";
        foreach ($data as $key => $value) {
            echo "  - $key: " . ($value ?: 'NULL') . "\n";
        }
    }
} else {
    echo "✗ Failed to update birthday: " . $stmt->error . "\n";
}
?>