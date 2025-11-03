<?php
/**
 * Standardized Student Header Include
 * 
 * This file provides a unified way to include the header on all student pages
 * Ensures consistent functionality across the student dashboard
 * 
 * Usage: require_once '../../includes/student-header-standard.php';
 * Then call: renderStudentHeader($pageTitle, $pageSubtitle);
 */

// Ensure required files are included
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
require_once '../../includes/header.php';

// Ensure user is a student
requireRole('student');

/**
 * Render standardized student header
 * 
 * @param string $pageTitle - The page title (e.g., 'Dashboard', 'Enrollment', 'Payments')
 * @param string $pageSubtitle - Optional subtitle (default: empty)
 * @param bool $showNotifications - Whether to show notifications (default: true)
 */
function renderStudentHeader($pageTitle = 'Dashboard', $pageSubtitle = '', $showNotifications = true) {
    // Get current user data
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        // If no user session, redirect to login
        header('Location: ../../auth/login.php');
        exit();
    }
    
    // Get student data - use simple, robust approach
    $student_data = getStudentDashboardData($user_id);
    $display_name = $student_data['name'] ?? $_SESSION['name'] ?? 'Student';
    
    // Get notifications - use simple approach like working pages
    $notifications = [];
    if ($showNotifications) {
        $notifications = getUserNotifications($user_id, 10);
    }
    
    // Get messages (placeholder for future implementation)
    $messages = [];
    
    // Render the header with standardized parameters
    renderHeader(
        $pageTitle,
        $pageSubtitle,
        'student',
        $display_name,
        $notifications,
        $messages
    );
}

/**
 * Get standard student header data without rendering
 * Useful for pages that need the data but want to render manually
 */
function getStudentHeaderData() {
    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['name'] ?? 'Student';
    
    if (!$user_id) {
        return null;
    }
    
    $student_data = getStudentDashboardData($user_id);
    $display_name = $student_data['name'] ?? $user_name;
    $notifications = getUserNotifications($user_id, 10);
    
    return [
        'user_id' => $user_id,
        'display_name' => $display_name,
        'notifications' => $notifications,
        'messages' => []
    ];
}
?>