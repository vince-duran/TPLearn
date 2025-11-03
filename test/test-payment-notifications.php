<?php
/**
 * Test Payment Validation Email Notifications
 * This script simulates payment validation to test email notifications
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';

// Enable error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing Payment Validation Email Notifications</h1>\n";

try {
    // Check if there are any pending payments in the system
    echo "<h2>Step 1: Finding pending payments</h2>\n";
    
    $stmt = $conn->prepare("
        SELECT p.id, p.amount, pr.name as program_name, u.id as student_user_id, u.email,
               COALESCE(sp.first_name, 'Student') as first_name
        FROM payments p
        JOIN enrollments e ON p.enrollment_id = e.id
        JOIN programs pr ON e.program_id = pr.id
        JOIN users u ON e.student_user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE p.status = 'pending' AND p.reference_number IS NOT NULL
        LIMIT 5
    ");
    
    $stmt->execute();
    $pending_payments = $stmt->get_result();
    
    if ($pending_payments->num_rows > 0) {
        echo "📋 Found pending payments:<br>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Payment ID</th><th>Student</th><th>Program</th><th>Amount</th><th>Email</th><th>Actions</th></tr>\n";
        
        while ($payment = $pending_payments->fetch_assoc()) {
            echo "<tr>";
            echo "<td>PAY-" . date('Ymd') . "-" . str_pad($payment['id'], 3, '0', STR_PAD_LEFT) . "</td>";
            echo "<td>" . htmlspecialchars($payment['first_name']) . "</td>";
            echo "<td>" . htmlspecialchars($payment['program_name']) . "</td>";
            echo "<td>₱" . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($payment['email']) . "</td>";
            echo "<td>";
            echo "<a href='?action=validate&id=" . $payment['id'] . "' style='color: green; margin-right: 10px;'>✅ Validate</a>";
            echo "<a href='?action=reject&id=" . $payment['id'] . "' style='color: red;'>❌ Reject</a>";
            echo "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "ℹ️ No pending payments found. You can create a test payment first.<br>\n";
    }
    
    // Handle validation/rejection actions
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $action = $_GET['action'];
        $payment_id = intval($_GET['id']);
        
        echo "<h2>Step 2: Processing payment $action</h2>\n";
        
        // Mock admin user (in real scenario, this would come from session)
        $admin_user_id = 1; // Change this to a valid admin user ID
        
        if ($action === 'validate') {
            $result = validatePayment($payment_id, $admin_user_id, 'validated', 'Test validation - payment approved automatically');
            
            if ($result > 0) {
                echo "✅ Payment validated successfully!<br>\n";
                echo "📧 Email notification should have been sent to the student.<br>\n";
            } else {
                echo "❌ Failed to validate payment.<br>\n";
            }
            
        } elseif ($action === 'reject') {
            $result = validatePayment($payment_id, $admin_user_id, 'rejected', 'Test rejection - invalid payment information');
            
            if ($result > 0) {
                echo "❌ Payment rejected successfully!<br>\n";
                echo "📧 Email notification should have been sent to the student.<br>\n";
            } else {
                echo "❌ Failed to reject payment.<br>\n";
            }
        }
        
        echo "<a href='?' style='display: inline-block; margin: 10px 0; padding: 5px 10px; background: #007cba; color: white; text-decoration: none;'>🔄 Refresh Page</a><br>\n";
    }
    
    // Show recent notifications
    echo "<h2>Step 3: Recent notifications in database</h2>\n";
    
    $stmt = $conn->prepare("
        SELECT n.*, u.email, COALESCE(sp.first_name, 'Student') as first_name
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $notifications = $stmt->get_result();
    
    if ($notifications->num_rows > 0) {
        echo "📋 Recent notifications:<br>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>\n";
        echo "<tr><th>Student</th><th>Title</th><th>Message</th><th>Type</th><th>Created</th></tr>\n";
        
        while ($notification = $notifications->fetch_assoc()) {
            $type_color = [
                'success' => 'green',
                'error' => 'red',
                'warning' => 'orange',
                'info' => 'blue'
            ];
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($notification['first_name'] . ' (' . $notification['email'] . ')') . "</td>";
            echo "<td>" . htmlspecialchars($notification['title']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($notification['message'], 0, 100)) . "...</td>";
            echo "<td style='color: " . ($type_color[$notification['type']] ?? 'black') . ";'>" . $notification['type'] . "</td>";
            echo "<td>" . $notification['created_at'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "ℹ️ No notifications found in database.<br>\n";
    }
    
    // Show email logs if available
    echo "<h2>Step 4: Email log entries</h2>\n";
    
    $log_file = '../logs/notification_emails.log';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $log_lines = explode("\n", $log_content);
        $recent_lines = array_slice($log_lines, -30); // Last 30 lines
        
        echo "📧 Recent email log entries:<br>\n";
        echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; max-height: 400px; overflow-y: auto; font-size: 12px;'>";
        echo htmlspecialchars(implode("\n", $recent_lines));
        echo "</pre>\n";
    } else {
        echo "ℹ️ Email log file not found. Emails might be sent directly via SMTP.<br>\n";
    }
    
    echo "<h2>✅ Test completed!</h2>\n";
    echo "<p><strong>Instructions:</strong></p>\n";
    echo "<ol>\n";
    echo "<li>If you see pending payments above, click on 'Validate' or 'Reject' to test the email notifications.</li>\n";
    echo "<li>Check the 'Recent notifications' section to see if database notifications were created.</li>\n";
    echo "<li>Check your email inbox (if using real SMTP) or the email logs (if using simulation mode).</li>\n";
    echo "<li>The notification emails should include the payment details and action taken.</li>\n";
    echo "</ol>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Test failed with error:</h2>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>