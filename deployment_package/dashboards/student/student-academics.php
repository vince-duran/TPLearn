<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('student');

// Get current user data from session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['username'] ?? 'Student';

// Check if user_id is available
if (!$user_id) {
  header('Location: ../../login.php');
  exit();
}

// Get student data for display name
$student_data = getStudentDashboardData($user_id);
$display_name = $student_data['name'] ?? $user_name;

// Get enrolled programs for the student
$enrolled_programs = getStudentEnrolledPrograms($user_id);
$currentDate = date('l, F j, Y');

// Handle error messages from URL parameters
$error_message = '';
if (isset($_GET['error'])) {
  switch ($_GET['error']) {
    case 'payment_locked':
      $error_message = '<div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                          <div class="flex">
                            <div class="flex-shrink-0">
                              <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                              </svg>
                            </div>
                            <div class="ml-3">
                              <p class="text-sm text-red-800 font-medium">Program Access Locked</p>
                              <p class="text-sm text-red-700 mt-1">Your access to this program has been locked due to overdue payments beyond the 3-day grace period. Please settle your outstanding payments to regain access.</p>
                              <a href="student-payments.php" class="inline-block mt-2 text-sm bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                                View Payment Details
                              </a>
                            </div>
                          </div>
                        </div>';
      break;
    case 'access_denied':
      $error_message = '<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                          <div class="flex">
                            <div class="flex-shrink-0">
                              <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                              </svg>
                            </div>
                            <div class="ml-3">
                              <p class="text-sm text-yellow-800 font-medium">Access Denied</p>
                              <p class="text-sm text-yellow-700 mt-1">You do not have access to the requested program. This may be because your enrollment is not active or you are not enrolled in this program.</p>
                            </div>
                          </div>
                        </div>';
      break;
    case 'program_not_found':
      $error_message = '<div class="bg-gray-50 border-l-4 border-gray-400 p-4 mb-6">
                          <div class="flex">
                            <div class="flex-shrink-0">
                              <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                              </svg>
                            </div>
                            <div class="ml-3">
                              <p class="text-sm text-gray-800 font-medium">Program Not Found</p>
                              <p class="text-sm text-gray-700 mt-1">The requested program could not be found or is no longer available.</p>
                            </div>
                          </div>
                        </div>';
      break;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Academic Progress - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="../../assets/tplearn-tailwind.css?v=<?= filemtime(__DIR__ . '/../../assets/tplearn-tailwind.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  
  <style>
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
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/student-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 lg:ml-64">
      <?php 
      require_once '../../includes/student-header-standard.php';
      renderStudentHeader('Academic Progress', 'Track your learning progress and performance');
      ?>

      <!-- Dashboard Content -->
      <main class="p-6">
        <!-- Error Messages -->
        <?php if ($error_message): ?>
          <?php echo $error_message; ?>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-t-lg shadow-sm border border-gray-200 border-b-0">
          <div class="flex border-b border-gray-200">
            <button id="programs-tab" class="px-6 py-3 text-sm font-medium tab-active" onclick="switchTab('programs')">
              Programs
            </button>
            <button id="schedule-tab" class="px-6 py-3 text-sm font-medium tab-inactive" onclick="switchTab('schedule')">
              Schedule
            </button>
            <button id="grades-tab" class="px-6 py-3 text-sm font-medium tab-inactive" onclick="switchTab('grades')">
              Grades
            </button>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-b-lg shadow-sm border border-gray-200 border-t-0">

        <!-- Programs Tab Content -->
        <div id="programs-content" class="p-6">
          <!-- Filter Buttons -->
          <div class="mb-6">
            <div class="flex flex-wrap gap-2">
              <button id="all-programs-btn" class="px-4 py-2 bg-tplearn-green text-white rounded-lg text-sm font-medium" onclick="filterPrograms('all')">
                All Programs
              </button>
              <button id="online-programs-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300" onclick="filterPrograms('online')">
                Online Programs
              </button>
              <button id="inperson-programs-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300" onclick="filterPrograms('inperson')">
                In-Person Programs
              </button>
            </div>
          </div>

          <!-- Programs List -->
          <div class="space-y-4">
            <?php if (empty($enrolled_programs)): ?>
              <!-- No Programs State -->
              <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No Enrolled Programs</h3>
                <p class="mt-1 text-sm text-gray-500">You haven't enrolled in any programs yet.</p>
                <div class="mt-6">
                  <a href="student-enrollment.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-tplearn-green hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tplearn-green">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Browse Programs
                  </a>
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($enrolled_programs as $index => $program): ?>
                <?php
                // Generate unique ID for this program
                $program_id = 'program-' . $program['id'];
                
                // Check program access status for payment restrictions
                $access_check = checkStudentProgramAccess($user_id, $program['id']);
                $is_access_blocked = !$access_check['has_access'];
                $access_reason = $access_check['reason'];
                
                // Determine status badge with payment lock consideration
                $status_badge = '';
                $status_color = '';
                
                if ($is_access_blocked && ($access_reason === 'payments_locked' || $access_reason === 'payments_pending_validation' || $access_reason === 'payments_rejected')) {
                  if ($access_reason === 'payments_pending_validation') {
                    $status_badge = 'Payment Under Review';
                  } elseif ($access_reason === 'payments_rejected') {
                    $status_badge = 'Payment Rejected';
                  } else {
                    $status_badge = 'Access Locked';
                  }
                  $status_color = 'bg-red-100 text-red-800';
                } elseif ($access_check['reason'] === 'grace_period') {
                  $status_badge = 'Payment Warning';
                  $status_color = 'bg-yellow-100 text-yellow-800';
                } elseif ($program['enrollment_status'] === 'paused') {
                  $status_badge = 'Paused';
                  $status_color = 'bg-yellow-100 text-yellow-800';
                } elseif ($program['program_status'] === 'completed') {
                  $status_badge = 'Completed';
                  $status_color = 'bg-gray-100 text-gray-800';
                } elseif ($program['program_status'] === 'ongoing') {
                  $status_badge = 'Active';
                  $status_color = 'bg-green-100 text-green-800';
                } else {
                  $status_badge = 'Starting Soon';
                  $status_color = 'bg-blue-100 text-blue-800';
                }

                // Format session type for data attribute
                $session_type_attr = strtolower($program['session_type'] ?? 'online');
                ?>
                
                <!-- Program Card -->
                <div class="bg-white rounded-lg shadow program-card" data-type="<?php echo $session_type_attr; ?>">
                  <div class="p-6">
                    <div class="flex items-center justify-between mb-4 cursor-pointer" onclick="toggleProgram('<?php echo $program_id; ?>')">
                      <div class="flex-1">
                        <div class="flex items-center justify-between mb-2">
                          <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($program['name'] ?? 'Program Name'); ?></h3>
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                            <?php echo $status_badge; ?>
                          </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($program['description'] ?? 'Program description'); ?></p>

                        <!-- Progress Bar -->
                        <div class="mb-4">
                          <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">Program Progress</span>
                            <span class="text-sm font-medium text-gray-700"><?php echo $program['progress_percentage'] ?? 0; ?>%</span>
                          </div>
                          <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-tplearn-green h-2 rounded-full" style="width: <?php echo $program['progress_percentage'] ?? 0; ?>%"></div>
                          </div>
                        </div>

                        <!-- Program Info -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                          <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                            </svg>
                            Tutor: <?php echo htmlspecialchars($program['tutor_name'] ?? 'TBD'); ?>
                          </div>
                          <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                            </svg>
                            Next: <?php echo htmlspecialchars($program['next_session']['date'] ?? 'TBD'); ?>
                          </div>
                          <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                              <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo ucfirst($program['session_type'] ?? 'Online'); ?> Sessions
                          </div>
                        </div>
                      </div>
                      <div class="ml-4">
                        <svg id="<?php echo $program_id; ?>-icon" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                      </div>
                    </div>

                    <!-- Payment Status Warning (if applicable) -->
                    <?php if ($is_access_blocked || $access_check['reason'] === 'grace_period'): ?>
                    <div class="mb-4 p-3 rounded-lg border-l-4 <?php echo $is_access_blocked ? 'bg-red-50 border-red-400' : 'bg-yellow-50 border-yellow-400'; ?>">
                      <div class="flex items-start">
                        <div class="flex-shrink-0">
                          <?php if ($is_access_blocked): ?>
                            <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                            </svg>
                          <?php else: ?>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                          <?php endif; ?>
                        </div>
                        <div class="ml-3 flex-1">
                          <?php if ($is_access_blocked): ?>
                            <h4 class="text-sm font-medium text-red-800">Program Access Locked</h4>
                            <p class="text-sm text-red-700 mt-1">
                              <?php echo htmlspecialchars($access_check['message']); ?>
                            </p>
                          <?php else: ?>
                            <h4 class="text-sm font-medium text-yellow-800">Payment Warning</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                              You have <?php echo $access_check['overdue_payments']; ?> overdue payment(s). 
                              <span class="font-medium"><?php echo $access_check['grace_period_remaining']; ?> day(s) remaining</span> 
                              to settle before program access is locked.
                            </p>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="mb-4 space-y-2">
                      <?php if ($is_access_blocked && ($access_reason === 'payments_locked' || $access_reason === 'payments_pending_validation' || $access_reason === 'payments_rejected')): ?>
                        <!-- Blocked Access Button -->
                        <div class="w-full bg-red-50 text-red-700 py-3 px-4 rounded-lg border border-red-200 flex items-center justify-center space-x-2">
                          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                          </svg>
                          <span class="font-medium">
                            <?php if ($access_reason === 'payments_pending_validation'): ?>
                              Access Blocked - Payment Under Review
                            <?php elseif ($access_reason === 'payments_rejected'): ?>
                              Access Blocked - Payment Rejected
                            <?php else: ?>
                              Access Blocked - Settle Payments
                            <?php endif; ?>
                          </span>
                        </div>
                        <a href="student-payments.php" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg flex items-center justify-center space-x-2 transition-colors text-sm">
                          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                          </svg>
                          <span>View Payment Details</span>
                        </a>
                      <?php elseif ($access_check['reason'] === 'grace_period'): ?>
                        <!-- Grace Period Warning Button -->
                        <div class="w-full bg-yellow-50 text-yellow-800 py-2 px-4 rounded-lg border border-yellow-200 flex items-center justify-center space-x-2 text-sm">
                          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                          </svg>
                          <span class="font-medium">Payment Warning - <?php echo $access_check['grace_period_remaining']; ?> days remaining</span>
                        </div>
                        <a href="program-stream.php?program_id=<?php echo $program['id']; ?>&program=<?php echo urlencode($program['name'] ?? 'Program'); ?>" class="w-full bg-yellow-100 hover:bg-yellow-200 text-yellow-800 py-3 px-4 rounded-lg border border-yellow-300 flex items-center justify-center space-x-2 transition-colors">
                          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                          </svg>
                          <span>View Program Stream</span>
                        </a>
                      <?php else: ?>
                        <!-- Normal Access Button -->
                        <a href="program-stream.php?program_id=<?php echo $program['id']; ?>&program=<?php echo urlencode($program['name'] ?? 'Program'); ?>" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 px-4 rounded-lg border border-gray-300 flex items-center justify-center space-x-2 transition-colors">
                          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                          </svg>
                          <span>View Program Stream</span>
                        </a>
                      <?php endif; ?>
                    </div>

                    <!-- Expanded Content -->
                    <div id="<?php echo $program_id; ?>-details" class="hidden border-t border-gray-200 pt-4 mt-4">
                      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Next Session -->
                        <div>
                          <h4 class="font-semibold text-gray-800 mb-3">Next Session</h4>
                          <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                              <span class="font-medium text-gray-800">
                                <?php echo htmlspecialchars($program['next_session']['date'] ?? 'Saturday, Oct 25'); ?>
                              </span>
                              <span class="bg-<?php echo ($program['session_type'] ?? 'online') === 'online' ? 'green' : 'blue'; ?>-100 text-<?php echo ($program['session_type'] ?? 'online') === 'online' ? 'green' : 'blue'; ?>-800 text-xs px-2 py-1 rounded-full">
                                <?php echo ucfirst($program['session_type'] ?? 'Online'); ?>
                              </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">
                              <?php echo $program['session_time'] ?? '2:55 PM - 4:55 PM'; ?>
                            </p>
                          </div>
                        </div>

                        <!-- Program Details -->
                        <div>
                          <h4 class="font-semibold text-gray-800 mb-3">Program Details</h4>
                          <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                              <span class="text-gray-600">Schedule:</span>
                              <span class="font-medium"><?php echo htmlspecialchars($program['days'] ?? 'Mon, Wed, Fri'); ?></span>
                            </div>
                            <div class="flex justify-between">
                              <span class="text-gray-600">Duration:</span>
                              <span class="font-medium"><?php echo $program['duration_weeks'] ?? 5; ?> weeks</span>
                            </div>
                            <div class="flex justify-between">
                              <span class="text-gray-600">Category:</span>
                              <span class="font-medium"><?php echo htmlspecialchars($program['category'] ?? 'General'); ?></span>
                            </div>
                            <div class="flex justify-between">
                              <span class="text-gray-600">Format:</span>
                              <span class="font-medium"><?php echo ucfirst($program['session_type'] ?? 'Online'); ?></span>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Student Action Buttons -->
                      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button onclick="viewAttendance(<?php echo $program['id']; ?>)" class="flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                          </svg>
                          View Attendance
                        </button>
                        <button onclick="viewGrades(<?php echo $program['id']; ?>)" class="flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path>
                            <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path>
                          </svg>
                          View Grades
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Schedule Tab Content -->
        <div id="schedule-content" class="p-6 hidden">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Weekly Schedule</h3>
          <p class="text-gray-600">Schedule view will be implemented here.</p>
        </div>

        <!-- Grades Tab Content -->
        <div id="grades-content" class="p-6 hidden">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Grades</h3>
          <p class="text-gray-600">Grades view will be implemented here.</p>
        </div>

        </div>
      </main>
    </div>
  </div>

  <!-- Include Sidebar JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>

  <script>
    console.log('🎓 Student Academics - Loading...');

    // Tab switching functionality
    function switchTab(tabName) {
      // Hide all tab contents
      document.getElementById('programs-content').classList.add('hidden');
      document.getElementById('schedule-content').classList.add('hidden');
      document.getElementById('grades-content').classList.add('hidden');

      // Remove active class from all tabs
      document.getElementById('programs-tab').className = 'px-6 py-3 text-sm font-medium tab-inactive';
      document.getElementById('schedule-tab').className = 'px-6 py-3 text-sm font-medium tab-inactive';
      document.getElementById('grades-tab').className = 'px-6 py-3 text-sm font-medium tab-inactive';

      // Show selected tab content and mark tab as active
      if (tabName === 'programs') {
        document.getElementById('programs-content').classList.remove('hidden');
        document.getElementById('programs-tab').className = 'px-6 py-3 text-sm font-medium tab-active';
      } else if (tabName === 'schedule') {
        document.getElementById('schedule-content').classList.remove('hidden');
        document.getElementById('schedule-tab').className = 'px-6 py-3 text-sm font-medium tab-active';
      } else if (tabName === 'grades') {
        document.getElementById('grades-content').classList.remove('hidden');
        document.getElementById('grades-tab').className = 'px-6 py-3 text-sm font-medium tab-active';
      }
    }

    // Program filtering functionality
    function filterPrograms(type) {
      const allCards = document.querySelectorAll('.program-card');
      const allButtons = document.querySelectorAll('[id$="-programs-btn"]');

      // Reset all button styles
      allButtons.forEach(btn => {
        btn.classList.remove('bg-tplearn-green', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
      });

      // Set active button style
      const activeButton = document.getElementById(type + '-programs-btn');
      activeButton.classList.remove('bg-gray-200', 'text-gray-700');
      activeButton.classList.add('bg-tplearn-green', 'text-white');

      // Filter cards
      allCards.forEach(card => {
        if (type === 'all' || card.dataset.type === type) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // Program expand/collapse functionality
    function toggleProgram(programId) {
      const details = document.getElementById(programId + '-details');
      const icon = document.getElementById(programId + '-icon');

      if (details && icon) {
        if (details.classList.contains('hidden')) {
          details.classList.remove('hidden');
          icon.style.transform = 'rotate(180deg)';
        } else {
          details.classList.add('hidden');
          icon.style.transform = 'rotate(0deg)';
        }
      }
    }

    // Header functions
    function openNotifications() {
      TPAlert.info('Information', 'Opening notifications...');
    }

    function openMessages() {
      TPAlert.info('Information', 'Opening messages...');
    }

    // Student action functions
    function viewAttendance(programId) {
      console.log('Viewing attendance for program:', programId);
      openAttendanceModal(programId);
    }

    function viewGrades(programId) {
      console.log('Viewing grades for program:', programId);
      openGradesModal(programId);
    }

    function openGradesModal(programId) {
      // Show loading modal first
      const loadingHTML = `
        <div id="grades-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-tplearn-green mx-auto"></div>
              <p class="mt-4 text-gray-600">Loading your grades...</p>
            </div>
          </div>
        </div>
      `;
      
      document.body.insertAdjacentHTML('beforeend', loadingHTML);
      document.body.style.overflow = 'hidden';
      
      // Fetch real grade data
      fetch(`../../api/grades.php?action=student_grades&program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            throw new Error(data.error);
          }
          
          // Remove loading modal
          const loadingModal = document.getElementById('grades-modal');
          if (loadingModal) loadingModal.remove();
          
          // Create actual modal with real data
          const modalHTML = `
            <div id="grades-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
              <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                  <!-- Modal Header -->
                  <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">My Grades</h3>
                    <button onclick="closeGradesModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>
                  </div>

                  <!-- My Performance Summary -->
                  <div class="mt-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4">
                      <h4 class="font-semibold text-gray-800 mb-3">My Performance</h4>
                      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                          <div class="text-2xl font-bold text-blue-600">${data.summary.assessment_avg}%</div>
                          <div class="text-sm text-gray-600">Assessment Avg</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-green-600">${data.summary.assignment_avg}%</div>
                          <div class="text-sm text-gray-600">Assignment Avg</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-purple-600">${data.summary.overall_grade}%</div>
                          <div class="text-sm text-gray-600">Overall Grade</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-green-600">${data.summary.letter_grade}</div>
                          <div class="text-sm text-gray-600">Letter Grade</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Grades Table -->
                  <div class="overflow-x-auto">
                    ${data.assignments.length > 0 ? `
                      <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                          <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                          </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                          ${data.assignments.map(assignment => {
                            const statusClass = {
                              'graded': 'bg-green-100 text-green-800',
                              'submitted': 'bg-yellow-100 text-yellow-800',
                              'upcoming': 'bg-gray-100 text-gray-800',
                              'overdue': 'bg-red-100 text-red-800'
                            };
                            
                            const gradeClass = {
                              'graded': 'text-green-600',
                              'submitted': 'text-yellow-600',
                              'upcoming': 'text-gray-400',
                              'overdue': 'text-red-600'
                            };
                            
                            return `
                              <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${assignment.name}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${assignment.type}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${assignment.date}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold ${gradeClass[assignment.status] || 'text-gray-400'}">${assignment.grade}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass[assignment.status] || 'bg-gray-100 text-gray-800'}">
                                    ${assignment.status.charAt(0).toUpperCase() + assignment.status.slice(1)}
                                  </span>
                                </td>
                              </tr>
                            `;
                          }).join('')}
                        </tbody>
                      </table>
                    ` : `
                      <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No Assignments Yet</h3>
                        <p class="mt-1 text-sm text-gray-500">Assignments and assessments will appear here once they're created.</p>
                      </div>
                    `}
                  </div>

                  <!-- Modal Footer -->
                  <div class="mt-6 flex justify-end space-x-3">
                    <button onclick="closeGradesModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                      Close
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `;

          // Add modal to page
          document.body.insertAdjacentHTML('beforeend', modalHTML);
        })
        .catch(error => {
          console.error('Error fetching grades:', error);
          
          // Remove loading modal
          const loadingModal = document.getElementById('grades-modal');
          if (loadingModal) loadingModal.remove();
          
          // Show error modal
          const errorHTML = `
            <div id="grades-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
              <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                  <svg class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z" />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900">Error Loading Grades</h3>
                  <p class="mt-1 text-sm text-gray-500">Unable to load your grades at this time. Please try again later.</p>
                  <button onclick="closeGradesModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    Close
                  </button>
                </div>
              </div>
            </div>
          `;
          
          document.body.insertAdjacentHTML('beforeend', errorHTML);
        });
    }

    function closeGradesModal() {
      const modal = document.getElementById('grades-modal');
      if (modal) {
        modal.remove();
        document.body.style.overflow = 'auto';
      }
    }

    function openAttendanceModal(programId) {
      // Show loading modal first
      const loadingHTML = `
        <div id="attendance-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
              <div class="flex items-center justify-center mb-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
              </div>
              <h3 class="text-lg font-semibold text-gray-900">Loading Attendance...</h3>
            </div>
          </div>
        </div>
      `;
      
      document.body.insertAdjacentHTML('beforeend', loadingHTML);
      document.body.style.overflow = 'hidden';
      
      // Fetch attendance data
      console.log('Fetching attendance for program ID:', programId);
      fetch(`/TPLearn/api/grades.php?action=student_attendance&program_id=${programId}`)
        .then(response => {
          console.log('API Response status:', response.status);
          console.log('API Response headers:', response.headers);
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then(data => {
          console.log('API Response data:', data);
          if (data.error) {
            throw new Error(data.error);
          }
          
          // Remove loading modal
          const loadingModal = document.getElementById('attendance-modal');
          if (loadingModal) loadingModal.remove();
          
          // Create actual modal with real data
          const modalHTML = `
            <div id="attendance-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
              <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                  <!-- Modal Header -->
                  <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">My Attendance Record (Program ${programId})</h3>
                    <button onclick="closeAttendanceModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>
                  </div>

                  <!-- Attendance Summary -->
                  <div class="mt-4 mb-6">
                    <div class="bg-purple-50 rounded-lg p-4">
                      <h4 class="font-semibold text-gray-800 mb-3">Attendance Summary</h4>
                      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="text-center">
                          <div class="text-2xl font-bold text-green-600">${data.summary.total_sessions || 0}</div>
                          <div class="text-sm text-gray-600">Total Sessions</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-blue-600">${data.summary.attended_sessions || 0}</div>
                          <div class="text-sm text-gray-600">Attended</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-purple-600">${data.summary.attendance_rate || 0}%</div>
                          <div class="text-sm text-gray-600">Attendance Rate</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Session List -->
                  <div class="max-h-96 overflow-y-auto">
                    ${renderAttendanceList(data.sessions)}
                  </div>

                  <!-- Modal Footer -->
                  <div class="flex justify-end pt-4 border-t border-gray-200">
                    <button onclick="closeAttendanceModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                      Close
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `;
          
          document.body.insertAdjacentHTML('beforeend', modalHTML);
        })
        .catch(error => {
          console.error('Error loading attendance data:', error);
          
          // Remove loading modal
          const loadingModal = document.getElementById('attendance-modal');
          if (loadingModal) loadingModal.remove();
          
          // Show error modal
          const errorHTML = `
            <div id="attendance-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
              <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                  <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                  </div>
                  <h3 class="text-lg font-semibold text-gray-900 mb-2">Error Loading Attendance</h3>
                  <p class="text-sm text-gray-600 mb-4">${error.message}</p>
                  <button onclick="closeAttendanceModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    Close
                  </button>
                </div>
              </div>
            </div>
          `;
          
          document.body.insertAdjacentHTML('beforeend', errorHTML);
        });
    }

    function renderAttendanceList(sessions) {
      if (!sessions || sessions.length === 0) {
        return `
          <div class="p-8 text-center text-gray-500">
            <p class="text-lg font-medium">No Sessions Found</p>
            <p class="text-sm">No attendance records are available yet.</p>
          </div>
        `;
      }

      let tableHtml = `
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
      `;

      sessions.forEach(session => {
        let statusClass = 'bg-gray-100 text-gray-800';
        let statusText = session.status || 'Scheduled';
        
        // Set status classes based on attendance status
        if (session.status === 'completed' && session.student_attended == 1) {
          statusClass = 'bg-green-100 text-green-800';
          statusText = 'Present';
        } else if (session.status === 'completed' && session.student_attended == 0) {
          statusClass = 'bg-red-100 text-red-800';
          statusText = 'Absent';
        } else if (session.status === 'missed') {
          statusClass = 'bg-red-100 text-red-800';
          statusText = 'Missed';
        } else if (session.status === 'cancelled') {
          statusClass = 'bg-gray-100 text-gray-800';
          statusText = 'Cancelled';
        } else if (session.status === 'scheduled') {
          statusClass = 'bg-blue-100 text-blue-800';
          statusText = 'Scheduled';
        }
        
        const sessionDate = new Date(session.session_date).toLocaleDateString();
        const sessionTime = session.start_time && session.end_time 
          ? `${session.start_time} - ${session.end_time}`
          : 'TBD';
        
        tableHtml += `
          <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
              ${sessionDate}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
              ${sessionTime}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                ${statusText}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
              ${session.notes || '-'}
            </td>
          </tr>
        `;
      });
      
      tableHtml += `
            </tbody>
          </table>
        </div>
      `;
      
      return tableHtml;
    }

    function closeAttendanceModal() {
      const modal = document.getElementById('attendance-modal');
      if (modal) {
        modal.remove();
        document.body.style.overflow = 'auto';
      }
    }

    // Initialize with programs tab active
    document.addEventListener('DOMContentLoaded', function() {
      switchTab('programs');
      
      // Mobile menu functionality
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const mobileCloseButton = document.getElementById('mobile-close-button');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('mobile-menu-overlay');

      function openMobileMenu() {
        if (sidebar) sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.remove('hidden');
      }

      function closeMobileMenu() {
        if (sidebar) sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
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
            closeMobileMenu();
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

    console.log('✅ Student Academics - Loaded Successfully!');
  </script>
</body>

</html>