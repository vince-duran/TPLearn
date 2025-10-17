<?php
// Simple test to check if API can receive POST data
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Test endpoint working',
    'post_data' => $_POST,
    'files_data' => $_FILES,
    'session_data' => $_SESSION ?? 'No session'
]);
?>