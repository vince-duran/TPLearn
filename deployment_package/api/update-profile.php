<?php
require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            
            if (empty($current_password) || empty($new_password)) {
                echo json_encode(['success' => false, 'message' => 'All password fields are required']);
                exit();
            }
            
            if (strlen($new_password) < 6) {
                echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
                exit();
            }
            
            // Verify current password
            $user = getUserById($user_id);
            if (!$user || !password_verify($current_password, $user['password'])) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                exit();
            }
            
            // Update password
            $result = updateUserPassword($user_id, $new_password);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to change password']);
            }
            break;
            
        case 'upload_profile_picture':
            if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
                exit();
            }
            
            $file = $_FILES['profile_picture'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed']);
                exit();
            }
            
            // Validate file size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File size too large. Maximum 5MB allowed']);
                exit();
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update user profile picture in database
                $profile_picture_url = 'uploads/profile_pictures/' . $filename;
                $result = updateUserProfilePicture($user_id, $profile_picture_url);
                
                if ($result) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Profile picture uploaded successfully',
                        'profile_picture_url' => $profile_picture_url
                    ]);
                } else {
                    // Delete uploaded file if database update fails
                    unlink($upload_path);
                    echo json_encode(['success' => false, 'message' => 'Failed to update profile picture in database']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Update profile error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>