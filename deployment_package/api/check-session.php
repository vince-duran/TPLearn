<?php
session_start();
header('Content-Type: application/json');

$response = [
    'session_started' => session_status() === PHP_SESSION_ACTIVE,
    'session_id' => session_id(),
    'user_logged_in' => isset($_SESSION['user_id']),
    'user_data' => [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'first_name' => $_SESSION['first_name'] ?? null,
        'last_name' => $_SESSION['last_name'] ?? null,
    ],
    'all_session_data' => $_SESSION
];

echo json_encode($response, JSON_PRETTY_PRINT);
