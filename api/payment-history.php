<?php
// Disable error reporting to prevent warnings from corrupting JSON
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

// Clear any unwanted output
ob_clean();

// Set JSON content type
header('Content-Type: application/json');

// Debug session info
error_log("Session info - User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));

// Check if user is logged in and has appropriate role (student or admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// Get payment ID from request
$payment_id = $_GET['payment_id'] ?? '';

if (empty($payment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payment ID is required']);
    exit();
}

try {
    // Check if function exists
    if (!function_exists('getPaymentHistory')) {
        echo json_encode(['success' => false, 'error' => 'Function getPaymentHistory not found']);
        exit();
    }
    
    // Get payment history
    $historyData = getPaymentHistory($payment_id);
    
    if (isset($historyData['error'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => $historyData['error']]);
        exit();
    }
    
    $payment = $historyData['payment'];
    $history = $historyData['history'];
    
    // Verify access permissions
    if ($_SESSION['role'] === 'student') {
        // Students can only access their own payments
        $current_username = $_SESSION['username'];
        if ($payment['student_username'] !== $current_username) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied - payment belongs to different user']);
            exit();
        }
    }
    // Admins can access all payment histories
    
    // Return success response
    echo json_encode([
        'success' => true,
        'payment' => $payment,
        'history' => $history,
        'total_events' => count($history)
    ]);
    
} catch (Exception $e) {
    error_log("Payment history API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>
