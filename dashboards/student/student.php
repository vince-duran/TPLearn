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
  'unread_messages' => 0
], $student_data);

// Get available programs for student
$programs = getStudentPrograms($user_id);

// Get recent activities/sessions for student
$activities = getStudentRecentActivities($user_id, 8);

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
      require_once '../../includes/header.php';
      renderHeader(
        'Student Dashboard',
        '',
        'student',
        $student_data['name'] ?? 'Student',
        [], // notifications array - to be implemented
        []  // messages array - to be implemented
      );
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
            <p class="text-white/90">You have <?php echo $student_data['sessions_today']; ?> session today and <?php echo $student_data['unread_messages']; ?> unread messages.</p>
          </div>
        </div>
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
          <!-- Enrolled Programs -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-green-100 rounded-lg">
                <?= statusIcon('check-circle', 'success', 'lg') ?>
              </div>
              <span class="text-sm text-green-600 font-medium">Active</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1" id="enrolled-programs"><?php echo $student_data['enrolled_programs']; ?></h3>
            <p class="text-gray-600 text-sm">Enrolled Programs</p>
          </div>

          <!-- Sessions Today -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-blue-100 rounded-lg">
                <?= icon('clock', 'w-6 h-6 text-blue-600') ?>
              </div>
              <span class="text-sm text-blue-600 font-medium">Today</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1" id="sessions-today"><?php echo $student_data['sessions_today']; ?></h3>
            <p class="text-gray-600 text-sm">Sessions Today</p>
          </div>

          <!-- Unread Messages -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-purple-100 rounded-lg">
                <?= icon('envelope', 'w-6 h-6 text-purple-600') ?>
              </div>
              <span class="text-sm text-purple-600 font-medium">New</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1" id="unread-messages"><?php echo $student_data['unread_messages']; ?></h3>
            <p class="text-gray-600 text-sm">Unread Messages</p>
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

          <!-- Recent Activities -->
          <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
              <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Recent Activities</h3>
              </div>
              <div class="divide-y divide-gray-200" id="activities-container">
                <?php if (empty($activities)): ?>
                  <div class="p-6 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>No recent activities</p>
                    <p class="text-sm">Your activities will appear here</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                      <div class="flex-shrink-0 mr-3">
                        <?php if ($activity['icon'] === 'academic-cap'): ?>
                          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                            </svg>
                          </div>
                        <?php elseif ($activity['icon'] === 'video-camera'): ?>
                          <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                            </svg>
                          </div>
                        <?php elseif ($activity['icon'] === 'trophy'): ?>
                          <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                          </div>
                        <?php else: ?>
                          <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                              <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                            </svg>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900 font-medium"><?php echo $activity['message']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo $activity['time']; ?></p>
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

    // Notification functions
    function openNotifications() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Notifications</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-blue-50 rounded-lg">
              <p class="text-sm text-blue-800">New assignment posted in Math Excellence</p>
              <p class="text-xs text-blue-600 mt-1">1 hour ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    function openMessages() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Messages</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Message from Mark Santos about your progress</p>
              <p class="text-xs text-gray-600 mt-1">2 hours ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Reminder: English Literature session tomorrow</p>
              <p class="text-xs text-gray-600 mt-1">5 hours ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Payment confirmation for Math Excellence program</p>
              <p class="text-xs text-gray-600 mt-1">1 day ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

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