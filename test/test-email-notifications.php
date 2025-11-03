<?php
/**
 * Test Email Notification System
 * This script tests the notification email functionality
 */

require_once '../includes/db.php';
require_once '../includes/notification-helpers.php';

// Enable error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing Email Notification System</h1>\n";

try {
    // Test 1: Create a simple notification without email
    echo "<h2>Test 1: Creating notification without email</h2>\n";
    
    // Use a test user ID (you can change this to an actual user ID from your database)
    $test_user_id = 1; // Change this to a valid user ID
    
    $result1 = createNotification(
        $test_user_id,
        'Test Notification',
        'This is a test notification without email.',
        'info',
        'test-page.php',
        false // Don't send email
    );
    
    if ($result1['success']) {
        echo "✅ Notification created successfully. ID: " . $result1['notification_id'] . "<br>\n";
    } else {
        echo "❌ Failed to create notification: " . $result1['error'] . "<br>\n";
    }
    
    // Test 2: Create a notification with email (will log to file since it's simulated)
    echo "<h2>Test 2: Creating notification with email</h2>\n";
    
    $result2 = createNotification(
        $test_user_id,
        'Test Email Notification',
        'This is a test notification that should send an email.',
        'success',
        'test-page.php',
        true // Send email
    );
    
    if ($result2['success']) {
        echo "✅ Notification created successfully. ID: " . $result2['notification_id'] . "<br>\n";
        echo "📧 Email sent: " . ($result2['email_sent'] ? 'Yes' : 'No') . "<br>\n";
        if (!$result2['email_sent'] && isset($result2['email_error'])) {
            echo "📧 Email error: " . $result2['email_error'] . "<br>\n";
        }
    } else {
        echo "❌ Failed to create notification: " . $result2['error'] . "<br>\n";
    }
    
    // Test 3: Test payment notification function
    echo "<h2>Test 3: Testing payment notification</h2>\n";
    
    $result3 = createPaymentNotification(
        $test_user_id,
        'validated',
        'Test Math Program',
        1500.00,
        'Payment approved by admin'
    );
    
    if ($result3['success']) {
        echo "✅ Payment notification created successfully. ID: " . $result3['notification_id'] . "<br>\n";
        echo "📧 Email sent: " . ($result3['email_sent'] ? 'Yes' : 'No') . "<br>\n";
    } else {
        echo "❌ Failed to create payment notification: " . $result3['error'] . "<br>\n";
    }
    
    // Test 4: Test enrollment notification function
    echo "<h2>Test 4: Testing enrollment notification</h2>\n";
    
    $result4 = createEnrollmentNotification(
        $test_user_id,
        'Test Science Program',
        2000.00
    );
    
    if ($result4['success']) {
        echo "✅ Enrollment notification created successfully. ID: " . $result4['notification_id'] . "<br>\n";
        echo "📧 Email sent: " . ($result4['email_sent'] ? 'Yes' : 'No') . "<br>\n";
    } else {
        echo "❌ Failed to create enrollment notification: " . $result4['error'] . "<br>\n";
    }
    
    // Test 5: Check if notifications were inserted into database
    echo "<h2>Test 5: Checking database for created notifications</h2>\n";
    
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param('i', $test_user_id);
    $stmt->execute();
    $notifications = $stmt->get_result();
    
    if ($notifications->num_rows > 0) {
        echo "📋 Recent notifications for user $test_user_id:<br>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Title</th><th>Message</th><th>Type</th><th>Created</th></tr>\n";
        
        while ($notification = $notifications->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $notification['id'] . "</td>";
            echo "<td>" . htmlspecialchars($notification['title']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($notification['message'], 0, 50)) . "...</td>";
            echo "<td>" . $notification['type'] . "</td>";
            echo "<td>" . $notification['created_at'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "❌ No notifications found in database for user $test_user_id<br>\n";
    }
    
    // Check email logs
    echo "<h2>Test 6: Checking email logs</h2>\n";
    
    $log_file = '../logs/notification_emails.log';
    if (file_exists($log_file)) {
        echo "📧 Email log file exists. Recent entries:<br>\n";
        $log_content = file_get_contents($log_file);
        $log_lines = explode("\n", $log_content);
        $recent_lines = array_slice($log_lines, -20); // Last 20 lines
        
        echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; max-height: 300px; overflow-y: auto;'>";
        echo htmlspecialchars(implode("\n", $recent_lines));
        echo "</pre>\n";
    } else {
        echo "❌ Email log file not found at: $log_file<br>\n";
    }
    
    echo "<h2>✅ All tests completed!</h2>\n";
    echo "<p><strong>Note:</strong> Since the email provider is set to 'gmail', emails are being sent via Gmail SMTP. ";
    echo "If you want to test without sending real emails, change the provider to 'simulate' in config/email.php.</p>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Test failed with error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>