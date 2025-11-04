<?php
// Image serving script for program covers
$requestedFile = $_GET['file'] ?? '';

// Security: only allow files from program_covers directory
if (empty($requestedFile) || strpos($requestedFile, '..') !== false) {
    http_response_code(404);
    exit('File not found');
}

$filePath = __DIR__ . '/uploads/program_covers/' . basename($requestedFile);

// Check if file exists and is an image
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Get file info
$imageInfo = getimagesize($filePath);
if (!$imageInfo) {
    http_response_code(415);
    exit('Invalid image file');
}

// Set proper headers
header('Content-Type: ' . $imageInfo['mime']);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// Output the image
readfile($filePath);
?>