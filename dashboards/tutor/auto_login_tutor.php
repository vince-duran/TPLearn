<?php
session_start();
require_once '../../includes/db.php';

// Auto-login as the tutor TPT2025-693 (ID 8)
$_SESSION['user_id'] = 8;
$_SESSION['role'] = 'tutor';

echo "<!DOCTYPE html>";
echo "<html><head><title>Auto Login Tutor</title></head><body>";
echo "<h1>Auto Login Successful</h1>";
echo "<p>Logged in as Tutor ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>Role: " . $_SESSION['role'] . "</p>";

// Check tutor's programs
$stmt = $conn->prepare("SELECT id, name FROM programs WHERE tutor_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$programs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h2>Your Programs:</h2>";
foreach ($programs as $program) {
    echo "<p><a href='tutor-program-stream.php?program_id={$program['id']}&program=" . urlencode($program['name']) . "'>{$program['name']} (ID: {$program['id']})</a></p>";
}

echo "</body></html>";
?>