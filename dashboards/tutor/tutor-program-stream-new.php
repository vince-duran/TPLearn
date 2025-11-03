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

// Get materials for this program - using direct SQL query for reliability
$materials_query = "
    SELECT 
        pm.id as material_id,
        pm.title,
        pm.description,
        pm.material_type,
        pm.is_required,
        pm.sort_order,
        pm.created_at,
        f.id as file_id,
        f.filename,
        f.original_filename,
        f.file_path,
        f.file_size,
        f.mime_type,
        f.upload_type,
        u.username as uploader_username,
        CASE 
            WHEN u.role = 'tutor' THEN tp.first_name
            ELSE u.username
        END as uploader_first_name,
        CASE 
            WHEN u.role = 'tutor' THEN tp.last_name
            ELSE ''
        END as uploader_last_name
    FROM program_materials pm
    INNER JOIN file_uploads f ON pm.file_upload_id = f.id
    INNER JOIN users u ON f.user_id = u.id
    LEFT JOIN tutor_profiles tp ON u.id = tp.user_id AND u.role = 'tutor'
    WHERE pm.program_id = ?
    ORDER BY pm.created_at DESC, pm.sort_order ASC
";

$stmt = $conn->prepare($materials_query);
$stmt->bind_param('i', $program_id);
$stmt->execute();
$result = $stmt->get_result();

$materials = [];
while ($row = $result->fetch_assoc()) {
    $row['uploader_name'] = trim($row['uploader_first_name'] . ' ' . $row['uploader_last_name']) ?: $row['uploader_username'];
    $row['file_size_formatted'] = formatFileSize($row['file_size']);
    $row['upload_date_formatted'] = date('M j, Y', strtotime($row['created_at']));
    $row['upload_time_formatted'] = date('M j, Y, g:i A', strtotime($row['created_at']));
    $materials[] = $row;
}

// Debug logging
error_log("=== TUTOR PROGRAM STREAM DEBUG ===");
error_log("Program ID: " . $program_id);
error_log("Materials count: " . count($materials));
if (!empty($materials)) {
    error_log("First material: " . print_r($materials[0], true));
}

// Helper function to get material type color and icon
function getMaterialTypeDisplay($type) {
    $displays = [
        'document' => ['color' => 'blue', 'icon' => 'M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z'],
        'video' => ['color' => 'purple', 'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
        'image' => ['color' => 'green', 'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
        'slides' => ['color' => 'orange', 'icon' => 'M9 17V7h2v10h-2zM20 3H4a1 1 0 00-1 1v16a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1z'],
        'assignment' => ['color' => 'purple', 'icon' => 'M9 2a1 1 0 000 2h2a1 1 0 100-2H9z M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z'],
        'assessment' => ['color' => 'red', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'other' => ['color' => 'gray', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z']
    ];
    return $displays[$type] ?? $displays['other'];
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round(($bytes / pow($k, $i)), 2) . ' ' . $sizes[$i];
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
                        <button onclick="window.location.href='tutor-programs.php'" class="mr-4 p-2 hover:bg-gray-100 rounded-lg">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        <h1 class="text-xl font-semibold text-gray-900">Program Stream</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Tutor') ?></span>
                        <div class="w-8 h-8 bg-tplearn-green text-white rounded-full flex items-center justify-center text-sm font-medium">
                            <?= strtoupper(substr($_SESSION['name'] ?? 'T', 0, 1)) ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Program Header -->
            <div class="bg-white border-b border-gray-200 px-4 lg:px-6 py-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($program_name) ?></h2>
                        <p class="text-gray-600 mt-1"><?= htmlspecialchars($program_description) ?></p>
                    </div>
                    <button onclick="openUploadModal()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Upload Material
                    </button>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="bg-white border-b border-gray-200 px-4 lg:px-6">
                <nav class="-mb-px flex space-x-8">
                    <button onclick="filterContent('all')" class="filter-tab border-tplearn-green text-tplearn-green whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        All Materials (<?= count($materials) ?>)
                    </button>
                    <button onclick="filterContent('documents')" class="filter-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Documents
                    </button>
                    <button onclick="filterContent('assignments')" class="filter-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Assignments
                    </button>
                    <button onclick="filterContent('videos')" class="filter-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Videos
                    </button>
                </nav>
            </div>

            <!-- Main Content -->
            <main class="p-4 lg:p-6">
                <div class="space-y-6">
                    <?php if (!empty($materials)): ?>
                        <?php foreach ($materials as $material): ?>
                            <?php 
                                $display = getMaterialTypeDisplay($material['material_type']);
                                $color = $display['color'];
                                $icon = $display['icon'];
                                
                                // Determine filter type for JS
                                $filter_type = 'documents';
                                if ($material['material_type'] === 'assignment' || $material['material_type'] === 'assessment') {
                                    $filter_type = 'assignments';
                                } elseif ($material['material_type'] === 'video') {
                                    $filter_type = 'videos';
                                }
                            ?>
                            <div class="content-item bg-white rounded-lg border border-gray-200 p-6 hover:shadow-md transition-shadow" data-type="<?= $filter_type ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start space-x-4 flex-1">
                                        <!-- Material Icon -->
                                        <div class="w-12 h-12 bg-<?= $color ?>-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6 text-<?= $color ?>-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="<?= $icon ?>" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>

                                        <!-- Material Info -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <h3 class="text-lg font-semibold text-gray-900 truncate"><?= htmlspecialchars($material['title']) ?></h3>
                                                <span class="bg-<?= $color ?>-100 text-<?= $color ?>-800 px-2 py-1 rounded text-xs font-medium">
                                                    <?= ucfirst($material['material_type']) ?>
                                                </span>
                                                <?php if ($material['is_required']): ?>
                                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Required</span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($material['description'])): ?>
                                                <p class="text-gray-600 text-sm mb-3"><?= htmlspecialchars($material['description']) ?></p>
                                            <?php endif; ?>

                                            <!-- File Info -->
                                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                                <span class="flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                    <?= htmlspecialchars($material['original_filename']) ?>
                                                </span>
                                                <span><?= $material['file_size_formatted'] ?></span>
                                                <span>Uploaded <?= $material['upload_date_formatted'] ?></span>
                                                <span>by <?= htmlspecialchars($material['uploader_name']) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center space-x-2 ml-4">
                                        <button onclick="viewItem(<?= $material['material_id'] ?>, '<?= $material['material_type'] ?>')" 
                                                class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" 
                                                title="View Details">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </button>
                                        <button onclick="downloadItem(<?= $material['file_id'] ?>)" 
                                                class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" 
                                                title="Download">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </button>
                                        <button onclick="editItem(<?= $material['material_id'] ?>)" 
                                                class="p-2 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors" 
                                                title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button onclick="deleteItem(<?= $material['material_id'] ?>)" 
                                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" 
                                                title="Delete">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- No Materials State -->
                        <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Materials Yet</h3>
                            <p class="text-gray-600 mb-4">You haven't uploaded any materials for this program yet.</p>
                            <button onclick="openUploadModal()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                                Upload First Material
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Upload Modal -->
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
                    <div class="space-y-4">
                        <div>
                            <label for="materialTitle" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" id="materialTitle" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green">
                        </div>
                        <div>
                            <label for="materialDescription" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                            <textarea id="materialDescription" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green"></textarea>
                        </div>
                        <div>
                            <label for="materialType" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select id="materialType" name="material_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green">
                                <option value="document">Document</option>
                                <option value="video">Video</option>
                                <option value="image">Image</option>
                                <option value="slides">Slides</option>
                                <option value="assignment">Assignment</option>
                                <option value="assessment">Assessment</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="materialFile" class="block text-sm font-medium text-gray-700 mb-1">File</label>
                            <input type="file" id="materialFile" name="file" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeUploadModal()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-700">Upload Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer" class="fixed top-4 right-4 z-[9999] space-y-3"></div>

    <script>
        // Notification System
        function showNotification(message, type = 'info', duration = 5000) {
            const container = document.getElementById('notificationContainer');
            if (!container) return;

            const notification = document.createElement('div');
            const notificationId = 'notification-' + Date.now();
            notification.id = notificationId;
            
            const typeStyles = {
                'success': 'bg-green-50 border-green-200 text-green-800',
                'error': 'bg-red-50 border-red-200 text-red-800',
                'warning': 'bg-yellow-50 border-yellow-200 text-yellow-800',
                'info': 'bg-blue-50 border-blue-200 text-blue-800'
            };

            notification.className = `${typeStyles[type]} border rounded-lg shadow-lg p-4 min-w-80 max-w-md transform transition-all duration-300 ease-in-out translate-x-full opacity-0`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">${message}</div>
                    <button onclick="closeNotification('${notificationId}')" class="ml-4 text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;

            container.appendChild(notification);

            setTimeout(() => {
                notification.classList.remove('translate-x-full', 'opacity-0');
                notification.classList.add('translate-x-0', 'opacity-100');
            }, 100);

            if (duration > 0) {
                setTimeout(() => closeNotification(notificationId), duration);
            }

            return notificationId;
        }

        function closeNotification(notificationId) {
            const notification = document.getElementById(notificationId);
            if (notification) {
                notification.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => notification.remove(), 300);
            }
        }

        // Filter Content
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

        // Upload Modal Functions
        function openUploadModal() {
            document.getElementById('uploadModal').classList.remove('hidden');
            document.getElementById('uploadModal').classList.add('flex');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('uploadModal').classList.remove('flex');
            document.getElementById('uploadForm').reset();
        }

        // Submit Upload
        function submitUpload(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('program_id', <?= $program_id ?>);
            formData.append('title', document.getElementById('materialTitle').value);
            formData.append('description', document.getElementById('materialDescription').value);
            formData.append('material_type', document.getElementById('materialType').value);
            formData.append('file', document.getElementById('materialFile').files[0]);

            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Uploading...';

            fetch('../../api/upload-program-material.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Material uploaded successfully!', 'success');
                    closeUploadModal();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(data.error || 'Upload failed', 'error');
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                showNotification('Upload failed. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

        // Material Actions
        function viewItem(materialId, type) {
            showNotification(`Viewing ${type} material...`, 'info');
        }

        function downloadItem(fileId) {
            const url = `../../api/serve-program-file.php?file_id=${fileId}&action=download`;
            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            link.click();
            showNotification('Download started...', 'success');
        }

        function editItem(materialId) {
            showNotification('Edit functionality coming soon...', 'info');
        }

        function deleteItem(materialId) {
            TPAlert.confirm('Confirm Action', 'Are you sure you want to delete this material? This action cannot be undone.').then(result => {
        if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('material_id', materialId);

                fetch('../../api/delete-program-material.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Material deleted successfully!', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showNotification(data.error || 'Delete failed', 'error');
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    showNotification('Delete failed. Please try again.', 'error');
                });
            }
        }

        // Initialize page
        console.log('TPLearn Tutor Program Stream loaded successfully');
        console.log('Materials count:', <?= count($materials) ?>);
    </script>
</body>
</html>