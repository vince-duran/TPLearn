<?php
// Public test endpoint to verify the image file works
require_once __DIR__ . '/../includes/db.php';

$attachment_id = $_GET['id'] ?? 7; // Default to attachment 7

// Get attachment info
$sql = "SELECT * FROM payment_attachments WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $attachment_id);
$stmt->execute();
$attachment = $stmt->get_result()->fetch_assoc();

if (!$attachment) {
    exit('Attachment not found');
}

$file_path = __DIR__ . '/../uploads/payment_receipts/' . $attachment['filename'];

if (!file_exists($file_path)) {
    exit('File not found: ' . $file_path);
}

// Serve the file without any authentication
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline');

readfile($file_path);
?>