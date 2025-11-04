<?php
require_once __DIR__ . '/../../assets/icons.php';
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('admin');

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-30'); // 30th of current month
$report_type = $_GET['report_type'] ?? 'payment_summary';

// Debug: Log the received parameters
error_log("Reports filter - Start: $start_date, End: $end_date, Type: $report_type");

// Fetch payment summary data with error handling
try {
  $paymentSummary = getPaymentSummaryData($start_date, $end_date);
  $recentPayments = getRecentPayments(10);
  $enrollmentStats = getEnrollmentStats();
  $scheduleStats = getScheduleStats();
  $enrollmentTrends = getEnrollmentTrends('all'); // Get last 12 months
} catch (Exception $e) {
  error_log("Error fetching reports data: " . $e->getMessage());
  $paymentSummary = [
    'total_revenue' => 0,
    'outstanding_payments' => 0,
    'completion_rate' => 0,
    'total_transactions' => 0,
    'validated_count' => 0,
    'pending_count' => 0,
    'rejected_count' => 0
  ];
  $recentPayments = [];
  $enrollmentStats = [
    'total_enrollments' => 0,
    'active_students' => 0,
    'completion_rate' => 0,
    'enrollment_growth' => 0,
    'students_growth' => 0,
    'completion_growth' => 0
  ];
  $scheduleStats = [
    'total_schedules' => 0,
    'occupied' => 0,
    'available' => 0,
    'occupancy_rate' => 0
  ];
  $enrollmentTrends = [];
}

// Ensure we have valid data
$paymentSummary = $paymentSummary ?: [
  'total_revenue' => 0,
  'outstanding_payments' => 0, 
  'completion_rate' => 0,
  'total_transactions' => 0,
  'validated_count' => 0,
  'pending_count' => 0,
  'rejected_count' => 0
];
$recentPayments = $recentPayments ?: [];
$enrollmentStats = $enrollmentStats ?: [
  'total_enrollments' => 0,
  'active_students' => 0,
  'completion_rate' => 0,
  'enrollment_growth' => 0,
  'students_growth' => 0,
  'completion_growth' => 0
];
$scheduleStats = $scheduleStats ?: [
  'total_schedules' => 0,
  'occupied' => 0,
  'available' => 0,
  'occupancy_rate' => 0
];
$enrollmentTrends = $enrollmentTrends ?: [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - Admin Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?php include '../../includes/common-scripts.php'; ?>
  
  <style>
    /* Custom styles matching admin-tools design */
    .stat-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    /* Tab styles */
    .tab-active {
      border-bottom: 2px solid #10b981;
      color: #10b981;
    }

    .tab-inactive {
      color: #6b7280;
      border-bottom: 2px solid transparent;
    }

    .tab-inactive:hover {
      color: #374151;
    }

    /* Chart placeholder */
    .chart-placeholder {
      background: #f9fafb;
      border: 2px dashed #d1d5db;
      border-radius: 8px;
      height: 200px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6b7280;
      font-size: 14px;
    }

    /* Stat number animation */
    .stat-number {
      display: inline-block;
      transition: transform 0.2s ease;
    }

    .stat-card:hover .stat-number {
      transform: scale(1.05);
    }

    /* Loading animation */
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    .animate-spin {
      animation: spin 1s linear infinite;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/admin-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/header.php';
      
      // Get admin notifications
      $admin_notifications = getAdminNotifications(15);
      
      renderHeader(
        'Reports',
        '',
        'admin',
        $_SESSION['username'] ?? 'Admin',
        $admin_notifications
      );
      ?>

      <!-- Reports Content -->
      <main class="p-6">
        <!-- Header with Export Buttons -->
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-lg font-semibold text-gray-800">Reports</h2>
          <div class="flex space-x-2">
            <button onclick="exportReport('pdf')" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
              </svg>
              Export PDF
            </button>
            <button onclick="exportReport('excel')" class="flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
              </svg>
              Export CSV
            </button>
          </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-t-lg shadow-sm border border-gray-200 border-b-0">
          <div class="flex border-b border-gray-200 overflow-x-auto">
            <button id="tab-enrollment" class="px-4 sm:px-6 py-3 text-sm font-medium tab-inactive whitespace-nowrap flex-shrink-0" onclick="switchTab('enrollment')">
              Enrollment Reports
            </button>
            <button id="tab-payment" class="px-4 sm:px-6 py-3 text-sm font-medium tab-active whitespace-nowrap flex-shrink-0" onclick="switchTab('payment')">
              Payment Summary
            </button>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-b-lg shadow-sm border border-gray-200 border-t-0">

          <!-- Enrollment Reports Tab -->
          <div id="content-enrollment" class="p-6 hidden">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Enrollment Reports</h3>
            
            <!-- Enrollment Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Total Enrollments</p>
                  <p class="text-3xl font-bold text-gray-900 stat-number"><?= number_format($enrollmentStats['total_enrollments']) ?></p>
                  <?php if ($enrollmentStats['enrollment_growth'] >= 0): ?>
                    <p class="text-green-600 text-sm mt-2">+<?= $enrollmentStats['enrollment_growth'] ?>% this month</p>
                  <?php else: ?>
                    <p class="text-red-600 text-sm mt-2"><?= $enrollmentStats['enrollment_growth'] ?>% this month</p>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Active Students</p>
                  <p class="text-3xl font-bold text-gray-900 stat-number"><?= number_format($enrollmentStats['active_students']) ?></p>
                  <?php if ($enrollmentStats['students_growth'] >= 0): ?>
                    <p class="text-green-600 text-sm mt-2">+<?= $enrollmentStats['students_growth'] ?>% this month</p>
                  <?php else: ?>
                    <p class="text-red-600 text-sm mt-2"><?= $enrollmentStats['students_growth'] ?>% this month</p>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Completion Rate</p>
                  <p class="text-3xl font-bold text-gray-900 stat-number"><?= $enrollmentStats['completion_rate'] ?>%</p>
                  <?php if ($enrollmentStats['completion_growth'] >= 0): ?>
                    <p class="text-green-600 text-sm mt-2">+<?= $enrollmentStats['completion_growth'] ?>% this month</p>
                  <?php else: ?>
                    <p class="text-red-600 text-sm mt-2"><?= $enrollmentStats['completion_growth'] ?>% this month</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Enrollment Trends Chart -->
            <div class="bg-white rounded-lg border border-gray-200 p-6">
              <h4 class="text-md font-semibold text-gray-800 mb-4">Enrollment Trends</h4>
              <?php if ($enrollmentStats['total_enrollments'] > 0): ?>
                <div class="relative h-64">
                  <canvas id="enrollmentChart"></canvas>
                </div>
              <?php else: ?>
                <div class="h-64 bg-gray-50 border-2 dashed border-gray-300 rounded-lg flex items-center justify-center">
                  <div class="text-center">
                    <p class="text-gray-500 mb-2">No enrollment data available</p>
                    <p class="text-sm text-gray-400">Start by adding students and programs to see enrollment trends</p>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Payment Summary Tab -->
          <div id="content-payment" class="p-6">
            <!-- Date Range Filter -->
            <form method="GET" action="reports.php" class="mb-6">
              <div class="flex flex-wrap items-end gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                  <input type="date" name="start_date" id="startDate" value="<?= $start_date ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                  <input type="date" name="end_date" id="endDate" value="<?= $end_date ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                  <input type="hidden" name="report_type" value="payment_summary">
                  <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm font-medium">
                    Apply Filter
                  </button>
                </div>
              </div>
            </form>

            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Summary</h3>
            
            <!-- Payment Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Total Revenue Collected</p>
                  <p class="text-3xl font-bold text-gray-900 stat-number">₱<?= number_format($paymentSummary['total_revenue'] ?? 0, 0) ?></p>
                </div>
              </div>
              
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Outstanding Payments</p>
                  <p class="text-3xl font-bold text-gray-900 stat-number">₱<?= number_format($paymentSummary['outstanding_payments'] ?? 0, 0) ?></p>
                </div>
              </div>
              
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Payment Completion Rate</p>
                  <p class="text-3xl font-bold text-gray-900 stat-number"><?= $paymentSummary['completion_rate'] ?? 0 ?>%</p>
                </div>
              </div>
            </div>

            <!-- Payment Status Overview -->
            <div class="mb-8">
              <h4 class="text-md font-semibold text-gray-800 mb-4">Payment Status Overview</h4>
              <?php if ($paymentSummary['total_transactions'] > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                  <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-green-800 font-semibold text-lg"><?= $paymentSummary['validated_count'] ?></div>
                    <div class="text-green-600 text-sm">Validated</div>
                  </div>
                  <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                    <div class="text-yellow-800 font-semibold text-lg"><?= $paymentSummary['pending_count'] ?></div>
                    <div class="text-yellow-600 text-sm">Pending</div>
                  </div>
                  <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                    <div class="text-red-800 font-semibold text-lg"><?= $paymentSummary['rejected_count'] ?></div>
                    <div class="text-red-600 text-sm">Rejected</div>
                  </div>
                  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                    <div class="text-blue-800 font-semibold text-lg"><?= $paymentSummary['total_transactions'] ?></div>
                    <div class="text-blue-600 text-sm">Total</div>
                  </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-6">
                  <h5 class="text-sm font-semibold text-gray-800 mb-4">Payment Status Distribution</h5>
                  <div class="relative h-64">
                    <canvas id="paymentStatusChart"></canvas>
                  </div>
                </div>
              <?php else: ?>
                <div class="chart-placeholder">
                  <div class="text-center">
                    <p class="text-gray-500 mb-2">No payment data available for the selected period</p>
                    <p class="text-sm text-gray-400">Adjust the date range or check if there are any payments in the system</p>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <!-- Recent Payments -->
            <div>
              <h4 class="text-md font-semibold text-gray-800 mb-4">Recent Payments</h4>
              <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($recentPayments)): ?>
                      <?php foreach ($recentPayments as $payment): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($payment['student_name'] ?? 'N/A') ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($payment['program_name'] ?? 'N/A') ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                            ₱<?= number_format($payment['amount'] ?? 0, 2) ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <?php 
                            $status = $payment['status'] ?? 'unknown';
                            $statusClass = '';
                            $statusText = ucfirst($status);
                            switch ($status) {
                              case 'validated':
                                $statusClass = 'bg-green-100 text-green-800 border-green-200';
                                break;
                              case 'pending':
                                $statusClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                break;
                              case 'rejected':
                                $statusClass = 'bg-red-100 text-red-800 border-red-200';
                                break;
                              default:
                                $statusClass = 'bg-gray-100 text-gray-800 border-gray-200';
                            }
                            ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full border <?= $statusClass ?>">
                              <?= $statusText ?>
                            </span>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('M j, Y', strtotime($payment['payment_date'] ?? 'now')) ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                          <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500 font-medium">No recent payments found</p>
                            <p class="text-sm text-gray-400 mt-1">Payments will appear here once students start making payments</p>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <script src="../../assets/admin-sidebar.js"></script>

  <!-- Tab Switching JavaScript -->
  <script>
    function switchTab(tabName) {
      // Hide all tab contents first
      document.getElementById('content-enrollment').classList.add('hidden');
      document.getElementById('content-payment').classList.add('hidden');

      // Show selected tab content and mark tab as active
      if (tabName === 'enrollment') {
        document.getElementById('content-enrollment').classList.remove('hidden');
        document.getElementById('tab-enrollment').classList.add('border-tplearn-green', 'text-tplearn-green');
        document.getElementById('tab-enrollment').classList.remove('border-transparent', 'text-gray-500');
        document.getElementById('tab-payment').classList.add('border-transparent', 'text-gray-500');
        document.getElementById('tab-payment').classList.remove('border-tplearn-green', 'text-tplearn-green');
      } else if (tabName === 'payment') {
        document.getElementById('content-payment').classList.remove('hidden');
        document.getElementById('tab-payment').classList.add('border-tplearn-green', 'text-tplearn-green');
        document.getElementById('tab-payment').classList.remove('border-transparent', 'text-gray-500');
        document.getElementById('tab-enrollment').classList.add('border-transparent', 'text-gray-500');
        document.getElementById('tab-enrollment').classList.remove('border-tplearn-green', 'text-tplearn-green');
      }
    }

    // Export functionality
    function exportReport(format) {
      const reportType = getActiveTab();
      console.log('Exporting', reportType, 'as', format);
      // Implementation for export functionality
    }

    function getActiveTab() {
      if (!document.getElementById('content-enrollment').classList.contains('hidden')) {
        return 'enrollment';
      } else if (!document.getElementById('content-payment').classList.contains('hidden')) {
        return 'payment';
      }
      return 'payment'; // default
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      // Show payment summary tab by default
      switchTab('payment');
    });

            <!-- Program Details Table -->
            <?php if (!empty($programSchedules)): ?>
              <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                  <h4 class="text-lg font-medium text-gray-900">Program Schedule Details</h4>
                  <p class="text-sm text-gray-500 mt-1">Detailed view of each program's schedule and occupancy</p>
                </div>
                
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupancy</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <?php foreach ($programSchedules as $program): ?>
                        <tr class="hover:bg-gray-50">
                          <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                              <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($program['program_name']) ?></div>
                              <div class="text-sm text-gray-500">
                                <?= date('M j', strtotime($program['schedule']['start_date'])) ?> - 
                                <?= date('M j, Y', strtotime($program['schedule']['end_date'])) ?>
                              </div>
                            </div>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                              <?= htmlspecialchars($program['schedule']['days']) ?>
                            </div>
                            <div class="text-sm text-gray-500">
                              <?= date('g:i A', strtotime($program['schedule']['start_time'])) ?> - 
                              <?= date('g:i A', strtotime($program['schedule']['end_time'])) ?>
                            </div>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?= htmlspecialchars($program['tutor_name']) ?></div>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= number_format($program['capacity']['enrolled_students']) ?> / <?= number_format($program['capacity']['max_students']) ?>
                            <div class="text-xs text-gray-500">
                              <?= number_format($program['capacity']['available_slots']) ?> available
                            </div>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                              <div class="text-sm font-medium text-gray-900">
                                <?= $program['capacity']['occupancy_percentage'] ?>%
                              </div>
                              <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full <?= 
                                  $program['capacity']['occupancy_percentage'] >= 80 ? 'bg-green-500' : 
                                  ($program['capacity']['occupancy_percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') 
                                ?>" style="width: <?= min(100, $program['capacity']['occupancy_percentage']) ?>%"></div>
                              </div>
                            </div>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                              <?= number_format($program['sessions']['completed_sessions']) ?> / <?= number_format($program['sessions']['total_sessions']) ?>
                            </div>
                            <div class="text-xs text-gray-500">
                              <?= number_format($program['sessions']['scheduled_sessions']) ?> scheduled
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php else: ?>

            <?php endif; ?>
          </div>

        </div>
      </main>
    </div>
  </div>

  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>

  <!-- Tab Switching JavaScript -->
  <script>
    function switchTab(tabName) {
      // Hide all tab contents first
      document.getElementById('content-enrollment').classList.add('hidden');
      document.getElementById('content-payment').classList.add('hidden');

      // Remove active class from all tabs
      document.getElementById('tab-enrollment').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-inactive whitespace-nowrap flex-shrink-0';
      document.getElementById('tab-payment').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-inactive whitespace-nowrap flex-shrink-0';

      // Show selected tab content and mark tab as active
      if (tabName === 'enrollment') {
        document.getElementById('content-enrollment').classList.remove('hidden');
        document.getElementById('tab-enrollment').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-active whitespace-nowrap flex-shrink-0';
      } else if (tabName === 'payment') {
        document.getElementById('content-payment').classList.remove('hidden');
        document.getElementById('tab-payment').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-active whitespace-nowrap flex-shrink-0';
      }
    }

    function applyDateFilter() {
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      
      console.log('Filter clicked with dates:', startDate, endDate);
      
      if (!startDate || !endDate) {
        TPAlert.warning('Required', 'Please select both start and end dates to filter the reports.');
        return;
      }
      
      if (new Date(startDate) > new Date(endDate)) {
        TPAlert.info('Information', 'Start date cannot be later than end date. Please check your date selection.');
        return;
      }
      
      // Simple redirect without complex tab detection
      const newUrl = `reports.php?start_date=${startDate}&end_date=${endDate}&report_type=payment_summary`;
      console.log('Redirecting to:', newUrl);
      window.location.href = newUrl;
    }

    function exportReport(format) {
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      
      // Get the currently active tab
      let activeTab = 'payment'; // default
      if (!document.getElementById('content-enrollment').classList.contains('hidden')) {
        activeTab = 'enrollment';
      }
      
      // Show loading state
      const button = event.target;
      const originalText = button.innerHTML;
      button.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path></svg>Exporting...';
      button.disabled = true;
      
      // Show loading alert
      const formatName = format === 'pdf' ? 'PDF' : 'CSV';
      console.log(`Generating ${formatName} Report for ${activeTab} report from ${startDate} to ${endDate}...`);
      
      // Construct export URL
      const exportFormat = format === 'pdf' ? 'pdf' : 'csv';
      const exportUrl = `export_report.php?report_type=${activeTab}&start_date=${startDate}&end_date=${endDate}&format=${exportFormat}`;
      
      // Create a temporary link to trigger download
      const link = document.createElement('a');
      link.href = exportUrl;
      link.style.display = 'none';
      document.body.appendChild(link);
      
      // For PDF, open in new tab so user can save/print
      if (format === 'pdf') {
        link.target = '_blank';
        link.click();
        
        // Reset button and show success
        setTimeout(() => {
          button.innerHTML = originalText;
          button.disabled = false;
          TPAlert.info('Information', 'PDF Report Generated! Your report has opened in a new tab.');
        }, 1000);
        
      } else {
        // For CSV, trigger direct download
        link.click();
        
        // Reset button and show success
        setTimeout(() => {
          button.innerHTML = originalText;
          button.disabled = false;
          TPAlert.info('Information', 'CSV Report Downloaded! Your report has been downloaded.');
        }, 1500);
      }
      
      // Clean up
      document.body.removeChild(link);
    }

    // Initialize with correct tab based on URL parameter
    document.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      const reportType = urlParams.get('report_type') || 'payment_summary';
      
      // Determine which tab to show
      let activeTab = 'payment';
      if (reportType === 'enrollment') {
        activeTab = 'enrollment';
      } else if (reportType === 'schedule') {
        activeTab = 'schedule';
      }
      
      switchTab(activeTab);
      initializeEnrollmentChart();
      initializePaymentStatusChart();
    });

    // Initialize Enrollment Trends Chart
    function initializeEnrollmentChart() {
      const enrollmentData = <?= json_encode($enrollmentTrends) ?>;
      
      if (enrollmentData.length === 0) {
        return; // No data to display
      }

      const ctx = document.getElementById('enrollmentChart');
      if (!ctx) {
        return; // Chart canvas not found
      }

      const labels = enrollmentData.map(item => item.label);
      const data = enrollmentData.map(item => item.enrollments);

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Enrollments',
            data: data,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleColor: '#ffffff',
              bodyColor: '#ffffff',
              borderColor: '#10b981',
              borderWidth: 1,
              cornerRadius: 6,
              displayColors: false,
              callbacks: {
                title: function(context) {
                  return context[0].label;
                },
                label: function(context) {
                  return `${context.parsed.y} enrollment${context.parsed.y !== 1 ? 's' : ''}`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                stepSize: 1,
                color: '#6b7280'
              },
              grid: {
                color: '#e5e7eb',
                drawBorder: false
              }
            },
            x: {
              ticks: {
                color: '#6b7280',
                maxRotation: 45
              },
              grid: {
                display: false
              }
            }
          },
          interaction: {
            intersect: false,
            mode: 'index'
          }
        }
      });
    }

    // Initialize Payment Status Chart
    function initializePaymentStatusChart() {
      const paymentSummary = <?= json_encode($paymentSummary) ?>;
      
      if (paymentSummary.total_transactions === 0) {
        return; // No data to display
      }

      const ctx = document.getElementById('paymentStatusChart');
      if (!ctx) {
        return; // Chart canvas not found
      }

      const data = [
        paymentSummary.validated_count,
        paymentSummary.pending_count,
        paymentSummary.rejected_count
      ];

      const labels = ['Validated', 'Pending', 'Rejected'];
      const colors = ['#10b981', '#f59e0b', '#ef4444'];

      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: data,
            backgroundColor: colors,
            borderColor: '#ffffff',
            borderWidth: 2,
            hoverBorderWidth: 3,
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                usePointStyle: true,
                font: {
                  size: 12
                },
                color: '#6b7280'
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleColor: '#ffffff',
              bodyColor: '#ffffff',
              borderColor: '#10b981',
              borderWidth: 1,
              cornerRadius: 6,
              displayColors: true,
              callbacks: {
                label: function(context) {
                  const total = context.dataset.data.reduce((sum, value) => sum + value, 0);
                  const percentage = ((context.parsed / total) * 100).toFixed(1);
                  return `${context.label}: ${context.parsed} (${percentage}%)`;
                }
              }
            }
          },
          cutout: '60%',
          interaction: {
            intersect: false
          }
        }
      });
    }
  </script>
</body>

</html>