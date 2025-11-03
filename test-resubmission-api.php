<?php
/**
 * Test Payment Resubmission API
 * Simulate the exact request that should happen when resubmitting payment 128
 */

// Start session for authentication
session_start();

// Set up authentication (simulate logged in user)
$_SESSION['user_id'] = 13; // Vince Matthew's user ID based on earlier checks
$_SESSION['role'] = 'student';

// Simulate POST data that should be sent for resubmission
$_POST = [
    'payment_id' => '128',
    'reference_number' => '2983498',
    'payment_method' => 'gcash',
    'is_resubmission' => 'true'
];

// Create a temporary file to simulate file upload
$temp_file = tempnam(sys_get_temp_dir(), 'test_receipt');
file_put_contents($temp_file, 'Test receipt content');

$_FILES = [
    'payment_receipt' => [
        'name' => 'test_receipt.png',
        'type' => 'image/png',
        'tmp_name' => $temp_file,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen('Test receipt content')
    ]
];

echo "=== Testing Payment Resubmission API ===\n";
echo "Simulating resubmission for Payment ID 128\n";
echo "Reference Number: 2983498\n";
echo "Payment Method: gcash\n";
echo "Is Resubmission: true\n\n";

// Capture output
ob_start();

try {
    // Include the API file
    include 'api/submit-payment.php';
    
    $output = ob_get_clean();
    
    echo "API Response:\n";
    echo $output . "\n\n";
    
    // Try to parse as JSON
    $response = json_decode($output, true);
    if ($response) {
        echo "Parsed Response:\n";
        echo "Success: " . ($response['success'] ? 'Yes' : 'No') . "\n";
        if (isset($response['message'])) {
            echo "Message: " . $response['message'] . "\n";
        }
        if (isset($response['error'])) {
            echo "Error: " . $response['error'] . "\n";
        }
    } else {
        echo "Response is not valid JSON\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "Exception occurred: " . $e->getMessage() . "\n";
} catch (Error $e) {
    ob_end_clean();
    echo "Error occurred: " . $e->getMessage() . "\n";
}

// Clean up
unlink($temp_file);

echo "\n=== Checking Database After Test ===\n";

// Check database connection
$conn = new mysqli("localhost", "root", "", "tplearn");
if ($conn->connect_error) {
    echo "Database connection failed: " . $conn->connect_error . "\n";
} else {
    // Check payment status after the test
    $stmt = $conn->prepare("SELECT status, reference_number, updated_at FROM payments WHERE id = 128");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        echo "Payment 128 status after test:\n";
        echo "- Status: " . $result['status'] . "\n";
        echo "- Reference: " . ($result['reference_number'] ?: 'None') . "\n";
        echo "- Updated: " . $result['updated_at'] . "\n";
    } else {
        echo "Payment 128 not found\n";
    }
    
    $conn->close();
}

echo "\n=== Test Complete ===\n";
?>