<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('student');

// Get current student data from session
$student_user_id = $_SESSION['user_id'] ?? null;
$student_name = $_SESSION['username'] ?? 'Student';

// Get program info from URL parameters
$program_id = $_GET['program_id'] ?? null;
$program_name = $_GET['program'] ?? 'Program';

if (!$program_id || !$student_user_id) {
  header('Location: student-academics.php');
  exit();
}

// Verify student has access to this program (enrolled)
$stmt = $conn->prepare("SELECT p.*, e.status as enrollment_status FROM programs p 
                        JOIN enrollments e ON p.id = e.program_id 
                        WHERE p.id = ? AND e.student_user_id = ?");
$stmt->bind_param('ii', $program_id, $student_user_id);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();

if (!$program || $program['enrollment_status'] !== 'active') {
  header('Location: student-academics.php?error=access_denied');
  exit();
}

$program_name = $program['name'];
$program_description = $program['description'] ?: 'Academic program with comprehensive learning materials and assignments.';

// Get materials for this program
$materials = getProgramMaterials($program_id, 'program_material');

// Get assignments for this program
$assignments = getProgramMaterials($program_id, 'assignment');

// Get sessions for this program
$sessions = getProgramSessions($program_id);

// Debug: Check if materials are being fetched
error_log("Student Program ID: " . $program_id);
error_log("Materials count: " . count($materials));
error_log("Assignments count: " . count($assignments));
error_log("Sessions count: " . count($sessions));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($program_name) ?> - Stream - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  
</head>

<body class="bg-gray-50 min-h-screen">
  <div class="flex">
    <?php include '../../includes/student-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 lg:ml-64">
      <!-- Top Header -->
      <header class="bg-white shadow-sm border-b border-gray-200 px-4 lg:px-6 py-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <!-- Mobile menu button -->
            <button id="mobile-menu-button" class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-tplearn-green mr-3">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>

            <!-- Back to Programs -->
            <a href="student-academics.php" class="flex items-center text-gray-600 hover:text-gray-900 mr-4 transition-colors">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
              </svg>
              Back to Programs
            </a>
          </div>

          <div class="flex items-center space-x-4">
            <!-- Notifications -->
            <div class="relative">
              <button onclick="openNotifications()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
                </svg>
              </button>
              <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
            </div>

            <!-- Messages -->
            <div class="relative">
              <button onclick="openMessages()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                  <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                </svg>
              </button>
              <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">2</span>
            </div>

            <!-- Profile -->
            <div class="flex items-center space-x-2">
              <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($student_name) ?></span>
              <div class="w-8 h-8 bg-tplearn-green rounded-full flex items-center justify-center text-white font-semibold text-sm">
                <?= strtoupper(substr($student_name, 0, 1)) ?>
              </div>
            </div>

            <!-- Back to Dashboard -->
            <button onclick="backToDashboard()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
              <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
              </svg>
              Back to Dashboard
            </button>
          </div>
        </div>
      </header>

      <!-- Program Header -->
      <div class="bg-white border-b border-gray-200 px-4 lg:px-6 py-6">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900"><?= htmlspecialchars($program_name) ?> Stream</h1>
            <p class="text-gray-600 mt-1"><?= htmlspecialchars($program_description) ?></p>
          </div>
          <div class="text-right">
            <div class="text-sm text-gray-500">Enrollment Status</div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              Active
            </span>
          </div>
        </div>
      </div>

      <!-- Filter Tabs -->
      <div class="bg-white border-b border-gray-200 px-4 lg:px-6">
        <nav class="-mb-px flex space-x-8">
          <button onclick="filterContent('all')" class="filter-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            All Content
          </button>
          <button onclick="filterContent('documents')" class="filter-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            Documents
          </button>
          <button onclick="filterContent('assignments')" class="filter-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            Assignments
          </button>
          <button onclick="filterContent('sessions')" class="filter-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            Sessions
          </button>
        </nav>
      </div>

      <!-- Main Content -->
      <main class="p-4 lg:p-6">
        <div class="space-y-6">

          <?php 
          // Helper function to get material type color and icon
          function getMaterialTypeDisplay($type) {
            $displays = [
              'document' => [
                'color_bg' => 'bg-blue-100', 
                'color_text' => 'text-blue-800',
                'color_icon' => 'text-blue-600',
                'icon' => 'M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z'
              ],
              'video' => [
                'color_bg' => 'bg-purple-100', 
                'color_text' => 'text-purple-800',
                'color_icon' => 'text-purple-600',
                'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'
              ],
              'image' => [
                'color_bg' => 'bg-green-100', 
                'color_text' => 'text-green-800',
                'color_icon' => 'text-green-600',
                'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'
              ],
              'slides' => [
                'color_bg' => 'bg-orange-100', 
                'color_text' => 'text-orange-800',
                'color_icon' => 'text-orange-600',
                'icon' => 'M9 17V7h2v10h-2zM20 3H4a1 1 0 00-1 1v16a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1z'
              ],
              'assignment' => [
                'color_bg' => 'bg-purple-100', 
                'color_text' => 'text-purple-800',
                'color_icon' => 'text-purple-600',
                'icon' => 'M9 2a1 1 0 000 2h2a1 1 0 100-2H9z M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z'
              ],
              'other' => [
                'color_bg' => 'bg-gray-100', 
                'color_text' => 'text-gray-800',
                'color_icon' => 'text-gray-600',
                'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
              ]
            ];
            return $displays[$type] ?? $displays['other'];
          }

          // Helper function to calculate time ago
          function getTimeAgo($timestamp) {
            $relativeTime = time() - strtotime($timestamp);
            
            if ($relativeTime < 0) {
              return 'Just now';
            } elseif ($relativeTime < 60) {
              return 'Just now';
            } elseif ($relativeTime < 3600) {
              return floor($relativeTime / 60) . ' minutes ago';
            } elseif ($relativeTime < 86400) {
              return floor($relativeTime / 3600) . ' hours ago';
            } elseif ($relativeTime < 604800) {
              return floor($relativeTime / 86400) . ' days ago';
            } else {
              return floor($relativeTime / 604800) . ' weeks ago';
            }
          }

          // Display materials (documents)
          if (!empty($materials)): 
            foreach ($materials as $material): 
              $typeDisplay = getMaterialTypeDisplay($material['material_type'] ?? 'document');
              $timeAgo = getTimeAgo($material['created_at']);
          ?>
          <!-- Material: <?= htmlspecialchars($material['title'] ?? 'Document') ?> -->
          <div class="content-item bg-white rounded-lg border border-gray-200 p-6" data-type="documents">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 <?= $typeDisplay['color_bg'] ?> rounded-lg flex items-center justify-center">
                  <svg class="w-6 h-6 <?= $typeDisplay['color_icon'] ?>" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="<?= $typeDisplay['icon'] ?>" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars($material['title'] ?? 'Document') ?></h3>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="<?= $typeDisplay['color_bg'] ?> <?= $typeDisplay['color_text'] ?> px-2 py-1 rounded text-xs font-medium"><?= ucfirst($material['material_type'] ?? 'Document') ?></span>
                      <span class="text-sm text-gray-500">File:</span>
                      <span class="text-sm font-medium text-gray-600"><?= htmlspecialchars($material['filename'] ?? $material['original_name'] ?? 'Unknown') ?></span>
                      <?php if (!empty($material['uploaded_by_name'])): ?>
                      <span class="text-sm text-gray-500">by</span>
                      <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($material['uploaded_by_name']) ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($material['description'])): ?>
                    <p class="text-sm text-gray-700 mb-3"><?= htmlspecialchars($material['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                      <?php if (!empty($material['file_size'])): ?>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        <?php echo formatFileSize($material['file_size']); ?>
                      </span>
                      <?php endif; ?>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <?php echo $timeAgo; ?>
                      </span>
                    </div>
                    <div class="flex items-center space-x-2">
                      <button onclick="viewItem('<?php echo $material['id']; ?>', 'document')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View
                      </button>
                      <button onclick="downloadItem('<?php echo $material['id']; ?>')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download
                      </button>
                    </div>
                  </div>
                  <div class="text-sm text-gray-500 text-right">
                    <div><?= $timeAgo ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php 
            endforeach;
          endif;

          // Display assignments
          if (!empty($assignments)): 
            foreach ($assignments as $assignment): 
              $typeDisplay = getMaterialTypeDisplay('assignment');
              $timeAgo = getTimeAgo($assignment['created_at']);
              $due_date = $assignment['due_date'] ?? null;
              $is_overdue = $due_date && strtotime($due_date) < time();
          ?>
          <!-- Assignment: <?= htmlspecialchars($assignment['title'] ?? 'Assignment') ?> -->
          <div class="content-item bg-white rounded-lg border border-gray-200 p-6" data-type="assignments">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 <?= $typeDisplay['color_bg'] ?> rounded-lg flex items-center justify-center">
                  <svg class="w-6 h-6 <?= $typeDisplay['color_icon'] ?>" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="<?= $typeDisplay['icon'] ?>" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars($assignment['title'] ?? 'Assignment') ?></h3>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="<?= $typeDisplay['color_bg'] ?> <?= $typeDisplay['color_text'] ?> px-2 py-1 rounded text-xs font-medium">Assignment</span>
                      <?php if ($is_overdue): ?>
                      <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Overdue</span>
                      <?php endif; ?>
                      <?php if (!empty($assignment['uploaded_by_name'])): ?>
                      <span class="text-sm text-gray-500">by</span>
                      <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($assignment['uploaded_by_name']) ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($assignment['description'])): ?>
                    <p class="text-sm text-gray-700 mb-3"><?= htmlspecialchars($assignment['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                      <?php if (!empty($due_date)): ?>
                      <span class="flex items-center <?= $is_overdue ? 'text-red-600' : '' ?>">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        Due: <?= date('M j, Y, g:i A', strtotime($due_date)) ?>
                      </span>
                      <?php endif; ?>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        Assigned: <?= $timeAgo ?>
                      </span>
                    </div>
                    <div class="flex items-center space-x-2">
                      <button onclick="viewItem('<?= $assignment['id'] ?>', 'assignment')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View
                      </button>
                      <button onclick="submitAssignment('<?= $assignment['id'] ?>')" class="bg-tplearn-green text-white px-3 py-1 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Submit
                      </button>
                    </div>
                  </div>
                  <div class="text-sm text-gray-500 text-right">
                    <div><?= $timeAgo ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php 
            endforeach;
          endif;

          // Display sessions
          if (!empty($sessions)): 
            foreach ($sessions as $session): 
              $timeAgo = getTimeAgo($session['session_date']);
              $status = $session['status'] ?? 'scheduled';
              $status_colors = [
                'scheduled' => 'bg-blue-100 text-blue-800',
                'ongoing' => 'bg-yellow-100 text-yellow-800',
                'completed' => 'bg-green-100 text-green-800',
                'cancelled' => 'bg-gray-100 text-gray-800'
              ];
              $status_color = $status_colors[$status] ?? 'bg-gray-100 text-gray-800';
          ?>
          <!-- Session: <?= date('M j, Y', strtotime($session['session_date'] ?? 'now')) ?> -->
          <div class="content-item bg-white rounded-lg border border-gray-200 p-6" data-type="sessions">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                  <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                  </svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">
                      Session: <?= date('M j, Y', strtotime($session['session_date'] ?? 'now')) ?>
                      <?php if (!empty($session['topic'])): ?>
                        - <?= htmlspecialchars($session['topic']) ?>
                      <?php endif; ?>
                    </h3>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="<?= $status_color ?> px-2 py-1 rounded text-xs font-medium"><?= ucfirst($status) ?></span>
                    </div>
                    <?php if (!empty($session['notes'])): ?>
                    <p class="text-sm text-gray-700 mb-3"><?= htmlspecialchars($session['notes']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                      <?php if (!empty($session['start_time']) && !empty($session['end_time'])): ?>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <?= date('g:i A', strtotime($session['start_time'])) ?> - <?= date('g:i A', strtotime($session['end_time'])) ?>
                      </span>
                      <?php endif; ?>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        <?= date('M j, Y', strtotime($session['session_date'] ?? 'now')) ?>
                      </span>
                    </div>
                    <div class="flex items-center space-x-2">
                      <?php if ($status === 'scheduled' && strtotime($session['session_date']) <= strtotime('+1 day')): ?>
                      <button onclick="joinSession('<?= $session['id'] ?>')" class="bg-tplearn-green text-white px-3 py-1 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        Join Session
                      </button>
                      <?php elseif ($status === 'completed'): ?>
                      <button onclick="viewSessionRecording('<?= $session['id'] ?>')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293H15M9 10V9a2 2 0 012-2h2a2 2 0 012 2v1M9 10v5a2 2 0 002 2h2a2 2 0 002-2v-5"></path>
                        </svg>
                        View Recording
                      </button>
                      <?php else: ?>
                      <button onclick="viewItem('<?= $session['id'] ?>', 'session')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View Details
                      </button>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-sm text-gray-500 text-right">
                    <div><?= $timeAgo ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php 
            endforeach;
          endif;

          // Show empty state if no content
          if (empty($materials) && empty($assignments) && empty($sessions)): 
          ?>
          <!-- No content available -->
          <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Content Available</h3>
            <p class="text-gray-600">Your instructor hasn't uploaded any materials yet. Check back later!</p>
          </div>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

  <!-- Assignment Submission Modal -->
  <div id="submissionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">Submit Assignment</h3>
        <button onclick="closeSubmissionModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <form id="submissionForm" class="space-y-6">
          <!-- Assignment Info -->
          <div class="bg-purple-50 rounded-lg p-4">
            <h4 id="submissionAssignmentTitle" class="font-semibold text-purple-900 mb-2">Assignment Title</h4>
            <p id="submissionAssignmentDescription" class="text-sm text-purple-700">Assignment description will appear here.</p>
            <div class="mt-2 text-sm text-purple-600">
              <span id="submissionDueDate">Due date will appear here</span>
            </div>
          </div>

          <!-- File Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Upload your submission</label>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
              <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
              </svg>
              <div class="mt-4">
                <label for="submission-file" class="cursor-pointer">
                  <span class="mt-2 block text-sm font-medium text-gray-900">
                    Click to upload or drag and drop
                  </span>
                  <span class="mt-1 block text-sm text-gray-500">
                    PDF, DOC, DOCX, TXT files up to 10MB
                  </span>
                  <input id="submission-file" type="file" class="sr-only" accept=".pdf,.doc,.docx,.txt" onchange="handleSubmissionFileSelect(this)">
                </label>
              </div>
            </div>
            <!-- File Preview -->
            <div id="submissionFilePreview" class="hidden mt-3 p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <svg class="w-8 h-8 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                  </svg>
                  <div>
                    <p id="submissionFileName" class="text-sm font-medium text-gray-900"></p>
                    <p id="submissionFileSize" class="text-xs text-gray-500"></p>
                  </div>
                </div>
                <button type="button" onclick="removeSubmissionFile()" class="text-red-600 hover:text-red-800">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Comments -->
          <div>
            <label for="submissionComments" class="block text-sm font-medium text-gray-700 mb-2">Comments (optional)</label>
            <textarea id="submissionComments" rows="3" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-tplearn-green focus:border-tplearn-green" placeholder="Add any comments about your submission..."></textarea>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeSubmissionModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" class="px-4 py-2 bg-tplearn-green border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700">
              Submit Assignment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Helper function for file size formatting
    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Filter content by type
    function filterContent(type) {
      const items = document.querySelectorAll('.content-item');
      const tabs = document.querySelectorAll('.filter-tab');

      // Update tab styles
      tabs.forEach(tab => {
        tab.classList.remove('border-tplearn-green', 'text-tplearn-green');
        tab.classList.add('border-transparent', 'text-gray-500');
      });
      event.target.classList.remove('border-transparent', 'text-gray-500');
      event.target.classList.add('border-tplearn-green', 'text-tplearn-green');

      // Filter items
      items.forEach(item => {
        if (type === 'all' || item.dataset.type === type) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    }

    // View item details
    function viewItem(id, type) {
      // Implementation would depend on your specific requirements
      TPAlert.info('Information', `Viewing ${type} with ID: ${id}`);
    }

    // Download item
    function downloadItem(id) {
      // Create download URL and trigger download
      const downloadUrl = `../../api/serve-program-file.php?file_id=${id}&action=download`;
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = '';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // Submit assignment
    function submitAssignment(assignmentId) {
      // Store assignment ID for form submission
      document.getElementById('submissionModal').setAttribute('data-assignment-id', assignmentId);
      
      // Show submission modal
      document.getElementById('submissionModal').classList.remove('hidden');
      document.getElementById('submissionModal').classList.add('flex');
    }

    // Close submission modal
    function closeSubmissionModal() {
      document.getElementById('submissionModal').classList.add('hidden');
      document.getElementById('submissionModal').classList.remove('flex');
      
      // Reset form
      document.getElementById('submissionForm').reset();
      document.getElementById('submissionFilePreview').classList.add('hidden');
    }

    // Handle file selection for submission
    function handleSubmissionFileSelect(input) {
      const file = input.files[0];
      if (file) {
        // Validate file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
          TPAlert.error('File Size Error', 'File size must be less than 10MB');
          input.value = '';
          return;
        }
        
        // Show file preview
        document.getElementById('submissionFilePreview').classList.remove('hidden');
        document.getElementById('submissionFileName').textContent = file.name;
        document.getElementById('submissionFileSize').textContent = formatFileSize(file.size);
      }
    }

    // Remove selected submission file
    function removeSubmissionFile() {
      document.getElementById('submission-file').value = '';
      document.getElementById('submissionFilePreview').classList.add('hidden');
    }

    // Join session
    function joinSession(sessionId) {
      TPAlert.info('Information', `Joining session with ID: ${sessionId}`);
    }

    // View session recording
    function viewSessionRecording(sessionId) {
      TPAlert.info('Information', `Viewing recording for session with ID: ${sessionId}`);
    }

    // Header functions
    function openNotifications() {
      TPAlert.info('Information', 'Opening notifications...');
    }

    function openMessages() {
      TPAlert.info('Information', 'Opening messages...');
    }

    function backToDashboard() {
      window.location.href = 'student-dashboard.php';
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      // Set default filter to "all"
      const allTab = document.querySelector('.filter-tab');
      if (allTab) {
        allTab.classList.remove('border-transparent', 'text-gray-500');
        allTab.classList.add('border-tplearn-green', 'text-tplearn-green');
      }

      // Handle submission form
      document.getElementById('submissionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('submission-file');
        const comments = document.getElementById('submissionComments').value;
        const assignmentId = document.getElementById('submissionModal').getAttribute('data-assignment-id');
        
        if (!fileInput.files[0]) {
          TPAlert.warning('Required', 'Please select a file to submit');
          return;
        }
        
        // Create FormData for file upload
        const formData = new FormData();
        formData.append('assignment_id', assignmentId);
        formData.append('submission_file', fileInput.files[0]);
        formData.append('comments', comments);
        
        // Submit form (implement your submission logic here)
        TPAlert.success('Success', 'Assignment submitted successfully!');
        closeSubmissionModal();
      });
    });
  </script>
</body>

</html>
