<?php
// Prevent any output before we set headers
ob_start();

// Suppress error display to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Clean any previous output
ob_clean();

// Set JSON header first
header('Content-Type: application/json');

// Ensure user is authenticated and is a student
$session_debug = [
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'username' => $_SESSION['username'] ?? 'not set', 
    'role' => $_SESSION['role'] ?? 'not set',
    'isLoggedIn' => isLoggedIn() ? 'true' : 'false'
];
error_log("Session debug: " . json_encode($session_debug));

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access',
        'debug' => $session_debug
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $attempt_id = $_POST['attempt_id'] ?? null;
    $comments = $_POST['comments'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    // Debug logging
    error_log("Submit Assessment Attempt - User ID: " . ($user_id ?? 'NULL') . ", Attempt ID: " . ($attempt_id ?? 'NULL'));
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    if (!$attempt_id) {
        throw new Exception('Attempt ID is required');
    }
    
    if (!$user_id) {
        throw new Exception('User not authenticated properly');
    }
    
    // Get attempt details and verify ownership
    $stmt = $conn->prepare("
        SELECT 
            aa.*,
            a.title as assessment_title,
            a.total_points,
            a.time_limit,
            a.due_date,
            pm.title as material_title
        FROM assessment_attempts aa
        INNER JOIN assessments a ON aa.assessment_id = a.id
        INNER JOIN program_materials pm ON a.material_id = pm.id
        WHERE aa.id = ? AND aa.student_user_id = ?
    ");
    
    $stmt->bind_param('ii', $attempt_id, $user_id);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    
    if (!$attempt) {
        throw new Exception('Assessment attempt not found or access denied');
    }
    
    // Check if already submitted
    if ($attempt['submitted_at']) {
        throw new Exception('Assessment already submitted');
    }
    
    // Check if time limit exceeded
    if ($attempt['time_limit_end'] && strtotime($attempt['time_limit_end']) < time()) {
        throw new Exception('Time limit exceeded');
    }
    
    // Handle file upload if provided
    $submission_file_id = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['submission_file'];
        
        // Validate file
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            throw new Exception('File size exceeds 10MB limit');
        }
        
        // Allowed file types
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowed_types));
        }
        
        // Generate unique filename
        $upload_dir = '../uploads/assessment_submissions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $unique_filename = 'assessment_' . uniqid() . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $unique_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to upload file');
        }
        
        // Store file information in database
        $file_stmt = $conn->prepare("
            INSERT INTO file_uploads (
                user_id,
                filename, 
                original_filename, 
                file_path, 
                file_size, 
                mime_type, 
                upload_type,
                related_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $relative_path = 'uploads/assessment_submissions/' . $unique_filename;
        $upload_type = 'assessment_attempt';
        $file_stmt->bind_param('isssissi', 
            $user_id,
            $unique_filename, 
            $file['name'], 
            $relative_path, 
            $file['size'], 
            $file['type'], 
            $upload_type,
            $attempt_id
        );
        
        if ($file_stmt->execute()) {
            $submission_file_id = $conn->insert_id;
        } else {
            // Clean up uploaded file if database insert fails
            unlink($file_path);
            throw new Exception('Failed to save file information');
        }
    }
    
    // Calculate time taken
    $started_time = strtotime($attempt['started_at']);
    $current_time = time();
    $time_taken = $current_time - $started_time; // in seconds
    
    // Determine if this is a late submission
    $is_late = false;
    if ($attempt['due_date'] && $attempt['due_date'] !== '0000-00-00 00:00:00' && strtotime($attempt['due_date'])) {
        $due_timestamp = strtotime($attempt['due_date']);
        $is_late = $current_time > $due_timestamp;
    }
    
    $status = $is_late ? 'late_submission' : 'submitted';
    
    // Update the attempt record
    $update_stmt = $conn->prepare("
        UPDATE assessment_attempts SET 
            submitted_at = NOW(),
            time_taken = ?,
            comments = ?,
            submission_file_id = ?,
            status = ?
        WHERE id = ? AND student_user_id = ?
    ");
    
    $update_stmt->bind_param('isisii', $time_taken, $comments, $submission_file_id, $status, $attempt_id, $user_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to submit assessment');
    }
    
    // Log the submission
    $submission_type = $is_late ? 'late' : 'on-time';
    error_log("Assessment submitted ($submission_type) - User: $user_id, Attempt: $attempt_id, File: " . ($submission_file_id ?: 'none'));
    
    // Prepare response data
    $response = [
        'success' => true,
        'message' => $is_late ? 'Assessment submitted successfully (Late Submission)' : 'Assessment submitted successfully',
        'attempt_id' => $attempt_id,
        'submitted_at' => date('Y-m-d H:i:s'),
        'time_taken' => $time_taken,
        'time_taken_formatted' => gmdate('H:i:s', $time_taken),
        'is_late' => $is_late,
        'status' => $status
    ];
    
    if ($submission_file_id) {
        $response['file_uploaded'] = true;
        $response['file_id'] = $submission_file_id;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Clean output buffer to ensure no HTML is sent
    ob_clean();
    header('Content-Type: application/json');
    error_log("Submit assessment attempt error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>