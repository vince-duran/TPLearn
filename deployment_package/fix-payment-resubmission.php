<?php
/**
 * Direct test of payment resubmission logic
 */

require_once 'includes/db.php';

echo "=== Direct Payment Resubmission Test ===\n";

// Test data
$payment_id = 128;
$new_reference = '2983498';
$payment_method = 'gcash';

echo "Testing resubmission for Payment ID: $payment_id\n";
echo "New reference number: $new_reference\n";
echo "Payment method: $payment_method\n\n";

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check current payment status
    $stmt = $conn->prepare("
        SELECT id, status, reference_number, amount, enrollment_id
        FROM payments 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if (!$payment) {
        throw new Exception("Payment not found");
    }
    
    echo "Current payment status:\n";
    echo "- Status: " . $payment['status'] . "\n";
    echo "- Current reference: " . ($payment['reference_number'] ?: 'None') . "\n";
    echo "- Amount: ₱" . number_format($payment['amount'], 2) . "\n\n";
    
    if ($payment['status'] !== 'rejected') {
        throw new Exception("Payment must be rejected to resubmit. Current status: " . $payment['status']);
    }
    
    // Update payment for resubmission
    $update_sql = "UPDATE payments 
                   SET reference_number = ?, payment_method = ?, status = 'pending', notes = NULL, updated_at = NOW()
                   WHERE id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('ssi', $new_reference, $payment_method, $payment_id);
    
    if ($update_stmt->execute()) {
        $affected_rows = $update_stmt->affected_rows;
        echo "✅ Payment updated successfully!\n";
        echo "Affected rows: $affected_rows\n\n";
        
        // Commit transaction
        $conn->commit();
        
        // Verify the update
        $stmt = $conn->prepare("
            SELECT status, reference_number, payment_method, updated_at
            FROM payments 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $updated_payment = $stmt->get_result()->fetch_assoc();
        
        echo "Updated payment status:\n";
        echo "- Status: " . $updated_payment['status'] . "\n";
        echo "- Reference: " . $updated_payment['reference_number'] . "\n";
        echo "- Method: " . $updated_payment['payment_method'] . "\n";
        echo "- Updated: " . $updated_payment['updated_at'] . "\n\n";
        
        // Send notification (if the notification system is working)
        try {
            require_once 'includes/notification-helpers.php';
            
            // Get student and program info
            $stmt = $conn->prepare("
                SELECT e.student_user_id, pr.name as program_name
                FROM payments p
                JOIN enrollments e ON p.enrollment_id = e.id
                JOIN programs pr ON e.program_id = pr.id
                WHERE p.id = ?
            ");
            $stmt->bind_param('i', $payment_id);
            $stmt->execute();
            $info = $stmt->get_result()->fetch_assoc();
            
            if ($info) {
                $title = 'Payment Submitted for Review';
                $message = "Your payment of ₱" . number_format($payment['amount'], 2) . " for {$info['program_name']} has been submitted and is under review.";
                
                $notification_result = createNotification(
                    $info['student_user_id'], 
                    $title, 
                    $message, 
                    'info', 
                    'dashboards/student/student-payments.php'
                );
                
                if ($notification_result['success']) {
                    echo "✅ Notification sent successfully!\n";
                    echo "Email sent: " . ($notification_result['email_sent'] ? 'Yes' : 'No') . "\n";
                } else {
                    echo "❌ Failed to send notification: " . $notification_result['error'] . "\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ Notification error: " . $e->getMessage() . "\n";
        }
        
    } else {
        throw new Exception("Failed to update payment: " . $conn->error);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "The payment should now show as 'pending' in the admin dashboard.\n";
?>