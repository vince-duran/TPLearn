<?php
/**
 * Test Payment Rejection with Enhanced Logging
 */

require_once 'includes/db.php';
require_once 'includes/data-helpers.php';

echo "=== Testing Payment Rejection with Enhanced Logging ===\n";

// Find a pending payment to test with
$stmt = $conn->prepare("
    SELECT p.id, p.amount, pr.name as program_name, e.student_user_id, u.email
    FROM payments p
    JOIN enrollments e ON p.enrollment_id = e.id
    JOIN programs pr ON e.program_id = pr.id
    JOIN users u ON e.student_user_id = u.id
    WHERE p.status = 'pending' AND p.reference_number IS NOT NULL
    LIMIT 1
");

$stmt->execute();
$test_payment = $stmt->get_result()->fetch_assoc();

if ($test_payment) {
    echo "Found test payment:\n";
    echo "- Payment ID: " . $test_payment['id'] . "\n";
    echo "- Program: " . $test_payment['program_name'] . "\n";
    echo "- Amount: ₱" . number_format($test_payment['amount'], 2) . "\n";
    echo "- Student ID: " . $test_payment['student_user_id'] . "\n";
    echo "- Student Email: " . $test_payment['email'] . "\n\n";
    
    echo "Testing payment rejection...\n";
    
    try {
        // Test the validatePayment function
        $admin_user_id = 1; // Mock admin user
        $result = validatePayment(
            $test_payment['id'], 
            $admin_user_id, 
            'rejected', 
            'Test rejection for debugging purposes'
        );
        
        if ($result > 0) {
            echo "✅ Payment rejected successfully!\n";
            echo "✅ Affected rows: $result\n";
            
            // Check if notification was created
            $stmt = $conn->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                AND title LIKE '%reject%'
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->bind_param('i', $test_payment['student_user_id']);
            $stmt->execute();
            $notification = $stmt->get_result()->fetch_assoc();
            
            if ($notification) {
                echo "✅ Notification created:\n";
                echo "   - Title: " . $notification['title'] . "\n";
                echo "   - Message: " . substr($notification['message'], 0, 100) . "...\n";
                echo "   - Type: " . $notification['type'] . "\n";
                echo "   - Created: " . $notification['created_at'] . "\n";
            } else {
                echo "❌ No notification found for this rejection!\n";
            }
            
        } else {
            echo "❌ Payment rejection failed - no rows affected\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception during payment rejection: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
} else {
    echo "❌ No pending payments found for testing.\n";
    echo "Create a payment submission first, then try this test.\n";
}

echo "\n=== Test Complete ===\n";
echo "Check the error logs for detailed debugging information.\n";
?>