<?php
/**
 * Create a test payment that can be rejected from admin dashboard
 */

require_once 'includes/db.php';

echo "Creating a test payment that can be rejected from admin dashboard...\n\n";

// Find a student and enrollment
$stmt = $conn->prepare("
    SELECT u.id as user_id, u.email, 
           COALESCE(sp.first_name, 'Student') as first_name,
           e.id as enrollment_id, pr.name as program_name
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    JOIN enrollments e ON u.id = e.student_user_id
    JOIN programs pr ON e.program_id = pr.id
    WHERE u.role = 'student'
    ORDER BY u.id DESC
    LIMIT 1
");

$stmt->execute();
$test_data = $stmt->get_result()->fetch_assoc();

if ($test_data) {
    // Create a test payment that will appear in admin dashboard
    $stmt = $conn->prepare("
        INSERT INTO payments (enrollment_id, amount, payment_date, payment_method, reference_number, status, notes)
        VALUES (?, 750.00, CURDATE(), 'gcash', 'TESTREF789', 'pending', 'Test payment - please reject this from admin dashboard to test email notifications')
    ");
    $stmt->bind_param('i', $test_data['enrollment_id']);
    
    if ($stmt->execute()) {
        $payment_id = $conn->insert_id;
        
        echo "✅ Test payment created successfully!\n";
        echo "Payment ID: " . $payment_id . "\n";
        echo "Formatted ID: PAY-" . date('Ymd') . "-" . str_pad($payment_id, 3, '0', STR_PAD_LEFT) . "\n";
        echo "Student: " . $test_data['first_name'] . " (" . $test_data['email'] . ")\n";
        echo "Program: " . $test_data['program_name'] . "\n";
        echo "Amount: ₱750.00\n";
        echo "Reference: TESTREF789\n";
        echo "Status: pending\n\n";
        
        echo "📋 Instructions:\n";
        echo "1. Go to Admin Dashboard > Payments\n";
        echo "2. Find the payment with ID: PAY-" . date('Ymd') . "-" . str_pad($payment_id, 3, '0', STR_PAD_LEFT) . "\n";
        echo "3. Click 'Validate' or 'Details' to open the validation modal\n";
        echo "4. Click 'Reject Payment' and provide a reason\n";
        echo "5. Check the student's email: " . $test_data['email'] . "\n";
        echo "6. Check the notifications table for the new notification\n\n";
        
        echo "✅ The email notification system is now active and should work!\n";
        
    } else {
        echo "❌ Failed to create test payment: " . $conn->error . "\n";
    }
} else {
    echo "❌ No student enrollment found for testing.\n";
}
?>