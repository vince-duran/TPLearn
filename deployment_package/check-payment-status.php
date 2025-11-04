<?php
/**
 * Check payment status transition for debugging
 */

require_once 'includes/db.php';

echo "=== Payment Status Investigation ===\n";

// Check the specific rejected payment from the screenshot
$stmt = $conn->prepare("
    SELECT p.id, p.status, p.created_at, p.updated_at, p.notes,
           pr.name as program_name,
           u.email, COALESCE(sp.first_name, 'Student') as first_name
    FROM payments p
    JOIN enrollments e ON p.enrollment_id = e.id
    JOIN programs pr ON e.program_id = pr.id
    JOIN users u ON e.student_user_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE p.id = 128
");

$stmt->execute();
$payment_info = $stmt->get_result()->fetch_assoc();

if ($payment_info) {
    echo "Payment ID 128 Details:\n";
    echo "- Current Status: " . $payment_info['status'] . "\n";
    echo "- Student: " . $payment_info['first_name'] . " (" . $payment_info['email'] . ")\n";
    echo "- Program: " . $payment_info['program_name'] . "\n";
    echo "- Created: " . $payment_info['created_at'] . "\n";
    echo "- Updated: " . $payment_info['updated_at'] . "\n";
    echo "- Rejection Notes: " . ($payment_info['notes'] ?: 'None') . "\n\n";
} else {
    echo "Payment ID 128 not found!\n\n";
}

// Check payment history for this payment
if (class_exists('mysqli') && $conn) {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'payment_history'");
    $stmt->execute();
    $table_exists = $stmt->get_result()->num_rows > 0;
    
    if ($table_exists) {
        echo "=== Payment History for Payment 128 ===\n";
        $stmt = $conn->prepare("
            SELECT ph.*, u.username
            FROM payment_history ph
            LEFT JOIN users u ON ph.performed_by = u.id
            WHERE ph.payment_id = 128
            ORDER BY ph.created_at DESC
        ");
        $stmt->execute();
        $history = $stmt->get_result();
        
        if ($history->num_rows > 0) {
            while ($row = $history->fetch_assoc()) {
                echo "- " . $row['created_at'] . ": " . $row['action'] . 
                     " (" . $row['old_status'] . " → " . $row['new_status'] . ")";
                if ($row['username']) echo " by " . $row['username'];
                if ($row['notes']) echo " - " . $row['notes'];
                echo "\n";
            }
        } else {
            echo "No payment history found for payment 128\n";
        }
    } else {
        echo "Payment history table does not exist\n";
    }
}

echo "\n=== Checking Notifications for Student ===\n";

if ($payment_info) {
    // Get student ID from the payment
    $stmt = $conn->prepare("
        SELECT e.student_user_id 
        FROM payments p
        JOIN enrollments e ON p.enrollment_id = e.id
        WHERE p.id = 128
    ");
    $stmt->execute();
    $student_data = $stmt->get_result()->fetch_assoc();
    
    if ($student_data) {
        $student_id = $student_data['student_user_id'];
        echo "Student ID: $student_id\n";
        
        // Check notifications for this student
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $notifications = $stmt->get_result();
        
        if ($notifications->num_rows > 0) {
            echo "Recent notifications for this student:\n";
            while ($notif = $notifications->fetch_assoc()) {
                echo "- " . $notif['created_at'] . ": " . $notif['title'] . 
                     " (Type: " . $notif['type'] . ")\n";
                echo "  Message: " . substr($notif['message'], 0, 100) . "...\n";
            }
        } else {
            echo "No notifications found for this student!\n";
        }
    }
}

echo "\n=== END INVESTIGATION ===\n";
?>