<?php
require_once __DIR__ . '/../includes/db.php';

$username = $argv[1] ?? 'TP2025-322';

$stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    echo "User found:\n";
    print_r($user);
} else {
    echo "User '{$username}' not found. Listing some TP2025 users...\n";
    $res = $conn->query("SELECT id, username, role FROM users WHERE username LIKE 'TP2025-%' ORDER BY id LIMIT 20");
    while ($r = $res->fetch_assoc()) {
        echo "- {$r['username']} (id={$r['id']}, role={$r['role']})\n";
    }
}

?>
