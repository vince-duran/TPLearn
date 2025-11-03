<?php
/**
 * Debug Email Notification for Payment Rejection
 * This script helps debug why email notifications aren't being sent
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debugging Payment Rejection Email Notifications</h1>\n";

try {
    // Check if there are any rejected payments
    echo "<h2>Step 1: Finding rejected payments</h2>\n";
    
    $stmt = $conn->prepare("
        SELECT p.id, p.amount, p.status, p.notes, p.updated_at,
               pr.name as program_name, 
               u.id as student_user_id, u.email,
               COALESCE(sp.first_name, 'Student') as first_name
        FROM payments p
        JOIN enrollments e ON p.enrollment_id = e.id
        JOIN programs pr ON e.program_id = pr.id
        JOIN users u ON e.student_user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE p.status = 'rejected'
        ORDER BY p.updated_at DESC
        LIMIT 5
    ");
    
    $stmt->execute();
    $rejected_payments = $stmt->get_result();
    
    if ($rejected_payments->num_rows > 0) {
        echo "📋 Found rejected payments:<br>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Payment ID</th><th>Student</th><th>Program</th><th>Amount</th><th>Rejection Reason</th><th>Updated</th></tr>\n";
        
        while ($payment = $rejected_payments->fetch_assoc()) {
            echo "<tr>";
            echo "<td>PAY-" . date('Ymd') . "-" . str_pad($payment['id'], 3, '0', STR_PAD_LEFT) . "</td>";
            echo "<td>" . htmlspecialchars($payment['first_name'] . ' (' . $payment['email'] . ')') . "</td>";
            echo "<td>" . htmlspecialchars($payment['program_name']) . "</td>";
            echo "<td>₱" . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($payment['notes'] ?: 'No reason provided') . "</td>";
            echo "<td>" . $payment['updated_at'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "ℹ️ No rejected payments found.<br>\n";
    }
    
    // Check if notifications were created for rejected payments
    echo "<h2>Step 2: Checking notifications for rejected payments</h2>\n";
    
    $stmt = $conn->prepare("
        SELECT n.*, u.email, COALESCE(sp.first_name, 'Student') as first_name
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE n.title LIKE '%rejected%' OR n.title LIKE '%Rejected%'
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $rejection_notifications = $stmt->get_result();
    
    if ($rejection_notifications->num_rows > 0) {
        echo "📧 Found rejection notifications:<br>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>\n";
        echo "<tr><th>Student</th><th>Title</th><th>Message</th><th>Type</th><th>Created</th></tr>\n";
        
        while ($notification = $rejection_notifications->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($notification['first_name'] . ' (' . $notification['email'] . ')') . "</td>";
            echo "<td>" . htmlspecialchars($notification['title']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($notification['message'], 0, 100)) . "...</td>";
            echo "<td style='color: red;'>" . $notification['type'] . "</td>";
            echo "<td>" . $notification['created_at'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "❌ No rejection notifications found in database!<br>\n";
    }
    
    // Test the notification system manually
    echo "<h2>Step 3: Testing notification creation manually</h2>\n";
    
    // Find a student to test with
    $stmt = $conn->prepare("
        SELECT u.id, u.email, COALESCE(sp.first_name, 'Student') as first_name
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.role = 'student'
        LIMIT 1
    ");
    
    $stmt->execute();
    $test_student = $stmt->get_result()->fetch_assoc();
    
    if ($test_student) {
        echo "🧪 Testing with student: " . $test_student['first_name'] . " (" . $test_student['email'] . ")<br>\n";
        
        try {
            require_once '../includes/notification-helpers.php';
            
            $test_result = createPaymentNotification(
                $test_student['id'],
                'rejected',
                'Test Math Program',
                1500.00,
                'Test rejection for debugging'
            );
            
            if ($test_result['success']) {
                echo "✅ Test notification created successfully!<br>\n";
                echo "📧 Email sent: " . ($test_result['email_sent'] ? 'Yes' : 'No') . "<br>\n";
                if (!$test_result['email_sent'] && isset($test_result['email_error'])) {
                    echo "❌ Email error: " . $test_result['email_error'] . "<br>\n";
                }
            } else {
                echo "❌ Failed to create test notification: " . $test_result['error'] . "<br>\n";
            }
        } catch (Exception $e) {
            echo "❌ Exception during test: " . $e->getMessage() . "<br>\n";
        }
    } else {
        echo "❌ No student found for testing.<br>\n";
    }
    
    // Check email configuration
    echo "<h2>Step 4: Checking email configuration</h2>\n";
    
    $email_config = require '../config/email.php';
    echo "📧 Email provider: " . $email_config['provider'] . "<br>\n";
    
    if ($email_config['provider'] === 'simulate') {
        echo "ℹ️ Email is in simulation mode - check logs for email content.<br>\n";
        
        $log_file = '../logs/notification_emails.log';
        if (file_exists($log_file)) {
            echo "📄 Email log file exists. Recent entries:<br>\n";
            $log_content = file_get_contents($log_file);
            $log_lines = explode("\n", $log_content);
            $recent_lines = array_slice($log_lines, -20);
            
            echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; max-height: 300px; overflow-y: auto; font-size: 12px;'>";
            echo htmlspecialchars(implode("\n", $recent_lines));
            echo "</pre>\n";
        } else {
            echo "❌ Email log file not found at: $log_file<br>\n";
        }
    } else {
        echo "📧 Email is configured for real sending via " . $email_config['provider'] . "<br>\n";
        echo "📧 From email: " . $email_config[$email_config['provider']]['from_email'] . "<br>\n";
    }
    
    // Check PHP error log for any issues
    echo "<h2>Step 5: Checking PHP error log</h2>\n";
    
    $error_log_path = ini_get('error_log');
    if ($error_log_path && file_exists($error_log_path)) {
        echo "📄 PHP error log found at: $error_log_path<br>\n";
        echo "🔍 Recent entries mentioning 'notification' or 'email':<br>\n";
        
        $log_content = file_get_contents($error_log_path);
        $log_lines = explode("\n", $log_content);
        $recent_lines = array_slice($log_lines, -100); // Last 100 lines
        
        $relevant_lines = array_filter($recent_lines, function($line) {
            return stripos($line, 'notification') !== false || 
                   stripos($line, 'email') !== false ||
                   stripos($line, 'payment');
        });
        
        if ($relevant_lines) {
            echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; max-height: 300px; overflow-y: auto; font-size: 12px;'>";
            echo htmlspecialchars(implode("\n", array_slice($relevant_lines, -20)));
            echo "</pre>\n";
        } else {
            echo "ℹ️ No recent entries found mentioning notifications or emails.<br>\n";
        }
    } else {
        echo "❌ PHP error log not found or not configured.<br>\n";
    }
    
    echo "<h2>✅ Debug analysis complete!</h2>\n";
    echo "<p><strong>Summary:</strong> Check the results above to identify why email notifications aren't being sent for payment rejections.</p>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Debug failed with error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>