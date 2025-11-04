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

// Get search and filter parameters
$search_filter = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get tutors data from database
$tutors = getTutors();

// Apply search and status filtering
if (!empty($search_filter) || !empty($status_filter)) {
  $tutors = array_filter($tutors, function($tutor) use ($search_filter, $status_filter) {
    $matches_search = true;
    $matches_status = true;
    
    // Search filtering
    if (!empty($search_filter)) {
      $search_term = strtolower($search_filter);
      $matches_search = (
        strpos(strtolower($tutor['first_name'] . ' ' . $tutor['last_name']), $search_term) !== false ||
        strpos(strtolower($tutor['email']), $search_term) !== false ||
        strpos(strtolower($tutor['specialization'] ?? ''), $search_term) !== false
      );
    }
    
    // Status filtering
    if (!empty($status_filter)) {
      $matches_status = ($tutor['status'] === $status_filter);
    }
    
    return $matches_search && $matches_status;
  });
}

$tutorsCount = count($tutors);

// Pagination settings
$itemsPerPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;
$total_items = $tutorsCount;
$total_pages = ceil($total_items / $itemsPerPage);

// Get paginated tutors
$paginatedTutors = array_slice($tutors, $offset, $itemsPerPage);

// Calculate showing range
$showingFrom = $total_items > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $itemsPerPage, $total_items);

// Calculate additional stats
$tutorsWithPrograms = 0;
$totalPrograms = 0;
foreach ($tutors as $tutor) {
  if (!empty($tutor['programs'])) {
    $tutorsWithPrograms++;
  }
  $totalPrograms += $tutor['program_count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tutors - Admin Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="../../assets/standard-ui.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
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

    /* Custom styles for tutor rows */
    .tutor-row {
      transition: all 0.2s ease;
    }

    .tutor-row:hover {
      background-color: #f9fafb;
    }

    /* Status badge styles */
    .status-active {
      background-color: #d1fae5;
      color: #065f46;
    }

    .status-inactive {
      background-color: #f3f4f6;
      color: #6b7280;
    }

    .status-pending {
      background-color: #dbeafe;
      color: #1e40af;
    }

    .status-on-leave {
      background-color: #fef3c7;
      color: #92400e;
    }

    /* Program badge styles */
    .program-active {
      background-color: #d1fae5;
      color: #065f46;
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

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/header.php';
      
      // Get admin notifications
      $admin_notifications = getAdminNotifications(15);
      
      renderHeader(
        'Tutors',
        '',
        'admin',
        $_SESSION['username'] ?? 'Admin',
        $admin_notifications
      );
      ?>

      <!-- Page content -->
      <main class="p-4 lg:p-6">
        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
          <div class="p-4">
            <form method="GET" action="" class="flex items-center justify-between">
              <div class="flex items-center gap-6 flex-wrap min-w-0">
                <!-- Search Input -->
                <div class="relative flex-shrink-0">
                  <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                  </svg>
                  <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Search tutors..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" style="width: 280px;">
                </div>

                <!-- Status Filter -->
                <div class="relative flex-shrink-0" style="min-width: 140px;">
                  <select name="status" class="bg-white border border-gray-300 rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-full" onchange="this.form.submit()">
                    <option value="" <?php echo empty($_GET['status']) ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
            Showing <?php echo count($tutors); ?> of <?php echo $tutorsCount; ?> tutors
          </div>
        </div>

        <!-- Tutors Table -->
        <div class="tplearn-table-container">
          <div class="overflow-x-auto -mx-4 sm:mx-0">
            <div class="inline-block min-w-full align-middle">
              <table class="min-w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutor Information</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Contact</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Specialization</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Programs</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (empty($tutors)): ?>
                    <tr>
                      <td colspan="6" class="px-6 py-12 text-center">
                        <div class="mx-auto max-w-sm">
                          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                          </svg>
                          <h3 class="mt-2 text-sm font-medium text-gray-900">No tutors found</h3>
                          <p class="mt-1 text-sm text-gray-500">There are no tutors matching your current filters.</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($paginatedTutors as $index => $tutor):
                      // Generate avatar colors - All avators are now green
                      $avatarColors = ['bg-green-500'];
                      $avatarColor = $avatarColors[0];

                      // Get two letters for avatar (first and last name initials)
                      $fullName = isset($tutor['first_name']) && isset($tutor['last_name']) 
                        ? $tutor['first_name'] . ' ' . $tutor['last_name']
                        : ($tutor['name'] ?? $tutor['username'] ?? 'Unknown');
                      
                      // Generate two-letter initials
                      if (isset($tutor['first_name']) && isset($tutor['last_name'])) {
                        $initial = strtoupper(substr($tutor['first_name'], 0, 1) . substr($tutor['last_name'], 0, 1));
                      } else if (isset($tutor['name']) && strpos($tutor['name'], ' ') !== false) {
                        // If name field contains space, split on space
                        $nameParts = explode(' ', $tutor['name'], 2);
                        $initial = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                      } else {
                        // Fallback: first two letters of available name/username
                        $fallbackName = $tutor['name'] ?? $tutor['username'] ?? 'UN';
                        $initial = strtoupper(substr($fallbackName, 0, 2));
                      }

                      // Display name
                      $displayName = $fullName;
                      
                      // Determine status styles
                      $statusVariants = [
                        'active' => 'bg-green-100 text-green-800',
                        'inactive' => 'bg-gray-100 text-gray-800',
                        'on_leave' => 'bg-yellow-100 text-yellow-800',
                        'pending' => 'bg-blue-100 text-blue-800'
                      ];
                      $statusClass = $statusVariants[$tutor['status'] ?? 'active'] ?? 'bg-gray-100 text-gray-800';
                      $statusText = ucfirst(str_replace('_', ' ', $tutor['status'] ?? 'Active'));

                      // Program information
                      $programCount = $tutor['program_count'] ?? 0;
                      $programText = $programCount === 0 ? 'No programs assigned' : $programCount . ' program' . ($programCount !== 1 ? 's' : '');
                      
                      // Contact information
                      $phoneNumber = !empty($tutor['phone']) ? $tutor['phone'] : 'No phone';
                      $specialization = !empty($tutor['specialization']) ? $tutor['specialization'] : 'New tutor';
                    ?>

                      <tr class="tutor-row">
                        <td class="px-4 sm:px-6 py-4">
                          <div class="flex items-center">
                            <div class="w-10 h-10 <?= $avatarColor ?> rounded-full flex items-center justify-center mr-3 sm:mr-4">
                              <span class="text-white font-medium text-sm"><?= $initial ?></span>
                            </div>
                            <div class="min-w-0 flex-1">
                              <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($displayName) ?></div>
                              <div class="text-sm text-gray-500"><?= htmlspecialchars($tutor['username'] ?? $tutor['user_id'] ?? 'N/A') ?></div>
                              <div class="lg:hidden mt-1">
                                <div class="text-xs text-gray-500 flex items-center">
                                  <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                  </svg>
                                  <?= htmlspecialchars($tutor['email']) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                  <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                  </svg>
                                  <?= htmlspecialchars($phoneNumber) ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($specialization) ?></div>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                          <div class="text-sm text-gray-500 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                              <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                            </svg>
                            <?= htmlspecialchars($tutor['email']) ?>
                          </div>
                          <div class="text-sm text-gray-500 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                            </svg>
                            <?= htmlspecialchars($phoneNumber) ?>
                          </div>
                        </td>
                        <td class="px-4 sm:px-6 py-4 hidden md:table-cell">
                          <div class="text-sm text-gray-900"><?= htmlspecialchars($specialization) ?></div>
                          <div class="text-sm text-gray-500"><?= htmlspecialchars($tutor['experience'] ?? 'New tutor') ?></div>
                        </td>
                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $programCount > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $programText ?>
                          </span>
                        </td>
                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                            <?= $statusText ?>
                          </span>
                        </td>
                        <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <div class="flex space-x-4">
                            <button onclick="viewTutorDetails(<?= $tutor['id'] ?>, '<?= htmlspecialchars($displayName, ENT_QUOTES) ?>')" 
                                    class="text-blue-600 hover:text-blue-900 font-medium">
                              View
                            </button>
                            <div class="relative">
                              <button onclick="toggleMoreOptions(<?= $tutor['id'] ?>)" 
                                      id="more-btn-<?= $tutor['id'] ?>"
                                      class="text-gray-600 hover:underline font-medium">
                                More
                              </button>
                              <!-- Dropdown Menu -->
                              <div id="more-menu-<?= $tutor['id'] ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                                <div class="py-1">
                                  <button onclick="viewPrograms(<?= $tutor['id'] ?>)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 block">
                                    View Programs
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
          <?php if ($total_pages > 1): ?>
          <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
              <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
              <?php else: ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-gray-50 cursor-not-allowed">Previous</span>
              <?php endif; ?>
              <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
              <?php else: ?>
                <span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-gray-50 cursor-not-allowed">Next</span>
              <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Showing <span class="font-medium"><?php echo $showingFrom; ?></span> to <span class="font-medium"><?php echo $showingTo; ?></span> of <span class="font-medium"><?php echo $tutorsCount; ?></span> results
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
                  $endPage = min($total_pages, $page + 2);
                  
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
                  
                  <?php if ($page < $total_pages): ?>
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
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

  <!-- Tutor Review Modal -->
  <div id="reviewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
      <div class="mt-3">
        <!-- Modal Header -->
        <div class="flex justify-between items-center pb-4 border-b">
          <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Review Tutor Application</h3>
          <button class="close-modal text-gray-400 hover:text-gray-600" onclick="closeReviewModal()">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Modal Content -->
        <div class="mt-4 max-h-96 overflow-y-auto">
          <div id="modalContent" class="space-y-6">
            <!-- Loading state -->
            <div class="flex justify-center items-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green"></div>
              <span class="ml-2">Loading tutor details...</span>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end space-x-3 pt-4 border-t mt-6">
          <button onclick="closeReviewModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
            Cancel
          </button>
          <button id="rejectFromModal" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
            Reject
          </button>
          <button id="approveFromModal" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
            Approve
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Tutor Modal -->
  <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
      <div class="mt-3">
        <!-- Modal Header -->
        <div class="flex justify-between items-center pb-4 border-b">
          <h3 class="text-lg font-semibold text-gray-900" id="editModalTitle">Edit Tutor</h3>
          <button class="close-edit-modal text-gray-400 hover:text-gray-600" onclick="closeEditModal()">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Edit Form -->
        <form id="editTutorForm" class="mt-4">
          <input type="hidden" id="editTutorId" name="tutor_id">
          
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-h-96 overflow-y-auto">
            <!-- Personal Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-lg font-semibold text-gray-900 mb-4">Personal Information</h4>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">First Name</label>
                  <input type="text" id="editFirstName" name="first_name" 
                         class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                  <input type="text" id="editMiddleName" name="middle_name" 
                         class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Last Name</label>
                  <input type="text" id="editLastName" name="last_name" 
                         class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Email</label>
                  <input type="email" id="editEmail" name="email" 
                         class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                  <input type="text" id="editContactNumber" name="contact_number" 
                         class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Address</label>
                  <textarea id="editAddress" name="address" rows="3"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green"></textarea>
                </div>
              </div>
            </div>

            <!-- Academic Information -->
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-lg font-semibold text-gray-900 mb-4">Academic Information</h4>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Bachelor's Degree</label>
                  <input type="text" id="editBachelorDegree" name="bachelor_degree" 
                         class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Specializations</label>
                  <input type="text" id="editSpecializations" name="specializations" 
                         class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Bio</label>
                  <textarea id="editBio" name="bio" rows="4"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Status</label>
                  <select id="editStatus" name="status" 
                          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-tplearn-green focus:border-tplearn-green">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="on_leave">On Leave</option>
                    <option value="pending">Pending</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="flex justify-end space-x-3 pt-4 border-t mt-6">
            <button type="button" onclick="closeEditModal()" 
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
              Cancel
            </button>
            <button type="submit" 
                    class="px-4 py-2 bg-tplearn-green text-white rounded-md hover:bg-green-700 transition-colors">
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Programs Modal -->
  <div id="programsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white">
      <div class="mt-3">
        <!-- Modal Header -->
        <div class="flex justify-between items-center pb-4 border-b">
          <h3 class="text-lg font-semibold text-gray-900" id="programsModalTitle">Tutor Programs</h3>
          <button class="close-modal text-gray-400 hover:text-gray-600" onclick="closeProgramsModal()">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Modal Content -->
        <div class="mt-4 max-h-96 overflow-y-auto">
          <div id="programsModalContent" class="space-y-4">
            <!-- Loading state -->
            <div class="flex justify-center items-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green"></div>
              <span class="ml-2">Loading programs...</span>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end space-x-3 pt-4 border-t mt-6">
          <button onclick="closeProgramsModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>
  
  <!-- Tutor approval/rejection JavaScript -->
  <script>
    let currentTutorId = null;
    let currentTutorName = null;

    document.addEventListener('DOMContentLoaded', function() {
      // Handle review tutor
      document.querySelectorAll('.review-tutor').forEach(button => {
        button.addEventListener('click', function() {
          const tutorId = this.getAttribute('data-tutor-id');
          const tutorName = this.getAttribute('data-tutor-name');
          
          currentTutorId = tutorId;
          currentTutorName = tutorName;
          
          openReviewModal(tutorId, tutorName);
        });
      });

      // Handle view tutor
      document.querySelectorAll('.view-tutor').forEach(button => {
        button.addEventListener('click', function() {
          const tutorId = this.getAttribute('data-tutor-id');
          const tutorName = this.getAttribute('data-tutor-name');
          
          openViewModal(tutorId, tutorName);
        });
      });

      // Handle approve tutor
      document.querySelectorAll('.approve-tutor').forEach(button => {
        button.addEventListener('click', function() {
          const tutorId = this.getAttribute('data-tutor-id');
          const tutorName = this.getAttribute('data-tutor-name');
          
          TPAlert.confirm('Confirm Action', `Are you sure you want to approve ${tutorName} as a tutor?`).then(result => {
            if (result.isConfirmed) {
              updateTutorStatus(tutorId, 'active', 'approved');
            }
          });
        });
      });
      
      // Handle reject tutor
      document.querySelectorAll('.reject-tutor').forEach(button => {
        button.addEventListener('click', function() {
          const tutorId = this.getAttribute('data-tutor-id');
          const tutorName = this.getAttribute('data-tutor-name');
          
          TPAlert.confirm('Confirm Action', `Are you sure you want to reject ${tutorName}'s tutor application?`).then(result => {
            if (result.isConfirmed) {
              updateTutorStatus(tutorId, 'inactive', 'rejected');
            }
          });
        });
      });

      // Handle modal approve/reject buttons
      document.getElementById('approveFromModal').addEventListener('click', function() {
        if (currentTutorId && currentTutorName) {
          TPAlert.confirm('Confirm Action', `Are you sure you want to approve ${currentTutorName} as a tutor?`).then(result => {
            if (result.isConfirmed) {
              updateTutorStatus(currentTutorId, 'active', 'approved');
              closeReviewModal();
            }
          });
        }
      });

      document.getElementById('rejectFromModal').addEventListener('click', function() {
        if (currentTutorId && currentTutorName) {
          TPAlert.confirm('Confirm Action', `Are you sure you want to reject ${currentTutorName}'s tutor application?`).then(result => {
            if (result.isConfirmed) {
              updateTutorStatus(currentTutorId, 'inactive', 'rejected');
              closeReviewModal();
            }
          });
        }
      });
      
      // Function to update tutor status
      function updateTutorStatus(tutorId, status, action) {
        const formData = new FormData();
        formData.append('action', 'update_user_status');
        formData.append('user_id', tutorId);
        formData.append('status', status);
        
        fetch('../../api/users.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Show success message and reload page
            TPAlert.info('Information', `Tutor ${action} successfully!`);
            window.location.reload();
          } else {
            TPAlert.info('Information', `Error: ${data.message || 'Failed to update tutor status'}`);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          TPAlert.error('Error', 'An error occurred while updating tutor status');
        });
      }
    });

    // Global functions for onclick handlers
    function viewTutorDetails(tutorId, tutorName) {
      // Handle view tutor details using the existing modal
      openViewModal(tutorId, tutorName);
    }

    function toggleMoreOptions(tutorId) {
      // Close all other open menus
      document.querySelectorAll('[id^="more-menu-"]').forEach(menu => {
        if (menu.id !== `more-menu-${tutorId}`) {
          menu.classList.add('hidden');
        }
      });
      
      // Toggle the current menu
      const menu = document.getElementById(`more-menu-${tutorId}`);
      menu.classList.toggle('hidden');
    }

    // Modal functions
    function openReviewModal(tutorId, tutorName) {
      const modal = document.getElementById('reviewModal');
      const modalTitle = document.getElementById('modalTitle');
      const modalContent = document.getElementById('modalContent');
      const approveBtn = document.getElementById('approveFromModal');
      const rejectBtn = document.getElementById('rejectFromModal');
      
      modalTitle.textContent = `Review Application: ${tutorName}`;
      
      // Show approve/reject buttons for review modal
      approveBtn.style.display = 'block';
      rejectBtn.style.display = 'block';
      
      // Show loading state
      modalContent.innerHTML = `
        <div class="flex justify-center items-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green"></div>
          <span class="ml-2">Loading tutor details...</span>
        </div>
      `;
      
      modal.classList.remove('hidden');
      
      // Fetch tutor details
      fetchTutorDetails(tutorId);
    }

    function openViewModal(tutorId, tutorName) {
      const modal = document.getElementById('reviewModal');
      const modalTitle = document.getElementById('modalTitle');
      const modalContent = document.getElementById('modalContent');
      const approveBtn = document.getElementById('approveFromModal');
      const rejectBtn = document.getElementById('rejectFromModal');
      
      modalTitle.textContent = `Tutor Details: ${tutorName}`;
      
      // Hide approve/reject buttons for view modal
      approveBtn.style.display = 'none';
      rejectBtn.style.display = 'none';
      
      // Show loading state
      modalContent.innerHTML = `
        <div class="flex justify-center items-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green"></div>
          <span class="ml-2">Loading tutor details...</span>
        </div>
      `;
      
      modal.classList.remove('hidden');
      
      // Fetch tutor details
      fetchTutorDetails(tutorId);
    }

    function closeReviewModal() {
      const modal = document.getElementById('reviewModal');
      modal.classList.add('hidden');
      currentTutorId = null;
      currentTutorName = null;
    }

    function fetchTutorDetails(tutorId) {
      const formData = new FormData();
      formData.append('action', 'get_tutor_details');
      formData.append('tutor_id', tutorId);
      
      fetch('../../api/users.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayTutorDetails(data.tutor);
        } else {
          document.getElementById('modalContent').innerHTML = `
            <div class="text-center py-8 text-red-600">
              <p>Error loading tutor details: ${data.message || 'Unknown error'}</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('modalContent').innerHTML = `
          <div class="text-center py-8 text-red-600">
            <p>Error loading tutor details. Please try again.</p>
          </div>
        `;
      });
    }

    function displayTutorDetails(tutor) {
      const modalContent = document.getElementById('modalContent');
      
      modalContent.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Personal Information -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Personal Information</h4>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                <p class="mt-1 text-sm text-gray-900">${tutor.first_name} ${tutor.middle_name || ''} ${tutor.last_name}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <p class="mt-1 text-sm text-gray-900">${tutor.email}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                <p class="mt-1 text-sm text-gray-900">${tutor.contact_number || 'Not provided'}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Address</label>
                <p class="mt-1 text-sm text-gray-900">${tutor.address || 'Not provided'}</p>
              </div>
            </div>
          </div>

          <!-- Academic Information -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Academic Information</h4>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">Bachelor's Degree</label>
                <p class="mt-1 text-sm text-gray-900">${tutor.bachelor_degree || 'Not provided'}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Specializations</label>
                <p class="mt-1 text-sm text-gray-900">${tutor.specializations || 'Not provided'}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Bio</label>
                <p class="mt-1 text-sm text-gray-900">${tutor.bio || 'Not provided'}</p>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    function fetchTutorForEdit(tutorId) {
      const formData = new FormData();
      formData.append('action', 'get_tutor_details');
      formData.append('tutor_id', tutorId);
      
      fetch('../../api/users.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          populateEditForm(data.tutor);
        } else {
          alert('Error loading tutor details: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        TPAlert.error('Error', 'Error loading tutor details. Please try again.');
      });
    }

    function populateEditForm(tutor) {
      document.getElementById('editTutorId').value = tutor.id;
      document.getElementById('editFirstName').value = tutor.first_name || '';
      document.getElementById('editMiddleName').value = tutor.middle_name || '';
      document.getElementById('editLastName').value = tutor.last_name || '';
      document.getElementById('editEmail').value = tutor.email || '';
      document.getElementById('editContactNumber').value = tutor.contact_number || '';
      document.getElementById('editAddress').value = tutor.address || '';
      document.getElementById('editBachelorDegree').value = tutor.bachelor_degree || '';
      document.getElementById('editSpecializations').value = tutor.specializations || '';
      document.getElementById('editBio').value = tutor.bio || '';
      document.getElementById('editStatus').value = tutor.status || 'active';
    }

    // Close modal when clicking outside
    document.getElementById('reviewModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeReviewModal();
      }
    });

    // Close edit modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeEditModal();
      }
    });

    // Close programs modal when clicking outside
    document.getElementById('programsModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeProgramsModal();
      }
    });

    function viewPrograms(tutorId) {
      // Get tutor name from the DOM
      const tutorRow = document.querySelector(`#more-btn-${tutorId}`).closest('tr');
      const tutorName = tutorRow.querySelector('.text-sm.font-medium.text-gray-900').textContent;
      
      // Open programs modal
      openProgramsModal(tutorId, tutorName);
      
      // Close the dropdown
      document.getElementById(`more-menu-${tutorId}`).classList.add('hidden');
    }

    // Programs Modal Functions
    function openProgramsModal(tutorId, tutorName) {
      const modal = document.getElementById('programsModal');
      const modalTitle = document.getElementById('programsModalTitle');
      const modalContent = document.getElementById('programsModalContent');
      
      modalTitle.textContent = `Programs: ${tutorName}`;
      
      // Show loading state
      modalContent.innerHTML = `
        <div class="flex justify-center items-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green"></div>
          <span class="ml-2">Loading programs...</span>
        </div>
      `;
      
      modal.classList.remove('hidden');
      
      // Fetch tutor programs
      fetchTutorPrograms(tutorId);
    }

    function closeProgramsModal() {
      const modal = document.getElementById('programsModal');
      modal.classList.add('hidden');
    }

    function fetchTutorPrograms(tutorId) {
      const formData = new FormData();
      formData.append('action', 'get_tutor_programs');
      formData.append('tutor_id', tutorId);
      
      fetch('../../api/users.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayTutorPrograms(data.programs);
        } else {
          document.getElementById('programsModalContent').innerHTML = `
            <div class="text-center py-8 text-red-600">
              <p>Error loading programs: ${data.message || 'Unknown error'}</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('programsModalContent').innerHTML = `
          <div class="text-center py-8 text-red-600">
            <p>Error loading programs. Please try again.</p>
          </div>
        `;
      });
    }

    function displayTutorPrograms(programs) {
      const modalContent = document.getElementById('programsModalContent');
      
      if (!programs || programs.length === 0) {
        modalContent.innerHTML = `
          <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No Programs</h3>
            <p class="mt-1 text-sm text-gray-500">This tutor has no assigned programs.</p>
          </div>
        `;
        return;
      }

      modalContent.innerHTML = programs.map(program => `
        <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
          <div class="flex justify-between items-start mb-3">
            <div>
              <h4 class="text-lg font-semibold text-gray-900">${program.name}</h4>
              <p class="text-sm text-gray-600">${program.description || 'No description'}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
              program.program_status === 'ongoing' ? 'bg-green-100 text-green-800' :
              program.program_status === 'upcoming' ? 'bg-blue-100 text-blue-800' :
              'bg-gray-100 text-gray-800'
            }">
              ${(program.program_status || 'unknown').charAt(0).toUpperCase() + (program.program_status || 'unknown').slice(1)}
            </span>
          </div>
          
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3">
            <div>
              <span class="text-xs font-medium text-gray-500">Duration</span>
              <p class="text-sm text-gray-900">${program.duration_weeks} weeks</p>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Students</span>
              <p class="text-sm text-gray-900">${program.enrolled_students}/${program.max_students}</p>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Fee</span>
              <p class="text-sm text-gray-900">₱${parseFloat(program.fee).toLocaleString()}</p>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Progress</span>
              <p class="text-sm text-gray-900">${program.progress_percentage}%</p>
            </div>
          </div>
          
          <div class="flex items-center justify-between text-sm text-gray-600">
            <span>${program.session_time || 'Time TBD'}</span>
            <span>${program.start_date} - ${program.end_date}</span>
          </div>
        </div>
      `).join('');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('[id^="more-btn-"]') && !e.target.closest('[id^="more-menu-"]')) {
        document.querySelectorAll('[id^="more-menu-"]').forEach(menu => {
          menu.classList.add('hidden');
        });
      }
    });
  </script>
  
  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>
</body>

</html>