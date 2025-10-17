<?php
require_once __DIR__ . '/../../assets/icons.php';
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
require_once '../../includes/ui-components.php';
requireRole('admin');

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
      background-color: #fee2e2;
      color: #991b1b;
    }

    .status-pending {
      background-color: #fef3c7;
      color: #92400e;
    }

    .status-on-leave {
      background-color: #e0e7ff;
      color: #3730a3;
    }

    /* Program badge styles */
    .program-math {
      background-color: #dbeafe;
      color: #1e40af;
    }

    .program-science {
      background-color: #d1fae5;
      color: #065f46;
    }

    .program-english {
      background-color: #fce7f3;
      color: #be185d;
    }

    .program-advanced {
      background-color: #f3e8ff;
      color: #7c2d12;
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
      renderHeader(
        'Tutors',
        '',
        'admin',
        $_SESSION['name'] ?? 'Admin',
        [], // notifications array - to be implemented
        []  // messages array - to be implemented
      );
      ?>

      <!-- Page content -->
      <main class="p-4 lg:p-6">
        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
          <div class="p-4">
            <form method="GET" action="" class="flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <!-- Search Input -->
                <div class="relative">
                  <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                  </svg>
                  <input type="text" 
                    name="search" 
                    value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                    placeholder="Search tutors..." 
                    class="pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-64 h-10 text-sm">
                </div>

                <!-- Status Filter -->
                <div class="relative">
                  <select name="status" 
                    class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" 
                    onchange="this.form.submit()">
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

        <!-- Tutors table -->
        <div class="tplearn-table-container">
          <!-- Table -->
          <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tutor
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Contact
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Specialization
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Programs
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($tutors)): ?>
                  <tr>
                    <td colspan="6" class="px-6 py-12 text-center">
                      <?= TPLearnUI::renderEmptyState([
                        'title' => 'No tutors found',
                        'description' => 'There are no tutors available at the moment.',
                        'icon' => '<svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path></svg>'
                      ]) ?>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($tutors as $index => $tutor):
                    // Generate avatar colors and initials
                    $avatarColors = ['bg-blue-500', 'bg-purple-500', 'bg-green-500', 'bg-pink-500', 'bg-orange-500', 'bg-indigo-500', 'bg-red-500'];
                    $avatarColor = $avatarColors[$index % count($avatarColors)];

                    // Get initials from name
                    $nameParts = explode(' ', $tutor['name']);
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

                    // Determine status styles
                    $statusStyles = [
                      'active' => 'status-active',
                      'inactive' => 'status-inactive',
                      'on_leave' => 'status-on-leave',
                      'pending' => 'status-pending'
                    ];
                    $statusClass = $statusStyles[$tutor['status']] ?? 'status-active';
                  ?>

                    <tr class="tutor-row">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full <?= $avatarColor ?> flex items-center justify-center">
                              <span class="text-sm font-medium text-white"><?= $initials ?></span>
                            </div>
                          </div>
                          <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($tutor['name']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($tutor['email']) ?></div>
                        <div class="text-sm text-gray-500"><?= htmlspecialchars($tutor['phone'] ?? 'No phone') ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($tutor['specialization'] ?? 'General') ?></div>
                        <div class="text-sm text-gray-500"><?= htmlspecialchars($tutor['experience'] ?? 'New tutor') ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-wrap gap-1">
                          <?php if (!empty($tutor['programs'])): ?>
                            <?php
                            $programs = is_array($tutor['programs']) ? $tutor['programs'] : explode(',', $tutor['programs']);
                            $programs = array_map('trim', $programs); // Remove whitespace
                            $programColors = ['program-math', 'program-science', 'program-english', 'program-advanced'];
                            ?>
                            <?php foreach (array_slice($programs, 0, 2) as $idx => $program): ?>
                              <?= TPLearnUI::renderBadge([
                                'text' => strlen($program) > 12 ? substr($program, 0, 12) . '...' : $program,
                                'variant' => 'info',
                                'additional_classes' => 'mr-1'
                              ]) ?>
                            <?php endforeach; ?>
                            <?php if (count($programs) > 2): ?>
                              <span class="inline-flex px-2 py-1 text-xs text-gray-500 cursor-help" 
                                    title="<?= htmlspecialchars(implode(', ', array_slice($programs, 2))) ?>">
                                +<?= count($programs) - 2 ?> more
                              </span>
                            <?php endif; ?>
                            <div class="text-xs text-gray-400 mt-1">
                              Total: <?= $tutor['program_count'] ?? count($programs) ?> program<?= (($tutor['program_count'] ?? count($programs)) != 1) ? 's' : '' ?>
                            </div>
                          <?php else: ?>
                            <span class="text-xs text-gray-500">No programs assigned</span>
                            <div class="text-xs text-gray-400 mt-1">0 programs</div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?= TPLearnUI::renderBadge([
                          'text' => ucfirst(str_replace('_', ' ', $tutor['status'] ?? 'unknown')),
                          'variant' => $tutor['status'] ?? 'neutral'
                        ]) ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div class="flex space-x-2">
                          <?php if ($tutor['status'] === 'pending'): ?>
                            <!-- Review button for pending tutors -->
                            <?= TPLearnUI::renderButton([
                              'text' => 'Review',
                              'variant' => 'primary',
                              'size' => 'sm',
                              'additional_classes' => 'review-tutor',
                              'data-tutor-id' => $tutor['id'],
                              'data-tutor-name' => htmlspecialchars($tutor['name']),
                              'title' => 'Review Tutor Application'
                            ]) ?>
                          <?php endif; ?>
                          
                          <!-- Standard action buttons for all tutors -->
                          <?= TPLearnUI::renderButton([
                            'type' => 'icon',
                            'variant' => 'ghost',
                            'size' => 'sm',
                            'additional_classes' => 'view-tutor',
                            'data-tutor-id' => $tutor['id'],
                            'data-tutor-name' => htmlspecialchars($tutor['name']),
                            'title' => 'View Tutor Details',
                            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>'
                          ]) ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
              <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Previous
              </a>
              <a href="#" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Next
              </a>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Showing <span class="font-medium">1</span> to <span class="font-medium"><?= $tutorsCount ?></span> of <span class="font-medium"><?= $tutorsCount ?></span> results
                </p>
              </div>
            </div>
          </div>
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
          
          if (confirm(`Are you sure you want to approve ${tutorName} as a tutor?`)) {
            updateTutorStatus(tutorId, 'active', 'approved');
          }
        });
      });
      
      // Handle reject tutor
      document.querySelectorAll('.reject-tutor').forEach(button => {
        button.addEventListener('click', function() {
          const tutorId = this.getAttribute('data-tutor-id');
          const tutorName = this.getAttribute('data-tutor-name');
          
          if (confirm(`Are you sure you want to reject ${tutorName}'s tutor application?`)) {
            updateTutorStatus(tutorId, 'inactive', 'rejected');
          }
        });
      });

      // Handle modal approve/reject buttons
      document.getElementById('approveFromModal').addEventListener('click', function() {
        if (currentTutorId && currentTutorName) {
          if (confirm(`Are you sure you want to approve ${currentTutorName} as a tutor?`)) {
            updateTutorStatus(currentTutorId, 'active', 'approved');
            closeReviewModal();
          }
        }
      });

      document.getElementById('rejectFromModal').addEventListener('click', function() {
        if (currentTutorId && currentTutorName) {
          if (confirm(`Are you sure you want to reject ${currentTutorName}'s tutor application?`)) {
            updateTutorStatus(currentTutorId, 'inactive', 'rejected');
            closeReviewModal();
          }
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
            alert(`Tutor ${action} successfully!`);
            window.location.reload();
          } else {
            alert(`Error: ${data.message || 'Failed to update tutor status'}`);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while updating tutor status');
        });
      }
    });

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

          <!-- Documents -->
          <div class="bg-gray-50 p-4 rounded-lg lg:col-span-2">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Submitted Documents</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              ${tutor.cv_document_path ? `
                <div class="text-center">
                  <div class="bg-blue-100 p-3 rounded-lg mb-2">
                    <svg class="w-8 h-8 mx-auto text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-gray-900">Curriculum Vitae</p>
                  <a href="../../${tutor.cv_document_path}" target="_blank" 
                     class="text-xs text-blue-600 hover:text-blue-800 underline">View Document</a>
                </div>
              ` : '<div class="text-center text-gray-500 text-sm">CV: Not provided</div>'}

              ${tutor.diploma_document_path ? `
                <div class="text-center">
                  <div class="bg-green-100 p-3 rounded-lg mb-2">
                    <svg class="w-8 h-8 mx-auto text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path>
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-gray-900">Diploma</p>
                  <a href="../../${tutor.diploma_document_path}" target="_blank" 
                     class="text-xs text-blue-600 hover:text-blue-800 underline">View Document</a>
                </div>
              ` : '<div class="text-center text-gray-500 text-sm">Diploma: Not provided</div>'}

              ${tutor.tor_document_path ? `
                <div class="text-center">
                  <div class="bg-purple-100 p-3 rounded-lg mb-2">
                    <svg class="w-8 h-8 mx-auto text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-gray-900">Transcript of Records</p>
                  <a href="../../${tutor.tor_document_path}" target="_blank" 
                     class="text-xs text-blue-600 hover:text-blue-800 underline">View Document</a>
                </div>
              ` : '<div class="text-center text-gray-500 text-sm">TOR: Not provided</div>'}

              ${tutor.lpt_csc_document_path ? `
                <div class="text-center">
                  <div class="bg-orange-100 p-3 rounded-lg mb-2">
                    <svg class="w-8 h-8 mx-auto text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-gray-900">LPT/CSC Results</p>
                  <a href="../../${tutor.lpt_csc_document_path}" target="_blank" 
                     class="text-xs text-blue-600 hover:text-blue-800 underline">View Document</a>
                </div>
              ` : '<div class="text-center text-gray-500 text-sm">LPT/CSC: Not provided</div>'}
            </div>

            ${tutor.other_documents_paths ? `
              <div class="mt-4">
                <h5 class="text-sm font-medium text-gray-900 mb-2">Other Documents</h5>
                <div class="space-y-2">
                  ${JSON.parse(tutor.other_documents_paths).map(doc => `
                    <div class="flex items-center space-x-2">
                      <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                      </svg>
                      <a href="../../${doc}" target="_blank" 
                         class="text-sm text-blue-600 hover:text-blue-800 underline">Additional Document</a>
                    </div>
                  `).join('')}
                </div>
              </div>
            ` : ''}
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
        alert('Error loading tutor details. Please try again.');
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
  </script>
  
  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>
</body>

</html>