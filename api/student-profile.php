<?php
// Suppress any warnings or notices that might interfere with JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Start output buffering to catch any stray output
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Ensure user is authenticated and has student role
if (!isAuthenticated()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

if (!hasRole('student')) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Student role required.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = getCurrentUserId();

switch ($method) {
    case 'GET':
        getStudentProfile($user_id);
        break;
    case 'PUT':
        updateStudentProfile($user_id);
        break;
    default:
        ob_end_clean();
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getStudentProfile($user_id) {
    global $conn;
    
    try {
        // Get student profile with user information
        $sql = "SELECT 
                    sp.*,
                    u.username,
                    u.email as user_email,
                    u.created_at,
                    u.last_login
                FROM student_profiles sp 
                JOIN users u ON sp.user_id = u.id 
                WHERE sp.user_id = ? AND u.status = 'active'";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Student profile not found']);
            return;
        }
        
        $profile = $result->fetch_assoc();
        
        // Calculate age from birthday
        if (!empty($profile['birthday'])) {
            $birthday = new DateTime($profile['birthday']);
            $today = new DateTime();
            $age = $today->diff($birthday)->y;
            $profile['calculated_age'] = $age;
            $profile['age_display'] = $age . ' years old';
        }
        
        // Format birthday for display
        if (!empty($profile['birthday'])) {
            $birthday = new DateTime($profile['birthday']);
            $profile['birthday_display'] = $birthday->format('F j, Y');
        }
        
        // Remove sensitive information
        unset($profile['created_at']);
        unset($profile['updated_at']);

        // Clean output buffer and send response
        ob_end_clean();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $profile
        ]);
        
    } catch (Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
}

function updateStudentProfile($user_id) {
    global $conn;
    
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }
        
        // Include the comprehensive data helpers for system-wide updates
        require_once '../includes/data-helpers.php';
        require_once '../includes/email-verification.php';
        
        // Check if email is being changed
        $email_change_requested = false;
        $new_email = null;
        
        if (isset($input['email'])) {
            // Get current email
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_user = $result->fetch_assoc();
            $stmt->close();
            
            if ($current_user && $current_user['email'] !== $input['email']) {
                $email_change_requested = true;
                $new_email = $input['email'];
                // Remove email from input so it doesn't get updated directly
                unset($input['email']);
            }
        }
        
        // Validate the profile data using the new validation function
        $validation = validateStudentProfileData($input);
        if (!$validation['is_valid']) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'validation_errors' => $validation['errors']]);
            return;
        }
        
        // Update profile (excluding email if change was requested)
        $result = updateStudentProfileSystemWide($user_id, $input);
        
        if ($result['success']) {
            $response = [
                'success' => true,
                'message' => $result['message'],
                'tables_updated' => $result['tables_updated']
            ];
            
            // If email change was requested, initiate verification process
            if ($email_change_requested) {
                // Get student's first name for the email
                $stmt = $conn->prepare("SELECT first_name FROM student_profiles WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $profile_result = $stmt->get_result();
                $profile = $profile_result->fetch_assoc();
                $stmt->close();
                
                $first_name = $profile['first_name'] ?? 'Student';
                $current_email = $current_user['email'];
                
                $email_result = initiateEmailChange($user_id, $current_email, $new_email, $first_name);
                
                if ($email_result['success']) {
                    $response['email_verification'] = [
                        'initiated' => true,
                        'message' => $email_result['message'],
                        'new_email' => $new_email
                    ];
                } else {
                    $response['email_verification'] = [
                        'initiated' => false,
                        'error' => $email_result['error']
                    ];
                }
            }
            
            // Clean output buffer and send response
            ob_end_clean();
            http_response_code(200);
            echo json_encode($response);
        } else {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>