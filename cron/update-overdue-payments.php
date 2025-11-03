<?php
/**
 * Cron job to update overdue payments
 * Run this script daily to automatically mark payments as overdue
 * 
 * Schedule: 0 0 * * * (daily at midnight)
 * Command: php /path/to/TPLearn/cron/update-overdue-payments.php
 */

require_once __DIR__ . '/../includes/db.php';

echo "=== TPLearn Overdue Payment Update ===" . PHP_EOL;
echo "Started at: " . date('Y-m-d H:i:s') . PHP_EOL;

try {
    // Update payments that are past due date and still pending (grace period: overdue but not locked)
    $sql_overdue = "UPDATE payments 
                    SET status = 'overdue', 
                        updated_at = CURRENT_TIMESTAMP
                    WHERE status = 'pending' 
                    AND due_date < CURDATE()
                    AND due_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
    
    $result_overdue = $conn->query($sql_overdue);
    
    // Update payments that are overdue beyond 3-day grace period to 'locked'
    $sql_locked = "UPDATE payments 
                   SET status = 'locked', 
                       updated_at = CURRENT_TIMESTAMP
                   WHERE status IN ('pending', 'overdue') 
                   AND due_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
    
    $result_locked = $conn->query($sql_locked);
    
    $result = $result_overdue && $result_locked;
    
    if ($result) {
        $overdue_rows = $conn->affected_rows;
        $locked_rows = 0;
        
        // Get affected rows for locked payments
        if ($result_locked) {
            $locked_query = "SELECT ROW_COUNT() as locked_count";
            $locked_result = $conn->query($locked_query);
            if ($locked_result && $locked_data = $locked_result->fetch_assoc()) {
                // We need to track this differently since ROW_COUNT() gets reset
                $locked_count_sql = "SELECT COUNT(*) as count FROM payments 
                                     WHERE status = 'locked' 
                                     AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
                $locked_count_result = $conn->query($locked_count_sql);
                if ($locked_count_result && $locked_count_data = $locked_count_result->fetch_assoc()) {
                    $locked_rows = $locked_count_data['count'];
                }
            }
        }
        
        echo "Successfully updated {$overdue_rows} payments to overdue status" . PHP_EOL;
        echo "Successfully updated {$locked_rows} payments to locked status (beyond 3-day grace period)" . PHP_EOL;
        
        if ($overdue_rows > 0 || $locked_rows > 0) {
            // Log the recently updated payments for reference
            $recent_query = "SELECT p.id, p.amount, p.due_date, p.status,
                                    DATEDIFF(CURDATE(), p.due_date) as days_overdue,
                                    CONCAT(u.first_name, ' ', u.last_name) as student_name,
                                    pr.name as program_name
                             FROM payments p
                             JOIN enrollments e ON p.enrollment_id = e.id
                             JOIN users u ON e.student_user_id = u.id
                             JOIN programs pr ON e.program_id = pr.id
                             WHERE p.status IN ('overdue', 'locked')
                             AND p.updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                             ORDER BY p.status, p.due_date";
            
            $recent_result = $conn->query($recent_query);
            
            if ($recent_result) {
                echo "\nRecently updated payment statuses:" . PHP_EOL;
                echo str_repeat("-", 90) . PHP_EOL;
                printf("%-10s %-18s %-13s %-10s %-8s %-8s %-12s\n", "Payment ID", "Student", "Program", "Amount", "Status", "Days", "Due Date");
                echo str_repeat("-", 90) . PHP_EOL;
                
                while ($row = $recent_result->fetch_assoc()) {
                    printf("%-10s %-18s %-13s $%-9.2f %-8s %-8s %-12s\n", 
                        $row['id'], 
                        substr($row['student_name'], 0, 16),
                        substr($row['program_name'], 0, 11),
                        floatval($row['amount']),
                        strtoupper($row['status']),
                        $row['days_overdue'],
                        $row['due_date']
                    );
                }
                echo str_repeat("-", 90) . PHP_EOL;
            }
        }
        
        // Get summary statistics
        $stats_query = "SELECT 
                            COUNT(CASE WHEN status = 'overdue' THEN 1 END) as total_overdue,
                            COUNT(CASE WHEN status = 'locked' THEN 1 END) as total_locked,
                            COUNT(CASE WHEN status = 'pending' THEN 1 END) as total_pending,
                            COUNT(CASE WHEN status = 'validated' THEN 1 END) as total_validated,
                            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as total_rejected,
                            SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as overdue_amount,
                            SUM(CASE WHEN status = 'locked' THEN amount ELSE 0 END) as locked_amount
                        FROM payments";
        
        $stats_result = $conn->query($stats_query);
        if ($stats_result && $stats = $stats_result->fetch_assoc()) {
            echo "\nPayment Status Summary:" . PHP_EOL;
            echo "- Overdue (Grace Period): {$stats['total_overdue']} payments (₱" . number_format($stats['overdue_amount'], 2) . ")" . PHP_EOL;
            echo "- Locked (Beyond Grace): {$stats['total_locked']} payments (₱" . number_format($stats['locked_amount'], 2) . ")" . PHP_EOL;
            echo "- Pending: {$stats['total_pending']} payments" . PHP_EOL;
            echo "- Validated: {$stats['total_validated']} payments" . PHP_EOL;
            echo "- Rejected: {$stats['total_rejected']} payments" . PHP_EOL;
        }
        
    } else {
        throw new Exception("Database error: " . $conn->error);
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "\nCompleted at: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "===================================" . PHP_EOL;
?>