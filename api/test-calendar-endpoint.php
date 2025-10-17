<?php
require_once '../includes/config.php';

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Simple test endpoint without authentication to check data retrieval
echo json_encode([
    'success' => true,
    'message' => 'API endpoint is working',
    'timestamp' => date('Y-m-d H:i:s'),
    'session_status' => session_status(),
    'session_id' => session_id(),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
    'input' => file_get_contents('php://input')
]);
?>