<?php
require_once '../../assets/icons.php';
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('student');

// Get current user data from session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? 'Student';
$user_email = $_SESSION['email'] ?? '';

// Check if user_id is available
if (!$user_id) {
  // Redirect to login if user_id is missing
  header('Location: ../../login.php');
  exit();
}

// Get real student dashboard data
$student_data = getStudentDashboardData($user_id);

// Ensure we have default values to prevent errors
$student_data = array_merge([
  'name' => $user_name,
  'program' => 'General',
  'level' => 'Beginner',
  'overall_progress' => 0,
  'enrolled_programs' => 0,
  'sessions_today' => 0,
  'pending_payments' => 0,
  'overdue_payments' => 0
], $student_data);

// Get available programs for student
$programs = getStudentPrograms($user_id);

// Get upcoming assessments/assignments for student
$upcoming_items = getStudentUpcomingAssessments($user_id, 8);

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  
  <style>
    /* Custom styles */
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

    .welcome-card {
      background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
      border-radius: 16px;
      position: relative;
      overflow: hidden;
    }

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

    .activity-item {
      display: flex;
      align-items: center;
      padding: 1rem;
      border-bottom: 1px solid #f3f4f6;
      transition: background-color 0.2s ease;
    }

    .activity-item:hover {
      background-color: #f9fafb;
    }

    .activity-item:last-child {
      border-bottom: none;
    }

    .program-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease;
    }

    .program-card:hover {
      transform: translateY(-2px);
    }

    .progress-bar {
      background-color: #e5e7eb;
      border-radius: 9999px;
      height: 8px;
      overflow: hidden;
    }

    .progress-fill {
      background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
      height: 100%;
      border-radius: 9999px;
      transition: width 0.3s ease;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/student-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/student-header-standard.php';
      renderStudentHeader('Student Dashboard', 'Welcome to your learning portal');
      ?>

      <!-- Main Content Area -->
      <main class="p-4 sm:p-6">

        <!-- Welcome Card -->
        <div class="welcome-card p-8 mb-8 text-white relative">
          <div class="relative z-10">
            <?php
            // Set timezone to Philippine Time (UTC+8)
            date_default_timezone_set('Asia/Manila');
            
            // Get current hour for time-based greeting
            $current_hour = (int) date('H');
            $greeting = '';
            
            if ($current_hour >= 5 && $current_hour < 12) {
                $greeting = 'Morning';
            } elseif ($current_hour >= 12 && $current_hour < 17) {
                $greeting = 'Afternoon';
            } elseif ($current_hour >= 17 && $current_hour < 21) {
                $greeting = 'Evening';
            } else {
                $greeting = 'Evening'; // Late night/early morning
            }
            
            $first_name = explode(' ', $student_data['name'])[0];
            ?>
            <h2 class="text-3xl font-bold mb-2">Good <?php echo $greeting; ?>, <?php echo htmlspecialchars($first_name); ?>!</h2>
            <p class="text-white/90">You have <?php echo $student_data['sessions_today']; ?> session today and <?php echo $student_data['pending_payments']; ?> pending payments.</p>
          </div>
        </div>
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
          <!-- Enrolled Programs -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Enrolled Programs</p>
                <p class="text-3xl font-bold text-gray-900" id="enrolled-programs"><?php echo $student_data['enrolled_programs']; ?></p>
              </div>
              <div class="bg-green-100 p-3 rounded-full">
                <?= statusIcon('check-circle', 'success', 'lg') ?>
              </div>
            </div>
          </div>

          <!-- Sessions Today -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Sessions Today</p>
                <p class="text-3xl font-bold text-gray-900" id="sessions-today"><?php echo $student_data['sessions_today']; ?></p>
              </div>
              <div class="bg-blue-100 p-3 rounded-full">
                <?= icon('clock', 'lg text-blue-600') ?>
              </div>
            </div>
          </div>

          <!-- Pending Payments -->
          <div class="stat-card p-6 cursor-pointer" onclick="window.location.href='student-payments.php'">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Pending Payments</p>
                <p class="text-3xl font-bold text-gray-900" id="pending-payments"><?php echo $student_data['pending_payments']; ?></p>
              </div>
              <div class="bg-orange-100 p-3 rounded-full">
                <?= icon('credit-card', 'lg text-orange-600') ?>
              </div>
            </div>
          </div>

          <!-- Overdue Payments -->
          <div class="stat-card p-6 cursor-pointer" onclick="window.location.href='student-payments.php'">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Overdue Payments</p>
                <p class="text-3xl font-bold text-gray-900" id="overdue-payments"><?php echo $student_data['overdue_payments']; ?></p>
              </div>
              <div class="bg-red-100 p-3 rounded-full">
                <?= icon('exclamation-triangle', 'lg text-red-600') ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Programs and Activities Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">

          <!-- My Programs -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
              <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                  <h3 class="text-lg font-semibold text-gray-900">My Programs</h3>
                  <a href="student-enrollment.php" class="text-tplearn-green hover:text-green-700 text-sm font-medium">View All</a>
                </div>
              </div>
              <div class="p-6">
                <div class="space-y-4" id="programs-container">
                  <?php if (empty($programs)): ?>
                    <div class="text-center py-8 text-gray-500">
                      <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                      </svg>
                      <p>No enrolled programs</p>
                      <p class="text-sm">Browse available programs to get started</p>
                      <a href="student-enrollment.php" class="inline-block mt-2 px-4 py-2 bg-tplearn-green text-white text-sm rounded hover:bg-green-700">Browse Programs</a>
                    </div>
                  <?php else: ?>
                    <?php foreach ($programs as $program): ?>
                      <div class="program-card p-4 hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='program-stream.php?program_id=<?php echo $program['id']; ?>'">
                        <div class="flex justify-between items-start mb-3">
                          <div class="flex-1">
                            <h4 class="font-semibold text-gray-900 mb-1 hover:text-tplearn-green transition-colors"><?php echo htmlspecialchars($program['name']); ?></h4>
                            <p class="text-sm text-gray-600">Tutor: <?php echo htmlspecialchars($program['tutor']); ?></p>
                          </div>
                          <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full ml-3">
                            <?php echo $program['status']; ?>
                          </span>
                        </div>

                        <div class="flex justify-between items-center mb-2">
                          <span class="text-sm text-gray-600">Progress</span>
                          <span class="text-sm font-medium text-gray-900"><?php echo $program['progress']; ?>%</span>
                        </div>

                        <div class="progress-bar mb-3">
                          <div class="progress-fill" style="width: <?php echo $program['progress']; ?>%"></div>
                        </div>

                        <div class="flex justify-between items-center">
                          <span class="text-sm text-gray-600">Next Session:</span>
                          <span class="text-sm font-medium text-tplearn-green"><?php echo $program['next_session']; ?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Upcoming Assessment/Assignment -->
          <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
              <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Upcoming Assessment/Assignment</h3>
              </div>
              <div class="divide-y divide-gray-200" id="upcoming-container">
                <?php if (empty($upcoming_items)): ?>
                  <div class="p-6 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p>No upcoming items</p>
                    <p class="text-sm">Your assignments and assessments will appear here</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($upcoming_items as $item): ?>
                    <div class="activity-item">
                      <div class="flex-shrink-0 mr-3">
                        <?php if ($item['icon'] === 'clipboard-document-check'): ?>
                          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                          </div>
                        <?php elseif ($item['icon'] === 'document-text'): ?>
                          <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                          </div>
                        <?php else: ?>
                          <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                          <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($item['type'] . ': ' . $item['title']); ?></p>
                          <?php
                          // Determine submission status tag color and text
                          $status_class = match($item['submission_status']) {
                            'submitted' => 'bg-green-100 text-green-800',
                            'draft' => 'bg-yellow-100 text-yellow-800', 
                            'in_progress' => 'bg-blue-100 text-blue-800',
                            default => 'bg-gray-100 text-gray-800'
                          };
                          ?>
                          <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($item['submission_display']); ?>
                          </span>
                        </div>
                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($item['program']); ?></p>
                        <p class="text-xs <?php echo strpos($item['time'], 'Overdue') !== false ? 'text-red-500 font-medium' : 'text-gray-500'; ?>"><?php echo htmlspecialchars($item['time']); ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script>
    // Mobile menu functionality
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileCloseButton = document.getElementById('mobile-close-button');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-menu-overlay');

    function openMobileMenu() {
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
    }

    function closeMobileMenu() {
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
    }

    if (mobileMenuButton) {
      mobileMenuButton.addEventListener('click', openMobileMenu);
    }

    if (mobileCloseButton) {
      mobileCloseButton.addEventListener('click', closeMobileMenu);
    }

    if (overlay) {
      overlay.addEventListener('click', closeMobileMenu);
    }

    // Notification dropdown functions
    function toggleNotificationDropdown() {
      const dropdown = document.getElementById('notification-dropdown');
      if (dropdown) {
        dropdown.classList.toggle('hidden');
      }
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      const notificationDropdown = document.getElementById('notification-dropdown');
      const notificationButton = event.target.closest('button[onclick="toggleNotificationDropdown()"]');
      
      if (notificationDropdown && !notificationButton && !notificationDropdown.contains(event.target)) {
        notificationDropdown.classList.add('hidden');
      }
    });



    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
      if (event.target.classList.contains('fixed') && event.target.classList.contains('bg-black')) {
        event.target.remove();
      }
    });
  </script>
  <!-- Dashboard JavaScript -->
  <script src="../../assets/dashboard.js"></script>
</body>

</html>