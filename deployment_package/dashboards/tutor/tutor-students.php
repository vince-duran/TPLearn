<?php
// Set charset header to prevent encoding issues
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('tutor');

// Get tutor data from session
$tutor_user_id = $_SESSION['user_id'] ?? null;
$tutor_name = $_SESSION['username'] ?? 'Tutor';

if (!$tutor_user_id) {
  header('Location: ../../login.php');
  exit();
}

// Debug: Log current tutor info
error_log("Current tutor - ID: $tutor_user_id, Name: $tutor_name");

/**
 * Get all students enrolled in programs taught by this tutor
 */
function getTutorStudents($tutor_user_id) {
  global $conn;
  
  try {
    // Debug: Log the tutor ID being searched
    error_log("Searching for students for tutor ID: $tutor_user_id");
    
    $sql = "
      SELECT 
        e.student_user_id as id,
        u.user_id,
        COALESCE(sp.first_name, SUBSTRING_INDEX(u.username, '-', 1)) as first_name,
        COALESCE(sp.last_name, SUBSTRING_INDEX(u.username, '-', -1)) as last_name,
        u.email,
        COALESCE(pp.contact_number, sp.address, 'N/A') as contact_number,
        -- Combine all programs for this student
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') as program_names,
        GROUP_CONCAT(DISTINCT p.category ORDER BY p.category SEPARATOR ', ') as program_categories,
        -- Get earliest enrollment date
        MIN(e.enrollment_date) as enrollment_date,
        -- Count total programs
        COUNT(DISTINCT p.id) as program_count,
        -- Simple attendance calculation (placeholder)
        0 as attendance_rate
      FROM programs p
      INNER JOIN enrollments e ON p.id = e.program_id
      INNER JOIN users u ON e.student_user_id = u.id
      LEFT JOIN student_profiles sp ON u.id = sp.user_id
      LEFT JOIN parent_profiles pp ON u.id = pp.student_user_id
      WHERE p.tutor_id = ? 
      AND e.status IN ('active', 'pending', 'completed')
      GROUP BY e.student_user_id, u.user_id, u.email, u.username, sp.first_name, sp.last_name, sp.address, pp.contact_number
      ORDER BY COALESCE(sp.first_name, u.username), COALESCE(sp.last_name, '')
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tutor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $row['name'] = $row['full_name']; // For backward compatibility
        $row['programs'] = $row['program_names'];
        $row['phone'] = $row['contact_number']; // Use the contact number from database
        $row['attendance_rate'] = round($row['attendance_rate'], 1);
        
        // Keep contact_number for display
        $row['display_contact'] = !empty($row['contact_number']) && $row['contact_number'] !== 'N/A' ? $row['contact_number'] : 'No Contact Number';
        $row['city'] = 'N/A'; // Not available in current schema
        $row['state'] = 'N/A'; // Not available in current schema
        
        $row['status'] = 'active'; // All fetched students are active
        $students[] = $row;
      }
    }
    
    // Debug: Log number of students found
    error_log("Found " . count($students) . " students for tutor ID: $tutor_user_id");
    
    return $students;
  } catch (Exception $e) {
    error_log("Error fetching tutor students: " . $e->getMessage());
    return [];
  }
}

// Get real students data for this tutor
$students = getTutorStudents($tutor_user_id);

// Debug: Show what we found
if (!empty($_GET['debug'])) {
    echo "<pre>Debug - Tutor ID: $tutor_user_id\n";
    echo "Students found: " . count($students) . "\n";
    if (!empty($students)) {
        echo "First student: " . print_r($students[0], true);
    }
    
    // Additional debug info
    echo "\nDetailed Debug Info:\n";
    echo "- Session user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n";
    echo "- Session username: " . ($_SESSION['username'] ?? 'not set') . "\n";
    echo "- Session role: " . ($_SESSION['role'] ?? 'not set') . "\n";
    
    // Check if tutor has programs
    $program_check = $conn->query("SELECT COUNT(*) as count FROM programs WHERE tutor_id = $tutor_user_id");
    $program_count = $program_check->fetch_assoc()['count'];
    echo "- Programs assigned to this tutor: $program_count\n";
    
    if ($program_count > 0) {
        $programs_list = $conn->query("SELECT id, name FROM programs WHERE tutor_id = $tutor_user_id");
        echo "- Programs:\n";
        while ($prog = $programs_list->fetch_assoc()) {
            echo "  * ID {$prog['id']}: {$prog['name']}\n";
        }
    }
    
    echo "</pre>";
}

// Get unique program names for filtering dropdown
$all_programs = [];
foreach ($students as $student) {
  $program_names = explode(', ', $student['program_names']);
  foreach ($program_names as $program) {
    $program = trim($program);
    if ($program && !in_array($program, $all_programs)) {
      $all_programs[] = $program;
    }
  }
}
sort($all_programs);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Students - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="../../assets/tplearn-tailwind.css?v=<?= filemtime(__DIR__ . '/../../assets/tplearn-tailwind.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../../assets/standard-ui.css">
</head>

<body class="bg-gray-50 min-h-screen">
  <div class="flex">

    <?php 
    include '../../includes/tutor-sidebar.php';
    ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1 flex flex-col h-screen">
      <?php 
      require_once '../../includes/tutor-header-standard.php';
      renderTutorHeader('My Students');
      ?>

      <!-- Main Content Area -->
      <main class="p-6 flex-1 overflow-y-auto">

        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
          <div class="p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <!-- Search Input -->
                <div class="relative">
                  <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                  </svg>
                  <input type="text" id="searchInput" placeholder="Search students..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-64">
                </div>

                <!-- Program Filter -->
                <div class="relative">
                  <select id="programFilter" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
                    <option value="">All Programs</option>
                    <?php foreach ($all_programs as $program): ?>
                      <option value="<?= htmlspecialchars($program) ?>"><?= htmlspecialchars($program) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>

              <!-- Clear Filters Button -->
              <button type="button" onclick="clearFilters()" class="px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                Clear Filters
              </button>
            </div>
          </div>
        </div>

        <!-- Results Summary -->
        <div class="mb-6">
          <p class="text-sm text-gray-600">
            <span id="resultsText">Showing <?= count($students) ?> students</span>
            <?php if (!empty($_GET['debug'])): ?>
              <span class="ml-2 text-xs text-blue-600">(found: <?= count($students) ?> students)</span>
            <?php endif; ?>
          </p>
        </div>

        <!-- Students Table -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
          <div class="overflow-x-auto -mx-4 sm:mx-0">
            <div class="inline-block min-w-full align-middle">
              <table class="min-w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Information</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Contact Details</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Program</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="studentsTableBody">
                <?php if (!empty($students)): ?>
                  <?php foreach ($students as $student): 
                    // Generate avatar color and initial
                    $colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-yellow-500', 'bg-red-500'];
                    $avatarColor = $colors[abs(crc32($student['name'])) % count($colors)];
                    $initial = strtoupper(substr($student['name'], 0, 1));
                    $displayName = htmlspecialchars($student['name']);
                    
                    // Program count
                    $programCount = $student['program_count'];
                    $activeProgramCount = $programCount; // Assume all are active for tutors
                    
                    // Format programs display
                    if ($programCount == 0) {
                      $programClass = 'program-none';
                      $programText = 'No Programs';
                    } else if ($activeProgramCount > 0) {
                      $programClass = 'program-active';
                      $programText = $activeProgramCount . ' Active';
                    } else {
                      $programClass = 'program-multiple';
                      $programText = $programCount . ' Programs';
                    }

                    // Status display text
                    $statusText = 'Active'; // Tutors typically see active students
                  ?>

                    <tr class="student-row" 
                        data-name="<?= strtolower($student['name']) ?>"
                        data-email="<?= strtolower($student['email']) ?>"
                        data-programs="<?= strtolower($student['program_names']) ?>"
                        data-contact="<?= strtolower($student['display_contact']) ?>">
                      <td class="px-4 sm:px-6 py-4">
                        <div class="flex items-center">
                          <div class="w-10 h-10 <?= $avatarColor ?> rounded-full flex items-center justify-center mr-3 sm:mr-4">
                            <span class="text-white font-medium text-sm"><?= $initial ?></span>
                          </div>
                          <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-gray-900"><?= $displayName ?></div>
                            <div class="text-sm text-gray-500"><?= htmlspecialchars($student['user_id'] ?: $student['username']) ?></div>
                            <div class="lg:hidden mt-1">
                              <div class="text-xs text-gray-600"><?= htmlspecialchars($student['email']) ?></div>
                              <div class="text-xs text-gray-500">
                                <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                  <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                </svg>
                                <?= htmlspecialchars($student['display_contact']) ?>
                              </div>
                              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $activeProgramCount > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?> mt-1">
                                <?= $programText ?>
                              </span>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                        <div class="text-sm text-gray-900"><?= htmlspecialchars($student['email']) ?></div>
                        <div class="text-sm text-gray-500 flex items-center">
                          <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                          </svg>
                          <?= htmlspecialchars($student['display_contact']) ?>
                        </div>
                      </td>
                      <td class="px-4 sm:px-6 py-4 whitespace-nowrap hidden md:table-cell">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $activeProgramCount > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                          <?= $programText ?>
                        </span>
                        <?php if (!empty($student['program_names'])): ?>
                          <div class="text-xs text-gray-500 mt-1">
                            <?= htmlspecialchars($student['program_names']) ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
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
                                <button onclick="viewProgress(<?= $student['id'] ?>)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 block">
                                  View Progress
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="px-6 py-12 text-center">
                      <div class="mx-auto max-w-sm">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-2.239"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No Students Found</h3>
                        <?php
                        // Check if tutor has any programs
                        $program_check = $conn->query("SELECT COUNT(*) as count FROM programs WHERE tutor_id = $tutor_user_id");
                        $program_count = $program_check->fetch_assoc()['count'];
                        
                        if ($program_count == 0): ?>
                          <p class="mt-1 text-sm text-gray-500">
                            You don't have any programs assigned yet. Contact an administrator to get programs assigned to you.
                          </p>
                          <div class="mt-3">
                            <a href="../tutor/programs.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-tplearn-green hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                              <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                              </svg>
                              View Programs
                            </a>
                          </div>
                        <?php else: ?>
                          <p class="mt-1 text-sm text-gray-500">
                            You have <?= $program_count ?> program<?= $program_count > 1 ? 's' : '' ?> but no students are enrolled yet.
                          </p>
                          <div class="mt-3">
                            <a href="../tutor/programs.php" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                              View My Programs
                            </a>
                          </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($_GET['debug'])): ?>
                          <div class="mt-4 text-left">
                            <p class="text-xs text-gray-400">Debug: Tutor ID <?= $tutor_user_id ?> | Programs: <?= $program_count ?></p>
                          </div>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      </main>
    </div>
  </div>

  <script>
    // Combined filter and search functionality
    function filterAndSearch() {
      const searchTerm = document.getElementById('searchInput').value.toLowerCase();
      const programFilter = document.getElementById('programFilter').value.toLowerCase();
      const rows = document.querySelectorAll('.student-row');
      let visibleCount = 0;

      rows.forEach(row => {
        const studentName = row.getAttribute('data-name');
        const studentEmail = row.getAttribute('data-email');
        const studentPrograms = row.getAttribute('data-programs');
        const studentContact = row.getAttribute('data-contact');

        // Check search term (name, email, or contact)
        const matchesSearch = !searchTerm || 
          studentName.includes(searchTerm) || 
          studentEmail.includes(searchTerm) ||
          studentContact.includes(searchTerm);

        // Check program filter
        const matchesProgram = !programFilter || studentPrograms.includes(programFilter);

        if (matchesSearch && matchesProgram) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      // Update results text
      const resultsText = document.getElementById('resultsText');
      if (resultsText) {
        resultsText.textContent = `Showing ${visibleCount} of <?= count($students) ?> students`;
      }

      // Update results count (if element exists)
      const resultsCount = document.getElementById('resultsCount');
      if (resultsCount) {
        resultsCount.textContent = visibleCount;
      }
    }

    // Clear all filters
    function clearFilters() {
      document.getElementById('searchInput').value = '';
      document.getElementById('programFilter').value = '';
      filterAndSearch();
    }

    // Sort functionality
    function sortStudents() {
      const sortBy = document.getElementById('sortBy') ? document.getElementById('sortBy').value : 'name';
      const tableBody = document.getElementById('studentsTableBody');
      const rows = Array.from(tableBody.getElementsByClassName('student-row'));

      rows.sort((a, b) => {
        let aValue, bValue;

        switch (sortBy) {
          case 'name':
            aValue = a.getAttribute('data-name');
            bValue = b.getAttribute('data-name');
            return aValue.localeCompare(bValue);

          case 'program_count':
            // Extract program count from the badge text
            const aBadge = a.querySelector('.bg-green-100, .bg-gray-100');
            const bBadge = b.querySelector('.bg-green-100, .bg-gray-100');
            aValue = aBadge ? parseInt(aBadge.textContent) || 0 : 0;
            bValue = bBadge ? parseInt(bBadge.textContent) || 0 : 0;
            return bValue - aValue; // Descending order

          case 'enrollment_date':
            // Sort by name as fallback since enrollment date not in DOM
            aValue = a.getAttribute('data-name');
            bValue = b.getAttribute('data-name');
            return aValue.localeCompare(bValue);

          default:
            return 0;
        }
      });

      // Re-append sorted rows
      rows.forEach(row => tableBody.appendChild(row));
    }

    // More Options Menu Functions
    function toggleMoreOptions(studentId) {
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
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('[id^="more-btn-"]') && !event.target.closest('[id^="more-menu-"]')) {
        document.querySelectorAll('[id^="more-menu-"]').forEach(menu => {
          menu.classList.add('hidden');
        });
      }
    });

    // Student Details Modal Functions
    function viewStudentDetails(studentId) {
      console.log('Opening student details for ID:', studentId);
      // This would integrate with the same student details functionality as admin
      showInfo('Student details functionality - to be implemented for tutors');
    }

    // More Options Functions
    function viewEnrollments(studentId) {
      document.querySelectorAll('[id^="more-menu-"]').forEach(menu => menu.classList.add('hidden'));
      console.log('View enrollments for student:', studentId);
      showInfo('View enrollments functionality - to be implemented for tutors');
    }

    function viewProgress(studentId) {
      document.querySelectorAll('[id^="more-menu-"]').forEach(menu => menu.classList.add('hidden'));
      console.log('View progress for student:', studentId);
      showInfo('View progress functionality - to be implemented for tutors');
    }

    // Legacy functions for backwards compatibility
    function viewStudent(studentId) {
      viewStudentDetails(studentId);
    }

    function searchStudents() {
      filterAndSearch();
    }

    // Notification functions
    function openNotifications() {
      console.log('Opening notifications...');
      showInfo('Notifications feature coming soon!');
    }

    function openMessages() {
      console.log('Opening messages...');
      showInfo('Messages feature coming soon!');
    }

    // Custom notification system
    function showNotification(message, type = 'info', duration = 5000) {
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg max-w-sm ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
      }`;
      notification.textContent = message;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.remove();
      }, duration);
    }

    // Convenience functions
    function showInfo(message) { showNotification(message, 'info'); }
    function showSuccess(message) { showNotification(message, 'success'); }
    function showError(message) { showNotification(message, 'error'); }
    function showWarning(message) { showNotification(message, 'warning'); }

    // Mobile sidebar functionality
    function initializeMobileSidebar() {
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const mobileCloseButton = document.getElementById('mobile-close-button');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('mobile-menu-overlay');

      function showMobileSidebar() {
        if (sidebar) sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
      }

      function hideMobileSidebar() {
        if (sidebar) sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
      }

      if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', showMobileSidebar);
      }

      if (mobileCloseButton) {
        mobileCloseButton.addEventListener('click', hideMobileSidebar);
      }

      if (overlay) {
        overlay.addEventListener('click', hideMobileSidebar);
      }

      // Close sidebar on escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          hideMobileSidebar();
        }
      });
    }

    // Initialize all functionality when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
      // Set up real-time search
      const searchInput = document.getElementById('searchInput');
      if (searchInput) {
        searchInput.addEventListener('input', filterAndSearch);
        searchInput.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            filterAndSearch();
          }
        });
      }

      // Set up program filter
      const programFilter = document.getElementById('programFilter');
      if (programFilter) {
        programFilter.addEventListener('change', filterAndSearch);
      }

      // Initialize mobile sidebar
      initializeMobileSidebar();
    });
  </script>
</body>

</html>