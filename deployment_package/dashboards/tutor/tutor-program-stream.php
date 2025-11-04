<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('tutor');

// Get current tutor data from session
$tutor_user_id = $_SESSION['user_id'] ?? null;

// Get program info from URL parameters
$program_id = $_GET['program_id'] ?? null;
$program_name = $_GET['program'] ?? 'Program';

if (!$program_id || !$tutor_user_id) {
  header('Location: tutor-programs.php');
  exit();
}

// Verify tutor has access to this program
$stmt = $conn->prepare("SELECT * FROM programs WHERE id = ? AND tutor_id = ?");
$stmt->bind_param('ii', $program_id, $tutor_user_id);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();

if (!$program) {
  header('Location: tutor-programs.php?error=access_denied');
  exit();
}

$program_name = $program['name'];
$program_description = $program['description'] ?: 'Advanced mathematics program for high school students focusing on algebra, calculus, and geometry.';

// Get materials for this program (no filter to get all materials)
$materials = getProgramMaterials($program_id);

// Debug: Check if materials are being fetched
error_log("Program ID: " . $program_id);
error_log("Materials count: " . count($materials));
if (!empty($materials)) {
  error_log("First material: " . print_r($materials[0], true));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($program_name) ?> - Stream - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="../../assets/tplearn-tailwind.css?v=<?= filemtime(__DIR__ . '/../../assets/tplearn-tailwind.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Jitsi Meet External API -->
  <script src="https://meet.jit.si/external_api.js"></script>
</head>

<body class="bg-gray-50 min-h-screen">
  <div class="flex">
    <?php include '../../includes/tutor-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 lg:ml-64">
      <?php 
      require_once '../../includes/header.php';
      renderHeader(
        htmlspecialchars($program_name) . ' Stream',
        '',
        'tutor',
        $_SESSION['name'] ?? 'Tutor'
      );
      ?>

      <!-- Back to Programs -->
      <div class="bg-white border-b border-gray-200 px-4 lg:px-6 py-4">
        <a href="tutor-programs.php" class="flex items-center text-gray-600 hover:text-gray-900">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
          </svg>
          Back to Programs
        </a>
      </div>

      <!-- Filter Tabs -->
      <div class="bg-white border-b border-gray-200 px-4 lg:px-6">
        <nav class="flex space-x-8">
          <button onclick="filterContent('all')" class="filter-tab py-4 px-1 border-b-2 border-tplearn-green text-tplearn-green font-medium text-sm whitespace-nowrap">
            All
          </button>
          <button onclick="filterContent('documents')" class="filter-tab py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
            Documents
          </button>
          <button onclick="filterContent('assignments')" class="filter-tab py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
            Assignments
          </button>
          <button onclick="filterContent('live-classes')" class="filter-tab py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
            Live Sessions
          </button>
        </nav>
      </div>

      <!-- Main Content -->
      <main class="p-4 lg:p-6">
        <!-- Action Buttons Header -->
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-semibold text-gray-900">Program Stream</h2>
          <div class="flex space-x-3">
            <button onclick="openCreateLiveClassModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
              </svg>
              Create Live Session
            </button>
            <button onclick="openUploadModal()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors flex items-center">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
              </svg>
              Upload Material
            </button>
          </div>
        </div>
        
        <div class="space-y-6" id="content-area">
          
          <!-- Empty State (shown when no content) -->
          <div id="empty-state" class="hidden bg-white rounded-lg border border-gray-200 p-12 text-center">
            <div class="max-w-md mx-auto">
              <div class="flex justify-center space-x-4 mb-6">
                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center">
                  <svg class="w-8 h-8 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center">
                  <svg class="w-8 h-8 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">No Content Yet</h3>
              <p class="text-gray-600">Get started by uploading materials or creating a live session for your students.</p>
            </div>
          </div>
          
          <!-- Live Classes Section -->
          <div id="live-classes-section" class="content-section" data-type="live-classes">
            <div id="live-classes-container">
              <!-- Live classes will be loaded here -->
            </div>
          </div>

          <?php 
          // Helper function to get material type color and icon
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

          // Display materials
          if (!empty($materials)): 
            foreach ($materials as $material): 
              $typeDisplay = getMaterialTypeDisplay($material['material_type']);
              $relativeTime = time() - strtotime($material['created_at']);
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
          <!-- Material: <?= htmlspecialchars($material['title']) ?> -->
          <div class="content-item bg-white rounded-lg border border-gray-200 p-6" data-type="<?= $material['material_type'] ?>">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-<?= $typeDisplay['color'] ?>-100 rounded-lg flex items-center justify-center">
                  <svg class="w-6 h-6 text-<?= $typeDisplay['color'] ?>-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="<?= $typeDisplay['icon'] ?>" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars($material['title']) ?></h3>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="bg-<?= $typeDisplay['color'] ?>-100 text-<?= $typeDisplay['color'] ?>-800 px-2 py-1 rounded text-xs font-medium"><?= ucfirst($material['material_type']) ?></span>
                      <?php if (!empty($material['assessment_id'])): ?>
                      <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-medium">Assessment</span>
                      <?php endif; ?>
                      <span class="text-sm text-gray-500">File:</span>
                      <span class="text-sm font-medium text-gray-600"><?= htmlspecialchars($material['original_filename'] ?? $material['filename'] ?? 'No file') ?></span>
                      <span class="text-sm text-gray-500">by</span>
                      <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($material['uploader_name'] ?? 'Unknown') ?></span>
                    </div>
                    <?php if (!empty($material['description'])): ?>
                    <p class="text-sm text-gray-700 mb-3"><?= htmlspecialchars($material['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        <?= $material['file_size_formatted'] ?>
                      </span>
                      <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <?= $material['upload_time_formatted'] ?>
                      </span>
                    </div>
                    <div class="flex items-center space-x-2">
                      <button onclick="viewItem('<?= $material['material_id'] ?>', 'document')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View
                      </button>
                      <button onclick="downloadItem('<?= $material['file_id'] ?>')" class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download
                      </button>
                      <?php if ($material['material_type'] === 'assignment'): ?>
                      <button onclick="viewSubmissions('<?= $material['material_id'] ?>')" class="text-purple-600 hover:text-purple-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        View Submissions
                      </button>
                      <?php endif; ?>
                      <?php if (!empty($material['assessment_id'])): ?>
                      <button onclick="viewAssessment('<?= $material['assessment_id'] ?>')" class="text-purple-600 hover:text-purple-700 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                          <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                        </svg>
                        View Assessment
                      </button>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-sm text-gray-500 text-right">
                    <div><?= $timeAgo ?></div>
                    <!-- Tutor Management Actions -->
                    <div class="flex items-center space-x-2 mt-2">
                      <button onclick="editItem('<?= $material['material_id'] ?>')" class="bg-gray-600 text-white px-3 py-1 rounded-lg text-xs hover:bg-gray-700 transition-colors">
                        Edit
                      </button>
                      <button onclick="deleteItem('<?= $material['material_id'] ?>')" class="bg-red-600 text-white px-3 py-1 rounded-lg text-xs hover:bg-red-700 transition-colors">
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php 
            endforeach; 
          endif; 
          ?>

        </div>
      </main>
    </div>
  </div>

  <!-- Custom Notification System -->
  <div id="notificationContainer" class="fixed top-4 right-4 z-[9999] space-y-3"></div>

  <!-- Upload Material Modal -->
  <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">Upload Material</h3>
        <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <form id="uploadForm" onsubmit="submitUpload(event)">
          <!-- Material Type -->
          <div class="mb-6">
            <label for="materialType" class="block text-sm font-medium text-gray-700 mb-3">Material Type</label>
            <select id="materialType" name="material_type" required 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
              onchange="handleMaterialTypeChange(this.value)">
              <option value="document" selected>Document</option>
              <option value="video">Video</option>
              <option value="assignment">Assignment</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- File Upload -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Upload File</label>
            <div id="uploadArea" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-gray-400 transition-colors">
              <input type="file" id="fileInput" name="file" class="hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.mp4,.avi,.mov,.wmv,.jpg,.jpeg,.png,.gif,.bmp,.svg" onchange="handleFileSelect(event)">
              <div id="uploadAreaContent">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <p class="text-gray-600 mb-2">Click to upload or drag and drop</p>
                <p class="text-sm text-gray-500">Documents, Videos, Images, Presentations up to 50MB</p>
              </div>
            </div>
            <div id="filePreview" class="hidden mt-3 p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <svg id="fileIcon" class="w-8 h-8 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                  </svg>
                  <div>
                    <p id="fileName" class="font-medium text-gray-900"></p>
                    <p id="fileSize" class="text-sm text-gray-600"></p>
                  </div>
                </div>
                <button type="button" onclick="removeFile()" class="text-red-600 hover:text-red-700">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Material Details -->
          <div class="space-y-4 mb-6">
            <div>
              <label for="materialTitle" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
              <input type="text" id="materialTitle" name="title" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                placeholder="Enter material title">
            </div>

            <div>
              <label for="materialDescription" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea id="materialDescription" name="description" rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                placeholder="Describe the material and its purpose"></textarea>
            </div>

            <div id="assignmentDetails" class="hidden space-y-4">
              <div>
                <label for="assignmentInstructions" class="block text-sm font-medium text-gray-700 mb-2">Assignment Instructions</label>
                <textarea id="assignmentInstructions" name="assignment_instructions" rows="2"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                  placeholder="Provide instructions for the assignment"></textarea>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label for="dueDate" class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                  <input type="datetime-local" id="dueDate" name="due_date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
                </div>
                <div>
                  <label for="totalPoints" class="block text-sm font-medium text-gray-700 mb-2">Total Points</label>
                  <input type="number" id="totalPoints" name="total_points" min="1" value="100"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                    placeholder="Enter total points">
                </div>
              </div>
              <div>
                <label class="flex items-center">
                  <input type="checkbox" id="allowLateSubmissions" name="allow_late_submissions" 
                    class="rounded border-gray-300 text-tplearn-green focus:ring-tplearn-green">
                  <span class="ml-2 text-sm text-gray-700">Allow late submissions (with penalty)</span>
                </label>
              </div>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <div class="flex items-start">
                  <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                  </svg>
                  <div>
                    <p class="text-sm font-medium text-blue-800">Assignment Grading</p>
                    <p class="text-sm text-blue-700">Students will submit their work for this assignment. You can grade submissions and provide feedback through the grading interface.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Assessment Attachment Section (for non-assignment/assessment materials) -->
          <div id="assessmentAttachmentSection" class="space-y-4 mb-6">
            <div class="border-t pt-4">
              <h4 class="text-lg font-medium text-gray-900 mb-3">Attach Assessment (Optional)</h4>
              <p class="text-sm text-gray-600 mb-4">You can attach an assessment file to this material. Students will be able to access the material and complete the attached assessment.</p>
              
              <!-- Assessment File Upload -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Assessment File</label>
                <div id="assessmentUploadArea" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-gray-400 transition-colors">
                  <input type="file" id="assessmentFileInput" name="assessmentFile" class="hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt" onchange="handleAssessmentFileSelect(event)">
                  <div id="assessmentUploadAreaContent">
                    <svg class="w-10 h-10 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-gray-600 mb-1">Click to upload assessment file</p>
                    <p class="text-xs text-gray-500">PDF, Word, Excel, PowerPoint documents</p>
                  </div>
                </div>
                <div id="assessmentFilePreview" class="hidden mt-3 p-3 bg-gray-50 rounded-lg">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                      <svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                      </svg>
                      <div>
                        <p id="assessmentFileName" class="font-medium text-gray-900"></p>
                        <p id="assessmentFileSize" class="text-sm text-gray-600"></p>
                      </div>
                    </div>
                    <button type="button" onclick="removeAssessmentFile()" class="text-red-600 hover:text-red-700">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Assessment Details -->
              <div class="grid grid-cols-1 gap-4">
                <div>
                  <label for="assessmentTitle" class="block text-sm font-medium text-gray-700 mb-2">Assessment Title</label>
                  <input type="text" id="assessmentTitle" name="assessmentTitle"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                    placeholder="Enter assessment title (optional)">
                </div>
                <div>
                  <label for="assessmentDescription" class="block text-sm font-medium text-gray-700 mb-2">Assessment Instructions</label>
                  <textarea id="assessmentDescription" name="assessmentDescription" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                    placeholder="Provide instructions for the assessment"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="assessmentDueDate" class="block text-sm font-medium text-gray-700 mb-2">Assessment Due Date</label>
                    <input type="datetime-local" id="assessmentDueDate" name="assessmentDueDate"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
                  </div>
                  <div>
                    <label for="assessmentTotalPoints" class="block text-sm font-medium text-gray-700 mb-2">Total Points</label>
                    <input type="number" id="assessmentTotalPoints" name="assessmentTotalPoints" min="1" value="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                      placeholder="Enter total points">
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end space-x-3 pt-4 border-t">
            <button type="button" onclick="closeUploadModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
              Cancel
            </button>
            <button type="submit" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
              Upload Material
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Upload Progress Modal -->
  <div id="uploadProgressModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
      <div class="p-6">
        <div class="text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-tplearn-green mx-auto mb-4"></div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Uploading Material</h3>
          <p id="uploadStatus" class="text-sm text-gray-600 mb-4">Preparing upload...</p>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div id="uploadProgress" class="bg-tplearn-green h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
          </div>
          <p id="uploadPercent" class="text-sm text-gray-600 mt-2">0%</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Material Modal -->
  <div id="editMaterialModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">Edit Material</h3>
        <button onclick="closeEditMaterialModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <form id="editMaterialForm" onsubmit="submitEditMaterial(event)">
          <!-- Current File Info -->
          <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <h4 class="font-semibold text-gray-900 mb-3">Current File</h4>
            <div class="flex items-center space-x-3">
              <svg id="editFileIcon" class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
              </svg>
              <div>
                <p id="editCurrentFileName" class="font-medium text-gray-900">Algebra Fundamentals.pdf</p>
                <p id="editCurrentFileSize" class="text-sm text-gray-600">1.5 MB � Uploaded May 20, 2025</p>
              </div>
            </div>
          </div>

          <!-- Replace File (Optional) -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Replace File (Optional)</label>
            <div id="editUploadArea" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-gray-400 transition-colors">
              <input type="file" id="editFileInput" name="newFile" class="hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.mp4,.avi,.mov,.wmv,.jpg,.jpeg,.png,.gif,.bmp,.svg" onchange="handleEditFileSelect(event)">
              <div id="editUploadAreaContent">
                <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <p class="text-sm text-gray-600">Click to upload a replacement file</p>
                <p class="text-xs text-gray-500">Or keep the current file</p>
              </div>
            </div>
            <div id="editFilePreview" class="hidden mt-3 p-3 bg-blue-50 rounded-lg">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                  </svg>
                  <div>
                    <p id="editNewFileName" class="font-medium text-gray-900"></p>
                    <p id="editNewFileSize" class="text-sm text-gray-600"></p>
                  </div>
                </div>
                <button type="button" onclick="removeEditFile()" class="text-red-600 hover:text-red-700">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Material Details -->
          <div class="space-y-4 mb-6">
            <div>
              <label for="editMaterialTitle" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
              <input type="text" id="editMaterialTitle" name="title" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                value="Algebra Fundamentals.pdf">
            </div>

            <div>
              <label for="editMaterialDescription" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea id="editMaterialDescription" name="description" rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                placeholder="Describe the material and its purpose">Core concepts of algebra including equations and functions</textarea>
            </div>
          </div>

          <!-- Assignment Details (shown only for assignments) -->
          <div id="editAssignmentDetails" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
              <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
              </svg>
              Assignment Details
            </h4>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="editDueDate" class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                <input type="datetime-local" id="editDueDate" name="due_date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              </div>
              
              <div>
                <label for="editTotalPoints" class="block text-sm font-medium text-gray-700 mb-2">Total Points</label>
                <input type="number" id="editTotalPoints" name="total_points" min="1" max="1000" value="100"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              </div>
            </div>
            
            <div class="mt-4">
              <label class="flex items-center">
                <input type="checkbox" id="editAllowLateSubmissions" name="allow_late_submissions" 
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Allow late submissions</span>
              </label>
            </div>
            
            <div class="mt-4 p-3 bg-blue-100 rounded-lg">
              <p class="text-sm text-blue-800">
                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                Students will be able to submit work for this assignment, and you can grade submissions through the grading interface.
              </p>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end space-x-3 pt-4 border-t">
            <button type="button" onclick="closeEditMaterialModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
              Cancel
            </button>
            <button type="submit" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
              Update Material
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Material Modal -->
  <div id="viewMaterialModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" onclick="closeViewMaterialModal()">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[95vh] overflow-y-auto" onclick="event.stopPropagation()">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 id="viewMaterialTitle" class="text-lg font-semibold text-gray-900">Material Details</h3>
          <p id="viewMaterialType" class="text-sm text-gray-600 mt-1">Document</p>
        </div>
        <button onclick="closeViewMaterialModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Material Info -->
          <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">File Information</h4>
              <div class="space-y-3">
                <div class="flex items-center space-x-3">
                  <svg id="viewFileIcon" class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                  </svg>
                  <div>
                    <p id="viewFileName" class="font-medium text-gray-900">Algebra Fundamentals.pdf</p>
                    <p id="viewFileSize" class="text-sm text-gray-600">1.5 MB</p>
                  </div>
                </div>
                <div class="text-sm space-y-2">
                  <div>
                    <span class="text-gray-600">Uploaded:</span>
                    <p id="viewUploadDate" class="font-medium">May 20, 2025, 02:30 PM</p>
                  </div>
                  <div>
                    <span class="text-gray-600">By:</span>
                    <p id="viewUploadedBy" class="font-medium">Maria Santos</p>
                  </div>
                  <div>
                    <span class="text-gray-600">Status:</span>
                    <span id="viewStatus" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Published</span>
                  </div>
                </div>
              </div>
            </div>



            <div class="space-y-2">
              <button onclick="viewMaterialFileFromModal()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Full Size
              </button>
              <button onclick="downloadMaterialFromView()" class="w-full bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download
              </button>
              <button onclick="editMaterialFromView()" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700 flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                </svg>
                Edit
              </button>
            </div>
          </div>

          <!-- Preview Area -->
          <div class="lg:col-span-2">
            <div class="bg-gray-50 rounded-lg p-4 h-full">
              <h4 class="font-semibold text-gray-900 mb-3">Preview</h4>
              <div id="viewPreviewContent" class="h-96 bg-white rounded border-2 border-dashed border-gray-300 flex items-center justify-center">
                <div class="text-center">
                  <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                  </svg>
                  <h3 class="text-lg font-medium text-gray-900 mb-2">File Preview</h3>
                  <p id="viewDescription" class="text-gray-600 mb-4">Core concepts of algebra including equations and functions</p>
                  <button onclick="downloadMaterialFromView()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                    Download to View
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Attendance Management Modal -->
  <div id="attendanceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Session Attendance</h3>
          <p id="attendanceSessionTitle" class="text-sm text-gray-600 mt-1">Introduction to Calculus Session</p>
        </div>
        <button onclick="closeAttendanceModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <!-- Session Info -->
          <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Session Details</h4>
              <div class="space-y-3 text-sm">
                <div>
                  <span class="text-gray-600">Date:</span>
                  <p id="attendanceSessionDate" class="font-medium">May 15, 2025</p>
                </div>
                <div>
                  <span class="text-gray-600">Time:</span>
                  <p id="attendanceSessionTime" class="font-medium">09:00 AM - 10:30 AM</p>
                </div>
                <div>
                  <span class="text-gray-600">Duration:</span>
                  <p id="attendanceSessionDuration" class="font-medium">90 minutes</p>
                </div>
                <div>
                  <span class="text-gray-600">Total Enrolled:</span>
                  <p id="attendanceTotalStudents" class="font-medium">15 students</p>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Attendance Summary</h4>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-600">Present:</span>
                  <span id="attendancePresent" class="font-medium text-green-600">12</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Absent:</span>
                  <span id="attendanceAbsent" class="font-medium text-red-600">2</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Late:</span>
                  <span id="attendanceLate" class="font-medium text-yellow-600">1</span>
                </div>
                <div class="flex justify-between pt-2 border-t">
                  <span class="text-gray-600">Attendance Rate:</span>
                  <span id="attendanceRate" class="font-medium text-gray-900">86.7%</span>
                </div>
              </div>
            </div>

            <div class="space-y-2">
              <button onclick="exportAttendance()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Export Report
              </button>
              <button onclick="markAllPresent()" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                Mark All Present
              </button>
              <button onclick="sendAbsenteeNotices()" class="w-full bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700">
                Send Absentee Notices
              </button>
            </div>
          </div>

          <!-- Student Attendance List -->
          <div class="lg:col-span-3">
            <div class="bg-white rounded-lg border">
              <div class="px-6 py-4 border-b">
                <div class="flex items-center justify-between">
                  <h4 class="font-semibold text-gray-900">Student Attendance</h4>
                  <div class="flex items-center space-x-2">
                    <div class="relative">
                      <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                      </svg>
                      <input type="text" id="attendanceSearch" placeholder="Search students..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-48 text-sm">
                    </div>
                    <div class="relative">
                      <select id="attendanceFilter" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent text-sm">
                        <option value="all">All Students</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                      </select>
                      <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>

              <div class="max-h-96 overflow-y-auto">
                <div id="attendanceList" class="divide-y divide-gray-200">
                  <!-- Student attendance entries will be populated here -->
                  <div class="attendance-item p-4 hover:bg-gray-50" data-status="present">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                          JD
                        </div>
                        <div>
                          <p class="font-medium text-gray-900">John Doe</p>
                          <p class="text-sm text-gray-600">john.doe@email.com</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                          <span>Joined: 09:00 AM</span>
                        </div>
                        <div class="flex items-center space-x-2">
                          <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Present</span>
                          <select class="attendance-status-select px-2 py-1 border border-gray-300 rounded text-xs" onchange="updateAttendanceStatus('john-doe', this.value)">
                            <option value="present" selected>Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="attendance-item p-4 hover:bg-gray-50" data-status="present">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                          JS
                        </div>
                        <div>
                          <p class="font-medium text-gray-900">Jane Smith</p>
                          <p class="text-sm text-gray-600">jane.smith@email.com</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                          <span>Joined: 09:00 AM</span>
                        </div>
                        <div class="flex items-center space-x-2">
                          <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Present</span>
                          <select class="attendance-status-select px-2 py-1 border border-gray-300 rounded text-xs" onchange="updateAttendanceStatus('jane-smith', this.value)">
                            <option value="present" selected>Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="attendance-item p-4 hover:bg-gray-50" data-status="late">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                          MB
                        </div>
                        <div>
                          <p class="font-medium text-gray-900">Mike Brown</p>
                          <p class="text-sm text-gray-600">mike.brown@email.com</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                          <span>Joined: 09:15 AM</span>
                        </div>
                        <div class="flex items-center space-x-2">
                          <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">Late</span>
                          <select class="attendance-status-select px-2 py-1 border border-gray-300 rounded text-xs" onchange="updateAttendanceStatus('mike-brown', this.value)">
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late" selected>Late</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="attendance-item p-4 hover:bg-gray-50" data-status="absent">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                          SD
                        </div>
                        <div>
                          <p class="font-medium text-gray-900">Sarah Davis</p>
                          <p class="text-sm text-gray-600">sarah.davis@email.com</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                          <span>Did not join</span>
                        </div>
                        <div class="flex items-center space-x-2">
                          <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Absent</span>
                          <select class="attendance-status-select px-2 py-1 border border-gray-300 rounded text-xs" onchange="updateAttendanceStatus('sarah-davis', this.value)">
                            <option value="present">Present</option>
                            <option value="absent" selected>Absent</option>
                            <option value="late">Late</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="attendance-item p-4 hover:bg-gray-50" data-status="absent">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-indigo-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                          TW
                        </div>
                        <div>
                          <p class="font-medium text-gray-900">Tom Wilson</p>
                          <p class="text-sm text-gray-600">tom.wilson@email.com</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                          <span>Did not join</span>
                        </div>
                        <div class="flex items-center space-x-2">
                          <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Absent</span>
                          <select class="attendance-status-select px-2 py-1 border border-gray-300 rounded text-xs" onchange="updateAttendanceStatus('tom-wilson', this.value)">
                            <option value="present">Present</option>
                            <option value="absent" selected>Absent</option>
                            <option value="late">Late</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Save Actions -->
        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
          <button onclick="closeAttendanceModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
            Close
          </button>
          <button onclick="saveAttendance()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
            Save Attendance
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">Confirm Deletion</h3>
        <button onclick="closeDeleteConfirmModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <div class="flex items-center mb-4">
          <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
          </div>
          <div>
            <h4 class="font-semibold text-gray-900">Delete Material</h4>
            <p id="deleteItemName" class="text-sm text-gray-600 mt-1">This action cannot be undone</p>
          </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
          <div class="flex">
            <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <div class="text-sm">
              <p class="font-medium text-yellow-800">Warning</p>
              <div id="deleteWarningContent" class="text-yellow-700 mt-1">
                <p>Deleting this material will:</p>
                <ul class="list-disc list-inside mt-2 space-y-1">
                  <li>Remove access for all students</li>
                  <li>Delete associated submissions and grades</li>
                  <li>Remove from gradebook calculations</li>
                  <li>Cannot be recovered after deletion</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="mb-4">
          <label class="flex items-center">
            <input type="checkbox" id="deleteConfirmCheckbox" class="text-red-600 focus:ring-red-500">
            <span class="ml-2 text-sm text-gray-700">I understand this action cannot be undone</span>
          </label>
        </div>

        <div class="flex justify-end space-x-3">
          <button onclick="closeDeleteConfirmModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
            Cancel
          </button>
          <button id="confirmDeleteButton" onclick="confirmDelete()" disabled
            class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
            Delete Permanently
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Submissions Modal -->
  <div id="viewSubmissionsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Assignment Submissions</h3>
          <p id="submissionsAssignmentInfo" class="text-sm text-gray-600 mt-1">Week 3 Assignment: Quadratic Equations</p>
        </div>
        <button onclick="closeViewSubmissionsModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Assignment Overview -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            <div class="flex-1">
              <div class="grid grid-cols-4 gap-4">
                <div>
                  <p class="text-sm font-medium text-blue-800">Due Date</p>
                  <p id="submissionsDueDate" class="text-sm text-blue-700">May 25, 2023, 11:59 PM</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-blue-800">Total Points</p>
                  <p id="submissionsTotalPoints" class="text-sm text-blue-700">100 points</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-blue-800">Submissions</p>
                  <p id="submissionsCount" class="text-sm text-blue-700">6 of 8 submitted</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-blue-800">Average Score</p>
                  <p id="submissionsAverage" class="text-sm text-blue-700">85.5 / 100</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Submissions Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submission</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody id="submissionsTableBody" class="bg-white divide-y divide-gray-200">
              <!-- Sample submissions data -->
              <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                      <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                        <span class="text-sm font-medium text-green-800">JD</span>
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900">Juan Dela Cruz</div>
                      <div class="text-sm text-gray-500">juan.delacruz@email.com</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">juan_quadratic_equations.pdf</div>
                  <div class="text-sm text-gray-500">Submitted May 20, 2023, 02:30 PM</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                    Submitted
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div class="text-lg font-bold text-green-600">95</div>
                  <div class="text-xs text-gray-500">/ 100 points</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                  <button onclick="gradeSubmission('juan_dela_cruz')" class="text-blue-600 hover:text-blue-900">Grade</button>
                  <button onclick="viewSubmission('juan_quadratic_equations.pdf')" class="text-blue-600 hover:text-blue-900">View</button>
                </td>
              </tr>
              <!-- More submission rows would be dynamically loaded -->
            </tbody>
          </table>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center pt-6 border-t">
          <div class="flex space-x-3">
            <button onclick="downloadAllSubmissions()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
              Download All Submissions
            </button>
            <button onclick="exportGrades()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
              Export Grades
            </button>
          </div>
          <button onclick="closeViewSubmissionsModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Assessment Submissions Modal -->
  <div id="viewAssessmentSubmissionsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Assessment Submissions</h3>
          <p id="assessmentSubmissionsInfo" class="text-sm text-gray-600 mt-1">Assessment: Loading...</p>
        </div>
        <button onclick="closeViewAssessmentSubmissionsModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Assessment Overview -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            <div class="flex-1">
              <div class="grid grid-cols-4 gap-4">
                <div>
                  <p class="text-sm font-medium text-blue-800">Due Date</p>
                  <p id="assessmentSubmissionsDueDate" class="text-sm text-blue-700">No due date</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-blue-800">Total Points</p>
                  <p id="assessmentTotalPoints" class="text-sm text-blue-700">100 points</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-blue-800">Submissions</p>
                  <p id="assessmentSubmissionsCount" class="text-sm text-blue-700">1 submissions</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-blue-800">Average Score</p>
                  <p id="assessmentAverage" class="text-sm text-blue-700">0 / 100</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Assessment Submissions Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submission</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody id="assessmentSubmissionsTableBody" class="bg-white divide-y divide-gray-200">
              <!-- Loading state -->
              <tr>
                <td colspan="5" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center">
                    <svg class="animate-spin h-8 w-8 text-gray-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-500">Loading assessment submissions...</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center pt-6 border-t">
          <div class="flex space-x-3">
            <button onclick="downloadAllAssessmentSubmissions()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
              Download All Submissions
            </button>
            <button onclick="exportAssessmentGrades()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
              Export Grades
            </button>
          </div>
          <button onclick="closeViewAssessmentSubmissionsModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Individual Student Grading Modal -->
  <div id="individualGradingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Grade Assignment</h3>
          <p id="gradingStudentInfo" class="text-sm text-gray-600 mt-1">Juan Dela Cruz - Week 3 Assignment: Quadratic Equations</p>
        </div>
        <button onclick="closeIndividualGradingModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Assignment Details -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            <div class="flex-1">
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <p class="text-sm font-medium text-blue-800">Max Score</p>
                  <p id="gradingMaxScore" class="text-sm text-blue-700">100 points</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-blue-800">Submitted</p>
                  <p id="gradingSubmissionDate" class="text-sm text-blue-700">May 20, 2023, 02:30 PM</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-blue-800">Status</p>
                  <p id="gradingSubmissionStatus" class="text-sm text-blue-700">On Time</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Student Submission -->
        <div class="mb-6">
          <h4 class="text-lg font-semibold text-gray-900 mb-3">Student Submission</h4>
          <div class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-center space-x-3 mb-3">
              <svg class="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
              </svg>
              <div>
                <p id="gradingFileName" class="font-medium text-gray-900">juan_quadratic_equations.pdf</p>
                <p id="gradingFileSize" class="text-sm text-gray-600">2.3 MB</p>
              </div>
            </div>
            <div class="flex space-x-3">
              <button onclick="previewSubmission()" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                Preview
              </button>
              <button onclick="downloadIndividualSubmission()" class="text-green-600 hover:text-green-700 text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download
              </button>
            </div>
          </div>
        </div>

        <!-- Grading Form -->
        <form id="individualGradingForm" onsubmit="submitIndividualGrade(event)">
          <div class="grid grid-cols-2 gap-6 mb-6">
            <!-- Score Input -->
            <div>
              <label for="studentScore" class="block text-sm font-medium text-gray-700 mb-2">Score</label>
              <div class="relative">
                <input type="number" id="studentScore" name="score" min="0" max="100" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="0">
                <span class="absolute right-3 top-2 text-gray-500">/ 100</span>
              </div>
              <!-- Quick Grade Buttons -->
              <div class="flex space-x-2 mt-2">
                <button type="button" onclick="setQuickGrade(100)" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">100%</button>
                <button type="button" onclick="setQuickGrade(95)" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">95%</button>
                <button type="button" onclick="setQuickGrade(90)" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs hover:bg-yellow-200">90%</button>
                <button type="button" onclick="setQuickGrade(85)" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs hover:bg-yellow-200">85%</button>
                <button type="button" onclick="setQuickGrade(80)" class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs hover:bg-orange-200">80%</button>
                <button type="button" onclick="setQuickGrade(75)" class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs hover:bg-red-200">75%</button>
              </div>
            </div>

            <!-- Grade Letter Display -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Grade</label>
              <div class="h-10 bg-gray-50 border border-gray-300 rounded-lg flex items-center justify-center">
                <span id="gradeLetterDisplay" class="text-lg font-bold text-gray-600">-</span>
              </div>
              <p id="gradeDescription" class="text-xs text-gray-500 mt-1">Enter score to see grade</p>
            </div>
          </div>

          <!-- Feedback -->
          <div class="mb-6">
            <label for="studentFeedback" class="block text-sm font-medium text-gray-700 mb-2">Feedback</label>
            <textarea id="studentFeedback" name="feedback" rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Provide detailed feedback on the student's work..."></textarea>
            <!-- Feedback Templates -->
            <div class="mt-2">
              <p class="text-xs text-gray-600 mb-1">Quick feedback:</p>
              <div class="flex flex-wrap gap-1">
                <button type="button" onclick="addFeedbackTemplate('Excellent work! Shows strong understanding of the concepts.')" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">Excellent</button>
                <button type="button" onclick="addFeedbackTemplate('Good work, but could improve on problem-solving approach.')" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs hover:bg-yellow-200">Good</button>
                <button type="button" onclick="addFeedbackTemplate('Needs improvement. Please review the concepts and try again.')" class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs hover:bg-red-200">Needs Work</button>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-between items-center pt-4 border-t">
            <div class="flex space-x-3">
              <button type="button" onclick="saveAsDraft()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-200">
                Save as Draft
              </button>
            </div>
            <div class="flex space-x-3">
              <button type="button" onclick="closeIndividualGradingModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
                Cancel
              </button>
              <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
                Save Grade
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Individual Assessment Grading Modal -->
  <div id="individualAssessmentGradingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Grade Assessment</h3>
          <p id="assessmentGradingStudentInfo" class="text-sm text-gray-600 mt-1">Loading student and assessment details...</p>
        </div>
        <button onclick="closeIndividualAssessmentGradingModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Assessment Details -->
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
          <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-purple-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            <div class="flex-1">
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <p class="text-sm font-medium text-purple-800">Max Score</p>
                  <p id="assessmentGradingMaxScore" class="text-sm text-purple-700">100 points</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-purple-800">Submitted</p>
                  <p id="assessmentGradingSubmissionDate" class="text-sm text-purple-700">Loading...</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-purple-800">Status</p>
                  <p id="assessmentGradingSubmissionStatus" class="text-sm text-purple-700">Loading...</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Student Submission -->
        <div class="mb-6">
          <h4 class="text-lg font-semibold text-gray-900 mb-3">Assessment Submission</h4>
          <div class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-center space-x-3 mb-3">
              <svg class="w-8 h-8 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
              </svg>
              <div>
                <p id="assessmentGradingFileName" class="font-medium text-gray-900">Loading...</p>
                <p id="assessmentGradingFileSize" class="text-sm text-gray-600">Loading...</p>
              </div>
            </div>
            <div class="flex space-x-3">
              <button onclick="previewAssessmentSubmission()" class="text-purple-600 hover:text-purple-700 text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                Preview
              </button>
              <button onclick="downloadIndividualAssessmentSubmission()" class="text-green-600 hover:text-green-700 text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download
              </button>
            </div>
          </div>
        </div>

        <!-- Assessment Grading Form -->
        <form id="individualAssessmentGradingForm" onsubmit="submitIndividualAssessmentGrade(event)">
          <div class="grid grid-cols-2 gap-6 mb-6">
            <!-- Score Input -->
            <div>
              <label for="assessmentStudentScore" class="block text-sm font-medium text-gray-700 mb-2">Score</label>
              <div class="relative">
                <input type="number" id="assessmentStudentScore" name="score" min="0" max="100" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                  placeholder="0" onchange="updateAssessmentGradeDisplay(this.value)" oninput="updateAssessmentGradeDisplay(this.value)">
                <span class="absolute right-3 top-2 text-gray-500">/ 100</span>
              </div>
              <!-- Quick Grade Buttons -->
              <div class="flex space-x-2 mt-2">
                <button type="button" onclick="setQuickAssessmentGrade(100)" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">100%</button>
                <button type="button" onclick="setQuickAssessmentGrade(95)" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">95%</button>
                <button type="button" onclick="setQuickAssessmentGrade(90)" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs hover:bg-yellow-200">90%</button>
                <button type="button" onclick="setQuickAssessmentGrade(85)" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs hover:bg-yellow-200">85%</button>
                <button type="button" onclick="setQuickAssessmentGrade(80)" class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs hover:bg-orange-200">80%</button>
                <button type="button" onclick="setQuickAssessmentGrade(75)" class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs hover:bg-red-200">75%</button>
              </div>
            </div>

            <!-- Grade Letter Display -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Grade</label>
              <div class="h-10 bg-gray-50 border border-gray-300 rounded-lg flex items-center justify-center">
                <span id="assessmentGradeLetterDisplay" class="text-lg font-bold text-gray-600">-</span>
              </div>
              <p id="assessmentGradeDescription" class="text-xs text-gray-500 mt-1">Enter score to see grade</p>
            </div>
          </div>

          <!-- Feedback -->
          <div class="mb-6">
            <label for="assessmentStudentFeedback" class="block text-sm font-medium text-gray-700 mb-2">Feedback</label>
            <textarea id="assessmentStudentFeedback" name="feedback" rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
              placeholder="Provide detailed feedback on the student's assessment submission..."></textarea>
            <!-- Feedback Templates -->
            <div class="mt-2">
              <p class="text-xs text-gray-600 mb-1">Quick feedback:</p>
              <div class="flex flex-wrap gap-1">
                <button type="button" onclick="addAssessmentFeedbackTemplate('Outstanding performance! Demonstrates excellent understanding of all concepts.')" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">Outstanding</button>
                <button type="button" onclick="addAssessmentFeedbackTemplate('Solid work with good grasp of key concepts. Room for improvement in some areas.')" class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs hover:bg-yellow-200">Good</button>
                <button type="button" onclick="addAssessmentFeedbackTemplate('Shows basic understanding but needs more practice. Please review and resubmit if allowed.')" class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs hover:bg-red-200">Needs Work</button>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-between items-center pt-4 border-t">
            <div class="flex space-x-3">
              <button type="button" onclick="saveAssessmentAsDraft()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-200">
                Save as Draft
              </button>
            </div>
            <div class="flex space-x-3">
              <button type="button" onclick="closeIndividualAssessmentGradingModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
                Cancel
              </button>
              <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700">
                Save Grade
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- File Preview Modal -->
  <div id="filePreviewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[95vh] overflow-hidden flex flex-col">
      <div class="flex items-center justify-between p-6 border-b bg-gray-50">
        <div class="flex items-center space-x-4">
          <h3 id="previewFileName" class="text-lg font-semibold text-gray-900">File Preview</h3>
          <span id="previewFileType" class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"></span>
          <span id="previewFileSize" class="text-sm text-gray-500"></span>
        </div>
        <button onclick="closeFilePreviewModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      
      <div class="flex-1 overflow-hidden">
        <!-- Student Info Bar (for submissions) -->
        <div id="previewStudentInfo" class="hidden bg-blue-50 px-6 py-3 border-b">
          <div class="flex items-center justify-between">
            <div>
              <span class="font-medium text-blue-900" id="previewStudentName">Student Name</span>
              <span class="text-blue-600 text-sm ml-2" id="previewSubmissionDate">Submitted: Date</span>
            </div>
            <div class="flex items-center space-x-2">
              <span id="previewSubmissionStatus" class="px-2 py-1 text-xs font-medium rounded-full">Status</span>
              <span id="previewCurrentScore" class="text-sm text-blue-700"></span>
            </div>
          </div>
        </div>
        
        <!-- File Content Area -->
        <div class="h-full overflow-auto">
          <div id="previewContent" class="p-6 h-full">
            <div class="flex items-center justify-center h-full text-gray-500">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p>Loading file preview...</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Action Buttons -->
      <div class="border-t bg-gray-50 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <button id="previewDownloadBtn" onclick="downloadFileFromPreview()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>Download</span>
          </button>
          
          <button id="previewGradeBtn" onclick="gradeFromPreview()" class="hidden bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            <span>Grade</span>
          </button>
        </div>
        
        <button onclick="closeFilePreviewModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400">
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- Create Live Session Modal -->
  <div id="createLiveClassModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <h2 class="text-xl font-semibold text-gray-800">Create Live Session</h2>
        <button onclick="closeCreateLiveClassModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <form id="createLiveClassForm" onsubmit="submitCreateLiveClass(event)" class="p-6">
        <!-- Hidden fields for API -->
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="program_id" value="<?= $program_id ?>">
        
        <div class="space-y-4">
          <!-- Session Title -->
          <div>
            <label for="liveClassTitle" class="block text-sm font-medium text-gray-700 mb-1">Session Title</label>
            <input type="text" id="liveClassTitle" name="title" required 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="Enter session title">
          </div>

          <!-- Description -->
          <div>
            <label for="liveClassDescription" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
            <textarea id="liveClassDescription" name="description" rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      placeholder="Describe what will be covered in this live session"></textarea>
          </div>

          <!-- Date and Time -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="liveClassDate" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
              <input type="date" id="liveClassDate" name="scheduled_date" required 
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                     min="<?= date('Y-m-d') ?>">
            </div>
            <div>
              <label for="liveClassTime" class="block text-sm font-medium text-gray-700 mb-1">Time</label>
              <input type="time" id="liveClassTime" name="scheduled_time" required 
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
          </div>

          <!-- Duration and Participants -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="liveClassDuration" class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
              <select id="liveClassDuration" name="duration_minutes" 
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="30">30 minutes</option>
                <option value="45">45 minutes</option>
                <option value="60" selected>1 hour</option>
                <option value="90">1.5 hours</option>
                <option value="120">2 hours</option>
              </select>
            </div>
            <div>
              <label for="liveClassMaxParticipants" class="block text-sm font-medium text-gray-700 mb-1">Max Participants</label>
              <input type="number" id="liveClassMaxParticipants" name="max_participants" value="50" min="1" max="100"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
          <button type="button" onclick="closeCreateLiveClassModal()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Create Live Session
          </button>
        </div>

        <input type="hidden" name="program_id" value="<?= $program_id ?>">
      </form>
    </div>
  </div>

  <!-- Live Class Details Modal -->
  <div id="liveClassDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <h2 class="text-xl font-semibold text-gray-800" id="liveClassDetailsTitle">Live Class Details</h2>
        <button onclick="closeLiveClassDetailsModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6" id="liveClassDetailsContent">
        <!-- Content will be loaded dynamically -->
      </div>
    </div>
  </div>

  <script>
    // Global variables for Jitsi integration
    let jitsiAPI = null;
    let currentProgramId = <?php echo json_encode($program_id); ?>;
    let currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    let currentUserName = '<?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>';
    let currentUserEmail = '<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>';
    
    // =================================
    // TIMEZONE UTILITY FUNCTIONS
    // =================================
    
    /**
     * Get current date/time in Philippine Standard Time
     * @returns {Date} Current date in PST
     */
    function getCurrentPSTTime() {
      const now = new Date();
      // Convert to PST (UTC+8)
      const pstOffset = 8 * 60; // PST is UTC+8
      const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
      return new Date(utc + (pstOffset * 60000));
    }
    
    /**
     * Format time for consistent display in PST
     * @param {string} dateStr - Date string (Y-m-d)
     * @param {string} timeStr - Time string (H:i:s)
     * @returns {string} Formatted time in PST
     */
    function formatPSTTime(dateStr, timeStr) {
      const datetime = new Date(dateStr + 'T' + timeStr);
      return datetime.toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    }
    
    /**
     * Check if a meeting is currently live, upcoming, or past
     * @param {string} scheduledDate - Meeting date (Y-m-d) 
     * @param {string} scheduledTime - Meeting time (H:i:s)
     * @param {number} duration - Duration in minutes
     * @returns {object} Status object with is_live, is_upcoming, is_past
     */
    function getMeetingStatusJS(scheduledDate, scheduledTime, duration = 60) {
      const now = getCurrentPSTTime();
      const meetingStart = new Date(scheduledDate + 'T' + scheduledTime);
      const meetingEnd = new Date(meetingStart.getTime() + (duration * 60000));
      
      return {
        is_live: now >= meetingStart && now <= meetingEnd,
        is_upcoming: now < meetingStart,
        is_past: now > meetingEnd,
        current_time: now,
        start_time: meetingStart,
        end_time: meetingEnd
      };
    }
    
    /**
     * Add timezone indicator to time displays
     * @param {string} timeDisplay - Formatted time string
     * @returns {string} Time with PST indicator
     */
    function addTimezoneIndicator(timeDisplay) {
      return timeDisplay + ' PST';
    }
    
    // =================================
    // END TIMEZONE UTILITY FUNCTIONS
    // =================================
    
    // Custom Notification System
    function showNotification(message, type = 'info', duration = 5000) {
      const container = document.getElementById('notificationContainer');
      if (!container) return;

      // Create notification element
      const notification = document.createElement('div');
      const notificationId = 'notification-' + Date.now();
      notification.id = notificationId;
      
      // Define notification styles based on type
      const typeStyles = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
      };

      const iconStyles = {
        success: 'text-green-400',
        error: 'text-red-400', 
        warning: 'text-yellow-400',
        info: 'text-blue-400'
      };

      const icons = {
        success: `<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>`,
        error: `<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>`,
        warning: `<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>`,
        info: `<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>`
      };

      notification.className = `${typeStyles[type]} border rounded-lg shadow-lg p-4 min-w-80 max-w-md transform transition-all duration-300 ease-in-out translate-x-full opacity-0`;
      
      notification.innerHTML = `
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <svg class="w-5 h-5 ${iconStyles[type]}" fill="currentColor" viewBox="0 0 20 20">
              ${icons[type]}
            </svg>
          </div>
          <div class="ml-3 flex-1">
            <p class="text-sm font-medium leading-5">${message}</p>
          </div>
          <div class="ml-4 flex-shrink-0 flex">
            <button onclick="closeNotification('${notificationId}')" class="inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition ease-in-out duration-150">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
              </svg>
            </button>
          </div>
        </div>
      `;

      // Add to container
      container.appendChild(notification);

      // Animate in
      setTimeout(() => {
        notification.classList.remove('translate-x-full', 'opacity-0');
        notification.classList.add('translate-x-0', 'opacity-100');
      }, 100);

      // Auto-remove after duration
      if (duration > 0) {
        setTimeout(() => {
          closeNotification(notificationId);
        }, duration);
      }

      return notificationId;
    }

    function closeNotification(notificationId) {
      const notification = document.getElementById(notificationId);
      if (notification) {
        notification.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 300);
      }
    }

    // Success notification
    function showSuccess(message, duration = 5000) {
      return showNotification(message, 'success', duration);
    }

    // Error notification  
    function showError(message, duration = 8000) {
      return showNotification(message, 'error', duration);
    }

    // Warning notification
    function showWarning(message, duration = 6000) {
      return showNotification(message, 'warning', duration);
    }

    // Info notification
    function showInfo(message, duration = 5000) {
      return showNotification(message, 'info', duration);
    }

    // Filter content by type
    function filterContent(type) {
      console.log('Filtering by type:', type);
      const items = document.querySelectorAll('.content-item');
      const tabs = document.querySelectorAll('.filter-tab');

      // Update tab styles
      tabs.forEach(tab => {
        tab.classList.remove('border-tplearn-green', 'text-tplearn-green');
        tab.classList.add('border-transparent', 'text-gray-500');
      });
      event.target.classList.remove('border-transparent', 'text-gray-500');
      event.target.classList.add('border-tplearn-green', 'text-tplearn-green');

      // Show/hide content sections
      const sections = document.querySelectorAll('.content-section');
      sections.forEach(section => {
        const sectionType = section.getAttribute('data-type');
        if (type === 'all') {
          section.style.display = 'block';
        } else if (type === 'live-classes') {
          section.style.display = sectionType === 'live-classes' ? 'block' : 'none';
        } else {
          section.style.display = sectionType === 'live-classes' ? 'none' : 'block';
        }
      });

      // Filter items based on type
      let visibleCount = 0;
      items.forEach(item => {
        const itemType = item.getAttribute('data-type');
        let shouldShow = false;

        if (type === 'all') {
          shouldShow = true;
        } else if (type === 'documents') {
          // Documents include: document, video, image, slides, other
          shouldShow = ['document', 'video', 'image', 'slides', 'other'].includes(itemType);
        } else if (type === 'assignments') {
          // Assignments include only assignment type
          shouldShow = itemType === 'assignment';
        } else if (type === 'live-classes') {
          // Live classes
          shouldShow = itemType === 'live-classes';
        }

        item.style.display = shouldShow ? 'block' : 'none';
        if (shouldShow) visibleCount++;
      });
      
      console.log(`Showing ${visibleCount} items for filter: ${type}`);
    }

    // View item
    function viewItem(id, type) {
      console.log('Opening view modal for material ID:', id);
      
      // Validate input
      if (!id || isNaN(id)) {
        console.error('Invalid material ID:', id);
        showError('Invalid material ID provided');
        return;
      }
      
      // Check if DOM is ready
      if (!document.getElementById('viewMaterialModal')) {
        console.error('View material modal not found in DOM');
        showError('Page not fully loaded. Please refresh and try again.');
        return;
      }
      
      // If modal is already open, close it first
      if (isViewModalOpen) {
        console.log('Modal already open, closing first...');
        closeViewMaterialModal();
        // Add small delay before opening new modal
        setTimeout(() => openViewModal(id, type), 100);
        return;
      }
      
      openViewModal(id, type);
    }
    
    function openViewModal(id, type) {
      console.log('Actually opening modal for ID:', id);
      isViewModalOpen = true;
      
      // Ensure modal exists and is properly hidden first
      const modal = document.getElementById('viewMaterialModal');
      if (!modal) {
        console.error('View material modal not found!');
        showError('Modal not available. Please refresh the page.');
        return;
      }
      
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      
      // Reset to loading state with null checks
      const titleEl = document.getElementById('viewMaterialTitle');
      const typeEl = document.getElementById('viewMaterialType');
      const fileNameEl = document.getElementById('viewFileName');
      const fileSizeEl = document.getElementById('viewFileSize');
      const uploadDateEl = document.getElementById('viewUploadDate');
      const uploadedByEl = document.getElementById('viewUploadedBy');
      const statusEl = document.getElementById('viewStatus');
      const descriptionEl = document.getElementById('viewDescription');
      
      if (titleEl) titleEl.textContent = 'Loading...';
      if (typeEl) typeEl.textContent = type || 'Document';
      if (fileNameEl) fileNameEl.textContent = 'Loading...';
      if (fileSizeEl) fileSizeEl.textContent = '';
      if (uploadDateEl) uploadDateEl.textContent = '';
      if (uploadedByEl) uploadedByEl.textContent = '';
      if (statusEl) {
        statusEl.textContent = 'Loading...';
        statusEl.className = 'bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium';
      }
      if (descriptionEl) descriptionEl.textContent = 'Loading material details...';

      // Reset preview content to loading state
      const previewContent = document.getElementById('viewPreviewContent');
      if (previewContent) {
        previewContent.innerHTML = `
          <div class="h-96 bg-white rounded border-2 border-dashed border-gray-300 flex items-center justify-center">
            <div class="text-center">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-tplearn-green mx-auto mb-4"></div>
              <p class="text-gray-600">Loading preview...</p>
            </div>
          </div>
        `;
      } else {
        console.error('Preview content element not found!');
      }

      // Store material ID for actions
      modal.setAttribute('data-material-id', id);

      // Show modal with a slight delay to ensure proper state reset
      setTimeout(() => {
        if (modal && document.getElementById('viewMaterialTitle')) {
          modal.classList.remove('hidden');
          modal.classList.add('flex');
          console.log('Modal should be visible now');
        } else {
          console.error('Modal or required elements not ready');
          showError('Unable to show modal. Please refresh the page.');
        }
      }, 10);

      // Load material data from API
      fetch(`../../api/get-program-material.php?material_id=${id}`, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      })
        .then(response => {
          console.log('API Response status:', response.status);
          if (!response.ok) {
            return response.text().then(text => {
              console.error('API Error Response:', text);
              throw new Error(`HTTP ${response.status}: ${response.statusText} - ${text}`);
            });
          }
          return response.json();
        })
        .then(data => {
          console.log('API Response data:', data);
          if (data.success) {
            const material = data.material;
            
            // Update modal content with real data (with null checks)
            const titleEl = document.getElementById('viewMaterialTitle');
            const typeEl = document.getElementById('viewMaterialType');
            const fileNameEl = document.getElementById('viewFileName');
            const fileSizeEl = document.getElementById('viewFileSize');
            const uploadDateEl = document.getElementById('viewUploadDate');
            const uploadedByEl = document.getElementById('viewUploadedBy');
            const statusEl = document.getElementById('viewStatus');
            const descriptionEl = document.getElementById('viewDescription');
            
            if (titleEl) titleEl.textContent = material.title || 'Material Details';
            if (typeEl) typeEl.textContent = material.material_type || type || 'Document';
            
            // Handle case where material might not have a file
            if (material.file) {
              if (fileNameEl) fileNameEl.textContent = material.file.original_filename || material.file.filename || 'Unknown file';
              if (fileSizeEl) fileSizeEl.textContent = material.file.file_size_formatted || formatFileSize(material.file.file_size || 0);
            } else {
              if (fileNameEl) fileNameEl.textContent = 'No file attached';
              if (fileSizeEl) fileSizeEl.textContent = 'N/A';
            }
            
            // Format upload date
            if (material.file && material.file.created_at && uploadDateEl) {
              const date = new Date(material.file.created_at);
              uploadDateEl.textContent = date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              });
            } else if (uploadDateEl) {
              uploadDateEl.textContent = 'N/A';
            }
            
            if (uploadedByEl) uploadedByEl.textContent = (material.uploader && material.uploader.name) || 'Unknown';
            if (statusEl) {
              statusEl.textContent = 'Active';
              statusEl.className = 'bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium';
            }
            if (descriptionEl) descriptionEl.textContent = material.description || 'No description available.';
            
            // Load file preview
            const previewContent = document.getElementById('viewPreviewContent');
            if (material.file && previewContent) {
              try {
                loadMaterialPreview(material);
              } catch (error) {
                console.error('Error loading material preview:', error);
                previewContent.innerHTML = `
                  <div class="h-96 bg-gray-50 rounded border-2 border-dashed border-gray-300 flex items-center justify-center">
                    <div class="text-center">
                      <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                      </svg>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">Preview Error</h3>
                      <p class="text-gray-600">Unable to load preview for this file.</p>
                    </div>
                  </div>
                `;
              }
            } else if (previewContent) {
              // Show no file available message
              previewContent.innerHTML = `
                <div class="h-96 bg-gray-50 rounded border-2 border-dashed border-gray-300 flex items-center justify-center">
                  <div class="text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No File Attached</h3>
                    <p class="text-gray-600">This material doesn't have a file attachment.</p>
                  </div>
                </div>
              `;
            }
            
          } else {
            console.error('API Error:', data.error || data.message);
            showError(data.error || data.message || 'Failed to load material details');
            const titleEl = document.getElementById('viewMaterialTitle');
            if (titleEl) titleEl.textContent = 'Error Loading Material';
          }
        })
        .catch(error => {
          console.error('Fetch Error:', error);
          showError(`Failed to load material details: ${error.message}`);
          const titleEl = document.getElementById('viewMaterialTitle');
          if (titleEl) titleEl.textContent = 'Error Loading Material';
        });
    }

    // Edit item (tutor function)
    function editItem(id) {
      // Reset form to clear any previous data
      document.getElementById('editMaterialForm').reset();
      
      // Show loading state with spinner
      document.getElementById('editCurrentFileName').innerHTML = '<span class="flex items-center"><svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Loading...</span>';
      document.getElementById('editCurrentFileSize').textContent = '';
      document.getElementById('editMaterialTitle').value = '';
      document.getElementById('editMaterialDescription').value = '';

      // Store current material ID for form submission
      document.getElementById('editMaterialForm').setAttribute('data-material-id', id);

      // Show modal
      document.getElementById('editMaterialModal').classList.remove('hidden');
      document.getElementById('editMaterialModal').classList.add('flex');

      // Load material data from API
      fetch(`../../api/get-program-material.php?material_id=${id}`, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      })
        .then(response => {
          console.log('Edit API Response status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then(data => {
          console.log('Edit API Response data:', data);
          if (data.success) {
            const material = data.material;
            
            // Populate form fields
            document.getElementById('editMaterialTitle').value = material.title;
            document.getElementById('editMaterialDescription').value = material.description || '';
            
            // Update current file info
            document.getElementById('editCurrentFileName').textContent = material.file.original_filename || material.file.filename || 'No file';
            document.getElementById('editCurrentFileSize').textContent = `${material.file.file_size_formatted} � Uploaded ${material.file.upload_date}`;
            
            // Handle assignment-specific fields
            if (material.material_type === 'assignment' && material.assignment) {
              // Show assignment details section
              document.getElementById('editAssignmentDetails').classList.remove('hidden');
              
              // Populate assignment fields
              if (material.assignment.due_date) {
                // Convert datetime to date input format
                const dueDate = new Date(material.assignment.due_date);
                const dateStr = dueDate.toISOString().slice(0, 16); // Format: YYYY-MM-DDTHH:MM
                document.getElementById('editDueDate').value = dateStr;
              }
              document.getElementById('editTotalPoints').value = material.assignment.total_points || 100;
              document.getElementById('editAllowLateSubmissions').checked = material.assignment.allow_late_submissions || false;
            } else {
              // Hide assignment details section for non-assignment materials
              document.getElementById('editAssignmentDetails').classList.add('hidden');
            }
            
          } else {
            console.error('Failed to load material data:', data.error);
            document.getElementById('editCurrentFileName').textContent = 'Error loading material';
            document.getElementById('editCurrentFileSize').textContent = 'Please try again';
            showError('Failed to load material data: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error loading material data:', error);
          document.getElementById('editCurrentFileName').textContent = 'Error loading material';
          document.getElementById('editCurrentFileSize').textContent = 'Please try again';
          showError('Error loading material data. Please try again.');
        });
    }

    // Delete item (tutor function)
    function deleteItem(id) {
      // Update modal content with generic confirmation message
      document.getElementById('deleteItemName').textContent = `Material will be permanently deleted`;

      // Standard warning content
      let warningContent = '<p>Deleting this material will:</p><ul class="list-disc list-inside mt-2 space-y-1">';
      warningContent += '<li>Remove access for all students</li>';
      warningContent += '<li>Delete any associated submissions or data</li>';
      warningContent += '<li>Cannot be recovered after deletion</li>';
      warningContent += '</ul>';

      document.getElementById('deleteWarningContent').innerHTML = warningContent;

      // Store material ID for deletion
      document.getElementById('deleteConfirmModal').setAttribute('data-material-id', id);

      // Reset checkbox
      document.getElementById('deleteConfirmCheckbox').checked = false;
      document.getElementById('confirmDeleteButton').disabled = true;

      // Show modal
      document.getElementById('deleteConfirmModal').classList.remove('hidden');
      document.getElementById('deleteConfirmModal').classList.add('flex');
    }

    // Close delete confirmation modal
    function closeDeleteConfirmModal() {
      document.getElementById('deleteConfirmModal').classList.add('hidden');
      document.getElementById('deleteConfirmModal').classList.remove('flex');
    }

    // Confirm delete
    function confirmDelete() {
      const materialId = document.getElementById('deleteConfirmModal').getAttribute('data-material-id');

      // Validate material ID
      if (!materialId) {
        console.error('Material ID not found!');
        showError('Material ID is missing. Please close and reopen the delete dialog.');
        return;
      }

      // Show deletion progress
      closeDeleteConfirmModal();

      const deleteHtml = `
        <div class="fixed top-4 right-4 bg-white rounded-lg shadow-lg border p-4 z-50" id="deleteProgress">
          <div class="flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-red-600"></div>
            <div>
              <p class="font-medium text-gray-900">Deleting Material...</p>
              <p class="text-sm text-gray-600" id="deleteStatus">Removing access...</p>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', deleteHtml);

      // Create form data for deletion
      const formData = new FormData();
      formData.append('material_id', materialId);
      formData.append('program_id', <?= $program_id ?>);
      formData.append('tutor_id', <?= $tutor_user_id ?>);

      // Update status
      setTimeout(() => {
        document.getElementById('deleteStatus').textContent = 'Cleaning up data...';
      }, 800);

      // Debug: Log delete data being sent
      console.log('Deleting material with data:', {
        material_id: materialId,
        program_id: <?= $program_id ?>,
        tutor_id: <?= $tutor_user_id ?>
      });

      // Submit delete request to API
      fetch('../../api/delete-program-material.php', {
        method: 'POST',
        credentials: 'include',
        body: formData
      })
      .then(response => {
        console.log('Delete response status:', response.status);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Delete response data:', data);
        document.getElementById('deleteStatus').textContent = 'Finalizing deletion...';
        
        setTimeout(() => {
          document.getElementById('deleteProgress').remove();
          
          if (data.success) {
            showSuccess('Material deleted successfully! The material has been permanently removed and is no longer accessible to students.');
            
            // Refresh the page to show updated data
            setTimeout(() => {
              location.reload();
            }, 2000);
          } else {
            showError('Error deleting material: ' + (data.message || data.error || 'Unknown error'));
          }
        }, 800);
      })
      .catch(error => {
        console.error('Delete Error:', error);
        document.getElementById('deleteProgress').remove();
        showError(`Error deleting material: ${error.message}. Please try again.`);
      });
    }

    // View attendance (tutor function)
    function viewAttendance(id) {
      // Populate attendance modal with session data
      const sessionData = {
        'intro-calculus': {
          title: 'Introduction to Calculus Session',
          date: 'May 15, 2025',
          time: '09:00 AM - 10:30 AM',
          duration: '90 minutes',
          totalStudents: 15,
          present: 12,
          absent: 2,
          late: 1
        }
      };

      const session = sessionData[id] || sessionData['intro-calculus'];
      const attendanceRate = ((session.present + session.late) / session.totalStudents * 100).toFixed(1);

      // Populate modal
      document.getElementById('attendanceSessionTitle').textContent = session.title;
      document.getElementById('attendanceSessionDate').textContent = session.date;
      document.getElementById('attendanceSessionTime').textContent = session.time;
      document.getElementById('attendanceSessionDuration').textContent = session.duration;
      document.getElementById('attendanceTotalStudents').textContent = `${session.totalStudents} students`;
      document.getElementById('attendancePresent').textContent = session.present;
      document.getElementById('attendanceAbsent').textContent = session.absent;
      document.getElementById('attendanceLate').textContent = session.late;
      document.getElementById('attendanceRate').textContent = `${attendanceRate}%`;

      // Store session ID for actions
      document.getElementById('attendanceModal').setAttribute('data-session-id', id);

      // Show modal
      document.getElementById('attendanceModal').classList.remove('hidden');
      document.getElementById('attendanceModal').classList.add('flex');
    }

    // Close attendance modal
    function closeAttendanceModal() {
      document.getElementById('attendanceModal').classList.add('hidden');
      document.getElementById('attendanceModal').classList.remove('flex');
    }

    // Update attendance status
    function updateAttendanceStatus(studentId, status) {
      const studentItem = document.querySelector(`[data-status]`);

      // Update the visual status badge
      const statusBadge = event.target.parentElement.querySelector('span');
      const statusClasses = {
        'present': 'bg-green-100 text-green-800',
        'absent': 'bg-red-100 text-red-800',
        'late': 'bg-yellow-100 text-yellow-800'
      };

      statusBadge.className = `${statusClasses[status]} px-2 py-1 rounded text-xs font-medium`;
      statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);

      // Update the parent item's data-status
      event.target.closest('.attendance-item').setAttribute('data-status', status);

      // Recalculate attendance summary
      updateAttendanceSummary();
    }

    // Update attendance summary
    function updateAttendanceSummary() {
      const items = document.querySelectorAll('.attendance-item');
      let present = 0,
        absent = 0,
        late = 0;

      items.forEach(item => {
        const status = item.getAttribute('data-status');
        if (status === 'present') present++;
        else if (status === 'absent') absent++;
        else if (status === 'late') late++;
      });

      const total = items.length;
      const attendanceRate = ((present + late) / total * 100).toFixed(1);

      document.getElementById('attendancePresent').textContent = present;
      document.getElementById('attendanceAbsent').textContent = absent;
      document.getElementById('attendanceLate').textContent = late;
      document.getElementById('attendanceRate').textContent = `${attendanceRate}%`;
    }

    // Mark all present
    function markAllPresent() {
      if (confirm('Mark all students as present for this session?')) {
        const selects = document.querySelectorAll('.attendance-status-select');
        selects.forEach(select => {
          select.value = 'present';
          updateAttendanceStatus(null, 'present');
        });
        showSuccess('All students marked as present.');
      }
    }

    // Export attendance
    function exportAttendance() {
      const sessionId = document.getElementById('attendanceModal').getAttribute('data-session-id');

      // Show export progress
      const exportHtml = `
        <div class="fixed top-4 right-4 bg-white rounded-lg shadow-lg border p-4 z-50" id="exportAttendanceProgress">
          <div class="flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            <div>
              <p class="font-medium text-gray-900">Exporting Attendance...</p>
              <p class="text-sm text-gray-600" id="exportAttendanceStatus">Generating report...</p>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', exportHtml);

      setTimeout(() => {
        document.getElementById('exportAttendanceStatus').textContent = 'Formatting data...';
      }, 800);

      setTimeout(() => {
        document.getElementById('exportAttendanceProgress').remove();
        alert(`Attendance report exported successfully!\n\nFile: ${sessionId}_attendance_report.xlsx\n\nReport includes:\n- Student attendance status\n- Join times\n- Session statistics\n- Attendance summary`);
      }, 1600);
    }

    // Send absentee notices
    function sendAbsenteeNotices() {
      const absentStudents = document.querySelectorAll('.attendance-item[data-status="absent"]');

      if (absentStudents.length === 0) {
        showInfo('No absent students to notify.');
        return;
      }

      if (confirm(`Send absence notifications to ${absentStudents.length} students?`)) {
        const noticeHtml = `
          <div class="fixed top-4 right-4 bg-white rounded-lg shadow-lg border p-4 z-50" id="noticeProgress">
            <div class="flex items-center space-x-3">
              <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-yellow-600"></div>
              <div>
                <p class="font-medium text-gray-900">Sending Notices...</p>
                <p class="text-sm text-gray-600" id="noticeStatus">Preparing notifications...</p>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', noticeHtml);

        setTimeout(() => {
          document.getElementById('noticeStatus').textContent = 'Sending emails...';
        }, 800);

        setTimeout(() => {
          document.getElementById('noticeProgress').remove();
          alert(`Absence notifications sent successfully!\n\n${absentStudents.length} students notified via email.`);
        }, 1600);
      }
    }

    // Save attendance
    function saveAttendance() {
      const sessionId = document.getElementById('attendanceModal').getAttribute('data-session-id');

      // Show save progress
      const saveHtml = `
        <div class="fixed top-4 right-4 bg-white rounded-lg shadow-lg border p-4 z-50" id="saveAttendanceProgress">
          <div class="flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-tplearn-green"></div>
            <div>
              <p class="font-medium text-gray-900">Saving Attendance...</p>
              <p class="text-sm text-gray-600">Updating records...</p>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', saveHtml);

      setTimeout(() => {
        document.getElementById('saveAttendanceProgress').remove();
        showSuccess('Attendance saved successfully! All attendance records have been updated.');
        closeAttendanceModal();
      }, 1200);
    }

    // Grade assignment (tutor function)
    function gradeAssignment(id) {
      document.getElementById('gradingModal').classList.remove('hidden');
      document.getElementById('gradingModal').classList.add('flex');
    }

    // View submissions (tutor function)
    function viewSubmissions(id) {
      // Load assignment data
      loadAssignmentSubmissions(id);
      
      // Show submissions modal
      document.getElementById('viewSubmissionsModal').classList.remove('hidden');
      document.getElementById('viewSubmissionsModal').classList.add('flex');
    }

    // View assessment submissions (tutor function)
    function viewAssessment(assessmentId) {
      // Show assessment submissions modal
      document.getElementById('assessmentSubmissionsInfo').textContent = 'Loading assessment details...';
      document.getElementById('viewAssessmentSubmissionsModal').classList.remove('hidden');
      document.getElementById('viewAssessmentSubmissionsModal').classList.add('flex');
      
      // Load assessment submissions data
      loadAssessmentSubmissions(assessmentId);
    }

    // Close view assessment submissions modal
    function closeViewAssessmentSubmissionsModal() {
      document.getElementById('viewAssessmentSubmissionsModal').classList.add('hidden');
      document.getElementById('viewAssessmentSubmissionsModal').classList.remove('flex');
    }

    // Load assessment submissions data
    function loadAssessmentSubmissions(assessmentId) {
      // Reset to loading state
      document.getElementById('assessmentSubmissionsTableBody').innerHTML = `
        <tr>
          <td colspan="5" class="px-6 py-12 text-center">
            <div class="flex flex-col items-center">
              <svg class="animate-spin h-8 w-8 text-gray-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <p class="text-gray-500">Loading assessment submissions...</p>
            </div>
          </td>
        </tr>
      `;

      // Fetch assessment submissions from API
      fetch(`../../api/get-assessment-submissions.php?assessment_id=${assessmentId}`)
        .then(response => {
          console.log('Assessment submissions API response status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then(data => {
          console.log('Assessment submissions API data:', data);
          if (data.success) {
            loadAssessmentSubmissionsTableWithData(data.submissions, data.assessment);
          } else {
            console.error('API returned error:', data);
            showError(data.message || 'Failed to load assessment submissions');
            document.getElementById('assessmentSubmissionsTableBody').innerHTML = `
              <tr>
                <td colspan="5" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center">
                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-gray-500 text-lg font-medium">Failed to load submissions</p>
                    <p class="text-gray-400 text-sm">${data.message || 'Please try again later'}</p>
                  </div>
                </td>
              </tr>
            `;
          }
        })
        .catch(error => {
          console.error('Error loading assessment submissions:', error);
          const errorMessage = error.message || 'Unknown error';
          showError(`Network error loading assessment submissions: ${errorMessage}`);
          document.getElementById('assessmentSubmissionsTableBody').innerHTML = `
            <tr>
              <td colspan="5" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center">
                  <svg class="w-12 h-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                  </svg>
                  <p class="text-red-500 text-lg font-medium">Network Error</p>
                  <p class="text-gray-400 text-sm">${errorMessage}</p>
                  <p class="text-gray-400 text-xs mt-2">Check browser console for details</p>
                </div>
              </td>
            </tr>
          `;
        });
    }

    // Load assessment submissions table data with real data
    function loadAssessmentSubmissionsTableWithData(submissions, assessment) {
      // Update assessment info
      document.getElementById('assessmentSubmissionsInfo').textContent = assessment.title || 'Assessment Submissions';
      
      // Format due date similar to assignment format
      let dueDateText = 'No due date';
      if (assessment.due_date && assessment.due_date !== '0000-00-00 00:00:00' && assessment.due_date !== null) {
        try {
          const dueDate = new Date(assessment.due_date);
          if (!isNaN(dueDate.getTime())) {
            dueDateText = dueDate.toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric', 
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
              hour12: true
            });
          }
        } catch (e) {
          console.error('Error formatting due date:', e);
        }
      }
      
      // Update due date element
      const dueDateElement = document.getElementById('assessmentSubmissionsDueDate');
      if (dueDateElement) {
        dueDateElement.textContent = dueDateText;
        console.log('Successfully updated due date to:', dueDateText);
      } else {
        console.error('Could not find assessmentSubmissionsDueDate element');
      }
      
      // Filter to only show actual submissions (students who have submitted)
      const actualSubmissions = submissions.filter(submission => 
        submission.submitted_at && submission.submitted_at !== null && submission.submitted_at !== ''
      );
      
      document.getElementById('assessmentTotalPoints').textContent = `${assessment.total_points || 100} points`;
      document.getElementById('assessmentSubmissionsCount').textContent = `${actualSubmissions.length} submission${actualSubmissions.length !== 1 ? 's' : ''}`;
      
      // Calculate average score from actual submissions only
      const gradedSubmissions = actualSubmissions.filter(sub => sub.score !== null && sub.score !== undefined);
      const totalScore = gradedSubmissions.reduce((sum, sub) => sum + (parseFloat(sub.score) || 0), 0);
      const averageScore = gradedSubmissions.length > 0 ? (totalScore / gradedSubmissions.length).toFixed(1) : 0;
      document.getElementById('assessmentAverage').textContent = `${averageScore} / ${assessment.total_points || 100}`;

      const tableBody = document.getElementById('assessmentSubmissionsTableBody');
      
      console.log('Total submissions from API:', submissions.length);
      console.log('Actual submissions (filtered):', actualSubmissions.length);
      
      if (actualSubmissions.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">No submissions yet</td></tr>';
        return;
      }

      // Generate table rows for actual submissions only
      const rowsHtml = actualSubmissions.map(submission => {
        const fullName = submission.student_name || 'Unknown Student';
        const initials = fullName.split(' ').map(n => n[0] || '').slice(0, 2).join('').toUpperCase();
        const email = submission.student_email || 'N/A';
        
        // Format submission date
        const submissionDate = submission.submitted_at ? 
          new Date(submission.submitted_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          }) : 'Not submitted';

        // Determine status and score display
        let statusHtml, scoreHtml;
        if (submission.score !== null && submission.score !== undefined) {
          const score = parseFloat(submission.score);
          const maxScore = assessment.total_points || 100;
          const percentage = (score / maxScore) * 100;
          
          statusHtml = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Graded</span>`;
          scoreHtml = `
            <div class="text-lg font-bold text-green-600">${score}</div>
            <div class="text-xs text-gray-500">/ ${maxScore} points</div>
          `;
        } else if (submission.submitted_at) {
          // Check if this is a late submission
          if (submission.status === 'late_submission') {
            statusHtml = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">Late Submission</span>`;
          } else {
            statusHtml = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Submitted</span>`;
          }
          scoreHtml = `
            <div class="text-sm text-gray-500">Pending</div>
            <div class="text-xs text-gray-400">Not graded</div>
          `;
        } else {
          statusHtml = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Not Submitted</span>`;
          scoreHtml = `
            <div class="text-sm text-gray-500">-</div>
            <div class="text-xs text-gray-400">No submission</div>
          `;
        }

        // Submission file info
        const submissionInfo = submission.submitted_at ? 
          (submission.file_name || 'Assessment submission') : 'No file';

        return `
          <tr data-submission-id="${submission.id || submission.student_id}">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="flex-shrink-0 h-10 w-10">
                  <div class="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                    <span class="text-sm font-medium text-purple-800">${initials}</span>
                  </div>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-900">${fullName}</div>
                  <div class="text-sm text-gray-500">${email}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${submissionInfo}</div>
              <div class="text-sm text-gray-500">${submissionDate}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">${statusHtml}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${scoreHtml}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
              ${submission.submitted_at ? 
                `<button onclick="gradeAssessmentSubmission('${submission.id || submission.student_id}')" class="text-blue-600 hover:text-blue-900">Grade</button>
                 ${submission.file_id ? `<button onclick="viewAssessmentSubmissionWithData('${submission.file_id}', ${JSON.stringify(submission).replace(/"/g, '&quot;')})" class="text-blue-600 hover:text-blue-900">View</button>` : '<span class="text-gray-400">No file</span>'}` :
                `<span class="text-gray-400">No actions</span>`
              }
            </td>
          </tr>
        `;
      }).join('');

      tableBody.innerHTML = rowsHtml;
    }

    // Download all assessment submissions
    function downloadAllAssessmentSubmissions() {
      showInfo('Preparing assessment submissions download...');
      // Implementation would depend on API endpoint
    }

    // Export assessment grades
    function exportAssessmentGrades() {
      showInfo('Exporting assessment grades...');
      // Implementation would depend on API endpoint
    }

    // Grade assessment submission
    function gradeAssessmentSubmission(submissionId) {
      // Load assessment submission data from API
      loadAssessmentGradingData(submissionId);
      
      // Show individual assessment grading modal
      document.getElementById('individualAssessmentGradingModal').classList.remove('hidden');
      document.getElementById('individualAssessmentGradingModal').classList.add('flex');
    }

    // Load assessment grading data from API
    async function loadAssessmentGradingData(attemptId) {
      try {
        const response = await fetch(`../../api/get-assessment-attempt-details.php?attempt_id=${attemptId}`);
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const response_data = await response.json();
        
        if (!response_data.success) {
          throw new Error(response_data.message || 'Failed to load assessment submission data');
        }
        
        const submission = response_data.data;
        
        // Update modal with submission data
        document.getElementById('assessmentGradingStudentInfo').textContent = `${submission.student_name} - ${submission.assessment_title}`;
        document.getElementById('assessmentGradingMaxScore').textContent = `${submission.max_score} points`;
        document.getElementById('assessmentGradingSubmissionDate').textContent = submission.submitted_date_formatted || 'Not submitted';
        document.getElementById('assessmentGradingSubmissionStatus').textContent = submission.is_late ? 'Late Submission' : 'On Time';
        document.getElementById('assessmentGradingSubmissionStatus').className = submission.is_late ? 'text-sm text-orange-700 font-medium' : 'text-sm text-green-700';
        document.getElementById('assessmentGradingFileName').textContent = submission.file?.original_filename || 'No file submitted';
        document.getElementById('assessmentGradingFileSize').textContent = submission.file?.file_size ? formatFileSize(submission.file.file_size) : '';

        // Store attempt ID and file ID for form submission
        document.getElementById('individualAssessmentGradingForm').setAttribute('data-attempt-id', attemptId);
        document.getElementById('individualAssessmentGradingForm').setAttribute('data-file-id', submission.file?.id || '');
        
        // If already graded, populate the form
        if (submission.score !== null) {
          document.getElementById('assessmentStudentScore').value = submission.score;
          document.getElementById('assessmentStudentFeedback').value = submission.comments || '';
          updateAssessmentGradeDisplay(submission.score);
        } else {
          // Reset form for new grading
          document.getElementById('individualAssessmentGradingForm').reset();
          document.getElementById('assessmentGradeLetterDisplay').textContent = '-';
          document.getElementById('assessmentGradeDescription').textContent = 'Enter score to see grade';
        }
        
      } catch (error) {
        console.error('Error loading assessment submission data:', error);
        showError('Error loading assessment submission data. Please try again.');
        closeIndividualAssessmentGradingModal();
      }
    }

    // Close individual assessment grading modal
    function closeIndividualAssessmentGradingModal() {
      document.getElementById('individualAssessmentGradingModal').classList.add('hidden');
      document.getElementById('individualAssessmentGradingModal').classList.remove('flex');
    }

    // Set quick assessment grade
    function setQuickAssessmentGrade(score) {
      document.getElementById('assessmentStudentScore').value = score;
      updateAssessmentGradeDisplay(score);
    }

    // Update assessment grade display based on score
    function updateAssessmentGradeDisplay(score) {
      const gradeDisplay = document.getElementById('assessmentGradeLetterDisplay');
      const gradeDescription = document.getElementById('assessmentGradeDescription');
      
      let letter, description, color;
      
      if (score >= 97) {
        letter = 'A+'; description = 'Excellent'; color = 'text-green-600';
      } else if (score >= 93) {
        letter = 'A'; description = 'Excellent'; color = 'text-green-600';
      } else if (score >= 90) {
        letter = 'A-'; description = 'Very Good'; color = 'text-green-600';
      } else if (score >= 87) {
        letter = 'B+'; description = 'Good'; color = 'text-blue-600';
      } else if (score >= 83) {
        letter = 'B'; description = 'Good'; color = 'text-blue-600';
      } else if (score >= 80) {
        letter = 'B-'; description = 'Satisfactory'; color = 'text-blue-600';
      } else if (score >= 77) {
        letter = 'C+'; description = 'Fair'; color = 'text-yellow-600';
      } else if (score >= 73) {
        letter = 'C'; description = 'Fair'; color = 'text-yellow-600';
      } else if (score >= 70) {
        letter = 'C-'; description = 'Below Average'; color = 'text-yellow-600';
      } else if (score >= 67) {
        letter = 'D+'; description = 'Poor'; color = 'text-red-600';
      } else if (score >= 63) {
        letter = 'D'; description = 'Poor'; color = 'text-red-600';
      } else if (score >= 60) {
        letter = 'D-'; description = 'Poor'; color = 'text-red-600';
      } else {
        letter = 'F'; description = 'Failing'; color = 'text-red-600';
      }
      
      gradeDisplay.textContent = letter;
      gradeDisplay.className = `text-lg font-bold ${color}`;
      gradeDescription.textContent = description;
    }

    // Add assessment feedback template
    function addAssessmentFeedbackTemplate(template) {
      const feedbackTextarea = document.getElementById('assessmentStudentFeedback');
      const currentText = feedbackTextarea.value.trim();
      feedbackTextarea.value = currentText ? `${currentText}\n\n${template}` : template;
    }

    // Save assessment as draft
    function saveAssessmentAsDraft() {
      showInfo('Assessment draft saved successfully!');
    }

    // Submit individual assessment grade
    async function submitIndividualAssessmentGrade(event) {
      event.preventDefault();
      
      const score = document.getElementById('assessmentStudentScore').value;
      const feedback = document.getElementById('assessmentStudentFeedback').value;
      const attemptId = document.getElementById('individualAssessmentGradingForm').getAttribute('data-attempt-id');
      
      if (!score) {
        showError('Please enter a score for the student.');
        return;
      }
      
      if (score < 0 || score > 100) {
        showError('Score must be between 0 and 100.');
        return;
      }
      
      if (!attemptId) {
        showError('Assessment attempt ID not found. Please try again.');
        return;
      }
      
      try {
        // Show loading state
        const submitButton = document.querySelector('#individualAssessmentGradingModal button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.textContent = 'Submitting...';
        submitButton.disabled = true;
        
        const formData = new FormData();
        formData.append('attempt_id', attemptId);
        formData.append('score', parseFloat(score));
        formData.append('comments', feedback);
        
        const response = await fetch('../../api/grade-assessment-attempt.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Failed to submit grade');
        }
        
        // Show success message
        showSuccess(`Assessment grade submitted successfully! Score: ${score}/100 (${result.data.grade_letter})`);
        
        // Close modal
        closeIndividualAssessmentGradingModal();
        
        // Update the assessment submissions table to reflect the new grade
        updateAssessmentSubmissionInTable(attemptId, score, result.data.grade_letter);
        
        // Reset button state
        submitButton.textContent = originalText;
        submitButton.disabled = false;
        
      } catch (error) {
        console.error('Error submitting assessment grade:', error);
        showError(`Failed to submit assessment grade: ${error.message}`);
        
        // Reset button state
        const submitButton = document.querySelector('#individualAssessmentGradingModal button[type="submit"]');
        if (submitButton) {
          submitButton.textContent = 'Save Grade';
          submitButton.disabled = false;
        }
      }
    }

    // Update assessment submission in table after grading
    function updateAssessmentSubmissionInTable(attemptId, score, gradeLetter) {
      // Find the row with this attempt and update it
      const row = document.querySelector(`[data-submission-id="${attemptId}"]`);
      if (row) {
        // Update status
        const statusCell = row.querySelector('td:nth-child(3)');
        if (statusCell) {
          statusCell.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Graded</span>';
        }
        
        // Update score
        const scoreCell = row.querySelector('td:nth-child(4)');
        if (scoreCell) {
          scoreCell.innerHTML = `
            <div class="text-lg font-bold text-green-600">${score}</div>
            <div class="text-xs text-gray-500">/ 100 points</div>
          `;
        }
        
        // Update actions
        const actionsCell = row.querySelector('td:nth-child(5)');
        if (actionsCell) {
          // Get file ID from the grading form if available
          const fileId = document.getElementById('individualAssessmentGradingForm')?.getAttribute('data-file-id');
          const viewButton = fileId ? `<button onclick="viewAssessmentSubmission('${fileId}')" class="text-blue-600 hover:text-blue-900">View</button>` : '<span class="text-gray-400">No file</span>';
          
          actionsCell.innerHTML = `
            <button onclick="gradeAssessmentSubmission('${attemptId}')" class="text-blue-600 hover:text-blue-900">Edit Grade</button>
            ${viewButton}
          `;
        }
      }
    }

    // Preview assessment submission
    function previewAssessmentSubmission() {
      const form = document.getElementById('individualAssessmentGradingForm');
      const fileId = form.getAttribute('data-file-id');
      
      if (!fileId) {
        showError('No file available for preview');
        return;
      }
      
      // Get the filename from the modal to determine file type
      const filename = document.getElementById('assessmentGradingFileName').textContent || '';
      const extension = filename.split('.').pop().toLowerCase();
      
      console.log('Preview file:', filename, 'Extension:', extension, 'File ID:', fileId);
      
      // Create the preview URL
      const previewUrl = `../../api/serve-submission-file.php?file_id=${fileId}&action=view`;
      
      // For PDFs and images, open in new tab - browsers can handle these natively
      if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg'].includes(extension)) {
        const newWindow = window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        if (!newWindow) {
          showError('Please allow popups to preview files');
        } else {
          showSuccess('Opening file preview in new tab');
        }
      } else {
        // For other file types, create an iframe modal for better control
        showSimplePreviewModal(previewUrl, filename);
      }
    }
    
    // Simple preview modal for non-PDF files
    function showSimplePreviewModal(url, filename) {
      // Create modal HTML
      const modalHTML = `
        <div id="simplePreviewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white rounded-lg shadow-xl w-11/12 h-5/6 flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
              <h3 class="text-lg font-semibold">${filename}</h3>
              <button onclick="closeSimplePreviewModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            <div class="flex-1 p-4">
              <iframe src="${url}" class="w-full h-full border rounded" frameborder="0"></iframe>
            </div>
          </div>
        </div>`;
      
      // Add to page
      document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    function closeSimplePreviewModal() {
      const modal = document.getElementById('simplePreviewModal');
      if (modal) {
        modal.remove();
      }
    }

    // Download individual assessment submission
    function downloadIndividualAssessmentSubmission() {
      const form = document.getElementById('individualAssessmentGradingForm');
      const fileId = form ? form.getAttribute('data-file-id') : null;
      
      console.log('Downloading assessment submission, file ID:', fileId);
      
      if (!fileId || fileId === 'null' || fileId === 'undefined') {
        showError('No file available for download. Please refresh and try again.');
        return;
      }
      
      const downloadUrl = `../../api/serve-submission-file.php?file_id=${fileId}&action=download`;
      console.log('Assessment download URL:', downloadUrl);
      
      try {
        // Test API availability first
        fetch(downloadUrl, { 
          method: 'HEAD',
          credentials: 'include'
        })
        .then(response => {
          if (response.ok) {
            // Create download link and trigger download
            const downloadLink = document.createElement('a');
            downloadLink.href = downloadUrl;
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            
            showSuccess('Assessment submission download started!');
          } else {
            console.error('Assessment download failed:', response.status);
            showError(`Failed to download assessment submission (Status: ${response.status})`);
          }
        })
        .catch(error => {
          console.error('Assessment download error:', error);
          showError('Failed to download assessment submission. Please try again.');
        });
      } catch (error) {
        console.error('Unexpected assessment download error:', error);
        showError('An unexpected error occurred while downloading.');
      }
    }

    // Helper function to format file size
    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // View assessment submission
    function viewAssessmentSubmission(fileId, studentData = null) {
      console.log('Assessment - File ID:', fileId, 'Student data:', studentData);
      
      // Get the filename from student data to determine file type
      const filename = studentData?.file_name || studentData?.filename || 'document.pdf';
      const extension = filename.split('.').pop().toLowerCase();
      
      console.log('Preview file:', filename, 'Extension:', extension, 'File ID:', fileId);
      
      // Create the preview URL
      const previewUrl = `../../api/serve-submission-file.php?file_id=${fileId}&action=view`;
      
      // For PDFs and images, open in new tab - browsers can handle these natively
      if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg'].includes(extension)) {
        const newWindow = window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        if (!newWindow) {
          showError('Please allow popups to preview files');
        } else {
          showSuccess('Opening file preview in new tab');
        }
      } else {
        // For other file types, create an iframe modal for better control
        showSimplePreviewModal(previewUrl, filename);
      }
    }

    function viewAssessmentSubmissionWithData(fileId, studentDataStr) {
      try {
        console.log('Assessment - Raw student data string:', studentDataStr);
        const studentData = JSON.parse(studentDataStr.replace(/&quot;/g, '"'));
        console.log('Assessment - Parsed student data:', studentData);
        console.log('Assessment - File ID:', fileId);
        
        // Get the filename from student data to determine file type
        const filename = studentData.file_name || studentData.filename || 'unknown.pdf';
        const extension = filename.split('.').pop().toLowerCase();
        
        console.log('Preview file:', filename, 'Extension:', extension, 'File ID:', fileId);
        
        // Create the preview URL
        const previewUrl = `../../api/serve-submission-file.php?file_id=${fileId}&action=view`;
        
        // For PDFs and images, open in new tab - browsers can handle these natively
        if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg'].includes(extension)) {
          const newWindow = window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
          if (!newWindow) {
            showError('Please allow popups to preview files');
          } else {
            showSuccess('Opening file preview in new tab');
          }
        } else {
          // For other file types, create an iframe modal for better control
          showSimplePreviewModal(previewUrl, filename);
        }
      } catch (error) {
        console.error('Error parsing student data:', error);
        // Fallback - just try to preview with file ID
        const previewUrl = `../../api/serve-submission-file.php?file_id=${fileId}&action=view`;
        const newWindow = window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        if (!newWindow) {
          showError('Please allow popups to preview files');
        }
      }
    }

    // Close view submissions modal
    function closeViewSubmissionsModal() {
      document.getElementById('viewSubmissionsModal').classList.add('hidden');
      document.getElementById('viewSubmissionsModal').classList.remove('flex');
    }

    // Load assignment submissions data
    function loadAssignmentSubmissions(materialId) {
      // Set loading state
      document.getElementById('submissionsAssignmentInfo').textContent = 'Loading assignment details...';
      document.getElementById('submissionsDueDate').textContent = 'Loading...';
      document.getElementById('submissionsTotalPoints').textContent = 'Loading...';
      document.getElementById('submissionsCount').textContent = 'Loading...';
      document.getElementById('submissionsAverage').textContent = 'Loading...';
      
      // Clear table
      document.getElementById('submissionsTableBody').innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading submissions...</td></tr>';
      
      // Fetch real data from API
      fetch(`../../api/get-assignment-submissions.php?material_id=${materialId}`)
        .then(response => {
          console.log('API Response status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          console.log('API Response data:', data);
          if (data.success) {
            // Update assignment info
            document.getElementById('submissionsAssignmentInfo').textContent = data.assignment.title;
            document.getElementById('submissionsDueDate').textContent = data.assignment.due_date_formatted;
            document.getElementById('submissionsTotalPoints').textContent = `${data.assignment.max_score} points`;
            document.getElementById('submissionsCount').textContent = `${data.statistics.total_submissions} submissions`;
            document.getElementById('submissionsAverage').textContent = `${data.statistics.average_score} / ${data.assignment.max_score}`;
            
            // Load submissions table
            loadSubmissionsTableWithData(data.submissions, data.assignment.max_score);
          } else {
            throw new Error(data.error || 'Failed to load submissions');
          }
        })
        .catch(error => {
          console.error('Error loading submissions:', error);
          document.getElementById('submissionsAssignmentInfo').textContent = 'Error loading assignment';
          document.getElementById('submissionsDueDate').textContent = 'Error';
          document.getElementById('submissionsTotalPoints').textContent = 'Error';
          document.getElementById('submissionsCount').textContent = 'Error';
          document.getElementById('submissionsAverage').textContent = 'Error';
          document.getElementById('submissionsTableBody').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-600">Error loading submissions. Please try again.</td></tr>';
        });
    }

    // Load submissions table data with real data
    function loadSubmissionsTableWithData(submissions, maxScore) {
      const tableBody = document.getElementById('submissionsTableBody');
      
      // Clear existing rows
      tableBody.innerHTML = '';
      
      if (!submissions || submissions.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">No submissions yet</td></tr>';
        return;
      }
      
      // Generate table rows from real data
      submissions.forEach(submission => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        
        // Student info
        const studentCell = document.createElement('td');
        studentCell.className = 'px-6 py-4 whitespace-nowrap';
        const initials = submission.student_name.split(' ').map(n => n[0]).join('').toUpperCase();
        studentCell.innerHTML = `
          <div class="flex items-center">
            <div class="flex-shrink-0 h-10 w-10">
              <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center">
                <span class="text-sm font-medium text-white">${initials}</span>
              </div>
            </div>
            <div class="ml-4">
              <div class="text-sm font-medium text-gray-900">${submission.student_name}</div>
              <div class="text-sm text-gray-500">${submission.email}</div>
            </div>
          </div>
        `;
        
        // Submission info
        const submissionCell = document.createElement('td');
        submissionCell.className = 'px-6 py-4 whitespace-nowrap';
        submissionCell.innerHTML = `
          <div class="text-sm text-gray-900">${submission.file_name || 'No file'}</div>
          <div class="text-sm text-gray-500">Submitted ${submission.submitted_date_formatted}</div>
        `;
        
        // Status
        const statusCell = document.createElement('td');
        statusCell.className = 'px-6 py-4 whitespace-nowrap';
        let statusClass = 'bg-yellow-100 text-yellow-800';
        let statusText = submission.status || 'Submitted';
        
        if (submission.grade !== null) {
          statusClass = 'bg-green-100 text-green-800';
          statusText = 'Graded';
        }
        
        statusCell.innerHTML = `<span class="${statusClass} px-2 inline-flex text-xs leading-5 font-semibold rounded-full">${statusText}</span>`;
        
        // Score
        const scoreCell = document.createElement('td');
        scoreCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-900';
        if (submission.grade !== null) {
          scoreCell.innerHTML = `
            <div class="text-lg font-bold text-green-600">${submission.grade}</div>
            <div class="text-xs text-gray-500">/ ${maxScore} points</div>
          `;
        } else {
          scoreCell.innerHTML = `
            <div class="text-gray-500">-</div>
          `;
        }
        
        // Actions
        const actionsCell = document.createElement('td');
        actionsCell.className = 'px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2';
        actionsCell.innerHTML = `
          <button onclick="gradeSubmission('${submission.submission_id}')" class="text-blue-600 hover:text-blue-900">Grade</button>
          ${submission.file_name ? `<button onclick="viewSubmissionWithData('${submission.file_id}', ${JSON.stringify(submission).replace(/"/g, '&quot;')})" class="text-blue-600 hover:text-blue-900">View</button>` : ''}
        `;
        
        row.appendChild(studentCell);
        row.appendChild(submissionCell);
        row.appendChild(statusCell);
        row.appendChild(scoreCell);
        row.appendChild(actionsCell);
        
        tableBody.appendChild(row);
      });
    }

    // Legacy function - now uses real data
    function loadSubmissionsTable(materialId) {
      // This now just calls the new function that loads real data
      loadAssignmentSubmissions(materialId);
    }

    // Grade individual submission
    function gradeSubmission(submissionId) {
      // Load student and assignment data from API
      loadStudentGradingData(submissionId);
      
      // Show individual grading modal
      document.getElementById('individualGradingModal').classList.remove('hidden');
      document.getElementById('individualGradingModal').classList.add('flex');
    }

    // Load student grading data from API
    async function loadStudentGradingData(submissionId) {
      try {
        const response = await fetch(`../../api/get-submission-details.php?submission_id=${submissionId}`);
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const response_data = await response.json();
        
        if (!response_data.success) {
          throw new Error(response_data.message || 'Failed to load submission data');
        }
        
        const submission = response_data.data;
        
        // Update modal with submission data
        document.getElementById('gradingStudentInfo').textContent = `${submission.student_name} - ${submission.material_title}`;
        document.getElementById('gradingMaxScore').textContent = `${submission.max_score} points`;
        document.getElementById('gradingSubmissionDate').textContent = submission.submitted_date_formatted;
        document.getElementById('gradingSubmissionStatus').textContent = submission.is_late ? 'Late' : 'On Time';
        
        // Update file information
        if (submission.file && submission.file.original_filename) {
          document.getElementById('gradingFileName').textContent = submission.file.original_filename;
          document.getElementById('gradingFileSize').textContent = formatFileSize(submission.file.file_size);
        } else {
          document.getElementById('gradingFileName').textContent = 'No file submitted';
          document.getElementById('gradingFileSize').textContent = '';
        }
        
        // Store submission ID and file ID for form submission and preview
        document.getElementById('individualGradingForm').setAttribute('data-submission-id', submissionId);
        document.getElementById('individualGradingForm').setAttribute('data-file-id', submission.file?.id || '');
        
        // If already graded, populate the form
        if (submission.score !== null) {
          document.getElementById('studentScore').value = submission.score;
          document.getElementById('studentFeedback').value = submission.feedback || '';
          updateGradeDisplay(submission.score);
        } else {
          // Reset form for new grading
          document.getElementById('individualGradingForm').reset();
          document.getElementById('gradeLetterDisplay').textContent = '-';
          document.getElementById('gradeDescription').textContent = 'Enter score to see grade';
        }
        
      } catch (error) {
        console.error('Error loading submission data:', error);
        alert('Error loading submission data. Please try again.');
        closeIndividualGradingModal();
      }
    }

    // Close individual grading modal
    function closeIndividualGradingModal() {
      document.getElementById('individualGradingModal').classList.add('hidden');
      document.getElementById('individualGradingModal').classList.remove('flex');
    }

    // Set quick grade
    function setQuickGrade(score) {
      document.getElementById('studentScore').value = score;
      updateGradeDisplay(score);
    }

    // Update grade display based on score
    function updateGradeDisplay(score) {
      const gradeDisplay = document.getElementById('gradeLetterDisplay');
      const gradeDescription = document.getElementById('gradeDescription');
      
      let letter, description, color;
      
      if (score >= 97) {
        letter = 'A+'; description = 'Excellent'; color = 'text-green-600';
      } else if (score >= 93) {
        letter = 'A'; description = 'Excellent'; color = 'text-green-600';
      } else if (score >= 90) {
        letter = 'A-'; description = 'Very Good'; color = 'text-green-600';
      } else if (score >= 87) {
        letter = 'B+'; description = 'Good'; color = 'text-blue-600';
      } else if (score >= 83) {
        letter = 'B'; description = 'Good'; color = 'text-blue-600';
      } else if (score >= 80) {
        letter = 'B-'; description = 'Satisfactory'; color = 'text-blue-600';
      } else if (score >= 77) {
        letter = 'C+'; description = 'Fair'; color = 'text-yellow-600';
      } else if (score >= 73) {
        letter = 'C'; description = 'Fair'; color = 'text-yellow-600';
      } else if (score >= 70) {
        letter = 'C-'; description = 'Below Average'; color = 'text-orange-600';
      } else if (score >= 60) {
        letter = 'D'; description = 'Poor'; color = 'text-red-600';
      } else {
        letter = 'F'; description = 'Failing'; color = 'text-red-600';
      }
      
      gradeDisplay.textContent = letter;
      gradeDisplay.className = `text-lg font-bold ${color}`;
      gradeDescription.textContent = description;
    }

    // Add feedback template
    function addFeedbackTemplate(template) {
      const feedbackTextarea = document.getElementById('studentFeedback');
      const currentFeedback = feedbackTextarea.value.trim();
      
      if (currentFeedback) {
        feedbackTextarea.value = currentFeedback + '\n\n' + template;
      } else {
        feedbackTextarea.value = template;
      }
    }

    // Save as draft
    function saveAsDraft() {
      const score = document.getElementById('studentScore').value;
      const feedback = document.getElementById('studentFeedback').value;
      
      if (!score && !feedback.trim()) {
        showWarning('Please enter a score or feedback before saving as draft.');
        return;
      }
      
      showSuccess('Grade saved as draft. You can return to complete it later.');
      closeIndividualGradingModal();
    }

    // Submit individual grade
    async function submitIndividualGrade(event) {
      event.preventDefault();
      
      const score = document.getElementById('studentScore').value;
      const feedback = document.getElementById('studentFeedback').value;
      const submissionId = document.getElementById('individualGradingForm').getAttribute('data-submission-id');
      
      if (!score) {
        showError('Please enter a score for the student.');
        return;
      }
      
      if (score < 0 || score > 100) {
        showError('Score must be between 0 and 100.');
        return;
      }
      
      if (!submissionId) {
        showError('Submission ID not found. Please try again.');
        return;
      }
      
      try {
        // Show loading state
        const submitButton = event.target.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.textContent = 'Submitting...';
        submitButton.disabled = true;
        
        const formData = new FormData();
        formData.append('submission_id', submissionId);
        formData.append('score', parseFloat(score));
        formData.append('feedback', feedback);
        
        const response = await fetch('../../api/grade-assignment-submission.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Failed to submit grade');
        }
        
        // Show success message
        showSuccess(`Grade submitted successfully! Score: ${score}/100 (${result.data.grade_letter})`);
        
        // Close modal
        closeIndividualGradingModal();
        
        // Update the submissions table to reflect the new grade
        updateSubmissionInTable(submissionId, score, result.data.grade_letter);
        
        // Reset button state
        submitButton.textContent = originalText;
        submitButton.disabled = false;
        
      } catch (error) {
        console.error('Error submitting grade:', error);
        showError(`Failed to submit grade: ${error.message}`);
        
        // Reset button state
        const submitButton = document.querySelector('#individualGradingModal button[type="submit"]');
        if (submitButton) {
          submitButton.textContent = 'Submit Grade';
          submitButton.disabled = false;
        }
      }
    }

    // Update submission in table after grading
    function updateSubmissionInTable(submissionId, score, gradeLetter) {
      // Find the row with this submission and update it
      const tableBody = document.getElementById('submissionsTableBody');
      const rows = tableBody.querySelectorAll('tr');
      
      rows.forEach(row => {
        const gradeButton = row.querySelector(`button[onclick*="${submissionId}"]`);
        if (gradeButton) {
          // Update status
          const statusCell = row.cells[2];
          statusCell.innerHTML = '<span class="bg-green-100 text-green-800 px-2 inline-flex text-xs leading-5 font-semibold rounded-full">Graded</span>';
          
          // Update score
          const scoreCell = row.cells[3];
          scoreCell.innerHTML = `
            <div class="text-lg font-bold text-green-600">${score}</div>
            <div class="text-xs text-gray-500">/ 100 points</div>
          `;
          
          // Update button text
          gradeButton.textContent = 'Re-grade';
        }
      });
    }

    // Preview submission
    function previewSubmission() {
      const form = document.getElementById('individualGradingForm');
      const fileId = form ? form.getAttribute('data-file-id') : null;
      
      if (!fileId) {
        showError('No file available for preview');
        return;
      }
      
      // Get the filename from the modal to determine file type
      const filename = document.getElementById('gradingFileName').textContent || '';
      const extension = filename.split('.').pop().toLowerCase();
      
      console.log('Preview file:', filename, 'Extension:', extension, 'File ID:', fileId);
      
      // Create the preview URL (use assignment-specific API)
      const previewUrl = `../../api/serve-assignment-file.php?file_id=${fileId}&action=view`;
      
      // For PDFs and images, open in new tab - browsers can handle these natively
      if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg'].includes(extension)) {
        const newWindow = window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        if (!newWindow) {
          showError('Please allow popups to preview files');
        } else {
          showSuccess('Opening file preview in new tab');
        }
      } else {
        // For other file types, create an iframe modal for better control
        showSimplePreviewModal(previewUrl, filename);
      }
    }

    // Download individual submission
    function downloadIndividualSubmission() {
      const form = document.getElementById('individualGradingForm');
      const fileId = form ? form.getAttribute('data-file-id') : null;
      
      console.log('Downloading assignment submission, file ID:', fileId);
      
      if (!fileId || fileId === 'null' || fileId === 'undefined') {
        showError('No file available for download. Please refresh and try again.');
        return;
      }
      
      const downloadUrl = `../../api/serve-assignment-file.php?file_id=${fileId}&action=download`;
      console.log('Assignment download URL:', downloadUrl);
      
      try {
        // Test API availability first
        fetch(downloadUrl, { 
          method: 'HEAD',
          credentials: 'include'
        })
        .then(response => {
          if (response.ok) {
            // Create download link and trigger download (use assignment-specific API)
            const downloadLink = document.createElement('a');
            downloadLink.href = downloadUrl;
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            
            showSuccess('Assignment submission download started!');
          } else {
            console.error('Assignment download failed:', response.status);
            showError(`Failed to download assignment submission (Status: ${response.status})`);
          }
        })
        .catch(error => {
          console.error('Assignment download error:', error);
          showError('Failed to download assignment submission. Please try again.');
        });
      } catch (error) {
        console.error('Unexpected assignment download error:', error);
        showError('An unexpected error occurred while downloading.');
      }
    }

    // Listen for score input changes to update grade display
    document.addEventListener('DOMContentLoaded', function() {
      const scoreInput = document.getElementById('studentScore');
      if (scoreInput) {
        scoreInput.addEventListener('input', function() {
          const score = parseInt(this.value);
          if (!isNaN(score) && score >= 0 && score <= 100) {
            updateGradeDisplay(score);
          } else {
            document.getElementById('gradeLetterDisplay').textContent = '-';
            document.getElementById('gradeDescription').textContent = 'Enter valid score (0-100)';
          }
        });
      }
    });

    // View assignment submission
    function viewSubmission(fileId, studentData = null) {
      console.log('Assignment - File ID:', fileId, 'Student data:', studentData);
      
      // Get the filename from student data to determine file type
      const filename = studentData?.file_name || studentData?.filename || 'document.pdf';
      const extension = filename.split('.').pop().toLowerCase();
      
      console.log('Preview file:', filename, 'Extension:', extension, 'File ID:', fileId);
      
      // Create the preview URL (use assignment-specific API)
      const previewUrl = `../../api/serve-assignment-file.php?file_id=${fileId}&action=view`;
      
      // For PDFs and images, open in new tab - browsers can handle these natively
      if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg'].includes(extension)) {
        const newWindow = window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        if (!newWindow) {
          showError('Please allow popups to preview files');
        } else {
          showSuccess('Opening file preview in new tab');
        }
      } else {
        // For other file types, create an iframe modal for better control
        showSimplePreviewModal(previewUrl, filename);
      }
    }

    function viewSubmissionWithData(fileId, studentDataStr) {
      try {
        console.log('Assignment - Raw student data string:', studentDataStr);
        const studentData = JSON.parse(studentDataStr.replace(/&quot;/g, '"'));
        console.log('Assignment - Parsed student data:', studentData);
        console.log('Assignment - File ID:', fileId);
        
        // Get the filename from student data to determine file type
        const filename = studentData.file_name || studentData.filename || 'document.pdf';
        const extension = filename.split('.').pop().toLowerCase();
        
        console.log('Preview file:', filename, 'Extension:', extension, 'File ID:', fileId);
        
        // Create the preview URL (use assignment-specific API)
        const previewUrl = `../../api/serve-assignment-file.php?file_id=${fileId}&action=view`;
        
        // For PDFs and images, open in new tab - browsers can handle these natively
        if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg'].includes(extension)) {
          const newWindow = window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
          if (!newWindow) {
            showError('Please allow popups to preview files');
          } else {
            showSuccess('Opening file preview in new tab');
          }
        } else {
          // For other file types, create an iframe modal for better control
          showSimplePreviewModal(previewUrl, filename);
        }
      } catch (error) {
        console.error('Error parsing student data:', error);
        // Fallback - just try to preview with file ID (use assignment-specific API)
        const previewUrl = `../../api/serve-assignment-file.php?file_id=${fileId}&action=view`;
        const newWindow = window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        if (!newWindow) {
          showError('Please allow popups to preview files');
        }
      }
    }

    // View attendance (tutor function) - this is now handled above
    // function viewAttendance(id) was moved and enhanced above

    // Close grading modal
    function closeGradingModal() {
      document.getElementById('gradingModal').classList.add('hidden');
      document.getElementById('gradingModal').classList.remove('flex');
    }

    // Grade individual student
    function gradeStudent(studentId) {
      // Populate student data (in real app, this would fetch from API)
      const studentData = {
        'john-doe': {
          name: 'John Doe',
          email: 'john.doe@email.com',
          avatar: 'JD',
          avatarColor: 'bg-blue-500',
          submissionDate: 'Sep 20, 2025 2:30 PM',
          dueDate: 'Sep 23, 2025 11:59 PM',
          status: 'On Time',
          statusClass: 'bg-green-100 text-green-800',
          attempts: '1 of 3'
        },
        'jane-smith': {
          name: 'Jane Smith',
          email: 'jane.smith@email.com',
          avatar: 'JS',
          avatarColor: 'bg-purple-500',
          submissionDate: 'Sep 19, 2025 11:45 AM',
          dueDate: 'Sep 23, 2025 11:59 PM',
          status: 'On Time',
          statusClass: 'bg-green-100 text-green-800',
          attempts: '1 of 3'
        }
      };

      const student = studentData[studentId] || studentData['john-doe'];

      // Populate modal with student data
      document.getElementById('studentName').textContent = student.name;
      document.getElementById('studentEmail').textContent = student.email;
      document.getElementById('studentAvatar').textContent = student.avatar;
      document.getElementById('studentAvatar').className = `w-12 h-12 ${student.avatarColor} rounded-full flex items-center justify-center text-white font-semibold`;
      document.getElementById('submissionDate').textContent = student.submissionDate;
      document.getElementById('dueDate').textContent = student.dueDate;
      document.getElementById('submissionStatus').textContent = student.status;
      document.getElementById('submissionStatus').className = `${student.statusClass} px-2 py-1 rounded text-xs font-medium`;
      document.getElementById('attemptCount').textContent = student.attempts;

      // Show modal
      document.getElementById('studentGradingModal').classList.remove('hidden');
      document.getElementById('studentGradingModal').classList.add('flex');
    }

    // Close student grading modal
    function closeStudentGradingModal() {
      document.getElementById('studentGradingModal').classList.add('hidden');
      document.getElementById('studentGradingModal').classList.remove('flex');
      // Reset form
      document.getElementById('gradingForm').reset();
    }

    // Set quick grade
    function setGrade(grade) {
      document.getElementById('gradeValue').value = grade;
    }

    // Submit grade
    function submitGrade(event) {
      event.preventDefault();
      const formData = new FormData(event.target);
      const grade = formData.get('grade');
      const feedback = formData.get('feedback');

      if (!grade) {
        alert('Please enter a grade before submitting.');
        return;
      }

      // Show success message
      alert(`Grade submitted successfully!\nGrade: ${grade}%\nFeedback: ${feedback.substring(0, 50)}${feedback.length > 50 ? '...' : ''}`);

      // Close modal and refresh grading list
      closeStudentGradingModal();
      // In real app, would update the main grading modal list
    }

    // Edit grade
    function editGrade(studentId) {
      // Populate edit modal with current data
      const studentData = {
        'jane-smith': {
          name: 'Jane Smith',
          avatar: 'JS',
          avatarColor: 'bg-purple-500',
          currentGrade: 'A (92%)',
          gradeValue: 92,
          feedback: 'Excellent work on solving quadratic equations. Your method is clear and accurate.'
        }
      };

      const student = studentData[studentId] || studentData['jane-smith'];

      document.getElementById('editStudentName').textContent = student.name;
      document.getElementById('editStudentAvatar').textContent = student.avatar;
      document.getElementById('editStudentAvatar').className = `w-10 h-10 ${student.avatarColor} rounded-full flex items-center justify-center text-white font-semibold text-sm`;
      document.getElementById('currentGrade').textContent = student.currentGrade;
      document.getElementById('editGradeValue').value = student.gradeValue;
      document.getElementById('editFeedback').value = student.feedback;

      // Show modal
      document.getElementById('editGradeModal').classList.remove('hidden');
      document.getElementById('editGradeModal').classList.add('flex');
    }

    // Close edit grade modal
    function closeEditGradeModal() {
      document.getElementById('editGradeModal').classList.add('hidden');
      document.getElementById('editGradeModal').classList.remove('flex');
    }

    // Update grade
    function updateGrade(event) {
      event.preventDefault();
      const formData = new FormData(event.target);
      const newGrade = formData.get('grade');
      const newFeedback = formData.get('feedback');
      const reason = formData.get('changeReason');

      if (!reason.trim()) {
        alert('Please provide a reason for changing the grade.');
        return;
      }

      alert(`Grade updated successfully!\nNew Grade: ${newGrade}%\nReason: ${reason}`);
      closeEditGradeModal();
    }

    // Preview file
    function previewFile(fileName) {
      document.getElementById('previewFileName').textContent = fileName;

      // Simulate file preview content based on file type
      const previewContent = document.getElementById('filePreviewContent');

      if (fileName.endsWith('.pdf')) {
        previewContent.innerHTML = `
          <div class="w-full h-96 bg-white border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center">
            <svg class="w-16 h-16 text-red-500 mb-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">PDF Preview</h3>
            <p class="text-gray-600 text-center mb-4">${fileName}</p>
            <div class="bg-white p-4 rounded border shadow-sm max-w-md">
              <h4 class="font-semibold mb-2">Assignment Solution</h4>
              <p class="text-sm text-gray-700 mb-2">Problem 1: Solve x� + 5x + 6 = 0</p>
              <p class="text-sm text-gray-700 mb-2">Solution: Using the quadratic formula...</p>
              <p class="text-sm text-gray-700">x = -2 or x = -3</p>
            </div>
            <button onclick="downloadFile('${fileName}')" class="mt-4 bg-tplearn-green text-white px-4 py-2 rounded-lg hover:bg-green-700">
              Download Full PDF
            </button>
          </div>
        `;
      } else if (fileName.endsWith('.docx')) {
        previewContent.innerHTML = `
          <div class="w-full h-96 bg-white border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center">
            <svg class="w-16 h-16 text-blue-500 mb-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Word Document Preview</h3>
            <p class="text-gray-600 text-center mb-4">${fileName}</p>
            <div class="bg-white p-4 rounded border shadow-sm max-w-md">
              <h4 class="font-semibold mb-2">Detailed Calculations</h4>
              <p class="text-sm text-gray-700 mb-2">Step-by-step solution process:</p>
              <p class="text-sm text-gray-700 mb-1">1. Identify coefficients: a=1, b=5, c=6</p>
              <p class="text-sm text-gray-700 mb-1">2. Apply quadratic formula</p>
              <p class="text-sm text-gray-700">3. Simplify and solve</p>
            </div>
            <button onclick="downloadFile('${fileName}')" class="mt-4 bg-tplearn-green text-white px-4 py-2 rounded-lg hover:bg-green-700">
              Download Document
            </button>
          </div>
        `;
      }

      // Show preview modal
      document.getElementById('filePreviewModal').classList.remove('hidden');
      document.getElementById('filePreviewModal').classList.add('flex');
    }

    // Close file preview modal
    function closeFilePreviewModal() {
      document.getElementById('filePreviewModal').classList.add('hidden');
      document.getElementById('filePreviewModal').classList.remove('flex');
    }

    // Download file
    function downloadFile(fileName) {
      alert(`Downloading ${fileName}...`);
      // In real app, would trigger actual file download
    }

    // Download all submissions
    function downloadAllSubmissions() {
      // Show confirmation dialog
      if (confirm('Download all student submissions as a ZIP file?')) {
        // Simulate download progress
        const progressHtml = `
          <div class="fixed top-4 right-4 bg-white rounded-lg shadow-lg border p-4 z-50" id="downloadProgress">
            <div class="flex items-center space-x-3">
              <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-tplearn-green"></div>
              <div>
                <p class="font-medium text-gray-900">Preparing Downloads...</p>
                <p class="text-sm text-gray-600" id="downloadStatus">Collecting files...</p>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', progressHtml);

        // Simulate progress steps
        setTimeout(() => {
          document.getElementById('downloadStatus').textContent = 'Compressing files...';
        }, 1000);

        setTimeout(() => {
          document.getElementById('downloadStatus').textContent = 'Download ready!';
          setTimeout(() => {
            document.getElementById('downloadProgress').remove();
            alert('All submissions downloaded successfully!\nFile: Week3_Assignment_Submissions.zip');
          }, 500);
        }, 2000);
      }
    }

    // Export grades
    function exportGrades() {
      // Show export options
      const exportOptions = confirm('Export grades to CSV?\n\nOK = CSV Format\nCancel = PDF Report');

      if (exportOptions !== null) {
        const format = exportOptions ? 'CSV' : 'PDF';

        // Simulate export process
        const exportHtml = `
          <div class="fixed top-4 right-4 bg-white rounded-lg shadow-lg border p-4 z-50" id="exportProgress">
            <div class="flex items-center space-x-3">
              <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-purple-600"></div>
              <div>
                <p class="font-medium text-gray-900">Exporting Grades...</p>
                <p class="text-sm text-gray-600" id="exportStatus">Generating ${format} file...</p>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', exportHtml);

        setTimeout(() => {
          document.getElementById('exportStatus').textContent = 'Formatting data...';
        }, 800);

        setTimeout(() => {
          document.getElementById('exportProgress').remove();
          alert(`Grades exported successfully!\nFile: Week3_Assignment_Grades.${format.toLowerCase()}\n\nExported data includes:\n- Student names\n- Grades\n- Submission dates\n- Feedback summary`);
        }, 1600);
      }
    }

    // Upload modal
    function openUploadModal() {
      document.getElementById('uploadModal').classList.remove('hidden');
      document.getElementById('uploadModal').classList.add('flex');
      
      // Initialize with default material type (document)
      handleMaterialTypeChange('document');
    }

    // Close upload modal
    function closeUploadModal() {
      const uploadModal = document.getElementById('uploadModal');
      const uploadForm = document.getElementById('uploadForm');
      
      if (uploadModal) {
        uploadModal.classList.add('hidden');
        uploadModal.classList.remove('flex');
      }
      
      if (uploadForm) {
        uploadForm.reset();
      }
      
      resetUploadArea();
    }

    // Handle file selection for upload
    function handleFileSelect(event) {
      const file = event.target.files[0];
      if (file) {
        displayFilePreview(file);
      }
    }

    // Display file preview
    function displayFilePreview(file) {
      const fileName = file.name;
      const fileSize = (file.size / (1024 * 1024)).toFixed(1) + ' MB';

      const fileNameElement = document.getElementById('fileName');
      const fileSizeElement = document.getElementById('fileSize');
      const uploadArea = document.getElementById('uploadArea');
      const filePreview = document.getElementById('filePreview');
      const materialTitle = document.getElementById('materialTitle');

      if (fileNameElement) {
        fileNameElement.textContent = fileName;
      }
      
      if (fileSizeElement) {
        fileSizeElement.textContent = fileSize;
      }
      
      if (uploadArea) {
        uploadArea.classList.add('hidden');
      }
      
      if (filePreview) {
        filePreview.classList.remove('hidden');
      }

      // Auto-populate title if empty
      if (materialTitle && !materialTitle.value) {
        materialTitle.value = fileName.replace(/\.[^/.]+$/, "");
      }
    }

    // Remove selected file
    function removeFile() {
      const fileInput = document.getElementById('fileInput');
      const uploadArea = document.getElementById('uploadArea');
      const filePreview = document.getElementById('filePreview');
      
      if (fileInput) {
        fileInput.value = '';
      }
      
      if (uploadArea) {
        uploadArea.classList.remove('hidden');
      }
      
      if (filePreview) {
        filePreview.classList.add('hidden');
      }
    }

    // Handle assessment file selection
    function handleAssessmentFileSelect(event) {
      const file = event.target.files[0];
      if (!file) return;

      const fileName = file.name;
      const fileSize = formatFileSize(file.size);
      
      document.getElementById('assessmentFileName').textContent = fileName;
      document.getElementById('assessmentFileSize').textContent = fileSize;
      document.getElementById('assessmentUploadAreaContent').classList.add('hidden');
      document.getElementById('assessmentFilePreview').classList.remove('hidden');
      
      // Auto-fill assessment title if empty
      const assessmentTitleInput = document.getElementById('assessmentTitle');
      if (!assessmentTitleInput.value.trim()) {
        const nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.')) || fileName;
        assessmentTitleInput.value = nameWithoutExt;
      }
    }

    // Remove selected assessment file
    function removeAssessmentFile() {
      const fileInput = document.getElementById('assessmentFileInput');
      const uploadAreaContent = document.getElementById('assessmentUploadAreaContent');
      const filePreview = document.getElementById('assessmentFilePreview');
      
      if (fileInput) {
        fileInput.value = '';
      }
      
      if (uploadAreaContent) {
        uploadAreaContent.classList.remove('hidden');
      }
      
      if (filePreview) {
        filePreview.classList.add('hidden');
      }
    }

    // Reset upload area
    function resetUploadArea() {
      const fileInput = document.getElementById('fileInput');
      const uploadArea = document.getElementById('uploadArea');
      const filePreview = document.getElementById('filePreview');
      const assignmentDetails = document.getElementById('assignmentDetails');
      const sessionDetails = document.getElementById('sessionDetails');
      const scheduleDateTime = document.getElementById('scheduleDateTime');
      
      if (fileInput) {
        fileInput.value = '';
      }
      
      if (uploadArea) {
        uploadArea.classList.remove('hidden');
      }
      
      if (filePreview) {
        filePreview.classList.add('hidden');
      }
      
      if (assignmentDetails) {
        assignmentDetails.classList.add('hidden');
      }
      
      if (sessionDetails) {
        sessionDetails.classList.add('hidden');
      }
      
      if (scheduleDateTime) {
        scheduleDateTime.classList.add('hidden');
      }
    }

    // Submit upload
    function submitUpload(event) {
      event.preventDefault();

      console.log('Starting upload submission...');

      // Validate required DOM elements exist
      const requiredElements = [
        'fileInput', 'materialType', 'materialTitle', 'uploadProgressModal'
      ];
      
      for (const elementId of requiredElements) {
        const element = document.getElementById(elementId);
        if (!element) {
          console.error(`Required element not found: ${elementId}`);
          showError('Error: Missing form element. Please refresh the page and try again.');
          return;
        }
      }

      // Validate file selection
      const fileInput = document.getElementById('fileInput');
      if (!fileInput.files[0]) {
        showWarning('Please select a file to upload.');
        return;
      }

      // Validate form data
      const formData = new FormData(event.target);
      const materialType = formData.get('material_type');
      const title = formData.get('title');

      console.log('Form submission - Material Type:', materialType);
      console.log('Form submission - Title:', title);
      console.log('All form data:');
      for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
      }

      if (!materialType) {
        showError('Please select a material type.');
        return;
      }

      if (!title || title.trim() === '') {
        showError('Please enter a title for the material.');
        return;
      }

      // Validate file size (max 50MB for videos, 10MB for others)
      const file = fileInput.files[0];
      let maxSize = 10 * 1024 * 1024; // 10MB default
      if (materialType === 'video') {
        maxSize = 50 * 1024 * 1024; // 50MB for videos
      }
      
      if (file.size > maxSize) {
        const maxSizeMB = maxSize / (1024 * 1024);
        showError(`File size too large. Maximum allowed size for ${materialType} is ${maxSizeMB}MB.`);
        return;
      }

      // Add program_id to form data
      formData.append('program_id', <?php echo $program_id; ?>);

      // Add assignment-specific fields if material type is assignment
      if (materialType === 'assignment') {
        const dueDate = document.getElementById('dueDate').value;
        const totalPoints = document.getElementById('totalPoints').value;
        const allowLate = document.getElementById('allowLateSubmissions').checked;

        console.log('Assignment form data:', { dueDate, totalPoints, allowLate });

        if (dueDate) formData.append('due_date', dueDate);
        if (totalPoints) formData.append('total_points', totalPoints);
        formData.append('allow_late_submissions', allowLate ? '1' : '0');
      } else {
        console.log('Not an assignment, material type is:', materialType);
      }

      // Check if assessment is attached and add assessment-specific fields
      const assessmentFileInput = document.getElementById('assessmentFileInput');
      if (assessmentFileInput && assessmentFileInput.files.length > 0) {
        console.log('Assessment file attached, adding assessment fields...');
        
        const assessmentTitle = document.getElementById('assessmentTitle').value;
        const assessmentDescription = document.getElementById('assessmentDescription').value;
        const assessmentDueDate = document.getElementById('assessmentDueDate').value;
        const assessmentTotalPoints = document.getElementById('assessmentTotalPoints').value;

        console.log('Assessment attachment data:', { 
          assessmentTitle, 
          assessmentDescription, 
          assessmentDueDate, 
          assessmentTotalPoints 
        });

        if (assessmentTitle) formData.append('assessmentTitle', assessmentTitle);
        if (assessmentDescription) formData.append('assessmentDescription', assessmentDescription);
        if (assessmentDueDate) formData.append('assessmentDueDate', assessmentDueDate);
        if (assessmentTotalPoints) formData.append('assessmentTotalPoints', assessmentTotalPoints);
      }

      // Show progress modal first (keep upload modal open)
      showUploadProgress(title, materialType);

      // Actually submit to API
      fetch('../../api/upload-program-material.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('API Response:', data);
        
        // Clear the progress interval
        if (window.uploadProgressInterval) {
          clearInterval(window.uploadProgressInterval);
        }
        
        if (data.success) {
          // Close both modals and show success
          closeUploadModal();
          
          const progressModal = document.getElementById('uploadProgressModal');
          if (progressModal) {
            progressModal.classList.add('hidden');
            progressModal.classList.remove('flex');
          }
          
          showSuccess(`Material "${title}" uploaded successfully! The material is now available to students.`);
          setTimeout(() => {
            window.location.reload();
          }, 2000);
        } else {
          // Hide progress modal but keep upload modal open for retry
          const progressModal = document.getElementById('uploadProgressModal');
          if (progressModal) {
            progressModal.classList.add('hidden');
            progressModal.classList.remove('flex');
          }
          
          console.error('Upload failed with data:', data);
          showError('Upload failed: ' + (data.error || data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        // Clear the progress interval
        if (window.uploadProgressInterval) {
          clearInterval(window.uploadProgressInterval);
        }
        
        // Hide progress modal but keep upload modal open for retry
        const progressModal = document.getElementById('uploadProgressModal');
        if (progressModal) {
          progressModal.classList.add('hidden');
          progressModal.classList.remove('flex');
        }
        
        console.error('Upload error:', error);
        showError('Upload failed. Please try again. Error: ' + error.message);
      });
    }

    // Show upload progress
    function showUploadProgress(title, type) {
      const progressModal = document.getElementById('uploadProgressModal');
      if (!progressModal) {
        console.error('Upload progress modal not found');
        return;
      }
      
      progressModal.classList.remove('hidden');
      progressModal.classList.add('flex');

      const progressBar = document.getElementById('uploadProgress');
      const progressPercent = document.getElementById('uploadPercent');
      const status = document.getElementById('uploadStatus');
      
      if (!progressBar || !progressPercent || !status) {
        console.error('Upload progress elements not found');
        return;
      }

      let progress = 0;

      const steps = [
        'Validating file...',
        'Uploading file...',
        'Processing content...',
        'Creating material entry...',
        'Finalizing upload...'
      ];

      let stepIndex = 0;
      status.textContent = steps[stepIndex];

      // Store the interval ID so we can clear it from outside
      window.uploadProgressInterval = setInterval(() => {
        progress += Math.random() * 15 + 5;

        if (progress > 100) {
          progress = 100;
          // Don't show alert here - let the API response handle it
          progressBar.style.width = '100%';
          progressPercent.textContent = '100%';
          status.textContent = 'Finalizing upload...';
          clearInterval(window.uploadProgressInterval);
          return;
        }

        if (progress > 20 && stepIndex === 0) {
          stepIndex = 1;
          status.textContent = steps[stepIndex];
        } else if (progress > 50 && stepIndex === 1) {
          stepIndex = 2;
          status.textContent = steps[stepIndex];
        } else if (progress > 75 && stepIndex === 2) {
          stepIndex = 3;
          status.textContent = steps[stepIndex];
        } else if (progress > 90 && stepIndex === 3) {
          stepIndex = 4;
          status.textContent = steps[stepIndex];
        }

        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';
      }, 200);
    }

    // Close edit material modal
    function closeEditMaterialModal() {
      document.getElementById('editMaterialModal').classList.add('hidden');
      document.getElementById('editMaterialModal').classList.remove('flex');
      document.getElementById('editMaterialForm').reset();
      resetEditUploadArea();
    }

    // Handle file selection for edit
    function handleEditFileSelect(event) {
      const file = event.target.files[0];
      if (file) {
        displayEditFilePreview(file);
      }
    }

    // Display edit file preview
    function displayEditFilePreview(file) {
      const fileName = file.name;
      const fileSize = (file.size / (1024 * 1024)).toFixed(1) + ' MB';

      document.getElementById('editNewFileName').textContent = fileName;
      document.getElementById('editNewFileSize').textContent = fileSize;
      document.getElementById('editUploadArea').classList.add('hidden');
      document.getElementById('editFilePreview').classList.remove('hidden');
    }

    // Remove edit file
    function removeEditFile() {
      document.getElementById('editFileInput').value = '';
      document.getElementById('editUploadArea').classList.remove('hidden');
      document.getElementById('editFilePreview').classList.add('hidden');
    }

    // Reset edit upload area
    function resetEditUploadArea() {
      document.getElementById('editFileInput').value = '';
      document.getElementById('editUploadArea').classList.remove('hidden');
      document.getElementById('editFilePreview').classList.add('hidden');
    }

    // Submit edit material
    function submitEditMaterial(event) {
      event.preventDefault();

      const formData = new FormData(event.target);
      const materialId = event.target.getAttribute('data-material-id');
      const title = formData.get('title');
      const hasNewFile = document.getElementById('editFileInput').files[0];

      // Validate required fields
      if (!materialId) {
        console.error('Material ID not found!');
        showError('Material ID is missing. Please close and reopen the edit dialog.');
        return;
      }

      if (!title || title.trim() === '') {
        showError('Please enter a title for the material.');
        return;
      }

      // Validate file size if new file is selected
      if (hasNewFile) {
        const maxSize = 50 * 1024 * 1024; // 50MB
        if (hasNewFile.size > maxSize) {
          showWarning('File size too large. Maximum allowed size is 50MB.');
          return;
        }
      }

      // Add required fields to form data
      formData.append('material_id', materialId);
      formData.append('program_id', <?= $program_id ?>);
      formData.append('tutor_id', <?= $tutor_user_id ?>);

      // Add assignment-specific fields if assignment details are visible
      if (!document.getElementById('editAssignmentDetails').classList.contains('hidden')) {
        const dueDate = document.getElementById('editDueDate').value;
        const totalPoints = document.getElementById('editTotalPoints').value;
        const allowLate = document.getElementById('editAllowLateSubmissions').checked;

        if (dueDate) formData.append('due_date', dueDate);
        if (totalPoints) formData.append('total_points', totalPoints);
        formData.append('allow_late_submissions', allowLate ? '1' : '0');
      }

      // Show loading state
      const submitButton = event.target.querySelector('button[type="submit"]');
      const originalText = submitButton.textContent;
      submitButton.disabled = true;
      submitButton.textContent = 'Updating...';

      // Debug: Log form data being sent
      console.log('Submitting edit with data:', {
        material_id: materialId,
        program_id: <?= $program_id ?>,
        tutor_id: <?= $tutor_user_id ?>,
        title: title,
        has_new_file: !!hasNewFile
      });

      // Submit to API
      fetch('../../api/update-program-material.php', {
        method: 'POST',
        credentials: 'include',
        body: formData
      })
      .then(response => {
        console.log('Update response status:', response.status);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Update response data:', data);
        if (data.success) {
          closeEditMaterialModal();
          
          const updateMessage = hasNewFile ?
            `Material "${title}" updated successfully! New file uploaded and material details updated.` :
            `Material "${title}" updated successfully! Material details updated.`;
          
          showSuccess(updateMessage);
          
          // Refresh the page to show updated data
          setTimeout(() => {
            location.reload();
          }, 2000);
        } else {
          showError('Error updating material: ' + (data.message || data.error || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Update Error:', error);
        showError(`Error updating material: ${error.message}. Please try again.`);
      })
      .finally(() => {
        // Reset button state
        submitButton.disabled = false;
        submitButton.textContent = originalText;
      });
    }

    // Close view material modal
    function closeViewMaterialModal() {
      console.log('Closing view modal...');
      const modal = document.getElementById('viewMaterialModal');
      
      // Hide modal
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      
      // Reset global state
      isViewModalOpen = false;
      
      // Reset modal state
      modal.removeAttribute('data-material-id');
      
      // Reset content to default state
      const previewContent = document.getElementById('viewPreviewContent');
      previewContent.innerHTML = `
        <div class="text-center">
          <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2">File Preview</h3>
          <p id="viewDescription" class="text-gray-600 mb-4">Material details would be loaded here...</p>
          <button onclick="downloadMaterialFromView()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
            Download to View
          </button>
        </div>
      `;
      
      // Reset file information
      document.getElementById('viewMaterialTitle').textContent = 'Material Details';
      document.getElementById('viewMaterialType').textContent = 'Document';
      document.getElementById('viewFileName').textContent = 'Loading...';
      document.getElementById('viewFileSize').textContent = '';
      document.getElementById('viewUploadDate').textContent = '';
      document.getElementById('viewUploadedBy').textContent = '';
      document.getElementById('viewStatus').textContent = 'Active';
      document.getElementById('viewStatus').className = 'bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium';
      
      console.log('Modal closed and reset, state:', isViewModalOpen);
    }

    // Load material preview
    function loadMaterialPreview(material) {
      const previewContent = document.getElementById('viewPreviewContent');
      const fileInfo = material.file;
      const fileExt = getFileExtension(fileInfo.original_filename || fileInfo.filename);
      
      // Check if file can be previewed
      const previewableTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
      const canPreview = previewableTypes.includes(fileExt.toLowerCase());
      
      if (canPreview) {
        if (fileExt.toLowerCase() === 'pdf') {
          // PDF preview
          const viewUrl = `../../api/serve-material-file.php?material_id=${material.id}&action=view`;
          previewContent.innerHTML = `
            <div class="h-full min-h-96 max-h-96 overflow-hidden">
              <iframe src="${viewUrl}" class="w-full h-full border-0 rounded" 
                      onload="this.style.display='block'" 
                      onerror="this.style.display='none'; showPreviewFallback(${material.id}, '${material.description || 'Material available for viewing'}')">
              </iframe>
            </div>
          `;
        } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt.toLowerCase())) {
          // Image preview
          const viewUrl = `../../api/serve-material-file.php?material_id=${material.id}&action=view`;
          previewContent.innerHTML = `
            <div class="h-full min-h-96 max-h-96 overflow-hidden flex items-center justify-center bg-gray-50 rounded">
              <img src="${viewUrl}" alt="Preview" class="max-w-full max-h-full object-contain rounded shadow-lg" 
                   onload="this.style.display='block'" 
                   onerror="showPreviewFallback(${material.id}, '${material.description || 'Material available for viewing'}')">
            </div>
          `;
        }
      } else {
        // Fallback for non-previewable files
        showPreviewFallback(material.id, material.description || 'Material available for viewing');
      }
    }
    
    // Show preview fallback
    function showPreviewFallback(materialId, description) {
      const previewContent = document.getElementById('viewPreviewContent');
      previewContent.innerHTML = `
        <div class="text-center">
          <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2">File Preview</h3>
          <p class="text-gray-600 mb-4">${description}</p>
          <div class="space-x-2">
            <button onclick="viewMaterialFile(${materialId})" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
              <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
              </svg>
              View File
            </button>
            <button onclick="downloadMaterialFromView()" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700">
              <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              Download
            </button>
          </div>
        </div>
      `;
    }

    // View material file
    function viewMaterialFile(materialId) {
      // Open the material file in a new tab for viewing
      const viewUrl = `../../api/serve-material-file.php?material_id=${materialId}&action=view`;
      window.open(viewUrl, '_blank');
      showInfo('Opening file in new tab...');
    }

    // View material file from modal button
    function viewMaterialFileFromModal() {
      const materialId = document.getElementById('viewMaterialModal').getAttribute('data-material-id');
      if (materialId) {
        viewMaterialFile(materialId);
      } else {
        showError('Material ID not found');
      }
    }

    // Download from view modal
    function downloadMaterialFromView() {
      const materialId = document.getElementById('viewMaterialModal').getAttribute('data-material-id');
      console.log('Downloading material from view modal, ID:', materialId);
      
      if (!materialId || materialId === 'null' || materialId === 'undefined') {
        showError('Material ID not found. Please close and reopen the modal.');
        return;
      }

      const downloadUrl = `../../api/serve-material-file.php?material_id=${materialId}&action=download`;
      console.log('Material download URL:', downloadUrl);
      
      try {
        // Test if the API is reachable first
        fetch(downloadUrl, { 
          method: 'HEAD',
          credentials: 'include'
        })
        .then(response => {
          if (response.ok) {
            // If API responds OK, proceed with download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showSuccess('Download started successfully!');
          } else {
            console.error('Material download failed. HTTP status:', response.status);
            if (response.status === 401) {
              showError('Not authorized to download this material. Please login again.');
            } else if (response.status === 403) {
              showError('Access denied. You do not have permission to download this material.');
            } else if (response.status === 404) {
              showError('Material not found. It may have been deleted or moved.');
            } else {
              showError(`Download failed with status: ${response.status}`);
            }
          }
        })
        .catch(error => {
          console.error('Material download error:', error);
          showError('Failed to download material. Please check your connection and try again.');
        });
      } catch (error) {
        console.error('Unexpected material download error:', error);
        showError('An unexpected error occurred while downloading the material.');
      }
    }

    // Edit from view modal
    function editMaterialFromView() {
      closeViewMaterialModal();
      const materialId = document.getElementById('viewMaterialModal').getAttribute('data-material-id');
      if (materialId) {
        editItem(materialId);
      } else {
        showError('Material ID not found');
      }
    }

    // Handle material type change from dropdown
    function handleMaterialTypeChange(value) {
      console.log('Material type changed to:', value);
      const assignmentDetails = document.getElementById('assignmentDetails');
      const assessmentAttachmentSection = document.getElementById('assessmentAttachmentSection');
      
      if (value === 'assignment') {
        console.log('Showing assignment details, hiding assessment attachment');
        assignmentDetails.classList.remove('hidden');
        assessmentAttachmentSection.classList.add('hidden');
      } else {
        console.log('Hiding assignment details, showing assessment attachment option');
        assignmentDetails.classList.add('hidden');
        // Show assessment attachment section for other material types (document, video, other)
        assessmentAttachmentSection.classList.remove('hidden');
      }
    }

    // Handle material type selection
    document.addEventListener('DOMContentLoaded', function() {
      // File upload area click handlers
      document.getElementById('uploadArea').addEventListener('click', function() {
        document.getElementById('fileInput').click();
      });

      document.getElementById('editUploadArea').addEventListener('click', function() {
        document.getElementById('editFileInput').click();
      });

      // Assessment file upload area click handler
      document.getElementById('assessmentUploadArea').addEventListener('click', function() {
        document.getElementById('assessmentFileInput').click();
      });

      // Delete confirmation checkbox
      document.getElementById('deleteConfirmCheckbox').addEventListener('change', function() {
        document.getElementById('confirmDeleteButton').disabled = !this.checked;
      });

      // Attendance search and filter
      document.getElementById('attendanceSearch').addEventListener('input', function() {
        filterAttendanceList();
      });

      document.getElementById('attendanceFilter').addEventListener('change', function() {
        filterAttendanceList();
      });
    });

    // Filter attendance list
    function filterAttendanceList() {
      const searchTerm = document.getElementById('attendanceSearch').value.toLowerCase();
      const statusFilter = document.getElementById('attendanceFilter').value;
      const items = document.querySelectorAll('.attendance-item');

      items.forEach(item => {
        const studentName = item.querySelector('.font-medium').textContent.toLowerCase();
        const studentEmail = item.querySelector('.text-gray-600').textContent.toLowerCase();
        const itemStatus = item.getAttribute('data-status');

        const matchesSearch = studentName.includes(searchTerm) || studentEmail.includes(searchTerm);
        const matchesFilter = statusFilter === 'all' || itemStatus === statusFilter;

        if (matchesSearch && matchesFilter) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    }

    // Header functions
    function openNotifications() {
      alert('Opening notifications...');
    }

    function openMessages() {
      alert('Opening messages...');
    }

    // Back to dashboard
    function backToDashboard() {
      window.location.href = 'tutor.php';
    }

    // Function to download files
    function downloadItem(fileId) {
      console.log('Downloading file with ID:', fileId);
      
      // Validate file ID
      if (!fileId || fileId === 'undefined' || fileId === 'null') {
        showError('Invalid file ID. Cannot download file.');
        console.error('Invalid file ID provided:', fileId);
        return;
      }

      const downloadUrl = `../../api/serve-program-file.php?file_id=${fileId}&action=download`;
      console.log('Download URL:', downloadUrl);
      
      try {
        // Create download link and trigger download directly
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showInfo('Download started. Check your downloads folder.');
        
        // Optional: Test if download was successful after a short delay
        setTimeout(() => {
          fetch(downloadUrl.replace('action=download', 'action=view'), { 
            method: 'HEAD',
            credentials: 'include'
          })
          .then(response => {
            if (!response.ok) {
              console.error('Download validation failed. HTTP status:', response.status);
              if (response.status === 401) {
                showError('Session expired. Please login again.');
              } else if (response.status === 403) {
                showError('Access denied. You do not have permission to access this file.');
              } else if (response.status === 404) {
                showError('File not found. It may have been deleted or moved.');
              }
            }
          })
          .catch(error => {
            console.warn('Download validation failed:', error);
            // Don't show error to user as download might still work
          });
        }, 1000);
        
      } catch (error) {
        console.error('Unexpected download error:', error);
        showError('An unexpected error occurred while downloading the file.');
      }
    }

    // Global variables for file preview
    let currentPreviewFileId = null;
    let currentPreviewType = null;
    let currentPreviewStudentData = null;
    let isViewModalOpen = false;

    // Open file preview modal
    async function openFilePreviewModal(fileId, type = 'assignment', studentData = null) {
      try {
        console.log('Opening preview modal:', { fileId, type, studentData });
        
        // Check if fileId is valid
        if (!fileId || fileId === 'null' || fileId === 'undefined') {
          console.warn('Invalid file ID provided:', fileId);
          showWarning('Invalid file ID. Using fallback data.');
          fileId = null;
        }
        
        // Store current preview data
        currentPreviewFileId = fileId;
        currentPreviewType = type;
        currentPreviewStudentData = studentData;

        // Show modal
        document.getElementById('filePreviewModal').classList.remove('hidden');
        document.getElementById('filePreviewModal').classList.add('flex');

        // Show loading state
        document.getElementById('previewContent').innerHTML = `
          <div class="flex items-center justify-center h-full text-gray-500">
            <div class="text-center">
              <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
              </svg>
              <p>Loading file preview...</p>
            </div>
          </div>
        `;

        // Get file details - try API first, fall back to student data
        let file = null;
        
        if (fileId) {
          try {
            console.log('Calling API with fileId:', fileId);
            const response = await fetch(`../../api/get-file-info.php?file_id=${fileId}`, {
              credentials: 'include'
            });
            console.log('API response status:', response.status);
          
          if (response.ok) {
            const fileInfo = await response.json();
            console.log('API response data:', fileInfo);
            if (fileInfo.success) {
              file = fileInfo.data;
              console.log('File data from API:', file);
            } else {
              console.warn('API returned error:', fileInfo.error);
              showWarning('API Error: ' + (fileInfo.error || 'Unknown error'));
            }
          } else {
            const errorText = await response.text();
            console.warn('API request failed with status:', response.status, 'Response:', errorText);
            showError(`API request failed: ${response.status} ${response.statusText}`);
          }
          } catch (error) {
            console.error('API call failed:', error);
            showError('API call failed: ' + error.message);
          }
        } else {
          console.log('No file ID provided, using fallback data only');
        }

        // If API failed, use data from studentData or defaults
        if (!file && studentData) {
          console.log('Using student data as fallback:', studentData);
          
          // Try to get filename from different possible properties
          const fileName = studentData.file_name || 
                          studentData.original_filename || 
                          studentData.filename ||
                          studentData.submission_filename ||
                          studentData.original_name ||
                          (studentData.student_name ? `${studentData.student_name}_submission` : null) ||
                          'Student File';
          
          file = {
            original_filename: fileName,
            original_name: fileName,
            file_size: studentData.file_size || studentData.size || 0,
            mime_type: studentData.mime_type || 'application/octet-stream'
          };
          console.log('Fallback file object created:', file);
        }

        if (!file) {
          console.warn('No file data available, using defaults');
          file = {
            original_filename: 'Unknown File',
            original_name: 'Unknown File',
            file_size: 0
          };
        }

        // Update modal header
        const fileName = file.original_filename || file.original_name || 'Unknown File';
        document.getElementById('previewFileName').textContent = fileName;
        document.getElementById('previewFileType').textContent = getFileExtension(fileName).toUpperCase();
        document.getElementById('previewFileSize').textContent = formatFileSize(file.file_size || 0);

        // Show student info if provided (for submissions)
        if (studentData) {
          document.getElementById('previewStudentInfo').classList.remove('hidden');
          document.getElementById('previewStudentName').textContent = studentData.student_name || 'Unknown Student';
          document.getElementById('previewSubmissionDate').textContent = studentData.submitted_at ? 
            `Submitted: ${new Date(studentData.submitted_at).toLocaleString()}` : 'Not submitted';
          
          // Status
          const statusElement = document.getElementById('previewSubmissionStatus');
          if (studentData.status === 'graded') {
            statusElement.className = 'px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800';
            statusElement.textContent = 'Graded';
          } else if (studentData.status === 'submitted') {
            statusElement.className = 'px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800';
            statusElement.textContent = 'Submitted';
          } else {
            statusElement.className = 'px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800';
            statusElement.textContent = 'Not submitted';
          }

          // Score
          document.getElementById('previewCurrentScore').textContent = studentData.score ? 
            `Score: ${studentData.score}/${studentData.max_score || 100}` : 'Not graded';

          // Show grade button for submissions
          document.getElementById('previewGradeBtn').classList.remove('hidden');
        } else {
          document.getElementById('previewStudentInfo').classList.add('hidden');
          document.getElementById('previewGradeBtn').classList.add('hidden');
        }

        // Load file content based on type
        if (fileId && fileId !== 'null') {
          await loadFileContent(fileId, file);
        } else {
          // Show message that preview is not available but file can be downloaded
          document.getElementById('previewContent').innerHTML = `
            <div class="flex items-center justify-center h-full">
              <div class="text-center max-w-md">
                <div class="bg-gray-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                  <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">${fileName}</h3>
                <p class="text-gray-500 mb-4">File preview is not available.</p>
                <p class="text-sm text-gray-400 mb-2">File size: ${formatFileSize(file.file_size || 0)}</p>
                <p class="text-sm text-gray-400 mb-6">Type: ${getFileExtension(fileName).toUpperCase()} file</p>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                  <p class="text-sm text-yellow-800">
                    <strong>Note:</strong> File preview is not available because the file ID is missing from the database. 
                    The file may need to be re-uploaded or there may be a data integrity issue.
                  </p>
                </div>
              </div>
            </div>
          `;
        }

      } catch (error) {
        console.error('Error opening file preview:', error);
        showError('Failed to load file preview: ' + error.message);
        document.getElementById('previewContent').innerHTML = `
          <div class="flex items-center justify-center h-full text-red-500">
            <div class="text-center">
              <svg class="w-16 h-16 mx-auto mb-4 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
              <p>Error loading file preview</p>
              <p class="text-sm mt-2">${error.message}</p>
            </div>
          </div>
        `;
      }
    }

    // Load file content for preview
    async function loadFileContent(fileId, fileInfo) {
      const fileName = fileInfo.original_filename || fileInfo.original_name || 'file';
      const extension = getFileExtension(fileName).toLowerCase();
      const previewContent = document.getElementById('previewContent');

      try {
        if (extension === 'pdf') {
          // Show PDF using embed
          previewContent.innerHTML = `
            <embed src="../../api/serve-submission-file.php?file_id=${fileId}&action=view" 
                   type="application/pdf" 
                   width="100%" 
                   height="100%"
                   style="min-height: 600px;">
          `;
        } else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
          // Show image
          previewContent.innerHTML = `
            <div class="flex items-center justify-center h-full">
              <img src="../../api/serve-submission-file.php?file_id=${fileId}&action=view" 
                   alt="File preview" 
                   class="max-w-full max-h-full object-contain">
            </div>
          `;
        } else if (['txt', 'md'].includes(extension)) {
          // Load text content
          const response = await fetch(`../../api/serve-submission-file.php?file_id=${fileId}&action=view`, {
            credentials: 'include'
          });
          const text = await response.text();
          previewContent.innerHTML = `
            <div class="bg-gray-50 border rounded-lg p-4 h-full overflow-auto">
              <pre class="whitespace-pre-wrap font-mono text-sm">${escapeHtml(text)}</pre>
            </div>
          `;
        } else {
          // Generic file type - show file info and download option
          previewContent.innerHTML = `
            <div class="flex items-center justify-center h-full">
              <div class="text-center max-w-md">
                <div class="bg-gray-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                  <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">${fileName}</h3>
                <p class="text-gray-500 mb-4">This file type cannot be previewed directly.</p>
                <p class="text-sm text-gray-400">File size: ${formatFileSize(fileInfo.file_size || 0)}</p>
                <p class="text-sm text-gray-400">Type: ${extension.toUpperCase()} file</p>
                <div class="mt-6">
                  <button onclick="downloadFileFromPreview()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                    Download File
                  </button>
                </div>
              </div>
            </div>
          `;
        }
      } catch (error) {
        console.error('Error loading file content:', error);
        previewContent.innerHTML = `
          <div class="flex items-center justify-center h-full text-orange-500">
            <div class="text-center">
              <svg class="w-16 h-16 mx-auto mb-4 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
              <p>Preview not available</p>
              <p class="text-sm mt-2">Use the download button to view this file</p>
            </div>
          </div>
        `;
      }
    }

    // Close file preview modal
    function closeFilePreviewModal() {
      document.getElementById('filePreviewModal').classList.add('hidden');
      document.getElementById('filePreviewModal').classList.remove('flex');
      
      // Clear stored data
      currentPreviewFileId = null;
      currentPreviewType = null;
      currentPreviewStudentData = null;
    }

    // Download file from preview
    function downloadFileFromPreview() {
      if (currentPreviewFileId && currentPreviewFileId !== 'null') {
        window.open(`../../api/serve-submission-file.php?file_id=${currentPreviewFileId}&action=download`, '_blank');
      } else {
        showError('Download not available: File ID is missing from database');
      }
    }

    // Grade from preview
    function gradeFromPreview() {
      if (currentPreviewStudentData && currentPreviewType) {
        closeFilePreviewModal();
        
        if (currentPreviewType === 'assessment') {
          gradeAssessmentSubmission(currentPreviewStudentData.id || currentPreviewStudentData.student_id);
        } else if (currentPreviewType === 'assignment') {
          gradeSubmission(currentPreviewStudentData.submission_id || currentPreviewStudentData.id);
        }
      } else {
        showError('Cannot grade: missing submission data');
      }
    }

    // Helper function to get file extension
    function getFileExtension(filename) {
      return filename ? filename.split('.').pop() || '' : '';
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // =======================
    // JITSI LIVE CLASS FUNCTIONS
    // =======================
    
    // Open Create Live Class Modal
    function openCreateLiveClassModal() {
      document.getElementById('createLiveClassModal').classList.remove('hidden');
      document.getElementById('createLiveClassModal').classList.add('flex');
      
      // Set default date to today
      document.getElementById('liveClassDate').value = new Date().toISOString().split('T')[0];
      // Set default time to current hour + 1
      const now = new Date();
      now.setHours(now.getHours() + 1);
      document.getElementById('liveClassTime').value = now.toTimeString().substring(0, 5);
    }
    
    // Close Create Live Class Modal
    function closeCreateLiveClassModal() {
      document.getElementById('createLiveClassModal').classList.add('hidden');
      document.getElementById('createLiveClassModal').classList.remove('flex');
      document.getElementById('createLiveClassForm').reset();
    }
    
    // Submit Create Live Class Form
    async function submitCreateLiveClass(event) {
      event.preventDefault();
      
      const formData = new FormData(event.target);
      
      try {
        showInfo('Creating live session...');
        
        const response = await fetch('../../api/jitsi_meetings.php', {
          method: 'POST',
          body: formData,
          credentials: 'include'
        });
        
        const result = await response.json();
        
        if (result.success) {
          showSuccess('Live session created successfully!');
          closeCreateLiveClassModal();
          loadLiveClasses(); // Refresh the live classes list
        } else {
          showError('Error: ' + result.message);
        }
      } catch (error) {
        console.error('Error creating live session:', error);
        showError('Failed to create live session. Please try again.');
      }
    }
    
    // Load Live Classes
    async function loadLiveClasses() {
      try {
        const response = await fetch(`../../api/jitsi_meetings.php?action=get_meetings&program_id=<?= $program_id ?>`, {
          credentials: 'include'
        });
        
        const result = await response.json();
        
        if (result.success) {
          displayLiveClasses(result.meetings);
        } else {
          console.error('Failed to load live classes:', result.message);
        }
      } catch (error) {
        console.error('Error loading live classes:', error);
      }
    }
    
    // Display Live Classes
    function displayLiveClasses(meetings) {
      const container = document.getElementById('live-classes-container');
      
      if (meetings.length === 0) {
        container.innerHTML = '';
        checkEmptyState();
        return;
      }
      
      let html = '';
      
      meetings.forEach(meeting => {
        const isLive = meeting.is_live;
        const isUpcoming = meeting.is_upcoming;
        const isPast = meeting.is_past;
        const isEnded = meeting.status === 'completed' || meeting.status === 'cancelled';
        
        // Use consistent status colors and text
        const statusColor = getLiveStatusColor(isLive, isUpcoming, isPast);
        let statusText = getStatusText(isLive, isUpcoming, isPast);
        
        // Override status text if explicitly ended
        if (isEnded) {
          statusText = meeting.status === 'completed' ? 'Ended' : 'Cancelled';
        }
        
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
          <!-- Live Class: ${escapeHtml(meeting.title)} -->
          <div class="content-item bg-white rounded-lg border border-gray-200 p-6 mb-6" data-type="live-classes">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 ${isEnded ? 'bg-gray-100' : 'bg-red-100'} rounded-lg flex items-center justify-center">
                  <svg class="w-6 h-6 ${isEnded ? 'text-gray-600' : 'text-red-600'}" fill="currentColor" viewBox="0 0 20 20">
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
                        ${statusText}
                      </span>
                    </div>
                    <div class="flex items-center space-x-2 mb-2">
                      <span class="text-sm text-gray-500">Meeting:</span>
                      <span class="text-sm font-medium text-gray-600">${meeting.meeting_id}</span>
                      <span class="text-sm text-gray-500">on</span>
                      <span class="text-sm font-medium text-gray-700">${meeting.formatted_date} at ${addTimezoneIndicator(meeting.formatted_time)}</span>
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
                        <button onclick="joinLiveClass('${meeting.meeting_url}', ${meeting.id}, true)" 
                                class="text-tplearn-green hover:text-green-700 text-sm font-medium flex items-center" 
                                title="Join live session">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                          </svg>
                          Join Now
                        </button>
                      ` : `
                        <button onclick="viewLiveClassDetails(${meeting.id})" 
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
                    <!-- Tutor Management Actions -->
                    <div class="flex items-center space-x-2 mt-2">
                      <button onclick="editItem(${meeting.id}, 'live-class')" class="bg-gray-600 text-white px-3 py-1 rounded-lg text-xs hover:bg-gray-700 transition-colors">
                        Edit
                      </button>
                      <button onclick="deleteLiveClass(${meeting.id})" class="bg-red-600 text-white px-3 py-1 rounded-lg text-xs hover:bg-red-700 transition-colors">
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
      checkEmptyState();
    }
    
    // Check if there's any content and show/hide empty state
    function checkEmptyState() {
      const liveClassesContainer = document.getElementById('live-classes-container');
      const materialsExist = document.querySelectorAll('.content-item').length > 0;
      const liveClassesExist = liveClassesContainer && liveClassesContainer.innerHTML.trim() !== '';
      const emptyState = document.getElementById('empty-state');
      
      if (!materialsExist && !liveClassesExist) {
        emptyState.classList.remove('hidden');
      } else {
        emptyState.classList.add('hidden');
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
    
    // Join Live Class using Jitsi Meet External API
    async function joinLiveClass(meetingUrl, meetingId, openInNewTab = false) {
      try {
        showInfo('Starting session as moderator...');
        
        // Record tutor participation tracking (this enables students to join)
        await fetch('../../api/track-session-participation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            meeting_id: meetingId,
            action: 'join'
          }),
          credentials: 'include'
        });
        
        // Also record in existing system
        await fetch('../../api/jitsi_meetings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=join_meeting&meeting_id=${meetingId}`,
          credentials: 'include'
        });
        
        if (openInNewTab) {
          // Store meeting ID in session storage for tracking
          sessionStorage.setItem('active_meeting_id', meetingId);
          
          // Open in new tab/window
          const meetingWindow = window.open(meetingUrl, '_blank');
          showInfo('Opening live session in new tab...');
          
          // Set up polling to check if the meeting window is closed
          const checkWindowClosed = setInterval(async () => {
            if (meetingWindow && meetingWindow.closed) {
              clearInterval(checkWindowClosed);
              console.log('Meeting window closed, ending session...');
              
              // End the meeting
              await endMeetingSession(meetingId);
              await trackTutorLeave(meetingId);
              
              // Remove from session storage
              sessionStorage.removeItem('active_meeting_id');
              
              // Refresh the live classes list
              showSuccess('Live session ended - Meeting marked as completed');
              setTimeout(() => {
                loadLiveClasses();
              }, 1000);
            }
          }, 1000); // Check every second
          
        } else {
          // Extract room name from meeting URL
          const roomName = meetingUrl.split('/').pop();
          
          // Open Jitsi meeting in modal overlay
          openJitsiMeeting(roomName, meetingId);
          
          // Store meetingId for heartbeat tracking
          window.currentMeetingId = meetingId;
          
          // Start heartbeat to maintain tutor presence
          startTutorHeartbeat(meetingId);
        }
        
      } catch (error) {
        console.error('Error joining meeting:', error);
        showError('Failed to join live session. Please try again.');
      }
    }
    
    // Start tutor heartbeat to maintain presence
    function startTutorHeartbeat(meetingId) {
      // Clear any existing heartbeat
      if (window.tutorHeartbeatInterval) {
        clearInterval(window.tutorHeartbeatInterval);
      }
      
      // Send heartbeat every 60 seconds
      window.tutorHeartbeatInterval = setInterval(async () => {
        try {
          await fetch('../../api/track-session-participation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              meeting_id: meetingId,
              action: 'heartbeat'
            }),
            credentials: 'include'
          });
          console.log('Tutor heartbeat sent');
        } catch (error) {
          console.error('Heartbeat failed:', error);
        }
      }, 60000);
    }
    
    // Stop tutor heartbeat
    function stopTutorHeartbeat() {
      if (window.tutorHeartbeatInterval) {
        clearInterval(window.tutorHeartbeatInterval);
        window.tutorHeartbeatInterval = null;
      }
    }
    
    // Track when tutor leaves session
    async function trackTutorLeave(meetingId) {
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
        console.log('Tutor leave tracked');
      } catch (error) {
        console.error('Failed to track tutor leave:', error);
      }
    }
    
    // Generate consistent room name for moderator access
    function generateModeratorRoomName(roomName, meetingId) {
      // Ensure consistent room naming between tutor and students
      // Remove any existing suffixes and add program/meeting context
      const baseRoom = roomName.replace(/\/(tutor|student)$/, '');
      return `tplearn-${currentProgramId}-${meetingId}-${baseRoom}`;
    }
    
    // Open Jitsi Meeting using External API
    function openJitsiMeeting(roomName, meetingId) {
      // Create modal overlay for Jitsi meeting
      const modal = document.createElement('div');
      modal.id = 'jitsi-modal';
      modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75';
      modal.innerHTML = `
        <div class="relative w-full h-full max-w-7xl mx-4">
          <div class="absolute top-4 right-4 z-10 flex items-center space-x-2">
            <button onclick="closeJitsiMeeting()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium">
              Leave Meeting
            </button>
          </div>
          <div id="jitsi-container" class="w-full h-full bg-white rounded-lg overflow-hidden"></div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Generate consistent room name for moderator
      const moderatorRoomName = generateModeratorRoomName(roomName, meetingId);
      
      // Configure Jitsi Meet options for TUTOR (Moderator)
      const options = {
        roomName: moderatorRoomName,
        width: '100%',
        height: '100%',
        parentNode: document.querySelector('#jitsi-container'),
        configOverwrite: {
          startWithAudioMuted: false,
          startWithVideoMuted: false,
          enableWelcomePage: false,
          prejoinPageEnabled: false,
          disableModeratorIndicator: false,
          startScreenSharing: false,
          enableEmailInStats: false,
          // Tutor-specific moderator settings
          startAudioOnly: false,
          enableLayerSuspension: true,
          enableTalkWhileMuted: false,
          enableNoAudioSignal: true,
          enableNoisyMicDetection: true,
          // Security and moderation features
          enableLobbyChat: false,
          enableInsecureRoomNameWarning: false
        },
        interfaceConfigOverwrite: {
          DISABLE_JOIN_LEAVE_NOTIFICATIONS: false,
          DISABLE_PRESENCE_STATUS: false,
          DISPLAY_WELCOME_PAGE_CONTENT: false,
          ENABLE_FEEDBACK_ANIMATION: false,
          FILMSTRIP_ENABLED: true,
          GENERATE_ROOMNAMES_ON_WELCOME_PAGE: false,
          HIDE_INVITE_MORE_HEADER: false,
          JITSI_WATERMARK_LINK: 'https://tplearn.com',
          POLICY_LOGO: null,
          PROVIDER_NAME: 'TPLearn',
          SHOW_JITSI_WATERMARK: false,
          SHOW_WATERMARK_FOR_GUESTS: false,
          SUPPORT_URL: 'https://tplearn.com/support',
          // Full toolbar for tutor/moderator with all controls
          TOOLBAR_BUTTONS: [
            'microphone', 'camera', 'closedcaptions', 'desktop', 'embedmeeting',
            'fullscreen', 'fodeviceselection', 'hangup', 'profile', 'chat',
            'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
            'videoquality', 'filmstrip', 'invite', 'feedback', 'stats', 'shortcuts',
            'tileview', 'videobackgroundblur', 'download', 'help', 'mute-everyone',
            'mute-video-everyone', 'security', 'lobby-mode', 'participants-pane'
          ],
          // Moderator-specific interface settings
          SHOW_CHROME_EXTENSION_BANNER: false,
          DEFAULT_BACKGROUND: '#474747'
        },
        userInfo: {
          displayName: `${currentUserName} (Tutor)`,
          email: currentUserEmail,
          // Mark this user as moderator
          role: 'moderator'
        },
        // Add moderator token or room suffix to ensure moderator rights
        roomSuffix: '/tutor'
      };
      
      // Initialize Jitsi Meet API
      jitsiAPI = new JitsiMeetExternalAPI('meet.jit.si', options);
      
      // Add escape key listener
      document.addEventListener('keydown', handleJitsiEscape);
      
      // Add event listeners with moderator-specific functionality
      jitsiAPI.addEventListener('videoConferenceJoined', () => {
        console.log('Tutor (Moderator) successfully joined the meeting');
        
        // Show success message
        showSuccess('Successfully joined as moderator! You have full control of the session.');
        
        // Set moderator privileges
        try {
          // Grant moderator rights
          jitsiAPI.executeCommand('toggleLobby', true); // Can control lobby
          console.log('Moderator privileges activated');
        } catch (error) {
          console.log('Some moderator features may not be available:', error);
        }
      });
      
      jitsiAPI.addEventListener('videoConferenceLeft', () => {
        console.log('Tutor left the meeting');
        closeJitsiMeeting();
      });
      
      jitsiAPI.addEventListener('readyToClose', () => {
        console.log('Tutor ready to close meeting');
        closeJitsiMeeting();
      });
      
      jitsiAPI.addEventListener('participantJoined', (participant) => {
        console.log('Participant joined the tutor session:', participant);
        // Tutor can see when students join
        if (participant.displayName && !participant.displayName.includes('Tutor')) {
          showInfo(`Student ${participant.displayName} joined the session`);
        }
      });
      
      jitsiAPI.addEventListener('participantLeft', (participant) => {
        console.log('Participant left the tutor session:', participant);
        // Tutor can see when students leave
        if (participant.displayName && !participant.displayName.includes('Tutor')) {
          showWarning(`Student ${participant.displayName} left the session`);
        }
      });
      
      // Moderator-specific event listeners
      jitsiAPI.addEventListener('participantRoleChanged', (event) => {
        console.log('Participant role changed:', event);
      });
      
      jitsiAPI.addEventListener('passwordRequired', () => {
        console.log('Password required for room');
      });
      
      // Show success message
      showInfo('Loading live session...');
    }
    
    // Close Jitsi Meeting
    function closeJitsiMeeting() {
      // Track tutor leaving the session and end the meeting
      if (window.currentMeetingId) {
        // End the meeting (mark as completed)
        endMeetingSession(window.currentMeetingId);
        
        // Track tutor leaving
        trackTutorLeave(window.currentMeetingId);
        window.currentMeetingId = null;
      }
      
      // Stop heartbeat
      stopTutorHeartbeat();
      
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
      
      showSuccess('Live session ended - Meeting marked as completed');
      
      // Refresh the live classes list to show updated status
      setTimeout(() => {
        loadLiveClasses();
      }, 1000);
    }
    
    // End meeting session (mark as completed)
    async function endMeetingSession(meetingId) {
      try {
        const response = await fetch('../../api/jitsi_meetings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=end_meeting&meeting_id=${meetingId}`,
          credentials: 'include'
        });
        
        const result = await response.json();
        
        if (result.success) {
          console.log('Meeting ended successfully');
        } else {
          console.error('Failed to end meeting:', result.message);
        }
      } catch (error) {
        console.error('Error ending meeting:', error);
      }
    }
    
    // Handle escape key to close Jitsi meeting
    function handleJitsiEscape(event) {
      if (event.key === 'Escape' && jitsiAPI) {
        closeJitsiMeeting();
      }
    }
    
    // View Live Class Details
    function viewLiveClassDetails(meetingId) {
      // Create modal for meeting details
      const modal = document.createElement('div');
      modal.id = 'meetingDetailsModal';
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Live Class Details</h3>
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
          
          // Check actual database status first
          const isEnded = meeting.status === 'completed' || meeting.status === 'cancelled' || meeting.status === 'ended';
          
          // Calculate consistent status for details view
          const isLive = !isEnded && meeting.is_live;
          const isUpcoming = !isEnded && meeting.is_upcoming;
          const isPast = isEnded || meeting.is_past;
          const statusColor = getLiveStatusColor(isLive, isUpcoming, isPast);
          let statusText = getStatusText(isLive, isUpcoming, isPast);
          
          // Override status text for explicitly ended meetings
          if (isEnded) {
            statusText = meeting.status === 'completed' ? 'Ended' : 'Cancelled';
          }
          
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
                  <p class="text-gray-600 mt-1">Live Class</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-medium ${statusColor}">
                  ${statusText}
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
                <div class="bg-gray-50 p-3 rounded border flex items-center justify-between">
                  <p class="text-sm font-mono text-gray-600 break-all flex-1 mr-3">${meeting.meeting_url}</p>
                  <button onclick="copyToClipboard('${meeting.meeting_url}')" 
                          class="text-tplearn-green hover:text-green-700 text-sm font-medium">
                    Copy
                  </button>
                </div>
              </div>

              <!-- Tutor Actions -->
              <div class="flex space-x-3 pt-4 border-t">
                ${!isEnded ? `
                  <button onclick="closeMeetingDetailsModal(); joinLiveClass('${meeting.meeting_url}', ${meeting.id}, true)" 
                          class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Join Now
                  </button>
                ` : ''}
                <button onclick="closeMeetingDetailsModal(); editLiveClass(${meeting.id})" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 flex items-center">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                  </svg>
                  Edit
                </button>
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

    function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
        showSuccess('Meeting link copied to clipboard!');
      }).catch(() => {
        showError('Failed to copy to clipboard');
      });
    }
    
    // Delete Live Class
    async function deleteLiveClass(meetingId) {
      if (!confirm('Are you sure you want to delete this live class? This action cannot be undone.')) {
        return;
      }
      
      try {
        const response = await fetch('../../api/jitsi_meetings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=delete&meeting_id=${meetingId}`,
          credentials: 'include'
        });
        
        const result = await response.json();
        
        if (result.success) {
          showSuccess('Live class deleted successfully');
          loadLiveClasses(); // Refresh the list
        } else {
          showError('Error: ' + result.message);
        }
      } catch (error) {
        console.error('Error deleting live class:', error);
        showError('Failed to delete live class. Please try again.');
      }
    }
    
    // Close Live Class Details Modal
    function closeLiveClassDetailsModal() {
      document.getElementById('liveClassDetailsModal').classList.add('hidden');
      document.getElementById('liveClassDetailsModal').classList.remove('flex');
    }

    // Test that JavaScript is working
    console.log('TPLearn Program Stream JavaScript loaded successfully');
    
    // Test if key functions are available
    console.log('Functions available:', {
      viewSubmissions: typeof viewSubmissions,
      editItem: typeof editItem,
      deleteItem: typeof deleteItem,
      gradeSubmission: typeof gradeSubmission,
      openCreateLiveClassModal: typeof openCreateLiveClassModal,
      loadLiveClasses: typeof loadLiveClasses
    });


    
    // Initialize page functionality on load
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, initializing program stream...');
      
      // Load live classes
      loadLiveClasses();
      
      // Check empty state on page load
      checkEmptyState();
      
      // Test if assignment details element exists
      const assignmentDetails = document.getElementById('assignmentDetails');
      if (assignmentDetails) {
        console.log('Assignment details element found');
      } else {
        console.error('Assignment details element NOT found');
      }
      
      // Test material type dropdown
      const materialTypeSelect = document.getElementById('materialType');
      if (materialTypeSelect) {
        console.log('Material type select found');
      } else {
        console.error('Material type select NOT found');
      }
    });
    
    // Add window focus event to refresh live classes when returning to page
    window.addEventListener('focus', function() {
      console.log('Window focused, checking for active meetings...');
      
      // Check if there was an active meeting that might have ended
      const activeMeetingId = sessionStorage.getItem('active_meeting_id');
      if (activeMeetingId) {
        console.log('Active meeting detected, refreshing live classes...');
        // Give it a moment for any background processes to complete
        setTimeout(() => {
          loadLiveClasses();
        }, 500);
      } else {
        // Still refresh to show any status updates
        loadLiveClasses();
      }
    });
    
    // Add visibility change event for better mobile support
    document.addEventListener('visibilitychange', function() {
      if (!document.hidden) {
        console.log('Page became visible, refreshing live classes...');
        setTimeout(() => {
          loadLiveClasses();
        }, 500);
      }
    });

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
  </script>
</body>

</html>