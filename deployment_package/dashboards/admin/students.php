<?php
// Suppress any potential debug output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to prevent any accidental output
ob_start();

require_once __DIR__ . '/../../assets/icons.php';
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
require_once '../../includes/ui-components.php';
requireRole('admin');

// Clean any accidental output
ob_end_clean();

// Get filter parameters from URL
$search_filter = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$program_filter = $_GET['program'] ?? '';

// Pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10; // Students per page
$offset = ($page - 1) * $limit;

// Build filters array
$filters = [];
if (!empty($search_filter)) {
  $filters['search'] = $search_filter;
}
if (!empty($status_filter) && $status_filter !== 'all') {
  $filters['status'] = $status_filter;
}
if (!empty($program_filter) && $program_filter !== 'all') {
  $filters['program'] = $program_filter;
}

// Get students data from database with filters and pagination
$students = getStudentsWithFilters($filters, $limit, $offset);
$totalStudents = getTotalStudentsCount($filters);
$studentsCount = count($students);

// Calculate pagination info
$totalPages = ceil($totalStudents / $limit);
$showingFrom = $totalStudents > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $limit, $totalStudents);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Students - Admin Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/standard-ui.css">
  
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

    .program-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease;
    }

    .program-card:hover {
      transform: translateY(-2px);
    }

    /* Custom styles for student rows */
    .student-row {
      transition: all 0.2s ease;
    }

    .student-row:hover {
      background-color: #f9fafb;
    }

    /* Grade badge styles */
    .grade-a-plus {
      background-color: #d1fae5;
      color: #065f46;
    }

    .grade-b-plus {
      background-color: #dbeafe;
      color: #1e40af;
    }

    .grade-a-minus {
      background-color: #fef3c7;
      color: #92400e;
    }

    .grade-c-plus {
      background-color: #fed7d7;
      color: #c53030;
    }

    /* Status badge styles */
    .status-active {
      background-color: #d1fae5;
      color: #065f46;
    }

    .status-paused {
      background-color: #fef3c7;
      color: #92400e;
    }

    .status-inactive {
      background-color: #f3f4f6;
      color: #6b7280;
    }

    .status-completed {
      background-color: #e0e7ff;
      color: #3730a3;
    }

    .status-pending {
      background-color: #dbeafe;
      color: #1e40af;
    }

    /* Program badge styles */
    .program-active {
      background-color: #d1fae5;
      color: #065f46;
    }

    .program-multiple {
      background-color: #e0e7ff;
      color: #3730a3;
    }

    .program-none {
      background-color: #f3f4f6;
      color: #6b7280;
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
        'Students',
        '',
        'admin',
        $_SESSION['username'] ?? 'Admin',
        $admin_notifications
      );
      ?>

      <!-- Students Content -->
      <main class="p-4 sm:p-6">
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
                  <input type="text" name="search" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Search students..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" style="width: 280px;">
                </div>

                <!-- Status Filter -->
                <div class="relative flex-shrink-0" style="min-width: 140px;">
                  <select name="status" class="bg-white border border-gray-300 rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-full" onchange="this.form.submit()" style="position: relative; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: none;">
                    <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
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

        <!-- Results Summary -->
        <div class="mb-4 flex justify-between items-center">
          <div class="text-sm text-gray-600">
            Showing <?php echo $showingFrom; ?> to <?php echo $showingTo; ?> of <?php echo $totalStudents; ?> students
            <span class="ml-2 text-xs text-blue-600">(Found: <?php echo count($students); ?> students)</span>
          </div>
        </div>

        <!-- Students Table -->
        <div class="tplearn-table-container">
          <div class="overflow-x-auto -mx-4 sm:mx-0">
            <div class="inline-block min-w-full align-middle">
              <table class="min-w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Information</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Details</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Program</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (empty($students)): ?>
                    <tr>
                      <td colspan="5" class="px-6 py-12 text-center">
                        <div class="mx-auto max-w-sm">
                          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                          </svg>
                          <h3 class="mt-2 text-sm font-medium text-gray-900">No students found</h3>
                          <p class="mt-1 text-sm text-gray-500">There are no students matching your current filters.</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($students as $index => $student):
                      // Generate avatar colors - All avatars are now green
                      $avatarColors = ['bg-green-500'];
                      $avatarColor = $avatarColors[0];

                      // Get first letter of full name for avatar
                      $fullName = isset($student['first_name']) && isset($student['last_name']) 
                        ? $student['first_name'] . ' ' . $student['last_name']
                        : $student['username'];
                      $initial = strtoupper(substr($fullName, 0, 1));

                      // Use full name as display name
                      $displayName = $fullName;
                      
                      // Determine status styles using calculated status
                      $statusStyles = [
                        'active' => 'status-active',
                        'inactive' => 'status-inactive',
                        'paused' => 'status-paused',
                        'completed' => 'status-completed',
                        'pending' => 'status-pending'
                      ];
                      $statusClass = $statusStyles[$student['calculated_status'] ?? 'inactive'] ?? 'status-inactive';

                      // Determine program badge style and text
                      $programCount = $student['enrolled_programs'] ?? 0;
                      $activeProgramCount = $student['active_programs'] ?? 0;
                      
                      if ($programCount == 0) {
                        $programClass = 'program-none';
                        $programText = 'No Programs';
                      } else if ($activeProgramCount > 0) {
                        $programClass = 'program-active';
                        $programText = $activeProgramCount . ' Active';
                      } else if ($programCount == 1) {
                        $programClass = 'program-none';
                        $programText = '1 Program';
                      } else {
                        $programClass = 'program-multiple';
                        $programText = $programCount . ' Programs';
                      }

                      // Status display text
                      $statusText = ucfirst($student['calculated_status'] ?? 'Unknown');
                      
                      // Format contact number for display
                      $contactNumber = !empty($student['contact_number']) ? $student['contact_number'] : 'No Contact Number';
                    ?>

                      <tr class="student-row">
                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="w-10 h-10 <?= $avatarColor ?> rounded-full flex items-center justify-center flex-shrink-0">
                              <span class="text-white font-medium text-sm"><?= $initial ?></span>
                            </div>
                            <div class="ml-4">
                              <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($displayName) ?></div>
                              <div class="text-sm text-gray-500"><?= htmlspecialchars($student['user_id'] ?: $student['username']) ?></div>
                            </div>
                          </div>
                        </td>
                        <td class="px-4 sm:px-6 py-4">
                          <div class="text-sm text-gray-500 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                              <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                            </svg>
                            <?= htmlspecialchars($student['email']) ?>
                          </div>
                          <div class="text-sm text-gray-500 flex items-center mt-1">
                            <svg class="w-3 h-3 mr-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                            </svg>
                            <?= htmlspecialchars($contactNumber) ?>
                          </div>
                        </td>
                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap hidden md:table-cell">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $activeProgramCount > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $programText ?>
                          </span>
                        </td>
                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                          <?php 
                          $statusVariants = [
                            'active' => 'bg-green-100 text-green-800',
                            'inactive' => 'bg-gray-100 text-gray-800',
                            'paused' => 'bg-yellow-100 text-yellow-800',
                            'completed' => 'bg-blue-100 text-blue-800',
                            'pending' => 'bg-blue-100 text-blue-800'
                          ];
                          $statusClass = $statusVariants[$student['calculated_status'] ?? 'inactive'] ?? 'bg-gray-100 text-gray-800';
                          ?>
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                            <?= $statusText ?>
                          </span>
                        </td>
                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <div class="flex space-x-4">
                            <button onclick="viewStudentDetails(<?= $student['id'] ?>)" 
                                    class="text-blue-600 hover:text-blue-900 font-medium">
                              View
                            </button>
                            <div class="relative">
                              <button onclick="toggleMoreOptions(<?= $student['id'] ?>)" 
                                      id="more-btn-<?= $student['id'] ?>"
                                      class="text-gray-600 hover:underline font-medium">
                                More
                              </button>
                              <!-- Dropdown Menu -->
                              <div id="more-menu-<?= $student['id'] ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                                <div class="py-1">
                                  <button onclick="viewEnrollments(<?= $student['id'] ?>)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 block">
                                    View Enrollments
                                  </button>
                                  <button onclick="viewPayments(<?= $student['id'] ?>)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 block">
                                    View Payments
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pagination -->
          <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
              <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                  Previous
                </a>
              <?php else: ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-400 bg-gray-50 cursor-not-allowed">
                  Previous
                </span>
              <?php endif; ?>
              
              <?php if ($page < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                  Next
                </a>
              <?php else: ?>
                <span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-400 bg-gray-50 cursor-not-allowed">
                  Next
                </span>
              <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Showing <span class="font-medium"><?php echo $showingFrom; ?></span> to <span class="font-medium"><?php echo $showingTo; ?></span> of <span class="font-medium"><?php echo $totalStudents; ?></span> results
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                      <span class="sr-only">Previous</span>
                      <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                      </svg>
                    </a>
                  <?php else: ?>
                    <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-300 cursor-not-allowed">
                      <span class="sr-only">Previous</span>
                      <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                      </svg>
                    </span>
                  <?php endif; ?>
                  
                  <?php 
                  // Show page numbers
                  $startPage = max(1, $page - 2);
                  $endPage = min($totalPages, $page + 2);
                  
                  for ($i = $startPage; $i <= $endPage; $i++): 
                  ?>
                    <?php if ($i == $page): ?>
                      <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-tplearn-green text-sm font-medium text-white">
                        <?php echo $i; ?>
                      </span>
                    <?php else: ?>
                      <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <?php echo $i; ?>
                      </a>
                    <?php endif; ?>
                  <?php endfor; ?>
                  
                  <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                      <span class="sr-only">Next</span>
                      <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                      </svg>
                    </a>
                  <?php else: ?>
                    <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-300 cursor-not-allowed">
                      <span class="sr-only">Next</span>
                      <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                      </svg>
                    </span>
                  <?php endif; ?>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Student Details Modal -->
  <div id="studentDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center p-4 min-h-screen">
      <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50 flex-shrink-0">
        <h2 class="text-xl font-semibold text-gray-800 flex items-center">
          <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
          </svg>
          Student Details
        </h2>
        <button onclick="closeStudentDetailsModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto p-6" id="studentDetailsContent">
        <div class="flex items-center justify-center p-8">
          <div class="animate-spin h-8 w-8 border-4 border-blue-600 border-t-transparent rounded-full"></div>
          <span class="ml-3 text-gray-600">Loading student details...</span>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-3 sm:space-y-0 sm:space-x-3 p-6 border-t border-gray-200 bg-gray-50 flex-shrink-0">
        <div class="flex flex-wrap gap-2">
          <button onclick="editStudentFromModal()" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Edit Student
          </button>
          
          <button onclick="viewEnrollmentsFromModal()" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            View Enrollments
          </button>
          
          <button onclick="viewPaymentsFromModal()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6M9 12h6m-6 4h3m-6 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            View Payments
          </button>
        </div>
        
        <div class="flex justify-end">
          <button onclick="closeStudentDetailsModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
    </div>
  </div>

  <!-- JavaScript Functions -->
  <script>
    // Define all functions immediately so they're available for onclick handlers
    window.viewStudentDetails = function(studentId) {
      // Store current student ID for modal actions
      window.currentModalStudentId = studentId;
      
      // Show modal
      document.getElementById('studentDetailsModal').classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      
      // Show loading state
      document.getElementById('studentDetailsContent').innerHTML = `
        <div class="flex items-center justify-center p-8">
          <div class="animate-spin h-8 w-8 border-4 border-blue-600 border-t-transparent rounded-full"></div>
          <span class="ml-3 text-gray-600">Loading student details...</span>
        </div>
      `;
      
      // Fetch student details
      fetch(`../../api/students.php?action=get_student_details&id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Store current student data for modal actions
            window.currentModalStudentData = data.student;
            displayStudentDetails(data.student);
          } else {
            showError('Failed to load student details: ' + (data.message || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error loading student details:', error);
          showError('Error loading student details. Please try again.');
        });
    };
    
    window.displayStudentDetails = function(student) {
      const content = document.getElementById('studentDetailsContent');
      
      // Recent enrollments for display
      const recentEnrollments = (student.enrollments || []).slice(0, 3);
      const recentPayments = (student.payments || []).slice(0, 3);
      
      content.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Personal Information -->
          <div class="space-y-6">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                </svg>
                Personal Information
              </h3>
              <div class="space-y-3 text-sm">
                <div>
                  <span class="text-blue-700 font-medium">Full Name:</span>
                  <div class="text-blue-900 text-base font-medium">${student.first_name || ''} ${student.last_name || ''}</div>
                </div>
                <div>
                  <span class="text-blue-700 font-medium">User ID:</span>
                  <div class="text-blue-900">${student.user_id || student.username}</div>
                </div>
                <div>
                  <span class="text-blue-700 font-medium">Email:</span>
                  <div class="text-blue-900">${student.email}</div>
                </div>
                ${student.birthday ? `
                <div>
                  <span class="text-blue-700 font-medium">Birthday:</span>
                  <div class="text-blue-900">${new Date(student.birthday).toLocaleDateString()}</div>
                </div>` : ''}
                ${student.age ? `
                <div>
                  <span class="text-blue-700 font-medium">Age:</span>
                  <div class="text-blue-900">${student.age} years old</div>
                </div>` : ''}
              </div>
            </div>
            
            ${student.address ? `
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-green-900 mb-4">Address Information</h3>
              <div class="text-green-800">${student.address}</div>
            </div>` : ''}
            
            ${student.medical_notes ? `
            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-orange-900 mb-4">Medical Notes</h3>
              <div class="text-orange-800 text-sm">${student.medical_notes}</div>
            </div>` : ''}
            
            ${student.is_pwd ? `
            <div class="bg-gradient-to-r from-red-50 to-pink-50 p-4 rounded-lg border border-red-200">
              <div class="flex items-center text-red-800">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                Person with Disability (PWD)
              </div>
            </div>` : ''}
          </div>
          
          <!-- Academic & Financial Information -->
          <div class="space-y-6">
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-purple-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"></path>
                </svg>
                Academic Information
              </h3>
              <div class="space-y-3 text-sm">
                <div>
                  <span class="text-purple-700 font-medium">Enrolled Programs:</span>
                  <div class="text-purple-900">${student.enrolled_programs || 0}</div>
                </div>
                <div>
                  <span class="text-purple-700 font-medium">Active Programs:</span>
                  <div class="text-purple-900">${student.active_programs || 0}</div>
                </div>
                <div>
                  <span class="text-purple-700 font-medium">Status:</span>
                  <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium mt-1 ${getStatusClass(student.calculated_status)}">
                    ${student.calculated_status ? student.calculated_status.charAt(0).toUpperCase() + student.calculated_status.slice(1) : 'Unknown'}
                  </div>
                </div>
                <div>
                  <span class="text-purple-700 font-medium">Registration Date:</span>
                  <div class="text-purple-900">${new Date(student.created_at).toLocaleDateString()}</div>
                </div>
                ${student.last_enrollment ? `
                <div>
                  <span class="text-purple-700 font-medium">Last Enrollment:</span>
                  <div class="text-purple-900">${new Date(student.last_enrollment).toLocaleDateString()}</div>
                </div>` : ''}
                <div>
                  <span class="text-purple-700 font-medium">Total Payments:</span>
                  <div class="text-purple-900">₱${parseFloat(student.paid_amount || 0).toLocaleString()}</div>
                </div>
              </div>
            </div>
            
            <!-- Recent Enrollments -->
            ${recentEnrollments.length > 0 ? `
            <div class="bg-gradient-to-r from-cyan-50 to-blue-50 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-cyan-900 mb-4">Recent Enrollments</h3>
              <div class="space-y-3">
                ${recentEnrollments.map(enrollment => `
                  <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-cyan-200">
                    <div>
                      <div class="text-sm font-medium text-cyan-900">${enrollment.program_title}</div>
                      <div class="text-xs text-cyan-700">${new Date(enrollment.created_at).toLocaleDateString()}</div>
                    </div>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                      ${enrollment.status === 'active' ? 'bg-green-100 text-green-800' : 
                        enrollment.status === 'completed' ? 'bg-blue-100 text-blue-800' : 
                        'bg-gray-100 text-gray-800'}">
                      ${enrollment.status.charAt(0).toUpperCase() + enrollment.status.slice(1)}
                    </span>
                  </div>
                `).join('')}
                ${student.enrollments.length > 3 ? `
                <button onclick="viewEnrollments(${student.id})" class="w-full text-center text-sm text-cyan-700 hover:text-cyan-900 mt-2">
                  View all ${student.enrollments.length} enrollments →
                </button>` : ''}
              </div>
            </div>` : ''}
            
            <!-- Recent Payments -->
            ${recentPayments.length > 0 ? `
            <div class="bg-gradient-to-r from-emerald-50 to-green-50 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-emerald-900 mb-4">Recent Payments</h3>
              <div class="space-y-3">
                ${recentPayments.map(payment => `
                  <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-emerald-200">
                    <div>
                      <div class="text-sm font-medium text-emerald-900">₱${parseFloat(payment.amount || 0).toLocaleString()}</div>
                      <div class="text-xs text-emerald-700">${new Date(payment.created_at).toLocaleDateString()}</div>
                    </div>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                      ${payment.status === 'completed' || payment.status === 'paid' || payment.status === 'validated' ? 'bg-green-100 text-green-800' : 
                        payment.status === 'pending' || payment.status === 'pending_validation' ? 'bg-yellow-100 text-yellow-800' : 
                        'bg-red-100 text-red-800'}">
                      ${payment.status ? payment.status.charAt(0).toUpperCase() + payment.status.slice(1) : 'Unknown'}
                    </span>
                  </div>
                `).join('')}
                ${student.payments.length > 3 ? `
                <button onclick="viewPayments(${student.id})" class="w-full text-center text-sm text-emerald-700 hover:text-emerald-900 mt-2">
                  View all ${student.payments.length} payments →
                </button>` : ''}
              </div>
            </div>` : ''}
          </div>
        </div>
      `;
    };
    
    window.getStatusClass = function(status) {
      const statusClasses = {
        'active': 'bg-green-100 text-green-800',
        'inactive': 'bg-gray-100 text-gray-800',
        'paused': 'bg-yellow-100 text-yellow-800',
        'completed': 'bg-blue-100 text-blue-800',
        'pending': 'bg-purple-100 text-purple-800'
      };
      return statusClasses[status] || 'bg-gray-100 text-gray-800';
    };
    
    window.showError = function(message) {
      document.getElementById('studentDetailsContent').innerHTML = `
        <div class="text-center p-8">
          <div class="text-red-600 mb-4">
            <svg class="w-12 h-12 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Error Loading Details</h3>
          <p class="text-gray-600">${message}</p>
        </div>
      `;
    };
    
    window.closeStudentDetailsModal = function() {
      document.getElementById('studentDetailsModal').classList.add('hidden');
      document.body.style.overflow = 'auto';
      // Clear stored data
      window.currentModalStudentId = null;
      window.currentModalStudentData = null;
    };
    
    // Modal Action Functions
    window.editStudentFromModal = function() {
      const studentId = window.currentModalStudentId;
      const studentData = window.currentModalStudentData;
      
      if (studentId && studentData) {
        closeStudentDetailsModal();
        showEditStudentForm(studentData);
      } else {
        TPAlert.info('Information', 'Student data not available. Please try again.');
      }
    };
    
    window.viewEnrollmentsFromModal = function() {
      const studentId = window.currentModalStudentId;
      const studentData = window.currentModalStudentData;
      
      if (studentId && studentData) {
        closeStudentDetailsModal();
        showEnrollmentsModal(studentData);
      } else {
        TPAlert.info('Information', 'Student data not available. Please try again.');
      }
    };
    
    window.viewPaymentsFromModal = function() {
      const studentId = window.currentModalStudentId;
      const studentData = window.currentModalStudentData;
      
      if (studentId && studentData) {
        closeStudentDetailsModal();
        showPaymentsModal(studentData);
      } else {
        TPAlert.info('Information', 'Student data not available. Please try again.');
      }
    };
    
    window.deactivateStudentFromModal = function() {
      const studentId = window.currentModalStudentId;
      const studentData = window.currentModalStudentData;
      
      if (studentId && studentData) {
        const studentName = `${studentData.first_name || ''} ${studentData.last_name || ''}`.trim() || studentData.username;
        
        TPAlert.confirm('Confirm Action', `Are you sure you want to deactivate ${studentName}? This will make their account inactive but can be reversed later.`).then(result => {
        if (result.isConfirmed) {
          closeStudentDetailsModal();
          deactivateStudent(studentId);
        }
      });
      } else {
        TPAlert.info('Information', 'Student data not available. Please try again.');
      }
    };
    
    // More Options Menu Functions
    window.toggleMoreOptions = function(studentId) {
      const menu = document.getElementById(`more-menu-${studentId}`);
      const allMenus = document.querySelectorAll('[id^="more-menu-"]');
      
      // Close all other menus
      allMenus.forEach(m => {
        if (m.id !== `more-menu-${studentId}`) {
          m.classList.add('hidden');
        }
      });
      
      // Toggle current menu
      menu.classList.toggle('hidden');
    };
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('[id^="more-btn-"]') && !event.target.closest('[id^="more-menu-"]')) {
        document.querySelectorAll('[id^="more-menu-"]').forEach(menu => {
          menu.classList.add('hidden');
        });
      }
    });
    
    // More Options Functions
    window.editStudent = function(studentId) {
      document.querySelectorAll('[id^="more-menu-"]').forEach(menu => menu.classList.add('hidden'));
      
      // Fetch student data first
      fetch(`../../api/students.php?action=get_student_details&id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showEditStudentForm(data.student);
          } else {
            alert('Error loading student data: ' + (data.message || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          TPAlert.error('Error', 'Error loading student data. Please try again.');
        });
    };
    
    window.showEditStudentForm = function(student) {
      const editForm = `
        <div id="editStudentModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
          <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
              <h2 class="text-xl font-semibold text-gray-800">Edit Student</h2>
              <button onclick="closeEditModal()" class="p-2 text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-160px)]">
              <form id="editStudentForm" class="space-y-6">
                <input type="hidden" id="studentId" value="${student.id}">
                
                <!-- Student Information -->
                <div class="bg-green-50 p-4 rounded-lg">
                  <h3 class="text-lg font-medium text-gray-800 mb-4">Student Information</h3>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                      <input type="text" id="firstName" value="${student.first_name || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                      <input type="text" id="lastName" value="${student.last_name || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                      <input type="text" id="middleName" value="${student.middle_name || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Birthday <span class="text-red-500">*</span></label>
                      <input type="date" id="birthday" value="${student.birthday || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                      <input type="number" id="age" value="${student.age || ''}" readonly
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                    </div>
                    <div class="flex items-center mt-6">
                      <input type="checkbox" id="isPwd" ${student.is_pwd ? 'checked' : ''} 
                             class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                      <label for="isPwd" class="ml-2 block text-sm text-gray-900">Person with Disability (PWD)</label>
                    </div>
                  </div>
                  
                  <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" value="${student.email || ''}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                  </div>
                </div>
                
                <!-- Address Information -->
                <div class="bg-blue-50 p-4 rounded-lg">
                  <h3 class="text-lg font-medium text-gray-800 mb-4">Address Information</h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Province</label>
                      <input type="text" id="province" value="${student.province || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">City/Municipality</label>
                      <input type="text" id="city" value="${student.city || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                      <input type="text" id="barangay" value="${student.barangay || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                      <input type="text" id="zipCode" value="${student.zip_code || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Subdivision</label>
                      <input type="text" id="subdivision" value="${student.subdivision || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Street</label>
                      <input type="text" id="street" value="${student.street || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">House Number</label>
                      <input type="text" id="houseNumber" value="${student.house_number || ''}" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                  </div>
                </div>
                
                <!-- Medical and Parent Information -->
                <div class="bg-yellow-50 p-4 rounded-lg">
                  <h3 class="text-lg font-medium text-gray-800 mb-4">Additional Information</h3>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Medical Notes</label>
                    <textarea id="medicalNotes" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">${student.medical_notes || ''}</textarea>
                  </div>
                </div>
              </form>
            </div>
            
            <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
              <button onclick="closeEditModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancel
              </button>
              <button onclick="saveStudentChanges()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                Save Changes
              </button>
            </div>
          </div>
        </div>
      `;
      
      document.body.insertAdjacentHTML('beforeend', editForm);
      document.body.style.overflow = 'hidden';
      
      // Add birthday change listener to auto-calculate age
      document.getElementById('birthday').addEventListener('change', function() {
        const birthday = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - birthday.getFullYear();
        const monthDiff = today.getMonth() - birthday.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
          age--;
        }
        document.getElementById('age').value = age;
      });
    }
    
    window.closeEditModal = function() {
      const modal = document.getElementById('editStudentModal');
      if (modal) {
        modal.remove();
        document.body.style.overflow = 'auto';
      }
    }
    
    window.saveStudentChanges = function() {
      const formData = {
        id: document.getElementById('studentId').value,
        first_name: document.getElementById('firstName').value,
        last_name: document.getElementById('lastName').value,
        middle_name: document.getElementById('middleName').value,
        birthday: document.getElementById('birthday').value,
        age: document.getElementById('age').value,
        email: document.getElementById('email').value,
        province: document.getElementById('province').value,
        city: document.getElementById('city').value,
        barangay: document.getElementById('barangay').value,
        zip_code: document.getElementById('zipCode').value,
        subdivision: document.getElementById('subdivision').value,
        street: document.getElementById('street').value,
        house_number: document.getElementById('houseNumber').value,
        medical_notes: document.getElementById('medicalNotes').value,
        is_pwd: document.getElementById('isPwd').checked
      };
      
      fetch('../../api/students.php?action=update_student', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          TPAlert.success('Success', 'Student updated successfully!');
          closeEditModal();
          location.reload(); // Refresh the page to show updated data
        } else {
          alert('Error updating student: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        TPAlert.error('Error', 'Error updating student. Please try again.');
      });
    }
    
    window.viewEnrollments = function(studentId) {
      document.querySelectorAll('[id^="more-menu-"]').forEach(menu => menu.classList.add('hidden'));
      
      // Fetch student data including enrollments
      fetch(`../../api/students.php?action=get_student_details&id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showEnrollmentsModal(data.student);
          } else {
            alert('Error loading enrollment data: ' + (data.message || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          TPAlert.error('Error', 'Error loading enrollment data. Please try again.');
        });
    }
    
    window.showEnrollmentsModal = function(student) {
      const enrollments = student.enrollments || [];
      
      const enrollmentRows = enrollments.length > 0 ? enrollments.map(enrollment => `
        <tr class="border-b border-gray-200">
          <td class="px-4 py-3 text-sm text-gray-900">${enrollment.program_title}</td>
          <td class="px-4 py-3 text-sm">
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
              ${enrollment.status === 'active' ? 'bg-green-100 text-green-800' : 
                enrollment.status === 'completed' ? 'bg-blue-100 text-blue-800' : 
                'bg-gray-100 text-gray-800'}">
              ${enrollment.status.charAt(0).toUpperCase() + enrollment.status.slice(1)}
            </span>
          </td>
          <td class="px-4 py-3 text-sm">
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
              ${enrollment.payment_status === 'paid' ? 'bg-green-100 text-green-800' : 
                enrollment.payment_status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                'bg-red-100 text-red-800'}">
              ${enrollment.payment_status ? enrollment.payment_status.charAt(0).toUpperCase() + enrollment.payment_status.slice(1) : 'Unknown'}
            </span>
          </td>
          <td class="px-4 py-3 text-sm text-gray-600">${new Date(enrollment.created_at).toLocaleDateString()}</td>
          <td class="px-4 py-3 text-sm text-gray-600">₱${parseFloat(enrollment.price || 0).toLocaleString()}</td>
        </tr>
      `).join('') : '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No enrollments found</td></tr>';
      
      const modal = `
        <div id="enrollmentsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
          <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
              <h2 class="text-xl font-semibold text-gray-800">Student Enrollments - ${student.first_name || ''} ${student.last_name || ''}</h2>
              <button onclick="closeEnrollmentsModal()" class="p-2 text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-160px)]">
              <div class="overflow-x-auto">
                <table class="min-w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enrolled Date</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${enrollmentRows}
                  </tbody>
                </table>
              </div>
            </div>
            
            <div class="flex justify-end p-6 border-t border-gray-200 bg-gray-50">
              <button onclick="closeEnrollmentsModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Close
              </button>
            </div>
          </div>
        </div>
      `;
      
      document.body.insertAdjacentHTML('beforeend', modal);
      document.body.style.overflow = 'hidden';
    }
    
    window.closeEnrollmentsModal = function() {
      const modal = document.getElementById('enrollmentsModal');
      if (modal) {
        modal.remove();
        document.body.style.overflow = 'auto';
      }
    }
    
    window.viewPayments = function(studentId) {
      document.querySelectorAll('[id^="more-menu-"]').forEach(menu => menu.classList.add('hidden'));
      
      // Fetch student data including payments
      fetch(`../../api/students.php?action=get_student_details&id=${studentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showPaymentsModal(data.student);
          } else {
            alert('Error loading payment data: ' + (data.message || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          TPAlert.error('Error', 'Error loading payment data. Please try again.');
        });
    }
    
    window.showPaymentsModal = function(student) {
      const payments = student.payments || [];
      
      const paymentRows = payments.length > 0 ? payments.map(payment => `
        <tr class="border-b border-gray-200">
          <td class="px-4 py-3 text-sm text-gray-900">${payment.program_title || 'N/A'}</td>
          <td class="px-4 py-3 text-sm text-gray-600">₱${parseFloat(payment.amount || 0).toLocaleString()}</td>
          <td class="px-4 py-3 text-sm text-gray-600">${payment.payment_method || 'N/A'}</td>
          <td class="px-4 py-3 text-sm">
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
              ${payment.status === 'completed' || payment.status === 'paid' || payment.status === 'validated' ? 'bg-green-100 text-green-800' : 
                payment.status === 'pending' || payment.status === 'pending_validation' ? 'bg-yellow-100 text-yellow-800' : 
                'bg-red-100 text-red-800'}">
              ${payment.status ? payment.status.charAt(0).toUpperCase() + payment.status.slice(1) : 'Unknown'}
            </span>
          </td>
          <td class="px-4 py-3 text-sm text-gray-600">${new Date(payment.created_at).toLocaleDateString()}</td>
        </tr>
      `).join('') : '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No payments found</td></tr>';
      
      const totalAmount = payments.reduce((sum, payment) => sum + parseFloat(payment.amount || 0), 0);
      
      const modal = `
        <div id="paymentsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
          <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
              <h2 class="text-xl font-semibold text-gray-800">Payment History - ${student.first_name || ''} ${student.last_name || ''}</h2>
              <button onclick="closePaymentsModal()" class="p-2 text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            
            <div class="p-6">
              <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">₱${totalAmount.toLocaleString()}</div>
                    <div class="text-sm text-gray-600">Total Paid</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">${payments.length}</div>
                    <div class="text-sm text-gray-600">Total Payments</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">${student.enrolled_programs || 0}</div>
                    <div class="text-sm text-gray-600">Programs</div>
                  </div>
                </div>
              </div>
              
              <div class="overflow-y-auto max-h-[calc(90vh-300px)]">
                <div class="overflow-x-auto">
                  <table class="min-w-full">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${paymentRows}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            
            <div class="flex justify-end p-6 border-t border-gray-200 bg-gray-50">
              <button onclick="closePaymentsModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Close
              </button>
            </div>
          </div>
        </div>
      `;
      
      document.body.insertAdjacentHTML('beforeend', modal);
      document.body.style.overflow = 'hidden';
    }
    
    window.closePaymentsModal = function() {
      const modal = document.getElementById('paymentsModal');
      if (modal) {
        modal.remove();
        document.body.style.overflow = 'auto';
      }
    }
    
    window.deactivateStudent = function(studentId) {
      document.querySelectorAll('[id^="more-menu-"]').forEach(menu => menu.classList.add('hidden'));
      
      TPAlert.confirm('Confirm Action', 'Are you sure you want to deactivate this student? This will make their account inactive but can be reversed later.').then(result => {
        if (result.isConfirmed) {
          fetch('../../api/students.php?action=deactivate_student', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: studentId })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              TPAlert.success('Success', 'Student deactivated successfully!');
              location.reload(); // Refresh the page to show updated status
            } else {
              alert('Error deactivating student: ' + (data.message || 'Unknown error'));
            }
          })
          .catch(error => {
            console.error('Error:', error);
            TPAlert.error('Error', 'Error deactivating student. Please try again.');
          });
        }
      });
    }

    // Initialize event listeners after DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
      // Close modal when clicking outside
      const modal = document.getElementById('studentDetailsModal');
      if (modal) {
        modal.addEventListener('click', function(e) {
          if (e.target === this) {
            closeStudentDetailsModal();
          }
        });
      }
    });
  </script>

  <!-- Include SweetAlert2 and Common Scripts -->
  <?php include '../../includes/common-scripts.php'; ?>

  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>
</body>

</html>