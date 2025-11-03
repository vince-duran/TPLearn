<?php
/**
 * Add payment history entry
 */
function addPaymentHistoryEntry($payment_id, $action, $old_status = null, $new_status = null, $performed_by = null, $notes = null) {
    global $conn;
    
    try {
        $sql = "INSERT INTO payment_history (payment_id, action, old_status, new_status, performed_by, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $stmt->bind_param('isssis', $payment_id, $action, $old_status, $new_status, $performed_by, $notes);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute statement: ' . $stmt->error);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error adding payment history: " . $e->getMessage());
        return false;
    }
}

/**
 * Log payment creation
 */
function logPaymentCreated($payment_id, $program_name, $amount) {
    addPaymentHistoryEntry(
        $payment_id,
        'created',
        null,
        'pending',
        null, // System created
        "Payment obligation created for {$program_name} - Amount: ₱{$amount}"
    );
}

/**
 * Log payment submission
 */
function logPaymentSubmitted($payment_id, $reference_number, $performed_by) {
    addPaymentHistoryEntry(
        $payment_id,
        'payment_submitted',
        'pending',
        'pending_validation',
        $performed_by,
        "Payment proof submitted with reference: {$reference_number}"
    );
}

/**
 * Log payment validation
 */
function logPaymentValidated($payment_id, $old_status, $performed_by, $notes = null) {
    addPaymentHistoryEntry(
        $payment_id,
        'validated',
        $old_status,
        'validated',
        $performed_by,
        $notes ?: 'Payment validated and approved'
    );
}

/**
 * Log payment rejection
 */
function logPaymentRejected($payment_id, $old_status, $performed_by, $notes = null) {
    addPaymentHistoryEntry(
        $payment_id,
        'rejected',
        $old_status,
        'rejected',
        $performed_by,
        $notes ?: 'Payment rejected - please resubmit with correct information'
    );
}

/**
 * Log payment resubmission
 */
function logPaymentResubmitted($payment_id, $reference_number, $performed_by) {
    addPaymentHistoryEntry(
        $payment_id,
        'resubmitted',
        'rejected',
        'pending_validation',
        $performed_by,
        "Payment resubmitted with new reference: {$reference_number}"
    );
}

/**
 * Get actual payment history from database
 */
function getActualPaymentHistory($payment_id) {
    global $conn;
    
    try {
        // First get the payment details
        $sql = "SELECT 
                   p.id,
                   CONCAT('PAY-', DATE_FORMAT(p.created_at, '%Y%m%d'), '-', LPAD(p.id, 3, '0')) as payment_id,
                   e.id as enrollment_id,
                   u.username as student_username,
                   COALESCE(CONCAT(sp.first_name, ' ', sp.last_name), CONCAT(u.username, ' (Student)')) as student_name,
                   sp.student_id,
                   pr.name as program_name,
                   p.amount,
                   p.payment_method,
                   p.payment_date,
                   p.due_date,
                   p.reference_number,
                   p.notes,
                   p.installment_number,
                   p.total_installments,
                   p.status,
                   v.username as validated_by_username,
                   COALESCE(CONCAT(vp.first_name, ' ', vp.last_name), v.username, 'System') as validated_by_name,
                   p.validated_at,
                   p.created_at
            FROM payments p
            LEFT JOIN enrollments e ON p.enrollment_id = e.id
            LEFT JOIN users u ON e.student_user_id = u.id
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            LEFT JOIN programs pr ON e.program_id = pr.id
            LEFT JOIN users v ON p.validated_by = v.id
            LEFT JOIN tutor_profiles vp ON v.id = vp.user_id
            WHERE CONCAT('PAY-', DATE_FORMAT(p.created_at, '%Y%m%d'), '-', LPAD(p.id, 3, '0')) = ?";
            
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare payment query: ' . $conn->error);
        }
        
        $stmt->bind_param('s', $payment_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute payment query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        if (!$payment) {
            return ['error' => 'Payment not found with ID: ' . $payment_id];
        }
        
        // Get the actual payment history from payment_history table
        $history_sql = "SELECT 
                           ph.action,
                           ph.old_status,
                           ph.new_status,
                           ph.notes,
                           ph.created_at as timestamp,
                           COALESCE(
                               CONCAT(sp.first_name, ' ', sp.last_name),
                               CONCAT(tp.first_name, ' ', tp.last_name), 
                               u.username,
                               'System'
                           ) as performed_by
                        FROM payment_history ph
                        LEFT JOIN users u ON ph.performed_by = u.id
                        LEFT JOIN student_profiles sp ON u.id = sp.user_id
                        LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
                        WHERE ph.payment_id = ?
                        ORDER BY ph.created_at ASC";
        
        $history_stmt = $conn->prepare($history_sql);
        if (!$history_stmt) {
            throw new Exception('Failed to prepare history query: ' . $conn->error);
        }
        
        $history_stmt->bind_param('i', $payment['id']);
        if (!$history_stmt->execute()) {
            throw new Exception('Failed to execute history query: ' . $history_stmt->error);
        }
        
        $history_result = $history_stmt->get_result();
        $history = [];
        
        while ($row = $history_result->fetch_assoc()) {
            $history[] = [
                'timestamp' => $row['timestamp'],
                'action' => $row['action'],
                'old_status' => $row['old_status'],
                'new_status' => $row['new_status'],
                'status' => $row['new_status'], // For compatibility
                'performed_by' => $row['performed_by'],
                'notes' => $row['notes'],
                'amount' => $payment['amount'],
                'reference_number' => $payment['reference_number']
            ];
        }
        
        // If no history exists, create basic events from payment data (for backwards compatibility)
        if (empty($history)) {
            $history = createLegacyHistory($payment);
        }
        
        return [
            'payment' => $payment,
            'history' => $history,
            'total_events' => count($history)
        ];
        
    } catch (Exception $e) {
        error_log("Error getting payment history for payment_id '$payment_id': " . $e->getMessage());
        return ['error' => 'Database error occurred: ' . $e->getMessage()];
    }
}

/**
 * Create legacy history for payments without proper tracking (backwards compatibility)
 */
function createLegacyHistory($payment) {
    $history = [];
    
    // Always add creation event
    $history[] = [
        'timestamp' => $payment['created_at'],
        'action' => 'created',
        'old_status' => null,
        'new_status' => 'pending',
        'status' => 'pending',
        'performed_by' => 'System',
        'notes' => 'Payment obligation created for ' . $payment['program_name'] . ' - Amount: ₱' . number_format($payment['amount'], 2),
        'amount' => $payment['amount'],
        'reference_number' => null
    ];
    
    // Add submission if reference exists
    if (!empty($payment['reference_number'])) {
        $submission_time = $payment['payment_date'] ?: $payment['created_at'];
        $history[] = [
            'timestamp' => $submission_time,
            'action' => 'payment_submitted',
            'old_status' => 'pending',
            'new_status' => 'pending_validation',
            'status' => 'pending_validation',
            'performed_by' => $payment['student_name'],
            'notes' => 'Payment proof submitted with reference: ' . $payment['reference_number'],
            'amount' => $payment['amount'],
            'reference_number' => $payment['reference_number']
        ];
    }
    
    // Add final status based on current payment status
    if ($payment['status'] === 'validated' && $payment['validated_at']) {
        $history[] = [
            'timestamp' => $payment['validated_at'],
            'action' => 'validated',
            'old_status' => 'pending_validation',
            'new_status' => 'validated',
            'status' => 'validated',
            'performed_by' => $payment['validated_by_name'],
            'notes' => 'Payment validated and approved',
            'amount' => $payment['amount'],
            'reference_number' => $payment['reference_number']
        ];
    } elseif ($payment['status'] === 'rejected') {
        $history[] = [
            'timestamp' => $payment['validated_at'] ?: $payment['created_at'],
            'action' => 'rejected',
            'old_status' => !empty($payment['reference_number']) ? 'pending_validation' : 'pending',
            'new_status' => 'rejected',
            'status' => 'rejected',
            'performed_by' => (!empty($payment['validated_by_name']) ? $payment['validated_by_name'] : 'Admin'),
            'notes' => (!empty($payment['notes']) ? $payment['notes'] : 'Payment rejected - please resubmit with correct information'),
            'amount' => $payment['amount'],
            'reference_number' => $payment['reference_number']
        ];
    } elseif ($payment['status'] === 'pending' && !empty($payment['reference_number'])) {
        // Payment is submitted but not yet validated/rejected
        $current_time = date('Y-m-d H:i:s');
        $history[] = [
            'timestamp' => $current_time,
            'action' => 'pending_review',
            'old_status' => 'pending_validation',
            'new_status' => 'pending_validation',
            'status' => 'pending_validation',
            'performed_by' => 'System',
            'notes' => 'Payment proof under review by admin',
            'amount' => $payment['amount'],
            'reference_number' => $payment['reference_number']
        ];
    }
    
    return $history;
}
?>