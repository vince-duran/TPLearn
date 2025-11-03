<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('tutor');

// Get current tutor data from session
$tutor_user_id = $_SESSION['user_id'] ?? null;

// Debug session data
error_log("Tutor session data: " . print_r($_SESSION, true));

// Check if user_id is available
if (!$tutor_user_id) {
  header('Location: ../../login.php');
  exit();
}

// Get tutor's full name from tutor_profiles
$tutor_name = getTutorFullName($tutor_user_id);

// Get assigned programs for the tutor
$programs = getTutorAssignedPrograms($tutor_user_id);

// Get current date for display
$currentDate = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Programs - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  
  <style>
    /* Custom styles */
    .program-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      transition: all 0.2s ease;
    }

    .program-card:hover {
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .tab-content {
      display: block;
    }

    .tab-content.hidden {
      display: none;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/tutor-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1 flex flex-col h-screen">
      <!-- Top Header -->
      <header class="bg-white shadow-sm border-b border-gray-200 px-4 sm:px-6 py-4 flex-shrink-0">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
          <div class="flex items-center">
            <!-- Mobile menu button -->
            <button id="mobile-menu-button" class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-tplearn-green mr-3 transition-colors">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>
          <div class="flex items-center space-x-3 sm:space-x-4">
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
              <span class="text-sm font-medium text-gray-700 hidden sm:inline"><?php echo htmlspecialchars($tutor_name); ?></span>
              <div class="w-8 h-8 bg-tplearn-green rounded-full flex items-center justify-center text-white font-semibold text-sm">
                <?php echo strtoupper(substr($tutor_name, 0, 1)); ?>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content Area -->
      <main class="p-6 flex-1 overflow-y-auto">

        <!-- Tab Navigation -->
        <div class="mb-6">
          <div class="border-b border-gray-200">
            <nav class="flex space-x-8">
              <button id="programs-tab" class="py-2 px-1 border-b-2 border-tplearn-green text-tplearn-green font-medium text-sm whitespace-nowrap" onclick="switchTab('programs')">
                Programs
              </button>
              <button id="students-tab" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap" onclick="switchTab('students')">
                Students
              </button>
            </nav>
          </div>
        </div>

        <!-- Programs Tab Content -->
        <div id="programs-content" class="tab-content">
          <!-- Filter Buttons -->
          <div class="mb-6">
            <div class="flex flex-wrap gap-2">
              <button id="all-programs-btn" class="px-4 py-2 bg-tplearn-green text-white rounded-lg text-sm font-medium" onclick="filterPrograms('all')">
                All Programs
              </button>
              <button id="online-programs-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300" onclick="filterPrograms('online')">
                Online Programs
              </button>
              <button id="inperson-programs-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300" onclick="filterPrograms('in-person')">
                In-Person Programs
              </button>
            </div>
          </div>

          <!-- Programs List -->
          <div class="space-y-4">
            <?php if (empty($programs)): ?>
              <!-- No Programs State -->
              <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No Assigned Programs</h3>
                <p class="mt-1 text-sm text-gray-500">You haven't been assigned to any programs yet.</p>
                <div class="mt-6">
                  <button class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-tplearn-green hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tplearn-green">
                    Contact Admin
                  </button>
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($programs as $index => $program): ?>
                <?php
                // Generate unique ID for this program
                $program_id = 'program-' . $program['id'];
                
                // Determine status badge
                $status_badge = '';
                $status_color = '';
                
                if ($program['program_status'] === 'completed') {
                  $status_badge = 'Completed';
                  $status_color = 'bg-gray-100 text-gray-800';
                } elseif ($program['program_status'] === 'ongoing') {
                  $status_badge = 'Ongoing';
                  $status_color = 'bg-green-100 text-green-800';
                } else {
                  $status_badge = 'Upcoming';
                  $status_color = 'bg-blue-100 text-blue-800';
                }

                // Format session type for data attribute
                $session_type_attr = strtolower($program['session_type']);
                ?>
                
                <!-- <?php echo htmlspecialchars($program['name']); ?> Program -->
                <div class="bg-white rounded-lg shadow program-card" data-type="<?php echo $session_type_attr; ?>">
                <div class="p-6">
                  <div class="flex items-center justify-between mb-4 cursor-pointer" onclick="toggleProgram('<?php echo $program_id; ?>')">
                    <div class="flex-1">
                      <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($program['name']); ?></h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                          <?php echo $status_badge; ?>
                        </span>
                      </div>
                      <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($program['description']); ?></p>

                      <!-- Progress Bar -->
                      <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                          <span class="text-sm font-medium text-gray-700">Program Progress</span>
                          <span class="text-sm font-medium text-gray-700"><?php echo $program['progress_percentage']; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                          <div class="bg-tplearn-green h-2 rounded-full" style="width: <?php echo $program['progress_percentage']; ?>%"></div>
                        </div>
                      </div>

                      <!-- Program Info -->
                      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div class="flex items-center text-gray-600">
                          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                          </svg>
                          <?php echo $program['students_count']; ?>/<?php echo $program['max_students']; ?> Students
                        </div>
                        <div class="flex items-center text-gray-600">
                          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                          </svg>
                          <?php echo htmlspecialchars($program['next_session']['date']); ?>
                          <?php if (!empty($program['next_session']['time']) && $program['next_session']['time'] !== 'Time TBD'): ?>
                            <br><span class="text-xs"><?php echo $program['next_session']['time']; ?></span>
                          <?php endif; ?>
                        </div>
                        <div class="flex items-center text-gray-600">
                          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                          </svg>
                          <?php echo ucfirst($program['session_type']); ?> Sessions
                        </div>
                      </div>
                    </div>
                    <div class="ml-4">
                      <svg id="<?php echo $program_id; ?>-icon" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                      </svg>
                    </div>
                  </div>

                  <!-- Action Buttons -->
                  <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button onclick="markAttendance('<?php echo $program['id']; ?>')" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-3 rounded-lg flex items-center justify-center space-x-2 transition-colors text-sm">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                      </svg>
                      <span>Attendance</span>
                    </button>
                  </div>

                  <!-- Expanded Content -->
                  <div id="<?php echo $program_id; ?>-details" class="hidden border-t border-gray-200 pt-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                      <!-- Program Details -->
                      <div>
                        <h4 class="font-semibold text-gray-800 mb-3">Program Details</h4>
                        <div class="space-y-2 text-sm">
                          <div class="flex justify-between">
                            <span class="text-gray-600">Schedule:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($program['days'] ?? 'TBD'); ?></span>
                          </div>
                          <div class="flex justify-between">
                            <span class="text-gray-600">Duration:</span>
                            <span class="font-medium"><?php echo $program['duration_weeks'] ?? 8; ?> weeks</span>
                          </div>
                          <div class="flex justify-between">
                            <span class="text-gray-600">Category:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($program['category'] ?? 'General'); ?></span>
                          </div>
                          <div class="flex justify-between">
                            <span class="text-gray-600">Age Group:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($program['age_group'] ?? 'All Ages'); ?></span>
                          </div>
                          <div class="flex justify-between">
                            <span class="text-gray-600">Fee:</span>
                            <span class="font-medium">₱<?php echo number_format($program['fee'], 2); ?></span>
                          </div>
                          <?php if ($program['session_type'] === 'in-person' && !empty($program['location'])): ?>
                          <div class="flex justify-between">
                            <span class="text-gray-600">Location:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($program['location']); ?></span>
                          </div>
                          <?php endif; ?>
                        </div>
                      </div>

                      <!-- Quick Actions -->
                      <div>
                        <h4 class="font-semibold text-gray-800 mb-3">Quick Actions</h4>
                        <div class="space-y-2">
                          <button onclick="uploadMaterials('<?php echo $program['id']; ?>')" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg flex items-center justify-center space-x-2 transition-colors text-sm">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Upload Materials</span>
                          </button>
                          <button onclick="manageGrades('<?php echo $program['id']; ?>')" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg flex items-center justify-center space-x-2 transition-colors text-sm">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                              <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Manage Grades</span>
                          </button>
                          <button onclick="viewStudents('<?php echo $program['id']; ?>')" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center justify-center space-x-2 transition-colors text-sm">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                            </svg>
                            <span>View Students</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Students Tab Content -->
        <div id="students-content" class="tab-content hidden">
          <?php
          // Get all students from tutor's programs
          $all_students = [];
          foreach ($programs as $program) {
            $program_students = getProgramStudents($program['id']);
            foreach ($program_students as $student) {
              $student['program_name'] = $program['name'];
              $student['program_id'] = $program['id'];
              // Avoid duplicates if student is in multiple programs
              $student_key = $student['user_id'] . '_' . $program['id'];
              $all_students[$student_key] = $student;
            }
          }
          ?>
          
          <!-- Search and Filter Controls -->
          <div class="bg-white rounded-lg shadow mb-6 p-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
              <!-- Search and Filters in one row -->
              <div class="flex flex-1 gap-3 items-center">
                <!-- Search Bar -->
                <div class="flex-1 max-w-md">
                  <div class="relative">
                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                    </svg>
                    <input type="text" id="studentSearch" placeholder="Search students by name or email..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent"
                           onkeyup="filterStudents()">
                  </div>
                  </div>
                </div>
                
                <!-- Program Filter -->
                <select id="programFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" onchange="filterStudents()">
                  <option value="">All Programs</option>
                  <?php foreach ($programs as $program): ?>
                    <option value="<?php echo htmlspecialchars($program['name']); ?>"><?php echo htmlspecialchars($program['name']); ?></option>
                  <?php endforeach; ?>
                </select>
                
                <!-- Status Filter -->
                <select id="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" onchange="filterStudents()">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="paused">Paused</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              
              <!-- Action Buttons -->
              <div class="flex gap-2">
                <button onclick="exportStudentList()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors flex items-center">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                  </svg>
                  Export
                </button>
                <button onclick="clearAllFilters()" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700 transition-colors">
                  Clear Filters
                </button>
              </div>
            </div>
          </div>
          
          <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
              <div class="flex justify-between items-center">
                <div>
                  <h3 class="text-lg font-semibold text-gray-800">All Students</h3>
                  <p class="text-sm text-gray-600 mt-1">Students enrolled in your programs</p>
                </div>
                <div class="text-sm text-gray-500">
                  Total: <span id="studentCount"><?php echo count($all_students); ?></span> students
                  <span id="filteredCount" class="hidden">(<span id="filteredNumber">0</span> filtered)</span>
                </div>
              </div>
            </div>
            
            <?php if (empty($all_students)): ?>
              <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-2.239" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No students yet</h3>
                <p class="mt-1 text-sm text-gray-500">Students will appear here once they enroll in your programs.</p>
              </div>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="w-full" id="studentsTable">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('name')">
                        Student
                        <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('program')">
                        Program
                        <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('status')">
                        Status
                        <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('attendance')">
                        Attendance
                        <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('enrolled')">
                        Enrolled
                        <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200" id="studentsTableBody">
                    <?php foreach ($all_students as $student): ?>
                      <tr class="hover:bg-gray-50 student-row" 
                          data-student-name="<?php echo strtolower(htmlspecialchars($student['full_name'])); ?>"
                          data-student-email="<?php echo strtolower(htmlspecialchars($student['email'])); ?>"
                          data-program="<?php echo htmlspecialchars($student['program_name']); ?>"
                          data-status="<?php echo htmlspecialchars($student['enrollment_status']); ?>"
                          data-attendance="<?php echo $student['attendance_rate']; ?>"
                          data-enrolled="<?php echo strtotime($student['enrollment_date']); ?>"
                          data-student-id="<?php echo $student['user_id']; ?>"
                          data-program-id="<?php echo $student['program_id']; ?>">
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                              <div class="h-10 w-10 rounded-full bg-tplearn-green flex items-center justify-center text-white font-medium">
                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                              </div>
                            </div>
                            <div class="ml-4">
                              <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></div>
                              <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['email']); ?></div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['program_name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $student['enrollment_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo ucfirst($student['enrollment_status']); ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="text-sm text-gray-900"><?php echo number_format($student['attendance_rate'], 1); ?>%</div>
                            <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                              <div class="<?php 
                                echo $student['attendance_rate'] >= 90 ? 'bg-green-500' : 
                                     ($student['attendance_rate'] >= 70 ? 'bg-yellow-500' : 'bg-red-500'); 
                              ?> h-2 rounded-full" style="width: <?php echo $student['attendance_rate']; ?>%"></div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <?php echo date('M j, Y', strtotime($student['enrollment_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                          <div class="flex space-x-2">
                            <button onclick="viewStudentDetails(<?php echo $student['user_id']; ?>, <?php echo $student['program_id']; ?>)" 
                                    class="text-tplearn-green hover:text-green-700 hover:bg-green-50 px-2 py-1 rounded transition-colors">
                              View Details
                            </button>
                            <button onclick="manageStudentGrades(<?php echo $student['user_id']; ?>, <?php echo $student['program_id']; ?>)" 
                                    class="text-yellow-600 hover:text-yellow-900 hover:bg-yellow-50 px-2 py-1 rounded transition-colors">
                              Grades
                            </button>
                            <button onclick="markStudentAttendance(<?php echo $student['user_id']; ?>)" 
                                    class="text-purple-600 hover:text-purple-900 hover:bg-purple-50 px-2 py-1 rounded transition-colors">
                              Attendance
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              
              <!-- Pagination -->
              <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                  Showing <span id="studentsShowing">1</span> to <span id="studentsTo"><?php echo min(10, count($all_students)); ?></span> of <span id="studentsTotal"><?php echo count($all_students); ?></span> students
                </div>
                <div class="flex space-x-2" id="paginationControls">
                  <button onclick="previousPage()" id="prevBtn" class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50" disabled>
                    Previous
                  </button>
                  <span id="pageNumbers" class="flex space-x-1">
                    <!-- Page numbers will be generated by JS -->
                  </span>
                  <button onclick="nextPage()" id="nextBtn" class="px-3 py-1 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50">
                    Next
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Join Online Modal -->
  <div id="joinOnlineModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Join Online Session</h3>
          <p class="text-sm text-gray-600 mt-1">Math Excellence - Today, 3:00 PM</p>
        </div>
        <button onclick="closeJoinOnlineModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Connection Setup -->
          <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Session Info</h4>
              <div class="space-y-2 text-sm">
                <div>
                  <span class="text-gray-600">Duration:</span>
                  <span class="font-medium">90 minutes</span>
                </div>
                <div>
                  <span class="text-gray-600">Students Expected:</span>
                  <span class="font-medium">6 students</span>
                </div>
                <div>
                  <span class="text-gray-600">Session Type:</span>
                  <span class="font-medium">Interactive Lesson</span>
                </div>
                <div>
                  <span class="text-gray-600">Status:</span>
                  <span id="sessionStatus" class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs font-medium">Not Started</span>
                </div>
              </div>
              
              <!-- Session Actions -->
              <div class="mt-6 space-y-3">
                <button onclick="startSession()" class="w-full bg-tplearn-green hover:bg-green-700 text-white py-3 px-4 rounded-lg font-medium">
                  Start Session
                </button>
                <button onclick="closeJoinOnlineModal()" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-lg">
                  Cancel
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Attendance Management Modal -->
  <div id="attendanceManagementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Attendance Management</h3>
          <p class="text-sm text-gray-600 mt-1">Math Excellence Program</p>
        </div>
        <button onclick="closeAttendanceManagementModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Session Date Selection -->
        <div class="mb-6">
          <label for="sessionDateSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Session Date</label>
          <select id="sessionDateSelect" name="sessionDate" onchange="loadSessionAttendance(this.value)" class="w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <!-- Options will be populated dynamically based on program dates -->
          </select>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <!-- Attendance Overview -->
          <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Today's Session</h4>
              <div class="space-y-3 text-sm">
                <div id="sessionTimeInfo">
                  <span class="text-gray-600">Time:</span>
                  <p class="font-medium">3:00 PM - 4:30 PM</p>
                </div>
                <div>
                  <span class="text-gray-600">Expected:</span>
                  <p id="expectedCount" class="font-medium">6 students</p>
                </div>
                <div>
                  <span class="text-gray-600">Present:</span>
                  <p id="presentCount" class="font-medium text-green-600">4</p>
                </div>
                <div>
                  <span class="text-gray-600">Absent:</span>
                  <p id="absentCount" class="font-medium text-red-600">2</p>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Quick Actions</h4>
              <div class="space-y-2">
                <button onclick="markAllPresent()" class="w-full bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
                  Mark All Present
                </button>
                <button onclick="markAllAbsent()" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 transition-colors">
                  Mark All Absent
                </button>
                <button onclick="exportAttendanceReport()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
                  Export Report
                </button>
                <button onclick="sendAbsentNotices()" class="w-full bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700 transition-colors">
                  Notify Absentees
                </button>
              </div>
            </div>
          </div>

          <!-- Student Attendance List -->
          <div class="lg:col-span-3">
            <div class="bg-white rounded-lg border">
              <div class="px-6 py-4 border-b">
                <h4 class="font-semibold text-gray-900">Student Attendance</h4>
              </div>
              <div class="divide-y divide-gray-200" id="attendanceStudentList">
                <!-- Student attendance items will be populated here -->
                <div class="p-4 hover:bg-gray-50">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                      <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                        S1
                      </div>
                      <div>
                        <p class="font-medium text-gray-900">Student 1</p>
                        <p class="text-sm text-gray-600">student1@email.com</p>
                      </div>
                    </div>
                    <div class="flex items-center space-x-4">
                      <span class="text-sm text-gray-600">Joined: 3:00 PM</span>
                      <select class="px-3 py-1 border border-gray-300 rounded text-sm" onchange="updateStudentAttendance('student1', this.value)">
                        <option value="present" selected>Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div class="p-4 hover:bg-gray-50">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                      <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                        S2
                      </div>
                      <div>
                        <p class="font-medium text-gray-900">Student 2</p>
                        <p class="text-sm text-gray-600">student2@email.com</p>
                      </div>
                    </div>
                    <div class="flex items-center space-x-4">
                      <span class="text-sm text-gray-600">Did not join</span>
                      <select class="px-3 py-1 border border-gray-300 rounded text-sm" onchange="updateStudentAttendance('student2', this.value)">
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

        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
          <button onclick="closeAttendanceManagementModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
            Close
          </button>
          <button onclick="saveAttendanceData()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
            Save Attendance
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Upload Materials Modal -->
  <div id="uploadMaterialsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Upload Materials</h3>
          <p class="text-sm text-gray-600 mt-1">Add new learning materials</p>
        </div>
        <button onclick="closeUploadMaterialsModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <form id="materialUploadForm" onsubmit="submitMaterialUpload(event)">
          <!-- Material Type -->
          <div class="mb-4">
            <label for="materialType" class="block text-sm font-medium text-gray-700 mb-2">Material Type</label>
            <select id="materialType" name="materialType" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <option value="document">Document</option>
              <option value="image">Image</option>
              <option value="slides">Slides</option>
              <option value="assignment">Assignment</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Title -->
          <div class="mb-4">
            <label for="materialTitle" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
            <input type="text" id="materialTitle" name="title" placeholder="e.g. Algebra Fundamentals" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          </div>

          <!-- Description -->
          <div class="mb-4">
            <label for="materialDescription" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea id="materialDescription" name="description" rows="3" placeholder="Brief description of this material..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"></textarea>
          </div>

          <!-- File Upload -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">File</label>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-gray-400 transition-colors" onclick="document.getElementById('materialFileInput').click()">
              <input type="file" id="materialFileInput" name="files" class="hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt" onchange="handleMaterialFileSelect(event)">
              <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
              </svg>
              <p class="text-blue-600 font-medium mb-1">Upload a file</p>
              <p class="text-gray-500 text-sm">or drag and drop</p>
              <p class="text-gray-400 text-xs mt-2">PDF, DOC, DOCX up to 10MB</p>
            </div>
            <div id="materialFileList" class="mt-3"></div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeUploadMaterialsModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
              Cancel
            </button>
            <button type="submit" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
              Upload
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Manage Grades Modal -->
  <div id="manageGradesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Manage Student Grades</h3>
          <p class="text-sm text-gray-600 mt-1">Math Excellence - Final Grades (8 Students Enrolled)</p>
        </div>
        <button onclick="closeManageGradesModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Student Grades List -->
        <div class="space-y-4 mb-6">
          <!-- Student Headers -->
          <div class="grid grid-cols-12 gap-4 px-4 py-2 bg-gray-50 rounded-lg text-sm font-medium text-gray-700">
            <div class="col-span-1">Student</div>
            <div class="col-span-6">Current Grade</div>
            <div class="col-span-3">Update Grade</div>
            <div class="col-span-2"></div>
          </div>

          <!-- Student 1 -->
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50">
            <div class="col-span-1">
              <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                A
              </div>
            </div>
            <div class="col-span-6">
              <div>
                <p class="font-medium text-gray-900">Student 1</p>
                <div class="flex items-center space-x-2 mt-1">
                  <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">95%</span>
                  <span class="text-sm text-gray-500">95</span>
                </div>
              </div>
            </div>
            <div class="col-span-3">
              <input type="number" value="95" min="0" max="100" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateStudentGrade('student1', this.value)">
            </div>
            <div class="col-span-2">
              <button onclick="editStudentGrade('student1')" class="text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Student 2 -->
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50">
            <div class="col-span-1">
              <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                B
              </div>
            </div>
            <div class="col-span-6">
              <div>
                <p class="font-medium text-gray-900">Student 2</p>
                <div class="flex items-center space-x-2 mt-1">
                  <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">77%</span>
                  <span class="text-sm text-gray-500">77</span>
                </div>
              </div>
            </div>
            <div class="col-span-3">
              <input type="number" value="77" min="0" max="100" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateStudentGrade('student2', this.value)">
            </div>
            <div class="col-span-2">
              <button onclick="editStudentGrade('student2')" class="text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Student 3 -->
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50">
            <div class="col-span-1">
              <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                C
              </div>
            </div>
            <div class="col-span-6">
              <div>
                <p class="font-medium text-gray-900">Student 3</p>
                <div class="flex items-center space-x-2 mt-1">
                  <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">94%</span>
                  <span class="text-sm text-gray-500">94</span>
                </div>
              </div>
            </div>
            <div class="col-span-3">
              <input type="number" value="94" min="0" max="100" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateStudentGrade('student3', this.value)">
            </div>
            <div class="col-span-2">
              <button onclick="editStudentGrade('student3')" class="text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Student 4 -->
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50">
            <div class="col-span-1">
              <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                D
              </div>
            </div>
            <div class="col-span-6">
              <div>
                <p class="font-medium text-gray-900">Student 4</p>
                <div class="flex items-center space-x-2 mt-1">
                  <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">98%</span>
                  <span class="text-sm text-gray-500">98</span>
                </div>
              </div>
            </div>
            <div class="col-span-3">
              <input type="number" value="98" min="0" max="100" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateStudentGrade('student4', this.value)">
            </div>
            <div class="col-span-2">
              <button onclick="editStudentGrade('student4')" class="text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Student 5 -->
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50">
            <div class="col-span-1">
              <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                E
              </div>
            </div>
            <div class="col-span-6">
              <div>
                <p class="font-medium text-gray-900">Student 5</p>
                <div class="flex items-center space-x-2 mt-1">
                  <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">70%</span>
                  <span class="text-sm text-gray-500">70</span>
                </div>
              </div>
            </div>
            <div class="col-span-3">
              <input type="number" value="70" min="0" max="100" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateStudentGrade('student5', this.value)">
            </div>
            <div class="col-span-2">
              <button onclick="editStudentGrade('student5')" class="text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Student 6 -->
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50">
            <div class="col-span-1">
              <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                F
              </div>
            </div>
            <div class="col-span-6">
              <div>
                <p class="font-medium text-gray-900">Student 6</p>
                <div class="flex items-center space-x-2 mt-1">
                  <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">80%</span>
                  <span class="text-sm text-gray-500">80</span>
                </div>
              </div>
            </div>
            <div class="col-span-3">
              <input type="number" value="80" min="0" max="100" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateStudentGrade('student6', this.value)">
            </div>
            <div class="col-span-2">
              <button onclick="editStudentGrade('student6')" class="text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Student 7 -->
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50">
            <div class="col-span-1">
              <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                G
              </div>
            </div>
            <div class="col-span-6">
              <div>
                <p class="font-medium text-gray-900">Student 7</p>
                <div class="flex items-center space-x-2 mt-1">
                  <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">80%</span>
                  <span class="text-sm text-gray-500">80</span>
                </div>
              </div>
            </div>
            <div class="col-span-3">
              <input type="number" value="80" min="0" max="100" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateStudentGrade('student7', this.value)">
            </div>
            <div class="col-span-2">
              <button onclick="editStudentGrade('student7')" class="text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Student 8 -->
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50">
            <div class="col-span-1">
              <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                H
              </div>
            </div>
            <div class="col-span-6">
              <div>
                <p class="font-medium text-gray-900">Student 8</p>
                <div class="flex items-center space-x-2 mt-1">
                  <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">70%</span>
                  <span class="text-sm text-gray-500">70</span>
                </div>
              </div>
            </div>
            <div class="col-span-3">
              <input type="number" value="70" min="0" max="100" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateStudentGrade('student8', this.value)">
            </div>
            <div class="col-span-2">
              <button onclick="editStudentGrade('student8')" class="text-blue-600 hover:text-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Grading Notes -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Grading Notes</label>
          <textarea rows="3" placeholder="Add any notes about the grading criteria..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"></textarea>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center pt-4 border-t">
          <div class="flex space-x-3">
            <button onclick="downloadGrades()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 flex items-center transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              Download CSV
            </button>
            <button onclick="printGrades()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 flex items-center transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
              </svg>
              Print Report
            </button>
          </div>
          <div class="flex space-x-3">
            <button onclick="closeManageGradesModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
              Cancel
            </button>
            <button onclick="saveAndCloseGrades()" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
              Save & Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Student Details Modal -->
  <div id="studentDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Student Details</h3>
          <p class="text-sm text-gray-600 mt-1" id="studentDetailsSubtitle">Comprehensive student information</p>
        </div>
        <button onclick="closeStudentDetailsModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Student Profile -->
          <div class="lg:col-span-1">
            <div class="bg-gray-50 rounded-lg p-6 text-center">
              <div class="w-24 h-24 bg-tplearn-green rounded-full flex items-center justify-center text-white font-bold text-2xl mx-auto mb-4" id="studentAvatar">
                <!-- Avatar will be populated by JS -->
              </div>
              <h4 class="text-lg font-semibold text-gray-900 mb-2" id="studentFullName">Student Name</h4>
              <p class="text-sm text-gray-600 mb-4" id="studentEmail">student@email.com</p>
              
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-600">Phone:</span>
                  <span class="font-medium" id="studentPhone">-</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Age Group:</span>
                  <span class="font-medium" id="studentAgeGroup">-</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Joined:</span>
                  <span class="font-medium" id="studentJoinDate">-</span>
                </div>
              </div>

              <div class="mt-6 space-y-2">
                <button onclick="contactStudentFromModal()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
                  Send Message
                </button>
                <button onclick="viewStudentProgress()" class="w-full bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
                  View Progress
                </button>
              </div>
            </div>
          </div>

          <!-- Student Details Tabs -->
          <div class="lg:col-span-2">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-6">
              <nav class="flex space-x-8">
                <button id="overview-tab" class="py-2 px-1 border-b-2 border-tplearn-green text-tplearn-green font-medium text-sm" onclick="switchStudentTab('overview')">
                  Overview
                </button>
                <button id="performance-tab" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm" onclick="switchStudentTab('performance')">
                  Performance
                </button>
                <button id="attendance-tab" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm" onclick="switchStudentTab('attendance')">
                  Attendance
                </button>
                <button id="assignments-tab" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm" onclick="switchStudentTab('assignments')">
                  Assignments
                </button>
              </nav>
            </div>

            <!-- Overview Tab -->
            <div id="overview-content" class="student-tab-content">
              <div class="space-y-6">
                <!-- Enrollment Information -->
                <div class="bg-white border rounded-lg p-4">
                  <h5 class="font-semibold text-gray-900 mb-3">Enrollment Information</h5>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span class="text-gray-600">Program:</span>
                      <span class="font-medium block" id="studentProgram">-</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Status:</span>
                      <span class="font-medium block" id="studentStatus">-</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Start Date:</span>
                      <span class="font-medium block" id="studentStartDate">-</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Expected End:</span>
                      <span class="font-medium block" id="studentEndDate">-</span>
                    </div>
                  </div>
                </div>

                <!-- Quick Stats -->
                <div class="grid grid-cols-3 gap-4">
                  <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600" id="studentAttendanceRate">0%</div>
                    <div class="text-sm text-blue-600">Attendance Rate</div>
                  </div>
                  <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600" id="studentAverageGrade">0%</div>
                    <div class="text-sm text-green-600">Average Grade</div>
                  </div>
                  <div class="bg-purple-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-purple-600" id="studentCompletedAssignments">0</div>
                    <div class="text-sm text-purple-600">Assignments Done</div>
                  </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white border rounded-lg p-4">
                  <h5 class="font-semibold text-gray-900 mb-3">Recent Activity</h5>
                  <div id="studentRecentActivity" class="space-y-2">
                    <!-- Activity items will be populated by JS -->
                  </div>
                </div>
              </div>
            </div>

            <!-- Performance Tab -->
            <div id="performance-content" class="student-tab-content hidden">
              <div class="space-y-6">
                <!-- Grade Chart -->
                <div class="bg-white border rounded-lg p-4">
                  <h5 class="font-semibold text-gray-900 mb-3">Grade Progression</h5>
                  <div class="h-64 flex items-center justify-center text-gray-500">
                    Grade chart will be displayed here
                  </div>
                </div>

                <!-- Assignments List -->
                <div class="bg-white border rounded-lg p-4">
                  <h5 class="font-semibold text-gray-900 mb-3">Assignment Grades</h5>
                  <div id="studentAssignmentGrades" class="space-y-2">
                    <!-- Assignment grades will be populated by JS -->
                  </div>
                </div>
              </div>
            </div>

            <!-- Attendance Tab -->
            <div id="attendance-content" class="student-tab-content hidden">
              <div class="space-y-6">
                <!-- Attendance Calendar -->
                <div class="bg-white border rounded-lg p-4">
                  <h5 class="font-semibold text-gray-900 mb-3">Attendance Calendar</h5>
                  <div id="studentAttendanceCalendar" class="grid grid-cols-7 gap-2 text-sm">
                    <!-- Calendar will be populated by JS -->
                  </div>
                </div>

                <!-- Attendance Summary -->
                <div class="bg-white border rounded-lg p-4">
                  <h5 class="font-semibold text-gray-900 mb-3">Attendance Summary</h5>
                  <div id="studentAttendanceSummary">
                    <!-- Summary will be populated by JS -->
                  </div>
                </div>
              </div>
            </div>

            <!-- Assignments Tab -->
            <div id="assignments-content" class="student-tab-content hidden">
              <div class="space-y-4">
                <div class="bg-white border rounded-lg p-4">
                  <h5 class="font-semibold text-gray-900 mb-3">Assignment Status</h5>
                  <div id="studentAssignmentStatus">
                    <!-- Assignment status will be populated by JS -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Actions -->
        <div class="mt-6 flex justify-end space-x-3 pt-4 border-t">
          <button onclick="printStudentReport()" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700 transition-colors">
            Print Report
          </button>
          <button onclick="closeStudentDetailsModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Student Contact Modal -->
  <div id="studentContactModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Contact Student</h3>
          <p class="text-sm text-gray-600 mt-1" id="contactStudentSubtitle">Send a message to the student</p>
        </div>
        <button onclick="closeStudentContactModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <form id="contactStudentForm">
          <!-- Message Type -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Message Type</label>
            <select id="messageType" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
              <option value="general">General Message</option>
              <option value="performance">Performance Update</option>
              <option value="attendance">Attendance Concern</option>
              <option value="assignment">Assignment Reminder</option>
              <option value="congratulations">Congratulations</option>
            </select>
          </div>

          <!-- Subject -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
            <input type="text" id="messageSubject" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" placeholder="Enter message subject">
          </div>

          <!-- Message Templates -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Quick Templates</label>
            <div class="grid grid-cols-2 gap-2">
              <button type="button" onclick="useTemplate('performance')" class="p-2 text-sm bg-blue-50 text-blue-700 rounded hover:bg-blue-100">
                Performance Feedback
              </button>
              <button type="button" onclick="useTemplate('absence')" class="p-2 text-sm bg-yellow-50 text-yellow-700 rounded hover:bg-yellow-100">
                Absence Follow-up
              </button>
              <button type="button" onclick="useTemplate('assignment')" class="p-2 text-sm bg-purple-50 text-purple-700 rounded hover:bg-purple-100">
                Assignment Reminder
              </button>
              <button type="button" onclick="useTemplate('congratulations')" class="p-2 text-sm bg-green-50 text-green-700 rounded hover:bg-green-100">
                Congratulations
              </button>
            </div>
          </div>

          <!-- Message Content -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
            <textarea id="messageContent" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" placeholder="Type your message here..."></textarea>
          </div>

          <!-- Options -->
          <div class="mb-6">
            <label class="flex items-center">
              <input type="checkbox" id="sendEmail" class="rounded border-gray-300 text-tplearn-green focus:ring-tplearn-green" checked>
              <span class="ml-2 text-sm text-gray-700">Send as email notification</span>
            </label>
            <label class="flex items-center mt-2">
              <input type="checkbox" id="saveToHistory" class="rounded border-gray-300 text-tplearn-green focus:ring-tplearn-green" checked>
              <span class="ml-2 text-sm text-gray-700">Save to communication history</span>
            </label>
          </div>

          <!-- Actions -->
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeStudentContactModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
              Cancel
            </button>
            <button type="submit" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
              Send Message
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Session Details Modal -->
  <div id="sessionDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Session Details</h3>
          <p class="text-sm text-gray-600 mt-1" id="sessionDetailsSubtitle">View and manage session information</p>
        </div>
        <button onclick="closeSessionDetailsModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Session Info -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Program</label>
              <div id="sessionProgram" class="text-sm text-gray-900 p-2 bg-gray-50 rounded">-</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date & Time</label>
              <div id="sessionDateTime" class="text-sm text-gray-900 p-2 bg-gray-50 rounded">-</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Duration</label>
              <div id="sessionDuration" class="text-sm text-gray-900 p-2 bg-gray-50 rounded">-</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Session Type</label>
              <div id="sessionType" class="text-sm text-gray-900 p-2 bg-gray-50 rounded">-</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Location/Link</label>
              <div id="sessionLocation" class="text-sm text-gray-900 p-2 bg-gray-50 rounded">-</div>
            </div>
          </div>
          
          <!-- Students -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Students</label>
              <div id="sessionStudents" class="text-sm text-gray-900 p-2 bg-gray-50 rounded min-h-[100px]">-</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <div id="sessionStatus" class="text-sm text-gray-900 p-2 bg-gray-50 rounded">-</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <div id="sessionNotes" class="text-sm text-gray-900 p-2 bg-gray-50 rounded min-h-[60px]">-</div>
            </div>
          </div>
        </div>

        <!-- Session Actions -->
        <div class="mt-6 pt-4 border-t border-gray-200">
          <div class="flex flex-wrap gap-2">
            <button id="startSessionBtn" onclick="startSession()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors hidden">
              Start Session
            </button>
            <button id="joinSessionBtn" onclick="joinSession()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors hidden">
              Join Session
            </button>
            <button id="endSessionBtn" onclick="endSession()" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 transition-colors hidden">
              End Session
            </button>
            <button id="markAttendanceBtn" onclick="markSessionAttendance()" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 transition-colors">
              Mark Attendance
            </button>
            <button id="editSessionBtn" onclick="editSession()" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700 transition-colors">
              Edit Session
            </button>
            <button id="cancelSessionBtn" onclick="cancelSession()" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 transition-colors">
              Cancel Session
            </button>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="mt-6 flex justify-end space-x-3 pt-4 border-t">
          <button onclick="closeSessionDetailsModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add/Edit Session Modal -->
  <div id="addEditSessionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900" id="addEditSessionTitle">Add New Session</h3>
          <p class="text-sm text-gray-600 mt-1">Schedule a new session for your program</p>
        </div>
        <button onclick="closeAddEditSessionModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <form id="sessionForm" class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Left Column -->
          <div class="space-y-4">
            <div>
              <label for="sessionProgramSelect" class="block text-sm font-medium text-gray-700 mb-2">Program *</label>
              <select id="sessionProgramSelect" name="program_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
                <option value="">Select a program</option>
                <!-- Options populated by JavaScript -->
              </select>
            </div>
            
            <div>
              <label for="sessionDate" class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
              <input type="date" id="sessionDate" name="session_date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
            </div>
            
            <div>
              <label for="sessionTime" class="block text-sm font-medium text-gray-700 mb-2">Start Time *</label>
              <input type="time" id="sessionTime" name="session_time" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
            </div>
            
            <div>
              <label for="sessionDurationInput" class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes) *</label>
              <select id="sessionDurationInput" name="duration" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
                <option value="60">1 hour</option>
                <option value="90">1.5 hours</option>
                <option value="120">2 hours</option>
                <option value="150">2.5 hours</option>
                <option value="180">3 hours</option>
              </select>
            </div>
          </div>
          
          <!-- Right Column -->
          <div class="space-y-4">
            <div>
              <label for="sessionTypeSelect" class="block text-sm font-medium text-gray-700 mb-2">Session Type *</label>
              <select id="sessionTypeSelect" name="session_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
                <option value="online">Online</option>
                <option value="in-person">In-Person</option>
                <option value="hybrid">Hybrid</option>
              </select>
            </div>
            
            <div>
              <label for="sessionLocationInput" class="block text-sm font-medium text-gray-700 mb-2">Location/Meeting Link</label>
              <input type="text" id="sessionLocationInput" name="location" placeholder="Enter location or meeting link" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
            </div>
            
            <div>
              <label for="sessionNotesInput" class="block text-sm font-medium text-gray-700 mb-2">Session Notes</label>
              <textarea id="sessionNotesInput" name="notes" rows="3" placeholder="Add any notes or preparation instructions..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent"></textarea>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Repeat Session</label>
              <div class="space-y-2">
                <label class="flex items-center">
                  <input type="checkbox" id="repeatSession" name="repeat_session" class="rounded border-gray-300 text-tplearn-green focus:ring-tplearn-green">
                  <span class="ml-2 text-sm text-gray-700">Repeat this session</span>
                </label>
                <select id="repeatFrequency" name="repeat_frequency" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" disabled>
                  <option value="weekly">Weekly</option>
                  <option value="biweekly">Every 2 weeks</option>
                  <option value="monthly">Monthly</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-6 flex justify-end space-x-3 pt-4 border-t">
          <button type="button" onclick="closeAddEditSessionModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
            Cancel
          </button>
          <button type="submit" class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
            Save Session
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Debug: Check if script is loading
    console.log('🔧 Tutor dashboard script loading...');
    
    // Essential function declarations - moved to top to prevent "function not defined" errors
    
    // Program filtering
    function filterPrograms(type) {
      try {
        console.log('Filtering programs by type:', type);
        
        // Update filter button styles
        const buttons = document.querySelectorAll('#all-programs-btn, #online-programs-btn, #inperson-programs-btn');
        buttons.forEach(btn => {
          btn.classList.remove('bg-tplearn-green', 'text-white');
          btn.classList.add('bg-gray-200', 'text-gray-700');
        });

        // Highlight active filter
        const activeBtn = document.getElementById(type === 'in-person' ? 'inperson-programs-btn' : type + '-programs-btn');
        if (activeBtn) {
          activeBtn.classList.remove('bg-gray-200', 'text-gray-700');
          activeBtn.classList.add('bg-tplearn-green', 'text-white');
        }

        // Filter program cards
        const cards = document.querySelectorAll('.program-card');
        cards.forEach(card => {
          if (type === 'all') {
            card.style.display = 'block';
          } else {
            const cardType = card.getAttribute('data-type');
            card.style.display = cardType === type ? 'block' : 'none';
          }
        });
        
        console.log('Filtered', cards.length, 'cards for type:', type);
      } catch (error) {
        console.error('Error in filterPrograms:', error);
        alert('Error filtering programs: ' + error.message);
      }
    }

    // Program expansion toggle
    function toggleProgram(programId) {
      const details = document.getElementById(programId + '-details');
      const icon = document.getElementById(programId + '-icon');

      if (details && details.classList.contains('hidden')) {
        details.classList.remove('hidden');
        if (icon) icon.style.transform = 'rotate(180deg)';
      } else if (details) {
        details.classList.add('hidden');
        if (icon) icon.style.transform = 'rotate(0deg)';
      }
    }

    // Join online session
    function joinOnline(programId) {
      console.log('Starting online session for program:', programId);
      
      // Show session modal
      const modal = document.getElementById('joinOnlineModal');
      if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Store current program ID for reference
        window.currentProgramId = programId;
        
        // Load basic session information
        loadSessionInfo(programId);
      } else {
        console.error('Join online modal not found');
        showNotification('Session interface not available', 'error');
      }
    }

    // Load session information
    function loadSessionInfo(programId) {
      console.log('Loading session info for program:', programId);
      // For now, just update the modal title - can be enhanced later
      const modalTitle = document.querySelector('#joinOnlineModal h3');
      const modalSubtitle = document.querySelector('#joinOnlineModal p');
      
      if (modalTitle) modalTitle.textContent = 'Join Online Session';
      if (modalSubtitle) modalSubtitle.textContent = 'Session ready to start';
    }

    // Close join online modal
    function closeJoinOnlineModal() {
      const modal = document.getElementById('joinOnlineModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
      // Clear stored program ID
      window.currentProgramId = null;
    }

    // Mark attendance
    function markAttendance(programId) {
      try {
        console.log('Opening attendance management for program:', programId);
        
        const modal = document.getElementById('attendanceManagementModal');
        if (modal) {
          modal.classList.remove('hidden');
          modal.classList.add('flex');
          loadAttendanceData(programId);
        } else {
          console.error('Attendance modal not found');
          TPAlert.info('Information', 'Attendance feature is not available');
        }
      } catch (error) {
        console.error('Error in markAttendance:', error);
        alert('Error opening attendance: ' + error.message);
      }
    }

    // Load attendance data for a program
    function loadAttendanceData(programId) {
      try {
        console.log('Loading attendance data for program:', programId);
        
        // Store the program ID globally for later use
        window.currentProgramId = programId;
        
        // Update modal title
        const modalTitle = document.querySelector('#attendanceManagementModal h3');
        if (modalTitle) {
          modalTitle.textContent = 'Attendance Management';
        }
        
        // Generate session dates and load initial data
        // This would normally fetch from API, but for now we'll show placeholder
        showNotification('Attendance data loaded', 'success');
        
      } catch (error) {
        console.error('Error loading attendance data:', error);
        showNotification('Error loading attendance data: ' + error.message, 'error');
      }
    }

    // Upload materials
    function uploadMaterials(programId) {
      try {
        console.log('Opening upload materials modal for program:', programId);
        
        const modal = document.getElementById('uploadMaterialsModal');
        if (modal) {
          modal.classList.remove('hidden');
          modal.classList.add('flex');
          
          // Store current program ID for upload
          window.currentUploadProgramId = programId;
        } else {
          console.error('Upload materials modal not found');
          TPAlert.info('Information', 'Upload materials feature is not available');
        }
      } catch (error) {
        console.error('Error in uploadMaterials:', error);
        alert('Error opening upload materials: ' + error.message);
      }
    }

    // View students 
    function viewStudents(programId) {
      try {
        console.log('Switching to students view for program:', programId);
        
        // Switch to students tab
        switchTab('students');
        
        // Filter students by program if needed
        const programFilter = document.getElementById('programFilter');
        if (programFilter) {
          programFilter.value = programId;
          filterStudents();
        }
      } catch (error) {
        console.error('Error in viewStudents:', error);
        alert('Error viewing students: ' + error.message);
      }
    }

    // Tab management
    function switchTab(tabName) {
      // Hide all tab contents
      const allTabs = document.querySelectorAll('[id$="-content"]');
      const allTabButtons = document.querySelectorAll('[id$="-tab"]');
      
      allTabs.forEach(tab => tab.classList.add('hidden'));
      allTabButtons.forEach(btn => {
        btn.classList.remove('border-tplearn-green', 'text-tplearn-green');
        btn.classList.add('border-transparent', 'text-gray-500');
      });

      // Show selected tab content
      document.getElementById(tabName + '-content').classList.remove('hidden');

      // Add active class to selected tab
      const activeTab = document.getElementById(tabName + '-tab');
      activeTab.classList.remove('border-transparent', 'text-gray-500');
      activeTab.classList.add('border-tplearn-green', 'text-tplearn-green');
    }

    // Action functions
    
    function populateSessionModal(data) {
      console.log('=== POPULATING SESSION MODAL ===');
      console.log('Received data:', data);
      
      // Update modal title and subtitle
      const modalTitle = document.querySelector('#joinOnlineModal h3');
      const modalSubtitle = document.querySelector('#joinOnlineModal p');
      
      if (modalTitle && modalSubtitle) {
        modalTitle.textContent = 'Join Online Session';
        modalSubtitle.textContent = `${data.program.name} - ${data.session.datetime}`;
        console.log('Updated modal title and subtitle');
      } else {
        console.error('Modal title/subtitle elements not found');
      }
      
      // Update session info section
      const sessionInfo = document.querySelector('#joinOnlineModal .bg-gray-50:nth-child(2)');
      if (sessionInfo) {
        sessionInfo.innerHTML = `
          <h4 class="font-semibold text-gray-900 mb-3">Session Info</h4>
          <div class="space-y-2 text-sm">
            <div>
              <span class="text-gray-600">Duration:</span>
              <span class="font-medium">${data.session.duration}</span>
            </div>
            <div>
              <span class="text-gray-600">Students Expected:</span>
              <span class="font-medium">${data.session.studentsExpected}</span>
            </div>
            <div>
              <span class="text-gray-600">Session Type:</span>
              <span class="font-medium">${data.session.type}</span>
            </div>
            <div>
              <span class="text-gray-600">Status:</span>
              <span id="sessionStatus" class="${getStatusClass(data.session.status)} px-2 py-1 rounded-full text-xs font-medium">
                ${formatSessionStatus(data.session.status, data.session.isActive)}
              </span>
            </div>
          </div>
        `;
        console.log('Updated session info section');
      } else {
        console.error('Session info section not found');
      }
      
      // Store session data globally
      window.currentSessionData = {
        sessionId: data.session.id,
        programId: data.program.id,
        programName: data.program.name,
        isActive: data.session.isActive,
        status: data.session.status
      };
      
      console.log('Stored session data globally:', window.currentSessionData);
      
      // Update start session button based on session status
      updateStartSessionButton(data.session);
      
      console.log('Session modal population completed');
    }
    
    function getStatusClass(status) {
      switch (status) {
        case 'scheduled':
        case 'upcoming':
          return 'bg-blue-100 text-blue-800';
        case 'active':
          return 'bg-green-100 text-green-800';
        case 'completed':
          return 'bg-gray-100 text-gray-800';
        default:
          return 'bg-yellow-100 text-yellow-800';
      }
    }
    
    function formatSessionStatus(status, isActive) {
      if (isActive) {
        return 'Ready to Start';
      }
      
      switch (status) {
        case 'scheduled':
          return 'Scheduled';
        case 'upcoming':
          return 'Upcoming';
        case 'completed':
          return 'Completed';
        default:
          return 'Pending';
      }
    }
    
    function updateStartSessionButton(session) {
      console.log('=== UPDATING START SESSION BUTTON ===');
      console.log('Session data:', session);
      
      const startButton = document.querySelector('[onclick="startSession()"]');
      console.log('Start button found:', !!startButton);
      
      if (startButton) {
        // Allow tutors to start sessions if they're scheduled or upcoming
        // Don't restrict based on exact time - tutors should have flexibility
        if (session.status === 'scheduled' || session.status === 'upcoming' || session.isActive) {
          startButton.textContent = 'Start Session';
          startButton.className = 'w-full bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 font-medium transition-colors';
          startButton.disabled = false;
          console.log('Button enabled for starting session');
        } else if (session.status === 'completed') {
          startButton.textContent = 'Session Completed';
          startButton.className = 'w-full bg-gray-400 text-white px-4 py-2 rounded-lg text-sm cursor-not-allowed';
          startButton.disabled = true;
          console.log('Button disabled - session completed');
        } else if (session.status === 'active') {
          startButton.textContent = 'Join Active Session';
          startButton.className = 'w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 font-medium transition-colors';
          startButton.disabled = false;
          console.log('Button enabled for joining active session');
        } else {
          startButton.textContent = 'Start Session';
          startButton.className = 'w-full bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 font-medium transition-colors';
          startButton.disabled = false;
          console.log('Button enabled - default state');
        }
      } else {
        console.error('Start session button not found in DOM');
      }
    }
    
    function showSessionError(message) {
      const modalTitle = document.querySelector('#joinOnlineModal h3');
      const modalSubtitle = document.querySelector('#joinOnlineModal p');
      
      modalTitle.textContent = 'Error Loading Session';
      modalSubtitle.textContent = message;
      
      // Show error in session info area
      const sessionInfo = document.querySelector('#joinOnlineModal .bg-gray-50:nth-child(2)');
      if (sessionInfo) {
        sessionInfo.innerHTML = `
          <h4 class="font-semibold text-gray-900 mb-3">Error</h4>
          <div class="bg-red-50 border border-red-200 rounded-lg p-3">
            <div class="flex">
              <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
              </svg>
              <div class="ml-3">
                <p class="text-sm text-red-800">${message}</p>
                <button onclick="location.reload()" class="mt-2 text-sm text-red-600 underline">Refresh Page</button>
              </div>
            </div>
          </div>
        `;
      }
    }

    function getConnectionStatusClass(status) {
      switch (status.toLowerCase()) {
        case 'ready':
        case 'stable':
        case 'connected':
          return 'text-green-600 font-medium';
        case 'testing...':
        case 'initializing...':
          return 'text-yellow-600 font-medium animate-pulse';
        case 'error':
        case 'permission denied':
        case 'not found':
        case 'not supported':
        case 'constraints error':
        case 'security error':
        case 'offline':
          return 'text-red-600 font-medium';
        case 'not available':
        case 'unknown':
          return 'text-gray-500 font-medium';
        default:
          return 'text-gray-600 font-medium';
      }
    }

    async function startSession() {
      showNotification('Session started successfully', 'success');
      console.log('Session started');
    }


    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        'bg-blue-500 text-white'
      }`;
      notification.textContent = message;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.remove();
      }, 3000);
    }



        console.log('Session interface prepared');






    function toggleChat() {
      showNotification('Chat feature coming soon!', 'info');
      // TODO: Implement chat functionality
    }

    function closeAttendanceManagementModal() {
      document.getElementById('attendanceManagementModal').classList.add('hidden');
      document.getElementById('attendanceManagementModal').classList.remove('flex');
    }

    function generateSessionDates(program) {
      const sessionSelect = document.getElementById('sessionDateSelect');
      if (!sessionSelect) return;
      
      console.log('Generating session dates for program:', program);
      
      // Clear existing options
      sessionSelect.innerHTML = '';
      
      // Parse program dates and times
      const startDate = new Date(program.start_date);
      const endDate = new Date(program.end_date);
      const currentDate = new Date();
      
      console.log('Program dates:', {
        startDate: startDate.toISOString(),
        endDate: endDate.toISOString(),
        currentDate: currentDate.toISOString(),
        days: program.days
      });
      
      // Format session times
      let sessionTimeText = '';
      if (program.start_time && program.end_time) {
        const startTime = new Date('2000-01-01 ' + program.start_time);
        const endTime = new Date('2000-01-01 ' + program.end_time);
        sessionTimeText = startTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) + 
                         ' - ' + endTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
      } else {
        sessionTimeText = 'Time TBD';
      }
      
      // Parse program days (e.g., "Mon, Wed, Fri" or "Monday, Wednesday, Friday")
      const daysStr = program.days || '';
      const dayMapping = {
        'mon': 1, 'monday': 1,
        'tue': 2, 'tuesday': 2, 'tues': 2,
        'wed': 3, 'wednesday': 3,
        'thu': 4, 'thursday': 4, 'thurs': 4,
        'fri': 5, 'friday': 5,
        'sat': 6, 'saturday': 6,
        'sun': 0, 'sunday': 0
      };
      
      const programDays = [];
      daysStr.toLowerCase().split(/[,\s]+/).forEach(day => {
        const cleanDay = day.trim().replace(/[^\w]/g, '');
        if (dayMapping.hasOwnProperty(cleanDay)) {
          programDays.push(dayMapping[cleanDay]);
        }
      });
      
      // If no valid days found, default to Mon/Wed/Fri
      if (programDays.length === 0) {
        programDays.push(1, 3, 5); // Monday, Wednesday, Friday
      }
      
      // Generate session dates
      const sessionDates = [];
      let iterDate = new Date(startDate);
      
      // Find all session dates between start and end date
      while (iterDate <= endDate) {
        if (programDays.includes(iterDate.getDay())) {
          sessionDates.push(new Date(iterDate));
        }
        iterDate.setDate(iterDate.getDate() + 1);
      }
      
      // Separate sessions into past, current, and future
      const today = new Date();
      today.setHours(0, 0, 0, 0); // Reset time to midnight for accurate comparison
      
      const pastSessions = sessionDates.filter(date => {
        const sessionDate = new Date(date);
        sessionDate.setHours(0, 0, 0, 0);
        return sessionDate < today;
      }).sort((a, b) => b - a); // Recent past first
      
      const currentSession = sessionDates.find(date => {
        const sessionDate = new Date(date);
        sessionDate.setHours(0, 0, 0, 0);
        return sessionDate.getTime() === today.getTime();
      });
      
      const futureSessions = sessionDates.filter(date => {
        const sessionDate = new Date(date);
        sessionDate.setHours(0, 0, 0, 0);
        return sessionDate > today;
      }).sort((a, b) => a - b); // Nearest future first
      
      // Add "Current Session" option if today matches a session day
      if (currentSession) {
        const option = document.createElement('option');
        option.value = 'current';
        option.textContent = `Current Session (Today, ${sessionTimeText})`;
        sessionSelect.appendChild(option);
      }
      
      // Add recent past sessions (most recent first)
      pastSessions.forEach((date, index) => {
        const option = document.createElement('option');
        const dateStr = date.toISOString().split('T')[0];
        option.value = dateStr;
        option.textContent = `Previous Session (${date.toLocaleDateString('en-US', { 
          weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' 
        })}, ${sessionTimeText})`;
        sessionSelect.appendChild(option);
      });
      
      // Add future sessions (nearest first)
      futureSessions.forEach((date, index) => {
        const option = document.createElement('option');
        const dateStr = date.toISOString().split('T')[0];
        option.value = dateStr;
        option.textContent = `Upcoming Session (${date.toLocaleDateString('en-US', { 
          weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' 
        })}, ${sessionTimeText})`;
        sessionSelect.appendChild(option);
      });
      
      // If no sessions found, add a default option
      if (sessionSelect.children.length === 0) {
        const option = document.createElement('option');
        option.value = 'no-sessions';
        option.textContent = 'No sessions scheduled';
        option.disabled = true;
        sessionSelect.appendChild(option);
      }
    }

    function loadSessionAttendance(programId, sessionDate) {
      console.log('Loading attendance for session:', sessionDate);
      
      if (sessionDate === 'current' || sessionDate === 'no-sessions') {
        // For current session, use today's date
        sessionDate = new Date().toISOString().split('T')[0];
      }
      
      // Fetch session-specific attendance
      fetch(`../../api/get-session-attendance.php?program_id=${programId}&session_date=${sessionDate}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateSessionAttendanceDisplay(data);
          } else {
            console.error('Error loading session attendance:', data.error);
          }
        })
        .catch(error => {
          console.error('Error fetching session attendance:', error);
        });
      
      // Update session info in the sidebar
      const sessionInfo = document.querySelector('#attendanceManagementModal .bg-gray-50 h4');
      const timeInfo = document.querySelector('#attendanceManagementModal .bg-gray-50 .space-y-3 div:first-child');
      
      if (sessionInfo && timeInfo) {
        const selectedOption = document.querySelector('#sessionDateSelect option:checked');
        if (selectedOption) {
          if (sessionDate === 'current') {
            sessionInfo.textContent = "Today's Session";
          } else if (sessionDate === 'no-sessions') {
            sessionInfo.textContent = "No Sessions";
          } else {
            const date = new Date(sessionDate);
            const today = new Date();
            
            if (date.toDateString() === today.toDateString()) {
              sessionInfo.textContent = "Today's Session";
            } else if (date < today) {
              sessionInfo.textContent = "Previous Session";
            } else {
              sessionInfo.textContent = "Upcoming Session";
            }
          }
          
          // Extract time from the option text
          const optionText = selectedOption.textContent;
          const timeMatch = optionText.match(/(\d{1,2}:\d{2}\s?[AP]M\s?-\s?\d{1,2}:\d{2}\s?[AP]M)/i);
          if (timeMatch) {
            timeInfo.innerHTML = `<div><span class="text-gray-600">Time:</span><span class="font-medium">${timeMatch[1]}</span></div>`;
          } else {
            timeInfo.innerHTML = `<div><span class="text-gray-600">Time:</span><span class="font-medium">Time TBD</span></div>`;
          }
        }
      }

      // Update attendance counts based on selected session
      updateAttendanceCounts();
    }

    function updateAttendanceCounts() {
      console.log('Updating attendance counts');
      
      const selects = document.querySelectorAll('#attendanceManagementModal select[data-student-id]');
      let presentCount = 0;
      let absentCount = 0;
      let lateCount = 0;

      selects.forEach(select => {
        const value = select.value;
        if (value === 'present') {
          presentCount++;
        } else if (value === 'absent') {
          absentCount++;
        } else if (value === 'late') {
          lateCount++;
        }
      });

      // Update the display
      document.getElementById('presentCount').textContent = presentCount;
      document.getElementById('absentCount').textContent = absentCount;
      
      // Update or create late count display if it doesn't exist
      let lateCountElement = document.getElementById('lateCount');
      if (!lateCountElement) {
        // Add late count to the overview if it doesn't exist
        const absentDiv = document.getElementById('absentCount').parentElement;
        const lateDiv = document.createElement('div');
        lateDiv.innerHTML = `
          <span class="text-gray-600">Late:</span>
          <p id="lateCount" class="font-medium text-yellow-600">${lateCount}</p>
        `;
        absentDiv.parentElement.appendChild(lateDiv);
      } else {
        lateCountElement.textContent = lateCount;
      }
    }

    function updateStudentAttendanceVisual(studentId, status) {
      // Update counts without visual feedback
      updateAttendanceCounts();
    }

    function markAllPresent() {
      const confirmation = (await TPAlert.confirm('Confirm Action', 'Mark all students as present for this session?')).isConfirmed;
      
      if (!confirmation) {
        return;
      }
      
      const selects = document.querySelectorAll('select[data-student-id]');
      let updatedCount = 0;
      
      selects.forEach(select => {
        if (select.value !== 'present') {
          select.value = 'present';
          updatedCount++;
        }
      });
      
      updateAttendanceCounts();
      
      // Show success message
      if (updatedCount > 0) {
        const toast = createToast(`✓ Marked ${updatedCount} students as present`, 'success');
        document.body.appendChild(toast);
        setTimeout(() => document.body.removeChild(toast), 3000);
      }
    }
    
    function markAllAbsent() {
      const confirmation = (await TPAlert.confirm('Confirm Action', 'Mark all students as absent for this session?')).isConfirmed;
      
      if (!confirmation) {
        return;
      }
      
      const selects = document.querySelectorAll('select[data-student-id]');
      let updatedCount = 0;
      
      selects.forEach(select => {
        if (select.value !== 'absent') {
          select.value = 'absent';
          updatedCount++;
        }
      });
      
      updateAttendanceCounts();
      
      // Show success message
      if (updatedCount > 0) {
        const toast = createToast('Marked ' + updatedCount + ' students as absent', 'warning');
        document.body.appendChild(toast);
        setTimeout(() => document.body.removeChild(toast), 3000);
      }
    }

    function createToast(message, type = 'info') {
      const toast = document.createElement('div');
      toast.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform max-w-sm`;
      
      switch(type) {
        case 'success':
          toast.classList.add('bg-green-600');
          break;
        case 'warning':
          toast.classList.add('bg-yellow-600');
          break;
        case 'error':
          toast.classList.add('bg-red-600');
          break;
        default:
          toast.classList.add('bg-blue-600');
      }
      
      // Add icon based on type
      let icon = '';
      switch(type) {
        case 'success':
          icon = '<svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
          break;
        case 'error':
          icon = '<svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
          break;
        case 'warning':
          icon = '<svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>';
          break;
        default:
          icon = '<svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
      }
      
      toast.innerHTML = icon + message;
      toast.style.transform = 'translateX(400px)';
      
      document.body.appendChild(toast);
      
      // Animate in
      setTimeout(() => {
        toast.style.transform = 'translateX(0)';
      }, 100);
      
      // Animate out
      setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => {
          if (document.body.contains(toast)) {
            document.body.removeChild(toast);
          }
        }, 300);
      }, 4000);
      
      return toast;
    }

    function exportAttendanceReport() {
      const programId = window.currentProgramId;
      const sessionDate = document.getElementById('sessionDateSelect').value;
      
      if (!programId) {
        showNotification('Error: Program not selected', 'error');
        return;
      }
      
      // Show loading state
      const button = event.target;
      const originalText = button.textContent;
      button.textContent = 'Generating...';
      button.disabled = true;
      
      // Collect attendance data
      const attendanceData = [];
      const attendanceSelects = document.querySelectorAll('select[data-student-id]');
      
      attendanceSelects.forEach(select => {
        const studentId = select.getAttribute('data-student-id');
        const studentName = select.closest('.p-4').querySelector('.font-medium').textContent;
        const studentEmail = select.closest('.p-4').querySelector('.text-sm').textContent;
        
        attendanceData.push({
          student_id: studentId,
          student_name: studentName,
          student_email: studentEmail,
          status: select.value
        });
      });
      
      // Generate CSV content
      let csvContent = "data:text/csv;charset=utf-8,";
      csvContent += "Student Name,Email,Status,Date\\n";
      
      attendanceData.forEach(row => {
        csvContent += `"${row.student_name}","${row.student_email}","${row.status}","${sessionDate}"\\n`;
      });
      
      // Create download link
      const encodedUri = encodeURI(csvContent);
      const link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", `attendance_${sessionDate}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      // Restore button
      setTimeout(() => {
        button.textContent = originalText;
        button.disabled = false;
      }, 1000);
    }

    function sendAbsentNotices() {
      const absentStudents = [];
      const attendanceSelects = document.querySelectorAll('select[data-student-id]');
      
      attendanceSelects.forEach(select => {
        if (select.value === 'absent') {
          const studentName = select.closest('.p-4').querySelector('.font-medium').textContent;
          const studentEmail = select.closest('.p-4').querySelector('.text-sm').textContent;
          absentStudents.push({ name: studentName, email: studentEmail });
        }
      });
      
      if (absentStudents.length === 0) {
        showNotification('No absent students to notify', 'info');
        return;
      }
      
      const confirmation = (await TPAlert.confirm('Confirm Action', `Send absence notifications to ${absentStudents.length} students?\\n\\n${absentStudents.map(s => s.name)).isConfirmed.join('\\n')}`);
      
      if (confirmation) {
        // Show loading state
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Sending...';
        button.disabled = true;
        
        // Simulate sending notifications (in real implementation, this would call an API)
        setTimeout(() => {
          showNotification(`Absence notifications sent to ${absentStudents.length} students successfully!`, 'success');
          button.textContent = originalText;
          button.disabled = false;
        }, 2000);
      }
    }

    function saveAttendanceData() {
      console.log('=== SAVE ATTENDANCE DEBUG ===');
      
      // Get current session date
      const sessionDateSelect = document.getElementById('sessionDateSelect');
      const selectedDate = sessionDateSelect.value;
      console.log('Selected date:', selectedDate);
      
      if (!selectedDate || selectedDate === 'no-sessions') {
        showNotification('Please select a valid session date', 'error');
        return;
      }
      
      // Get current program ID from the modal
      const programId = window.currentProgramId;
      console.log('Program ID:', programId);
      if (!programId) {
        showNotification('Error: Program ID not found', 'error');
        return;
      }
      
      // Collect attendance data
      const attendanceData = [];
      const attendanceSelects = document.querySelectorAll('select[onchange*="updateStudentAttendance"]');
      console.log('Found attendance selects:', attendanceSelects.length);
      
      attendanceSelects.forEach(select => {
        const studentId = select.getAttribute('data-student-id');
        console.log('Processing student:', studentId, 'status:', select.value);
        if (studentId) {
          attendanceData.push({
            student_user_id: parseInt(studentId),
            status: select.value,
            arrival_time: null, // Could be enhanced to capture actual arrival time
            notes: null
          });
        }
      });
      
      console.log('Attendance data to send:', attendanceData);
      
      if (attendanceData.length === 0) {
        showNotification('No attendance data to save', 'error');
        return;
      }
      
      const payload = {
        program_id: programId,
        session_date: selectedDate,
        attendance_data: attendanceData
      };
      
      console.log('Full payload:', payload);
      
      // Show loading state
      const saveButton = document.querySelector('button[onclick="saveAttendanceData()"]');
      const originalText = saveButton.textContent;
      saveButton.textContent = 'Saving...';
      saveButton.disabled = true;
      
      // Send to API
      fetch('../../api/save-attendance.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
      })
      .then(response => {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        return response.json();
      })
      .then(data => {
        console.log('Response data:', data);
        if (data.success) {
          // Show success message and stay in modal
          showNotification('✓ Attendance saved successfully for ' + data.saved_count + ' students!', 'success');
          
          // Optionally reload the session data to reflect changes
          loadSessionAttendance(selectedDate);
        } else {
          showNotification('Error saving attendance: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        showNotification('Network error while saving attendance: ' + error.message, 'error');
      })
      .finally(() => {
        // Restore button state
        saveButton.textContent = originalText;
        saveButton.disabled = false;
      });
    }

    function closeUploadMaterialsModal() {
      document.getElementById('uploadMaterialsModal').classList.add('hidden');
      document.getElementById('uploadMaterialsModal').classList.remove('flex');
      // Reset form
      document.getElementById('materialUploadForm').reset();
      document.getElementById('materialFileList').innerHTML = '';
      // Clear global program ID
      window.currentUploadProgramId = null;
    }

    function handleMaterialFileSelect(event) {
      const files = event.target.files;
      const fileList = document.getElementById('materialFileList');
      fileList.innerHTML = '';

      if (files.length === 0) {
        return;
      }

      // Only handle the first file (single file upload)
      const file = files[0];
      
      // Validate file size (50MB max)
      const maxSize = 50 * 1024 * 1024; // 50MB
      if (file.size > maxSize) {
        createToast('File size exceeds 50MB limit.', 'error');
        event.target.value = ''; // Clear the input
        return;
      }
      
      // Validate file type
      const allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
      const fileExtension = file.name.split('.').pop().toLowerCase();
      
      if (!allowedExtensions.includes(fileExtension)) {
        createToast('File type not allowed. Allowed types: ' + allowedExtensions.join(', '), 'error');
        event.target.value = ''; // Clear the input
        return;
      }

      // Display file info
      const fileItem = document.createElement('div');
      fileItem.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200';
      fileItem.innerHTML = `
        <div class="flex items-center space-x-3">
          <div class="w-8 h-8 bg-blue-100 rounded flex items-center justify-center">
            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-900">${file.name}</p>
            <p class="text-xs text-gray-500">${formatFileSize(file.size)}</p>
          </div>
        </div>
        <button type="button" onclick="clearSelectedFile()" class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      `;
      fileList.appendChild(fileItem);
    }
    
    function clearSelectedFile() {
      document.getElementById('materialFileInput').value = '';
      document.getElementById('materialFileList').innerHTML = '';
    }
    
    function formatFileSize(bytes) {
      if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
      } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
      } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
      } else {
        return bytes + ' bytes';
      }
    }

    function submitMaterialUpload(event) {
      event.preventDefault();

      // Get form data
      const form = document.getElementById('materialUploadForm');
      const formData = new FormData();
      
      // Add program ID
      if (!window.currentUploadProgramId) {
        createToast('Program ID not found. Please try again.', 'error');
        return;
      }
      
      formData.append('program_id', window.currentUploadProgramId);
      formData.append('title', form.title.value);
      formData.append('description', form.description.value);
      formData.append('material_type', form.materialType.value);
      
      // Get file input
      const fileInput = document.getElementById('materialFileInput');
      if (!fileInput.files || fileInput.files.length === 0) {
        createToast('Please select a file to upload.', 'error');
        return;
      }
      
      formData.append('file', fileInput.files[0]);

      // Show progress
      const submitButton = event.target.querySelector('button[type="submit"]');
      const originalText = submitButton.textContent;
      submitButton.textContent = 'Uploading...';
      submitButton.disabled = true;

      // Upload file
      fetch('../../api/upload-program-material.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          createToast('Material uploaded successfully!', 'success');
          closeUploadMaterialsModal();
          
          // Optionally refresh the page or update the materials list
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          throw new Error(data.error || 'Upload failed');
        }
      })
      .catch(error => {
        console.error('Upload error:', error);
        createToast('Upload failed: ' + error.message, 'error');
      })
      .finally(() => {
        submitButton.textContent = originalText;
        submitButton.disabled = false;
      });
    }

    function manageGrades(programId) {
      document.getElementById('manageGradesModal').classList.remove('hidden');
      document.getElementById('manageGradesModal').classList.add('flex');
      loadGradeData(programId);
    }

    function manageStudentGrades(studentId, programId) {
      console.log('Managing grades for student:', studentId, 'in program:', programId);
      
      // Store the specific student ID for filtering
      window.currentStudentFilter = studentId;
      
      // Open the grades modal with the program data, but filtered for this student
      document.getElementById('manageGradesModal').classList.remove('hidden');
      document.getElementById('manageGradesModal').classList.add('flex');
      loadGradeData(programId);
    }

    function closeManageGradesModal() {
      document.getElementById('manageGradesModal').classList.add('hidden');
      document.getElementById('manageGradesModal').classList.remove('flex');
      
      // Clear the student filter when closing
      window.currentStudentFilter = null;
    }`;
      console.log('Fetching from:', apiUrl);
      console.log('Current session should be active since page loaded successfully');
      
      // Fetch grades for this program
      fetch(apiUrl, {
        method: 'GET',
        credentials: 'same-origin', // Include cookies/session
        headers: {
          'Content-Type': 'application/json'
        }
      })
        .then(response => {
          console.log('API Response Status:', response.status);
          console.log('API Response Headers:', response.headers);
          
          if (!response.ok) {
            // Try to get error details from response
            return response.text().then(text => {
              console.log('Error response text:', text);
              try {
                const errorData = JSON.parse(text);
                throw new Error(`HTTP ${response.status}: ${errorData.message || response.statusText}`);
              } catch (parseError) {
                throw new Error(`HTTP ${response.status}: ${text || response.statusText}`);
              }
            });
          }
          return response.json();
        })
        .then(data => {
          console.log('Received data:', data);
          if (data.success) {
            updateGradeModal(data);
          } else {
            throw new Error(data.message || 'Failed to load grades');
          }
        })
        .catch(error => {
          console.error('Error loading grades:', error);
          
          // Provide specific error handling
          let errorMessage = error.message;
          if (errorMessage.includes('Authentication required')) {
            errorMessage = 'Your session has expired. Please refresh the page and log in again.';
          } else if (errorMessage.includes('Access denied')) {
            errorMessage = 'You do not have permission to view grades for this program.';
          }
          
          studentList.innerHTML = `
            <div class="p-8 text-center text-red-600">
              <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="font-semibold">Error Loading Grades</p>
              <p class="text-sm mt-1">${errorMessage}</p>
              <button onclick="loadGradeData('${programId}')" class="mt-3 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                Try Again
              </button>
            </div>
          `;
        });
    }

    function updateGradeModal(data) {
      console.log('Updating grade modal with data:', data);
      
      // Filter students if we have a specific student filter
      let studentsToShow = data.students;
      let titleSuffix = `(${data.statistics.total_students} Students Enrolled)`;
      
      if (window.currentStudentFilter) {
        studentsToShow = data.students.filter(s => s.student_id == window.currentStudentFilter);
        if (studentsToShow.length > 0) {
          const student = studentsToShow[0];
          titleSuffix = `- ${student.first_name} ${student.last_name}`;
        }
      }
      
      // Update modal title
      const modalTitle = document.querySelector('#manageGradesModal h3');
      const modalSubtitle = document.querySelector('#manageGradesModal p');
      
      modalTitle.textContent = 'Manage Student Grades';
      modalSubtitle.textContent = `${data.program.name} ${titleSuffix}`;
      
      // Update student list
      const studentList = document.querySelector('#manageGradesModal .space-y-4');
      
      if (studentsToShow.length === 0) {
        const message = window.currentStudentFilter ? 
          'Student not found or not enrolled in this program.' : 
          'No Students Enrolled';
        const description = window.currentStudentFilter ? 
          'The selected student may not be enrolled in this program.' : 
          'This program currently has no enrolled students.';
          
        studentList.innerHTML = `
          <div class="p-8 text-center text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
            </svg>
            <p class="text-lg font-medium mb-2">${message}</p>
            <p class="text-sm">${description}</p>
          </div>
        `;
        return;
      }

      // Add class statistics at the top (hide if showing individual student)
      let studentsHtml = '';
      if (!window.currentStudentFilter) {
        studentsHtml = `
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <h4 class="font-semibold text-blue-900 mb-2">Class Statistics</h4>
            <div class="grid grid-cols-4 gap-4 text-sm">
              <div>
                <span class="text-blue-700 font-medium">Class Average:</span>
                <span class="ml-1 font-bold">${data.statistics.class_average}%</span>
              </div>
              <div>
                <span class="text-green-700 font-medium">Highest:</span>
                <span class="ml-1 font-bold">${data.statistics.highest_grade}%</span>
              </div>
              <div>
                <span class="text-red-700 font-medium">Lowest:</span>
                <span class="ml-1 font-bold">${data.statistics.lowest_grade}%</span>
              </div>
              <div>
                <span class="text-gray-700 font-medium">Total Students:</span>
                <span class="ml-1 font-bold">${data.statistics.total_students}</span>
              </div>
            </div>
          </div>
        `;
      }

      // Generate student headers
      studentsHtml += `
        <div class="grid grid-cols-12 gap-4 px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium text-gray-700 border">
          <div class="col-span-3">Student</div>
          <div class="col-span-2">Assessment Avg</div>
          <div class="col-span-2">Assignment Avg</div>
          <div class="col-span-2">Overall Grade</div>
          <div class="col-span-2">Letter Grade</div>
          <div class="col-span-1">Details</div>
        </div>
      `;
      
      // Generate student rows
      studentsToShow.forEach((student, index) => {
        const initials = student.first_name.charAt(0) + student.last_name.charAt(0);
        const colorClasses = [
          'bg-green-500', 'bg-blue-500', 'bg-purple-500', 'bg-yellow-500', 
          'bg-red-500', 'bg-indigo-500', 'bg-pink-500', 'bg-gray-500'
        ];
        const colorClass = colorClasses[index % colorClasses.length];
        
        // Determine grade color based on overall average
        let gradeBadgeClass = 'bg-gray-100 text-gray-800';
        if (student.overall_average >= 90) {
          gradeBadgeClass = 'bg-green-100 text-green-800';
        } else if (student.overall_average >= 80) {
          gradeBadgeClass = 'bg-blue-100 text-blue-800';
        } else if (student.overall_average >= 70) {
          gradeBadgeClass = 'bg-yellow-100 text-yellow-800';
        } else if (student.overall_average > 0) {
          gradeBadgeClass = 'bg-red-100 text-red-800';
        }
        
        studentsHtml += `
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50 mb-2">
            <div class="col-span-3 flex items-center">
              <div class="w-8 h-8 ${colorClass} rounded-full flex items-center justify-center text-white font-semibold text-sm mr-3">
                ${initials}
              </div>
              <div>
                <p class="font-medium text-gray-900">${student.first_name} ${student.last_name}</p>
                <p class="text-xs text-gray-500">${student.email}</p>
              </div>
            </div>
            <div class="col-span-2">
              <span class="font-medium">${student.assessment_average}%</span>
              <span class="text-xs text-gray-500 block">(${student.assessment_count} assessments)</span>
            </div>
            <div class="col-span-2">
              <span class="font-medium">${student.assignment_average}%</span>
              <span class="text-xs text-gray-500 block">(${student.assignment_count} assignments)</span>
            </div>
            <div class="col-span-2">
              <span class="font-bold text-lg">${student.overall_average}%</span>
            </div>
            <div class="col-span-2">
              <span class="${gradeBadgeClass} px-3 py-1 rounded-full text-sm font-medium">${student.letter_grade}</span>
            </div>
            <div class="col-span-1">
              <button 
                onclick="viewStudentGradeDetails('${student.student_id}')" 
                class="text-blue-600 hover:text-blue-700 p-1"
                title="View detailed grades"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
              </button>
            </div>
          </div>
        `;
      });
      
      studentList.innerHTML = studentsHtml;
      
      // Store current student data for later use
      window.currentStudentGrades = studentsToShow;
      window.currentGradeStats = data.statistics;
    }

    function viewStudentGradeDetails(studentId) {
      console.log('Viewing grade details for student:', studentId);
      
      // Find the student in the current data
      const student = window.currentStudentGrades.find(s => s.student_id == studentId);
      if (!student) {
        showNotification('Student data not found', 'error');
        return;
      }
      
      // Create detailed grade breakdown modal content
      let detailsHtml = `
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70]" id="gradeDetailsModal">
          <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Grade Details: ${student.name}</h3>
                <p class="text-sm text-gray-600">${student.email}</p>
              </div>
              <button onclick="closeGradeDetailsModal()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            
            <div class="p-6">
              <!-- Grade Summary -->
              <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                  <div class="text-2xl font-bold text-blue-600">${student.assessment_average}%</div>
                  <div class="text-sm text-blue-700">Assessment Average</div>
                  <div class="text-xs text-gray-500">${student.assessment_count} completed</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                  <div class="text-2xl font-bold text-green-600">${student.assignment_average}%</div>
                  <div class="text-sm text-green-700">Assignment Average</div>
                  <div class="text-xs text-gray-500">${student.assignment_count} completed</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg text-center">
                  <div class="text-3xl font-bold text-purple-600">${student.overall_average}%</div>
                  <div class="text-sm text-purple-700">Overall Grade</div>
                  <div class="text-lg font-medium text-purple-800">${student.letter_grade}</div>
                </div>
              </div>
              
              <!-- Assessment Details -->
              <div class="mb-6">
                <h4 class="text-lg font-semibold mb-3">Assessments</h4>
                <div class="space-y-2">
      `;
      
      if (student.assessments.length > 0) {
        student.assessments.forEach(assessment => {
          const status = assessment.submitted_at ? 'Completed' : 'Not Submitted';
          const statusClass = assessment.submitted_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600';
          
          detailsHtml += `
            <div class="flex justify-between items-center p-3 border rounded-lg">
              <div>
                <div class="font-medium">${assessment.title}</div>
                <div class="text-sm text-gray-600">Max Points: ${assessment.max_points}</div>
              </div>
              <div class="text-right">
                <div class="font-bold">${assessment.score}/${assessment.max_points} (${assessment.percentage}%)</div>
                <span class="${statusClass} px-2 py-1 rounded text-xs">${status}</span>
              </div>
            </div>
          `;
        });
      } else {
        detailsHtml += `<p class="text-gray-500 italic">No assessments available</p>`;
      }
      
      detailsHtml += `
                </div>
              </div>
              
              <!-- Assignment Details -->
              <div>
                <h4 class="text-lg font-semibold mb-3">Assignments</h4>
                <div class="space-y-2">
      `;
      
      if (student.assignments.length > 0) {
        student.assignments.forEach(assignment => {
          const status = assignment.submitted_at ? 'Completed' : 'Not Submitted';
          const statusClass = assignment.submitted_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600';
          
          detailsHtml += `
            <div class="flex justify-between items-center p-3 border rounded-lg">
              <div>
                <div class="font-medium">${assignment.title}</div>
                <div class="text-sm text-gray-600">Max Points: ${assignment.max_points}</div>
              </div>
              <div class="text-right">
                <div class="font-bold">${assignment.score}/${assignment.max_points} (${assignment.percentage}%)</div>
                <span class="${statusClass} px-2 py-1 rounded text-xs">${status}</span>
              </div>
            </div>
          `;
        });
      } else {
        detailsHtml += `<p class="text-gray-500 italic">No assignments available</p>`;
      }
      
      detailsHtml += `
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
      
      // Add the modal to the document
      document.body.insertAdjacentHTML('beforeend', detailsHtml);
    }
    
    function closeGradeDetailsModal() {
      const modal = document.getElementById('gradeDetailsModal');
      if (modal) {
        modal.remove();
      }
    }

    function updateGrade(studentId, grade) {
      console.log('Updating grade for student:', studentId, 'Grade:', grade);
      const input = document.querySelector(`input[data-student-id="${studentId}"]`);
      if (!input) return;
      
      // Validate grade input
      const numericGrade = parseFloat(grade);
      
      // Reset any previous error states
      input.classList.remove('border-red-500', 'bg-red-50');
      
      // Validate grade range
      if (grade !== '' && (isNaN(numericGrade) || numericGrade < 0 || numericGrade > 100)) {
        input.classList.add('border-red-500', 'bg-red-50');
        createToast('Grade must be between 0 and 100', 'error');
        return;
      }
      
      const studentRow = input.closest('.grid');
      const gradeDisplay = studentRow.querySelector('.grade-display');
      const gradeValue = studentRow.querySelector('.grade-value');
      
      // Update display values
      gradeValue.textContent = grade;
      gradeDisplay.textContent = grade ? `${numericGrade}%` : 'No Grade';
      
      // Update badge color based on grade
      gradeDisplay.className = gradeDisplay.className.replace(/bg-(green|yellow|blue|red|gray)-100 text-(green|yellow|blue|red|gray)-800/g, '');
      
      if (numericGrade >= 90) {
        gradeDisplay.className += ' bg-green-100 text-green-800';
      } else if (numericGrade >= 80) {
        gradeDisplay.className += ' bg-blue-100 text-blue-800';
      } else if (numericGrade >= 70) {
        gradeDisplay.className += ' bg-yellow-100 text-yellow-800';
      } else if (numericGrade > 0) {
        gradeDisplay.className += ' bg-red-100 text-red-800';
      } else {
        gradeDisplay.className += ' bg-gray-100 text-gray-800';
      }
      
      // Mark as modified for batch save
      input.setAttribute('data-modified', 'true');
      
      // Show visual feedback for unsaved changes
      const saveButton = document.querySelector('button[onclick="saveAndCloseGrades()"]');
      if (saveButton && !saveButton.classList.contains('bg-yellow-500')) {
        saveButton.classList.remove('bg-tplearn-green');
        saveButton.classList.add('bg-yellow-500', 'hover:bg-yellow-600');
        saveButton.innerHTML = `
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
          </svg>
          Save Changes
        `;
      }
    }

    function focusGradeInput(studentId) {
      const input = document.querySelector(`input[data-student-id="${studentId}"]`);
      if (input) {
        input.focus();
        input.select();
      }
    }
    }

    function downloadGrades() {
      const programId = window.currentGradeProgramId;
      const students = window.currentStudentGrades;
      const stats = window.currentGradeStats;
      
      if (!students || students.length === 0) {
        showNotification('No grade data available to download.', 'error');
        return;
      }
      
      // Show loading state
      const button = event.target;
      const originalText = button.textContent;
      button.textContent = 'Downloading...';
      button.disabled = true;
      
      try {
        // Get current program name
        const modalSubtitle = document.querySelector('#manageGradesModal p');
        const programName = modalSubtitle ? modalSubtitle.textContent.split('(')[0].trim() : 'Program';
        
        // Generate CSV content
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Add header with program info
        csvContent += `"Grade Report - ${programName}"\n`;
        csvContent += `"Generated on: ${new Date().toLocaleString()}"\n`;
        csvContent += `"Total Students: ${students.length}"\n`;
        
        if (stats) {
          csvContent += `"Class Average: ${stats.average_grade}%"\n`;
          csvContent += `"Highest Grade: ${stats.highest_grade}%"\n`;
          csvContent += `"Lowest Grade: ${stats.lowest_grade}%"\n`;
        }
        
        csvContent += "\n"; // Empty line
        
        // Add column headers
        csvContent += "Student ID,First Name,Last Name,Email,Final Grade,Grade Status,Last Updated\n";
        
        // Add student data
        students.forEach(student => {
          const finalGrade = student.final_grade || 0;
          let gradeStatus = 'No Grade';
          
          if (finalGrade >= 90) {
            gradeStatus = 'Excellent (A)';
          } else if (finalGrade >= 80) {
            gradeStatus = 'Good (B)';
          } else if (finalGrade >= 70) {
            gradeStatus = 'Satisfactory (C)';
          } else if (finalGrade > 0) {
            gradeStatus = 'Needs Improvement';
          }
          
          // Get last updated date from grades
          let lastUpdated = 'Never';
          if (student.grades && student.grades.length > 0) {
            const finalGradeRecord = student.grades.find(g => g.grade_type === 'final');
            if (finalGradeRecord && finalGradeRecord.updated_at) {
              lastUpdated = new Date(finalGradeRecord.updated_at).toLocaleString();
            }
          }
          
          csvContent += `"${student.user_id_string}","${student.first_name}","${student.last_name}","${student.email}","${finalGrade}%","${gradeStatus}","${lastUpdated}"\n`;
        });
        
        // Add statistics summary at the end
        if (stats) {
          csvContent += "\n"; // Empty line
          csvContent += "Grade Distribution:\n";
          csvContent += `"A Grades (90-100%): ${stats.a_grades} students"\n`;
          csvContent += `"B Grades (80-89%): ${stats.b_grades} students"\n`;
          csvContent += `"C Grades (70-79%): ${stats.c_grades} students"\n`;
          csvContent += `"Failing Grades (<70%): ${stats.failing_grades} students"\n`;
        }
        
        // Create and trigger download
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        
        // Generate filename with timestamp
        const timestamp = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
        const filename = `grades_${programName.replace(/[^a-zA-Z0-9]/g, '_')}_${timestamp}.csv`;
        link.setAttribute("download", filename);
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Show success message
        createToast('Grade report downloaded successfully!', 'success');
        
      } catch (error) {
        console.error('Error downloading grades:', error);
        createToast('Failed to download grades. Please try again.', 'error');
      } finally {
        // Restore button
        setTimeout(() => {
          button.textContent = originalText;
          button.disabled = false;
        }, 1000);
      }
    }

    function printGrades() {
      const programId = window.currentGradeProgramId;
      const students = window.currentStudentGrades;
      const stats = window.currentGradeStats;
      
      if (!students || students.length === 0) {
        showNotification('No grade data available to print.', 'error');
        return;
      }
      
      // Show loading state
      const button = event.target;
      const originalText = button.textContent;
      button.textContent = 'Preparing...';
      button.disabled = true;
      
      try {
        // Get current program name
        const modalSubtitle = document.querySelector('#manageGradesModal p');
        const programName = modalSubtitle ? modalSubtitle.textContent.split('(')[0].trim() : 'Program';
        
        // Create printable version
        createPrintableGradeReport(programName, students, stats);
        
        // Show success message
        createToast('Print dialog opened!', 'success');
        
      } catch (error) {
        console.error('Error preparing print:', error);
        createToast('Failed to prepare print. Please try again.', 'error');
      } finally {
        // Restore button
        setTimeout(() => {
          button.textContent = originalText;
          button.disabled = false;
        }, 1000);
      }
    }</title>
          <style>
            body {
              font-family: Arial, sans-serif;
              margin: 20px;
              line-height: 1.4;
            }
            .header {
              text-align: center;
              margin-bottom: 30px;
              border-bottom: 2px solid #333;
              padding-bottom: 20px;
            }
            .header h1 {
              margin: 0;
              color: #2d7748;
              font-size: 24px;
            }
            .header h2 {
              margin: 5px 0;
              color: #666;
              font-size: 18px;
              font-weight: normal;
            }
            .info-section {
              display: flex;
              justify-content: space-between;
              margin-bottom: 20px;
              background-color: #f8f9fa;
              padding: 15px;
              border-radius: 5px;
            }
            .info-left, .info-right {
              flex: 1;
            }
            .stats-grid {
              display: grid;
              grid-template-columns: repeat(2, 1fr);
              gap: 10px;
              margin-bottom: 30px;
            }
            .stat-item {
              background-color: #e9ecef;
              padding: 10px;
              border-radius: 5px;
              text-align: center;
            }
            .stat-value {
              font-size: 18px;
              font-weight: bold;
              color: #2d7748;
            }
            .stat-label {
              font-size: 12px;
              color: #666;
              margin-top: 5px;
            }
            table {
              width: 100%;
              border-collapse: collapse;
              margin-bottom: 20px;
              font-size: 12px;
            }
            th, td {
              border: 1px solid #ddd;
              padding: 8px;
              text-align: left;
            }
            th {
              background-color: #2d7748;
              color: white;
              font-weight: bold;
            }
            .grade-excellent { background-color: #d4edda; }
            .grade-good { background-color: #d1ecf1; }
            .grade-satisfactory { background-color: #fff3cd; }
            .grade-needs-improvement { background-color: #f8d7da; }
            .grade-no-grade { background-color: #f8f9fa; }
            .footer {
              margin-top: 30px;
              text-align: center;
              font-size: 10px;
              color: #666;
              border-top: 1px solid #ddd;
              padding-top: 10px;
            }
            @media print {
              body { margin: 0; }
              .header { page-break-after: avoid; }
              table { page-break-inside: auto; }
              tr { page-break-inside: avoid; page-break-after: auto; }
            }
          </style>
        </head>
        <body>
          <div class="header">
            <h1>TPLearn Grade Report</h1>
            <h2>${programName}</h2>
            <p>Generated on: ${new Date().toLocaleString()}</p>
          </div>
          
          <div class="info-section">
            <div class="info-left">
              <strong>Total Students:</strong> ${students.length}<br>
              <strong>Report Date:</strong> ${new Date().toLocaleDateString()}
            </div>
            <div class="info-right">
              ${stats ? `
                <strong>Class Average:</strong> ${stats.average_grade}%<br>
                <strong>Grade Range:</strong> ${stats.lowest_grade}% - ${stats.highest_grade}%
              ` : ''}
            </div>
          </div>
          
          ${stats ? `
            <div class="stats-grid">
              <div class="stat-item">
                <div class="stat-value">${stats.a_grades || 0}</div>
                <div class="stat-label">A Grades (90-100%)</div>
              </div>
              <div class="stat-item">
                <div class="stat-value">${stats.b_grades || 0}</div>
                <div class="stat-label">B Grades (80-89%)</div>
              </div>
              <div class="stat-item">
                <div class="stat-value">${stats.c_grades || 0}</div>
                <div class="stat-label">C Grades (70-79%)</div>
              </div>
              <div class="stat-item">
                <div class="stat-value">${stats.failing_grades || 0}</div>
                <div class="stat-label">Failing Grades (<70%)</div>
              </div>
            </div>
          ` : ''}
          
          <table>
            <thead>
              <tr>
                <th>Student ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Grade</th>
                <th>Status</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
              ${students.map(student => {
                const finalGrade = student.final_grade || 0;
                let gradeStatus = 'No Grade';
                let rowClass = 'grade-no-grade';
                
                if (finalGrade >= 90) {
                  gradeStatus = 'Excellent (A)';
                  rowClass = 'grade-excellent';
                } else if (finalGrade >= 80) {
                  gradeStatus = 'Good (B)';
                  rowClass = 'grade-good';
                } else if (finalGrade >= 70) {
                  gradeStatus = 'Satisfactory (C)';
                  rowClass = 'grade-satisfactory';
                } else if (finalGrade > 0) {
                  gradeStatus = 'Needs Improvement';
                  rowClass = 'grade-needs-improvement';
                }
                
                let lastUpdated = 'Never';
                if (student.grades && student.grades.length > 0) {
                  const finalGradeRecord = student.grades.find(g => g.grade_type === 'final');
                  if (finalGradeRecord && finalGradeRecord.updated_at) {
                    lastUpdated = new Date(finalGradeRecord.updated_at).toLocaleDateString();
                  }
                }
                
                return `
                  <tr class="${rowClass}">
                    <td>${student.user_id_string}</td>
                    <td>${student.first_name}</td>
                    <td>${student.last_name}</td>
                    <td>${student.email}</td>
                    <td>${finalGrade}%</td>
                    <td>${gradeStatus}</td>
                    <td>${lastUpdated}</td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
          
          <div class="footer">
            <p>This report was generated by TPLearn - Trez at Psara's Academic and Tutorial Services</p>
            <p>For questions about this report, please contact your program administrator.</p>
          </div>
        </body>
        </html>
      `;
      
      printWindow.document.write(printContent);
      printWindow.document.close();
      
      // Wait for content to load, then print
      printWindow.onload = function() {
        setTimeout(() => {
          printWindow.print();
        }, 500);
      };
    }

    function saveAndCloseGrades() {
      const programId = window.currentGradeProgramId;
      if (!programId) {
        createToast('Program ID not found. Please reload the page.', 'error');
        return;
      }
      
      // Validate all grades first
      if (!validateAllGrades()) {
        createToast('Please fix invalid grades before saving.', 'error');
        return;
      }
      
      // Get all modified grade inputs
      const modifiedInputs = document.querySelectorAll('input[data-modified="true"]');
      
      if (modifiedInputs.length === 0) {
        // No changes to save, just close
        closeManageGradesModal();
        return;
      }
      
      // Show loading state
      const saveButton = document.querySelector('button[onclick="saveAndCloseGrades()"]');
      const originalText = saveButton.textContent;
      saveButton.textContent = 'Saving...';
      saveButton.disabled = true;
      
      // Collect all grade updates
      const gradeUpdates = [];
      modifiedInputs.forEach(input => {
        const studentId = input.getAttribute('data-student-id');
        const grade = parseFloat(input.value) || 0;
        
        if (grade >= 0 && grade <= 100) {
          gradeUpdates.push({
            student_user_id: studentId,
            grade: grade
          });
        }
      });
      
      if (gradeUpdates.length === 0) {
        saveButton.textContent = originalText;
        saveButton.disabled = false;
        createToast('No valid grades to save.', 'warning');
        return;
      }
      
      // Get grading notes
      const notesTextarea = document.querySelector('#manageGradesModal textarea');
      const comments = notesTextarea ? notesTextarea.value.trim() : '';
      
      // Process each grade update
      let completedUpdates = 0;
      let failedUpdates = 0;
      const totalUpdates = gradeUpdates.length;
      
      gradeUpdates.forEach(update => {
        const requestData = {
          program_id: parseInt(programId),
          student_user_id: update.student_user_id,
          grade: update.grade,
          grade_type: 'final',
          comments: comments
        };
        
        console.log('Sending grade update:', requestData);
        
        fetch('../../api/update-student-grade.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            completedUpdates++;
          } else {
            failedUpdates++;
            console.error('Failed to update grade:', data.message);
          }
          
          // Check if all updates are complete
          if (completedUpdates + failedUpdates === totalUpdates) {
            handleSaveCompletion(completedUpdates, failedUpdates, saveButton, originalText);
          }
        })
        .catch(error => {
          handleApiError(error, 'grade update');
          failedUpdates++;
          
          // Check if all updates are complete
          if (completedUpdates + failedUpdates === totalUpdates) {
            handleSaveCompletion(completedUpdates, failedUpdates, saveButton, originalText);
          }
        });
      });
    }grade${completed > 1 ? 's' : ''}!`, 'success');
        
        // Clear modification flags
        document.querySelectorAll('input[data-modified="true"]').forEach(input => {
          input.removeAttribute('data-modified');
        });
        
        // Reload grade data to show updated grades instead of closing modal
        const programId = window.currentGradeProgramId;
        if (programId) {
          setTimeout(() => {
            loadGradeData(programId);
            createToast('Grades refreshed successfully!', 'info');
          }, 1000);
        }
      } else {
        // Some updates failed
        const message = `Saved ${completed} grade${completed > 1 ? 's' : ''}, ${failed} failed. Please try again for failed grades.`;
        createToast(message, 'warning');
      }
    }function exportGradebook() {
      showNotification('Exporting gradebook...', 'info');
    }

    function generateReports() {
      showNotification('Generating grade reports...', 'info');
    }

    function sendGradeNotifications() {
      showNotification('Sending grade notifications to students...', 'info');
    }

    function addNewAssignment() {
      const assignmentName = prompt('Enter assignment name:');
      if (assignmentName) {
        showNotification(`Assignment "${assignmentName}" added successfully!`, 'success');
        // Add new assignment column to table
      }
    }

    function viewStudentDetails(studentId) {
      showNotification(`Viewing detailed grades for ${studentId}`, 'info');
    }

    // Material type selection
    document.addEventListener('DOMContentLoaded', function() {
      const materialTypeCards = document.querySelectorAll('.material-type-card');

      materialTypeCards.forEach(card => {
        card.addEventListener('click', function() {
          // Remove selection from all cards
          materialTypeCards.forEach(c => {
            c.querySelector('div').classList.remove('border-blue-500', 'border-purple-500', 'border-green-500');
            c.querySelector('div').classList.add('border-gray-200');
          });

          // Add selection to clicked card
          const radio = this.querySelector('input[type="radio"]');
          radio.checked = true;

          const cardDiv = this.querySelector('div');
          cardDiv.classList.remove('border-gray-200');

          if (radio.value === 'lesson') {
            cardDiv.classList.add('border-blue-500');
          } else if (radio.value === 'assignment') {
            cardDiv.classList.add('border-purple-500');
          } else if (radio.value === 'resource') {
            cardDiv.classList.add('border-green-500');
          }
        });
      });
    });,
        body: JSON.stringify({ 
          student_id: studentId, 
          program_id: programId 
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          populateStudentDetailsModal(data.student);
          openStudentDetailsModal();
        } else {
          alert('Error loading student details: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        TPAlert.error('Error', 'Error connecting to server');
      });
    }

    function contactStudent(studentId) {
      console.log('Contacting student:', studentId);
      
      // Store current student ID
      currentStudentId = studentId;
      
      // Update modal with student info
      const studentRow = document.querySelector(`tr[data-student="${studentId}"]`);
      if (studentRow) {
        const studentName = studentRow.querySelector('.text-sm.font-medium.text-gray-900').textContent;
        document.getElementById('contactStudentSubtitle').textContent = `Send a message to ${studentName}`;
      }
      
      // Reset form
      document.getElementById('contactStudentForm').reset();
      document.getElementById('messageType').value = 'general';
      document.getElementById('messageContent').value = '';
      
      openStudentContactModal();
    }"]`)?.dataset.program;
      if (programName && programFilter) {
        programFilter.value = programName;
        filterStudents();
      }
    }

    // Student Details Modal Functions
    function openStudentDetailsModal() {
      document.getElementById('studentDetailsModal').classList.remove('hidden');
      document.getElementById('studentDetailsModal').classList.add('flex');
      // Initialize with overview tab
      switchStudentTab('overview');
    }

    function closeStudentDetailsModal() {
      document.getElementById('studentDetailsModal').classList.add('hidden');
      document.getElementById('studentDetailsModal').classList.remove('flex');
    }</div>
              <div class="text-xs text-gray-500">${new Date(activity.date).toLocaleDateString()}</div>
            </div>
            <span class="text-xs px-2 py-1 rounded ${activity.type === 'positive' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}">${activity.type}</span>
          `;
          activityContainer.appendChild(activityDiv);
        });
      } else {
        activityContainer.innerHTML = '<div class="text-center text-gray-500 py-4">No recent activity</div>';
      }
    });

      // Show selected content and highlight tab
      document.getElementById(tabName + '-content').classList.remove('hidden');
      document.getElementById(tabName + '-tab').classList.remove('border-transparent', 'text-gray-500');
      document.getElementById(tabName + '-tab').classList.add('border-tplearn-green', 'text-tplearn-green');
    }

    // Student Contact Modal Functions
    let currentStudentId = null;

    function openStudentContactModal() {
      document.getElementById('studentContactModal').classList.remove('hidden');
      document.getElementById('studentContactModal').classList.add('flex');
    }

    function closeStudentContactModal() {
      document.getElementById('studentContactModal').classList.add('hidden');
      document.getElementById('studentContactModal').classList.remove('flex');
      currentStudentId = null;
    }

    function contactStudentFromModal() {
      if (currentStudentId) {
        closeStudentDetailsModal();
        contactStudent(currentStudentId);
      }
    }

    function useTemplate(templateType) {
      const subjectField = document.getElementById('messageSubject');
      const contentField = document.getElementById('messageContent');
      const typeField = document.getElementById('messageType');
      
      switch(templateType) {
        case 'performance':
          typeField.value = 'performance';
          subjectField.value = 'Performance Update';
          contentField.value = 'Dear Student,\n\nI wanted to take a moment to discuss your recent performance in our program. Your dedication and hard work have been impressive, and I\'d like to provide some feedback to help you continue improving.\n\n[Add specific feedback here]\n\nKeep up the great work!\n\nBest regards,\nYour Tutor';
          break;
        case 'absence':
          typeField.value = 'attendance';
          subjectField.value = 'Missed Session Follow-up';
          contentField.value = 'Dear Student,\n\nI noticed you missed our recent session. I wanted to check in and see if everything is okay, and to let you know what we covered.\n\n[Session summary]\n\nPlease let me know if you need any clarification or if there\'s anything I can help you with to catch up.\n\nBest regards,\nYour Tutor';
          break;
        case 'assignment':
          typeField.value = 'assignment';
          subjectField.value = 'Assignment Reminder';
          contentField.value = 'Dear Student,\n\nThis is a friendly reminder about the upcoming assignment deadline:\n\n[Assignment details]\nDue Date: [Date]\n\nIf you have any questions or need assistance, please don\'t hesitate to reach out.\n\nBest regards,\nYour Tutor';
          break;
        case 'congratulations':
          typeField.value = 'congratulations';
          subjectField.value = 'Congratulations!';
          contentField.value = 'Dear Student,\n\nCongratulations on your excellent work! Your recent [achievement/assignment/test] showed great improvement and dedication.\n\n[Specific praise]\n\nKeep up the fantastic work!\n\nBest regards,\nYour Tutor';
          break;
      }
    }

    // Handle contact form submission
    document.getElementById('contactStudentForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = {
        student_id: currentStudentId,
        message_type: document.getElementById('messageType').value,
        subject: document.getElementById('messageSubject').value,
        content: document.getElementById('messageContent').value,
        send_email: document.getElementById('sendEmail').checked,
        save_to_history: document.getElementById('saveToHistory').checked
      };
      
      // Send message
      fetch('../../api/send-student-message.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          TPAlert.success('Success', 'Message sent successfully!');
          closeStudentContactModal();
        } else {
          alert('Error sending message: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        TPAlert.error('Error', 'Error connecting to server');
      });
    });

    // Student Search and Filter Functions
    function filterStudents() {
      const searchTerm = document.getElementById('studentSearch').value.toLowerCase();
      const programFilter = document.getElementById('programFilter').value;
      const statusFilter = document.getElementById('statusFilter').value;
      
      const rows = document.querySelectorAll('.student-row');
      let visibleCount = 0;
      
      rows.forEach(row => {
        const studentName = row.dataset.studentName;
        const studentEmail = row.dataset.studentEmail;
        const program = row.dataset.program;
        const status = row.dataset.status;
        
        let shouldShow = true;
        
        // Search filter
        if (searchTerm && !studentName.includes(searchTerm) && !studentEmail.includes(searchTerm)) {
          shouldShow = false;
        }
        
        // Program filter
        if (programFilter && program !== programFilter) {
          shouldShow = false;
        }
        
        // Status filter
        if (statusFilter && status !== statusFilter) {
          shouldShow = false;
        }
        
        if (shouldShow) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      // Update count display
      document.getElementById('filteredNumber').textContent = visibleCount;
      if (visibleCount < rows.length) {
        document.getElementById('filteredCount').classList.remove('hidden');
      } else {
        document.getElementById('filteredCount').classList.add('hidden');
      }
      
      updatePagination();
    }

    function clearAllFilters() {
      document.getElementById('studentSearch').value = '';
      document.getElementById('programFilter').value = '';
      document.getElementById('statusFilter').value = '';
      
      filterStudents();
    }

    function exportStudentList() {
      const visibleRows = document.querySelectorAll('.student-row:not([style*="display: none"])');
      let csvContent = "Student Name,Email,Program,Status,Attendance Rate,Enrollment Date\n";
      
      visibleRows.forEach(row => {
        const name = row.querySelector('.text-sm.font-medium.text-gray-900').textContent;
        const email = row.querySelector('.text-sm.text-gray-500').textContent;
        const program = row.dataset.program;
        const status = row.dataset.status;
        const attendance = row.dataset.attendance + '%';
        const enrolled = new Date(parseInt(row.dataset.enrolled) * 1000).toLocaleDateString();
        
        csvContent += `"${name}","${email}","${program}","${status}","${attendance}","${enrolled}"\n`;
      });
      
      // Create and download file
      const blob = new Blob([csvContent], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'students_list_' + new Date().toISOString().split('T')[0] + '.csv';
      link.click();
      window.URL.revokeObjectURL(url);
    }

    // Pagination Functions
    let currentPage = 1;
    const studentsPerPage = 10;

    function updatePagination() {
      const visibleRows = document.querySelectorAll('.student-row:not([style*="display: none"])');
      const totalStudents = visibleRows.length;
      const totalPages = Math.ceil(totalStudents / studentsPerPage);
      
      // Hide all rows first
      visibleRows.forEach(row => row.style.display = 'none');
      
      // Show only current page rows
      const startIndex = (currentPage - 1) * studentsPerPage;
      const endIndex = Math.min(startIndex + studentsPerPage, totalStudents);
      
      for (let i = startIndex; i < endIndex; i++) {
        if (visibleRows[i]) {
          visibleRows[i].style.display = '';
        }
      }
      
      // Update pagination info
      document.getElementById('studentsShowing').textContent = totalStudents > 0 ? startIndex + 1 : 0;
      document.getElementById('studentsTo').textContent = endIndex;
      document.getElementById('studentsTotal').textContent = totalStudents;
      
      // Update pagination controls
      document.getElementById('prevBtn').disabled = currentPage === 1;
      document.getElementById('nextBtn').disabled = currentPage === totalPages || totalPages === 0;
      
      // Generate page numbers
      generatePageNumbers(totalPages);
    }

    function generatePageNumbers(totalPages) {
      const pageNumbers = document.getElementById('pageNumbers');
      pageNumbers.innerHTML = '';
      
      for (let i = 1; i <= Math.min(totalPages, 5); i++) {
        const pageBtn = document.createElement('button');
        pageBtn.textContent = i;
        pageBtn.className = `px-3 py-1 text-sm rounded ${i === currentPage ? 'bg-tplearn-green text-white' : 'text-gray-500 hover:text-gray-700'}`;
        pageBtn.onclick = () => goToPage(i);
        pageNumbers.appendChild(pageBtn);
      }
    }

    function previousPage() {
      if (currentPage > 1) {
        currentPage--;
        updatePagination();
      }
    }

    function nextPage() {
      const visibleRows = document.querySelectorAll('.student-row:not([style*="display: none"])');
      const totalPages = Math.ceil(visibleRows.length / studentsPerPage);
      if (currentPage < totalPages) {
        currentPage++;
        updatePagination();
      }
    }

    function goToPage(page) {
      currentPage = page;
      updatePagination();
    }

    // Additional Functions"]`);
      if (studentRow) {
        const programName = studentRow.querySelector('td:nth-child(2)').textContent.trim();
        // Find program ID by name (this could be improved)
        console.log('Opening attendance for student:', studentId, 'in program:', programName);
        
        // For now, open attendance modal for the first program
        // This should be improved to get the actual program ID
        markAttendance(1); // Defaulting to program 1, should be dynamic
      }
    }

    function viewStudentProgress(studentId) {
      TPAlert.info('Information', `Viewing progress for student ID: ${studentId || currentStudentId}`);
      // This would show detailed progress charts
    }

    function printStudentReport() {
      if (currentStudentId) {
        window.print();
      }
    }

    // Calendar Management Functions
    let currentCalendarView = 'month';
    let currentCalendarDate = new Date();
    let calendarData = {};
    let currentSessionId = null;

    // Debug functions to test calendar rendering
    window.debugCalendar = function() {
      console.log('Debug calendar called');
      console.log('currentCalendarDate:', currentCalendarDate);
      console.log('currentCalendarView:', currentCalendarView);
      console.log('calendarData:', calendarData);
      
      // Force render calendar with empty data
      calendarData = { monthly_sessions: {}, upcoming_sessions: [], programs: [] };
      renderCalendar();
      console.log('Calendar rendered');
    };

    // Debug function to test month view specifically
    window.debugMonthView = function() {
      console.log('Debugging month view');
      currentCalendarView = 'month';
      currentCalendarDate = new Date();
      calendarData = { monthly_sessions: {}, upcoming_sessions: [], programs: [] };
      renderMonthView();
      console.log('Month view rendered');
    };

    // Debug function to test attendance modal
    window.debugAttendance = function() {
      console.log('Testing attendance modal');
      markAttendance(1); // Test with program ID 1
    };

    // Force render calendar grid - simple version that always works
    function forceRenderCalendarGrid() {
      console.log('Force rendering calendar grid');
      const grid = document.getElementById('monthCalendarGrid');
      if (!grid) {
        console.error('Calendar grid not found');
        return;
      }
      
      grid.innerHTML = '';
      const today = new Date();
      const year = today.getFullYear();
      const month = today.getMonth();
      
      // First day of month
      const firstDay = new Date(year, month, 1);
      // Start from Sunday before first day
      const startDate = new Date(firstDay);
      startDate.setDate(firstDay.getDate() - firstDay.getDay());
      
      // Generate 42 days (6 weeks)
      for (let i = 0; i < 42; i++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + i);
        
        const dayCell = document.createElement('div');
        dayCell.className = 'min-h-[80px] p-1 border-0 bg-white hover:bg-gray-50 cursor-pointer';
        
        // Add day number
        const dayNumber = document.createElement('div');
        dayNumber.className = 'text-xs font-medium mb-1 text-gray-700';
        dayNumber.textContent = date.getDate();
        
        // Highlight today
        if (date.toDateString() === today.toDateString()) {
          dayNumber.className += ' text-blue-600 font-bold';
          dayCell.className += ' ring-2 ring-blue-500 ring-inset';
        }
        
        // Dim previous/next month days
        if (date.getMonth() !== month) {
          dayNumber.className = 'text-xs font-medium mb-1 text-gray-400';
          dayCell.className = dayCell.className.replace('bg-white', 'bg-gray-50');
        }
        
        dayCell.appendChild(dayNumber);
        grid.appendChild(dayCell);
      }
      
      console.log('Calendar grid rendered with', grid.children.length, 'cells');
    }

    // Calendar initialization and data loading
    function initializeCalendar() {
      console.log('Initializing calendar...');
      const today = new Date();
      currentCalendarDate = new Date(today.getFullYear(), today.getMonth(), 1);
      
      // First render empty calendar
      renderMonthView();
      updateCalendarDisplay();
      
      // Then load data
      loadCalendarData();
    }

    function loadCalendarData() {
      const year = currentCalendarDate.getFullYear();
      const month = currentCalendarDate.getMonth() + 1;
      
      console.log('Loading calendar data for:', year, month);
      
      // Make API call to get real session data (using test endpoint temporarily)
      fetch('../../api/get-calendar-data-test.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin', // Include session cookies
        body: JSON.stringify({ year: year, month: month })
      })
      .then(response => {
        console.log('API Response status:', response.status);
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Calendar API response:', data);
        if (data.success) {
          calendarData = data.data;
          
          // Process sessions and organize by date for calendar display
          if (calendarData.monthly_sessions) {
            calendarData.sessions_by_date = {};
            
            // The monthly_sessions comes as an object with day keys
            Object.keys(calendarData.monthly_sessions).forEach(dayKey => {
              const sessions = calendarData.monthly_sessions[dayKey];
              
              sessions.forEach(session => {
                // Extract date from session_date
                const sessionDate = session.session_date ? session.session_date.split(' ')[0] : null;
                
                if (sessionDate) {
                  if (!calendarData.sessions_by_date[sessionDate]) {
                    calendarData.sessions_by_date[sessionDate] = [];
                  }
                  calendarData.sessions_by_date[sessionDate].push(session);
                }
              });
            });
            
            console.log('Processed sessions by date:', calendarData.sessions_by_date);
          }
          
          renderCalendar();
          populateUpcomingSessions();
          populateProgramFilter();
          updateWeeklyStats();
        } else {
          console.error('Error loading calendar data:', data.message);
          showCalendarError('Failed to load calendar data: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error loading calendar data:', error);
        showCalendarError('Unable to connect to calendar service. Please refresh the page.');
      });
    }
    
    function showCalendarError(message) {
      const calendarGrid = document.getElementById('calendar-grid');
      if (calendarGrid) {
        calendarGrid.innerHTML = `<div class="col-span-7 text-center py-8 text-red-500 font-medium">${message}</div>`;
      }
    }

    function setCalendarView(view) {
      currentCalendarView = view;
      
      // Update button states
      document.querySelectorAll('#monthViewBtn, #weekViewBtn, #dayViewBtn').forEach(btn => {
        btn.classList.remove('bg-tplearn-green', 'text-white');
        btn.classList.add('text-gray-600', 'hover:bg-gray-100');
      });
      
      document.getElementById(view + 'ViewBtn').classList.remove('text-gray-600', 'hover:bg-gray-100');
      document.getElementById(view + 'ViewBtn').classList.add('bg-tplearn-green', 'text-white');
      
      // Show/hide calendar views
      document.querySelectorAll('.calendar-view').forEach(view => view.classList.add('hidden'));
      document.getElementById(view + 'View').classList.remove('hidden');
      
      updateCalendarDisplay();
      renderCalendar();
    }

    function updateCalendarDisplay() {
      const periodDisplay = document.getElementById('calendarPeriodDisplay');
      const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
      
      switch (currentCalendarView) {
        case 'month':
          periodDisplay.textContent = monthNames[currentCalendarDate.getMonth()] + ' ' + currentCalendarDate.getFullYear();
          break;
        case 'week':
          const weekStart = getWeekStart(currentCalendarDate);
          const weekEnd = new Date(weekStart);
          weekEnd.setDate(weekEnd.getDate() + 6);
          periodDisplay.textContent = formatDateRange(weekStart, weekEnd);
          break;
        case 'day':
          periodDisplay.textContent = currentCalendarDate.toLocaleDateString('en-US', { 
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
          });
          break;
      }
    }

    function renderCalendar() {
      switch (currentCalendarView) {
        case 'month':
          renderMonthView();
          break;
        case 'week':
          renderWeekView();
          break;
        case 'day':
          renderDayView();
          break;
      }
    }

    function renderMonthView() {
      const grid = document.getElementById('monthCalendarGrid');
      if (!grid) {
        console.error('Calendar grid element not found');
        return;
      }
      
      grid.innerHTML = '';
      
      const year = currentCalendarDate.getFullYear();
      const month = currentCalendarDate.getMonth();
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const startDate = new Date(firstDay);
      startDate.setDate(startDate.getDate() - firstDay.getDay());
      
      console.log('Rendering month view for:', year, month + 1);
      
      for (let i = 0; i < 42; i++) {
        const cellDate = new Date(startDate);
        cellDate.setDate(startDate.getDate() + i);
        
        const dayCell = createDayCell(cellDate, month);
        grid.appendChild(dayCell);
      }
      
      console.log('Calendar grid populated with', grid.children.length, 'cells');
    }

    function createDayCell(date, currentMonth) {
      const cell = document.createElement('div');
      const day = date.getDate();
      const isCurrentMonth = date.getMonth() === currentMonth;
      const isToday = isDateToday(date);
      
      // Create proper date key (YYYY-MM-DD format)
      const dateKey = date.toISOString().split('T')[0];
      
      cell.className = `min-h-[80px] p-1 border-0 bg-white cursor-pointer hover:bg-gray-50 transition-colors ${
        isCurrentMonth ? 'text-gray-700' : 'text-gray-400 bg-gray-50'
      } ${isToday ? 'ring-2 ring-blue-500 ring-inset' : ''}`;
      
      cell.onclick = () => selectCalendarDate(date);
      
      // Day number - smaller and positioned better
      const dayNumber = document.createElement('div');
      dayNumber.className = `text-xs font-medium mb-1 ${
        isCurrentMonth ? 'text-gray-700' : 'text-gray-400'
      } ${isToday ? 'text-blue-600 font-bold' : ''}`;
      dayNumber.textContent = day;
      cell.appendChild(dayNumber);
      
      // Sessions for this day - check both old format and new format
      let sessionsForDay = [];
      
      console.log(`Checking sessions for ${dateKey} (day ${day})`);
      
      // Check new format (by date key)
      if (calendarData && calendarData.sessions_by_date && calendarData.sessions_by_date[dateKey]) {
        sessionsForDay = calendarData.sessions_by_date[dateKey];
        console.log(`Found ${sessionsForDay.length} sessions in sessions_by_date for ${dateKey}`);
      }
      // Check old format (by day number) for backward compatibility
      else if (calendarData && calendarData.monthly_sessions && calendarData.monthly_sessions[day.toString()]) {
        sessionsForDay = calendarData.monthly_sessions[day.toString()];
        console.log(`Found ${sessionsForDay.length} sessions in monthly_sessions for day ${day}`);
      } else {
        console.log(`No sessions found for ${dateKey} (day ${day})`);
      }
      
      // Add sessions to the calendar cell
      sessionsForDay.forEach((session, index) => {
        if (index < 3) { // Limit to 3 sessions per cell for clean display
          console.log(`Adding session ${index + 1}:`, session.program_name, session.session_time_formatted);
          const sessionElement = createSessionElement(session);
          cell.appendChild(sessionElement);
        }
      });
      
      // Show "more" indicator if there are more than 3 sessions
      if (sessionsForDay.length > 3) {
        const moreElement = document.createElement('div');
        moreElement.className = 'text-xs text-gray-500 text-center mt-1';
        moreElement.textContent = `+${sessionsForDay.length - 3} more`;
        cell.appendChild(moreElement);
      }
      
      return cell;
    }

    function createSessionElement(session) {
      const element = document.createElement('div');
      element.className = 'text-xs p-1 mb-1 rounded cursor-pointer hover:shadow-sm transition-all';
      
      // Color code sessions by program - using brighter, more distinct colors
      let backgroundColor = '#3b82f6'; // Default blue
      let textColor = 'white';
      
      if (session.program_name) {
        if (session.program_name.includes('Sample 1')) {
          backgroundColor = '#3b82f6'; // Blue
        } else if (session.program_name.includes('Sample 2')) {
          backgroundColor = '#8b5cf6'; // Purple  
        } else if (session.program_name.includes('Sample 3')) {
          backgroundColor = '#10b981'; // Green
        } else if (session.program_name.includes('Math')) {
          backgroundColor = '#3b82f6'; // Blue for Math
        } else if (session.program_name.includes('English')) {
          backgroundColor = '#10b981'; // Green for English
        } else if (session.program_name.includes('Science')) {
          backgroundColor = '#8b5cf6'; // Purple for Science
        }
      }
      
      element.style.backgroundColor = backgroundColor;
      element.style.color = textColor;
      element.title = `${session.program_name} - ${session.session_time_formatted || session.start_time}`;
      
      // Format time display - more compact
      let timeText = '';
      if (session.session_time_formatted) {
        timeText = session.session_time_formatted;
      } else if (session.start_time) {
        timeText = new Date('2000-01-01 ' + session.start_time).toLocaleTimeString('en-US', {
          hour: 'numeric',
          minute: '2-digit',
          hour12: true
        });
      }
      
      // Compact layout similar to the academic schedule
      element.innerHTML = `
        <div class="font-medium text-xs leading-tight">${session.program_name || 'Session'}</div>
        <div class="text-xs opacity-90 leading-tight">${timeText}</div>
      `;
      
      element.onclick = (e) => {
        e.stopPropagation();
        viewSessionDetails(session.id, session);
      };
      
      return element;
    }

    function renderWeekView() {
      const weekGrid = document.getElementById('weekTimeGrid');
      const weekHeaders = document.getElementById('weekHeaders');
      
      // Clear existing content
      weekGrid.innerHTML = '';
      weekHeaders.innerHTML = '';
      
      // Generate week headers
      const weekStart = getWeekStart(currentCalendarDate);
      for (let i = 0; i < 7; i++) {
        const dayDate = new Date(weekStart);
        dayDate.setDate(weekStart.getDate() + i);
        
        const header = document.createElement('div');
        header.className = 'text-center py-2 text-sm font-medium text-gray-700';
        header.innerHTML = `
          <div>${dayDate.toLocaleDateString('en-US', { weekday: 'short' })}</div>
          <div class="text-lg ${isDateToday(dayDate) ? 'text-tplearn-green font-bold' : ''}">${dayDate.getDate()}</div>
        `;
        weekHeaders.appendChild(header);
      }
      
      // Generate time slots (7 AM to 9 PM)
      for (let hour = 7; hour <= 21; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'grid grid-cols-8 gap-1 border-b border-gray-100';
        
        // Time label
        const timeLabel = document.createElement('div');
        timeLabel.className = 'py-4 text-sm text-gray-500 text-right pr-2';
        timeLabel.textContent = formatHour(hour);
        timeSlot.appendChild(timeLabel);
        
        // Day slots
        for (let day = 0; day < 7; day++) {
          const daySlot = document.createElement('div');
          daySlot.className = 'min-h-[60px] p-1 hover:bg-gray-50 cursor-pointer border-r border-gray-100';
          
          const slotDate = new Date(weekStart);
          slotDate.setDate(weekStart.getDate() + day);
          slotDate.setHours(hour, 0, 0, 0);
          
          daySlot.onclick = () => addSessionAtTime(slotDate);
          
          // Check for sessions at this time
          const dayKey = slotDate.getDate().toString();
          if (calendarData.monthly_sessions && calendarData.monthly_sessions[dayKey]) {
            const sessions = calendarData.monthly_sessions[dayKey].filter(session => {
              const sessionHour = new Date(session.session_date).getHours();
              return sessionHour === hour;
            });
            
            sessions.forEach(session => {
              const sessionEl = createWeekSessionElement(session);
              daySlot.appendChild(sessionEl);
            });
          }
          
          timeSlot.appendChild(daySlot);
        }
        
        weekGrid.appendChild(timeSlot);
      }
    }

    function createWeekSessionElement(session) {
      const element = document.createElement('div');
      element.className = 'text-xs p-1 rounded cursor-pointer hover:opacity-80 transition-opacity mb-1';
      element.style.backgroundColor = session.color_code || '#10b981';
      element.style.color = 'white';
      element.title = `${session.program_name} - ${session.session_time_formatted}`;
      
      element.innerHTML = `
        <div class="font-medium truncate">${session.program_name}</div>
        <div class="opacity-90">${session.session_time_formatted}</div>
      `;
      
      element.onclick = (e) => {
        e.stopPropagation();
        viewSessionDetails(session.id);
      };
      
      return element;
    }

    function renderDayView() {
      const daySchedule = document.getElementById('daySchedule');
      const dayTitle = document.getElementById('dayViewTitle');
      
      dayTitle.textContent = currentCalendarDate.toLocaleDateString('en-US', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
      });
      
      daySchedule.innerHTML = '';
      
      const dayKey = currentCalendarDate.getDate().toString();
      const sessions = calendarData.monthly_sessions?.[dayKey] || [];
      
      if (sessions.length === 0) {
        const noSessions = document.createElement('div');
        noSessions.className = 'text-center text-gray-500 py-8';
        noSessions.innerHTML = `
          <svg class="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
          </svg>
          <div>No sessions scheduled for this day</div>
          <button onclick="addNewSession()" class="mt-2 text-tplearn-green hover:text-green-700">+ Add Session</button>
        `;
        daySchedule.appendChild(noSessions);
      } else {
        sessions.sort((a, b) => new Date(a.session_date) - new Date(b.session_date));
        sessions.forEach(session => {
          const sessionCard = createDaySessionCard(session);
          daySchedule.appendChild(sessionCard);
        });
      }
    }

    function createDaySessionCard(session) {
      const card = document.createElement('div');
      card.className = 'bg-white border border-gray-200 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow';
      card.onclick = () => viewSessionDetails(session.id);
      
      card.innerHTML = `
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center space-x-3 mb-2">
              <div class="w-3 h-3 rounded-full" style="background-color: ${session.color_code || '#10b981'}"></div>
              <h4 class="font-medium text-gray-900">${session.program_name}</h4>
              <span class="text-xs px-2 py-1 rounded-full ${getStatusBadgeClass(session.status)}">${session.status}</span>
            </div>
            <div class="text-sm text-gray-600 space-y-1">
              <div>⏰ ${session.session_time_formatted} - ${session.session_end_time}</div>
              <div>👥 ${session.student_count} student${session.student_count !== 1 ? 's' : ''}</div>
              <div>📍 ${session.session_type === 'online' ? 'Online Session' : 'In-Person'}</div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-sm font-medium text-gray-900">${session.session_duration_formatted}</div>
          </div>
        </div>
      `;
      
      return card;
    }

    // Navigation functions
    function previousPeriod() {
      switch (currentCalendarView) {
        case 'month':
          currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
          loadCalendarData();
          break;
        case 'week':
          currentCalendarDate.setDate(currentCalendarDate.getDate() - 7);
          break;
        case 'day':
          currentCalendarDate.setDate(currentCalendarDate.getDate() - 1);
          break;
      }
      updateCalendarDisplay();
      renderCalendar();
    }

    function nextPeriod() {
      switch (currentCalendarView) {
        case 'month':
          currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
          loadCalendarData();
          break;
        case 'week':
          currentCalendarDate.setDate(currentCalendarDate.getDate() + 7);
          break;
        case 'day':
          currentCalendarDate.setDate(currentCalendarDate.getDate() + 1);
          break;
      }
      updateCalendarDisplay();
      renderCalendar();
    }

    function todayCalendar() {
      const today = new Date();
      if (currentCalendarView === 'month') {
        currentCalendarDate = new Date(today.getFullYear(), today.getMonth(), 1);
        loadCalendarData();
      } else {
        currentCalendarDate = new Date(today);
      }
      updateCalendarDisplay();
      renderCalendar();
    }

    // Session management functions
    function viewSessionDetails(sessionId) {
      currentSessionId = sessionId;
      
      fetch('../../api/get-session-details.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ session_id: sessionId })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          populateSessionDetailsModal(data.session);
          openSessionDetailsModal();
        } else {
          alert('Error loading session details: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        TPAlert.error('Error', 'Error connecting to server');
      });
    }

    function populateSessionDetailsModal(session) {
      document.getElementById('sessionProgram').textContent = session.program_name;
      document.getElementById('sessionDateTime').textContent = `${session.session_date_formatted} at ${session.session_time_formatted}`;
      document.getElementById('sessionDuration').textContent = session.session_duration_formatted;
      document.getElementById('sessionType').textContent = session.session_type;
      document.getElementById('sessionLocation').textContent = session.location || session.video_call_link || 'Not specified';
      document.getElementById('sessionStudents').textContent = session.student_names || 'No students enrolled';
      document.getElementById('sessionStatus').innerHTML = `<span class="px-2 py-1 rounded-full text-xs ${getStatusBadgeClass(session.status)}">${session.status}</span>`;
      document.getElementById('sessionNotes').textContent = session.notes || 'No notes';
      
      // Show/hide action buttons based on session status and time
      updateSessionActionButtons(session);
    }

    function updateSessionActionButtons(session) {
      const now = new Date();
      const sessionDate = new Date(session.session_date);
      const isToday = isDateToday(sessionDate);
      const isPast = sessionDate < now;
      const isFuture = sessionDate > now;
      
      // Hide all buttons first
      document.querySelectorAll('#startSessionBtn, #joinSessionBtn, #endSessionBtn').forEach(btn => {
        btn.classList.add('hidden');
      });
      
      // Show appropriate buttons based on status and time
      if (session.status === 'scheduled' && isToday && !isPast) {
        document.getElementById('startSessionBtn').classList.remove('hidden');
      } else if (session.status === 'ongoing') {
        document.getElementById('joinSessionBtn').classList.remove('hidden');
        document.getElementById('endSessionBtn').classList.remove('hidden');
      }
    }

    function addNewSession() {
      currentSessionId = null;
      document.getElementById('addEditSessionTitle').textContent = 'Add New Session';
      document.getElementById('sessionForm').reset();
      
      // Set default date to current calendar date
      const dateStr = currentCalendarDate.toISOString().split('T')[0];
      document.getElementById('sessionDate').value = dateStr;
      
      populateSessionProgramOptions();
      openAddEditSessionModal();
    }

    function editSession() {
      if (!currentSessionId) return;
      
      document.getElementById('addEditSessionTitle').textContent = 'Edit Session';
      // Populate form with current session data
      populateSessionProgramOptions();
      openAddEditSessionModal();
    }

    function populateSessionProgramOptions() {
      const select = document.getElementById('sessionProgramSelect');
      select.innerHTML = '<option value="">Select a program</option>';
      
      if (calendarData.programs) {
        calendarData.programs.forEach(program => {
          const option = document.createElement('option');
          option.value = program.id;
          option.textContent = program.name;
          select.appendChild(option);
        });
      }
    }

    // Modal functions
    function openSessionDetailsModal() {
      document.getElementById('sessionDetailsModal').classList.remove('hidden');
      document.getElementById('sessionDetailsModal').classList.add('flex');
    }

    function closeSessionDetailsModal() {
      document.getElementById('sessionDetailsModal').classList.add('hidden');
      document.getElementById('sessionDetailsModal').classList.remove('flex');
    }

    function openAddEditSessionModal() {
      document.getElementById('addEditSessionModal').classList.remove('hidden');
      document.getElementById('addEditSessionModal').classList.add('flex');
    }

    function closeAddEditSessionModal() {
      document.getElementById('addEditSessionModal').classList.add('hidden');
      document.getElementById('addEditSessionModal').classList.remove('flex');
    }

    // Utility functions
    function isDateToday(date) {
      const today = new Date();
      return date.toDateString() === today.toDateString();
    }

    function getWeekStart(date) {
      const start = new Date(date);
      start.setDate(date.getDate() - date.getDay());
      return start;
    }

    function formatDateRange(start, end) {
      const startStr = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      const endStr = end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      return `${startStr} - ${endStr}`;
    }

    function formatHour(hour) {
      return hour > 12 ? `${hour - 12}:00 PM` : hour === 12 ? '12:00 PM' : `${hour}:00 AM`;
    }

    function getStatusBadgeClass(status) {
      switch (status) {
        case 'scheduled': return 'bg-blue-100 text-blue-800';
        case 'ongoing': return 'bg-green-100 text-green-800';
        case 'completed': return 'bg-gray-100 text-gray-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
      }
    }

    function populateUpcomingSessions() {
      const container = document.getElementById('upcomingSessions');
      container.innerHTML = '';
      
      if (!calendarData.upcoming_sessions || calendarData.upcoming_sessions.length === 0) {
        container.innerHTML = `
          <div class="p-4 text-center text-gray-500">
            <div class="text-sm">No upcoming sessions</div>
          </div>
        `;
        return;
      }
      
      calendarData.upcoming_sessions.forEach(session => {
        const sessionItem = document.createElement('div');
        sessionItem.className = 'p-4 hover:bg-gray-50 cursor-pointer transition-colors';
        sessionItem.onclick = () => viewSessionDetails(session.id);
        
        sessionItem.innerHTML = `
          <div class="flex items-start space-x-3">
            <div class="w-3 h-3 rounded-full mt-1.5" style="background-color: ${session.color_code || '#10b981'}"></div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-900 truncate">${session.program_name}</div>
              <div class="text-xs text-gray-500">${session.session_date_formatted}</div>
              <div class="text-xs text-gray-500">${session.session_time_formatted} • ${session.student_count} students</div>
            </div>
          </div>
        `;
        
        container.appendChild(sessionItem);
      });
    }

    function populateProgramFilter() {
      const select = document.getElementById('calendarProgramFilter');
      select.innerHTML = '<option value="">All Programs</option>';
      
      if (calendarData.programs) {
        calendarData.programs.forEach(program => {
          const option = document.createElement('option');
          option.value = program.id;
          option.textContent = program.name;
          select.appendChild(option);
        });
      }
    }

    function filterCalendarByProgram() {
      const selectedProgramId = document.getElementById('calendarProgramFilter').value;
      // Filter logic would be implemented here
      // For now, just re-render the calendar
      renderCalendar();
    }});
      }
      
      document.getElementById('weekSessionCount').textContent = sessionCount;
      document.getElementById('weekStudentCount').textContent = studentCount;
      document.getElementById('weekHoursCount').textContent = totalHours.toFixed(1);
    }

    // Additional quick action functions
    function viewAllStudents() {
      switchTab('students');
    }

    function exportSchedule() {
      TPAlert.info('Information', 'Schedule export functionality would be implemented here');
    }

    function syncCalendar() {
      TPAlert.info('Information', 'External calendar sync functionality would be implemented here');
    }

    function selectCalendarDate(date) {
      const dateKey = date.toISOString().split('T')[0];
      console.log('Selected date:', dateKey);
      
      // Get sessions for this date
      let sessionsForDate = [];
      if (calendarData && calendarData.sessions_by_date && calendarData.sessions_by_date[dateKey]) {
        sessionsForDate = calendarData.sessions_by_date[dateKey];
      }
      
      if (sessionsForDate.length > 0) {
        // Show sessions in a modal or switch to day view
        showSessionsForDate(date, sessionsForDate);
      } else {
        // No sessions for this date, maybe offer to add one
        console.log('No sessions for this date');
        const formatted = date.toLocaleDateString('en-US', { 
          weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
        });
        TPAlert.info('Information', `No sessions scheduled for ${formatted}. Would you like to add a session?`);
      }
    }

    function showSessionsForDate(date, sessions) {
      const formatted = date.toLocaleDateString('en-US', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
      });
      
      let sessionsList = sessions.map(session => {
        const timeText = session.start_time ? 
          new Date('2000-01-01 ' + session.start_time).toLocaleTimeString('en-US', {
            hour: 'numeric', minute: '2-digit', hour12: true
          }) : 'Time TBD';
          
        return `• ${session.program_name} - ${timeText}`;
      }).join('\\n');
      
      TPAlert.info('Information', `Sessions for ${formatted}:\\n\\n${sessionsList}`);
      
      // Future enhancement: Show in a proper modal with action buttons
      // openDaySessionModal(date, sessions);
    }

    function viewSessionDetails(sessionId, session) {
      console.log('Viewing session details:', sessionId, session);
      
      if (session) {
        const timeText = session.start_time ? 
          new Date('2000-01-01 ' + session.start_time).toLocaleTimeString('en-US', {
            hour: 'numeric', minute: '2-digit', hour12: true
          }) : 'Time TBD';
          
        const endTimeText = session.end_time ? 
          new Date('2000-01-01 ' + session.end_time).toLocaleTimeString('en-US', {
            hour: 'numeric', minute: '2-digit', hour12: true
          }) : '';
          
        const sessionDate = session.session_date ? session.session_date.split(' ')[0] : 'Date TBD';
        
        TPAlert.info('Information', `Session Details:\\n\\nProgram: ${session.program_name}\\nDate: ${sessionDate}\\nTime: ${timeText}${endTimeText ? ' - ' + endTimeText : ''}\\nStatus: ${session.status || 'scheduled'}`);
        
        // Future enhancement: Open session management modal
        // openSessionManagementModal(sessionId, session);
      } else {
        TPAlert.info('Information', 'Loading session details...');
      }
    }

    function addSessionAtTime(dateTime) {
      currentCalendarDate = new Date(dateTime);
      addNewSession();
      
      // Pre-fill the time
      const timeStr = dateTime.toTimeString().slice(0, 5);
      document.getElementById('sessionTime').value = timeStr;
    }

    // Session action functions (placeholders)
    // Note: startSession() is defined above
    
    function joinSession() {
      TPAlert.info('Information', 'Joining session...');
      // Implementation would handle joining online session
    }

    function endSession() {
      TPAlert.info('Information', 'Ending session...');
      // Implementation would handle session end logic
    }

    function markSessionAttendance() {
      TPAlert.info('Information', 'Opening attendance marking...');
      // Implementation would open attendance modal
    }

    function cancelSession() {
      TPAlert.confirm('Confirm Action', 'Are you sure you want to cancel this session?').then(result => {
        if (result.isConfirmed) {
        TPAlert.info('Information', 'Session cancelled');
        // Implementation would handle session cancellation
      }
    }

    // Session form handling
    document.getElementById('sessionForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      const sessionData = {};
      
      // Convert FormData to object
      for (let [key, value] of formData.entries()) {
        sessionData[key] = value;
      }
      
      // Combine date and time
      sessionData.session_datetime = sessionData.session_date + ' ' + sessionData.session_time;
      sessionData.session_id = currentSessionId; // Will be null for new sessions
      
      // Add repeat session data
      sessionData.repeat_session = document.getElementById('repeatSession').checked;
      sessionData.repeat_frequency = document.getElementById('repeatFrequency').value;
      
      // Submit session data
      fetch('../../api/save-session.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(sessionData)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          TPAlert.info('Information', currentSessionId ? 'Session updated successfully!' : 'Session created successfully!');
          closeAddEditSessionModal();
          loadCalendarData(); // Reload calendar data
        } else {
          alert('Error saving session: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        TPAlert.error('Error', 'Error connecting to server');
      });
    });

    // Handle repeat session checkbox
    document.getElementById('repeatSession').addEventListener('change', function() {
      document.getElementById('repeatFrequency').disabled = !this.checked;
    });

    // Header functions
    function openNotifications() {
      TPAlert.info('Information', 'Opening notifications...');
      // In a real application, this would open notifications panel
    }

    function openMessages() {
      TPAlert.info('Information', 'Opening messages...');
      // In a real application, this would open messages panel
    }

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
      // Set default tab
      switchTab('programs');

      // Set default filter
      filterPrograms('all');
      
      // Initialize student pagination
      updatePagination();
      
      // Initialize calendar data structures
      if (typeof calendarData === 'undefined') {
        window.calendarData = { monthly_sessions: {}, upcoming_sessions: [], programs: [] };
      }
      if (typeof currentCalendarDate === 'undefined') {
        window.currentCalendarDate = new Date();
      }
      if (typeof currentCalendarView === 'undefined') {
        window.currentCalendarView = 'month';
      }
      
      console.log('Page initialized. Calendar variables ready.');
      
      // Add keyboard shortcuts for grade management
      document.addEventListener('keydown', function(e) {
        // Only apply shortcuts when grade modal is open
        const gradeModal = document.getElementById('manageGradesModal');
        if (!gradeModal || gradeModal.classList.contains('hidden')) return;
        
        // Ctrl+S or Cmd+S to save grades
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
          e.preventDefault();
          saveAndCloseGrades();
        }
        
        // Escape to close modal
        if (e.key === 'Escape') {
          e.preventDefault();
          closeManageGradesModal();
        }
        
        // Ctrl+E or Cmd+E to export grades
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
          e.preventDefault();
          exportGrades();
        }
      });
      
      // Add keyboard shortcuts for student modals
      document.addEventListener('keydown', function(e) {
        // Escape to close student modals
        if (e.key === 'Escape') {
          const studentDetailsModal = document.getElementById('studentDetailsModal');
          const studentContactModal = document.getElementById('studentContactModal');
          
          if (studentDetailsModal && !studentDetailsModal.classList.contains('hidden')) {
            e.preventDefault();
            closeStudentDetailsModal();
          } else if (studentContactModal && !studentContactModal.classList.contains('hidden')) {
            e.preventDefault();
            closeStudentContactModal();
          }
        }
      });
      
      // Add warning for unsaved changes
      window.addEventListener('beforeunload', function(e) {
        const modifiedInputs = document.querySelectorAll('input[data-modified="true"]');
        if (modifiedInputs.length > 0) {
          e.preventDefault();
          e.returnValue = 'You have unsaved grade changes. Are you sure you want to leave?';
          return e.returnValue;
        }
      });
    });
    
    // Add helper function to validate all grades before saving
    function validateAllGrades() {
      const inputs = document.querySelectorAll('#manageGradesModal input[type="number"]');
      let hasErrors = false;
      
      inputs.forEach(input => {
        const value = input.value.trim();
        if (value !== '') {
          const numericValue = parseFloat(value);
          if (isNaN(numericValue) || numericValue < 0 || numericValue > 100) {
            input.classList.add('border-red-500', 'bg-red-50');
            hasErrors = true;
          } else {
            input.classList.remove('border-red-500', 'bg-red-50');
          }
        } else {
          input.classList.remove('border-red-500', 'bg-red-50');
        }
      });
      
      return !hasErrors;
    }
    
    // Enhanced error handling for API calls
    function handleApiError(error, context = 'operation') {
      console.error(`Error in ${context}:`, error);
      
      let message = `An error occurred during ${context}`;
      
      if (error.message) {
        if (error.message.includes('401')) {
          message = 'Your session has expired. Please log in again.';
        } else if (error.message.includes('403')) {
          message = 'You do not have permission to perform this action.';
        } else if (error.message.includes('404')) {
          message = 'The requested resource was not found.';
        } else if (error.message.includes('500')) {
          message = 'A server error occurred. Please try again later.';
        } else {
          message = error.message;
        }
      }
      
      createToast(message, 'error');
      return message;
    }

    // Calendar functionality
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();
    let sessionsData = [];
    let upcomingSessionsData = [];
    let allSessionsData = []; // Store all sessions for filtering
    let currentProgramFilter = 'all';
    let availablePrograms = new Set(); // Track available programs for filters

    // Force clear calendar immediately on script load
    function clearAllCalendarData() {
      sessionsData = [];
      upcomingSessionsData = [];
      allSessionsData = [];
      availablePrograms.clear();
      currentProgramFilter = 'all';
      
      // Clear calendar grid
      const calendarGrid = document.getElementById('calendar-grid');
      if (calendarGrid) {
        calendarGrid.innerHTML = `
          <div class="col-span-7 text-center py-12">
            <div class="text-gray-400 text-lg mb-2">📅</div>
            <div class="text-gray-600 font-medium mb-2">No calendar data available</div>
            <div class="text-gray-500 text-sm">Please log in as a valid tutor to view schedule</div>
          </div>
        `;
      }
      
      // Clear upcoming sessions
      const upcomingSessions = document.getElementById('upcoming-sessions-list');
      if (upcomingSessions) {
        upcomingSessions.innerHTML = `
          <div class="text-center py-4">
            <div class="text-gray-400 text-sm">No sessions available</div>
          </div>
        `;
      }
      
      // Clear program filters
      const filtersContainer = document.getElementById('program-filters');
      if (filtersContainer) {
        filtersContainer.innerHTML = '';
      }
      
      // Clear legend
      const legendContainer = document.getElementById('calendar-legend');
      if (legendContainer) {
        legendContainer.innerHTML = '';
      }
    }
    
    // Call clear function immediately
    clearAllCalendarData();

    // Fetch sessions data from API
    async function fetchSessionsData(year, month) {
      try {
        console.log(`Fetching sessions for ${year}-${month + 1}`);
        const response = await fetch(`../../api/get-tutor-calendar-sessions.php?year=${year}&month=${month + 1}`);
        
        if (!response.ok) {
          console.error('Response not ok:', response.status, response.statusText);
          
          // Clear all data on authentication failure
          allSessionsData = [];
          sessionsData = [];
          upcomingSessionsData = [];
          availablePrograms.clear();
          
          if (response.status === 401) {
            console.error('Authentication failed - not a valid tutor');
          }
          
          return false;
        }
        
        const data = await response.json();
        console.log('API Response:', data);
        
        if (data.success) {
          // Store all sessions for filtering
          allSessionsData = data.sessions.map(session => ({
            date: new Date(session.date),
            title: session.title,
            time: session.time,
            type: session.type,
            color: session.color,
            students: session.students,
            status: session.status,
            id: session.id,
            video_link: session.video_link,
            program_id: session.program_id,
            category: session.category,
            difficulty: session.difficulty,
            description: session.description
          }));
          
          // Track available programs for filters
          availablePrograms.clear();
          allSessionsData.forEach(session => {
            availablePrograms.add(session.title);
          });
          
          // Generate program filter buttons
          generateProgramFilters();
          
          // Apply current filter
          applyProgramFilter();
          
          upcomingSessionsData = data.upcoming_sessions.map(session => ({
            date: new Date(session.date),
            title: session.title,
            topic: session.topic,
            time: session.time,
            type: session.type,
            color: session.color,
            students: session.students,
            status: session.status,
            id: session.id,
            video_link: session.video_link,
            program_id: session.program_id,
            category: session.category,
            difficulty: session.difficulty
          }));
          
          console.log('Sessions loaded:', allSessionsData.length, 'total sessions,', sessionsData.length, 'filtered sessions,', upcomingSessionsData.length, 'upcoming sessions');
          return true;
        } else {
          console.error('Failed to fetch sessions:', data.error);
          
          // Clear all data on API error
          allSessionsData = [];
          sessionsData = [];
          upcomingSessionsData = [];
          availablePrograms.clear();
          
          return false;
        }
      } catch (error) {
        console.error('Error fetching sessions:', error);
        
        // Clear all data on network error
        allSessionsData = [];
        sessionsData = [];
        upcomingSessionsData = [];
        availablePrograms.clear();
        
        return false;
      }
    }

    async// Clear upcoming sessions
        const upcomingSessions = document.getElementById('upcoming-sessions-list');
        if (upcomingSessions) {
          upcomingSessions.innerHTML = `
            <div class="text-center py-4">
              <div class="text-gray-400 text-sm">No sessions available</div>
            </div>
          `;
        }
        
        // Clear program filters
        const filtersContainer = document.getElementById('program-filters');
        if (filtersContainer) {
          filtersContainer.innerHTML = '';
        }
        
        return;
      }

      const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
      ];

      // Update month/year display
      const monthYearElement = document.getElementById('current-month-year');
      if (monthYearElement) {
        monthYearElement.textContent = `${monthNames[currentMonth]} ${currentYear}`;
      }

      // Get first day of month and number of days
      const firstDay = new Date(currentYear, currentMonth, 1).getDay();
      const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

      const calendarGrid = document.getElementById('calendar-grid');
      if (!calendarGrid) return;
      
      calendarGrid.innerHTML = '';

      // Add empty cells for days before month starts
      for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'h-20 border border-gray-200 bg-gray-50';
        calendarGrid.appendChild(emptyDay);
      }

      // Add days of the month
      for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'h-20 border border-gray-200 bg-white p-1 overflow-hidden relative hover:bg-gray-50 cursor-pointer';

        // Check if this day has sessions
        const dayDate = new Date(currentYear, currentMonth, day);
        const daysSessions = sessionsData.filter(session => 
          session.date.getDate() === day &&
          session.date.getMonth() === currentMonth &&
          session.date.getFullYear() === currentYear
        );

        // Day number
        const dayNumber = document.createElement('div');
        dayNumber.className = 'text-xs font-medium text-gray-900 mb-1';
        dayNumber.textContent = day;
        dayElement.appendChild(dayNumber);

        // Add session indicators
        daysSessions.slice(0, 3).forEach((session, index) => {
          const sessionElement = document.createElement('div');
          sessionElement.className = `text-xs ${session.color} text-white px-1 py-0.5 rounded mb-0.5 truncate`;
          sessionElement.textContent = session.title;
          sessionElement.title = `${session.title} - ${session.time} (${session.students} students)`;
          
          // Add click handler for session details
          sessionElement.addEventListener('click', (e) => {
            e.stopPropagation();
            showSessionDetails(session);
          });
          
          dayElement.appendChild(sessionElement);
        });

        // Show "more" indicator if there are more than 3 sessions
        if (daysSessions.length > 3) {
          const moreElement = document.createElement('div');
          moreElement.className = 'text-xs text-gray-500 font-medium cursor-pointer';
          moreElement.textContent = `+${daysSessions.length - 3} more`;
          moreElement.addEventListener('click', () => {
            showDayDetails(dayDate, daysSessions);
          });
          dayElement.appendChild(moreElement);
        }

        calendarGrid.appendChild(dayElement);
      }

      renderUpcomingSessions();
    }

    function renderUpcomingSessions() {
      const upcomingSessionsList = document.getElementById('upcoming-sessions-list');
      if (!upcomingSessionsList) return;
      
      // Get upcoming sessions (next 7 days)
      const today = new Date();
      const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
      
      const upcomingSessions = sessionsData
        .filter(session => session.date >= today && session.date <= nextWeek)
        .sort((a, b) => a.date - b.date)
        .slice(0, 5);

      if (upcomingSessions.length === 0) {
        upcomingSessionsList.innerHTML = `
          <div class="text-center py-8">
            <div class="text-gray-400 mb-2">
              <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
              </svg>
            </div>
            <p class="text-sm text-gray-500">No upcoming sessions</p>
          </div>
        `;
        return;
      }

      upcomingSessionsList.innerHTML = upcomingSessions.map(session => {
        const formattedDate = session.date.toLocaleDateString('en-US', { 
          weekday: 'short', 
          month: 'short', 
          day: 'numeric' 
        });
        
        return `
          <div class="border-l-4 ${session.color} bg-gray-50 p-3 rounded-r-lg">
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <h4 class="font-medium text-gray-900 text-sm">${session.title}</h4>
                <p class="text-xs text-gray-600 mt-1">${session.topic || session.description || 'Scheduled session'}</p>
                <div class="flex items-center text-xs text-gray-500 mt-2">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                  </svg>
                  ${formattedDate}, ${session.time}
                </div>
                <div class="flex items-center text-xs text-gray-500 mt-1">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9 12a1 1 0 01-1-1V8a1 1 0 011-1h4a1 1 0 110 2h-3v3a1 1 0 01-1 1z" clip-rule="evenodd"></path>
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd"></path>
                  </svg>
                  ${session.students} students enrolled
                </div>
                ${session.category ? `<div class="text-xs text-gray-400 mt-1">${session.category} • ${session.difficulty || 'Standard'}</div>` : ''}
              </div>
              <button class="text-xs ${session.type === 'online' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'} px-2 py-1 rounded-full">
                ${session.type === 'online' ? 'Join Online' : 'In-person'}
              </button>
            </div>
          </div>
        `;
      }).join('');
    }

    async function previousMonth() {
      currentMonth--;
      if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
      }
      await renderCalendar();
    }

    async function nextMonth() {
      currentMonth++;
      if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
      }
      await renderCalendar();
    }Date: ${session.date.toLocaleDateString()}
Time: ${session.time}
Duration: ${session.duration || 90} minutes
Students Enrolled: ${session.students}
Session Type: ${session.type}
Status: ${session.status || 'Scheduled'}
Category: ${session.category || 'N/A'}
Difficulty: ${session.difficulty || 'N/A'}
${session.description ? 'Description: ' + session.description : ''}
${session.video_link ? 'Video Link Available' : 'In-Person Session'}
      `;
      
      TPAlert.info('Information', sessionInfo.trim());
    }at ${session.time} (${session.students} students)`
      ).join('\n');
      
      TPAlert.info('Information', `Sessions for ${date.toLocaleDateString()}:\n\n${sessionsList}`);
    }

    // Program filtering functions
    function generateProgramFilters() {
      const filtersContainer = document.getElementById('program-filters');
      if (!filtersContainer) return;
      
      filtersContainer.innerHTML = '';
      
      Array.from(availablePrograms).forEach(programName => {
        const button = document.createElement('button');
        button.textContent = programName;
        button.className = 'px-3 py-1 text-xs rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300';
        button.onclick = () => filterCalendarByProgram(programName);
        filtersContainer.appendChild(button);
      });
      
      // Also generate calendar legend
      generateCalendarLegend();
    }
    
    function generateCalendarLegend() {
      const legendContainer = document.getElementById('calendar-legend');
      if (!legendContainer) return;
      
      legendContainer.innerHTML = '';
      
      if (availablePrograms.size === 0) {
        return; // No programs to show
      }
      
      // Get unique programs with their colors from actual session data
      const programColors = new Map();
      allSessionsData.forEach(session => {
        if (!programColors.has(session.title)) {
          programColors.set(session.title, session.color);
        }
      });
      
      // Generate legend items
      programColors.forEach((color, programName) => {
        const legendItem = document.createElement('div');
        legendItem.className = 'flex items-center space-x-2';
        
        // Extract background color class (e.g., 'bg-green-500' -> 'bg-green-500')
        const colorClass = color.replace('bg-', '');
        
        legendItem.innerHTML = `
          <div class="w-3 h-3 ${color} rounded"></div>
          <span class="text-gray-600">${programName}</span>
        `;
        
        legendContainer.appendChild(legendItem);
      });
    }

    function filterCalendarByProgram(programName) {
      currentProgramFilter = programName;
      
      // Update filter button styles
      document.querySelectorAll('#program-filters button, #filter-all').forEach(btn => {
        btn.className = 'px-3 py-1 text-xs rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300';
      });
      
      if (programName === 'all') {
        document.getElementById('filter-all').className = 'px-3 py-1 text-xs rounded-full bg-tplearn-green text-white';
      } else {
        event.target.className = 'px-3 py-1 text-xs rounded-full bg-tplearn-green text-white';
      }
      
      // Apply filter and re-render calendar
      applyProgramFilter();
      renderCalendar();
    }

    function applyProgramFilter() {
      if (currentProgramFilter === 'all') {
        sessionsData = [...allSessionsData];
      } else {
        sessionsData = allSessionsData.filter(session => session.title === currentProgramFilter);
      }
    }

    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const mobileCloseButton = document.getElementById('mobile-close-button');
      const sidebar = document.getElementById('sidebar');
      const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
      
      function toggleMobileMenu() {
        if (sidebar && mobileMenuOverlay) {
          sidebar.classList.toggle('-translate-x-full');
          mobileMenuOverlay.classList.toggle('hidden');
        }
      }

      function closeMobileMenu() {
        if (sidebar && mobileMenuOverlay) {
          sidebar.classList.add('-translate-x-full');
          mobileMenuOverlay.classList.add('hidden');
        }
      }

      // Event listeners
      if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', toggleMobileMenu);
      }
      
      if (mobileCloseButton) {
        mobileCloseButton.addEventListener('click', closeMobileMenu);
      }
      
      if (mobileMenuOverlay) {
        mobileMenuOverlay.addEventListener('click', closeMobileMenu);
      }

      // Close mobile menu when clicking on a navigation link
      if (sidebar) {
        const navLinks = sidebar.querySelectorAll('a');
        navLinks.forEach(link => {
          link.addEventListener('click', () => {
            if (window.innerWidth < 1024) { // Only on mobile
              setTimeout(closeMobileMenu, 100); // Small delay for better UX
            }
          });
        });
      }

      // Close mobile menu on window resize to desktop
      window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
          closeMobileMenu();
        }
      });
    });

    // Debug: Verify functions are defined
    console.log('🔧 Function availability check:');
    console.log('✓ filterPrograms:', typeof filterPrograms);
    console.log('✓ markAttendance:', typeof markAttendance);
    console.log('✓ toggleProgram:', typeof toggleProgram);
    console.log('✓ switchTab:', typeof switchTab);
    console.log('🔧 Script loading complete!');

    // Debug function for testing buttons manually
    window.testButtons = function() {
      console.log('🧪 Testing button functions:');
      
      try {
        filterPrograms('all');
        console.log('✅ filterPrograms works');
      } catch (e) {
        console.error('❌ filterPrograms error:', e);
      }
      
      try {
        const programCards = document.querySelectorAll('.program-card');
        if (programCards.length > 0) {
          const firstCard = programCards[0];
          const programId = firstCard.getAttribute('data-program-id') || 'program-1';
          toggleProgram(programId);
          console.log('✅ toggleProgram works');
        }
      } catch (e) {
        console.error('❌ toggleProgram error:', e);
      }
      
      console.log('🧪 Button test complete. Check for errors above.');
    };

  </script>
</body>

</html>
