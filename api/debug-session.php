<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'authenticated' => isset($_SESSION['user_id']),
    'get_params' => $_GET,
    'server_method' => $_SERVER['REQUEST_METHOD']
]);
?>