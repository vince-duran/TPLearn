<?php
/**
 * Notification Helper Functions
 * Handles creation of notifications and sending email notifications to users
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email-verification.php';

/**
 * Create a notification in the database and optionally send email
 * @param int $user_id User ID to send notification to
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (info, success, warning, error)
 * @param string $action_url Optional URL for action
 * @param bool $send_email Whether to send email notification (default: true)
 * @return array Result array with success status and notification ID
 */
function createNotification($user_id, $title, $message, $type = 'info', $action_url = null, $send_email = true) {
    global $conn;
    
    try {
        // Validate inputs
        if (!$user_id || !$title || !$message) {
            throw new Exception('User ID, title, and message are required');
        }
        
        if (!in_array($type, ['info', 'success', 'warning', 'error'])) {
            $type = 'info';
        }
        
        // Insert notification into database
        $sql = "INSERT INTO notifications (user_id, title, message, type, action_url, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare notification insert: ' . $conn->error);
        }
        
        $stmt->bind_param('issss', $user_id, $title, $message, $type, $action_url);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create notification: ' . $stmt->error);
        }
        
        $notification_id = $conn->insert_id;
        $stmt->close();
        
        // Send email notification if requested
        if ($send_email) {
            try {
                $email_sent = sendNotificationEmail($user_id, $title, $message, $type, $action_url);
                
                return [
                    'success' => true,
                    'notification_id' => $notification_id,
                    'email_sent' => $email_sent,
                    'message' => 'Notification created successfully'
                ];
            } catch (Exception $e) {
                error_log("Email notification failed for user $user_id: " . $e->getMessage());
                
                // Still return success since the notification was created
                return [
                    'success' => true,
                    'notification_id' => $notification_id,
                    'email_sent' => false,
                    'email_error' => $e->getMessage(),
                    'message' => 'Notification created but email failed'
                ];
            }
        }
        
        return [
            'success' => true,
            'notification_id' => $notification_id,
            'email_sent' => false,
            'message' => 'Notification created successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send email notification to user
 * @param int $user_id User ID to send email to
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type
 * @param string $action_url Optional action URL
 * @return bool True if email sent successfully
 */
function sendNotificationEmail($user_id, $title, $message, $type, $action_url = null) {
    global $conn;
    
    // Get user information
    $sql = "SELECT u.email, 
                   COALESCE(sp.first_name, tp.first_name, 'User') as first_name,
                   COALESCE(sp.last_name, tp.last_name, '') as last_name,
                   u.role
            FROM users u
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
            WHERE u.id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare user query: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
    if (empty($full_name)) {
        $full_name = 'User';
    }
    
    return sendNotificationEmailTemplate($user['email'], $full_name, $title, $message, $type, $action_url);
}

/**
 * Send notification email using template
 * @param string $email Email address
 * @param string $name User's name
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type
 * @param string $action_url Optional action URL
 * @return bool True if email sent successfully
 */
function sendNotificationEmailTemplate($email, $name, $title, $message, $type, $action_url = null) {
    $config = require __DIR__ . '/../config/email.php';
    
    // Check if we should simulate emails
    if ($config['provider'] === 'simulate') {
        $logMessage = "\n=== NOTIFICATION EMAIL SIMULATION ===\n";
        $logMessage .= "To: {$email}\n";
        $logMessage .= "Name: {$name}\n";
        $logMessage .= "Subject: [TPLearn] {$title}\n";
        $logMessage .= "Type: {$type}\n";
        $logMessage .= "Message: {$message}\n";
        if ($action_url) {
            $logMessage .= "Action URL: {$action_url}\n";
        }
        $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "================================\n";
        
        error_log($logMessage);
        
        // Log to file
        if (!file_exists(__DIR__ . '/../logs')) {
            mkdir(__DIR__ . '/../logs', 0755, true);
        }
        file_put_contents(__DIR__ . '/../logs/notification_emails.log', $logMessage, FILE_APPEND);
        
        return true;
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Get provider config
        $provider = $config['provider'];
        $providerConfig = $config[$provider];
        
        // Gmail-specific password cleanup
        if ($provider === 'gmail') {
            $providerConfig['password'] = str_replace(' ', '', $providerConfig['password']);
        }
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $providerConfig['host'];
        $mail->SMTPAuth = $providerConfig['auth'];
        $mail->Username = $providerConfig['username'];
        $mail->Password = $providerConfig['password'];
        
        if ($providerConfig['security'] === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($providerConfig['security'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
        
        $mail->Port = $providerConfig['port'];
        
        // Recipients
        $mail->setFrom($providerConfig['from_email'], $providerConfig['from_name']);
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = '[TPLearn] ' . $title;
        
        // Get notification icon and color based on type
        $icon_info = getNotificationIconInfo($type);
        
        // Build action button if URL provided
        $action_button = '';
        if ($action_url) {
            $base_url = getNotificationBaseUrl();
            $full_url = $action_url;
            
            // Make URL absolute if it's relative
            if (strpos($action_url, 'http') !== 0) {
                $full_url = $base_url . '/' . ltrim($action_url, '/');
            }
            
            $action_button = "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$full_url}' style='
                        background-color: {$icon_info['button_color']};
                        color: white;
                        padding: 12px 24px;
                        text-decoration: none;
                        border-radius: 6px;
                        font-weight: bold;
                        display: inline-block;
                    '>View Details</a>
                </div>
            ";
        }
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #10b981; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 24px;'>TPLearn</h1>
                <p style='color: white; margin: 5px 0 0 0; opacity: 0.9;'>Tisa at Pisara's Academic and Tutorial Services</p>
            </div>
            
            <div style='background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px;'>
                <div style='background-color: {$icon_info['bg_color']}; color: {$icon_info['text_color']}; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center;'>
                    <div style='font-size: 24px; margin-bottom: 5px;'>{$icon_info['emoji']}</div>
                    <h2 style='margin: 0; font-size: 18px;'>{$title}</h2>
                </div>
                
                <div style='background-color: white; padding: 20px; border-radius: 6px; border-left: 4px solid {$icon_info['border_color']};'>
                    <p style='color: #333; line-height: 1.6; margin: 0; font-size: 16px;'>{$message}</p>
                </div>
                
                {$action_button}
                
                <div style='border-top: 1px solid #ddd; padding-top: 20px; margin-top: 30px; color: #666; font-size: 14px;'>
                    <p style='margin: 0;'>Hi {$name},</p>
                    <p style='margin: 10px 0;'>You received this notification from your TPLearn account. You can also view this and other notifications by logging into your dashboard.</p>
                    <p style='margin: 10px 0 0 0;'>Best regards,<br>The TPLearn Team</p>
                </div>
                
                <div style='text-align: center; margin-top: 30px; color: #999; font-size: 12px;'>
                    <p style='margin: 0;'>© 2025 TPLearn. All rights reserved.</p>
                    <p style='margin: 5px 0 0 0;'>This is an automated notification. Please do not reply to this email.</p>
                </div>
            </div>
        </div>";
        
        $mail->AltBody = "Hi $name,\n\n$title\n\n$message\n\n" . 
                        ($action_url ? "View details: $action_url\n\n" : "") .
                        "Best regards,\nThe TPLearn Team\n\n" .
                        "You can view this and other notifications by logging into your TPLearn dashboard.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Notification email failed: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        throw new Exception('Failed to send notification email: ' . $e->getMessage());
    }
}

/**
 * Get notification icon and styling info based on type
 * @param string $type Notification type
 * @return array Icon and styling information
 */
function getNotificationIconInfo($type) {
    switch ($type) {
        case 'success':
            return [
                'emoji' => '✅',
                'bg_color' => '#d1fae5',
                'text_color' => '#065f46',
                'border_color' => '#10b981',
                'button_color' => '#10b981'
            ];
        case 'warning':
            return [
                'emoji' => '⚠️',
                'bg_color' => '#fef3c7',
                'text_color' => '#92400e',
                'border_color' => '#f59e0b',
                'button_color' => '#f59e0b'
            ];
        case 'error':
            return [
                'emoji' => '❌',
                'bg_color' => '#fee2e2',
                'text_color' => '#991b1b',
                'border_color' => '#ef4444',
                'button_color' => '#ef4444'
            ];
        default: // info
            return [
                'emoji' => 'ℹ️',
                'bg_color' => '#dbeafe',
                'text_color' => '#1e40af',
                'border_color' => '#3b82f6',
                'button_color' => '#3b82f6'
            ];
    }
}

/**
 * Create payment validation notification
 * @param int $user_id Student user ID
 * @param string $status Payment status (validated/rejected)
 * @param string $program_name Program name
 * @param float $amount Payment amount
 * @param string $notes Optional notes
 * @return array Result array
 */
function createPaymentNotification($user_id, $status, $program_name, $amount, $notes = null) {
    if ($status === 'validated') {
        $title = 'Payment Approved';
        $message = "Your payment of ₱" . number_format($amount, 2) . " for {$program_name} has been approved and validated.";
        $type = 'success';
    } else {
        $title = 'Payment Rejected - Resubmit Required';
        $message = "Your payment of ₱" . number_format($amount, 2) . " for {$program_name} has been rejected.";
        if ($notes) {
            $message .= " Reason: {$notes}";
        }
        $message .= " Please resubmit your payment with the correct information.";
        $type = 'error';
    }
    
    return createNotification($user_id, $title, $message, $type, 'dashboards/student/student-payments.php');
}

/**
 * Create enrollment confirmation notification
 * @param int $user_id Student user ID
 * @param string $program_name Program name
 * @param float $total_fee Total fee
 * @return array Result array
 */
function createEnrollmentNotification($user_id, $program_name, $total_fee) {
    $title = 'Enrollment Confirmed';
    $message = "Your enrollment in {$program_name} has been confirmed. Total fee: ₱" . number_format($total_fee, 2) . ".";
    
    return createNotification($user_id, $title, $message, 'success', 'dashboards/student/student.php');
}

/**
 * Create assignment/material notification
 * @param int $user_id Student user ID
 * @param string $material_title Material title
 * @param string $program_name Program name
 * @return array Result array
 */
function createMaterialNotification($user_id, $material_title, $program_name) {
    $title = 'New Material Available';
    $message = "New material '{$material_title}' has been uploaded for {$program_name}.";
    
    return createNotification($user_id, $title, $message, 'info', 'dashboards/student/student.php');
}

/**
 * Send notification email to all admin users
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type
 * @param string $action_url Optional action URL
 * @return array Result array with success status and details
 */
function sendAdminEmailNotification($title, $message, $type = 'info', $action_url = null) {
    global $conn;
    
    try {
        // Get all admin users
        $sql = "SELECT id, email FROM users WHERE role = 'admin' AND status = 'active'";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('Failed to fetch admin users: ' . $conn->error);
        }
        
        $admin_emails_sent = 0;
        $admin_emails_failed = 0;
        $errors = [];
        
        while ($admin = $result->fetch_assoc()) {
            try {
                // Send email notification directly to admin email
                $email_sent = sendNotificationEmailTemplate(
                    $admin['email'], 
                    'Admin', 
                    $title, 
                    $message, 
                    $type, 
                    $action_url
                );
                
                if ($email_sent) {
                    $admin_emails_sent++;
                } else {
                    $admin_emails_failed++;
                    $errors[] = "Failed to send email to admin {$admin['email']}";
                }
                
            } catch (Exception $e) {
                $admin_emails_failed++;
                $errors[] = "Error sending email to admin {$admin['email']}: " . $e->getMessage();
                error_log("Admin email notification failed for {$admin['email']}: " . $e->getMessage());
            }
        }
        
        return [
            'success' => $admin_emails_sent > 0,
            'emails_sent' => $admin_emails_sent,
            'emails_failed' => $admin_emails_failed,
            'errors' => $errors,
            'message' => "Admin email notifications: {$admin_emails_sent} sent, {$admin_emails_failed} failed"
        ];
        
    } catch (Exception $e) {
        error_log("Error sending admin email notifications: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'emails_sent' => 0,
            'emails_failed' => 0
        ];
    }
}

/**
 * Create admin notification for new enrollment and send email to admins
 * @param int $student_id Student user ID
 * @param string $student_name Student name
 * @param string $program_name Program name
 * @param string $tutor_name Tutor name
 * @return array Result array
 */
function createAdminEnrollmentNotification($student_id, $student_name, $program_name, $tutor_name = '') {
    global $conn;
    
    try {
        // Get all admin users to create individual notifications
        $sql = "SELECT id FROM users WHERE role = 'admin' AND status = 'active'";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('Failed to fetch admin users: ' . $conn->error);
        }
        
        $title = 'New Enrollment';
        $tutor_info = $tutor_name ? " (Tutor: {$tutor_name})" : '';
        $message = "{$student_name} enrolled in {$program_name}{$tutor_info}";
        $action_url = 'dashboards/admin/students.php';
        
        $notifications_created = 0;
        $email_result = null;
        
        // Create notification for each admin
        while ($admin = $result->fetch_assoc()) {
            $notification_result = createNotification(
                $admin['id'], 
                $title, 
                $message, 
                'info', 
                $action_url, 
                false // Don't send individual emails, we'll send one admin email
            );
            
            if ($notification_result['success']) {
                $notifications_created++;
            }
        }
        
        // Send email notification to all admins
        $email_result = sendAdminEmailNotification($title, $message, 'info', $action_url);
        
        return [
            'success' => true,
            'notifications_created' => $notifications_created,
            'admin_email_result' => $email_result,
            'message' => 'Admin enrollment notifications created and emails sent'
        ];
        
    } catch (Exception $e) {
        error_log("Error creating admin enrollment notification: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create admin notification for payment validation needed and send email to admins
 * @param int $payment_id Payment ID
 * @param string $student_name Student name
 * @param float $amount Payment amount
 * @param string $program_name Program name
 * @return array Result array
 */
function createAdminPaymentValidationNotification($payment_id, $student_name, $amount, $program_name) {
    global $conn;
    
    try {
        // Get all admin users to create individual notifications
        $sql = "SELECT id FROM users WHERE role = 'admin' AND status = 'active'";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('Failed to fetch admin users: ' . $conn->error);
        }
        
        $title = 'Payment Validation Needed';
        $message = "{$student_name} - ₱" . number_format($amount, 2) . " for {$program_name}";
        $action_url = 'dashboards/admin/payments.php';
        
        $notifications_created = 0;
        
        // Create notification for each admin
        while ($admin = $result->fetch_assoc()) {
            $notification_result = createNotification(
                $admin['id'], 
                $title, 
                $message, 
                'warning', 
                $action_url, 
                false // Don't send individual emails, we'll send one admin email
            );
            
            if ($notification_result['success']) {
                $notifications_created++;
            }
        }
        
        // Send email notification to all admins
        $email_result = sendAdminEmailNotification($title, $message, 'warning', $action_url);
        
        return [
            'success' => true,
            'notifications_created' => $notifications_created,
            'admin_email_result' => $email_result,
            'message' => 'Admin payment validation notifications created and emails sent'
        ];
        
    } catch (Exception $e) {
        error_log("Error creating admin payment validation notification: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get base URL for the application (notification-specific version)
 */
function getNotificationBaseUrl() {
    // Handle CLI context (when running from command line)
    if (php_sapi_name() === 'cli') {
        return 'http://localhost/tplearn'; // Default for CLI
    }
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = isset($_SERVER['REQUEST_URI']) ? dirname($_SERVER['REQUEST_URI']) : '';
    
    // Remove common paths that might be in REQUEST_URI
    $scriptPath = str_replace(['/includes', '/api', '/dashboards'], '', $scriptPath);
    
    return rtrim($protocol . $domainName . $scriptPath, '/');
}