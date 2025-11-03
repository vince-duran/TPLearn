<?php
/**
 * Check specific payment status after resubmission
 */

require_once 'includes/db.php';

echo "=== Checking Payment Resubmission Status ===\n";

// Check the specific payment from the screenshot (ID 128 with new reference 2983498)
$stmt = $conn->prepare("
    SELECT p.id, p.status, p.reference_number, p.payment_method, p.amount,
           p.created_at, p.updated_at, p.notes,
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
    echo "Payment ID 128 Current Status:\n";
    echo "- Status: " . $payment_info['status'] . "\n";
    echo "- Reference Number: " . ($payment_info['reference_number'] ?: 'None') . "\n";
    echo "- Payment Method: " . $payment_info['payment_method'] . "\n";
    echo "- Amount: ₱" . number_format($payment_info['amount'], 2) . "\n";
    echo "- Student: " . $payment_info['first_name'] . " (" . $payment_info['email'] . ")\n";
    echo "- Program: " . $payment_info['program_name'] . "\n";
    echo "- Created: " . $payment_info['created_at'] . "\n";
    echo "- Last Updated: " . $payment_info['updated_at'] . "\n";
    echo "- Notes: " . ($payment_info['notes'] ?: 'None') . "\n\n";
    
    // Check if there are any payment attachments for this payment
    echo "=== Payment Attachments ===\n";
    $stmt = $conn->prepare("
        SELECT id, filename, original_filename, file_size, mime_type, created_at
        FROM payment_attachments 
        WHERE payment_id = 128
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $attachments = $stmt->get_result();
    
    if ($attachments->num_rows > 0) {
        echo "Found " . $attachments->num_rows . " attachment(s):\n";
        while ($attachment = $attachments->fetch_assoc()) {
            echo "- " . $attachment['created_at'] . ": " . $attachment['original_filename'] . 
                 " (" . number_format($attachment['file_size']/1024, 1) . " KB)\n";
            echo "  File: " . $attachment['filename'] . "\n";
            echo "  Type: " . $attachment['mime_type'] . "\n\n";
        }
    } else {
        echo "No payment attachments found.\n\n";
    }
    
    // Check payment history
    echo "=== Payment History ===\n";
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
                 " (" . ($row['old_status'] ?: 'N/A') . " → " . ($row['new_status'] ?: 'N/A') . ")";
            if ($row['username']) echo " by " . $row['username'];
            if ($row['notes']) echo " - " . $row['notes'];
            echo "\n";
        }
    } else {
        echo "No payment history found.\n";
    }
    
} else {
    echo "❌ Payment ID 128 not found!\n";
}

echo "\n=== Checking Recent Payment Submissions ===\n";

// Check for any recent payment submissions
$stmt = $conn->prepare("
    SELECT p.id, p.status, p.reference_number, p.amount, p.updated_at,
           pr.name as program_name, u.email
    FROM payments p
    JOIN enrollments e ON p.enrollment_id = e.id
    JOIN programs pr ON e.program_id = pr.id
    JOIN users u ON e.student_user_id = u.id
    WHERE p.updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    AND p.reference_number IS NOT NULL
    ORDER BY p.updated_at DESC
    LIMIT 10
");

$stmt->execute();
$recent_submissions = $stmt->get_result();

if ($recent_submissions->num_rows > 0) {
    echo "Recent payment submissions (last hour):\n";
    while ($submission = $recent_submissions->fetch_assoc()) {
        echo "- Payment " . $submission['id'] . ": " . $submission['status'] . 
             " (Ref: " . $submission['reference_number'] . ") - " . $submission['updated_at'] . "\n";
        echo "  Program: " . $submission['program_name'] . " | Student: " . $submission['email'] . "\n\n";
    }
} else {
    echo "No recent payment submissions found in the last hour.\n";
}

echo "\n=== System Status Check ===\n";

// Check if there are any database connection issues
if ($conn->connect_error) {
    echo "❌ Database connection error: " . $conn->connect_error . "\n";
} else {
    echo "✅ Database connection is working.\n";
}

// Check if payment submission API is accessible
$api_file = 'api/submit-payment.php';
if (file_exists($api_file)) {
    echo "✅ Payment submission API file exists.\n";
} else {
    echo "❌ Payment submission API file not found at: $api_file\n";
}

echo "\n=== Recommendations ===\n";

if ($payment_info) {
    if ($payment_info['status'] === 'rejected') {
        echo "🔄 Your payment was previously rejected and may need to be resubmitted.\n";
        echo "   Make sure you submitted a new payment receipt with reference: 2983498\n";
    } elseif ($payment_info['status'] === 'pending') {
        echo "⏳ Your payment is pending validation by an administrator.\n";
        echo "   The admin will review and approve/reject your payment soon.\n";
    } elseif ($payment_info['status'] === 'validated') {
        echo "✅ Your payment has been validated and approved.\n";
    }
    
    if ($payment_info['reference_number'] === '2983498') {
        echo "✅ Your new reference number (2983498) is recorded in the system.\n";
    } else {
        echo "❌ The new reference number (2983498) is not showing in the database.\n";
        echo "   This suggests the resubmission may not have been processed correctly.\n";
    }
} else {
    echo "❌ Payment not found. There may be a system issue.\n";
}

echo "\n=== END STATUS CHECK ===\n";
?>