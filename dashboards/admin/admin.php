<?php
require_once __DIR__ . '/../../assets/icons.php';
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('admin');

// Get current date for welcome message
$currentDate = date('l, F j, Y');

// Get real dashboard data from database
$dashboardStats = getDashboardStats();
$recentPrograms = getPrograms(['status' => 'active'], 5);
$recentStudents = getStudents(5);
$recentPayments = getPayments('pending', 5);
$paymentStats = getPaymentStats();
$allPrograms = getPrograms(['status' => 'active']);
$recentActivities = getAdminRecentActivities(8);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  
  <style>
    /* TPLearn specific decorative elements */
    .welcome-card::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 200px;
      height: 200px;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/><circle cx="50" cy="50" r="30" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/><circle cx="50" cy="50" r="20" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></svg>') no-repeat center;
      background-size: contain;
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
        'Admin Dashboard',
        'Welcome, Admin!',
        'admin',
        $_SESSION['name'] ?? 'Admin',
        [], // notifications array - to be implemented
        []  // messages array - to be implemented
      );
      ?>

      <!-- Dashboard Content -->
      <main class="p-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Total Students -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Students</p>
                <p class="text-3xl font-bold text-gray-900"><?= $dashboardStats['total_students'] ?></p>
              </div>
              <div class="bg-blue-100 p-3 rounded-full">
                <?= statIcon('users', 'primary') ?>
              </div>
            </div>
          </div>

          <!-- Total Tutors -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Tutors</p>
                <p class="text-3xl font-bold text-gray-900"><?= $dashboardStats['total_tutors'] ?></p>
              </div>
              <div class="bg-green-100 p-3 rounded-full">
                <?= statIcon('academic-cap', 'success') ?>
              </div>
            </div>
          </div>

          <!-- Active Programs -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Active Programs</p>
                <p class="text-3xl font-bold text-gray-900"><?= $dashboardStats['active_programs'] ?></p>
              </div>
              <div class="bg-purple-100 p-3 rounded-full">
                <?= statIcon('book-open', 'accent') ?>
              </div>
            </div>
          </div>

          <!-- Outstanding Payments -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Pending Payments</p>
                <p class="text-3xl font-bold text-gray-900"><?= $dashboardStats['pending_payments'] ?></p>
              </div>
              <div class="bg-yellow-100 p-3 rounded-full">
                <?= statIcon('currency-dollar', 'warning') ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

          <!-- Payment Monitoring Section -->
          <div class="program-card">
            <div class="px-6 py-4 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                  <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"></path>
                  </svg>
                  <h3 class="text-lg font-semibold text-gray-800">Payment Monitoring</h3>
                </div>
                <a href="payments.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                  View All Payments
                  <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </a>
              </div>
            </div>

            <div class="p-6">
              <!-- Payment Summary -->
              <div class="mb-6">
                <h4 class="text-sm font-medium text-gray-600 mb-3">Payment Summary</h4>
                <div class="grid grid-cols-3 gap-4 mb-4">
                  <div class="text-center p-3 bg-green-50 rounded-lg">
                    <div class="text-lg font-bold text-green-600">₱<?= number_format($paymentStats['total_collected'] ?? 0, 2) ?></div>
                    <div class="text-xs text-green-600">Collected</div>
                  </div>
                  <div class="text-center p-3 bg-yellow-50 rounded-lg">
                    <div class="text-lg font-bold text-yellow-600">₱<?= number_format($paymentStats['total_pending'] ?? 0, 2) ?></div>
                    <div class="text-xs text-yellow-600">Pending</div>
                  </div>
                  <div class="text-center p-3 bg-blue-50 rounded-lg">
                    <div class="text-lg font-bold text-blue-600">₱<?= number_format($paymentStats['total_revenue'] ?? 0, 2) ?></div>
                    <div class="text-xs text-blue-600">Total Revenue</div>
                  </div>
                </div>
              </div>

              <!-- Pending Payments Alert -->
              <?php if (!empty($recentPayments)): ?>
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                  <svg class="w-5 h-5 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                  </svg>
                  <span class="text-sm font-medium text-yellow-800"><?= count($recentPayments) ?> Pending Payments Require Attention</span>
                </div>
              </div>
              <?php endif; ?>

              <!-- Recent Pending Payments List -->
              <div class="space-y-3">
                <?php if (!empty($recentPayments)): ?>
                  <?php foreach ($recentPayments as $payment): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                      <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                          <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"></path>
                          </svg>
                        </div>
                        <div>
                          <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($payment['student_name'] ?? 'Unknown Student') ?></p>
                          <p class="text-xs text-gray-500"><?= htmlspecialchars($payment['program_name'] ?? 'Unknown Program') ?></p>
                        </div>
                      </div>
                      <div class="text-right">
                        <p class="text-sm font-medium text-gray-900">₱<?= number_format($payment['amount'] ?? 0, 2) ?></p>
                        <p class="text-xs text-gray-500"><?= date('M j', strtotime($payment['created_at'] ?? 'now')) ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p>No pending payments found</p>
                    <p class="text-sm">Payments will appear here when students make payments</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Enrollment Overview Section -->
          <div class="program-card">
            <div class="px-6 py-4 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                  <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"></path>
                  </svg>
                  <h3 class="text-lg font-semibold text-gray-800">Enrollment Overview</h3>
                </div>
                <div class="flex items-center space-x-2">
                  <button class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">Chart</button>
                  <button class="px-3 py-1 bg-gray-200 text-gray-700 text-xs rounded hover:bg-gray-300">Table</button>
                  <button class="p-1 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <div class="p-6">
              <!-- Programs Table -->
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-gray-200">
                      <th class="text-left py-2 text-gray-600 font-medium">Program Name</th>
                      <th class="text-center py-2 text-gray-600 font-medium">Enrolled</th>
                      <th class="text-center py-2 text-gray-600 font-medium">Fee</th>
                      <th class="text-center py-2 text-gray-600 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100" id="programs-table">
                    <?php if (empty($allPrograms)): ?>
                    <tr id="no-programs-row">
                      <td colspan="4" class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <p>No programs available</p>
                        <p class="text-sm">Create your first program to get started</p>
                        <a href="programs.php" class="inline-block mt-2 px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Create Program</a>
                      </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($allPrograms as $program): ?>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-gray-800 font-medium"><?php echo htmlspecialchars($program['name']); ?></td>
                      <td class="py-3 text-center">
                        <span class="text-gray-700"><?php echo $program['enrolled_count'] ?? 0; ?>/<?php echo $program['max_students']; ?></span>
                      </td>
                      <td class="py-3 text-center">
                        <span class="text-green-600 font-semibold">₱<?php echo number_format($program['fee']); ?></span>
                      </td>
                      <td class="py-3 text-center">
                        <?php 
                        $status = $program['status'] ?? 'active';
                        $badgeClass = '';
                        switch($status) {
                          case 'active':
                            $badgeClass = 'bg-green-100 text-green-800';
                            break;
                          case 'inactive':
                            $badgeClass = 'bg-red-100 text-red-800';
                            break;
                          case 'draft':
                            $badgeClass = 'bg-yellow-100 text-yellow-800';
                            break;
                          default:
                            $badgeClass = 'bg-gray-100 text-gray-800';
                        }
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                          <?php echo ucfirst($status); ?>
                        </span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activities Section (moved up) -->
        <div class="program-card mt-8">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-800">Recent Activities</h3>
              </div>
              <button class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"></path>
                  <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                </svg>
              </button>
            </div>
          </div>

          <div class="p-6">
            <div class="space-y-4 max-h-96 overflow-y-auto">
              <?php if (!empty($recentActivities)): ?>
                <?php foreach ($recentActivities as $activity): ?>
                  <div class="activity-item">
                    <div class="flex items-start space-x-3 w-full">
                      <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <?= icon($activity['icon'], 'w-4 h-4 ' . $activity['color']) ?>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900 leading-5"><?= htmlspecialchars($activity['message']) ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($activity['time']) ?></p>
                      </div>
                      <div class="flex-shrink-0">
                        <?php 
                        $badgeClass = '';
                        switch($activity['type']) {
                          case 'enrollment':
                            $badgeClass = 'bg-green-100 text-green-800';
                            break;
                          case 'payment':
                            $badgeClass = 'bg-yellow-100 text-yellow-800';
                            break;
                          case 'program':
                            $badgeClass = 'bg-blue-100 text-blue-800';
                            break;
                          case 'user':
                            $badgeClass = 'bg-purple-100 text-purple-800';
                            break;
                          default:
                            $badgeClass = 'bg-gray-100 text-gray-800';
                        }
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $badgeClass ?>">
                          <?= ucfirst($activity['type']) ?>
                        </span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                  <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  <p>No recent activities</p>
                  <p class="text-sm">Platform activities will appear here</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
    </div>
    </main>
  </div>
  </div>

  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>
  <!-- Dashboard JavaScript -->
  <script src="../../assets/dashboard.js"></script>
</body>

</html>