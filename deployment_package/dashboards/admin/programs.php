<?php
// Suppress any potential debug output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to prevent any accidental output
ob_start();

require_once '../../includes/auth.php';
require_once '../../assets/icons.php';
require_once '../../includes/data-helpers.php';
requireRole('admin');

// Clean any accidental output
ob_end_clean();

// Get filter parameters from URL
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

// Build filters array for getPrograms function
$filters = [];
if (!empty($status_filter) && $status_filter !== 'all') {
  $filters['status'] = $status_filter;
}
if (!empty($search_filter)) {
  $filters['search'] = $search_filter;
}

// Get real programs data from database with filters using calculated status
$allPrograms = getProgramsWithCalculatedStatus($filters);
$tutors = getTutors();

// Pagination settings
$itemsPerPage = 9; // 3x3 grid looks good
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;
$total_items = count($allPrograms);
$total_pages = ceil($total_items / $itemsPerPage);

// Get paginated programs
$programs = array_slice($allPrograms, $offset, $itemsPerPage);

// Calculate showing range
$showingFrom = $total_items > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $itemsPerPage, $total_items);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Programs - Admin Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
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

    /* Enhanced hover effects for program cards (matching student page) */
    .program-card-hover {
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .program-card-hover::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
      transition: left 0.5s ease;
      z-index: 1;
      pointer-events: none;
    }

    .program-card-hover:hover::before {
      left: 100%;
    }

    .program-card-hover:hover {
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    /* Smooth image zoom on hover */
    .image-zoom {
      transition: transform 0.4s ease;
    }

    .group:hover .image-zoom {
      transform: scale(1.05);
    }

    /* Glow effect for buttons */
    .btn-glow:hover {
      box-shadow: 0 0 20px rgba(34, 197, 94, 0.4);
    }

    /* Clean and modern program cards */
    .program-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: all 0.3s ease;
    }

    .program-card:hover {
      transform: translateY(-4px);
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/admin-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/header.php';
      
      // Get admin notifications
      $admin_notifications = getAdminNotifications(15);
      
      renderHeader(
        'Programs',
        '',
        'admin',
        $_SESSION['name'] ?? 'Admin',
        $admin_notifications
      );
      ?>

      <!-- Programs Content -->
      <main class="p-6">
        <!-- Search and Filter Section -->
        <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="p-4">
            <form method="GET" action="" class="flex items-center justify-between">
              <div class="flex items-center gap-6 flex-wrap min-w-0">
                <!-- Search Input -->
                <div class="relative flex-shrink-0">
                  <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                  </svg>
                  <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Search programs..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" style="width: 280px;">
                </div>

                <!-- Status Filter -->
                <div class="relative flex-shrink-0" style="min-width: 140px;">
                  <select name="status" id="statusFilter" class="bg-white border border-gray-300 rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-full" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter === 'all' || empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
                    <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                  </select>
                  <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>

              <!-- Clear Filters Button -->
              <a href="?" class="px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                Clear Filters
              </a>
            </form>
          </div>
        </div>

        <!-- Program Count and Page Info -->
        <div class="mb-6 flex justify-between items-center">
          <p class="text-sm text-gray-600">
            Showing <?php echo count($programs); ?> of <?php echo $total_items; ?> programs
          </p>

          <?php if ($total_pages > 1): ?>
            <div class="text-sm text-gray-600">
              Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Programs Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

          <?php if (empty($programs)): ?>
            <!-- Empty State -->
            <div class="col-span-full">
              <?= getEmptyState('programs') ?>
            </div>
          <?php else: ?>
            <?php foreach ($programs as $program):
              // Use calculated status and get badge configuration
              $calculatedStatus = $program['calculated_status'] ?? 'upcoming';
              $status = getStatusBadgeConfig($calculatedStatus);

              // Determine gradient colors based on category (matching student page)
              $categoryGradients = [
                'Technology' => 'from-blue-500 to-purple-600',
                'Mathematics' => 'from-green-500 to-blue-600',
                'Science' => 'from-purple-500 to-pink-600',
                'Language' => 'from-yellow-500 to-orange-600',
                'Arts' => 'from-pink-500 to-red-600',
                'Music' => 'from-indigo-500 to-purple-600',
                'Sports' => 'from-green-500 to-teal-600',
                'Early Childhood' => 'from-tplearn-green to-tplearn-light-green',
                'Primary Education' => 'from-blue-400 to-indigo-500',
                'Language Arts' => 'from-yellow-400 to-orange-500',
                'General' => 'from-gray-500 to-gray-600'
              ];
              $gradient = $categoryGradients[$program['category']] ?? $categoryGradients['General'];
            ?>

              <!-- Program Card -->
              <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 border border-gray-100 hover:border-tplearn-green cursor-pointer transform hover:-translate-y-1 hover:scale-105 group program-card-hover">
                <!-- Program Image -->
                <?php if (!empty($program['cover_image'])): ?>
                  <!-- Use cover image -->
                  <div class="h-48 relative rounded-t-lg overflow-hidden">
                    <img src="../../serve_image.php?file=<?= htmlspecialchars(basename($program['cover_image'])) ?>" 
                         alt="<?= htmlspecialchars($program['name']) ?> cover" 
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110 image-zoom"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <!-- Fallback gradient background (hidden by default) -->
                    <div class="h-48 bg-gradient-to-br <?= $gradient ?> relative rounded-t-lg" style="display: none;">
                      <div class="absolute inset-0 bg-black bg-opacity-10 rounded-t-lg"></div>
                      <div class="absolute inset-0 flex items-center justify-center">
                        <?= icon('book-open', '3xl text-white opacity-80') ?>
                      </div>
                    </div>
                    <div class="absolute inset-0 bg-black bg-opacity-20 rounded-t-lg group-hover:bg-opacity-30 transition-all duration-300"></div>
                    <div class="absolute top-4 left-4">
                      <span class="inline-block <?= $status['bg'] ?> <?= $status['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm transform group-hover:scale-105 transition-transform duration-300">
                        <?= htmlspecialchars($status['label']) ?>
                      </span>
                    </div>
                  </div>
                <?php else: ?>
                  <!-- Use gradient background (fallback) -->
                  <div class="h-48 bg-gradient-to-br <?= $gradient ?> relative rounded-t-lg transition-all duration-300 group-hover:bg-gradient-to-bl">
                    <div class="absolute inset-0 bg-black bg-opacity-10 rounded-t-lg group-hover:bg-opacity-20 transition-all duration-300"></div>
                    <div class="absolute top-4 left-4">
                      <span class="inline-block <?= $status['bg'] ?> <?= $status['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm transform group-hover:scale-105 transition-transform duration-300">
                        <?= htmlspecialchars($status['label']) ?>
                      </span>
                    </div>
                    <!-- Book Icon -->
                    <div class="absolute inset-0 flex items-center justify-center">
                      <div class="transform group-hover:scale-110 transition-transform duration-300">
                        <?= icon('book-open', '3xl text-white opacity-80') ?>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Program Details -->
                <div class="p-6 group-hover:bg-gray-50 transition-colors duration-300">
                  <h3 class="text-lg font-semibold text-gray-900 mb-3 line-clamp-2 group-hover:text-tplearn-green transition-colors duration-300"><?= htmlspecialchars($program['name']) ?></h3>

                  <!-- Program Info -->
                  <div class="space-y-3 mb-4">
                    <div class="flex items-center text-sm text-gray-600 group-hover:text-gray-700 transition-colors duration-300">
                      <?= iconWithSpacing('user', 'sm', 'secondary') ?>
                      <?= htmlspecialchars($program['age_group'] ?? 'All Ages') ?>
                    </div>
                    <div class="flex items-center text-sm text-blue-600 group-hover:text-blue-700 transition-colors duration-300">
                      <span class="font-medium capitalize"><?= htmlspecialchars($program['session_type'] ?? 'In-Person') ?></span>
                      <span class="mx-2">•</span>
                      <span><?= htmlspecialchars($program['location'] ?? 'Location TBD') ?></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                      <?= iconWithSpacing('clock', 'sm', 'secondary') ?>
                      <?php
                      // Calculate duration from start_date and end_date if available
                      $durationText = '8 weeks'; // Default
                      if (!empty($program['start_date']) && !empty($program['end_date'])) {
                        $startDate = new DateTime($program['start_date']);
                        $endDate = new DateTime($program['end_date']);
                        $interval = $startDate->diff($endDate);
                        $weeks = floor($interval->days / 7);
                        $durationText = $weeks > 0 ? $weeks . ' weeks' : $interval->days . ' days';
                      } else {
                        $durationText = (int)$program['duration_weeks'] . ' weeks';
                      }
                      ?>
                      <?= $durationText ?>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                      <?php
                      // Format time properly
                      $startTime = $program['start_time'] ?? '09:00:00';
                      $endTime = $program['end_time'] ?? '10:00:00';
                      $days = $program['days'] ?? 'Mon, Wed, Fri';

                      // Convert 24-hour format to 12-hour format
                      $startTimeFormatted = date('g:i A', strtotime($startTime));
                      $endTimeFormatted = date('g:i A', strtotime($endTime));
                      ?>
                      <?= htmlspecialchars($days) ?> • <?= $startTimeFormatted ?>-<?= $endTimeFormatted ?>
                    </div>
                    <div class="flex items-center text-sm text-gray-600 group-hover:text-gray-700 transition-colors duration-300">
                      <?= iconWithSpacing('users', 'sm', 'secondary') ?>
                      <?= (int)($program['enrolled_count'] ?? 0) ?>/<?= (int)($program['max_students'] ?? 15) ?> students
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2 group-hover:bg-gray-300 transition-colors duration-300">
                        <?php
                        $maxStudents = (int)($program['max_students'] ?? 15);
                        $enrolledCount = (int)($program['enrolled_count'] ?? 0);
                        $percentage = $maxStudents > 0 ? ($enrolledCount / $maxStudents) * 100 : 0;
                        ?>
                        <div class="bg-tplearn-green h-2 rounded-full group-hover:bg-green-500 transition-all duration-300" style="width: <?= min($percentage, 100) ?>%"></div>
                      </div>
                    </div>
                  </div>

                  <!-- Program Description -->
                  <p class="text-sm text-gray-600 mb-4 group-hover:text-gray-700 transition-colors duration-300"><?= htmlspecialchars(substr($program['description'] ?? 'No description available.', 0, 120)) ?><?= strlen($program['description'] ?? '') > 120 ? '...' : '' ?></p>

                  <!-- Price and Action -->
                  <div class="flex items-center justify-between">
                    <div class="text-xl font-bold text-tplearn-green group-hover:text-green-600 transition-colors duration-300">
                      ₱<?= number_format((float)($program['fee'] ?? 0), 0) ?>
                    </div>
                    <button onclick="viewProgramDetails(<?= $program['id'] ?>)" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition-colors btn-glow">
                      Learn More
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <!-- Add Program Card -->
          <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border-2 border-dashed border-gray-300 hover:border-tplearn-green">
            <div class="h-full flex flex-col items-center justify-center p-8 text-center min-h-[400px]">
              <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-6">
                <span class="text-3xl font-bold text-green-600 leading-none" style="margin-top: -2px;">+</span>
              </div>
              <h3 class="text-xl font-semibold text-gray-800 mb-3">Add New Program</h3>
              <p class="text-sm text-gray-600 mb-6 max-w-xs">Create a new tutoring program for students</p>
              <button type="button" onclick="openModal()" class="bg-tplearn-green text-white px-6 py-3 rounded-lg text-sm font-medium hover:bg-green-600 transition-colors relative z-10">
                Create Program
              </button>
            </div>
          </div>

        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="mt-8 flex justify-center">
            <nav class="flex items-center space-x-2">
              <!-- Previous Page -->
              <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                  class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                  <?= actionIcon('chevron-left') ?>
                </a>
              <?php endif; ?>

              <!-- Page Numbers -->
              <?php
              $start_page = max(1, $page - 2);
              $end_page = min($total_pages, $page + 2);

              for ($i = $start_page; $i <= $end_page; $i++):
              ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                  class="px-3 py-2 text-sm border rounded-lg transition-colors
                            <?php echo $i === $page ? 'bg-tplearn-green text-white border-tplearn-green' : 'border-gray-300 hover:bg-gray-50'; ?>">
                  <?php echo $i; ?>
                </a>
              <?php endfor; ?>

              <!-- Next Page -->
              <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                  class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                  <?= actionIcon('chevron-right') ?>
                </a>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>
      </main>
    </div>
  </div>

  <!-- Add New Program Modal -->
  <div id="addProgramModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800">Add New Program</h2>
        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
          <?= actionIcon('x-mark', 'lg') ?>
        </button>
      </div>

      <!-- Modal Body -->
      <form id="addProgramForm" class="p-6 space-y-6" enctype="multipart/form-data">
        <!-- Program Details Section -->
        <div>
          <h3 class="text-lg font-medium text-gray-800 mb-4">Program Details</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Program Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Program Name</label>
              <input type="text" id="programName" name="programName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" placeholder="Enter program name" required>
            </div>

            <!-- Target Age Group -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Target Age Group</label>
              <div class="border border-gray-300 rounded-md p-3 bg-white" id="ageGroupContainer">
                <div class="grid grid-cols-2 gap-2">
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input type="checkbox" name="ageGroups[]" value="3-4" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                    <span class="text-sm text-gray-700">Ages 3-4</span>
                  </label>
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input type="checkbox" name="ageGroups[]" value="4-5" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                    <span class="text-sm text-gray-700">Ages 4-5</span>
                  </label>
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input type="checkbox" name="ageGroups[]" value="5-6" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                    <span class="text-sm text-gray-700">Ages 5-6</span>
                  </label>
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input type="checkbox" name="ageGroups[]" value="6-7" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                    <span class="text-sm text-gray-700">Ages 6-7</span>
                  </label>
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input type="checkbox" name="ageGroups[]" value="7-8" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                    <span class="text-sm text-gray-700">Ages 7-8</span>
                  </label>
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input type="checkbox" name="ageGroups[]" value="8-9" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                    <span class="text-sm text-gray-700">Ages 8-9</span>
                  </label>
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input type="checkbox" name="ageGroups[]" value="9-10" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                    <span class="text-sm text-gray-700">Ages 9-10</span>
                  </label>
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input type="checkbox" name="ageGroups[]" value="10+" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                    <span class="text-sm text-gray-700">Ages 10+</span>
                  </label>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1" id="ageGroupHelpText">Select one or more age groups for this program</p>
            </div>
          </div>

          <!-- Description -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" placeholder="Enter program description" required></textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <!-- Program Fee -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Program Fee (₱)</label>
              <input type="number" id="programFee" name="programFee" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" placeholder="0.00" min="0" step="0.01" required>
            </div>

            <!-- Max Students per Class -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Max Students per Class</label>
              <input type="number" id="maxStudents" name="maxStudents" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" placeholder="15" min="1" max="50" required>
            </div>
          </div>

          <!-- Program Cover Image -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Program Cover Image (Optional)</label>
            <div class="flex items-center space-x-4">
              <div class="flex-1">
                <input type="file" id="coverImage" name="coverImage" accept="image/*" class="hidden" onchange="previewProgramCover(this)">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-tplearn-green transition-colors cursor-pointer" onclick="document.getElementById('coverImage').click()">
                  <div id="coverImagePreview" class="hidden">
                    <img id="previewImg" src="" alt="Cover Preview" class="max-w-full max-h-32 mx-auto rounded-lg object-cover">
                    <p id="fileName" class="text-sm text-gray-600 mt-2"></p>
                  </div>
                  <div id="uploadPlaceholder" class="text-gray-500">
                    <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <p class="text-sm">Click to upload cover image</p>
                    <p class="text-xs text-gray-400">PNG, JPG, GIF up to 5MB</p>
                  </div>
                </div>
              </div>
              <button type="button" id="removeCoverBtn" class="hidden px-3 py-2 text-sm bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors" onclick="removeProgramCover()">
                Remove
              </button>
            </div>
          </div>

          <!-- Session Type -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Session Type</label>
            <select id="sessionType" name="sessionType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" required>
              <option value="">Select Session Type</option>
              <option value="in-person">In-Person</option>
              <option value="online">Online</option>
              <option value="hybrid">Hybrid</option>
            </select>
          </div>

          <!-- Location -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
            <input type="text" id="location" name="location" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" placeholder="Enter location or 'Online'" required>
          </div>
        </div>

        <!-- Assigned Tutor -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Assigned Tutor (Optional)</label>
          <select id="assignedTutor" name="assignedTutor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
            <option value="">Select Tutor (Optional)</option>
            <?php foreach ($tutors as $tutor): ?>
              <option value="<?php echo htmlspecialchars($tutor['id']); ?>">
                <?php echo htmlspecialchars($tutor['name']); ?>
                <?php if (!empty($tutor['specialization']) && $tutor['specialization'] !== 'General'): ?>
                  - <?php echo htmlspecialchars($tutor['specialization']); ?>
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Schedule Settings -->
        <div>
          <h3 class="text-lg font-medium text-gray-800 mb-4">Schedule Settings</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Start Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
              <input type="date" id="startDate" name="startDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" required>
            </div>

            <!-- End Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
              <input type="date" id="endDate" name="endDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" required>
            </div>
          </div>

          <!-- Days of the Week -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-3">Days of the Week</label>
            <div class="flex flex-wrap gap-2">
              <label class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-100">
                <input type="checkbox" name="days[]" value="monday" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                <span class="text-sm text-gray-700">Mon</span>
              </label>
              <label class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-100">
                <input type="checkbox" name="days[]" value="tuesday" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                <span class="text-sm text-gray-700">Tue</span>
              </label>
              <label class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-100">
                <input type="checkbox" name="days[]" value="wednesday" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                <span class="text-sm text-gray-700">Wed</span>
              </label>
              <label class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-100">
                <input type="checkbox" name="days[]" value="thursday" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                <span class="text-sm text-gray-700">Thu</span>
              </label>
              <label class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-100">
                <input type="checkbox" name="days[]" value="friday" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                <span class="text-sm text-gray-700">Fri</span>
              </label>
              <label class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-100">
                <input type="checkbox" name="days[]" value="saturday" class="text-tplearn-green focus:ring-tplearn-green border-gray-300 rounded">
                <span class="text-sm text-gray-700">Sat</span>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <!-- Start Time -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
              <input type="time" id="startTime" name="startTime" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" required>
            </div>

            <!-- End Time -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">End Time</label>
              <input type="time" id="endTime" name="endTime" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" required>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
          <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-6 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors">
            Save Program
          </button>
        </div>
      </form>
      </div>
    </div>
  </div>

  <!-- Program Details Modal -->
  <div id="programDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800" id="programDetailsTitle">Program Details</h2>
        <button onclick="closeProgramDetailsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
          <?= actionIcon('x-mark', 'lg') ?>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6" id="programDetailsContent">
        <!-- Program header with image/gradient -->
        <div class="mb-6 relative h-48 rounded-lg overflow-hidden" id="programDetailsHeader">
          <div class="absolute inset-0 bg-black bg-opacity-20"></div>
          <div class="absolute inset-0 flex items-center justify-center">
            <?= icon('book-open', '3xl text-white opacity-80') ?>
          </div>
          <div class="absolute top-4 left-4" id="programDetailsStatus">
            <!-- Status badge will be inserted here -->
          </div>
        </div>

        <!-- Program Information Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <!-- Left Column -->
          <div class="space-y-4">
            <div>
              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Program Description</h3>
              <p class="text-gray-900" id="programDetailsDescription">Loading...</p>
            </div>

            <div>
              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Target Age Group</h3>
              <p class="text-gray-900 flex items-center">
                <?= iconWithSpacing('user', 'sm', 'muted') ?>
                <span id="programDetailsAgeGroup">Loading...</span>
              </p>
            </div>

            <div>
              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Session Details</h3>
              <div class="space-y-2">
                <p class="text-gray-900 flex items-center">
                  <?= iconWithSpacing('map-pin', 'sm', 'muted') ?>
                  <span id="programDetailsSessionType">Loading...</span> • <span id="programDetailsLocation">Loading...</span>
                </p>
                <p class="text-gray-900 flex items-center">
                  <?= iconWithSpacing('clock', 'sm', 'muted') ?>
                  <span id="programDetailsSchedule">Loading...</span>
                </p>
                <p class="text-gray-900 flex items-center">
                  <?= iconWithSpacing('calendar', 'sm', 'muted') ?>
                  <span id="programDetailsDuration">Loading...</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="space-y-4">
            <div>
              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Program Fee</h3>
              <p class="text-2xl font-bold text-tplearn-green" id="programDetailsFee">Loading...</p>
            </div>

            <div>
              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Enrollment Status</h3>
              <div class="space-y-2">
                <p class="text-gray-900 flex items-center">
                  <?= iconWithSpacing('users', 'sm', 'muted') ?>
                  <span id="programDetailsEnrollment">Loading...</span>
                </p>
                <div class="w-full bg-gray-200 rounded-full h-3">
                  <div class="bg-tplearn-green h-3 rounded-full transition-all duration-300" id="programDetailsProgress" style="width: 0%"></div>
                </div>
              </div>
            </div>

            <div id="programDetailsVideoSection" class="hidden">
              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Video Call Link</h3>
              <div class="flex items-center space-x-2">
                <?= icon('video-camera', 'w-4 h-4 text-blue-500') ?>
                <a href="#" id="programDetailsVideoLink" target="_blank" class="text-blue-600 hover:text-blue-800 underline text-sm">Join Video Call</a>
              </div>
            </div>

            <div>
              <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Program Dates</h3>
              <div class="space-y-1">
                <p class="text-gray-900 text-sm">
                  <span class="font-medium">Start:</span> <span id="programDetailsStartDate">Loading...</span>
                </p>
                <p class="text-gray-900 text-sm">
                  <span class="font-medium">End:</span> <span id="programDetailsEndDate">Loading...</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
          <button onclick="closeProgramDetailsModal()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
            Close
          </button>
          <button id="editProgramFromDetailsBtn" onclick="editProgramFromDetails()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <?= icon('pencil', 'w-4 h-4 inline mr-2') ?>
            Edit Program
          </button>
          <button id="deleteProgramFromDetailsBtn" onclick="deleteProgramFromDetails()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
            <?= icon('trash', 'w-4 h-4 inline mr-2') ?>
            Delete Program
          </button>
        </div>
      </div>
    </div>
    </div>
  </div>

  <script>
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, initializing functions...');
    });

    // Modal functionality
    function openModal() {
      console.log('openModal called');
      document.getElementById('addProgramModal').classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }

    function closeModal() {
      console.log('closeModal called');
      document.getElementById('addProgramModal').classList.add('hidden');
      document.body.style.overflow = 'auto';

      // Reset form and edit mode
      const form = document.getElementById('addProgramForm');
      form.reset();
      form.removeAttribute('data-edit-id');
      form.removeAttribute('data-current-cover');
      form.removeAttribute('data-remove-cover');
      document.querySelector('#addProgramModal h2').textContent = 'Add New Program';
      
      // Reset cover image preview
      removeProgramCover();
    }

    // Close modal when clicking outside
    document.getElementById('addProgramModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });

    // Program Cover Image Functions
    function previewProgramCover(input) {
      const file = input.files[0];
      if (file) {
        // Validate file type
        if (!file.type.startsWith('image/')) {
          TPAlert.warning('Invalid File', 'Please select a valid image file (PNG, JPG, GIF).');
          input.value = '';
          return;
        }

        // Validate file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
          TPAlert.warning('File Too Large', 'Image size must be less than 5MB.');
          input.value = '';
          return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('previewImg').src = e.target.result;
          document.getElementById('fileName').textContent = file.name;
          document.getElementById('coverImagePreview').classList.remove('hidden');
          document.getElementById('uploadPlaceholder').classList.add('hidden');
          document.getElementById('removeCoverBtn').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
      }
    }

    function removeProgramCover() {
      document.getElementById('coverImage').value = '';
      document.getElementById('previewImg').src = '';
      document.getElementById('fileName').textContent = '';
      document.getElementById('coverImagePreview').classList.add('hidden');
      document.getElementById('uploadPlaceholder').classList.remove('hidden');
      document.getElementById('removeCoverBtn').classList.add('hidden');
      
      // If in edit mode, mark that cover should be removed
      const form = document.getElementById('addProgramForm');
      if (form.getAttribute('data-edit-id')) {
        form.setAttribute('data-remove-cover', 'true');
        console.log('Edit mode: Cover image marked for removal');
      }
    }

    // Video conferencing functionality has been removed

    // Form submission
    document.getElementById('addProgramForm').addEventListener('submit', function(e) {
      e.preventDefault();

      // Get form data
      const initialFormData = new FormData(this);
      const data = {};

      // Convert form data to object
      for (let [key, value] of initialFormData.entries()) {
        if (key === 'days[]') {
          if (!data.days) data.days = [];
          data.days.push(value);
        } else if (key === 'ageGroups[]') {
          if (!data.ageGroups) data.ageGroups = [];
          data.ageGroups.push(value);
        } else {
          data[key] = value;
        }
      }

      // Debug: Check if programName field exists and has value
      console.log('Form submission started');
      console.log('programName field value:', document.getElementById('programName').value);
      console.log('Raw form data from FormData:', data);

      // Process age groups array into readable format
      let ageGroupString = 'All Ages'; // Default
      if (data.ageGroups && data.ageGroups.length > 0) {
        ageGroupString = data.ageGroups.map(age => `Ages ${age}`).join(', ');
      }

      // Process days array into readable format
      let daysString = 'Mon, Wed, Fri'; // Default
      if (data.days && data.days.length > 0) {
        const dayMapping = {
          'monday': 'Mon',
          'tuesday': 'Tue',
          'wednesday': 'Wed',
          'thursday': 'Thu',
          'friday': 'Fri',
          'saturday': 'Sat',
          'sunday': 'Sun'
        };
        daysString = data.days.map(day => dayMapping[day] || day).join(', ');
      }

      // Calculate duration in weeks from start and end dates
      let calculatedDuration = 8; // Default
      if (data.startDate && data.endDate) {
        const startDate = new Date(data.startDate);
        const endDate = new Date(data.endDate);
        const timeDiff = endDate.getTime() - startDate.getTime();
        const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
        calculatedDuration = Math.max(1, Math.ceil(daysDiff / 7));
      }

      // Map form field names to API expected names
      const mappedData = {
        title: data.programName,
        description: data.description,
        fee: data.programFee,
        start_date: data.startDate,
        end_date: data.endDate,
        max_students: data.maxStudents,
        tutor_id: data.assignedTutor,
        status: data.status || 'active',
        days: daysString,
        age_group: ageGroupString,
        location: data.location,
        session_type: data.sessionType,
        start_time: data.startTime,
        end_time: data.endTime,
        duration_weeks: calculatedDuration
      };

      // Debug: Log the form data and mapped data
      console.log('Original form data:', data);
      console.log('Mapped data:', mappedData);

      // Basic validation
      if (!mappedData.title || !mappedData.description || !mappedData.fee) {
        TPAlert.warning('Required', 'Please fill in all required fields.');
        console.log('Validation failed:', {
          title: mappedData.title,
          description: mappedData.description,
          fee: mappedData.fee
        });
        return;
      }

      if (!data.ageGroups || data.ageGroups.length === 0) {
        TPAlert.warning('Required', 'Please select at least one age group.');
        return;
      }

      if (!data.days || data.days.length === 0) {
        TPAlert.warning('Required', 'Please select at least one day of the week.');
        return;
      }

      // Check if we're in edit mode
      const editId = this.getAttribute('data-edit-id');
      const isEditMode = editId !== null && editId !== '';

      console.log('Edit mode check:', {
        editId,
        isEditMode
      });

      // Real-time program creation/update using API
      const formData = new FormData();
      formData.append('title', mappedData.title);
      formData.append('description', mappedData.description);
      formData.append('age_group', mappedData.age_group);
      formData.append('fee', mappedData.fee);
      formData.append('category', 'General'); // Default category
      formData.append('difficulty_level', 'beginner'); // Default difficulty
      formData.append('max_students', mappedData.max_students);
      formData.append('session_type', mappedData.session_type);
      formData.append('location', mappedData.location);
      formData.append('start_date', mappedData.start_date);
      formData.append('end_date', mappedData.end_date);
      formData.append('start_time', mappedData.start_time);
      formData.append('end_time', mappedData.end_time);
      formData.append('days', mappedData.days);
      formData.append('duration_weeks', mappedData.duration_weeks);
      formData.append('tutor_id', mappedData.tutor_id);

      // Handle cover image
      const coverImageInput = document.getElementById('coverImage');
      const currentCover = document.getElementById('addProgramForm').getAttribute('data-current-cover');
      const removeCover = document.getElementById('addProgramForm').getAttribute('data-remove-cover');
      
      if (coverImageInput.files && coverImageInput.files[0]) {
        // New file selected - use the new file
        formData.append('cover_image', coverImageInput.files[0]);
        console.log('New cover image added to form data:', coverImageInput.files[0].name);
      } else if (isEditMode && removeCover === 'true') {
        // Edit mode and user removed the cover image - set to null
        formData.append('remove_cover_image', 'true');
        console.log('Cover image marked for removal');
      } else if (isEditMode && currentCover) {
        // Edit mode with existing cover image and no changes - preserve existing
        formData.append('existing_cover_image', currentCover);
        console.log('Preserving existing cover image:', currentCover);
      }

      // Debug: Log all form data entries
      console.log('FormData being sent:');
      for (let [key, value] of formData.entries()) {
        console.log(`${key}:`, value);
      }

      if (isEditMode) {
        console.log('Edit mode: Adding ID', editId);
        // Add the ID to FormData for edit operations
        formData.append('id', editId);
        formData.append('_method', 'PUT'); // Indicate this is an update operation
      } else {
        console.log('Create mode: No ID needed');
      }

      // Use POST for both create and edit to avoid multipart parsing issues with PUT
      const url = '../../api/programs_crud.php';

      fetch(url, {
          method: 'POST',
          body: formData
        })
        .then(response => {
          console.log('Response status:', response.status);
          return response.json();
        })
        .then(result => {
          console.log('API Response:', result);
          if (result.success) {
            TPAlert.success('Success', isEditMode ? 'Program updated successfully!' : 'Program created successfully!');
            closeModal();
            // Add delay before refresh to allow users to see the success message
            setTimeout(() => {
              window.location.reload();
            }, 2000); // 2 second delay
          } else {
            // More detailed error message
            console.error('API Error Details:', result);
            const errorMessage = result.message || 'Unknown error occurred';
            TPAlert.info('Information', `Error ${isEditMode ? 'updating' : 'creating'} program:\n\n${errorMessage}\n\nPlease check the console for more details.`);
          }
        })
        .catch(error => {
          console.error('Network/Parse Error:', error);
          TPAlert.info('Information', `Network or server error ${isEditMode ? 'updating' : 'creating'} program:\n\n${error.message}\n\nPlease try again or contact support.`);
        });
    });

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('startDate').min = today;
    document.getElementById('endDate').min = today;

    // Update end date minimum when start date changes
    document.getElementById('startDate').addEventListener('change', function() {
      document.getElementById('endDate').min = this.value;
    });

    // Delete program function
    function deleteProgram(programId, programTitle) {
      console.log('deleteProgram called with ID:', programId, 'Title:', programTitle);

      // Enhanced confirmation dialog with SweetAlert2
      const confirmMessage = `<div class="text-left">
        <strong>Program:</strong> "${programTitle}"<br>
        <strong>ID:</strong> ${programId}<br><br>
        <div class="text-red-600 font-semibold">This action will:</div>
        <ul class="list-disc ml-6 mt-2">
          <li>Permanently delete the program</li>
          <li>Remove all associated data</li>
          <li>Cannot be undone</li>
        </ul>
      </div>`;

      Swal.fire({
        title: '⚠️ Delete Program Confirmation',
        html: confirmMessage,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Delete Program!',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        focusCancel: true
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading state
          Swal.fire({
            title: 'Deleting Program...',
            text: `Please wait while we delete "${programTitle}"`,
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
              Swal.showLoading();
            }
          });

          // Send DELETE request
          fetch(`../../api/programs_crud.php?id=${programId}`, {
              method: 'DELETE'
            })
            .then(response => response.json())
            .then(result => {
              if (result.success) {
                // Success notification
                Swal.fire({
                  title: 'Deleted Successfully!',
                  text: `Program "${programTitle}" has been deleted successfully.`,
                  icon: 'success',
                  confirmButtonColor: '#10b981',
                  timer: 2000,
                  timerProgressBar: true
                }).then(() => {
                  // Refresh the page to show updated list
                  window.location.reload();
                });
              } else {
                // Error notification
                Swal.fire({
                  title: 'Delete Failed',
                  text: result.message || 'Unknown error occurred. Please try again or contact support.',
                  icon: 'error',
                  confirmButtonColor: '#dc2626'
                });
              }
            })
            .catch(error => {
              console.error('Delete error:', error);
              Swal.fire({
                title: 'Network Error',
                text: 'A network or server error occurred. Please check your connection and try again.',
                icon: 'error',
                confirmButtonColor: '#dc2626'
              });
            });
        } else {
          console.log('Delete operation cancelled by user');
        }
      });
    }

    // Make deleteProgram globally available
    window.deleteProgram = deleteProgram;

    // Edit program function - opens modal with pre-filled data
    function editProgram(programId) {
      console.log('editProgram called with ID:', programId);
      // First fetch the program data
      fetch(`../../api/programs_crud.php?action=get&id=${programId}`)
        .then(response => {
          console.log('Edit Response status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(result => {
          console.log('Edit API Response:', result);
          if (result.success && result.program) {
            const program = result.program;

            // Pre-fill the form with existing data
            document.getElementById('programName').value = program.name || '';
            document.getElementById('description').value = program.description || '';
            document.getElementById('programFee').value = program.fee || '';
            document.getElementById('maxStudents').value = program.max_students || '';
            document.getElementById('sessionType').value = program.session_type || '';
            document.getElementById('location').value = program.location || '';
            document.getElementById('startDate').value = program.start_date || '';
            document.getElementById('endDate').value = program.end_date || '';
            document.getElementById('startTime').value = program.start_time || '';
            document.getElementById('endTime').value = program.end_time || '';
            document.getElementById('assignedTutor').value = program.tutor_id || '';

            // Handle age group checkboxes
            const ageGroups = (program.age_group || '').toLowerCase();
            
            // First uncheck all age group checkboxes
            document.querySelectorAll('input[name="ageGroups[]"]').forEach(checkbox => {
              checkbox.checked = false;
            });

            // Parse the age group string and check appropriate boxes
            if (ageGroups && ageGroups !== 'all ages') {
              // Extract age ranges from the string (e.g., "Ages 3-4, Ages 5-6" -> ["3-4", "5-6"])
              const ageRanges = ageGroups.match(/(\d+-\d+|\d+\+)/g) || [];
              ageRanges.forEach(range => {
                const checkbox = document.querySelector(`input[name="ageGroups[]"][value="${range}"]`);
                if (checkbox) {
                  checkbox.checked = true;
                }
              });
            }

            // Handle days checkboxes
            const days = (program.days || '').toLowerCase();

            // Create mapping from display format to checkbox values
            const dayMap = {
              'mon': 'monday',
              'tue': 'tuesday',
              'wed': 'wednesday',
              'thu': 'thursday',
              'fri': 'friday',
              'sat': 'saturday',
              'sun': 'sunday'
            };

            // First uncheck all checkboxes
            document.querySelectorAll('input[name="days[]"]').forEach(checkbox => {
              checkbox.checked = false;
            });

            // Then check the appropriate ones
            Object.keys(dayMap).forEach(shortDay => {
              if (days.includes(shortDay)) {
                const checkbox = document.querySelector(`input[name="days[]"][value="${dayMap[shortDay]}"]`);
                if (checkbox) {
                  checkbox.checked = true;
                }
              }
            });

            // Handle existing cover image
            if (program.cover_image && program.cover_image.trim() !== '') {
              // Construct image URL using serve script
              const imageUrl = `../../serve_image.php?file=${encodeURIComponent(program.cover_image.split('/').pop())}`;
              
              // Show existing cover image in preview
              document.getElementById('previewImg').src = imageUrl;
              document.getElementById('fileName').textContent = 'Current cover image';
              document.getElementById('coverImagePreview').classList.remove('hidden');
              document.getElementById('uploadPlaceholder').classList.add('hidden');
              document.getElementById('removeCoverBtn').classList.remove('hidden');
              
              // Store the current cover image path for potential reuse
              document.getElementById('addProgramForm').setAttribute('data-current-cover', program.cover_image);
              console.log('Edit mode: Existing cover image loaded:', program.cover_image);
              console.log('Edit mode: Display URL:', imageUrl);
            } else {
              // No existing cover image, show upload placeholder
              removeProgramCover();
              document.getElementById('addProgramForm').removeAttribute('data-current-cover');
              console.log('Edit mode: No existing cover image');
            }

            // Change form to edit mode
            document.getElementById('addProgramForm').setAttribute('data-edit-id', programId);
            document.getElementById('addProgramForm').removeAttribute('data-remove-cover');
            document.querySelector('#addProgramModal h2').textContent = 'Edit Program';

            // Open the modal
            openModal();
          } else {
            console.error('Edit API Error:', result);
            alert('Error loading program data: ' + (result.message || 'Program not found'));
          }
        })
        .catch(error => {
          console.error('Edit Error:', error);
          TPAlert.info('Information', `Error loading program data: ${error.message}. Please try again.`);
        });
    }

    // View program details function - Opens detailed modal
    function viewProgramDetails(programId) {
      console.log('viewProgramDetails called with ID:', programId);

      // Fetch program data and populate modal
      fetch(`../../api/programs_crud.php?action=get&id=${programId}`)
        .then(response => {
          console.log('Response status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(result => {
          console.log('API Response:', result);
          if (result.success && result.program) {
            const program = result.program;

            // Populate modal with program data
            populateProgramDetailsModal(program);

            // Open the modal
            document.getElementById('programDetailsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
          } else {
            console.error('API Error:', result);
            alert('Error loading program details: ' + (result.message || 'Program not found'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          TPAlert.info('Information', `Error loading program details: ${error.message}. Please try again.`);
        });
    }

    // Close program details modal
    function closeProgramDetailsModal() {
      document.getElementById('programDetailsModal').classList.add('hidden');
      document.body.style.overflow = 'auto';
    }

    // Helper function to safely set element content                                   
    function safeSetContent(elementId, content) {
      const element = document.getElementById(elementId);
      if (element) {
        element.textContent = content;
      } else {
        console.warn(`Element with ID '${elementId}' not found`);
      }
    }

    // Helper function to safely set element HTML
    function safeSetHTML(elementId, html) {
      const element = document.getElementById(elementId);
      if (element) {
        element.innerHTML = html;
      } else {
        console.warn(`Element with ID '${elementId}' not found`);
      }
    }

    // Populate the program details modal with data
    function populateProgramDetailsModal(program) {
      try {
        // Validate program object
        if (!program || typeof program !== 'object') {
          throw new Error('Invalid program data provided');
        }

        // Set title
        safeSetContent('programDetailsTitle', program.name || 'Untitled Program');

        // Set header gradient based on category, or use cover image if available
        const categoryGradients = {
          'Technology': 'from-blue-500 to-purple-600',
          'Mathematics': 'from-green-500 to-blue-600',
          'Science': 'from-purple-500 to-pink-600',
          'Language': 'from-yellow-500 to-orange-600',
          'Arts': 'from-pink-500 to-red-600',
          'Music': 'from-indigo-500 to-purple-600',
          'Sports': 'from-green-500 to-teal-600',
          'General': 'from-gray-500 to-gray-600'
        };
        const gradient = categoryGradients[program.category] || categoryGradients['General'];
        const headerElement = document.getElementById('programDetailsHeader');
        
        if (headerElement) {
          if (program.cover_image && program.cover_image.trim() !== '') {
            // Use cover image - serve via image script
            const imageUrl = `../../serve_image.php?file=${encodeURIComponent(program.cover_image.split('/').pop())}`;
            headerElement.className = 'mb-6 relative h-48 rounded-lg overflow-hidden';
            headerElement.innerHTML = `
              <img src="${imageUrl}" alt="${program.name} cover" class="w-full h-full object-cover"
                   onerror="console.error('Failed to load modal image:', this.src); this.style.display='none';">
              <div class="absolute inset-0 bg-black bg-opacity-20"></div>
              <div class="absolute top-4 left-4" id="programDetailsStatus">
                <!-- Status badge will be inserted here -->
              </div>
            `;
          } else {
            // Use gradient background (fallback)
            headerElement.className = `mb-6 relative h-48 rounded-lg overflow-hidden bg-gradient-to-br ${gradient}`;
            headerElement.innerHTML = `
              <div class="absolute inset-0 bg-black bg-opacity-20"></div>
              <div class="absolute inset-0 flex items-center justify-center">
                <svg class="w-12 h-12 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253z"></path>
                </svg>
              </div>
              <div class="absolute top-4 left-4" id="programDetailsStatus">
                <!-- Status badge will be inserted here -->
              </div>
            `;
          }
        }

        // Set status badge using calculated status
        const statusColors = {
          'upcoming': {
            bg: 'bg-blue-100',
            text: 'text-blue-800',
            label: 'Upcoming'
          },
          'ongoing': {
            bg: 'bg-green-100',
            text: 'text-green-800',
            label: 'Ongoing'
          },
          'ended': {
            bg: 'bg-gray-100',
            text: 'text-gray-800',
            label: 'Ended'
          }
        };
        const status = statusColors[program.calculated_status] || statusColors['upcoming'];
        safeSetHTML('programDetailsStatus', `
        <span class="inline-block ${status.bg} ${status.text} text-xs px-3 py-1 rounded-full font-medium">
          ${status.label}
        </span>
      `);

        // Populate fields
        safeSetContent('programDetailsDescription', program.description || 'No description available.');
        safeSetContent('programDetailsAgeGroup', program.age_group || 'All Ages');
        safeSetContent('programDetailsSessionType', (program.session_type || 'In-Person').charAt(0).toUpperCase() + (program.session_type || 'In-Person').slice(1));
        safeSetContent('programDetailsLocation', program.location || 'Location TBD');

        // Format schedule
        const startTime = program.start_time ? new Date('2000-01-01 ' + program.start_time).toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit'
        }) : '9:00 AM';
        const endTime = program.end_time ? new Date('2000-01-01 ' + program.end_time).toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit'
        }) : '10:00 AM';
        const days = program.days || 'Mon, Wed, Fri';
        safeSetContent('programDetailsSchedule', `${days} • ${startTime} - ${endTime}`);

        // Duration
        safeSetContent('programDetailsDuration', `${program.duration_weeks || 8} weeks program`);

        // Fee
        safeSetContent('programDetailsFee', `₱${Number(program.fee || 0).toLocaleString()}`);

        // Enrollment
        const maxStudents = parseInt(program.max_students) || 15;
        const enrolledCount = parseInt(program.enrolled_count) || 0;
        const percentage = maxStudents > 0 ? (enrolledCount / maxStudents) * 100 : 0;
        safeSetContent('programDetailsEnrollment', `${enrolledCount}/${maxStudents} students enrolled`);

        const progressElement = document.getElementById('programDetailsProgress');
        if (progressElement) {
          progressElement.style.width = `${Math.min(percentage, 100)}%`;
        }

        // Dates
        const startDate = program.start_date ? new Date(program.start_date).toLocaleDateString() : 'Not set';
        const endDate = program.end_date ? new Date(program.end_date).toLocaleDateString() : 'Not set';
        safeSetContent('programDetailsStartDate', startDate);
        safeSetContent('programDetailsEndDate', endDate);

        // Store program ID for action buttons (with null checks)
        const editBtn = document.getElementById('editProgramFromDetailsBtn');
        const deleteBtn = document.getElementById('deleteProgramFromDetailsBtn');

        if (editBtn) {
          editBtn.setAttribute('data-program-id', program.id);
        }
        if (deleteBtn) {
          deleteBtn.setAttribute('data-program-id', program.id);
          deleteBtn.setAttribute('data-program-title', program.name || 'Untitled Program');
        }
      } catch (error) {
        console.error('Error populating program details modal:', error);
        TPAlert.info('Information', `Error displaying program details: ${error.message}`);
      }
    }

    // Edit program from details modal
    function editProgramFromDetails() {
      const editBtn = document.getElementById('editProgramFromDetailsBtn');
      if (!editBtn) {
        console.error('Edit button not found');
        return;
      }
      const programId = editBtn.getAttribute('data-program-id');
      if (programId) {
        closeProgramDetailsModal();
        editProgram(programId);
      } else {
        console.error('Program ID not found');
      }
    }

    // Delete program from details modal
    function deleteProgramFromDetails() {
      const deleteBtn = document.getElementById('deleteProgramFromDetailsBtn');
      if (!deleteBtn) {
        console.error('Delete button not found');
        return;
      }
      const programId = deleteBtn.getAttribute('data-program-id');
      const programTitle = deleteBtn.getAttribute('data-program-title');
      if (programId) {
        closeProgramDetailsModal();
        deleteProgram(programId, programTitle || 'Untitled Program');
      } else {
        console.error('Program ID not found');
      }
    }

    // Make functions globally available
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.deleteProgram = deleteProgram;
    window.editProgram = editProgram;
  // Expose modal and action functions to global scope so inline onclick handlers work
  window.openModal = openModal;
  window.closeModal = closeModal;
  window.deleteProgram = deleteProgram;
  window.editProgram = editProgram;
  window.viewProgramDetails = viewProgramDetails;
  window.closeProgramDetailsModal = closeProgramDetailsModal;
  window.editProgramFromDetails = editProgramFromDetails;
  window.deleteProgramFromDetails = deleteProgramFromDetails;

    // Log that functions are loaded
    console.log('All functions loaded and made global:', {
      openModal: typeof window.openModal,
      closeModal: typeof window.closeModal,
      deleteProgram: typeof window.deleteProgram,
      editProgram: typeof window.editProgram,
      viewProgramDetails: typeof window.viewProgramDetails,
      closeProgramDetailsModal: typeof window.closeProgramDetailsModal
    });

    // Add click outside to close modal functionality
    document.getElementById('programDetailsModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeProgramDetailsModal();
      }
    });

    // Age Group checkboxes enhancement - visual feedback for multiple selections
    document.addEventListener('DOMContentLoaded', function() {
      const ageGroupCheckboxes = document.querySelectorAll('input[name="ageGroups[]"]');
      const ageGroupContainer = document.getElementById('ageGroupContainer');
      const helpText = document.getElementById('ageGroupHelpText');
      
      if (ageGroupCheckboxes.length > 0 && ageGroupContainer && helpText) {
        // Function to update visual feedback
        function updateAgeGroupFeedback() {
          const checkedBoxes = document.querySelectorAll('input[name="ageGroups[]"]:checked');
          
          if (checkedBoxes.length > 0) {
            // Update container border to green
            ageGroupContainer.classList.add('border-tplearn-green');
            ageGroupContainer.classList.remove('border-gray-300');
            
            // Update help text to show selected groups
            const selectedAges = Array.from(checkedBoxes).map(cb => `Ages ${cb.value}`).join(', ');
            helpText.textContent = `Selected: ${selectedAges}`;
            helpText.classList.remove('text-gray-500');
            helpText.classList.add('text-tplearn-green');
          } else {
            // Reset to default state
            ageGroupContainer.classList.remove('border-tplearn-green');
            ageGroupContainer.classList.add('border-gray-300');
            
            helpText.textContent = 'Select one or more age groups for this program';
            helpText.classList.add('text-gray-500');
            helpText.classList.remove('text-tplearn-green');
          }
        }

        // Add event listeners to all checkboxes
        ageGroupCheckboxes.forEach(checkbox => {
          checkbox.addEventListener('change', updateAgeGroupFeedback);
        });

        // Initial update
        updateAgeGroupFeedback();
      }
    });
  </script>
  
  <!-- TPAlert Implementation with SweetAlert2 -->
  <script>
    const TPAlert = {
      info: function(title, message) {
        return Swal.fire({
          title: title,
          text: message,
          icon: 'info',
          confirmButtonText: 'OK',
          confirmButtonColor: '#10b981'
        });
      },
      warning: function(title, message) {
        return Swal.fire({
          title: title,
          text: message,
          icon: 'warning',
          confirmButtonText: 'OK',
          confirmButtonColor: '#f59e0b'
        });
      },
      confirm: function(title, message) {
        return Swal.fire({
          title: title,
          html: message.replace(/\n/g, '<br>'),
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, Delete!',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          customClass: {
            confirmButton: 'swal2-confirm',
            cancelButton: 'swal2-cancel'
          }
        });
      },
      success: function(title, message) {
        return Swal.fire({
          title: title,
          text: message,
          icon: 'success',
          confirmButtonText: 'OK',
          confirmButtonColor: '#10b981',
          timer: 3000,
          timerProgressBar: true
        });
      },
      error: function(title, message) {
        return Swal.fire({
          title: title,
          text: message,
          icon: 'error',
          confirmButtonText: 'OK',
          confirmButtonColor: '#dc2626'
        });
      }
    };

    // Make TPAlert globally available
    window.TPAlert = TPAlert;
  </script>
  
  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>
</body>

</html>