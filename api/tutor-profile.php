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

// Ensure user is authenticated and has tutor role
if (!isAuthenticated()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

if (!hasRole('tutor')) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Tutor role required.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = getCurrentUserId();

switch ($method) {
    case 'GET':
        getTutorProfile($user_id);
        break;
    case 'PUT':
        updateTutorProfile($user_id);
        break;
    default:
        ob_end_clean();
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getTutorProfile($user_id) {
    global $conn;
    
    try {
        // Get tutor profile with user information
        $sql = "SELECT 
                    tp.*,
                    u.username,
                    u.email as user_email,
                    u.created_at,
                    u.last_login
                FROM tutor_profiles tp 
                JOIN users u ON tp.user_id = u.id 
                WHERE tp.user_id = ? AND u.status = 'active'";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Tutor profile not found']);
            return;
        }
        
        $profile = $result->fetch_assoc();
        
        // Format creation date for display
        if (!empty($profile['created_at'])) {
            $created = new DateTime($profile['created_at']);
            $profile['member_since'] = $created->format('F j, Y');
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

function updateTutorProfile($user_id) {
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
        
        // Validate required fields
        if (empty($input['first_name']) || empty($input['last_name'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'First name and last name are required']);
            return;
        }
        
        // Validate names (letters, spaces, apostrophes, hyphens only)
        if (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $input['first_name'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'First name must be 2-50 characters and contain only letters, spaces, apostrophes, or hyphens']);
            return;
        }
        
        if (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $input['last_name'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Last name must be 2-50 characters and contain only letters, spaces, apostrophes, or hyphens']);
            return;
        }
        
        // Validate middle name if provided
        if (!empty($input['middle_name']) && !preg_match("/^[a-zA-Z\s'-]{1,50}$/", $input['middle_name'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Middle name must be 1-50 characters and contain only letters, spaces, apostrophes, or hyphens']);
            return;
        }
        
        // Validate gender if provided
        if (!empty($input['gender']) && !in_array($input['gender'], ['Male', 'Female', 'Other'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Please select a valid gender option']);
            return;
        }
        
        // Validate suffix if provided
        if (!empty($input['suffix']) && !preg_match("/^[a-zA-Z\s.]{1,20}$/", $input['suffix'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Suffix must be 1-20 characters and contain only letters, spaces, or periods']);
            return;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Prepare update query
        $sql = "UPDATE tutor_profiles SET 
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                gender = ?,
                suffix = ?,
                contact_number = ?,
                address = ?,
                bachelor_degree = ?,
                specializations = ?,
                bio = ?
                WHERE user_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssssssi", 
            $input['first_name'],
            $input['middle_name'] ?: null,
            $input['last_name'],
            $input['gender'] ?: null,
            $input['suffix'] ?: null,
            $input['contact_number'] ?: null,
            $input['address'] ?: null,
            $input['bachelor_degree'] ?: null,
            $input['specializations'] ?: null,
            $input['bio'] ?: null,
            $user_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update tutor profile: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Clean output buffer and send response
        ob_end_clean();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'affected_rows' => $affected_rows
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
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