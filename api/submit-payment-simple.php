<?php
// Simple, clean payment submission API
ini_set('display_errors', 0);
error_reporting(0);

// Start session
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tplearn");
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    // Get form data
    $payment_id = $_POST['payment_id'] ?? '';
    $reference_number = $_POST['reference_number'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $is_resubmission = ($_POST['is_resubmission'] ?? 'false') === 'true';
    
    // Validate required fields
    if (!$payment_id || !$reference_number || !$payment_method) {
        throw new Exception('Missing required fields');
    }
    
    // Extract numeric payment ID
    if (preg_match('/PAY-\d{8}-(\d+)/', $payment_id, $matches)) {
        $numeric_payment_id = intval($matches[1]);
    } else {
        $numeric_payment_id = intval($payment_id);
    }
    
    // Verify payment belongs to user
    $stmt = $conn->prepare("SELECT p.id, p.status, e.student_user_id, pr.name as program_name 
                           FROM payments p 
                           JOIN enrollments e ON p.enrollment_id = e.id 
                           JOIN programs pr ON e.program_id = pr.id 
                           WHERE p.id = ? AND e.student_user_id = ?");
    $stmt->bind_param('ii', $numeric_payment_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception('Payment not found');
    }
    
    $payment_data = $result->fetch_assoc();
    
    // Handle file upload
    $receipt_filename = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/payment_receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extension = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg'])) {
            throw new Exception('Invalid file type');
        }
        
        $receipt_filename = 'receipt_' . $numeric_payment_id . '_' . time() . '.' . $extension;
        $receipt_path = $upload_dir . $receipt_filename;
        
        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $receipt_path)) {
            throw new Exception('File upload failed');
        }
    }
    
    // Update payment
    $conn->autocommit(false);
    
    $update_sql = "UPDATE payments SET reference_number = ?, payment_method = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('ssi', $reference_number, $payment_method, $numeric_payment_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update payment');
    }
    
    // Insert attachment record if file uploaded
    if ($receipt_filename) {
        // Determine MIME type from file extension
        $mime_type = 'application/octet-stream'; // default
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $mime_type = 'image/jpeg';
                break;
            case 'png':
                $mime_type = 'image/png';
                break;
            case 'gif':
                $mime_type = 'image/gif';
                break;
            case 'pdf':
                $mime_type = 'application/pdf';
                break;
        }
        
        $attach_sql = "INSERT INTO payment_attachments (payment_id, filename, original_filename, file_size, mime_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $attach_stmt = $conn->prepare($attach_sql);
        $attach_stmt->bind_param('issis', $numeric_payment_id, $receipt_filename, $_FILES['receipt']['name'], $_FILES['receipt']['size'], $mime_type);
        $attach_stmt->execute();
    }
    
    $conn->commit();
    $conn->autocommit(true);
    
    // Return success
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Payment submitted successfully',
        'payment_id' => $payment_id,
        'program_name' => $payment_data['program_name']
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->autocommit(true);
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>