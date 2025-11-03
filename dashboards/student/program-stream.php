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
  // Add debug for all materials
  foreach ($materials as $i => $material) {
    error_log("Material $i: ID=" . $material['material_id'] . ", Title=" . $material['title'] . ", Assessment_ID=" . ($material['assessment_id'] ?: 'None'));
  }
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <!-- Jitsi Meet External API -->
  <script src="https://meet.jit.si/external_api.js"></script>
  
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
      <?php 
      require_once '../../includes/student-header-standard.php';
      renderStudentHeader($program_name . ' Stream', 'Explore course materials, assignments, and live sessions');
      ?>

      <!-- Program Header -->
      <div class="bg-white border-b border-gray-200 px-4 lg:px-6 py-6">
        <div class="flex justify-between items-center">
          <div class="flex items-center space-x-4">
            <!-- Back to Programs -->
            <a href="student-academics.php" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
              </svg>
              Back to Programs
            </a>
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
          <button onclick="filterContent('live-sessions')" class="filter-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            Live Sessions
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
                      <button onclick="downloadItem('<?php echo $material['material_id']; ?>')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download
                      </button>
                      <button onclick="viewItem('<?php echo $material['material_id']; ?>', 'document')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View
                      </button>
                      <?php if (!empty($material['assessment_id']) && (!isset($material['is_submitted']) || $material['is_submitted'] != 1)): ?>
                        <button onclick="viewAssessment('<?php echo $material['assessment_id']; ?>')" class="bg-purple-600 text-white hover:bg-purple-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                          </svg>
                          View Assessment
                        </button>
                      <?php endif; ?>
                      <?php if (!empty($material['assessment_id'])): ?>
                        <?php if (isset($material['is_submitted']) && $material['is_submitted'] == 1): ?>
                          <!-- Assessment Submitted State -->
                          <div class="flex items-center space-x-2">
                            <button disabled class="bg-green-100 text-green-800 border border-green-200 text-sm font-medium px-3 py-1 rounded flex items-center cursor-not-allowed">
                              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                              </svg>
                              Assessment Submitted
                            </button>
                            <button onclick="viewAssessmentSubmission('<?php echo $material['assessment_attempt_id']; ?>')" class="bg-purple-600 text-white hover:bg-purple-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                              </svg>
                              View Submission
                            </button>
                            <?php if ($material['assessment_submitted_at']): ?>
                            <div class="text-xs text-gray-600">
                              <div>on <?php echo date('M j, Y', strtotime($material['assessment_submitted_at'])); ?></div>
                              <div class="text-gray-500">at <?php echo date('g:i A', strtotime($material['assessment_submitted_at'])); ?></div>
                              <?php if (isset($material['assessment_score']) && $material['assessment_score'] !== null): ?>
                              <div class="mt-1">
                                <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-medium">
                                  Score: <?php echo $material['assessment_score']; ?><?php if ($material['assessment_total_points']): ?>/<?php echo $material['assessment_total_points']; ?><?php endif; ?>
                                </span>
                                <?php if ($material['assessment_graded_at']): ?>
                                <div class="text-gray-500 mt-1">Graded: <?php echo date('M j, Y', strtotime($material['assessment_graded_at'])); ?></div>
                                <?php endif; ?>
                              </div>
                              <?php endif; ?>
                            </div>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      <?php endif; ?>
                      <?php if ($material['material_type'] === 'assignment'): ?>
                        <?php if (isset($material['is_submitted']) && $material['is_submitted'] == 1): ?>
                          <!-- Already Submitted State -->
                          <div class="flex items-center space-x-2">
                            <!-- View Submission Button -->
                            <button onclick="viewAssignmentSubmission('<?php echo $material['material_id']; ?>')" class="bg-blue-500 text-white hover:bg-blue-600 text-sm font-medium px-3 py-1 rounded flex items-center">
                              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                              </svg>
                              View Submission
                            </button>
                            
                            <button disabled class="bg-green-100 text-green-800 border border-green-200 text-sm font-medium px-3 py-1 rounded flex items-center cursor-not-allowed">
                              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                              </svg>
                              Submitted
                            </button>
                            <?php if ($material['submission_date']): ?>
                            <div class="text-xs text-gray-600">
                              <div>on <?php echo date('M j, Y', strtotime($material['submission_date'])); ?></div>
                              <div class="text-gray-500">at <?php echo date('g:i A', strtotime($material['submission_date'])); ?>
                                <?php if ($material['submission_is_late']): ?>
                                  <span class="text-red-500 font-medium">(Late)</span>
                                <?php endif; ?>
                              </div>
                              <?php if (isset($material['assignment_score']) && $material['assignment_score'] !== null): ?>
                              <div class="mt-1">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">
                                  Score: <?php echo $material['assignment_score']; ?>
                                </span>
                                <?php if ($material['assignment_graded_at']): ?>
                                <div class="text-gray-500 mt-1">Graded: <?php echo date('M j, Y', strtotime($material['assignment_graded_at'])); ?></div>
                                <?php endif; ?>
                              </div>
                              <?php endif; ?>
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
          <div id="noContentMessage" class="bg-white rounded-lg border border-gray-200 p-6 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Content Available</h3>
            <p class="text-gray-600">Your instructor hasn't uploaded any materials yet. Check back later!</p>
          </div>
          <?php endif; ?>

          <!-- Live Sessions Container (for All Content view) -->
          <div id="allContentLiveSessionsContainer">
            <!-- Live sessions will be loaded here for All Content view -->
          </div>

          <!-- Live Sessions Section (for Live Sessions tab only) -->
          <div id="liveSessionsSection" class="content-section hidden">
            <div id="liveSessionsContainer">
              <!-- Live sessions will be loaded here -->
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Assignment Submission Modal -->
  <div id="submissionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
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
            <div class="mt-2 flex justify-between items-center text-sm">
              <span id="submissionDueDate" class="text-purple-600">Due date will appear here</span>
              <span id="submissionTotalPoints" class="text-purple-800 font-medium">Total Points: --</span>
            </div>
          </div>

          <!-- Assignment Instructions -->
          <div class="bg-blue-50 rounded-lg p-4">
            <h5 class="font-semibold text-blue-900 mb-2">Assignment Instructions</h5>
            <div id="assignmentInstructions" class="text-blue-800 text-sm">
              Download the assignment document, complete all required tasks, and submit your work before the deadline.
            </div>
          </div>

          <!-- Assignment File (Instructions) -->
          <div id="assignmentFileSection" class="bg-blue-50 rounded-lg p-4 hidden">
            <h5 class="font-semibold text-blue-900 mb-2">Assignment Instructions File</h5>
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <div>
                  <p id="assignmentFileName" class="text-sm font-medium text-blue-900"></p>
                  <p id="assignmentFileSize" class="text-xs text-blue-600"></p>
                </div>
              </div>
              <div class="flex space-x-2">
                <button id="viewAssignmentFileBtn" type="button" class="bg-blue-600 text-white hover:bg-blue-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                  </svg>
                  View
                </button>
                <button id="downloadAssignmentFileBtn" type="button" class="bg-green-600 text-white hover:bg-green-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  Download
                </button>
              </div>
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
  <div id="notificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-[9999]">
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
  <div id="assessmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
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

  <!-- Assignment Submission View Modal -->
  <div id="assignmentViewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
      <div class="flex justify-between items-center p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Assignment Submission</h3>
        <button onclick="closeAssignmentViewModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      
      <div class="p-6 overflow-y-auto max-h-[75vh]">
        <div id="assignmentViewContent">
          <!-- Content will be loaded here -->
          <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
            <p class="mt-2 text-gray-600">Loading submission...</p>
          </div>
        </div>
      </div>
      
      <div class="flex justify-end space-x-3 p-6 border-t border-gray-200">
        <button onclick="closeAssignmentViewModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- Assessment Submission View Modal -->
  <div id="assessmentViewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
      <div class="flex justify-between items-center p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Assessment Submission</h3>
        <button onclick="closeAssessmentViewModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      
      <div class="p-6 overflow-y-auto max-h-[75vh]">
        <div id="assessmentViewContent">
          <!-- Content will be loaded here -->
          <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
            <p class="mt-2 text-gray-600">Loading submission...</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
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

      // Handle sections and items
      const sections = document.querySelectorAll('.content-section');
      
      // Hide all sections first
      sections.forEach(section => {
        section.classList.add('hidden');
      });
      
      const allContentLiveSessions = document.getElementById('allContentLiveSessionsContainer');
      
      if (type === 'all') {
        // Show all items and show live sessions in all content
        items.forEach(item => {
          item.style.display = 'block';
        });
        if (allContentLiveSessions) {
          allContentLiveSessions.style.display = 'block';
        }
      } else if (type === 'live-sessions') {
        // Show live sessions section and hide material items
        const liveSessionsSection = document.getElementById('liveSessionsSection');
        if (liveSessionsSection) {
          liveSessionsSection.classList.remove('hidden');
          loadLiveSessions(); // Load live sessions when tab is clicked
        }
        if (allContentLiveSessions) {
          allContentLiveSessions.style.display = 'none';
        }
        items.forEach(item => {
          item.style.display = 'none';
        });
      } else {
        // Show filtered items and hide live sessions
        if (allContentLiveSessions) {
          allContentLiveSessions.style.display = 'none';
        }
        items.forEach(item => {
          if (item.dataset.type === type) {
            item.style.display = 'block';
          } else {
            item.style.display = 'none';
          }
        });
      }
    }

    // View item details
    function viewItem(materialId, type) {
      // Fetch material details
      fetch(`../../api/get-program-material.php?material_id=${materialId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const material = data.material;
            const fileUrl = `../../api/serve-material-file.php?material_id=${materialId}&action=view`;
            
            // Determine how to display based on file type
            const mimeType = material.file.mime_type.toLowerCase();
            const fileName = material.file.original_filename || material.file.filename;
            
            console.log('File details:', {
              name: fileName,
              mimeType: mimeType,
              size: material.file.file_size_formatted
            });
            
            if (mimeType.startsWith('image/')) {
              // For images, show in a modal
              showImageModal(material, fileUrl);
            } else if (mimeType === 'application/pdf') {
              // For PDFs, open in new tab
              window.open(fileUrl, '_blank');
            } else if (mimeType.startsWith('text/') || mimeType === 'text/plain') {
              // For text files, open in new tab
              window.open(fileUrl, '_blank');
            } else if (mimeType.includes('document') || 
                       mimeType.includes('word') ||
                       mimeType.includes('officedocument') ||
                       mimeType.includes('presentation') ||
                       mimeType.includes('spreadsheet') ||
                       fileName.match(/\.(doc|docx|xls|xlsx|ppt|pptx)$/i)) {
              // For office documents, show notification and trigger download
              showNotification('info', 'Opening Document', 
                `${fileName} will be downloaded to your device since it cannot be viewed directly in the browser.`);
              
              // Trigger download after a short delay
              setTimeout(() => {
                window.location.href = fileUrl;
              }, 1500);
            } else {
              // For other types, try to open but warn user
              const proceed = confirm(`This file type (${mimeType}) may not display properly in your browser. Do you want to try opening it anyway?`);
              if (proceed) {
                window.open(fileUrl, '_blank');
              }
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
    function downloadItem(materialId) {
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
      const downloadUrl = `../../api/serve-material-file.php?material_id=${materialId}&action=download`;
      
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
    function submitAssignment(assignmentId) {
      // First fetch assignment details to populate the modal
      fetch(`../../api/get-program-material.php?material_id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
          console.log('Assignment API Response:', data); // Debug log
          if (data.success && data.material) {
            // Populate assignment details in modal
            document.getElementById('submissionAssignmentTitle').textContent = data.material.title;
            document.getElementById('submissionAssignmentDescription').textContent = data.material.description || 'No description provided';
            
            // Set total points if assignment data is available
            if (data.material.assignment && data.material.assignment.max_score) {
              document.getElementById('submissionTotalPoints').textContent = `Total Points: ${data.material.assignment.max_score}`;
            } else {
              document.getElementById('submissionTotalPoints').textContent = 'Total Points: 100'; // Default fallback
            }
            
            // Set assignment instructions
            const instructionsText = (data.material.assignment && data.material.assignment.instructions) 
              ? data.material.assignment.instructions 
              : 'Download the assignment document, complete all required tasks, and submit your work before the deadline.';
            document.getElementById('assignmentInstructions').textContent = instructionsText;
            
            // Show assignment file information if available
            if (data.material.file && data.material.file.id) {
              const fileSection = document.getElementById('assignmentFileSection');
              const fileName = document.getElementById('assignmentFileName');
              const fileSize = document.getElementById('assignmentFileSize');
              const viewBtn = document.getElementById('viewAssignmentFileBtn');
              const downloadBtn = document.getElementById('downloadAssignmentFileBtn');
              
              fileName.textContent = data.material.file.original_filename || 'Assignment File';
              fileSize.textContent = data.material.file.file_size_formatted || '';
              
              // Set up view button - opens file in new tab or downloads based on file type
              viewBtn.onclick = function() {
                // Add visual feedback
                const originalText = viewBtn.innerHTML;
                viewBtn.disabled = true;
                viewBtn.innerHTML = `
                  <svg class="w-4 h-4 mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                  </svg>
                  Opening...
                `;
                
                try {
                  const fileName = data.material.file.original_filename || 'Assignment File';
                  const mimeType = data.material.file.mime_type || '';
                  
                  // Check if file can be viewed inline
                  if (mimeType === 'application/pdf' || mimeType.startsWith('image/') || mimeType.startsWith('text/')) {
                    // Open viewable files in new tab
                    const newWindow = window.open(`../../api/serve-material-file.php?material_id=${assignmentId}&action=view`, '_blank');
                    
                    // Check if popup was blocked
                    if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                      throw new Error('Popup blocked. Please allow popups for this site.');
                    }
                  } else {
                    // For non-viewable files (DOC, DOCX, etc.), show notification and download
                    showNotification('info', 'Downloading Assignment', 
                      `${fileName} will be downloaded since it cannot be viewed directly in the browser.`);
                    
                    // Trigger download
                    setTimeout(() => {
                      window.location.href = `../../api/serve-material-file.php?material_id=${assignmentId}&action=view`;
                    }, 1000);
                  }
                } catch (error) {
                  console.error('Error opening file:', error);
                  showNotification('error', 'View Failed', 'Unable to open file. ' + error.message);
                }
                
                // Reset button after a short delay
                setTimeout(() => {
                  viewBtn.disabled = false;
                  viewBtn.innerHTML = originalText;
                }, 1000);
              };
              
              // Set up download button - downloads the file
              downloadBtn.onclick = function() {
                // Add visual feedback
                const originalText = downloadBtn.innerHTML;
                downloadBtn.disabled = true;
                downloadBtn.innerHTML = `
                  <svg class="w-4 h-4 mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                  </svg>
                  Downloading...
                `;
                
                try {
                  // Start download
                  window.location.href = `../../api/serve-material-file.php?material_id=${assignmentId}&action=download`;
                } catch (error) {
                  console.error('Error downloading file:', error);
                  showNotification('error', 'Download Failed', 'Unable to download file. ' + error.message);
                }
                
                // Reset button after a short delay
                setTimeout(() => {
                  downloadBtn.disabled = false;
                  downloadBtn.innerHTML = originalText;
                }, 2000);
              };
              
              fileSection.classList.remove('hidden');
            } else {
              // Hide file section if no file
              document.getElementById('assignmentFileSection').classList.add('hidden');
            }
            
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
          // Hide file section on error
          document.getElementById('assignmentFileSection').classList.add('hidden');
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
      
      // Hide assignment file section
      document.getElementById('assignmentFileSection').classList.add('hidden');
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
    function viewAssessment(assessmentId) {
      console.log('Opening assessment modal for ID:', assessmentId);
      
      // Show modal
      document.getElementById('assessmentModal').classList.remove('hidden');
      document.getElementById('assessmentModal').classList.add('flex');
      
      // Load assessment details
      fetch(`../../api/get-assessment.php?assessment_id=${assessmentId}`)
        .then(response => {
          console.log('Assessment API response status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then(data => {
          console.log('Assessment API response data:', data);
          if (data.success) {
            displayAssessmentDetails(data.assessment);
          } else {
            console.error('Assessment API returned error:', data);
            showNotification('error', 'Error', 'Failed to load assessment: ' + (data.error || data.message || 'Unknown error'));
            closeAssessmentModal();
          }
        })
        .catch(error => {
          console.error('Error loading assessment:', error);
          showNotification('error', 'Error', 'Failed to load assessment details: ' + error.message);
          closeAssessmentModal();
        });
    }

    function displayAssessmentDetails(assessment) {
      console.log('Displaying assessment details:', assessment);
      const content = document.getElementById('assessmentContent');
      const actions = document.getElementById('assessmentActions');
      
      // Format due date properly
      let dueDateDisplay = '';
      let isOverdue = false;
      if (assessment.due_date && assessment.due_date !== '0000-00-00 00:00:00' && assessment.due_date !== null) {
        try {
          const dueDate = new Date(assessment.due_date);
          const now = new Date();
          isOverdue = now > dueDate;
          
          const color = isOverdue ? 'red' : 'orange';
          const status = isOverdue ? 'OVERDUE' : 'Due';
          
          dueDateDisplay = `
            <div class="mt-2 flex items-center text-sm">
              <svg class="w-4 h-4 text-${color}-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span class="text-${color}-600 font-medium">${status}: ${dueDate.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              })}</span>
            </div>
          `;
          
          if (isOverdue) {
            dueDateDisplay += `
              <div class="mt-2 p-3 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                  <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.888-.833-2.598 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                  </svg>
                  <span class="text-red-800 font-medium text-sm">Late Submission Warning</span>
                </div>
                <p class="text-red-700 text-sm mt-1">This assessment is past its due date. Your submission will be marked as a LATE SUBMISSION.</p>
              </div>
            `;
          }
        } catch (e) {
          console.error('Error formatting due date:', e);
          dueDateDisplay = `
            <div class="mt-2 flex items-center text-sm">
              <svg class="w-4 h-4 text-gray-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span class="text-gray-600 font-medium">No due date set</span>
            </div>
          `;
        }
      } else {
        // No due date set
        dueDateDisplay = `
          <div class="mt-2 flex items-center text-sm">
            <svg class="w-4 h-4 text-gray-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-gray-600 font-medium">No due date set</span>
          </div>
        `;
      }
      
      // Build assessment content
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
              <h4 class="text-lg font-semibold text-gray-900">${assessment.title || 'Assessment'}</h4>
              <p class="text-sm text-gray-600 mt-1">${assessment.description || 'Assessment description will appear here'}</p>
              ${dueDateDisplay}
              ${assessment.total_points ? `
                <div class="mt-1 text-sm text-gray-600">
                  <span class="font-medium">Total Points:</span> ${assessment.total_points}
                </div>
              ` : ''}
            </div>
          </div>
          
          <!-- Assessment Information Panel -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <div>
                <h5 class="font-medium text-blue-900 mb-1">Assessment Instructions</h5>
                <p class="text-sm text-blue-800">${assessment.instructions || 'Complete the assessment by uploading your submission file before the due date.'}</p>
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
              <div class="flex space-x-2">
                <button onclick="viewAssessmentFile(${assessment.id})" class="bg-green-600 text-white hover:bg-green-700 px-4 py-2 rounded-lg text-sm font-medium">
                  View
                </button>
                <button onclick="downloadAssessmentFile(${assessment.id})" class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg text-sm font-medium">
                  Download
                </button>
              </div>
            </div>
          </div>
          ` : ''}
        </div>
      `;
      
      // Store assessment data for later use
      window.currentAssessment = assessment;
      
      // Show actions
      actions.classList.remove('hidden');
    }

    function viewAssessmentResults(attemptId) {
      showNotification('info', 'Assessment Results', 'Loading assessment results...');
      // TODO: Implement assessment results viewing
    }

    function downloadSubmissionFile(fileId) {
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

    function continueAssessment(attemptId) {
      showNotification('info', 'Continue Assessment', 'Resuming assessment...');
      // TODO: Implement continue assessment functionality
    }

    function editSubmission(attemptId, fileId) {
      // Show edit submission modal with current file and allow replacement
      showEditSubmissionModal(attemptId, fileId);
    }

    function viewGrades(attemptId) {
      // Show grades and feedback for the submission
      showGradesModal(attemptId);
    }

    function showEditSubmissionModal(attemptId, currentFileId) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.id = 'editSubmissionModal';
      
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Edit Submission</h3>
            <button onclick="closeEditSubmissionModal()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <form id="editSubmissionForm" class="space-y-6">
              <input type="hidden" id="editAttemptId" value="${attemptId}">
              
              <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex items-center">
                  <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  <p class="text-sm text-blue-800">You can replace your current submission with a new file. The previous file will be overwritten.</p>
                </div>
              </div>

              <!-- Current File Info -->
              <div class="border rounded-lg p-4 bg-gray-50">
                <h4 class="font-medium text-gray-900 mb-2">Current Submission</h4>
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="text-sm text-gray-700" id="currentFileName">Loading...</span>
                  </div>
                  <button type="button" onclick="viewSubmissionFile(${currentFileId}, 'current')" class="text-blue-600 hover:text-blue-800 text-sm">
                    View Current
                  </button>
                </div>
              </div>

              <!-- New File Upload -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Upload New File (Replace Current)
                </label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
                  <input type="file" id="newSubmissionFile" class="hidden" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar" onchange="handleNewFileSelect(this)">
                  <label for="newSubmissionFile" class="cursor-pointer">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                      <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="text-gray-600">Click to select a new file</span>
                    <p class="text-xs text-gray-500 mt-1">PDF, DOC, DOCX, TXT, JPG, PNG, ZIP, RAR (Max 10MB)</p>
                  </label>
                </div>
                <div id="newFilePreview" class="mt-3 hidden">
                  <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                      <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                      </svg>
                      <span class="text-sm text-green-800" id="newFileName"></span>
                      <span class="text-xs text-green-600 ml-2" id="newFileSize"></span>
                    </div>
                    <button type="button" onclick="removeNewFile()" class="text-green-600 hover:text-green-800">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <textarea id="editComments" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Add any comments about your updated submission..."></textarea>
              </div>

              <!-- Submit Button -->
              <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditSubmissionModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                  Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                  Update Submission
                </button>
              </div>
            </form>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Set up form submission
      document.getElementById('editSubmissionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        updateSubmission();
      });
    }

    function showGradesModal(attemptId) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.id = 'gradesModal';
      
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Assessment Grades & Feedback</h3>
            <button onclick="closeGradesModal()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div id="gradesContent">
              <div class="text-center py-8">
                <svg class="animate-spin mx-auto h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-gray-500">Loading grades...</p>
              </div>
            </div>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Load grades data
      loadGradesData(attemptId);
    }

    function handleNewFileSelect(input) {
      const file = input.files[0];
      if (file) {
        // Validate file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
          showNotification('error', 'File Too Large', 'Please select a file smaller than 10MB');
          input.value = '';
          return;
        }
        
        // Show file preview
        document.getElementById('newFilePreview').classList.remove('hidden');
        document.getElementById('newFileName').textContent = file.name;
        document.getElementById('newFileSize').textContent = formatFileSize(file.size);
      }
    }

    function removeNewFile() {
      document.getElementById('newSubmissionFile').value = '';
      document.getElementById('newFilePreview').classList.add('hidden');
    }

    function updateSubmission() {
      const attemptId = document.getElementById('editAttemptId').value;
      const fileInput = document.getElementById('newSubmissionFile');
      const comments = document.getElementById('editComments').value;
      
      if (!fileInput.files[0]) {
        showNotification('error', 'File Required', 'Please select a new file to update your submission');
        return;
      }
      
      const formData = new FormData();
      formData.append('attempt_id', attemptId);
      formData.append('submission_file', fileInput.files[0]);
      formData.append('comments', comments);
      formData.append('action', 'update');
      
      // Show loading state
      const submitButton = document.querySelector('#editSubmissionForm button[type="submit"]');
      const originalText = submitButton.textContent;
      submitButton.disabled = true;
      submitButton.innerHTML = `
        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Updating...
      `;
      
      // Submit to API
      fetch('../../api/update-assessment-submission.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
        
        if (data.success) {
          showNotification('success', 'Submission Updated', 'Your submission has been updated successfully');
          closeEditSubmissionModal();
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

    function loadGradesData(attemptId) {
      fetch(`../../api/get-assessment-grades.php?attempt_id=${attemptId}`)
        .then(response => response.json())
        .then(data => {
          const content = document.getElementById('gradesContent');
          
          if (data.success && data.grades) {
            const grades = data.grades;
            content.innerHTML = `
              <div class="space-y-6">
                <!-- Score Summary -->
                <div class="bg-blue-50 rounded-lg p-6 text-center">
                  <h4 class="text-lg font-semibold text-blue-900 mb-2">Your Score</h4>
                  <div class="text-3xl font-bold text-blue-700">
                    ${grades.score !== null ? grades.score + '%' : 'Not graded yet'}
                  </div>
                  ${grades.percentage !== null ? `
                    <p class="text-sm text-blue-600 mt-1">${grades.percentage}%</p>
                  ` : ''}
                </div>

                <!-- Status -->
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                  <span class="font-medium text-gray-700">Status:</span>
                  <span class="px-3 py-1 rounded-full text-sm font-medium ${
                    grades.status === 'graded' ? 'bg-green-100 text-green-800' :
                    grades.status === 'submitted' ? 'bg-blue-100 text-blue-800' :
                    'bg-yellow-100 text-yellow-800'
                  }">
                    ${grades.status.charAt(0).toUpperCase() + grades.status.slice(1)}
                  </span>
                </div>

                <!-- Submission Info -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="p-4 border border-gray-200 rounded-lg">
                    <h5 class="font-medium text-gray-700 mb-1">Submitted</h5>
                    <p class="text-sm text-gray-600">${grades.submitted_at ? new Date(grades.submitted_at).toLocaleString() : 'Not submitted'}</p>
                  </div>
                  <div class="p-4 border border-gray-200 rounded-lg">
                    <h5 class="font-medium text-gray-700 mb-1">Time Taken</h5>
                    <p class="text-sm text-gray-600">${grades.time_taken ? Math.round(grades.time_taken / 60) + ' minutes' : 'N/A'}</p>
                  </div>
                </div>

                <!-- Feedback -->
                ${grades.comments ? `
                  <div class="border border-gray-200 rounded-lg p-4">
                    <h5 class="font-medium text-gray-700 mb-2">Instructor Feedback</h5>
                    <div class="bg-gray-50 rounded p-3">
                      <p class="text-sm text-gray-700">${grades.comments}</p>
                    </div>
                  </div>
                ` : `
                  <div class="text-center py-4 text-gray-500">
                    <p>No feedback available yet</p>
                  </div>
                `}
              </div>
            `;
          } else {
            content.innerHTML = `
              <div class="text-center py-8 text-gray-500">
                <p>No grades available yet</p>
                <p class="text-sm mt-1">Your instructor hasn't graded this submission</p>
              </div>
            `;
          }
        })
        .catch(error => {
          console.error('Error loading grades:', error);
          document.getElementById('gradesContent').innerHTML = `
            <div class="text-center py-8 text-red-500">
              <p>Error loading grades</p>
            </div>
          `;
        });
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
        modal.remove();
      }
    }

    function closeAssessmentModal() {
      document.getElementById('assessmentModal').classList.add('hidden');
      document.getElementById('assessmentModal').classList.remove('flex');
      window.currentAssessment = null;
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

    function viewAssessmentFile(assessmentId) {
      // For Word documents and other non-browser-viewable files, 
      // we'll redirect to a page that offers download or external app options
      if (!window.currentAssessment) {
        // If we don't have assessment data, fetch it first
        fetch(`../../api/get-assessment.php?assessment_id=${assessmentId}`)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              openAssessmentFile(data.assessment);
            } else {
              showNotification('error', 'Error', 'Could not load assessment details');
            }
          })
          .catch(error => {
            console.error('Error loading assessment:', error);
            showNotification('error', 'Error', 'Could not load assessment details');
          });
      } else {
        openAssessmentFile(window.currentAssessment);
      }
    }

    function openAssessmentFile(assessment) {
      const fileName = assessment.file_name || '';
      const fileExt = fileName.split('.').pop().toLowerCase();
      
      // Check if it's a file type that browsers can display inline
      const viewableTypes = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
      
      if (viewableTypes.includes(fileExt)) {
        // For viewable files, open in new tab
        const viewUrl = `../../api/serve-assessment-file.php?assessment_id=${assessment.id}&action=view`;
        window.open(viewUrl, '_blank');
      } else {
        // For non-viewable files (like .docx), show a modal with options
        showFileViewModal(assessment);
      }
    }

    function showFileViewModal(assessment) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">View Assessment Document</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <div class="mb-6">
            <div class="flex items-center mb-3">
              <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
              </svg>
              <div>
                <h4 class="font-medium text-gray-900">${assessment.file_name}</h4>
                <p class="text-sm text-gray-600">Word Document</p>
              </div>
            </div>
            <p class="text-sm text-gray-600 mb-4">
              This is a Word document that cannot be displayed directly in the browser. 
              You can download it to view with Microsoft Word or a compatible application.
            </p>
          </div>
          
          <div class="flex space-x-3">
            <button onclick="this.closest('.fixed').remove()" 
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
              Cancel
            </button>
            <button onclick="downloadAssessmentFile(${assessment.id}); this.closest('.fixed').remove();" 
                    class="flex-1 px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
              Download File
            </button>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
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
          closeAssessmentModal();
          showNotification('error', 'Error', 'Failed to start assessment: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error starting assessment:', error);
        closeAssessmentModal();
        showNotification('error', 'Error', 'Failed to start assessment. Please try again.');
      });
    }

    function showAssessmentSubmissionModal(attemptData) {
      // Create assessment submission modal
      const modal = document.createElement('div');
      modal.id = 'assessmentSubmissionModal';
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Submit Assessment</h3>
            <button onclick="closeAssessmentSubmissionModal()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <div class="p-6">
            <form id="assessmentSubmissionForm" class="space-y-6">
              <input type="hidden" id="attemptId" value="${attemptData.attempt_id}">
              
              <!-- Assessment Info -->
              <div class="bg-purple-50 rounded-lg p-4">
                <h4 class="font-semibold text-purple-900 mb-2">${attemptData.assessment.title}</h4>
                <p class="text-sm text-purple-700">${attemptData.assessment.description || 'Complete the assessment by following the instructions and uploading your submission.'}</p>
                <div class="mt-2 flex justify-between items-center text-sm">
                  <span id="assessmentDueDate" class="text-purple-600">
                    ${attemptData.assessment.due_date ? (() => {
                      const dueDate = new Date(attemptData.assessment.due_date);
                      return isNaN(dueDate.getTime()) ? 'No due date set' : `Due: ${dueDate.toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                      })}`;
                    })() : 'No due date set'}
                  </span>
                  <span class="text-purple-800 font-medium">Total Points: ${attemptData.assessment.total_points || 100}</span>
                </div>
              </div>

              <!-- Assessment Instructions -->
              <div class="bg-blue-50 rounded-lg p-4">
                <h5 class="font-semibold text-blue-900 mb-2">Assessment Instructions</h5>
                <div class="text-blue-800 text-sm">
                  ${attemptData.assessment.instructions || 'Download the assessment document, complete all required tasks, and submit your work before the deadline.'}
                </div>
              </div>

              ${attemptData.assessment.file_name ? `
                <!-- Assessment Document -->
                <div class="bg-blue-50 rounded-lg p-4">
                  <h5 class="font-semibold text-blue-900 mb-2">Assessment Document</h5>
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                      <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                      </svg>
                      <div>
                        <p class="text-sm font-medium text-blue-900">${attemptData.assessment.file_name}</p>
                        <p class="text-xs text-blue-600">Assessment document</p>
                      </div>
                    </div>
                    <div class="flex space-x-2">
                      <button type="button" onclick="viewAssessmentFile(${attemptData.assessment.id})" class="bg-blue-600 text-white hover:bg-blue-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View
                      </button>
                      <button type="button" onclick="downloadAssessmentFile(${attemptData.assessment.id})" class="bg-green-600 text-white hover:bg-green-700 text-sm font-medium px-3 py-1 rounded flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                        </svg>
                        Download
                      </button>
                    </div>
                  </div>
                </div>
              ` : ''}

              <!-- Upload Your Response -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Upload Your Response <span class="text-red-500">*</span></label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-purple-400 transition-colors">
                  <label for="assessmentSubmissionFile" class="cursor-pointer">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <span class="mt-2 block text-sm font-medium text-purple-600">
                      Click to upload your assessment response
                    </span>
                    <span class="mt-1 block text-sm text-gray-500">
                      PDF, DOC, DOCX, TXT, JPG, PNG, ZIP, RAR files up to 10MB
                    </span>
                    <input id="assessmentSubmissionFile" type="file" class="sr-only" 
                           accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar" 
                           onchange="handleAssessmentFileSelect(this)">
                  </label>
                </div>
                
                <!-- File Preview -->
                <div id="assessmentFilePreview" class="hidden mt-3 p-3 bg-gray-50 rounded-lg">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center">
                      <svg class="w-8 h-8 text-purple-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                      </svg>
                      <div>
                        <p id="assessmentFileName" class="text-sm font-medium text-gray-900"></p>
                        <p id="assessmentFileSize" class="text-xs text-gray-500"></p>
                      </div>
                    </div>
                    <button type="button" onclick="removeAssessmentFile()" class="text-red-500 hover:text-red-700 ml-2">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Additional Comments -->
              <div>
                <label for="assessmentComments" class="block text-sm font-medium text-gray-700 mb-2">
                  Additional Comments (Optional)
                </label>
                <textarea id="assessmentComments" name="comments" rows="4" 
                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none"
                          placeholder="Any additional notes or explanations for your submission..."></textarea>
              </div>

              <!-- Submit Buttons -->
              <div class="flex justify-end space-x-3 pt-4">
                <button type="button" onclick="closeAssessmentSubmissionModal()" 
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium">
                  Cancel
                </button>
                <button type="submit" 
                        class="bg-purple-600 text-white px-8 py-2 rounded-lg hover:bg-purple-700 font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                  </svg>
                  Submit Assessment
                </button>
              </div>
            </form>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
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
      console.log('Starting assessment submission...');
      
      const form = document.getElementById('assessmentSubmissionForm');
      const fileInput = document.getElementById('assessmentSubmissionFile');
      const commentsElement = document.getElementById('assessmentComments');
      const attemptIdElement = document.getElementById('attemptId');
      
      // Debug logging
      console.log('Form elements found:', {
        form: !!form,
        fileInput: !!fileInput,
        commentsElement: !!commentsElement,
        attemptIdElement: !!attemptIdElement
      });
      
      if (!fileInput) {
        showNotification('error', 'Error', 'File input not found');
        return;
      }
      
      if (!attemptIdElement) {
        showNotification('error', 'Error', 'Attempt ID not found');
        return;
      }
      
      const comments = commentsElement ? commentsElement.value : '';
      const attemptId = attemptIdElement.value;
      
      console.log('Form data:', {
        attemptId: attemptId,
        hasFile: !!fileInput.files[0],
        fileName: fileInput.files[0]?.name,
        fileSize: fileInput.files[0]?.size,
        comments: comments
      });
      
      if (!fileInput.files[0]) {
        showNotification('error', 'File Required', 'Please upload your assessment response file');
        return;
      }
      
      if (!attemptId) {
        showNotification('error', 'Error', 'No attempt ID found. Please try starting the assessment again.');
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
      .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Get the response text to debug what we're actually receiving
        return response.text().then(text => {
          console.log('Raw response text:', text);
          
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}\nResponse: ${text.substring(0, 200)}`);
          }
          
          // Try to parse as JSON
          try {
            return JSON.parse(text);
          } catch (e) {
            throw new Error(`Invalid JSON response: ${text.substring(0, 200)}...`);
          }
        });
      })
      .then(data => {
        console.log('Submission response:', data);
        submitButton.disabled = false;
        submitButton.textContent = originalText;
        
        if (data.success) {
          closeAssessmentSubmissionModal();
          const notificationType = data.is_late ? 'warning' : 'success';
          const title = data.is_late ? 'Late Submission Accepted' : 'Assessment Submitted';
          const message = data.is_late ? 
            `Assessment submitted as LATE SUBMISSION! Time taken: ${data.time_taken_formatted}` :
            `Assessment submitted successfully! Time taken: ${data.time_taken_formatted}`;
          
          showNotification(notificationType, title, message, 
            () => {
              // Refresh the page to show updated status
              window.location.reload();
            });
        } else {
          console.error('Submission failed:', data);
          showNotification('error', 'Submission Failed', data.message || 'Unknown error occurred');
        }
      })
      .catch(error => {
        console.error('Error submitting assessment:', error);
        submitButton.disabled = false;
        submitButton.textContent = originalText;
        showNotification('error', 'Submission Failed', 'Failed to submit assessment: ' + error.message);
      });
    }



    function closeAssessmentSubmissionModal() {
      const modal = document.getElementById('assessmentSubmissionModal');
      if (modal) {
        modal.remove();
      }
    }

    // Header functions
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

      // Load Live Sessions for All Content view
      loadLiveSessionsForAllContent();

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

    // Assignment View Modal Functions
    function viewAssignmentSubmission(materialId) {
      console.log('viewAssignmentSubmission called with materialId:', materialId);
      
      // First, test if we have a session by calling a simple endpoint
      fetch('/TPLearn/session_debug.php')
        .then(response => response.text())
        .then(sessionText => {
          console.log('Session check response:', sessionText);
          
          // Now fetch assignment submission details
          fetch(`/TPLearn/api/get-assignment-submission.php?material_id=${materialId}`)
            .then(response => {
              console.log('API Response status:', response.status);
              console.log('API Response headers:', response.headers);
              return response.text();
            })
            .then(text => {
              console.log('API Raw response:', text);
              try {
                const data = JSON.parse(text);
                console.log('API Parsed data:', data);
                
                if (data.success && data.has_submission) {
                  showAssignmentViewModal(data.submission);
                } else {
                  console.error('API Error:', data);
                  showNotification('error', 'Error', data.message || 'No submission found for this assignment.');
                }
              } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Raw text was:', text);
                showNotification('error', 'Error', 'Failed to parse response from server. Check console for details.');
              }
            })
            .catch(error => {
              console.error('Fetch Error:', error);
              showNotification('error', 'Error', 'Failed to load submission details: ' + error.message);
            });
        })
        .catch(error => {
          console.error('Session check failed:', error);
          showNotification('error', 'Error', 'Session check failed: ' + error.message);
        });
    }

    function showAssignmentViewModal(submission) {
      const modal = document.getElementById('assignmentViewModal');
      const content = document.getElementById('assignmentViewContent');
      
      // Build the modal content
      let html = `
        <div class="space-y-6">
          <!-- Assignment Info -->
          <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <h4 class="font-semibold text-purple-900 mb-2">${submission.assignment_title}</h4>
            <p class="text-sm text-purple-700">${submission.assignment_description || 'No description available.'}</p>
            ${submission.due_date ? `<p class="text-sm text-purple-600 mt-2"><strong>Due:</strong> ${new Date(submission.due_date).toLocaleString()}</p>` : ''}
          </div>
          
          <!-- Submission Details -->
          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <h5 class="font-semibold text-gray-900 mb-3">Submission Details</h5>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="font-medium text-gray-700">Status:</span>
                <span class="ml-2 px-2 py-1 rounded text-xs ${submission.status === 'submitted' ? 'bg-green-100 text-green-800' : 
                  submission.status === 'graded' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}">${submission.status.charAt(0).toUpperCase() + submission.status.slice(1)}</span>
              </div>
              <div>
                <span class="font-medium text-gray-700">Submitted:</span>
                <span class="ml-2 text-gray-600">${new Date(submission.submission_date).toLocaleString()}</span>
              </div>
              ${submission.score !== null ? `
              <div>
                <span class="font-medium text-gray-700">Score:</span>
                <span class="ml-2 text-gray-600">${submission.score}</span>
              </div>
              ` : ''}
              ${submission.graded_at ? `
              <div>
                <span class="font-medium text-gray-700">Graded:</span>
                <span class="ml-2 text-gray-600">${new Date(submission.graded_at).toLocaleString()}</span>
              </div>
              ` : ''}
            </div>
          </div>
          
          <!-- Submission Content -->
          <div>
            <h5 class="font-semibold text-gray-900 mb-3">Submission Content</h5>
            ${submission.submission_text ? `
              <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                <h6 class="font-medium text-gray-700 mb-2">Text Submission:</h6>
                <div class="text-gray-600 whitespace-pre-wrap">${submission.submission_text}</div>
              </div>
            ` : ''}
            
            ${submission.file ? `
              <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h6 class="font-medium text-gray-700 mb-3">Submitted File:</h6>
                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded border">
                  <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  <div class="flex-1">
                    <p class="font-medium text-gray-900">${submission.file.original_filename}</p>
                    <p class="text-sm text-gray-500">${(submission.file.file_size / 1024 / 1024).toFixed(2)} MB</p>
                  </div>
                  <div class="flex space-x-2">
                    <a href="/TPLearn/api/serve-assignment-submission-file.php?file_id=${submission.file.id}" target="_blank" 
                       class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                      View
                    </a>
                    <a href="/TPLearn/api/serve-assignment-submission-file.php?file_id=${submission.file.id}&download=1" 
                       class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                      Download
                    </a>
                  </div>
                </div>
              </div>
            ` : '<p class="text-gray-500 italic">No file submitted.</p>'}
          </div>
          
          <!-- Feedback Section -->
          ${submission.feedback ? `
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h5 class="font-semibold text-blue-900 mb-2">Feedback</h5>
            <div class="text-blue-800 whitespace-pre-wrap">${submission.feedback}</div>
            ${submission.graded_by_fullname ? `<p class="text-sm text-blue-600 mt-2">By: ${submission.graded_by_fullname}</p>` : ''}
          </div>
          ` : ''}
        </div>
      `;
      
      content.innerHTML = html;
      modal.classList.remove('hidden');
    }

    function closeAssignmentViewModal() {
      document.getElementById('assignmentViewModal').classList.add('hidden');
    }

    // Assessment View Modal Functions
    function viewAssessmentSubmission(attemptId) {
      console.log('viewAssessmentSubmission called with attemptId:', attemptId);
      
      // Fetch assessment submission details
      fetch(`/TPLearn/api/get-assessment-submission.php?attempt_id=${attemptId}`)
        .then(response => {
          console.log('API Response status:', response.status);
          return response.text();
        })
        .then(text => {
          console.log('API Raw response:', text);
          try {
            const data = JSON.parse(text);
            console.log('API Parsed data:', data);
            
            if (data.success && data.submission) {
              showAssessmentViewModal(data.submission);
            } else {
              console.error('API Error:', data);
              showNotification('error', 'Error', data.message || 'No submission found for this assessment.');
            }
          } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Raw text was:', text);
            showNotification('error', 'Error', 'Failed to parse response from server. Check console for details.');
          }
        })
        .catch(error => {
          console.error('Fetch Error:', error);
          showNotification('error', 'Error', 'Failed to load submission details: ' + error.message);
        });
    }

    function showAssessmentViewModal(submission) {
      const modal = document.getElementById('assessmentViewModal');
      const content = document.getElementById('assessmentViewContent');
      
      // Build the modal content
      let html = `
        <div class="space-y-6">
          <!-- Assessment Info -->
          <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <h4 class="font-semibold text-purple-900 mb-2">${submission.assessment_title}</h4>
            <p class="text-sm text-purple-700">${submission.assessment_description || 'This assessment covers chapters 1-3 of the study material. Please read through the document carefully and answer all questions based on your understanding of the concepts presented.'}</p>
            <p class="text-sm text-purple-600 mt-2"><strong>Total Points:</strong> ${submission.total_points || 100}</p>
          </div>
          
          <!-- Submission Details -->
          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <h5 class="font-semibold text-gray-900 mb-3">Submission Details</h5>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="font-medium text-gray-700">Status:</span>
                <span class="ml-2 px-2 py-1 rounded text-xs ${
                  submission.status === 'submitted' ? 'bg-green-100 text-green-800' : 
                  submission.status === 'graded' ? 'bg-blue-100 text-blue-800' : 
                  'bg-gray-100 text-gray-800'
                }">${submission.status.charAt(0).toUpperCase() + submission.status.slice(1)}</span>
              </div>
              <div>
                <span class="font-medium text-gray-700">Submitted:</span>
                <span class="ml-2 text-gray-600">${new Date(submission.submitted_at).toLocaleString()}</span>
              </div>
              ${submission.score !== null ? `
              <div>
                <span class="font-medium text-gray-700">Score:</span>
                <span class="ml-2 text-gray-600">${submission.score}${submission.total_points ? '/' + submission.total_points : ''}</span>
              </div>
              ` : ''}
            </div>
          </div>
          
          <!-- Submission Content -->
          <div>
            <h5 class="font-semibold text-gray-900 mb-3">Submission Content</h5>
            
            ${submission.file ? `
              <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h6 class="font-medium text-gray-700 mb-3">Submitted File:</h6>
                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded border">
                  <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  <div class="flex-1">
                    <p class="font-medium text-gray-900">${submission.file.original_filename}</p>
                    <p class="text-sm text-gray-500">${(submission.file.file_size / 1024 / 1024).toFixed(2)} MB</p>
                  </div>
                  <div class="flex space-x-2">
                    <a href="/TPLearn/api/serve-submission-file.php?file_id=${submission.file.id}&action=view" target="_blank" 
                       class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                      View
                    </a>
                    <a href="/TPLearn/api/serve-submission-file.php?file_id=${submission.file.id}&action=download" 
                       class="bg-purple-500 text-white px-3 py-1 rounded text-sm hover:bg-purple-600">
                      Download
                    </a>
                  </div>
                </div>
              </div>
            ` : '<p class="text-gray-500 italic">No file submitted.</p>'}
          </div>
          
          <!-- Feedback Section -->
          ${submission.comments ? `
          <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <h5 class="font-semibold text-purple-900 mb-2">Feedback</h5>
            <div class="text-purple-800 whitespace-pre-wrap">${submission.comments}</div>
            ${submission.tutor_name ? `<p class="text-sm text-purple-600 mt-2">By: ${submission.tutor_name}</p>` : ''}
          </div>
          ` : ''}
        </div>
      `;
      
      content.innerHTML = html;
      modal.classList.remove('hidden');
    }

    function closeAssessmentViewModal() {
      document.getElementById('assessmentViewModal').classList.add('hidden');
    }

    // LIVE SESSIONS FUNCTIONS
    let jitsiAPI = null;
    let currentUserId = <?php echo json_encode($student_user_id); ?>;
    let currentUserName = '<?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'Student'); ?>';
    let currentUserEmail = '<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>';

    // Load Live Sessions for All Content view
    async function loadLiveSessionsForAllContent() {
      console.log('Loading Live Sessions for All Content view...');
      try {
        const apiUrl = `../../api/jitsi_meetings.php?action=get_meetings&program_id=<?= $program_id ?>`;
        console.log('API URL:', apiUrl);
        
        const response = await fetch(apiUrl, {
          credentials: 'include'
        });
        
        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('API Result:', result);
        
        if (result.success && result.meetings && result.meetings.length > 0) {
          console.log('Found', result.meetings.length, 'live sessions');
          displayLiveSessionsInAllContent(result.meetings);
          
          // Hide "No Content Available" message if it exists since we have live sessions
          const noContentDiv = document.getElementById('noContentMessage');
          if (noContentDiv) {
            noContentDiv.style.display = 'none';
          }
          
          // After adding Live Sessions, sort all content by Latest to Old
          sortAllContentByLatestToOld();
        } else {
          console.log('No live sessions found or API error:', result.message || 'Unknown error');
          // Even if no Live Sessions, sort existing materials
          sortAllContentByLatestToOld();
        }
        // Don't show error or empty state here since this is for All Content view
      } catch (error) {
        console.error('Error loading live sessions for all content:', error);
        // Sort existing materials even if Live Sessions fail to load
        sortAllContentByLatestToOld();
      }
    }

    function sortAllContentByLatestToOld() {
      const allContentContainer = document.getElementById('allContentLiveSessionsContainer').parentNode;
      const contentItems = Array.from(allContentContainer.querySelectorAll('.content-item'));
      
      console.log('=== SORTING DEBUG ===');
      console.log('Found content items:', contentItems.length);
      
      // Debug: log all items found
      contentItems.forEach((item, index) => {
        const title = item.querySelector('h3') ? item.querySelector('h3').textContent : 'No title';
        const timeDiv = item.querySelector('.text-gray-500.text-right div');
        const timeText = timeDiv ? timeDiv.textContent.trim() : 'No time';
        console.log(`Item ${index}: ${item.dataset.type} - "${title}" - ${timeText}`);
      });
      
      // Extract datetime for sorting
      const itemsWithDateTime = contentItems.map(item => {
        let dateTime = null;
        
        // Both materials and Live Sessions have the same structure for time ago display
        // Look for: <div class="text-sm text-gray-500 text-right"><div>X minutes ago</div></div>
        const timeAgoDiv = item.querySelector('.text-gray-500.text-right div');
        
        if (timeAgoDiv) {
          const timeText = timeAgoDiv.textContent.trim();
          console.log('Found time text:', timeText, 'for item type:', item.dataset.type);
          
          const now = new Date();
          
          if (timeText.includes('hour ago') && !timeText.includes('hours')) {
            // Handle "1 hour ago" specifically
            dateTime = new Date(now.getTime() - (1 * 60 * 60 * 1000));
          } else if (timeText.includes('hours ago')) {
            const hours = parseInt(timeText.match(/(\d+)\s+hours ago/)?.[1] || 0);
            dateTime = new Date(now.getTime() - (hours * 60 * 60 * 1000));
          } else if (timeText.includes('minutes ago')) {
            const minutes = parseInt(timeText.match(/(\d+)\s+minutes ago/)?.[1] || 0);
            dateTime = new Date(now.getTime() - (minutes * 60 * 1000));
          } else if (timeText.includes('days ago')) {
            const days = parseInt(timeText.match(/(\d+)\s+days ago/)?.[1] || 0);
            dateTime = new Date(now.getTime() - (days * 24 * 60 * 60 * 1000));
          } else if (timeText.includes('weeks ago')) {
            const weeks = parseInt(timeText.match(/(\d+)\s+weeks ago/)?.[1] || 0);
            dateTime = new Date(now.getTime() - (weeks * 7 * 24 * 60 * 60 * 1000));
          } else if (timeText === 'Just now') {
            dateTime = now;
          }
        }
        
        console.log('Parsed datetime for', item.dataset.type, ':', dateTime);
        
        return {
          element: item,
          dateTime: dateTime || new Date(0), // Default to epoch if can't parse
          timeText: timeAgoDiv ? timeAgoDiv.textContent.trim() : 'unknown'
        };
      });
      
      // Sort by dateTime descending (newest first - Latest to Old)
      itemsWithDateTime.sort((a, b) => {
        if (a.dateTime && b.dateTime) {
          return b.dateTime - a.dateTime; // Newest first
        }
        return 0;
      });
      
      console.log('Sorted items:');
      itemsWithDateTime.forEach((item, index) => {
        console.log(`${index + 1}. ${item.element.dataset.type} - ${item.timeText} - ${item.dateTime}`);
      });
      
      // Remove all items from DOM
      contentItems.forEach(item => item.remove());
      
      // Re-add items in sorted order - BUT PUT EVERYTHING IN THE MAIN CONTAINER
      itemsWithDateTime.forEach((item, index) => {
        // Insert all items into the main container in sorted order
        allContentContainer.insertBefore(item.element, document.getElementById('allContentLiveSessionsContainer'));
      });
      
      console.log('Content reordered successfully - Latest to Old');
      console.log('=== END SORTING DEBUG ===');
    }

    // Load Live Sessions
    async function loadLiveSessions() {
      try {
        const response = await fetch(`../../api/jitsi_meetings.php?action=get_meetings&program_id=<?= $program_id ?>`, {
          credentials: 'include'
        });
        
        const result = await response.json();
        
        if (result.success && result.meetings) {
          displayLiveSessions(result.meetings);
        } else {
          console.error('Failed to load live sessions:', result.message);
          displayEmptyLiveSessions();
        }
      } catch (error) {
        console.error('Error loading live sessions:', error);
        displayEmptyLiveSessions();
      }
    }

    // Display Live Sessions
    function displayLiveSessions(meetings) {
      const container = document.getElementById('liveSessionsContainer');
      
      if (!meetings || meetings.length === 0) {
        displayEmptyLiveSessions();
        return;
      }
      
      let html = '';
      
      meetings.forEach(meeting => {
        const isLive = meeting.is_live;
        const isUpcoming = meeting.is_upcoming;
        const isPast = meeting.is_past;
        
        // Use consistent status colors and text
        const statusColor = getLiveStatusColor(isLive, isUpcoming, isPast);
        const statusText = getStatusText(isLive, isUpcoming, isPast);
        
        // Calculate time ago
        const createdAt = new Date(meeting.created_at);
        const now = new Date();
        const timeDiff = Math.floor((now - createdAt) / 1000);
        let timeAgo = 'Just now';
        
        if (timeDiff >= 60 && timeDiff < 3600) {
          timeAgo = Math.floor(timeDiff / 60) + ' minutes ago';
        } else if (timeDiff >= 3600 && timeDiff < 86400) {
          timeAgo = Math.floor(timeDiff / 3600) + ' hours ago';
        } else if (timeDiff >= 86400) {
          timeAgo = Math.floor(timeDiff / 86400) + ' days ago';
        }
        
        html += `
          <!-- Live Session: ${escapeHtml(meeting.title)} -->
          <div class="content-item bg-white rounded-lg border border-gray-200 p-6 mb-6" data-type="live-sessions">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                  <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">${escapeHtml(meeting.title)}</h3>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Live Session</span>
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor}">
                        ${isLive ? 'Live' : (isUpcoming ? 'Upcoming' : 'Ended')}
                      </span>
                    </div>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="text-sm text-gray-500">Session:</span>
                      <span class="text-sm font-medium text-gray-600">${meeting.meeting_id}</span>
                      <span class="text-sm text-gray-500">on</span>
                      <span class="text-sm font-medium text-gray-700">${meeting.formatted_date} at ${meeting.formatted_time}</span>
                    </div>
                    ${meeting.description ? `<p class="text-sm text-gray-700 mb-3">${escapeHtml(meeting.description)}</p>` : ''}
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        ${meeting.duration_minutes} minutes
                      </span>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" clip-rule="evenodd"></path>
                        </svg>
                        ${meeting.participant_count}/${meeting.max_participants} participants
                      </span>
                    </div>
                    <div class="flex items-center space-x-2">
                      ${isLive || isUpcoming ? `
                        <button onclick="joinLiveSession('${meeting.meeting_url}', ${meeting.id}, true)" 
                                class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center" 
                                title="Join live session">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                          </svg>
                          Join Now
                        </button>
                      ` : `
                        <button onclick="viewLiveSessionDetails(${meeting.id})" 
                                class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                          </svg>
                          View Details
                        </button>
                      `}
                    </div>
                  </div>
                  <div class="text-sm text-gray-500 text-right">
                    <div>${timeAgo}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
      });
      
      container.innerHTML = html;
    }

    // Display Live Sessions in All Content view
    function displayLiveSessionsInAllContent(meetings) {
      const container = document.getElementById('allContentLiveSessionsContainer');
      
      if (!meetings || meetings.length === 0) {
        return; // Don't show anything if no sessions
      }
      
      let html = '';
      
      meetings.forEach(meeting => {
        const isLive = meeting.is_live;
        const isUpcoming = meeting.is_upcoming;
        const isPast = meeting.is_past;
        
        // Use consistent status colors and text
        const statusColor = getLiveStatusColor(isLive, isUpcoming, isPast);
        const statusText = getStatusText(isLive, isUpcoming, isPast);
        
        // Calculate time ago
        const createdAt = new Date(meeting.created_at);
        const now = new Date();
        const timeDiff = Math.floor((now - createdAt) / 1000);
        let timeAgo = 'Just now';
        
        if (timeDiff >= 60 && timeDiff < 3600) {
          timeAgo = Math.floor(timeDiff / 60) + ' minutes ago';
        } else if (timeDiff >= 3600 && timeDiff < 86400) {
          timeAgo = Math.floor(timeDiff / 3600) + ' hours ago';
        } else if (timeDiff >= 86400) {
          timeAgo = Math.floor(timeDiff / 86400) + ' days ago';
        }
        
        html += `
          <!-- Live Session: ${escapeHtml(meeting.title)} -->
          <div class="content-item bg-white rounded-lg border border-gray-200 p-6 mb-6" data-type="live-sessions">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                  <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">${escapeHtml(meeting.title)}</h3>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Live Session</span>
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor}">
                        ${isLive ? 'Live' : (isUpcoming ? 'Upcoming' : 'Ended')}
                      </span>
                    </div>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="text-sm text-gray-500">Session:</span>
                      <span class="text-sm font-medium text-gray-600">${meeting.meeting_id}</span>
                      <span class="text-sm text-gray-500">on</span>
                      <span class="text-sm font-medium text-gray-700">${meeting.formatted_date} at ${meeting.formatted_time}</span>
                    </div>
                    ${meeting.description ? `<p class="text-sm text-gray-700 mb-3">${escapeHtml(meeting.description)}</p>` : ''}
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        ${meeting.duration_minutes} minutes
                      </span>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" clip-rule="evenodd"></path>
                        </svg>
                        ${meeting.participant_count}/${meeting.max_participants} participants
                      </span>
                    </div>
                    <div class="flex items-center space-x-2">
                      ${isLive || isUpcoming ? `
                        <button onclick="joinLiveSession('${meeting.meeting_url}', ${meeting.id}, true)" 
                                class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center" 
                                title="Join live session">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                          </svg>
                          Join Now
                        </button>
                      ` : `
                        <button onclick="viewLiveSessionDetails(${meeting.id})" 
                                class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                          </svg>
                          View Details
                        </button>
                      `}
                    </div>
                  </div>
                  <div class="text-sm text-gray-500 text-right">
                    <div>${timeAgo}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
      });
      
      container.innerHTML = html;
    }

    // Display empty state for live sessions
    function displayEmptyLiveSessions() {
      const container = document.getElementById('liveSessionsContainer');
      container.innerHTML = `
        <div class="text-center py-12">
          <div class="w-16 h-16 bg-red-100 rounded-lg flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" clip-rule="evenodd"></path>
            </svg>
          </div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">No Live Sessions Available</h3>
          <p class="text-gray-600">Your instructor hasn't scheduled any live sessions yet. Check back later!</p>
        </div>
      `;
    }

    // Join Live Session using Jitsi Meet External API
    async function joinLiveSession(meetingUrl, meetingId, openInNewTab = false) {
      try {
        // Show checking status
        showNotification('info', 'Checking Session', 'Verifying tutor presence...');
        
        // First, check if tutor is present in the session
        const tutorCheckResponse = await fetch(`../../api/check-tutor-presence.php?meeting_id=${meetingId}&program_id=<?php echo $program_id; ?>`, {
          method: 'GET',
          credentials: 'include'
        });
        
        if (!tutorCheckResponse.ok) {
          throw new Error('Failed to verify session status');
        }
        
        const tutorCheck = await tutorCheckResponse.json();
        
        if (!tutorCheck.canJoin) {
          // Tutor is not present, show appropriate message
          let title = 'Cannot Join Session';
          let message = tutorCheck.message;
          let type = 'warning';
          
          if (tutorCheck.reason === 'tutor_not_present') {
            title = 'Waiting for Tutor';
            message = 'Your tutor has not started the session yet. Please wait for the tutor to join first.';
            type = 'info';
          } else if (tutorCheck.reason === 'tutor_left') {
            title = 'Session Paused';
            message = `Your tutor has left the session. Please wait for them to return or check if the session has ended.`;
            type = 'warning';
          } else if (tutorCheck.reason === 'meeting_not_started') {
            title = 'Session Not Started';
            message = `The session hasn't started yet. Scheduled start time: ${new Date(tutorCheck.startTime).toLocaleString()}`;
            type = 'info';
          } else if (tutorCheck.reason === 'meeting_ended') {
            title = 'Session Ended';
            message = `This session has already ended at ${new Date(tutorCheck.endTime).toLocaleString()}`;
            type = 'error';
          }
          
          showNotification(type, title, message);
          
          // Offer to check again if tutor might join soon
          if (tutorCheck.reason === 'tutor_not_present' || tutorCheck.reason === 'tutor_inactive') {
            setTimeout(() => {
              showRecheckTutorModal(meetingId, meetingUrl, openInNewTab);
            }, 2000);
          }
          
          return;
        }
        
        // Tutor is present, proceed to join
        showNotification('success', 'Tutor Present', `${tutorCheck.tutorName} is in the session. Joining now...`);
        
        // Record participation tracking
        await fetch('../../api/track-session-participation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            meeting_id: meetingId,
            action: 'join'
          }),
          credentials: 'include'
        });
        
        // Also record in the existing system
        await fetch('../../api/jitsi_meetings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=join_meeting&meeting_id=${meetingId}`,
          credentials: 'include'
        });
        
        if (openInNewTab) {
          // Open in new tab/window
          window.open(meetingUrl, '_blank');
          showNotification('info', 'Live Session', 'Opening live session...');
        } else {
          // Extract room name from meeting URL
          const roomName = meetingUrl.split('/').pop();
          
          // Open Jitsi meeting in modal overlay
          openJitsiMeeting(roomName, meetingId);
          
          // Store meetingId for tracking
          window.currentMeetingId = meetingId;
          
          // Start heartbeat for student
          startStudentHeartbeat(meetingId);
        }
        
      } catch (error) {
        console.error('Error joining session:', error);
        showNotification('error', 'Join Failed', 'Failed to join live session. Please try again.');
      }
    }

    // Show recheck tutor modal
    function showRecheckTutorModal(meetingId, meetingUrl, openInNewTab) {
      const modal = document.createElement('div');
      modal.id = 'recheckTutorModal';
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">Waiting for Tutor</h3>
            <button onclick="closeRecheckTutorModal()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <div class="mb-6">
            <div class="flex items-center justify-center mb-4">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green"></div>
            </div>
            <p class="text-gray-600 text-center mb-4">
              Waiting for your tutor to start the session...
            </p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
              <p class="text-sm text-blue-700">
                <strong>Tip:</strong> Your tutor needs to join the session first before you can participate. 
                We'll check automatically every 30 seconds.
              </p>
            </div>
          </div>
          
          <div class="flex space-x-3">
            <button 
              onclick="recheckTutorPresence(${meetingId}, '${meetingUrl}', ${openInNewTab})" 
              class="flex-1 bg-tplearn-green hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium">
              Check Again
            </button>
            <button 
              onclick="closeRecheckTutorModal()" 
              class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg font-medium">
              Cancel
            </button>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Start automatic checking every 30 seconds
      window.tutorCheckInterval = setInterval(() => {
        recheckTutorPresence(meetingId, meetingUrl, openInNewTab, true);
      }, 30000);
    }

    // Recheck tutor presence
    async function recheckTutorPresence(meetingId, meetingUrl, openInNewTab, autoCheck = false) {
      try {
        const response = await fetch(`../../api/check-tutor-presence.php?meeting_id=${meetingId}&program_id=<?php echo $program_id; ?>`, {
          method: 'GET',
          credentials: 'include'
        });
        
        const result = await response.json();
        
        if (result.canJoin) {
          // Tutor is now present, close modal and join
          closeRecheckTutorModal();
          showNotification('success', 'Tutor Joined', `${result.tutorName} is now in the session!`);
          
          // Small delay then join
          setTimeout(() => {
            joinLiveSession(meetingUrl, meetingId, openInNewTab);
          }, 1000);
          
        } else if (!autoCheck) {
          // Manual check failed, show message
          showNotification('info', 'Still Waiting', result.message);
        }
        
      } catch (error) {
        if (!autoCheck) {
          showNotification('error', 'Check Failed', 'Unable to verify tutor status. Please try again.');
        }
      }
    }

    // Close recheck tutor modal
    function closeRecheckTutorModal() {
      const modal = document.getElementById('recheckTutorModal');
      if (modal) {
        modal.remove();
      }
      
      // Clear the interval
      if (window.tutorCheckInterval) {
        clearInterval(window.tutorCheckInterval);
        window.tutorCheckInterval = null;
      }
    }

    // Start student heartbeat to maintain presence
    function startStudentHeartbeat(meetingId) {
      // Clear any existing heartbeat
      if (window.studentHeartbeatInterval) {
        clearInterval(window.studentHeartbeatInterval);
      }
      
      // Send heartbeat every 60 seconds
      window.studentHeartbeatInterval = setInterval(async () => {
        try {
          const response = await fetch('../../api/track-session-participation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              meeting_id: meetingId,
              action: 'heartbeat'
            }),
            credentials: 'include'
          });
          
          const result = await response.json();
          if (!result.success && result.action === 'rejoin_required') {
            // Session lost, need to rejoin
            console.log('Session lost, need to rejoin');
            stopStudentHeartbeat();
            // Could implement auto-rejoin logic here
          }
          
          console.log('Student heartbeat sent');
        } catch (error) {
          console.error('Student heartbeat failed:', error);
        }
      }, 60000);
    }
    
    // Stop student heartbeat
    function stopStudentHeartbeat() {
      if (window.studentHeartbeatInterval) {
        clearInterval(window.studentHeartbeatInterval);
        window.studentHeartbeatInterval = null;
      }
    }
    
    // Track when student leaves session
    async function trackStudentLeave(meetingId) {
      try {
        await fetch('../../api/track-session-participation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            meeting_id: meetingId,
            action: 'leave'
          }),
          credentials: 'include'
        });
        console.log('Student leave tracked');
      } catch (error) {
        console.error('Failed to track student leave:', error);
      }
    }

    // Generate consistent room name for student participation
    function generateStudentRoomName(roomName, meetingId) {
      // Ensure consistent room naming with tutor's room
      // Students join the same base room as the tutor
      const baseRoom = roomName.replace(/\/(tutor|student)$/, '');
      return `tplearn-${<?php echo $program_id; ?>}-${meetingId}-${baseRoom}`;
    }

    // Open Jitsi Meeting using External API
    function openJitsiMeeting(roomName, meetingId) {
      // Create modal overlay for Jitsi meeting
      const modal = document.createElement('div');
      modal.id = 'jitsi-modal';
      modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75';
      modal.innerHTML = `
        <div class="relative w-full h-full max-w-7xl mx-4">
          <div class="absolute top-4 right-4 z-10">
            <button onclick="closeJitsiMeeting()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium">
              Leave Session
            </button>
          </div>
          <div id="jitsi-container" class="w-full h-full bg-white rounded-lg overflow-hidden"></div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Generate consistent room name to join tutor's session
      const studentRoomName = generateStudentRoomName(roomName, meetingId);
      
      // Configure Jitsi Meet options for STUDENT (Participant)
      const options = {
        roomName: studentRoomName,
        width: '100%',
        height: '100%',
        parentNode: document.querySelector('#jitsi-container'),
        configOverwrite: {
          startWithAudioMuted: true, // Students start muted
          startWithVideoMuted: false,
          enableWelcomePage: false,
          prejoinPageEnabled: false,
          disableModeratorIndicator: true, // Hide moderator indicators for students
          startScreenSharing: false,
          enableEmailInStats: false,
          // Student-specific restrictions
          startAudioOnly: false,
          enableLayerSuspension: true,
          enableTalkWhileMuted: false,
          enableNoAudioSignal: true,
          enableNoisyMicDetection: true,
          // Disable advanced features for students
          disableDeepLinking: true,
          enableClosePage: false
        },
        interfaceConfigOverwrite: {
          DISABLE_JOIN_LEAVE_NOTIFICATIONS: true, // Less distracting for students
          DISABLE_PRESENCE_STATUS: true,
          DISPLAY_WELCOME_PAGE_CONTENT: false,
          ENABLE_FEEDBACK_ANIMATION: false,
          FILMSTRIP_ENABLED: true,
          GENERATE_ROOMNAMES_ON_WELCOME_PAGE: false,
          HIDE_INVITE_MORE_HEADER: true, // Students can't invite others
          JITSI_WATERMARK_LINK: 'https://tplearn.com',
          POLICY_LOGO: null,
          PROVIDER_NAME: 'TPLearn',
          SHOW_JITSI_WATERMARK: false,
          SHOW_WATERMARK_FOR_GUESTS: false,
          SUPPORT_URL: 'https://tplearn.com/support',
          // Limited toolbar for students (no moderation controls)
          TOOLBAR_BUTTONS: [
            'microphone', 'camera', 'hangup', 'chat', 'raisehand',
            'videoquality', 'filmstrip', 'settings', 'tileview',
            'fullscreen', 'fodeviceselection'
          ],
          // Student-specific interface restrictions
          SHOW_CHROME_EXTENSION_BANNER: false,
          DEFAULT_BACKGROUND: '#474747',
          // Hide advanced controls from students
          DISABLE_FOCUS_INDICATOR: true,
          DISABLE_DOMINANT_SPEAKER_INDICATOR: false
        },
        userInfo: {
          displayName: `${currentUserName} (Student)`,
          email: currentUserEmail,
          // Mark this user as participant (not moderator)
          role: 'participant'
        },
        // Join as regular participant
        roomSuffix: ''
      };
      
      // Initialize Jitsi Meet API
      jitsiAPI = new JitsiMeetExternalAPI('meet.jit.si', options);
      
      // Add escape key listener
      document.addEventListener('keydown', handleJitsiEscape);
      
      // Add event listeners for student participants
      jitsiAPI.addEventListener('videoConferenceJoined', () => {
        console.log('Student successfully joined the session');
        showNotification('success', 'Joined Session', 'Successfully joined the live session as a participant!');
        
        // Student-specific setup
        try {
          // Ensure student starts with appropriate settings
          console.log('Student participant setup complete');
        } catch (error) {
          console.log('Student setup error:', error);
        }
      });
      
      jitsiAPI.addEventListener('videoConferenceLeft', () => {
        console.log('Student left the session');
        closeJitsiMeeting();
      });
      
      jitsiAPI.addEventListener('readyToClose', () => {
        console.log('Student ready to close session');
        closeJitsiMeeting();
      });
      
      jitsiAPI.addEventListener('participantJoined', (participant) => {
        console.log('New participant joined:', participant);
        // Students see basic join notifications
        if (participant.displayName && participant.displayName.includes('Tutor')) {
          showNotification('info', 'Tutor Joined', 'Your tutor has joined the session');
        }
      });
      
      jitsiAPI.addEventListener('participantLeft', (participant) => {
        console.log('Participant left:', participant);
        // Students see when tutor leaves
        if (participant.displayName && participant.displayName.includes('Tutor')) {
          showNotification('warning', 'Tutor Left', 'Your tutor has left the session');
        }
      });
      
      // Student-specific event listeners
      jitsiAPI.addEventListener('participantRoleChanged', (event) => {
        console.log('Role changed (student view):', event);
        // Students are informed of their role changes
        if (event.role === 'participant') {
          console.log('Confirmed as participant');
        }
      });
      
      // Listen for moderator actions affecting this student
      jitsiAPI.addEventListener('audioMuteStatusChanged', (event) => {
        if (event.muted) {
          console.log('Audio muted by moderator');
          showNotification('info', 'Audio Muted', 'Your microphone was muted by the tutor');
        }
      });
      
      jitsiAPI.addEventListener('videoMuteStatusChanged', (event) => {
        if (event.muted) {
          console.log('Video muted by moderator');
          showNotification('info', 'Video Muted', 'Your camera was turned off by the tutor');
        }
      });
      
      // Show success message
      showNotification('info', 'Loading Session', 'Loading live session...');
    }

    // Close Jitsi Meeting
    function closeJitsiMeeting() {
      // Track student leaving the session
      if (window.currentMeetingId) {
        trackStudentLeave(window.currentMeetingId);
        window.currentMeetingId = null;
      }
      
      // Stop heartbeat
      stopStudentHeartbeat();
      
      if (jitsiAPI) {
        jitsiAPI.dispose();
        jitsiAPI = null;
      }
      
      const modal = document.getElementById('jitsi-modal');
      if (modal) {
        modal.remove();
      }
      
      // Remove escape key listener
      document.removeEventListener('keydown', handleJitsiEscape);
      
      // Close the recheck modal if it's open
      closeRecheckTutorModal();
      
      showNotification('info', 'Left Session', 'Left the live session');
    }

    // Handle escape key to close Jitsi meeting
    function handleJitsiEscape(event) {
      if (event.key === 'Escape' && jitsiAPI) {
        closeJitsiMeeting();
      }
    }

    // View Live Session Details
    function viewLiveSessionDetails(meetingId) {
      // Create modal for meeting details
      const modal = document.createElement('div');
      modal.id = 'meetingDetailsModal';
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Live Session Details</h3>
            <button onclick="closeMeetingDetailsModal()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div id="meetingDetailsContent">
              <div class="text-center py-8">
                <svg class="animate-spin h-8 w-8 text-tplearn-green mx-auto" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-gray-600">Loading meeting details...</p>
              </div>
            </div>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Load meeting details
      loadMeetingDetails(meetingId);
    }

    function loadMeetingDetails(meetingId) {
      fetch(`../../api/jitsi_meetings.php?action=get_meeting_details&meeting_id=${meetingId}`, {
        credentials: 'include'
      })
      .then(response => response.json())
      .then(data => {
        const content = document.getElementById('meetingDetailsContent');
        
        if (data.success && data.meeting) {
          const meeting = data.meeting;
          const startDate = new Date(meeting.scheduled_start);
          const endDate = new Date(meeting.scheduled_end);
          
          content.innerHTML = `
            <div class="space-y-6">
              <!-- Meeting Header -->
              <div class="flex items-start">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                  <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="flex-1">
                  <h4 class="text-xl font-semibold text-gray-900">${escapeHtml(meeting.title)}</h4>
                  <p class="text-gray-600 mt-1">Live Session</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(meeting.status)}">
                  ${meeting.status}
                </span>
              </div>

              <!-- Meeting Info -->
              <div class="bg-gray-50 rounded-lg p-4">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <dt class="font-medium text-gray-900">Meeting ID</dt>
                    <dd class="mt-1 text-gray-600 font-mono">${meeting.meeting_id}</dd>
                  </div>
                  <div>
                    <dt class="font-medium text-gray-900">Duration</dt>
                    <dd class="mt-1 text-gray-600">${meeting.duration_minutes} minutes</dd>
                  </div>
                  <div>
                    <dt class="font-medium text-gray-900">Scheduled Start</dt>
                    <dd class="mt-1 text-gray-600">${startDate.toLocaleDateString()} at ${startDate.toLocaleTimeString()}</dd>
                  </div>
                  <div>
                    <dt class="font-medium text-gray-900">Scheduled End</dt>
                    <dd class="mt-1 text-gray-600">${endDate.toLocaleDateString()} at ${endDate.toLocaleTimeString()}</dd>
                  </div>
                  <div>
                    <dt class="font-medium text-gray-900">Participants</dt>
                    <dd class="mt-1 text-gray-600">${meeting.participant_count}/${meeting.max_participants}</dd>
                  </div>
                  <div>
                    <dt class="font-medium text-gray-900">Created</dt>
                    <dd class="mt-1 text-gray-600">${new Date(meeting.created_at).toLocaleDateString()}</dd>
                  </div>
                </dl>
              </div>

              ${meeting.description ? `
              <!-- Description -->
              <div>
                <h5 class="font-medium text-gray-900 mb-2">Description</h5>
                <p class="text-gray-600">${escapeHtml(meeting.description)}</p>
              </div>
              ` : ''}

              <!-- Meeting URL -->
              <div>
                <h5 class="font-medium text-gray-900 mb-2">Meeting Link</h5>
                <div class="bg-gray-50 p-3 rounded border">
                  <p class="text-sm font-mono text-gray-600 break-all">${meeting.meeting_url}</p>
                </div>
              </div>
            </div>
          `;
        } else {
          content.innerHTML = `
            <div class="text-center py-8">
              <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="text-gray-600">Failed to load meeting details.</p>
              <p class="text-sm text-gray-500 mt-1">${data.message || 'Unknown error occurred'}</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error loading meeting details:', error);
        const content = document.getElementById('meetingDetailsContent');
        content.innerHTML = `
          <div class="text-center py-8">
            <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-gray-600">Error loading meeting details.</p>
          </div>
        `;
      });
    }

    function closeMeetingDetailsModal() {
      const modal = document.getElementById('meetingDetailsModal');
      if (modal) {
        modal.remove();
      }
    }

    // Get status color for meetings based on live status
    function getStatusColor(status) {
      const colors = {
        'scheduled': 'bg-blue-100 text-blue-800',
        'active': 'bg-green-100 text-green-800', 
        'completed': 'bg-gray-100 text-gray-800',
        'ended': 'bg-gray-100 text-gray-800',
        'cancelled': 'bg-red-100 text-red-800'
      };
      return colors[status] || 'bg-gray-100 text-gray-800';
    }
    
    // Get status color based on live/upcoming/ended status
    function getLiveStatusColor(isLive, isUpcoming, isPast) {
      if (isLive) {
        return 'bg-green-100 text-green-800'; // Green for live sessions
      } else if (isUpcoming) {
        return 'bg-blue-100 text-blue-800';   // Blue for upcoming sessions
      } else {
        return 'bg-gray-100 text-gray-800';   // Gray for ended sessions
      }
    }
    
    // Get consistent status text
    function getStatusText(isLive, isUpcoming, isPast) {
      if (isLive) {
        return 'Live Now';
      } else if (isUpcoming) {
        return 'Upcoming';
      } else {
        return 'Ended';
      }
    }

    // Escape HTML helper function
    function escapeHtml(text) {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
  </script>
</body>

</html>