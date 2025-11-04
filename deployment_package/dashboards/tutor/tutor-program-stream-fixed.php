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

// Get materials for this program
$materials = getProgramMaterials($program_id, 'program_material');

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
  
</head>

<body class="bg-gray-50 min-h-screen">
  <div class="flex">
    <?php include '../../includes/tutor-sidebar.php'; ?>

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
            <a href="tutor-programs.php" class="flex items-center text-gray-600 hover:text-gray-900">
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
              <span class="text-sm font-medium text-gray-700">Maria Santos</span>
              <div class="w-8 h-8 bg-tplearn-green rounded-full flex items-center justify-center text-white font-semibold text-sm">
                M
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Program Header -->
      <div class="bg-white border-b border-gray-200 px-4 lg:px-6 py-6">
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($program_name) ?> Stream</h1>
            <p class="text-gray-600"><?= htmlspecialchars($program_description) ?></p>
          </div>
          <div class="flex items-center space-x-3 ml-6">
            <button onclick="openUploadModal()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors flex items-center">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
              </svg>
              Upload Material
            </button>
            <button onclick="backToDashboard()" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors flex items-center">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
              </svg>
              Back to Dashboard
            </button>
          </div>
        </div>
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
        </nav>
      </div>

      <!-- Main Content -->
      <main class="p-4 lg:p-6">
        <div class="space-y-6">

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
                      <span class="text-sm text-gray-500">File:</span>
                      <span class="text-sm font-medium text-gray-600"><?= htmlspecialchars($material['original_name']) ?></span>
                      <span class="text-sm text-gray-500">by</span>
                      <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($material['uploader_name']) ?></span>
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
          else: 
          ?>
          <!-- No materials uploaded yet -->
          <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Materials Yet</h3>
            <p class="text-gray-600">You haven't uploaded any materials for this program yet.</p>
            <button onclick="openUploadModal()" class="mt-4 bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
              Upload First Material
            </button>
          </div>
          <?php endif; ?>

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
              <option value="image">Image</option>
              <option value="slides">Slides</option>
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
                <p id="editCurrentFileSize" class="text-sm text-gray-600">1.5 MB • Uploaded May 20, 2025</p>
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
  <div id="viewMaterialModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[95vh] overflow-y-auto">
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

            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Statistics</h4>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-600">Views:</span>
                  <span id="viewCount" class="font-medium">127</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Downloads:</span>
                  <span id="downloadCount" class="font-medium">43</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Last accessed:</span>
                  <span id="lastAccessed" class="font-medium">2 hours ago</span>
                </div>
              </div>
            </div>

            <div class="space-y-2">
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
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Preview</h4>
              <div id="viewPreviewContent" class="min-h-96 bg-white rounded border-2 border-dashed border-gray-300 flex items-center justify-center">
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
                    </select>
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
                  <button onclick="downloadSubmission('juan_quadratic_equations.pdf')" class="text-green-600 hover:text-green-900">Download</button>
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
                <p class="text-sm text-gray-600">2.3 MB</p>
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

