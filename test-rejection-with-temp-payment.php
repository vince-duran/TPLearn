<?php
/**
 * Create a test payment and then reject it to test notifications
 */

require_once 'includes/db.php';
require_once 'includes/data-helpers.php';

echo "=== Creating Test Payment for Rejection Testing ===\n";

// Find a student and program for testing
$stmt = $conn->prepare("
    SELECT u.id as user_id, u.email, 
           COALESCE(sp.first_name, 'Student') as first_name,
           e.id as enrollment_id, pr.name as program_name
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    JOIN enrollments e ON u.id = e.student_user_id
    JOIN programs pr ON e.program_id = pr.id
    WHERE u.role = 'student'
    LIMIT 1
");

$stmt->execute();
$test_data = $stmt->get_result()->fetch_assoc();

if ($test_data) {
    echo "Using test student: " . $test_data['first_name'] . " (" . $test_data['email'] . ")\n";
    echo "Using program: " . $test_data['program_name'] . "\n\n";
    
    // Create a test payment
    $stmt = $conn->prepare("
        INSERT INTO payments (enrollment_id, amount, payment_date, payment_method, reference_number, status, notes)
        VALUES (?, 500.00, CURDATE(), 'gcash', 'TEST123456', 'pending', 'Test payment for notification testing')
    ");
    $stmt->bind_param('i', $test_data['enrollment_id']);
    
    if ($stmt->execute()) {
        $test_payment_id = $conn->insert_id;
        echo "✅ Created test payment with ID: $test_payment_id\n\n";
        
        echo "Testing payment rejection...\n";
        
        try {
            // Test the validatePayment function
            $admin_user_id = 1; // Mock admin user
            $result = validatePayment(
                $test_payment_id, 
                $admin_user_id, 
                'rejected', 
                'Test rejection for notification debugging'
            );
            
            if ($result > 0) {
                echo "✅ Payment rejected successfully!\n";
                echo "✅ Affected rows: $result\n\n";
                
                // Check if notification was created
                $stmt = $conn->prepare("
                    SELECT * FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->bind_param('i', $test_data['user_id']);
                $stmt->execute();
                $notification = $stmt->get_result()->fetch_assoc();
                
                if ($notification) {
                    echo "✅ Notification created:\n";
                    echo "   - ID: " . $notification['id'] . "\n";
                    echo "   - Title: " . $notification['title'] . "\n";
                    echo "   - Message: " . substr($notification['message'], 0, 100) . "...\n";
                    echo "   - Type: " . $notification['type'] . "\n";
                    echo "   - Created: " . $notification['created_at'] . "\n\n";
                    
                    echo "✅ Email notification should have been sent to: " . $test_data['email'] . "\n";
                } else {
                    echo "❌ No notification found for this rejection!\n";
                    echo "This indicates the notification creation failed.\n";
                }
                
                // Check payment status
                $stmt = $conn->prepare("SELECT status, notes FROM payments WHERE id = ?");
                $stmt->bind_param('i', $test_payment_id);
                $stmt->execute();
                $payment_status = $stmt->get_result()->fetch_assoc();
                
                echo "Payment status after rejection: " . $payment_status['status'] . "\n";
                echo "Payment notes: " . $payment_status['notes'] . "\n";
                
            } else {
                echo "❌ Payment rejection failed - no rows affected\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Exception during payment rejection: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
        
        // Clean up - delete the test payment
        echo "\nCleaning up test payment...\n";
        $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->bind_param('i', $test_payment_id);
        $stmt->execute();
        echo "✅ Test payment deleted.\n";
        
    } else {
        echo "❌ Failed to create test payment: " . $conn->error . "\n";
    }
    
} else {
    echo "❌ No student enrollment found for testing.\n";
}

echo "\n=== Test Complete ===\n";
echo "If no notification was created, check the error logs for detailed debugging information.\n";
?>