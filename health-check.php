<?php
// Health check endpoint for Railway deployment
http_response_code(200);
echo json_encode([
    'status' => 'healthy',
    'service' => 'TPLearn',
    'timestamp' => date('c'),
    'environment' => $_ENV['RAILWAY_ENVIRONMENT'] ?? 'unknown'
]);
exit();
?>