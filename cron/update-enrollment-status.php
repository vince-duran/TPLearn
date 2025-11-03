<?php
/**
 * Cron job to update enrollment statuses based on payment status
 * This should be run periodically (e.g., daily) to:
 * - Pause enrollments with overdue installment payments
 * - Update enrollment statuses based on payment validation
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/data-helpers.php';

echo "=== ENROLLMENT STATUS UPDATE CRON JOB ===" . PHP_EOL;
echo "Started at: " . date('Y-m-d H:i:s') . PHP_EOL;

$updates_made = 0;

try {
    // 1. Update payment statuses with 3-day grace period
    echo PHP_EOL . "Updating payment statuses with grace period..." . PHP_EOL;
    
    // Mark payments as overdue (within grace period)
    $overdue_sql = "UPDATE payments 
                    SET status = 'overdue', updated_at = NOW() 
                    WHERE status = 'pending' 
                      AND due_date < CURDATE()
                      AND due_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
    
    $result_overdue = $conn->query($overdue_sql);
    $overdue_updated = $conn->affected_rows;
    echo "Updated $overdue_updated payments to overdue status (grace period)" . PHP_EOL;
    $updates_made += $overdue_updated;

    // Mark payments as locked (beyond grace period)
    $locked_sql = "UPDATE payments 
                   SET status = 'locked', updated_at = NOW() 
                   WHERE status IN ('pending', 'overdue') 
                     AND due_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
    
    $result_locked = $conn->query($locked_sql);
    $locked_updated = $conn->affected_rows;
    echo "Updated $locked_updated payments to locked status (beyond grace period)" . PHP_EOL;
    $updates_made += $locked_updated;

    // 2. Lock enrollments with locked payments
    echo PHP_EOL . "Checking for enrollments to lock due to overdue payments..." . PHP_EOL;
    $locked_count = checkAndUpdateEnrollmentStatusForOverduePayments();
    echo "Locked $locked_count enrollments due to payments beyond grace period" . PHP_EOL;
    $updates_made += $locked_count;

    // 3. Unlock enrollments where payments have been settled
    echo PHP_EOL . "Checking for enrollments to unlock due to settled payments..." . PHP_EOL;
    $unlocked_count = checkAndUnlockEnrollmentsForSettledPayments();
    echo "Unlocked $unlocked_count enrollments due to settled payments" . PHP_EOL;
    $updates_made += $unlocked_count;

    // 3. Log completion
    if ($updates_made > 0) {
        echo PHP_EOL . "Total updates made: $updates_made" . PHP_EOL;
        error_log("Enrollment status cron job completed: $updates_made updates made");
    } else {
        echo PHP_EOL . "No updates needed" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    error_log("Enrollment status cron job error: " . $e->getMessage());
}

echo "Completed at: " . date('Y-m-d H:i:s') . PHP_EOL;
?>
