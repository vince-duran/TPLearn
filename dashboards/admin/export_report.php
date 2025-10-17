<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data-helpers.php';
requireRole('admin');

// Get parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-30');
$report_type = $_GET['report_type'] ?? 'payment';
$format = $_GET['format'] ?? 'csv';

// Set headers for download
$filename = "{$report_type}_report_" . date('Y-m-d') . "_{$start_date}_to_{$end_date}";

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch ($report_type) {
        case 'payment':
            // Generate payment report CSV
            fputcsv($output, ['Payment Report - ' . $start_date . ' to ' . $end_date]);
            fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
            fputcsv($output, []); // Empty row
            
            // Get data
            $paymentSummary = getPaymentSummaryData($start_date, $end_date);
            $recentPayments = getRecentPayments(100); // Get more for export
            
            // Summary section
            fputcsv($output, ['PAYMENT SUMMARY']);
            fputcsv($output, ['Total Revenue Collected', '₱' . number_format($paymentSummary['total_revenue'], 2)]);
            fputcsv($output, ['Outstanding Payments', '₱' . number_format($paymentSummary['outstanding_payments'], 2)]);
            fputcsv($output, ['Payment Completion Rate', $paymentSummary['completion_rate'] . '%']);
            fputcsv($output, ['Total Transactions', $paymentSummary['total_transactions']]);
            fputcsv($output, ['Validated Payments', $paymentSummary['validated_count']]);
            fputcsv($output, ['Pending Payments', $paymentSummary['pending_count']]);
            fputcsv($output, ['Rejected Payments', $paymentSummary['rejected_count']]);
            fputcsv($output, []); // Empty row
            
            // Detailed payments
            fputcsv($output, ['DETAILED PAYMENTS']);
            fputcsv($output, ['Student Name', 'Program', 'Amount', 'Status', 'Payment Date']);
            
            foreach ($recentPayments as $payment) {
                fputcsv($output, [
                    $payment['student_name'],
                    $payment['program_name'],
                    '₱' . number_format($payment['amount'], 2),
                    ucfirst($payment['status']),
                    date('M j, Y', strtotime($payment['payment_date']))
                ]);
            }
            break;
            
        case 'enrollment':
            // Generate enrollment report CSV
            fputcsv($output, ['Enrollment Report - ' . $start_date . ' to ' . $end_date]);
            fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
            fputcsv($output, []); // Empty row
            
            $enrollmentStats = getEnrollmentStats();
            
            fputcsv($output, ['ENROLLMENT SUMMARY']);
            fputcsv($output, ['Total Enrollments', $enrollmentStats['total_enrollments']]);
            fputcsv($output, ['Active Students', $enrollmentStats['active_students']]);
            fputcsv($output, ['Completion Rate', $enrollmentStats['completion_rate'] . '%']);
            fputcsv($output, ['Enrollment Growth', $enrollmentStats['enrollment_growth'] . '%']);
            fputcsv($output, ['Students Growth', $enrollmentStats['students_growth'] . '%']);
            fputcsv($output, ['Completion Growth', $enrollmentStats['completion_growth'] . '%']);
            break;
            
        case 'schedule':
            // Generate schedule report CSV
            fputcsv($output, ['Schedule Report - ' . $start_date . ' to ' . $end_date]);
            fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
            fputcsv($output, []); // Empty row
            
            $scheduleStats = getScheduleStats();
            
            fputcsv($output, ['SCHEDULE SUMMARY']);
            fputcsv($output, ['Total Schedules', $scheduleStats['total_schedules']]);
            fputcsv($output, ['Occupied', $scheduleStats['occupied']]);
            fputcsv($output, ['Available', $scheduleStats['available']]);
            fputcsv($output, ['Occupancy Rate', $scheduleStats['occupancy_rate'] . '%']);
            break;
    }
    
    fclose($output);
    
} else if ($format === 'pdf') {
    // For PDF, we'll create an HTML version that can be converted to PDF
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= ucfirst($report_type) ?> Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .summary { margin-bottom: 30px; }
            .stat { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            .amount { text-align: right; }
            .status-validated { color: #059669; font-weight: bold; }
            .status-pending { color: #d97706; font-weight: bold; }
            .status-rejected { color: #dc2626; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?= ucfirst($report_type) ?> Report</h1>
            <p>Period: <?= date('M j, Y', strtotime($start_date)) ?> to <?= date('M j, Y', strtotime($end_date)) ?></p>
            <p>Generated on: <?= date('M j, Y g:i A') ?></p>
        </div>
        
        <?php if ($report_type === 'payment'): ?>
            <?php 
            $paymentSummary = getPaymentSummaryData($start_date, $end_date);
            $recentPayments = getRecentPayments(100);
            ?>
            
            <div class="summary">
                <h2>Payment Summary</h2>
                <div class="stat">Total Revenue Collected: <strong>₱<?= number_format($paymentSummary['total_revenue'], 2) ?></strong></div>
                <div class="stat">Outstanding Payments: <strong>₱<?= number_format($paymentSummary['outstanding_payments'], 2) ?></strong></div>
                <div class="stat">Payment Completion Rate: <strong><?= $paymentSummary['completion_rate'] ?>%</strong></div>
                <div class="stat">Total Transactions: <strong><?= $paymentSummary['total_transactions'] ?></strong></div>
                <div class="stat">Validated Payments: <strong><?= $paymentSummary['validated_count'] ?></strong></div>
                <div class="stat">Pending Payments: <strong><?= $paymentSummary['pending_count'] ?></strong></div>
                <div class="stat">Rejected Payments: <strong><?= $paymentSummary['rejected_count'] ?></strong></div>
            </div>
            
            <?php if (!empty($recentPayments)): ?>
                <h2>Payment Details</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Program</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['student_name']) ?></td>
                                <td><?= htmlspecialchars($payment['program_name']) ?></td>
                                <td class="amount">₱<?= number_format($payment['amount'], 2) ?></td>
                                <td class="status-<?= $payment['status'] ?>"><?= ucfirst($payment['status']) ?></td>
                                <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
        <?php elseif ($report_type === 'enrollment'): ?>
            <?php $enrollmentStats = getEnrollmentStats(); ?>
            
            <div class="summary">
                <h2>Enrollment Summary</h2>
                <div class="stat">Total Enrollments: <strong><?= $enrollmentStats['total_enrollments'] ?></strong></div>
                <div class="stat">Active Students: <strong><?= $enrollmentStats['active_students'] ?></strong></div>
                <div class="stat">Completion Rate: <strong><?= $enrollmentStats['completion_rate'] ?>%</strong></div>
                <div class="stat">Enrollment Growth: <strong><?= $enrollmentStats['enrollment_growth'] ?>%</strong></div>
                <div class="stat">Students Growth: <strong><?= $enrollmentStats['students_growth'] ?>%</strong></div>
                <div class="stat">Completion Growth: <strong><?= $enrollmentStats['completion_growth'] ?>%</strong></div>
            </div>
            
        <?php elseif ($report_type === 'schedule'): ?>
            <?php $scheduleStats = getScheduleStats(); ?>
            
            <div class="summary">
                <h2>Schedule Summary</h2>
                <div class="stat">Total Schedules: <strong><?= $scheduleStats['total_schedules'] ?></strong></div>
                <div class="stat">Occupied: <strong><?= $scheduleStats['occupied'] ?></strong></div>
                <div class="stat">Available: <strong><?= $scheduleStats['available'] ?></strong></div>
                <div class="stat">Occupancy Rate: <strong><?= $scheduleStats['occupancy_rate'] ?>%</strong></div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 40px; text-align: center; font-size: 12px; color: #666;">
            <p>This report was generated by TPLearn Admin Dashboard</p>
        </div>
    </body>
    </html>
    <?php
}
?>