<?php
/**
 * Standardized Tutor Header Include
 * 
 * This file provides a unified way to include the header on all tutor pages
 * Ensures consistent functionality across the tutor dashboard
 * 
 * Usage: require_once '../../includes/tutor-header-standard.php';
 * Then call: renderTutorHeader($pageTitle, $pageSubtitle);
 */

// Ensure required files are included
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
require_once '../../includes/header.php';

// Ensure user is a tutor
requireRole('tutor');

/**
 * Render standardized tutor header
 * 
 * @param string $pageTitle - The page title (e.g., 'Dashboard', 'My Programs', 'My Students')
 * @param string $pageSubtitle - Optional subtitle (default: empty)
 * @param bool $showNotifications - Whether to show notifications (default: true)
 */
function renderTutorHeader($pageTitle = 'Dashboard', $pageSubtitle = '', $showNotifications = true) {
    // Get current user data
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        // If no user session, redirect to login
        header('Location: ../../auth/login.php');
        exit();
    }
    
    // Get tutor data - use simple, robust approach
    $tutor_data = getTutorDashboardData($user_id);
    $display_name = $tutor_data['name'] ?? $_SESSION['name'] ?? 'Tutor';
    
    // Get notifications - use tutor-specific notifications
    $notifications = [];
    if ($showNotifications) {
        $notifications = getTutorNotifications($user_id, 10);
    }
    
    // Get messages (placeholder for future implementation)
    $messages = [];
    
    // Render the header with standardized parameters
    renderHeader(
        $pageTitle,
        $pageSubtitle,
        'tutor',
        $display_name,
        $notifications,
        $messages
    );
}

/**
 * Get current date for display
 * 
 * @return string Formatted date string
 */
function getTutorCurrentDate() {
    // Set timezone to Philippine Time (UTC+8)
    date_default_timezone_set('Asia/Manila');
    return date('l, F j, Y');
}

/**
 * Get time-based greeting for tutor
 * 
 * @param string $name Tutor's name
 * @return string Formatted greeting
 */
function getTutorGreeting($name = 'Tutor') {
    // Set timezone to Philippine Time (UTC+8)
    date_default_timezone_set('Asia/Manila');
    
    // Get current hour for time-based greeting
    $current_hour = (int) date('H');
    $greeting = '';
    
    if ($current_hour >= 5 && $current_hour < 12) {
        $greeting = 'Good Morning';
    } elseif ($current_hour >= 12 && $current_hour < 17) {
        $greeting = 'Good Afternoon';
    } elseif ($current_hour >= 17 && $current_hour < 21) {
        $greeting = 'Good Evening';
    } else {
        $greeting = 'Good Evening'; // Late night/early morning
    }
    
    return $greeting . ', ' . $name . '!';
}