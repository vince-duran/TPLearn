<?php
require_once __DIR__ . '/../../assets/icons.php';
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('admin');

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-30'); // 30th of current month
$report_type = $_GET['report_type'] ?? 'payment_summary';

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
      renderHeader(
        'Reports',
        '',
        'admin',
        $_SESSION['name'] ?? 'Admin',
        [], // notifications array - to be implemented
        []  // messages array - to be implemented
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
            <button id="tab-schedule" class="px-4 sm:px-6 py-3 text-sm font-medium tab-inactive whitespace-nowrap flex-shrink-0" onclick="switchTab('schedule')">
              Schedule Occupancy
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
            <div class="flex flex-col sm:flex-row items-start sm:items-end space-y-4 sm:space-y-0 sm:space-x-4 mb-6">
              <div class="flex space-x-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                  <input type="date" id="startDate" value="<?= $start_date ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                  <input type="date" id="endDate" value="<?= $end_date ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
                </div>
              </div>
              <button onclick="applyDateFilter()" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors text-sm">
                Apply Filter
              </button>
            </div>

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

          <!-- Schedule Occupancy Tab -->
          <div id="content-schedule" class="p-6 hidden">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Schedule Occupancy</h3>
            
            <!-- Occupancy Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Total Schedules</p>
                  <p class="text-3xl font-bold text-gray-900 stat-number"><?= number_format($scheduleStats['total_schedules']) ?></p>
                </div>
              </div>
              
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Occupied</p>
                  <p class="text-3xl font-bold text-green-600 stat-number"><?= number_format($scheduleStats['occupied']) ?></p>
                </div>
              </div>
              
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Available</p>
                  <p class="text-3xl font-bold text-blue-600 stat-number"><?= number_format($scheduleStats['available']) ?></p>
                </div>
              </div>
              
              <div class="stat-card p-6">
                <div class="text-center">
                  <p class="text-sm font-medium text-gray-600 mb-2">Occupancy Rate</p>
                  <p class="text-3xl font-bold text-gray-900 stat-number"><?= $scheduleStats['occupancy_rate'] ?>%</p>
                </div>
              </div>
            </div>

            <div class="chart-placeholder">
              <?php if ($scheduleStats['total_schedules'] > 0): ?>
                Schedule occupancy visualization will be displayed here
              <?php else: ?>
                <div class="text-center">
                  <p class="text-gray-500 mb-2">No schedule data available</p>
                  <p class="text-sm text-gray-400">Create sessions and programs to see schedule occupancy</p>
                </div>
              <?php endif; ?>
            </div>
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
      document.getElementById('content-schedule').classList.add('hidden');

      // Remove active class from all tabs
      document.getElementById('tab-enrollment').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-inactive whitespace-nowrap flex-shrink-0';
      document.getElementById('tab-payment').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-inactive whitespace-nowrap flex-shrink-0';
      document.getElementById('tab-schedule').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-inactive whitespace-nowrap flex-shrink-0';

      // Show selected tab content and mark tab as active
      if (tabName === 'enrollment') {
        document.getElementById('content-enrollment').classList.remove('hidden');
        document.getElementById('tab-enrollment').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-active whitespace-nowrap flex-shrink-0';
      } else if (tabName === 'payment') {
        document.getElementById('content-payment').classList.remove('hidden');
        document.getElementById('tab-payment').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-active whitespace-nowrap flex-shrink-0';
      } else if (tabName === 'schedule') {
        document.getElementById('content-schedule').classList.remove('hidden');
        document.getElementById('tab-schedule').className = 'px-4 sm:px-6 py-3 text-sm font-medium tab-active whitespace-nowrap flex-shrink-0';
      }
    }

    function applyDateFilter() {
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      
      if (!startDate || !endDate) {
        TPAlert.warning('Missing Dates', 'Please select both start and end dates to filter the reports.');
        return;
      }
      
      if (new Date(startDate) > new Date(endDate)) {
        TPAlert.error('Invalid Date Range', 'Start date cannot be later than end date. Please check your date selection.');
        return;
      }
      
      // Show loading state
      const button = event.target;
      const originalText = button.innerHTML;
      button.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path></svg>Filtering...';
      button.disabled = true;
      
      // Show loading alert
      TPAlert.loading('Applying Date Filter', 'Refreshing report data for the selected date range...');
      
      // Small delay to show the loading state
      setTimeout(() => {
        // Redirect with parameters
        window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}&report_type=payment_summary`;
      }, 1000);
    }

    function exportReport(format) {
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      
      // Get the currently active tab
      let activeTab = 'payment'; // default
      if (!document.getElementById('content-enrollment').classList.contains('hidden')) {
        activeTab = 'enrollment';
      } else if (!document.getElementById('content-schedule').classList.contains('hidden')) {
        activeTab = 'schedule';
      }
      
      // Show loading state
      const button = event.target;
      const originalText = button.innerHTML;
      button.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path></svg>Exporting...';
      button.disabled = true;
      
      // Show loading alert
      const formatName = format === 'pdf' ? 'PDF' : 'CSV';
      TPAlert.loading(
        `Generating ${formatName} Report`, 
        `Creating ${activeTab} report for ${startDate} to ${endDate}...`
      );
      
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
          TPAlert.success(
            'PDF Report Generated!', 
            'Your report has opened in a new tab. You can save or print it from there.'
          );
        }, 1000);
        
      } else {
        // For CSV, trigger direct download
        link.click();
        
        // Reset button and show success
        setTimeout(() => {
          button.innerHTML = originalText;
          button.disabled = false;
          TPAlert.success(
            'CSV Report Downloaded!', 
            'Your report has been downloaded and can be opened in Excel or any spreadsheet application.'
          );
        }, 1500);
      }
      
      // Clean up
      document.body.removeChild(link);
    }

    // Initialize with payment summary tab active
    document.addEventListener('DOMContentLoaded', function() {
      switchTab('payment');
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