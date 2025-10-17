<?php
// Simple connection test endpoint
// This endpoint is used to test internet connectivity during pre-session checks

// Set headers to allow connection testing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: HEAD, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple response to confirm connection
$response = [
    'status' => 'success',
    'message' => 'Connection test successful',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_time' => time()
];

// Return minimal response for HEAD requests (used in connection test)
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    exit();
}

// Return JSON response for GET requests
echo json_encode($response);
?>