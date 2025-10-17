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

// Get materials for this program (no filter, include student submission status)
$materials = getProgramMaterials($program_id, null, null, $student_user_id);

// Debug: Check if materials are being fetched
error_log("Student Program ID: " . $program_id);
error_log("Student Materials count: " . count($materials));
if (!empty($materials)) {
  error_log("Student First material: " . print_r($materials[0], true));
}

// Helper function to get material type color and icon (matching tutor version)
function getMaterialTypeDisplay($type) {
  $displays = [
    'document' => ['color' => 'blue', 'icon' => 'M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z'],
    'video' => ['color' => 'purple', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
    'image' => ['color' => 'green', 'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
    'slides' => ['color' => 'orange', 'icon' => 'M9 17V7h2v10h-2zM20 3H4a1 1 0 00-1 1v16a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1z'],
    'assignment' => ['color' => 'purple', 'icon' => 'M9 2a1 1 0 000 2h2a1 1 0 100-2H9z M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z'],
    'other' => ['color' => 'gray', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z']
  ];
  return $displays[$type] ?? $displays['other'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($program_name); ?> - Stream - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  
  <style>
    .modal-overlay {
      animation: fadeIn 0.3s ease-out;
    }
    .modal-content {
      animation: slideIn 0.3s ease-out;
    }
    .download-btn-success {
      background-color: #10b981 !important;
    }
    .download-btn-error {
      background-color: #ef4444 !important;
    }
  </style>
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
              <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($student_name); ?></span>
              <div class="w-8 h-8 bg-tplearn-green rounded-full flex items-center justify-center text-white font-semibold text-sm">
                <?php echo strtoupper(substr($student_name, 0, 1)); ?>
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
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($program_name); ?> Stream</h1>
            <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($program_description); ?></p>
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
        </nav>
      </div>

      <!-- Main Content -->
      <main class="p-4 lg:p-6">
        <div class="space-y-6">

          <?php 
          // Display materials (same format as tutor version)
          if (!empty($materials)): 
            foreach ($materials as $material): 
              $typeDisplay = getMaterialTypeDisplay($material['material_type'] ?? 'other');
              $relativeTime = time() - strtotime($material['created_at'] ?? 'now');
              $timeAgo = '';
              
              // Handle negative time (future dates or timezone issues)
              if ($relativeTime < 0) {
                $timeAgo = 'Just now';
              } elseif ($relativeTime < 60) {
                $timeAgo = 'Just now';
              } elseif ($relativeTime < 3600) {
                $timeAgo = floor($relativeTime / 60) . ' minutes ago';
              } elseif ($relativeTime < 86400) {
                $timeAgo = floor($relativeTime / 3600) . ' hours ago';
              } elseif ($relativeTime < 604800) {
                $timeAgo = floor($relativeTime / 86400) . ' days ago';
              } else {
                $timeAgo = floor($relativeTime / 604800) . ' weeks ago';
              }
          ?>
          <!-- Material: <?php echo htmlspecialchars($material['title']); ?> -->
          <div class="content-item bg-white rounded-lg border border-gray-200 p-6" data-type="<?php echo $material['material_type'] === 'assignment' ? 'assignments' : 'documents'; ?>">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-<?php echo $typeDisplay['color']; ?>-100 rounded-lg flex items-center justify-center">
                  <svg class="w-6 h-6 text-<?php echo $typeDisplay['color']; ?>-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="<?php echo $typeDisplay['icon']; ?>" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($material['title'] ?? 'Untitled'); ?></h3>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="bg-<?php echo $typeDisplay['color']; ?>-100 text-<?php echo $typeDisplay['color']; ?>-800 px-2 py-1 rounded text-xs font-medium"><?php echo ucfirst($material['material_type'] ?? 'unknown'); ?></span>
                      <?php if (!empty($material['assessment_id'])): ?>
                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-medium">Assessment</span>
                      <?php endif; ?>
                      <span class="text-sm text-gray-500">File:</span>
                      <span class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($material['original_filename'] ?? 'Unknown'); ?></span>
                      <span class="text-sm text-gray-500">by</span>
                      <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($material['uploader_name'] ?? 'Unknown'); ?></span>
                    </div>
                    <?php if (!empty($material['description'])): ?>
                    <p class="text-sm text-gray-700 mb-3"><?php echo htmlspecialchars($material['description'] ?? ''); ?></p>
                    <?php endif; ?>
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        <?php echo $material['file_size_formatted']; ?>
                      </span>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <?php echo $material['upload_time_formatted']; ?>
                      </span>
                    </div>
                    <div class="flex items-center space-x-2">
                      <button onclick="viewItem('<?php echo $material['material_id']; ?>', 'document')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View
                      </button>
                      <button onclick="downloadItem('<?php echo $material['file_id']; ?>')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download
                      </button>
                      <?php if (!empty($material['assessment_id'])): ?>
                        <button onclick="viewAssessment('<?php echo $material['assessment_id']; ?>')" class="bg-purple-600 text-white hover:bg-purple-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                          </svg>
                          View Assessment
                        </button>
                        <?php if (isset($material['is_submitted']) && $material['is_submitted'] == 1): ?>
                          <!-- Assessment Submitted State -->
                          <div class="flex items-center space-x-2">
                            <button disabled class="bg-green-100 text-green-800 border border-green-200 text-sm font-medium px-3 py-1 rounded flex items-center cursor-not-allowed">
                              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                              </svg>
                              Assessment Submitted
                            </button>
                            <?php if ($material['assessment_submitted_at']): ?>
                            <div class="text-xs text-gray-600">
                              <div>on <?php echo date('M j, Y', strtotime($material['assessment_submitted_at'])); ?></div>
                              <div class="text-gray-500">at <?php echo date('g:i A', strtotime($material['assessment_submitted_at'])); ?></div>
                            </div>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      <?php endif; ?>
                      <?php if ($material['material_type'] === 'assignment'): ?>
                        <?php if (isset($material['is_submitted']) && $material['is_submitted'] == 1): ?>
                          <!-- Already Submitted State -->
                          <div class="flex items-center space-x-2">
                            <button disabled class="bg-green-100 text-green-800 border border-green-200 text-sm font-medium px-3 py-1 rounded flex items-center cursor-not-allowed">
                              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                              </svg>
                              Submitted
                            </button>
                            <button onclick="viewAssignmentSubmission('<?php echo $material['assignment_id']; ?>')" class="bg-blue-600 text-white hover:bg-blue-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                              </svg>
                              View Submission
                            </button>
                            <?php if ($material['submission_date']): ?>
                            <div class="text-xs text-gray-600">
                              <div>on <?php echo date('M j, Y', strtotime($material['submission_date'])); ?></div>
                              <div class="text-gray-500">at <?php echo date('g:i A', strtotime($material['submission_date'])); ?>
                                <?php if ($material['submission_is_late']): ?>
                                  <span class="text-red-500 font-medium">(Late)</span>
                                <?php endif; ?>
                              </div>
                            </div>
                            <?php endif; ?>
                          </div>
                        <?php else: ?>
                          <!-- Not Submitted State -->
                          <button onclick="submitAssignment('<?php echo $material['material_id']; ?>')" class="bg-tplearn-green text-white hover:bg-green-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Submit
                          </button>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-sm text-gray-500 text-right">
                    <div><?php echo $timeAgo; ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php 
            endforeach;
          endif;

          // Show empty state if no content
          if (empty($materials)): 
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

  <!-- Notification Modal -->
  <div id="notificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95" id="notificationModalContent">
      <div class="p-6">
        <div class="flex items-start">
          <div id="notificationIcon" class="w-12 h-12 rounded-full flex items-center justify-center mr-4">
            <!-- Icon will be dynamically set based on notification type -->
          </div>
          <div class="flex-1">
            <h3 id="notificationTitle" class="text-lg font-medium text-gray-900 mb-2">
              <!-- Title will be set dynamically -->
            </h3>
            <p id="notificationMessage" class="text-sm text-gray-600">
              <!-- Message will be set dynamically -->
            </p>
          </div>
        </div>
        <div class="mt-6 flex justify-end">
          <button onclick="closeNotificationModal()" id="notificationButton" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            OK
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Assessment View Modal -->
  <div id="assessmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">Assessment</h3>
        <button onclick="closeAssessmentModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Assessment Details -->
        <div id="assessmentContent" class="space-y-6">
          <!-- Content will be loaded dynamically -->
          <div class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mx-auto"></div>
            <p class="text-gray-600 mt-2">Loading assessment...</p>
          </div>
        </div>

        <!-- Assessment Actions -->
        <div id="assessmentActions" class="mt-6 flex justify-end space-x-3 hidden">
          <button onclick="closeAssessmentModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
            Close
          </button>
          <button id="startAssessmentBtn" onclick="startAssessment()" class="px-4 py-2 bg-purple-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-purple-700">
            Start Assessment
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Submission Modal -->
  <div id="viewSubmissionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">View Submission</h3>
        <button onclick="closeViewSubmissionModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-6">
        <div id="viewSubmissionContent">
          <!-- Content will be loaded dynamically -->
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Submission Modal -->
  <div id="editSubmissionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">Edit Submission</h3>
        <button onclick="closeEditSubmissionModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-6">
        <div id="editSubmissionContent">
          <!-- Content will be loaded dynamically -->
        </div>
      </div>
    </div>
  </div>

  <!-- Grades Modal -->
  <div id="gradesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">Assessment Grades</h3>
        <button onclick="closeGradesModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-6">
        <div id="gradesContent">
          <!-- Content will be loaded dynamically -->
        </div>
      </div>
    </div>
  </div>

  <!-- Assignment Submission View Modal -->
  <div id="assignmentSubmissionViewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">Assignment</h3>
        <button onclick="closeAssignmentSubmissionViewModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-6">
        <div id="assignmentSubmissionViewContent">
          <!-- Content will be loaded dynamically -->
        </div>
      </div>
    </div>
  </div>

  <script>
    // Essential functions defined immediately on window object
    window.viewAssignmentSubmission = function(assignmentId) {
      console.log('viewAssignmentSubmission called with:', assignmentId);
      
      const modal = document.getElementById('assignmentSubmissionViewModal');
      const content = document.getElementById('assignmentSubmissionViewContent');
      
      if (!modal || !content) {
        console.error('Modal elements not found');
        return;
      }
      
      content.innerHTML = '<div class="text-center py-8"><p>Loading...</p></div>';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      
      fetch('/TPLearn/api/get-assignment-submission.php?assignment_id=' + assignmentId)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.has_submission) {
            displayAssignmentSubmission(data.submission, data.assignment);
          } else if (data.success && !data.has_submission) {
            showNoSubmissionMessage(data.assignment);
          } else {
            showSubmissionError(data.error || 'Failed to load submission details');
          }
        })
        .catch(error => {
          console.error('Error loading assignment submission:', error);
          showSubmissionError('Failed to load submission details');
        });
    };
    
    window.downloadItem = function(fileId) {
      console.log('downloadItem called with:', fileId);
      if (!fileId) return;
      
      const downloadUrl = '/TPLearn/api/serve-program-file.php?file_id=' + fileId + '&action=download';
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = '';
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    };
    
    window.viewAssessment = function(assessmentId) {
      console.log('viewAssessment called with:', assessmentId);
      const modal = document.getElementById('assessmentModal');
      if (!modal) return;
      
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      
      fetch('/TPLearn/api/get-assessment.php?assessment_id=' + assessmentId)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayAssessmentDetails(data.assessment);
            loadAssessmentAttempts(assessmentId);
          }
        })
        .catch(error => {
          console.error('Error loading assessment:', error);
        });
    };
    
    window.viewItem = function(materialId, type) {
      console.log('viewItem called with:', materialId, type);
      
      fetch('/TPLearn/api/get-program-material.php?material_id=' + materialId)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const material = data.material;
            const fileUrl = '/TPLearn/api/serve-program-file.php?file_id=' + material.file.id + '&action=view';
            
            if (material.file.mime_type && material.file.mime_type.startsWith('image/')) {
              showImageModal(material, fileUrl);
            } else {
              showFileInfoModal(material, fileUrl);
            }
          }
        })
        .catch(error => {
          console.error('Error loading material:', error);
        });
    };
    
    window.submitAssignment = function(assignmentId) {
      console.log('submitAssignment called with:', assignmentId);
      
      fetch('/TPLearn/api/get-program-material.php?material_id=' + assignmentId)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const material = data.material;
            
            const titleElement = document.getElementById('assignmentTitle');
            const descElement = document.getElementById('assignmentDescription');
            const idElement = document.getElementById('submissionAssignmentId');
            const modal = document.getElementById('submissionModal');
            
            if (titleElement) titleElement.textContent = material.title;
            if (descElement) descElement.textContent = material.description || 'No description provided.';
            if (idElement) idElement.value = assignmentId;
            if (modal) {
              modal.classList.remove('hidden');
              modal.classList.add('flex');
            }
          }
        })
        .catch(error => {
          console.error('Error loading assignment details:', error);
        });
    };
    
    window.filterContent = function(type) {
      console.log('filterContent called with:', type);
      const items = document.querySelectorAll('.content-item');
      const tabs = document.querySelectorAll('.filter-tab');

      tabs.forEach(tab => {
        tab.classList.remove('border-tplearn-green', 'text-tplearn-green');
        tab.classList.add('border-transparent', 'text-gray-500');
      });

      if (event && event.target) {
        const activeTab = event.target;
        activeTab.classList.remove('border-transparent', 'text-gray-500');
        activeTab.classList.add('border-tplearn-green', 'text-tplearn-green');
      }

      items.forEach(item => {
        const itemType = item.dataset.type;
        if (type === 'all' || itemType === type) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    };
    
    // Modal close functions
    window.closeSubmissionModal = function() {
      const modal = document.getElementById('submissionModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    };

    window.closeAssignmentSubmissionViewModal = function() {
      const modal = document.getElementById('assignmentSubmissionViewModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    };
    
    window.closeAssessmentModal = function() {
      const modal = document.getElementById('assessmentModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    };
    
    // Navigation functions
    window.openNotifications = function() { console.log('openNotifications'); };
    window.openMessages = function() { console.log('openMessages'); };
    window.backToDashboard = function() { window.location.href = 'student-academics.php'; };
    
    // Simple aliases for backward compatibility
    function viewAssignmentSubmission(assignmentId) { return window.viewAssignmentSubmission(assignmentId); }
    function downloadItem(fileId) { return window.downloadItem(fileId); }
    function viewAssessment(assessmentId) { return window.viewAssessment(assessmentId); }
    function viewItem(materialId, type) { return window.viewItem(materialId, type); }
    function submitAssignment(assignmentId) { return window.submitAssignment(assignmentId); }
    function filterContent(type) { return window.filterContent(type); }
    function closeSubmissionModal() { return window.closeSubmissionModal(); }
    function closeAssignmentSubmissionViewModal() { return window.closeAssignmentSubmissionViewModal(); }
    function closeAssessmentModal() { return window.closeAssessmentModal(); }
    function openNotifications() { return window.openNotifications(); }
    function openMessages() { return window.openMessages(); }
    function backToDashboard() { return window.backToDashboard(); }
    
    console.log('JavaScript loaded successfully - all functions available');
    
    // Helper functions for assignment submission display

    function downloadItem(fileId) {
      console.log('Download item called with:', fileId);
      // Show download progress
      const downloadBtn = event?.target?.closest('button');
      const originalText = downloadBtn?.innerHTML;
      
      if (downloadBtn) {
        downloadBtn.disabled = true;
        downloadBtn.innerHTML = `
          <svg class="w-4 h-4 mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Downloading...
        `;
      }
      
      // Create download URL and trigger download
      const downloadUrl = `/TPLearn/api/serve-program-file.php?file_id=${fileId}&action=download`;
      
      // Create a temporary link and click it
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = '';
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      
      // Handle download completion/failure
      const handleDownloadComplete = () => {
        if (downloadBtn) {
          downloadBtn.disabled = false;
          downloadBtn.innerHTML = originalText;
        }
        document.body.removeChild(link);
      };
      
      // Set a timeout to reset the button
      setTimeout(handleDownloadComplete, 3000);
    }

    function viewAssessment(assessmentId) {
      console.log('View assessment called with:', assessmentId);
      // Show modal
      document.getElementById('assessmentModal').classList.remove('hidden');
      document.getElementById('assessmentModal').classList.add('flex');
      
      // Load assessment details
      fetch(`/TPLearn/api/get-assessment.php?assessment_id=${assessmentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayAssessmentDetails(data.assessment);
            loadAssessmentAttempts(assessmentId);
          } else {
            console.error('Failed to load assessment:', data.message);
          }
        })
        .catch(error => {
          console.error('Error loading assessment:', error);
        });
    }

    function viewItem(materialId, type) {
      console.log('View item called with:', materialId, type);
      // Fetch material details
      fetch(`/TPLearn/api/get-program-material.php?material_id=${materialId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const material = data.material;
            const fileUrl = `/TPLearn/api/serve-program-file.php?file_id=${material.file.id}&action=view`;
            
            if (material.file.mime_type && material.file.mime_type.startsWith('image/')) {
              showImageModal(material, fileUrl);
            } else {
              showFileInfoModal(material, fileUrl);
            }
          }
        })
        .catch(error => {
          console.error('Error loading material:', error);
        });
    }

    function submitAssignment(assignmentId) {
      console.log('Submit assignment called with:', assignmentId);
      // First fetch assignment details to populate the modal
      fetch(`/TPLearn/api/get-program-material.php?material_id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
          console.log('Assignment API Response:', data);
          if (data.success) {
            const material = data.material;
            
            // Populate the form with assignment details
            document.getElementById('assignmentTitle').textContent = material.title;
            document.getElementById('assignmentDescription').textContent = material.description || 'No description provided.';
            document.getElementById('submissionAssignmentId').value = assignmentId;
            
            // Show the modal
            document.getElementById('submissionModal').classList.remove('hidden');
            document.getElementById('submissionModal').classList.add('flex');
          } else {
            console.error('Failed to load assignment details:', data.message);
          }
        })
        .catch(error => {
          console.error('Error loading assignment details:', error);
        });
    }

    function filterContent(type) {
      console.log('Filter content called with:', type);
      const items = document.querySelectorAll('.content-item');
      const tabs = document.querySelectorAll('.filter-tab');

      // Update tab styles
      tabs.forEach(tab => {
        tab.classList.remove('border-tplearn-green', 'text-tplearn-green');
        tab.classList.add('border-transparent', 'text-gray-500');
      });

      // Highlight active tab
      const activeTab = event.target;
      activeTab.classList.remove('border-transparent', 'text-gray-500');
      activeTab.classList.add('border-tplearn-green', 'text-tplearn-green');

      // Filter items
      items.forEach(item => {
        const itemType = item.dataset.type;
        if (type === 'all' || itemType === type) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    }

    // Navigation functions
    function openNotifications() { 
      console.log('openNotifications'); 
    }
    
    function openMessages() { 
      console.log('openMessages'); 
    }
    
    function backToDashboard() { 
      window.location.href = 'student-academics.php'; 
    }

    // Notification Modal Functions
    function showNotification(type, title, message, callback = null) {
      const modal = document.getElementById('notificationModal');
      const modalContent = document.getElementById('notificationModalContent');
      const icon = document.getElementById('notificationIcon');
      const titleElement = document.getElementById('notificationTitle');
      const messageElement = document.getElementById('notificationMessage');
      const button = document.getElementById('notificationButton');
      
      // Configure based on type
      if (type === 'success') {
        icon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-green-100';
        icon.innerHTML = `
          <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
        `;
        button.className = 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500';
      } else if (type === 'error') {
        icon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-red-100';
        icon.innerHTML = `
          <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        `;
        button.className = 'bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500';
      } else if (type === 'warning') {
        icon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-yellow-100';
        icon.innerHTML = `
          <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.888-.833-2.598 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
          </svg>
        `;
        button.className = 'bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500';
      } else { // info
        icon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-blue-100';
        icon.innerHTML = `
          <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        `;
        button.className = 'bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500';
      }
      
      titleElement.textContent = title;
      messageElement.textContent = message;
      
      // Store callback for later use
      window.notificationCallback = callback;
      
      // Show modal with animation
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      
      // Animate in
      setTimeout(() => {
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
      }, 10);
    }

    function closeNotificationModal() {
      const modal = document.getElementById('notificationModal');
      const modalContent = document.getElementById('notificationModalContent');
      
      // Animate out
      modalContent.classList.remove('scale-100');
      modalContent.classList.add('scale-95');
      
      setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        
        // Execute callback if provided
        if (window.notificationCallback) {
          window.notificationCallback();
          window.notificationCallback = null;
        }
      }, 300);
    }

    // Helper function for file size formatting
    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Filter content by type
    function internalFilterContent(type) {
      const items = document.querySelectorAll('.content-item');
      const tabs = document.querySelectorAll('.filter-tab');

      // Update tab styles
      tabs.forEach(tab => {
        tab.classList.remove('border-tplearn-green', 'text-tplearn-green');
        tab.classList.add('border-transparent', 'text-gray-500');
      });
      event.target.classList.remove('border-transparent', 'text-gray-500');
      event.target.classList.add('border-tplearn-green', 'text-tplearn-green');

      // Filter items - only documents and assignments now
      items.forEach(item => {
        if (type === 'all' || item.dataset.type === type) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    }

    // View item details
    function internalViewItem(materialId, type) {
      // Fetch material details
      fetch(`../../api/get-program-material.php?material_id=${materialId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const material = data.material;
            const fileUrl = `../../api/serve-program-file.php?file_id=${material.file.id}&action=view`;
            
            // Determine how to display based on file type
            const mimeType = material.file.mime_type.toLowerCase();
            
            if (mimeType.startsWith('image/')) {
              // For images, show in a modal
              showImageModal(material, fileUrl);
            } else if (mimeType === 'application/pdf') {
              // For PDFs, open in new tab
              window.open(fileUrl, '_blank');
            } else if (mimeType.startsWith('text/') || 
                       mimeType.includes('document') || 
                       mimeType.includes('word') ||
                       mimeType.includes('presentation') ||
                       mimeType.includes('spreadsheet')) {
              // For documents, try to open in new tab or download
              window.open(fileUrl, '_blank');
            } else {
              // For other types, show info modal and allow download
              showFileInfoModal(material, fileUrl);
            }
          } else {
            alert('Error loading material: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error fetching material:', error);
          alert('Error loading material details');
        });
    }

    // Show image in modal
    function showImageModal(material, fileUrl) {
      const modal = document.createElement('div');
      modal.className = 'modal-overlay fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="modal-content max-w-4xl max-h-full p-4">
          <div class="bg-white rounded-lg overflow-hidden shadow-2xl">
            <div class="flex justify-between items-center p-4 border-b bg-gray-50">
              <h3 class="text-lg font-semibold text-gray-900">${material.title}</h3>
              <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            <div class="p-6">
              <div class="text-center mb-4">
                <img src="${fileUrl}" alt="${material.title}" class="max-w-full max-h-96 mx-auto rounded-lg shadow-md" 
                     onerror="this.parentElement.innerHTML='<p class=&quot;text-red-500&quot;>Unable to load image</p>'" />
              </div>
              <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <p class="font-medium text-gray-700">File Details</p>
                    <p><strong>Name:</strong> ${material.file.original_filename}</p>
                    <p><strong>Size:</strong> ${formatFileSize(material.file.file_size)}</p>
                    <p><strong>Type:</strong> ${material.material_type}</p>
                  </div>
                  <div>
                    <p class="font-medium text-gray-700">Upload Info</p>
                    <p><strong>Uploaded by:</strong> ${material.uploader.name}</p>
                    <p><strong>Program:</strong> ${material.program.name}</p>
                  </div>
                </div>
                ${material.description ? `<div class="mt-4 pt-4 border-t border-gray-200"><p class="font-medium text-gray-700">Description</p><p>${material.description}</p></div>` : ''}
              </div>
              <div class="mt-6 flex justify-center">
                <button onclick="downloadItem('${material.file.id}')" class="bg-tplearn-green text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  Download Image
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      
      // Close modal when clicking outside
      modal.addEventListener('click', function(e) {
        if (e.target === modal) {
          modal.remove();
        }
      });
      
      // Close modal with Escape key
      const handleEscape = function(e) {
        if (e.key === 'Escape') {
          modal.remove();
          document.removeEventListener('keydown', handleEscape);
        }
      };
      document.addEventListener('keydown', handleEscape);
      
      // Focus the close button for accessibility
      setTimeout(() => {
        const closeBtn = modal.querySelector('button');
        if (closeBtn) closeBtn.focus();
      }, 100);
    }

    // Show file info modal
    function showFileInfoModal(material, fileUrl) {
      const modal = document.createElement('div');
      modal.className = 'modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
          <div class="flex justify-between items-center p-6 border-b bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">${material.title}</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div class="flex items-center mb-4">
              <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
              </div>
              <div>
                <h4 class="font-medium text-gray-900">${material.file.original_filename}</h4>
                <p class="text-sm text-gray-500">${formatFileSize(material.file.file_size)}</p>
              </div>
            </div>
            ${material.description ? `<div class="bg-gray-50 rounded-lg p-3 mb-4"><p class="text-sm text-gray-700">${material.description}</p></div>` : ''}
            <div class="space-y-2 text-sm text-gray-600 mb-6">
              <div class="flex justify-between">
                <span class="font-medium">Type:</span>
                <span>${material.material_type}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-medium">Uploaded by:</span>
                <span>${material.uploader.name}</span>
              </div>
              <div class="flex justify-between">
                <span class="font-medium">Program:</span>
                <span>${material.program.name}</span>
              </div>
            </div>
            <div class="flex space-x-3">
              <button onclick="window.open('${fileUrl}', '_blank')" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                Open File
              </button>
              <button onclick="downloadItem('${material.file.id}')" class="flex-1 bg-tplearn-green text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      
      // Close modal when clicking outside
      modal.addEventListener('click', function(e) {
        if (e.target === modal) {
          modal.remove();
        }
      });
      
      // Close modal with Escape key
      const handleEscape = function(e) {
        if (e.key === 'Escape') {
          modal.remove();
          document.removeEventListener('keydown', handleEscape);
        }
      };
      document.addEventListener('keydown', handleEscape);
      
      // Focus the close button for accessibility
      setTimeout(() => {
        const closeBtn = modal.querySelector('button');
        if (closeBtn) closeBtn.focus();
      }, 100);
    }

    // Download item
    function internalDownloadItem(fileId) {
      // Show download progress
      const downloadBtn = event?.target?.closest('button');
      const originalText = downloadBtn?.innerHTML;
      
      if (downloadBtn) {
        downloadBtn.disabled = true;
        downloadBtn.innerHTML = `
          <svg class="w-4 h-4 mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Downloading...
        `;
      }
      
      // Create download URL and trigger download
      const downloadUrl = `../../api/serve-program-file.php?file_id=${fileId}&action=download`;
      
      // Create a temporary link and click it
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = '';
      link.style.display = 'none';
      document.body.appendChild(link);
      
      // Handle download completion/failure
      const handleDownloadComplete = () => {
        if (downloadBtn) {
          downloadBtn.disabled = false;
          downloadBtn.innerHTML = originalText;
        }
        document.body.removeChild(link);
      };
      
      // Set a timeout to reset the button
      setTimeout(handleDownloadComplete, 3000);
      
      // Trigger the download
      try {
        link.click();
        
        // Show success message
        setTimeout(() => {
          if (downloadBtn) {
            downloadBtn.innerHTML = `
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
              Downloaded
            `;
            setTimeout(() => {
              if (downloadBtn && originalText) {
                downloadBtn.innerHTML = originalText;
              }
            }, 2000);
          }
        }, 1000);
        
      } catch (error) {
        console.error('Download error:', error);
        if (downloadBtn) {
          downloadBtn.innerHTML = `
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            Failed
          `;
          setTimeout(() => {
            if (originalText) {
              downloadBtn.innerHTML = originalText;
            }
          }, 2000);
        }
        alert('Download failed. Please try again.');
      }
    }

    // Submit assignment
    function internalSubmitAssignment(assignmentId) {
      // First fetch assignment details to populate the modal
      fetch(`../../api/get-program-material.php?material_id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
          console.log('Assignment API Response:', data); // Debug log
          if (data.success && data.material) {
            // Populate assignment details in modal
            document.getElementById('submissionAssignmentTitle').textContent = data.material.title;
            document.getElementById('submissionAssignmentDescription').textContent = data.material.description || 'No description provided';
            
            console.log('Due date from API:', data.material.due_date); // Debug log
            if (data.material.due_date && data.material.due_date !== null && data.material.due_date !== 'null') {
              const dueDate = new Date(data.material.due_date);
              console.log('Parsed due date:', dueDate); // Debug log
              // Check if the date is valid
              if (!isNaN(dueDate.getTime())) {
                document.getElementById('submissionDueDate').textContent = `Due: ${dueDate.toLocaleDateString('en-US', {
                  weekday: 'long',
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                })}`;
              } else {
                document.getElementById('submissionDueDate').textContent = 'Due date format error';
              }
            } else {
              document.getElementById('submissionDueDate').textContent = 'No due date specified';
            }
          }
        })
        .catch(error => {
          console.error('Error fetching assignment details:', error);
          // Still show modal with basic info
          document.getElementById('submissionAssignmentTitle').textContent = 'Assignment Submission';
          document.getElementById('submissionAssignmentDescription').textContent = 'Submit your completed work for grading';
          // Set a sample due date for testing
          document.getElementById('submissionDueDate').textContent = 'Due: Friday, October 11, 2025 at 11:59 PM';
        });

      // Store assignment ID for form submission
      document.getElementById('submissionModal').setAttribute('data-assignment-id', assignmentId);
      
      // Show submission modal
      document.getElementById('submissionModal').classList.remove('hidden');
      document.getElementById('submissionModal').classList.add('flex');
      
      // Focus the modal for accessibility
      setTimeout(() => {
        const modal = document.getElementById('submissionModal');
        const closeBtn = modal.querySelector('button');
        if (closeBtn) closeBtn.focus();
      }, 100);
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
          alert('File size must be less than 10MB');
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

    // Assessment functions
    function internalViewAssessment(assessmentId) {
      // Show modal
      document.getElementById('assessmentModal').classList.remove('hidden');
      document.getElementById('assessmentModal').classList.add('flex');
      
      // Load assessment details
      fetch(`../../api/get-assessment.php?assessment_id=${assessmentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayAssessmentDetails(data.assessment);
          } else {
            showNotification('error', 'Error', 'Failed to load assessment: ' + (data.error || 'Unknown error'));
            closeAssessmentModal();
          }
        })
        .catch(error => {
          console.error('Error loading assessment:', error);
          showNotification('error', 'Error', 'Failed to load assessment details');
          closeAssessmentModal();
        });
    }

    function displayAssessmentDetails(assessment) {
      const content = document.getElementById('assessmentContent');
      const actions = document.getElementById('assessmentActions');
      
      // First load the basic assessment info, then fetch attempts
      content.innerHTML = `
        <div class="space-y-6">
          <!-- Assessment Header -->
          <div class="flex items-start">
            <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center mr-4">
              <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
            </div>
            <div class="flex-1">
              <h4 class="text-lg font-semibold text-gray-900">${assessment.title}</h4>
              <p class="text-sm text-gray-600 mt-1">${assessment.description || 'Video explaining 3D geometric shapes and their properties'}</p>
            </div>
          </div>
          
          <!-- Assessment Submission Status -->
          <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
              <div class="grid grid-cols-5 gap-4 text-xs font-medium text-gray-500 uppercase tracking-wide">
                <div>Assessment</div>
                <div>Created</div>
                <div>Status</div>
                <div>Score</div>
                <div>Actions</div>
              </div>
            </div>
            <div id="assessmentAttempts" class="divide-y divide-gray-200">
              <!-- Submission status will be loaded here -->
              <div class="px-6 py-4 text-center text-gray-500">
                <svg class="animate-spin mx-auto h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2">Loading submission status...</p>
              </div>
            </div>
          </div>
          
          ${assessment.file_name ? `
          <!-- Assessment Document -->
          <div class="border rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <svg class="w-8 h-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <div>
                  <h5 class="font-medium text-gray-900">${assessment.file_name}</h5>
                  <p class="text-sm text-gray-500">Assessment Document</p>
                </div>
              </div>
              <button onclick="downloadAssessmentFile(${assessment.id})" class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg text-sm font-medium">
                Download
              </button>
            </div>
          </div>
          ` : ''}
        </div>
      `;
      
      // Store assessment data for later use
      window.currentAssessment = assessment;
      
      // Load assessment attempts
      loadAssessmentAttempts(assessment.id);
      
      // Show actions (Start Assessment button will be hidden/shown based on submission status)
      actions.classList.remove('hidden');
      
      // Initially show the Start Assessment button (will be hidden if submission exists)
      const startAssessmentBtn = document.getElementById('startAssessmentBtn');
      if (startAssessmentBtn) {
        startAssessmentBtn.style.display = 'inline-flex';
      }
    }

    function loadAssessmentAttempts(assessmentId) {
      fetch(`../../api/get-assessment-submission.php?assessment_id=${assessmentId}`)
        .then(response => response.json())
        .then(data => {
          const attemptsContainer = document.getElementById('assessmentAttempts');
          const startAssessmentBtn = document.getElementById('startAssessmentBtn');
          
          if (data.success && data.has_submission && data.submission) {
            // Hide Start Assessment button since there's already a submission
            if (startAssessmentBtn) {
              startAssessmentBtn.style.display = 'none';
            }
            
            const submission = data.submission;
            
            // Store submission data globally for use in other functions
            window.currentSubmissionData = submission;
            
            // Check if editing is allowed (before due date)
            const canEdit = submission.due_date && new Date() < new Date(submission.due_date);
            
            const statusColor = submission.status === 'submitted' ? 'text-green-600' : 
                               submission.status === 'graded' ? 'text-blue-600' : 
                               submission.status === 'in_progress' ? 'text-yellow-600' : 'text-gray-600';
            const statusBg = submission.status === 'submitted' ? 'bg-green-100' : 
                            submission.status === 'graded' ? 'bg-blue-100' :
                            submission.status === 'in_progress' ? 'bg-yellow-100' : 'bg-gray-100';
            
            attemptsContainer.innerHTML = `
              <div class="px-6 py-4">
                <div class="grid grid-cols-5 gap-4 items-center">
                  <div>
                    <p class="text-sm font-medium text-gray-900">${window.currentAssessment.title}</p>
                    <p class="text-xs text-gray-500">Your submission for this assessment</p>
                    ${submission.original_filename ? `
                      <div class="mt-2 flex items-center text-xs text-gray-600">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="truncate max-w-32" title="${submission.original_filename}">${submission.original_filename}</span>
                      </div>
                    ` : ''}
                  </div>
                  <div>
                    <p class="text-sm text-gray-900">${new Date(submission.created_at).toLocaleDateString('en-US', { 
                      month: 'short', 
                      day: 'numeric', 
                      year: 'numeric' 
                    })}, ${new Date(submission.created_at).toLocaleTimeString('en-US', { 
                      hour: '2-digit', 
                      minute: '2-digit' 
                    })}</p>
                  </div>
                  <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusBg} ${statusColor}">
                      ${submission.status === 'submitted' ? '● Submitted' : 
                        submission.status === 'graded' ? '● Graded' :
                        submission.status === 'in_progress' ? '● In Progress' : '● ' + submission.status}
                    </span>
                  </div>
                  <div>
                    <p class="text-sm text-gray-900">${submission.score ? submission.score + '%' : (submission.percentage ? submission.percentage + '%' : 'Pending')}</p>
                  </div>
                  <div class="flex space-x-2">
                    ${submission.status === 'submitted' || submission.status === 'graded' ? `
                      ${submission.submission_file_id ? `
                        <button onclick="viewSubmissionFile(${submission.submission_file_id}, '${submission.original_filename || 'submission'}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                          View
                        </button>
                        <button onclick="downloadSubmissionFile(${submission.submission_file_id})" class="text-green-600 hover:text-green-800 text-sm font-medium">
                          Download
                        </button>
                        ${canEdit ? `
                          <button onclick="editSubmission(${submission.id})" class="text-orange-600 hover:text-orange-800 text-sm font-medium">
                            Edit
                          </button>
                        ` : ''}
                        <button onclick="viewGrades(${submission.id})" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                          Grades
                        </button>
                      ` : `
                        <span class="text-gray-500 text-sm">No file</span>
                        <button onclick="viewGrades(${submission.id})" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                          Grades
                        </button>
                      `}
                    ` : `
                      <button onclick="continueAssessment(${submission.id})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Continue
                      </button>
                      ${submission.submission_file_id && canEdit ? `
                        <button onclick="editSubmission(${submission.id})" class="text-orange-600 hover:text-orange-800 text-sm font-medium">
                          Edit
                        </button>
                      ` : ''}
                    `}
                  </div>
                </div>
              </div>
            `;
          } else {
            // Show Start Assessment button since there's no submission yet
            if (startAssessmentBtn) {
              startAssessmentBtn.style.display = 'inline-flex';
            }
            
            // No submission yet - show start assessment option
            attemptsContainer.innerHTML = `
              <div class="px-6 py-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No submission yet</h3>
                <p class="mt-1 text-sm text-gray-500">Start your assessment (only one submission allowed)</p>
                <div class="mt-6">
                  <button onclick="startAssessment()" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Start Assessment
                  </button>
                </div>
              </div>
            `;
          }
        })
        .catch(error => {
          console.error('Error loading submission:', error);
          document.getElementById('assessmentAttempts').innerHTML = `
            <div class="px-6 py-4 text-center text-red-600">
              <p>Error loading assessment submission</p>
            </div>
          `;
        });
    }

    function viewAssessmentResults(attemptId) {
      showNotification('info', 'Assessment Results', 'Loading assessment results...');
      // TODO: Implement assessment results viewing
    }

    function downloadSubmissionFile(fileId) {
      console.log('Download button clicked! fileId:', fileId);
      const downloadUrl = `../../api/serve-submission-file.php?file_id=${fileId}&action=download`;
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = '';
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    function viewSubmissionFile(fileId, filename) {
      console.log('View button clicked! fileId:', fileId, 'filename:', filename);
      // Determine if we can view the file inline based on extension
      const extension = filename.split('.').pop().toLowerCase();
      const viewableExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
      
      if (viewableExtensions.includes(extension)) {
        // Open in new tab for viewing
        const viewUrl = `../../api/serve-submission-file.php?file_id=${fileId}&action=view`;
        window.open(viewUrl, '_blank');
      } else {
        // For non-viewable files, show info and offer download
        showFileInfoModal(fileId, filename);
      }
    }

    function showFileInfoModal(fileId, filename) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Submission File</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div class="flex items-center mb-4">
              <svg class="w-12 h-12 text-blue-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <div>
                <h4 class="font-medium text-gray-900">${filename}</h4>
                <p class="text-sm text-gray-500">Your submission file</p>
              </div>
            </div>
            <p class="text-sm text-gray-600 mb-6">
              This file cannot be previewed in the browser. Click the button below to download and view it.
            </p>
            <div class="flex justify-end space-x-3">
              <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                Close
              </button>
              <button onclick="downloadSubmissionFile(${fileId}); this.closest('.fixed').remove();" class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                Download File
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    function checkCanEdit(submission) {
      // Check if submission can be edited (before due date and not finally submitted)
      if (!submission.assessment_due_date) return true; // No due date means can edit
      
      const dueDate = new Date(submission.assessment_due_date);
      const now = new Date();
      
      // Can edit if due date hasn't passed and submission is not finalized
      return now < dueDate && submission.status !== 'graded';
    }

    function editSubmission(attemptId) {
      console.log('Edit button clicked! attemptId:', attemptId);
      // Show edit submission modal
      const modal = document.createElement('div');
      modal.id = 'editSubmissionModal';
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Edit Assessment Submission</h3>
            <button onclick="closeEditSubmissionModal()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <div class="p-6">
            <form id="editSubmissionForm" class="space-y-6">
              <input type="hidden" id="editAttemptId" value="${attemptId}">
              
              <!-- Current File Display -->
              <div class="bg-blue-50 rounded-lg p-4">
                <h4 class="font-medium text-blue-900 mb-2">Current Submission</h4>
                <div id="currentFileInfo" class="text-sm text-blue-700">
                  Loading current file information...
                </div>
              </div>
              
              <!-- New File Upload -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Upload New File (Optional)
                </label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                  <input type="file" id="editSubmissionFile" name="submission_file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar" class="hidden">
                  <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                  <div class="mt-4">
                    <button type="button" onclick="document.getElementById('editSubmissionFile').click()" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-700">
                      Choose New File
                    </button>
                    <p class="mt-2 text-sm text-gray-500">or keep the current file</p>
                  </div>
                  <p class="text-xs text-gray-500 mt-2">
                    PDF, DOC, DOCX, TXT, Images, ZIP, RAR up to 10MB
                  </p>
                </div>
                
                <!-- File Preview for New Upload -->
                <div id="editFilePreview" class="mt-4 hidden bg-gray-50 rounded-lg p-4">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center">
                      <svg class="w-8 h-8 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                      </svg>
                      <div>
                        <p id="editFileName" class="font-medium text-gray-900"></p>
                        <p id="editFileSize" class="text-sm text-gray-500"></p>
                      </div>
                    </div>
                    <button type="button" onclick="removeEditFile()" class="text-red-600 hover:text-red-700">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
              
              <!-- Comments -->
              <div>
                <label for="editComments" class="block text-sm font-medium text-gray-700 mb-2">
                  Comments (Optional)
                </label>
                <textarea id="editComments" name="comments" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500" placeholder="Add any comments about your submission..."></textarea>
              </div>
              
              <!-- Warning -->
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                  <svg class="w-5 h-5 text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                  </svg>
                  <div>
                    <h4 class="text-sm font-medium text-yellow-800">Important</h4>
                    <p class="text-sm text-yellow-700 mt-1">
                      If you upload a new file, it will replace your current submission. Make sure this is what you want before proceeding.
                    </p>
                  </div>
                </div>
              </div>
              
              <!-- Submit Button -->
              <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditSubmissionModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                  Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-purple-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-purple-700">
                  Update Submission
                </button>
              </div>
            </form>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Load current submission info
      loadCurrentSubmissionInfo(attemptId);
      
      // Handle file selection
      document.getElementById('editSubmissionFile').addEventListener('change', function(e) {
        handleEditFileSelect(e.target);
      });
      
      // Handle form submission
      document.getElementById('editSubmissionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitEditedAssessment();
      });
    }

    function loadCurrentSubmissionInfo(attemptId) {
      // You could make an API call here to get current submission details
      // For now, we'll use the data we already have
      const currentFileInfo = document.getElementById('currentFileInfo');
      
      // Get submission data from the current display
      const submission = window.currentSubmissionData;
      if (submission && submission.original_filename) {
        currentFileInfo.innerHTML = `
          <div class="flex items-center">
            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span class="font-medium">${submission.original_filename}</span>
          </div>
          <p class="text-xs mt-1">Submitted: ${new Date(submission.created_at).toLocaleString()}</p>
        `;
      } else {
        currentFileInfo.innerHTML = '<p class="text-gray-500">No file currently uploaded</p>';
      }
    }

    function handleEditFileSelect(input) {
      const file = input.files[0];
      if (file) {
        // Validate file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
          showNotification('error', 'File Too Large', 'Please select a file smaller than 10MB');
          input.value = '';
          return;
        }
        
        // Show file preview
        document.getElementById('editFilePreview').classList.remove('hidden');
        document.getElementById('editFileName').textContent = file.name;
        document.getElementById('editFileSize').textContent = formatFileSize(file.size);
      }
    }

    function removeEditFile() {
      document.getElementById('editSubmissionFile').value = '';
      document.getElementById('editFilePreview').classList.add('hidden');
    }

    function submitEditedAssessment() {
      const form = document.getElementById('editSubmissionForm');
      const fileInput = document.getElementById('editSubmissionFile');
      const comments = document.getElementById('editComments').value;
      const attemptId = document.getElementById('editAttemptId').value;
      
      // Show loading state
      const submitButton = form.querySelector('button[type="submit"]');
      const originalText = submitButton.textContent;
      submitButton.disabled = true;
      submitButton.innerHTML = `
        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Updating...
      `;
      
      // Create FormData for submission
      const formData = new FormData();
      formData.append('attempt_id', attemptId);
      formData.append('comments', comments);
      
      if (fileInput.files[0]) {
        formData.append('submission_file', fileInput.files[0]);
      }
      
      // Submit to API
      fetch('../../api/edit-assessment-submission.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
        
        if (data.success) {
          showNotification('success', 'Success', 'Assessment submission updated successfully', () => {
            closeEditSubmissionModal();
            // Refresh the assessment display
            if (window.currentAssessment) {
              loadAssessmentAttempts(window.currentAssessment.id);
            }
          });
        } else {
          showNotification('error', 'Update Failed', data.message || 'Failed to update submission');
        }
      })
      .catch(error => {
        console.error('Error updating submission:', error);
        submitButton.disabled = false;
        submitButton.textContent = originalText;
        showNotification('error', 'Update Failed', 'Failed to update submission. Please try again.');
      });
    }

    function closeEditSubmissionModal() {
      const modal = document.getElementById('editSubmissionModal');
      if (modal) {
        modal.remove();
      }
    }

    function viewGrades(attemptId) {
      // Show the grades modal
      const modal = document.getElementById('gradesModal');
      const gradesContent = document.getElementById('gradesContent');
      
      // Show loading state
      gradesContent.innerHTML = `
        <div class="text-center py-4">
          <svg class="animate-spin mx-auto h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="mt-2 text-gray-600">Loading grades...</p>
        </div>
      `;
      
      // Show modal
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      
      // Load grades data using existing function below
      setTimeout(() => loadGradesData(attemptId), 100);
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Assessment Grades</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div id="gradesContent" class="space-y-4">
              <div class="text-center py-4">
                <svg class="animate-spin mx-auto h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-gray-600">Loading grades...</p>
              </div>
            </div>
            <div class="mt-6 flex justify-end">
              <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                Close
              </button>
            </div>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Load grades data
      loadGradesData(attemptId);
    }

    function loadGradesData(attemptId) {
      // Get the submission data we already have
      const submission = window.currentSubmissionData;
      const gradesContent = document.getElementById('gradesContent');
      
      if (submission) {
        let gradeDisplay = '';
        
        if (submission.status === 'graded' && submission.score !== null) {
          gradeDisplay = `
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
              <div class="flex items-center">
                <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                  <h4 class="font-semibold text-green-900">Graded</h4>
                  <p class="text-green-700">Your assessment has been graded</p>
                </div>
              </div>
            </div>
            
            <div class="space-y-3">
              <div class="flex justify-between items-center py-2 border-b">
                <span class="font-medium text-gray-700">Score:</span>
                <span class="text-lg font-bold text-green-600">${submission.score}/${submission.total_points || 100}</span>
              </div>
              <div class="flex justify-between items-center py-2 border-b">
                <span class="font-medium text-gray-700">Percentage:</span>
                <span class="text-lg font-bold text-green-600">${submission.percentage || Math.round((submission.score / (submission.total_points || 100)) * 100)}%</span>
              </div>
              <div class="flex justify-between items-center py-2">
                <span class="font-medium text-gray-700">Status:</span>
                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">Graded</span>
              </div>
            </div>
          `;
          
          if (submission.comments) {
            gradeDisplay += `
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h5 class="font-medium text-blue-900 mb-2">Instructor Comments:</h5>
                <p class="text-blue-800">${submission.comments}</p>
              </div>
            `;
          }
        } else if (submission.status === 'submitted') {
          gradeDisplay = `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <div class="flex items-center">
                <svg class="w-8 h-8 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                  <h4 class="font-semibold text-yellow-900">Pending Review</h4>
                  <p class="text-yellow-700">Your submission is awaiting grading</p>
                </div>
              </div>
            </div>
            
            <div class="space-y-3">
              <div class="flex justify-between items-center py-2 border-b">
                <span class="font-medium text-gray-700">Submitted:</span>
                <span class="text-gray-600">${new Date(submission.submitted_at).toLocaleString()}</span>
              </div>
              <div class="flex justify-between items-center py-2">
                <span class="font-medium text-gray-700">Status:</span>
                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Pending Grade</span>
              </div>
            </div>
          `;
        } else {
          gradeDisplay = `
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <div class="flex items-center">
                <svg class="w-8 h-8 text-gray-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div>
                  <h4 class="font-semibold text-gray-900">Not Submitted</h4>
                  <p class="text-gray-600">Complete your submission to receive a grade</p>
                </div>
              </div>
            </div>
          `;
        }
        
        gradesContent.innerHTML = gradeDisplay;
      } else {
        gradesContent.innerHTML = `
          <div class="text-center py-4">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No Grade Available</h3>
            <p class="mt-1 text-sm text-gray-500">Grade information is not available at this time</p>
          </div>
        `;
      }
    }

    function continueAssessment(attemptId) {
      showNotification('info', 'Continue Assessment', 'Resuming assessment...');
      // TODO: Implement continue assessment functionality
    }

    function closeAssessmentModal() {
      document.getElementById('assessmentModal').classList.add('hidden');
      document.getElementById('assessmentModal').classList.remove('flex');
      window.currentAssessment = null;
    }

    function closeViewSubmissionModal() {
      const modal = document.getElementById('viewSubmissionModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    }

    function closeEditSubmissionModal() {
      const modal = document.getElementById('editSubmissionModal');
      if (modal) {
        modal.remove();
      }
    }

    function closeGradesModal() {
      const modal = document.getElementById('gradesModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    }

    function closeAssignmentSubmissionViewModal() {
      const modal = document.getElementById('assignmentSubmissionViewModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    }

    function displayAssignmentSubmission(submission, assignment) {
      const content = document.getElementById('assignmentSubmissionViewContent');
      
      const submissionDate = submission.submission_date ? new Date(submission.submission_date) : null;
      const isLate = submission.is_late === 1 || submission.is_late === '1';
      const score = submission.score || 'Pending';
      
      content.innerHTML = `
        <div class="space-y-6">
          <!-- Assignment Header -->
          <div class="flex items-start space-x-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex-shrink-0">
              <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-blue-900">${assignment.title}</h3>
              <p class="text-sm text-blue-700 mt-1">${assignment.description || 'Assignment submission details'}</p>
            </div>
          </div>

          <!-- Submission Details Table -->
          <div class="bg-white border rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b">
              <h4 class="font-medium text-gray-900">ASSIGNMENT</h4>
            </div>
            <div class="divide-y divide-gray-200">
              <div class="grid grid-cols-5 gap-4 px-4 py-3 text-sm font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                <div>ASSIGNMENT</div>
                <div>CREATED</div>
                <div>STATUS</div>
                <div>SCORE</div>
                <div>ACTIONS</div>
              </div>
              <div class="grid grid-cols-5 gap-4 px-4 py-4 items-center">
                <div>
                  <div class="font-medium text-gray-900">${assignment.title}</div>
                  <div class="text-sm text-gray-500">Your submission for this assignment</div>
                  <div class="flex items-center mt-1 text-xs text-gray-500">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    ASSIGNMENT-SUBMISSION
                  </div>
                </div>
                <div class="text-sm text-gray-600">
                  ${submissionDate ? submissionDate.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                  }) + ', ' + submissionDate.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                  }) : 'N/A'}
                </div>
                <div>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    ● Submitted
                  </span>
                  ${isLate ? '<div class="mt-1"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Late</span></div>' : ''}
                </div>
                <div class="text-sm font-medium text-gray-900">
                  ${score}
                </div>
                <div class="flex space-x-2">
                  <button onclick="viewAssignmentSubmissionDetails('${submission.id}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View
                  </button>
                  <button onclick="downloadAssignmentSubmission('${submission.id}')" class="text-green-600 hover:text-green-800 text-sm font-medium">
                    Download
                  </button>
                  ${submission.score ? '<button class="text-purple-600 hover:text-purple-800 text-sm font-medium">Grades</button>' : ''}
                </div>
              </div>
            </div>
          </div>

          <!-- Submitted Files Section -->
          ${submission.files && submission.files.length > 0 ? `
            <div class="space-y-3">
              ${submission.files.map(file => `
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                  <div class="flex items-center space-x-3">
                    <svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                    </svg>
                    <div>
                      <div class="font-medium text-gray-900">${file.original_filename}</div>
                      <div class="text-sm text-gray-500">Assignment Document</div>
                    </div>
                  </div>
                  <button onclick="downloadAssignmentSubmissionFile('${file.id}')" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">
                    Download
                  </button>
                </div>
              `).join('')}
            </div>
          ` : `
            <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900">No Files Submitted</h3>
              <p class="mt-1 text-sm text-gray-500">This submission does not include any attached files</p>
            </div>
          `}

          <!-- Actions -->
          <div class="flex justify-end space-x-3 pt-4 border-t">
            <button onclick="closeAssignmentSubmissionViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
              Close
            </button>
          </div>
        </div>
      `;
    }
                      <p class="font-medium text-gray-900">${submission.original_filename || 'submission_file'}</p>
                      <p class="text-sm text-gray-500">
                        ${submission.file_size ? formatFileSize(submission.file_size) : 'Unknown size'}
                        ${submission.mime_type ? ' • ' + submission.mime_type : ''}
                      </p>
                    </div>
                  </div>
                  <div class="flex space-x-2">
                    <button onclick="viewAssignmentSubmissionFile(${submission.submission_file_id}, '${submission.original_filename || 'submission'}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                      View
                    </button>
                    <button onclick="downloadAssignmentSubmissionFile(${submission.submission_file_id})" class="text-green-600 hover:text-green-800 text-sm font-medium">
                      Download
                    </button>
                  </div>
                </div>
              </div>
            ` : `
              <div class="border rounded-lg p-4">
                <h5 class="font-medium text-gray-900 mb-3">Submitted File</h5>
                <p class="text-gray-500 text-center py-4">No file was uploaded with this submission</p>
              </div>
            `}
            
            <!-- Comments -->
            ${submission.comments ? `
              <div class="border rounded-lg p-4">
                <h5 class="font-medium text-gray-900 mb-3">Your Comments</h5>
                <div class="bg-gray-50 rounded-lg p-3">
                  <p class="text-gray-700">${submission.comments}</p>
                </div>
              </div>
            ` : ''}
          </div>
          
          <!-- Actions -->
          <div class="flex justify-end space-x-3 pt-4 border-t">
            <button onclick="closeAssignmentSubmissionViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
              Close
            </button>
          </div>
        </div>
      `;
    }

    function showNoSubmissionMessage(assignment) {
      const content = document.getElementById('assignmentSubmissionViewContent');
      
      content.innerHTML = `
        <div class="text-center py-8">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900">No Submission Found</h3>
          <p class="mt-1 text-sm text-gray-500">There is no submission for this assignment</p>
          <div class="mt-6">
            <button onclick="closeAssignmentSubmissionViewModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
              Close
            </button>
          </div>
        </div>
      `;
    }

    function showSubmissionError(error) {
      const content = document.getElementById('assignmentSubmissionViewContent');
      
      content.innerHTML = `
        <div class="text-center py-8">
          <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900">Error Loading Submission</h3>
          <p class="mt-1 text-sm text-gray-500">${error}</p>
          <div class="mt-6">
            <button onclick="closeAssignmentSubmissionViewModal()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
              Close
            </button>
          </div>
        </div>
      `;
    }

    function viewAssignmentSubmissionFile(fileId, filename) {
      console.log('View assignment submission file clicked! fileId:', fileId, 'filename:', filename);
      
      // Determine if we can view the file inline based on extension
      const extension = filename.split('.').pop().toLowerCase();
      const viewableExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
      
      if (viewableExtensions.includes(extension)) {
        // Open in new tab for viewing
        const viewUrl = `../../api/serve-assignment-submission-file.php?file_id=${fileId}&action=view`;
        window.open(viewUrl, '_blank');
      } else {
        // For non-viewable files, show info and offer download
        showAssignmentFileInfoModal(fileId, filename);
      }
    }

    function downloadAssignmentSubmissionFile(fileId) {
      console.log('Download assignment submission file clicked! fileId:', fileId);
      
      const downloadUrl = `/TPLearn/api/serve-assignment-submission-file.php?file_id=${fileId}&action=download`;
      window.location.href = downloadUrl;
    }

    function viewAssignmentSubmissionDetails(submissionId) {
      console.log('View assignment submission details:', submissionId);
      // This could open a detailed view or do nothing if already detailed
    }

    function downloadAssignmentSubmission(submissionId) {
      console.log('Download assignment submission:', submissionId);
      // This could download a comprehensive submission report
    }
      
      const downloadUrl = `../../api/serve-assignment-submission-file.php?file_id=${fileId}&action=download`;
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = '';
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    function showAssignmentFileInfoModal(fileId, filename) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Assignment Submission File</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div class="flex items-center mb-4">
              <svg class="w-12 h-12 text-blue-500 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <div>
                <h4 class="font-medium text-gray-900">${filename}</h4>
                <p class="text-sm text-gray-500">Your assignment submission</p>
              </div>
            </div>
            <p class="text-sm text-gray-600 mb-6">
              This file cannot be previewed in the browser. Click the button below to download and view it.
            </p>
            <div class="flex justify-end space-x-3">
              <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                Close
              </button>
              <button onclick="downloadAssignmentSubmissionFile(${fileId}); this.closest('.fixed').remove();" class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                Download File
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    function downloadAssessmentFile(assessmentId) {
      const downloadUrl = `../../api/serve-assessment-file.php?assessment_id=${assessmentId}&action=download`;
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = '';
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    function startAssessment() {
      if (!window.currentAssessment) {
        showNotification('error', 'Error', 'No assessment selected');
        return;
      }
      
      // Start the assessment attempt
      fetch('../../api/start-assessment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          assessment_id: window.currentAssessment.id
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Close assessment modal and show submission modal
          closeAssessmentModal();
          showAssessmentSubmissionModal(data);
        } else {
          showNotification('error', 'Error', 'Failed to start assessment: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error starting assessment:', error);
        showNotification('error', 'Error', 'Failed to start assessment. Please try again.');
      });
    }

    function showAssessmentSubmissionModal(attemptData) {
      // Create assessment submission modal
      const modal = document.createElement('div');
      modal.id = 'assessmentSubmissionModal';
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      
      const timeLimit = attemptData.time_limit_end ? new Date(attemptData.time_limit_end).getTime() : null;
      const startTime = new Date(attemptData.started_at).getTime();
      
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">Submit Assessment</h3>
              <p class="text-sm text-gray-600">${attemptData.assessment.title}</p>
            </div>
            ${timeLimit ? `
              <div class="text-right">
                <div class="text-sm text-gray-500">Time Remaining</div>
                <div id="assessmentTimer" class="text-lg font-bold text-red-600">--:--:--</div>
              </div>
            ` : ''}
          </div>

          <div class="p-6">
            <form id="assessmentSubmissionForm" class="space-y-6">
              <input type="hidden" id="attemptId" value="${attemptData.attempt_id}">
              
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h4 class="font-semibold text-blue-900 mb-2">Assessment Instructions</h4>
                <div class="text-blue-800 text-sm">
                  ${attemptData.assessment.instructions || 'Complete the assessment and upload your response file.'}
                </div>
                ${attemptData.assessment.total_points ? `
                  <div class="mt-2 text-blue-700 font-medium">Total Points: ${attemptData.assessment.total_points}</div>
                ` : ''}
              </div>

              ${attemptData.assessment.file_name ? `
                <div class="bg-gray-50 rounded-lg p-4">
                  <h5 class="font-semibold text-gray-900 mb-2">Assessment Document</h5>
                  <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                      </svg>
                    </div>
                    <div class="flex-1">
                      <div class="font-medium text-gray-900">${attemptData.assessment.file_name}</div>
                      <div class="text-sm text-gray-600">Assessment document</div>
                    </div>
                    <button type="button" onclick="downloadAssessmentFile(${attemptData.assessment.id})" class="text-purple-600 hover:text-purple-700 text-sm font-medium">
                      Download
                    </button>
                  </div>
                </div>
              ` : ''}

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Upload Your Response <span class="text-red-500">*</span>
                </label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                  <input type="file" id="assessmentSubmissionFile" name="submission_file" 
                         accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar" 
                         class="hidden" onchange="handleAssessmentFileSelect(this)">
                  <div id="assessmentFileDropZone" onclick="document.getElementById('assessmentSubmissionFile').click()" 
                       class="cursor-pointer">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                      <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-600">
                      <span class="font-medium text-purple-600">Click to upload</span> your assessment response
                    </p>
                    <p class="mt-1 text-xs text-gray-500">PDF, DOC, DOCX, TXT, JPG, PNG, ZIP, RAR up to 10MB</p>
                  </div>
                  
                  <div id="assessmentFilePreview" class="hidden mt-4 p-4 bg-gray-50 rounded border">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-purple-100 rounded flex items-center justify-center">
                          <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                          </svg>
                        </div>
                        <div>
                          <div id="assessmentFileName" class="text-sm font-medium text-gray-900"></div>
                          <div id="assessmentFileSize" class="text-xs text-gray-500"></div>
                        </div>
                      </div>
                      <button type="button" onclick="removeAssessmentFile()" class="text-red-500 hover:text-red-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <label for="assessmentComments" class="block text-sm font-medium text-gray-700 mb-2">
                  Additional Comments (Optional)
                </label>
                <textarea id="assessmentComments" name="comments" rows="4" 
                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                          placeholder="Any additional notes or explanations for your submission..."></textarea>
              </div>

              <div class="flex justify-end space-x-3 pt-6 border-t">
                <button type="button" onclick="closeAssessmentSubmissionModal()" 
                        class="px-4 py-2 text-gray-600 hover:text-gray-800">
                  Cancel
                </button>
                <button type="submit" 
                        class="bg-purple-600 text-white px-6 py-2 rounded hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed">
                  Submit Assessment
                </button>
              </div>
            </form>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Start timer if there's a time limit
      if (timeLimit) {
        const timer = setInterval(() => {
          const now = new Date().getTime();
          const timeLeft = timeLimit - now;
          
          if (timeLeft <= 0) {
            clearInterval(timer);
            // Auto-submit the assessment
            autoSubmitAssessment();
          } else {
            const hours = Math.floor(timeLeft / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            const timerElement = document.getElementById('assessmentTimer');
            if (timerElement) {
              timerElement.textContent = 
                String(hours).padStart(2, '0') + ':' + 
                String(minutes).padStart(2, '0') + ':' + 
                String(seconds).padStart(2, '0');
              
              // Change color when time is running out
              if (timeLeft < 5 * 60 * 1000) { // 5 minutes
                timerElement.className = 'text-lg font-bold text-red-600 animate-pulse';
              } else if (timeLeft < 15 * 60 * 1000) { // 15 minutes  
                timerElement.className = 'text-lg font-bold text-orange-600';
              }
            }
          }
        }, 1000);
        
        // Store timer ID for cleanup
        window.assessmentTimer = timer;
      }
      
      // Handle form submission
      document.getElementById('assessmentSubmissionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitAssessmentAttempt();
      });
    }

    function handleAssessmentFileSelect(input) {
      const file = input.files[0];
      if (file) {
        // Validate file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
          showNotification('error', 'File Too Large', 'File size must be less than 10MB');
          input.value = '';
          return;
        }
        
        // Show file preview
        document.getElementById('assessmentFilePreview').classList.remove('hidden');
        document.getElementById('assessmentFileName').textContent = file.name;
        document.getElementById('assessmentFileSize').textContent = formatFileSize(file.size);
      }
    }

    function removeAssessmentFile() {
      document.getElementById('assessmentSubmissionFile').value = '';
      document.getElementById('assessmentFilePreview').classList.add('hidden');
    }

    function submitAssessmentAttempt() {
      const form = document.getElementById('assessmentSubmissionForm');
      const fileInput = document.getElementById('assessmentSubmissionFile');
      const comments = document.getElementById('assessmentComments').value;
      const attemptId = document.getElementById('attemptId').value;
      
      if (!fileInput.files[0]) {
        showNotification('error', 'File Required', 'Please upload your assessment response file');
        return;
      }
      
      // Show loading state
      const submitButton = form.querySelector('button[type="submit"]');
      const originalText = submitButton.textContent;
      submitButton.disabled = true;
      submitButton.innerHTML = `
        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Submitting...
      `;
      
      // Create FormData for file upload
      const formData = new FormData();
      formData.append('attempt_id', attemptId);
      formData.append('submission_file', fileInput.files[0]);
      formData.append('comments', comments);
      
      // Submit to API
      fetch('../../api/submit-assessment-attempt.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
        
        if (data.success) {
          closeAssessmentSubmissionModal();
          showNotification('success', 'Assessment Submitted', 
            `Assessment submitted successfully! Time taken: ${data.time_taken_formatted}`, 
            () => {
              // Refresh the page to show updated status
              window.location.reload();
            });
        } else {
          showNotification('error', 'Submission Failed', data.message);
        }
      })
      .catch(error => {
        console.error('Error submitting assessment:', error);
        submitButton.disabled = false;
        submitButton.textContent = originalText;
        showNotification('error', 'Submission Failed', 'Failed to submit assessment. Please try again.');
      });
    }

    function autoSubmitAssessment() {
      showNotification('warning', 'Time Expired', 'Assessment time limit reached. Auto-submitting...');
      
      setTimeout(() => {
        const form = document.getElementById('assessmentSubmissionForm');
        if (form) {
          const fileInput = document.getElementById('assessmentSubmissionFile');
          if (fileInput.files[0]) {
            submitAssessmentAttempt();
          } else {
            // Submit without file if no file uploaded
            const formData = new FormData();
            formData.append('attempt_id', document.getElementById('attemptId').value);
            formData.append('comments', 'Auto-submitted due to time limit');
            
            fetch('../../api/submit-assessment-attempt.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              closeAssessmentSubmissionModal();
              if (data.success) {
                showNotification('info', 'Auto-Submitted', 'Assessment was auto-submitted due to time limit.');
              } else {
                showNotification('error', 'Auto-Submit Failed', data.message);
              }
            });
          }
        }
      }, 2000);
    }

    function closeAssessmentSubmissionModal() {
      const modal = document.getElementById('assessmentSubmissionModal');
      if (modal) {
        // Clear timer
        if (window.assessmentTimer) {
          clearInterval(window.assessmentTimer);
          window.assessmentTimer = null;
        }
        
        modal.remove();
      }
    }

    // Header functions
    function openNotifications() {
      showNotification('info', 'Notifications', 'Opening notifications feature...');
    }

    function openMessages() {
      showNotification('info', 'Messages', 'Opening messages feature...');
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
          showNotification('warning', 'File Required', 'Please select a file to submit before proceeding.');
          return;
        }
        
        if (!assignmentId) {
          showNotification('error', 'System Error', 'Assignment ID not found. Please try again.');
          return;
        }

        // Show loading state
        const submitButton = document.querySelector('#submissionForm button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.innerHTML = `
          <svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Submitting...
        `;
        
        // Create FormData for file upload
        const formData = new FormData();
        formData.append('assignment_id', assignmentId);
        formData.append('submission_file', fileInput.files[0]);
        formData.append('comments', comments);
        
        // Submit to API
        fetch('../../api/submit-assignment.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Show success message
            const message = data.is_late ? 
              'Assignment submitted successfully! Note: This submission is late.' : 
              'Assignment submitted successfully!';
            
            showNotification('success', 'Submission Complete', message, () => {
              // Close modal and reset form
              closeSubmissionModal();
              
              // Refresh the page to show updated status
              setTimeout(() => {
                window.location.reload();
              }, 500);
            });
          } else {
            throw new Error(data.error || 'Submission failed');
          }
        })
        .catch(error => {
          console.error('Submission error:', error);
          showNotification('error', 'Submission Failed', 'Failed to submit assignment: ' + error.message);
        })
        .finally(() => {
          // Reset button state
          submitButton.disabled = false;
          submitButton.textContent = originalText;
        });
      });
    });
  </script>
</body>

</html>