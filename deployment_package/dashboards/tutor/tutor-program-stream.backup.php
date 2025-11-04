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
                    <input type="text" id="attendanceSearch" placeholder="Search students..."
                      class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <select id="attendanceFilter" class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                      <option value="all">All Students</option>
                      <option value="present">Present</option>
                      <option value="absent">Absent</option>
                      <option value="late">Late</option>
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

  <script>
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
        }

        item.style.display = shouldShow ? 'block' : 'none';
        if (shouldShow) visibleCount++;
      });
      
      console.log(`Showing ${visibleCount} items for filter: ${type}`);
    }

    // View item
    function viewItem(id, type) {
      // Simple view implementation - could be enhanced to load material details via API
      document.getElementById('viewMaterialTitle').textContent = 'Material Details';
      document.getElementById('viewMaterialType').textContent = type || 'Document';
      document.getElementById('viewFileName').textContent = 'Loading...';
      document.getElementById('viewFileSize').textContent = '';
      document.getElementById('viewUploadDate').textContent = '';
      document.getElementById('viewUploadedBy').textContent = '';
      document.getElementById('viewStatus').textContent = 'Active';
      document.getElementById('viewStatus').className = 'bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium';
      document.getElementById('viewCount').textContent = '0';
      document.getElementById('downloadCount').textContent = '0';
      document.getElementById('lastAccessed').textContent = 'N/A';
      document.getElementById('viewDescription').textContent = 'Material details would be loaded here...';

      // Show modal
      document.getElementById('viewMaterialModal').classList.remove('hidden');
      document.getElementById('viewMaterialModal').classList.add('flex');
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
      fetch(`../../api/get-program-material.php?material_id=${id}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const material = data.material;
            
            // Populate form fields
            document.getElementById('editMaterialTitle').value = material.title;
            document.getElementById('editMaterialDescription').value = material.description || '';
            
            // Update current file info
            document.getElementById('editCurrentFileName').textContent = material.file.original_name || material.file.filename;
            document.getElementById('editCurrentFileSize').textContent = `${material.file.file_size_formatted} • Uploaded ${material.file.upload_date}`;
            
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

      // Update status
      setTimeout(() => {
        document.getElementById('deleteStatus').textContent = 'Cleaning up data...';
      }, 800);

      // Submit delete request to API
      fetch('../../api/delete-program-material.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        document.getElementById('deleteStatus').textContent = 'Finalizing deletion...';
        
        setTimeout(() => {
          document.getElementById('deleteProgress').remove();
          
          if (data.success) {
            TPAlert.info('Information', `Material deleted successfully.\n\nThe material has been permanently removed and is no longer accessible to students.`);
            
            // Refresh the page to show updated data
            location.reload();
          } else {
            alert('Error deleting material: ' + (data.message || 'Unknown error'));
          }
        }, 800);
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('deleteProgress').remove();
        TPAlert.error('Error', 'Error deleting material. Please try again.');
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
      TPAlert.confirm('Confirm Action', 'Mark all students as present for this session?').then(result => {
        if (result.isConfirmed) {
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
        TPAlert.info('Information', `Attendance report exported successfully!\n\nFile: ${sessionId}_attendance_report.xlsx\n\nReport includes:\n- Student attendance status\n- Join times\n- Session statistics\n- Attendance summary`);
      }, 1600);
    }

    // Send absentee notices
    function sendAbsenteeNotices() {
      const absentStudents = document.querySelectorAll('.attendance-item[data-status="absent"]');

      if (absentStudents.length === 0) {
        showInfo('No absent students to notify.');
        return;
      }

      TPAlert.confirm('Confirm Action', `Send absence notifications to ${absentStudents.length} students?`).then(result => {
        if (result.isConfirmed) {
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
          TPAlert.info('Information', `Absence notifications sent successfully!\n\n${absentStudents.length} students notified via email.`);
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
          ${submission.file_name ? `<button onclick="downloadSubmission('${submission.submission_id}')" class="text-green-600 hover:text-green-900">Download</button>` : ''}
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
        const response = await fetch(`../api/get-submission-details.php?submission_id=${submissionId}`);
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const submission = await response.json();
        
        // Update modal with submission data
        document.getElementById('gradingStudentInfo').textContent = `${submission.student_name} - ${submission.material_title}`;
        document.getElementById('gradingMaxScore').textContent = `${submission.max_score} points`;
        document.getElementById('gradingSubmissionDate').textContent = submission.submitted_date_formatted;
        document.getElementById('gradingSubmissionStatus').textContent = submission.is_late ? 'Late' : 'On Time';
        document.getElementById('gradingFileName').textContent = submission.file_name || 'No file submitted';
        
        // Store submission ID for form submission
        document.getElementById('individualGradingForm').setAttribute('data-submission-id', submissionId);
        
        // If already graded, populate the form
        if (submission.grade !== null) {
          document.getElementById('studentScore').value = submission.grade;
          document.getElementById('studentFeedback').value = submission.feedback || '';
          updateGradeDisplay(submission.grade);
        } else {
          // Reset form for new grading
          document.getElementById('individualGradingForm').reset();
          document.getElementById('gradeLetterDisplay').textContent = '-';
          document.getElementById('gradeDescription').textContent = 'Enter score to see grade';
        }
        
      } catch (error) {
        console.error('Error loading submission data:', error);
        TPAlert.error('Error', 'Error loading submission data. Please try again.');
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
        
        const response = await fetch('../api/submit-grade.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            submission_id: submissionId,
            grade: parseFloat(score),
            feedback: feedback
          })
        });
        
        const result = await response.json();
        
        if (!response.ok) {
          throw new Error(result.error || 'Failed to submit grade');
        }
        
        // Show success message
        showSuccess(`Grade submitted successfully! Score: ${score}/100 (${result.grade_letter})`);
        
        // Close modal
        closeIndividualGradingModal();
        
        // Update the submissions table to reflect the new grade
        updateSubmissionInTable(submissionId, score, result.grade_letter);
        
        // Reset button state
        submitButton.textContent = originalText;
        submitButton.disabled = false;
        
      } catch (error) {
        console.error('Error submitting grade:', error);
        showError(`Failed to submit grade: ${error.message}`);
        
        // Reset button state
        const submitButton = event.target.querySelector('button[type="submit"]');
        submitButton.textContent = 'Submit Grade';
        submitButton.disabled = false;
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
      showInfo('Opening submission preview...');
      // This would open a file preview modal or new tab
    }

    // Download individual submission
    function downloadIndividualSubmission() {
      const fileName = document.getElementById('gradingFileName').textContent;
      showSuccess(`Downloading ${fileName}...`);
      // This would trigger the actual file download
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

    // Download individual submission
    function downloadSubmission(submissionId) {
      // Use the serve-submission-file.php endpoint to download the submission file
      window.location.href = `../api/serve-submission-file.php?id=${submissionId}`;
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
        TPAlert.warning('Required', 'Please enter a grade before submitting.');
        return;
      }

      // Show success message
      TPAlert.info('Information', `Grade submitted successfully!\nGrade: ${grade}%\nFeedback: ${feedback.substring(0, 50)}${feedback.length > 50 ? '...' : ''}`);

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
        TPAlert.warning('Required', 'Please provide a reason for changing the grade.');
        return;
      }

      TPAlert.info('Information', `Grade updated successfully!\nNew Grade: ${newGrade}%\nReason: ${reason}`);
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
              <p class="text-sm text-gray-700 mb-2">Problem 1: Solve x² + 5x + 6 = 0</p>
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
      TPAlert.info('Information', `Downloading ${fileName}...`);
      // In real app, would trigger actual file download
    }

    // Download all submissions
    function downloadAllSubmissions() {
      // Show confirmation dialog
      TPAlert.confirm('Confirm Action', 'Download all student submissions as a ZIP file?').then(result => {
        if (result.isConfirmed) {
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
            TPAlert.success('Success', 'All submissions downloaded successfully!\nFile: Week3_Assignment_Submissions.zip');
          }, 500);
        }, 2000);
      }
    }

    // Export grades
    function exportGrades() {
      // Show export options
      const exportOptions = (await TPAlert.confirm('Confirm Action', 'Export grades to CSV?\n\nOK = CSV Format\nCancel = PDF Report')).isConfirmed;

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
          TPAlert.info('Information', `Grades exported successfully!\nFile: Week3_Assignment_Grades.${format.toLowerCase()}\n\nExported data includes:\n- Student names\n- Grades\n- Submission dates\n- Feedback summary`);
        }, 1600);
      }
    }

    // Upload modal
    function openUploadModal() {
      document.getElementById('uploadModal').classList.remove('hidden');
      document.getElementById('uploadModal').classList.add('flex');
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

      // Validate title
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

      // Add material ID to form data
      formData.append('material_id', materialId);

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

      // Submit to API
      fetch('../../api/update-program-material.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
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
        console.error('Error:', error);
        showError('Error updating material. Please try again.');
      })
      .finally(() => {
        // Reset button state
        submitButton.disabled = false;
        submitButton.textContent = originalText;
      });
    }

    // Close view material modal
    function closeViewMaterialModal() {
      document.getElementById('viewMaterialModal').classList.add('hidden');
      document.getElementById('viewMaterialModal').classList.remove('flex');
    }

    // Download from view modal
    function downloadMaterialFromView() {
      const fileName = document.getElementById('viewFileName').textContent;
      downloadItem(fileName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, ''));
    }

    // Edit from view modal
    function editMaterialFromView() {
      const fileName = document.getElementById('viewFileName').textContent;
      const materialId = fileName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
      closeViewMaterialModal();
      editItem(materialId);
    }

    // Handle material type change from dropdown
    function handleMaterialTypeChange(value) {
      console.log('Material type changed to:', value);
      if (value === 'assignment') {
        console.log('Showing assignment details');
        document.getElementById('assignmentDetails').classList.remove('hidden');
      } else {
        console.log('Hiding assignment details');
        document.getElementById('assignmentDetails').classList.add('hidden');
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
      TPAlert.info('Information', 'Opening notifications...');
    }

    function openMessages() {
      TPAlert.info('Information', 'Opening messages...');
    }

    // Back to dashboard
    function backToDashboard() {
      window.location.href = 'tutor.php';
    }

    // Function to download files
    function downloadItem(fileId) {
      const downloadUrl = `../../api/serve-program-file.php?file_id=${fileId}&action=download`;
      const link = document.createElement('a');
      link.href = downloadUrl;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // Test that JavaScript is working
    console.log('TPLearn Program Stream JavaScript loaded successfully');
    
    // Test if key functions are available
    console.log('Functions available:', {
      viewSubmissions: typeof viewSubmissions,
      editItem: typeof editItem,
      deleteItem: typeof deleteItem,
      gradeSubmission: typeof gradeSubmission
    });
    
    // Test assignment functionality on page load
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, testing assignment functionality...');
      
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
  </script>
</body>

</html>
