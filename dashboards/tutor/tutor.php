<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
require_once '../../assets/icons.php';
requireRole('tutor');

// Get current user data from session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? 'Tutor';
$user_email = $_SESSION['email'] ?? '';

// Check if user_id is available
if (!$user_id) {
  // Redirect to login if user_id is missing
  header('Location: ../../login.php');
  exit();
}

// Get real tutor dashboard data
$tutor_data = getTutorDashboardData($user_id);

// Use tutor name from profile if available, fallback to session name
$display_name = !empty($tutor_data['name']) ? $tutor_data['name'] : $user_name;

// Get programs assigned to this tutor
$programs = getPrograms(['tutor_id' => $user_id]);

// Get recent activities/sessions for tutor
$activities = getTutorRecentActivities($user_id, 8);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tutor Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="../../assets/tplearn-tailwind.css?v=<?= filemtime(__DIR__ . '/../../assets/tplearn-tailwind.css') ?>">
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

    <?php include '../../includes/tutor-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1 flex flex-col h-screen">
      <?php 
      require_once '../../includes/tutor-header-standard.php';
      renderTutorHeader('Tutor Dashboard', getTutorGreeting($display_name));
      ?>

      <!-- Main Content Area -->
      <main class="p-4 sm:p-6 flex-1 overflow-y-auto">

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
            
            $first_name = !empty($display_name) ? htmlspecialchars(explode(' ', $display_name)[0]) : 'Tutor';
            ?>
            <h2 class="text-3xl font-bold mb-2">Good <?php echo $greeting; ?>, <?php echo $first_name; ?>!</h2>
            <p class="text-white/90">You have <?php echo $tutor_data['total_students'] ?? 0; ?> students across <?php echo $tutor_data['assigned_programs'] ?? 0; ?> active programs.</p>
          </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
          <!-- Total Students -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Students</p>
                <p class="text-3xl font-bold text-gray-900" id="total-students"><?php echo $tutor_data['total_students'] ?? 0; ?></p>
              </div>
              <div class="bg-blue-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Active Programs -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Active Programs</p>
                <p class="text-3xl font-bold text-gray-900" id="active-programs"><?php echo $tutor_data['assigned_programs'] ?? 0; ?></p>
              </div>
              <div class="bg-green-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Payments -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Payments</p>
                <p class="text-3xl font-bold text-gray-900" id="total-payments"><?php echo $tutor_data['total_payments'] ?? 0; ?></p>
              </div>
              <div class="bg-yellow-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Assessments -->
          <div class="stat-card p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Assessments</p>
                <p class="text-3xl font-bold text-gray-900" id="total-assessments"><?php echo $tutor_data['total_assessments'] ?? 0; ?></p>
              </div>
              <div class="bg-purple-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                </svg>
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
                  <a href="tutor-programs.php" class="text-tplearn-green hover:text-green-700 text-sm font-medium">View All</a>
                </div>
              </div>
              <div class="p-6">
                <div class="space-y-4" id="programs-container">
                  <?php if (empty($programs)): ?>
                    <div class="text-center py-8 text-gray-500">
                      <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                      </svg>
                      <p>No assigned programs</p>
                      <p class="text-sm">Programs will be assigned to you by the administrator</p>
                      <a href="tutor-programs.php" class="inline-block mt-2 px-4 py-2 bg-tplearn-green text-white text-sm rounded hover:bg-green-700">View All Programs</a>
                    </div>
                  <?php else: ?>
                    <?php foreach ($programs as $program): ?>
                      <div class="program-card p-4">
                        <div class="flex justify-between items-start mb-3">
                          <div>
                            <h4 class="font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($program['name']); ?></h4>
                            <p class="text-sm text-gray-600">
                              <?php 
                                $schedule = '';
                                if (!empty($program['days']) && !empty($program['start_time'])) {
                                  $schedule = $program['days'] . ' at ' . $program['start_time'];
                                } else {
                                  $schedule = 'Schedule TBD';
                                }
                                echo htmlspecialchars($schedule);
                              ?>
                            </p>
                          </div>
                          <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                            <?php echo htmlspecialchars($program['status'] ?? 'active'); ?>
                          </span>
                        </div>

                        <div class="flex justify-between items-center mb-2">
                          <span class="text-sm text-gray-600">Enrollment</span>
                          <span class="text-sm font-medium text-gray-900">
                            <?php 
                              $enrolled = $program['enrolled_count'] ?? 0;
                              $capacity = $program['max_students'] ?? 0;
                              echo $enrolled . '/' . $capacity . ' students';
                            ?>
                          </span>
                        </div>

                        <div class="progress-bar">
                          <div class="progress-fill" style="width: <?php 
                            $enrolled = $program['enrolled_count'] ?? 0;
                            $capacity = $program['max_students'] ?? 0;
                            $percentage = $capacity > 0 ? ($enrolled / $capacity) * 100 : 0;
                            echo min(100, max(0, $percentage));
                          ?>%"></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script>
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
              <p class="text-sm text-blue-800">New student enrolled in Kindergarten Program</p>
              <p class="text-xs text-blue-600 mt-1">1 hour ago</p>
            </div>
            <div class="p-3 bg-green-50 rounded-lg">
              <p class="text-sm text-green-800">Class attendance marked for Playschool Program</p>
              <p class="text-xs text-green-600 mt-1">3 hours ago</p>
            </div>
            <div class="p-3 bg-yellow-50 rounded-lg">
              <p class="text-sm text-yellow-800">Monthly report is ready for review</p>
              <p class="text-xs text-yellow-600 mt-1">1 day ago</p>
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
              <p class="text-sm text-gray-800">Parent inquiry about Playschool Program</p>
              <p class="text-xs text-gray-600 mt-1">2 hours ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Admin: Schedule change for next week</p>
              <p class="text-xs text-gray-600 mt-1">5 hours ago</p>
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
    
    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
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

      // Close mobile menu when clicking on a navigation link
      if (sidebar) {
        const navLinks = sidebar.querySelectorAll('a');
        navLinks.forEach(link => {
          link.addEventListener('click', () => {
            if (window.innerWidth < 1024) { // Only on mobile
              setTimeout(closeMobileMenu, 100); // Small delay for better UX
            }
          });
        });
      }

      // Close mobile menu on window resize to desktop
      window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
          closeMobileMenu();
        }
      });
    });
  </script>
</body>

</html>