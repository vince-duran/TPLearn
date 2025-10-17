<?php
// Suppress any warnings or notices that might interfere with JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Start output buffering to catch any stray output
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/email-verification.php';

// Helper function to send JSON response
function sendJsonResponse($data, $status_code = 200) {
    ob_end_clean();
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

// Ensure user is authenticated and has student role
if (!isAuthenticated()) {
    sendJsonResponse(['error' => 'Authentication required'], 401);
}

if (!hasRole('student')) {
    sendJsonResponse(['error' => 'Access denied. Student role required.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = getCurrentUserId();

if ($method !== 'POST') {
    sendJsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(['error' => 'Invalid JSON data'], 400);
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'initiate_change':
            handleInitiateEmailChange($user_id, $input);
            break;
            
        case 'verify_change':
            handleVerifyEmailChange($user_id, $input);
            break;
            
        case 'cancel_change':
            handleCancelEmailChange($user_id);
            break;
            
        case 'resend_code':
            handleResendCode($user_id);
            break;
            
        case 'get_pending':
            handleGetPendingChange($user_id);
            break;
            
        default:
            sendJsonResponse(['error' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log("=== EMAIL CHANGE API ERROR ===");
    error_log("Error: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}

function handleInitiateEmailChange($user_id, $input) {
    global $conn;
    
    try {
        error_log("=== EMAIL CHANGE DEBUG ===");
        error_log("User ID: " . $user_id);
        error_log("Input: " . json_encode($input));
        
        $new_email = trim($input['new_email'] ?? '');
        
        if (empty($new_email)) {
            error_log("Error: New email is empty");
            sendJsonResponse(['error' => 'New email address is required'], 400);
        }
        
        // Validate email format
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            error_log("Error: Invalid email format: " . $new_email);
            sendJsonResponse(['error' => 'Invalid email format'], 400);
        }
        
        error_log("New email validated: " . $new_email);
        
        // Get current user info
        $stmt = $conn->prepare("
            SELECT u.email, sp.first_name 
            FROM users u 
            LEFT JOIN student_profiles sp ON u.id = sp.user_id 
            WHERE u.id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        error_log("User found: " . ($user ? 'Yes' : 'No'));
        if ($user) {
            error_log("Current email: " . $user['email']);
            error_log("First name: " . ($user['first_name'] ?: 'Not set'));
        }
        
        if (!$user) {
            error_log("Error: User not found in database");
            sendJsonResponse(['error' => 'User not found'], 404);
        }
        
        // Check if new email is same as current
        if ($user['email'] === $new_email) {
            error_log("Error: New email same as current");
            sendJsonResponse(['error' => 'New email must be different from current email'], 400);
        }
        
        $first_name = $user['first_name'] ?: 'Student';
        error_log("Calling initiateEmailChange function...");
        $result = initiateEmailChange($user_id, $user['email'], $new_email, $first_name);
        
        error_log("initiateEmailChange result: " . json_encode($result));
        
        if ($result['success']) {
            sendJsonResponse($result, 200);
        } else {
            sendJsonResponse($result, 400);
        }
        
    } catch (Exception $e) {
        error_log("Exception in handleInitiateEmailChange: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        sendJsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

function handleVerifyEmailChange($user_id, $input) {
    $verification_code = trim($input['verification_code'] ?? '');
    
    if (empty($verification_code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Verification code is required']);
        return;
    }
    
    // Validate code format (6 digits)
    if (!preg_match('/^\d{6}$/', $verification_code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid verification code format']);
        return;
    }
    
    $result = verifyEmailChange($user_id, $verification_code);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
}

function handleCancelEmailChange($user_id) {
    $result = cancelEmailChange($user_id);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Email change cancelled successfully'
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'No pending email change found'
        ]);
    }
}

function handleResendCode($user_id) {
    global $conn;
    
    // Get pending email change
    $stmt = $conn->prepare("SELECT new_email FROM pending_email_changes WHERE user_id = ? AND expires_at > NOW()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending = $result->fetch_assoc();
    $stmt->close();
    
    if (!$pending) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'No pending email change found or verification expired'
        ]);
        return;
    }
    
    // Get user info for name
    $stmt = $conn->prepare("
        SELECT sp.first_name 
        FROM student_profiles sp 
        WHERE sp.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    $first_name = $user['first_name'] ?? 'Student';
    
    // Generate new verification code and update
    $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    $stmt = $conn->prepare("
        UPDATE pending_email_changes 
        SET verification_code = ?, expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
        WHERE user_id = ?
    ");
    $stmt->bind_param("si", $verification_code, $user_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Send new verification email
        $email_sent = sendEmailChangeVerification($pending['new_email'], $verification_code, $first_name);
        
        if ($email_sent) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Verification code resent successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to send verification email'
            ]);
        }
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate new verification code'
        ]);
    }
}

function handleGetPendingChange($user_id) {
    $pending = getPendingEmailChange($user_id);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'pending_change' => $pending
    ]);
}
?>