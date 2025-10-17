<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('tutor');

$tutor_user_id = $_SESSION['user_id'] ?? null;
$tutor_name = getTutorFullName($tutor_user_id);

if (!$tutor_user_id) {
  header('Location: ../../login.php');
  exit();
}

$programs = getTutorAssignedPrograms($tutor_user_id);
$currentDate = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Programs - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="../../assets/tplearn-tailwind.css?v=<?= filemtime(__DIR__ . '/../../assets/tplearn-tailwind.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  
  <style>
    /* Tab styles */
    .tab-active {
      border-bottom: 2px solid #10b981;
      color: #10b981;
    }

    .tab-inactive {
      color: #6b7280;
      border-bottom: 2px solid transparent;
    }

    .tab-inactive:hover {
      color: #374151;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/tutor-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 lg:ml-64">
      <?php 
      require_once '../../includes/header.php';
      renderHeader(
        'My Programs',
        $currentDate,
        'tutor',
        $tutor_name
      );
      ?>

      <!-- Dashboard Content -->
      <main class="p-6">
        <!-- Tab Navigation -->
        <div class="bg-white rounded-t-lg shadow-sm border border-gray-200 border-b-0">
          <div class="flex border-b border-gray-200">
            <button id="programs-tab" class="px-6 py-3 text-sm font-medium tab-active" onclick="switchTab('programs')">
              Programs
            </button>
            <button id="students-tab" class="px-6 py-3 text-sm font-medium tab-inactive" onclick="switchTab('students')">
              Students
            </button>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-b-lg shadow-sm border border-gray-200 border-t-0">

        <!-- Programs Tab Content -->
        <div id="programs-content" class="p-6">
          <!-- Filter Buttons -->
          <div class="mb-6">
            <div class="flex flex-wrap gap-2">
              <button id="all-programs-btn" class="px-4 py-2 bg-tplearn-green text-white rounded-lg text-sm font-medium" onclick="filterPrograms('all')">
                All Programs
              </button>
              <button id="online-programs-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300" onclick="filterPrograms('online')">
                Online Programs
              </button>
              <button id="inperson-programs-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300" onclick="filterPrograms('inperson')">
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
                <h3 class="mt-2 text-sm font-medium text-gray-900">No Programs Assigned</h3>
                <p class="mt-1 text-sm text-gray-500">You don't have any programs assigned yet.</p>
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
                <div class="bg-white rounded-lg shadow program-card" 
                     data-type="<?php echo $session_type_attr; ?>"
                     data-program-id="<?php echo $program['id']; ?>"
                     data-start-date="<?php echo htmlspecialchars($program['start_date'] ?? ''); ?>"
                     data-end-date="<?php echo htmlspecialchars($program['end_date'] ?? ''); ?>"
                     data-days="<?php echo htmlspecialchars($program['days'] ?? ''); ?>"
                     data-start-time="<?php echo htmlspecialchars($program['start_time'] ?? ''); ?>"
                     data-end-time="<?php echo htmlspecialchars($program['end_time'] ?? ''); ?>">
                  <div class="p-4 lg:p-6">
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
                              <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                            </svg>
                            Students: <?php echo $program['enrolled_students'] ?? 0; ?>
                          </div>
                          <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo htmlspecialchars($program['next_session']['date']); ?>
                          </div>
                          <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                              <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo ucfirst($program['session_type']); ?> Sessions
                          </div>
                        </div>
                        
                        <!-- View Program Stream Button -->
                        <div class="mt-4">
                          <button onclick="viewProgramStream(<?php echo $program['id']; ?>)" class="w-full flex items-center justify-center px-4 py-2 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors border border-gray-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                            </svg>
                            View Program Stream
                          </button>
                        </div>
                      </div>
                      <div class="ml-4">
                        <svg id="<?php echo $program_id; ?>-icon" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                      </div>
                    </div>

                    <!-- Expanded Content -->
                    <div id="<?php echo $program_id; ?>-details" class="hidden border-t border-gray-200 pt-4 mt-4">
                      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Next Session -->
                        <div>
                          <h4 class="font-semibold text-gray-800 mb-3">Next Session</h4>
                          <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                              <span class="font-medium text-gray-800">
                                <?php echo htmlspecialchars($program['next_session']['date']); ?>
                              </span>
                              <span class="bg-<?php echo $program['session_type'] === 'online' ? 'green' : 'blue'; ?>-100 text-<?php echo $program['session_type'] === 'online' ? 'green' : 'blue'; ?>-800 text-xs px-2 py-1 rounded-full">
                                <?php echo ucfirst($program['session_type']); ?>
                              </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">
                              <?php echo $program['session_time'] ?? 'Time TBD'; ?>
                            </p>
                          </div>
                        </div>

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
                              <span class="text-gray-600">Format:</span>
                              <span class="font-medium"><?php echo ucfirst($program['session_type']); ?></span>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Tutor Action Buttons -->
                      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <button onclick="manageAttendance(<?php echo $program['id']; ?>)" class="flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                          </svg>
                          Manage Attendance
                        </button>
                        <button onclick="manageGrades(<?php echo $program['id']; ?>)" class="flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path>
                            <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path>
                          </svg>
                          Manage Grades
                        </button>
                        <button onclick="viewStudents(<?php echo $program['id']; ?>)" class="flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors sm:col-span-2 lg:col-span-1">
                          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                          </svg>
                          View Students
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Students Tab Content -->
        <div id="students-content" class="p-6 hidden">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">All Students</h3>
          <p class="text-gray-600">Student management view coming soon.</p>
        </div>

        </div>
      </main>
    </div>
  </div>

  <!-- Attendance Management Modal -->
  <div id="attendanceManagementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[95vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Attendance Management</h3>
          <p id="attendanceModalProgramName" class="text-sm text-gray-600 mt-1">Program Name</p>
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
          <select id="sessionDateSelect" onchange="loadSessionAttendance(window.currentProgramId, this.value)" class="w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">Loading sessions...</option>
          </select>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <!-- Attendance Overview -->
          <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Session Info</h4>
              <div class="space-y-3 text-sm">
                <div id="sessionTimeInfo">
                  <span class="text-gray-600">Time:</span>
                  <p class="font-medium">Loading...</p>
                </div>
                <div>
                  <span class="text-gray-600">Expected:</span>
                  <p id="expectedCount" class="font-medium">0 students</p>
                </div>
                <div>
                  <span class="text-gray-600">Present:</span>
                  <p id="presentCount" class="font-medium text-green-600">0</p>
                </div>
                <div>
                  <span class="text-gray-600">Absent:</span>
                  <p id="absentCount" class="font-medium text-red-600">0</p>
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
                <div class="p-8 text-center text-gray-500">
                  Select a session date to load student attendance
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

  <!-- Manage Grades Modal -->
  <div id="manageGradesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Manage Student Grades</h3>
          <p class="text-sm text-gray-600 mt-1" id="gradeModalSubtitle">Loading program grades...</p>
        </div>
        <button onclick="closeManageGradesModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Student Grades List Container -->
        <div class="space-y-4 mb-6" id="studentGradesList">
          <!-- Loading state -->
          <div class="p-8 text-center text-gray-500">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green mx-auto mb-2"></div>
            <p>Loading student grades...</p>
          </div>
        </div>

        <!-- Grading Notes -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Grading Notes</label>
          <textarea rows="3" placeholder="Add any notes about the grading criteria..." 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                    id="gradingNotes"></textarea>
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

  <!-- View Students Modal -->
  <div id="viewStudentsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Program Students</h3>
          <p class="text-sm text-gray-600 mt-1" id="studentsModalSubtitle">Loading students...</p>
        </div>
        <button onclick="closeViewStudentsModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="p-6">
        <!-- Search and Filter -->
        <div class="mb-6 flex flex-col sm:flex-row gap-4">
          <div class="flex-1">
            <input type="text" id="studentSearch" placeholder="Search students by name or email..." 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                   onkeyup="filterStudents()">
          </div>
          <div class="flex gap-2">
            <select id="enrollmentStatusFilter" onchange="filterStudents()" 
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="paused">Paused</option>
            </select>
          </div>
        </div>

        <!-- Students List Container -->
        <div id="studentsList">
          <!-- Loading state -->
          <div class="p-8 text-center text-gray-500">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green mx-auto mb-2"></div>
            <p>Loading students...</p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center pt-4 border-t mt-6">
          <div class="flex space-x-3">
            <button onclick="exportStudentList()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 flex items-center transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              Export List
            </button>
            <button onclick="emailAllStudents()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 flex items-center transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
              </svg>
              Email All
            </button>
          </div>
          <button onclick="closeViewStudentsModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Include Sidebar JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>

  <script>
    console.log('🎓 Tutor Programs - Loading...');
    
    // Global variable to store current program ID for modals
    window.currentProgramId = null;

    // Tab switching functionality
    function switchTab(tabName) {
      // Hide all tab contents
      document.getElementById('programs-content').classList.add('hidden');
      document.getElementById('students-content').classList.add('hidden');

      // Remove active class from all tabs
      document.getElementById('programs-tab').className = 'px-6 py-3 text-sm font-medium tab-inactive';
      document.getElementById('students-tab').className = 'px-6 py-3 text-sm font-medium tab-inactive';

      // Show selected tab content and mark tab as active
      if (tabName === 'programs') {
        document.getElementById('programs-content').classList.remove('hidden');
        document.getElementById('programs-tab').className = 'px-6 py-3 text-sm font-medium tab-active';
      } else if (tabName === 'students') {
        document.getElementById('students-content').classList.remove('hidden');
        document.getElementById('students-tab').className = 'px-6 py-3 text-sm font-medium tab-active';
      }
    }

    // Program filtering functionality
    function filterPrograms(type) {
      const allCards = document.querySelectorAll('.program-card');
      const allButtons = document.querySelectorAll('[id$="-programs-btn"]');

      allButtons.forEach(btn => {
        btn.classList.remove('bg-tplearn-green', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
      });

      const activeButton = document.getElementById(type + '-programs-btn');
      activeButton.classList.remove('bg-gray-200', 'text-gray-700');
      activeButton.classList.add('bg-tplearn-green', 'text-white');

      allCards.forEach(card => {
        if (type === 'all' || card.dataset.type === type) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // Program expand/collapse functionality
    function toggleProgram(programId) {
      const details = document.getElementById(programId + '-details');
      const icon = document.getElementById(programId + '-icon');

      if (details && icon) {
        if (details.classList.contains('hidden')) {
          details.classList.remove('hidden');
          icon.style.transform = 'rotate(180deg)';
        } else {
          details.classList.add('hidden');
          icon.style.transform = 'rotate(0deg)';
        }
      }
    }

    // Tutor Action Functions
    function manageAttendance(programId) {
      console.log('📋 Opening Attendance Management for program:', programId);
      
      // Store program ID globally
      window.currentProgramId = programId;
      
      // Find program data from the DOM
      const programCard = document.querySelector(`[data-program-id="${programId}"]`);
      if (!programCard) {
        showNotification('Program data not found', 'error');
        return;
      }
      
      // Extract program data from data attributes
      const programData = {
        id: programId,
        name: programCard.querySelector('.text-lg.font-semibold')?.textContent || 'Unknown Program',
        start_date: programCard.dataset.startDate || '',
        end_date: programCard.dataset.endDate || '',
        days: programCard.dataset.days || '',
        start_time: programCard.dataset.startTime || '',
        end_time: programCard.dataset.endTime || ''
      };
      
      // Debug: Log the extracted program data
      console.log('Extracted program data:', programData);
      
      // Update modal with program name
      document.getElementById('attendanceModalProgramName').textContent = programData.name;
      
      // Show modal
      const modal = document.getElementById('attendanceManagementModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      
      // Initialize modal with session dates
      generateSessionDates(programData);
    }

    function manageGrades(programId) {
      console.log('📊 Opening Grades Management for program:', programId);
      
      // Store program ID globally
      window.currentProgramId = programId;
      
      // Show modal
      const modal = document.getElementById('manageGradesModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      
      // Load grade data for this program
      loadGradeData(programId);
    }

    function loadGradeData(programId) {
      console.log('Loading grade data for program:', programId);
      
      const studentList = document.getElementById('studentGradesList');
      const subtitle = document.getElementById('gradeModalSubtitle');
      
      // Show loading state
      studentList.innerHTML = `
        <div class="p-8 text-center text-gray-500">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green mx-auto mb-2"></div>
          <p>Loading student grades...</p>
        </div>
      `;
      
      subtitle.textContent = 'Loading program grades...';
      
      // Construct API URL
      const apiUrl = `../../api/grades.php?action=program_statistics&program_id=${programId}`;
      console.log('Fetching from:', apiUrl);
      
      // Fetch grades for this program
      fetch(apiUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
        }
      })
      .then(response => {
        console.log('API Response Status:', response.status);
        
        if (!response.ok) {
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
        console.log('Received grade data:', data);
        if (data.error) {
          throw new Error(data.error);
        }
        updateGradeModal(data);
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
        subtitle.textContent = 'Error loading grades';
      });
    }

    function updateGradeModal(data) {
      console.log('Updating grade modal with data:', data);
      
      const studentList = document.getElementById('studentGradesList');
      const subtitle = document.getElementById('gradeModalSubtitle');
      
      // Update modal subtitle
      subtitle.textContent = `${data.program_name} (${data.statistics.total_students} Students Enrolled)`;
      
      if (!data.students || data.students.length === 0) {
        studentList.innerHTML = `
          <div class="p-8 text-center text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
            </svg>
            <p class="text-lg font-medium mb-2">No Students Enrolled</p>
            <p class="text-sm">This program currently has no enrolled students.</p>
          </div>
        `;
        return;
      }

      // Add class statistics at the top
      let studentsHtml = `
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
      data.students.forEach((student, index) => {
        const nameParts = student.name.split(' ');
        const initials = nameParts.length > 1 ? nameParts[0].charAt(0) + nameParts[1].charAt(0) : nameParts[0].charAt(0) + nameParts[0].charAt(1);
        const colorClasses = [
          'bg-green-500', 'bg-blue-500', 'bg-purple-500', 'bg-yellow-500', 
          'bg-red-500', 'bg-indigo-500', 'bg-pink-500', 'bg-gray-500'
        ];
        const colorClass = colorClasses[index % colorClasses.length];
        
        // Determine grade color based on overall grade
        let gradeBadgeClass = 'bg-gray-100 text-gray-800';
        if (student.overall_grade >= 90) {
          gradeBadgeClass = 'bg-green-100 text-green-800';
        } else if (student.overall_grade >= 80) {
          gradeBadgeClass = 'bg-blue-100 text-blue-800';
        } else if (student.overall_grade >= 70) {
          gradeBadgeClass = 'bg-yellow-100 text-yellow-800';
        } else if (student.overall_grade > 0) {
          gradeBadgeClass = 'bg-red-100 text-red-800';
        }
        
        studentsHtml += `
          <div class="grid grid-cols-12 gap-4 items-center px-4 py-3 border rounded-lg hover:bg-gray-50 mb-2">
            <div class="col-span-3 flex items-center">
              <div class="w-8 h-8 ${colorClass} rounded-full flex items-center justify-center text-white font-semibold text-sm mr-3">
                ${initials}
              </div>
              <div>
                <p class="font-medium text-gray-900">${student.name}</p>
                <p class="text-xs text-gray-500">${student.email}</p>
              </div>
            </div>
            <div class="col-span-2">
              <span class="font-medium">${student.assessment_avg}%</span>
              <span class="text-xs text-gray-500 block">(${student.total_assessments} assessments)</span>
            </div>
            <div class="col-span-2">
              <span class="font-medium">${student.assignment_avg}%</span>
              <span class="text-xs text-gray-500 block">(${student.total_assignments} assignments)</span>
            </div>
            <div class="col-span-2">
              <span class="font-bold text-lg">${student.overall_grade}%</span>
            </div>
            <div class="col-span-2">
              <span class="${gradeBadgeClass} px-3 py-1 rounded-full text-sm font-medium">${student.letter_grade}</span>
            </div>
            <div class="col-span-1">
              <button 
                onclick="viewStudentGradeDetails('${student.user_id_string}')" 
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
      window.currentStudentGrades = data.students;
      window.currentGradeStats = data.statistics;
    }

    function closeManageGradesModal() {
      const modal = document.getElementById('manageGradesModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      
      // Clear stored data
      window.currentStudentGrades = null;
      window.currentGradeStats = null;
    }

    function viewStudentGradeDetails(studentId) {
      console.log('Viewing grade details for student:', studentId);
      
      // Find the student in the current data
      if (!window.currentStudentGrades) {
        showNotification('Student grade data not available', 'error');
        return;
      }
      
      const student = window.currentStudentGrades.find(s => s.user_id_string == studentId);
      if (!student) {
        console.log('Available students:', window.currentStudentGrades);
        console.log('Looking for studentId:', studentId);
        showNotification('Student data not found', 'error');
        return;
      }
      
      // Fetch detailed grade data for this student
      fetchStudentGradeDetails(studentId, student);
    }

    function fetchStudentGradeDetails(studentId, studentBasicData) {
      console.log('Fetching detailed grades for student:', studentId);
      
      // Use grades API with tutor_student_details action
      const apiUrl = `../../api/grades.php?action=tutor_student_details&student_username=${studentId}&program_id=${window.currentProgramId}`;
      console.log('API URL:', apiUrl);
      
      fetch(apiUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
        }
      })
      .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
          return response.text().then(text => {
            console.error('Error response text:', text);
            throw new Error(`HTTP ${response.status}: ${text || response.statusText}`);
          });
        }
        return response.json();
      })
      .then(data => {
        console.log('Received detailed grade data:', data);
        if (data.error) {
          throw new Error(data.error);
        }
        
        // Our API returns assignments array and summary directly
        if (data.assignments && data.summary) {
          showStudentGradeDetailsModal(studentBasicData, data);
        } else {
          throw new Error('Invalid data format received from API');
        }
      })
      .catch(error => {
        console.error('Error loading detailed grades:', error);
        showNotification('Error loading detailed grade information: ' + error.message, 'error');
      });
    }

    function showStudentGradeDetailsModal(student, detailData) {
      console.log('Showing detailed grade modal for:', student, detailData);
      
      const nameParts = student.name.split(' ');
      const initials = nameParts.length >= 2 ? nameParts[0].charAt(0) + nameParts[1].charAt(0) : student.name.charAt(0) + student.name.charAt(1);
      
      // Create detailed grade breakdown modal
      const modalHtml = `
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70]" id="gradeDetailsModal">
          <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b">
              <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-tplearn-green rounded-full flex items-center justify-center text-white font-bold text-lg">
                  ${initials}
                </div>
                <div>
                  <h3 class="text-xl font-semibold text-gray-900">Grade Details: ${student.name}</h3>
                  <p class="text-sm text-gray-600">${student.email} • Overall Grade: ${student.overall_grade}% (${student.letter_grade})</p>
                </div>
              </div>
              <button onclick="closeGradeDetailsModal()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            
            <div class="p-6">
              <!-- Grade Summary Cards -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center">
                  <div class="text-3xl font-bold text-blue-600 mb-2">${detailData.summary.assessment_avg}%</div>
                  <div class="text-sm font-medium text-blue-700 mb-1">Assessment Average</div>
                  <div class="text-xs text-gray-500">${detailData.summary.total_assessments} assessments</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                  <div class="text-3xl font-bold text-green-600 mb-2">${detailData.summary.assignment_avg}%</div>
                  <div class="text-sm font-medium text-green-700 mb-1">Assignment Average</div>
                  <div class="text-xs text-gray-500">${detailData.summary.total_assignments} assignments</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-6 text-center">
                  <div class="text-4xl font-bold text-purple-600 mb-2">${detailData.summary.overall_grade}%</div>
                  <div class="text-sm font-medium text-purple-700 mb-1">Overall Grade</div>
                  <div class="text-lg font-semibold text-purple-800">${detailData.summary.letter_grade}</div>
                </div>
              </div>
              
              <!-- Detailed Grades Table -->
              <div class="mb-6">
                <h4 class="text-lg font-semibold text-gray-900 mb-4">Assignment & Assessment Details</h4>
                ${generateGradesTable(detailData.assignments)}
              </div>
              
              <!-- Action Buttons -->
              <div class="flex justify-between items-center pt-6 border-t mt-8">
                <div class="flex space-x-3">
                  <button onclick="downloadStudentGrades('${student.student_id}')" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Report
                  </button>
                  <button onclick="emailStudentGrades('${student.student_id}')" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Email Report
                  </button>
                </div>
                <button onclick="closeGradeDetailsModal()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-400 transition-colors">
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
      
      // Remove existing modal if any
      const existingModal = document.getElementById('gradeDetailsModal');
      if (existingModal) {
        existingModal.remove();
      }
      
      // Add modal to document
      document.body.insertAdjacentHTML('beforeend', modalHtml);
      
      // Store current detail data
      window.currentStudentDetailData = detailData;
      window.currentStudentBasicData = student;
    }

    function generateGradesTable(assignments) {
      if (!assignments || assignments.length === 0) {
        return `
          <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-lg font-medium">No Assignments or Assessments</p>
            <p class="text-sm">This student has not completed any work yet.</p>
          </div>
        `;
      }
      
      let tableHtml = `
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
      `;
      
      assignments.forEach(item => {
        let gradeClass = 'text-gray-500';
        let statusClass = 'bg-gray-100 text-gray-800';
        let status = item.status || 'upcoming';
        
        // Set status classes based on our status values
        switch(status) {
          case 'graded':
            statusClass = 'bg-green-100 text-green-800';
            status = 'Graded';
            break;
          case 'submitted':
            statusClass = 'bg-blue-100 text-blue-800';
            status = 'Submitted';
            break;
          case 'upcoming':
            statusClass = 'bg-gray-100 text-gray-800';
            status = 'Upcoming';
            break;
          case 'overdue':
            statusClass = 'bg-red-100 text-red-800';
            status = 'Overdue';
            break;
        }
        
        // Set grade color based on percentage
        if (item.grade_percentage >= 90) gradeClass = 'text-green-600 font-semibold';
        else if (item.grade_percentage >= 80) gradeClass = 'text-blue-600 font-semibold';
        else if (item.grade_percentage >= 70) gradeClass = 'text-yellow-600 font-semibold';
        else if (item.grade_percentage > 0) gradeClass = 'text-red-600 font-semibold';
        
        // Type badge color
        const typeClass = item.type === 'Assignment' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800';
        
        tableHtml += `
          <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">${item.name}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${typeClass}">
                ${item.type}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm ${gradeClass}">${item.grade}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${item.earned_points || 0} / ${item.total_points}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${item.date}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">
                ${status}
              </span>
            </td>
          </tr>
        `;
      });
      
      tableHtml += `
            </tbody>
          </table>
        </div>
      `;
      
      return tableHtml;
    }

    function generateAssessmentsTable(assessments) {
      if (!assessments || assessments.length === 0) {
        return `
          <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-lg font-medium">No Assessments</p>
            <p class="text-sm">This student has not completed any assessments yet.</p>
          </div>
        `;
      }
      
      let tableHtml = `
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Points</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Submitted</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
      `;
      
      assessments.forEach(assessment => {
        const percentage = assessment.max_points > 0 ? ((assessment.score / assessment.max_points) * 100).toFixed(1) : 0;
        const statusClass = assessment.submitted_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
        const status = assessment.submitted_at ? 'Completed' : 'Pending';
        
        let gradeClass = 'text-gray-900';
        if (percentage >= 90) gradeClass = 'text-green-600 font-semibold';
        else if (percentage >= 80) gradeClass = 'text-blue-600 font-semibold';
        else if (percentage >= 70) gradeClass = 'text-yellow-600 font-semibold';
        else if (percentage > 0) gradeClass = 'text-red-600 font-semibold';
        
        tableHtml += `
          <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">${assessment.title}</div>
              <div class="text-sm text-gray-500">${assessment.description || 'No description'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm ${gradeClass}">${assessment.score || 0}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${assessment.max_points}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm ${gradeClass}">${percentage}%</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${assessment.submitted_at ? new Date(assessment.submitted_at).toLocaleDateString() : '-'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">
                ${status}
              </span>
            </td>
          </tr>
        `;
      });
      
      tableHtml += `
            </tbody>
          </table>
        </div>
      `;
      
      return tableHtml;
    }

    function generateAssignmentsTable(assignments) {
      if (!assignments || assignments.length === 0) {
        return `
          <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <p class="text-lg font-medium">No Assignments</p>
            <p class="text-sm">This student has not completed any assignments yet.</p>
          </div>
        `;
      }
      
      // Use same structure as assessments table but with assignment-specific fields
      let tableHtml = `
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Points</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
      `;
      
      assignments.forEach(assignment => {
        const percentage = assignment.max_points > 0 ? ((assignment.score / assignment.max_points) * 100).toFixed(1) : 0;
        const isLate = assignment.due_date && assignment.submitted_at && new Date(assignment.submitted_at) > new Date(assignment.due_date);
        let statusClass = 'bg-gray-100 text-gray-800';
        let status = 'Not Submitted';
        
        if (assignment.submitted_at) {
          if (isLate) {
            statusClass = 'bg-yellow-100 text-yellow-800';
            status = 'Late';
          } else {
            statusClass = 'bg-green-100 text-green-800';
            status = 'Completed';
          }
        }
        
        let gradeClass = 'text-gray-900';
        if (percentage >= 90) gradeClass = 'text-green-600 font-semibold';
        else if (percentage >= 80) gradeClass = 'text-blue-600 font-semibold';
        else if (percentage >= 70) gradeClass = 'text-yellow-600 font-semibold';
        else if (percentage > 0) gradeClass = 'text-red-600 font-semibold';
        
        tableHtml += `
          <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">${assignment.title}</div>
              <div class="text-sm text-gray-500">${assignment.description || 'No description'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm ${gradeClass}">${assignment.score || 0}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${assignment.max_points}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm ${gradeClass}">${percentage}%</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${assignment.due_date ? new Date(assignment.due_date).toLocaleDateString() : '-'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">
                ${status}
              </span>
            </td>
          </tr>
        `;
      });
      
      tableHtml += `
            </tbody>
          </table>
        </div>
      `;
      
      return tableHtml;
    }

    function generateProgressChart(progress) {
      return `
        <div class="text-center py-8 text-gray-500">
          <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
          </svg>
          <p class="text-lg font-medium">Progress Chart</p>
          <p class="text-sm">Progress visualization coming soon!</p>
        </div>
      `;
    }

    function switchGradeDetailTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.grade-detail-tab-content').forEach(content => {
        content.classList.add('hidden');
      });

      // Remove active styles from all tabs
      document.querySelectorAll('[id$="-detail-tab"]').forEach(tab => {
        tab.classList.remove('border-tplearn-green', 'text-tplearn-green');
        tab.classList.add('border-transparent', 'text-gray-500');
      });

      // Show selected tab content
      document.getElementById(tabName + '-detail-content').classList.remove('hidden');

      // Add active styles to selected tab
      const activeTab = document.getElementById(tabName + '-detail-tab');
      activeTab.classList.remove('border-transparent', 'text-gray-500');
      activeTab.classList.add('border-tplearn-green', 'text-tplearn-green');
    }

    function closeGradeDetailsModal() {
      const modal = document.getElementById('gradeDetailsModal');
      if (modal) {
        modal.remove();
      }
      
      // Clear stored detail data
      window.currentStudentDetailData = null;
      window.currentStudentBasicData = null;
    }

    function downloadStudentGrades(studentId) {
      if (!window.currentStudentDetailData || !window.currentStudentBasicData) {
        showNotification('Student grade data not available', 'error');
        return;
      }
      
      const student = window.currentStudentBasicData;
      const details = window.currentStudentDetailData;
      
      // Generate detailed CSV report
      let csv = `Student Grade Report\n`;
      csv += `Student: ${student.first_name} ${student.last_name}\n`;
      csv += `Email: ${student.email}\n`;
      csv += `Overall Grade: ${student.overall_average}% (${student.letter_grade})\n\n`;
      
      csv += `ASSESSMENTS\n`;
      csv += `Title,Score,Max Points,Percentage,Date Submitted,Status\n`;
      details.assessments.forEach(assessment => {
        const percentage = assessment.max_points > 0 ? ((assessment.score / assessment.max_points) * 100).toFixed(1) : 0;
        const status = assessment.submitted_at ? 'Completed' : 'Pending';
        csv += `"${assessment.title}",${assessment.score || 0},${assessment.max_points},${percentage}%,"${assessment.submitted_at ? new Date(assessment.submitted_at).toLocaleDateString() : 'Not submitted'}","${status}"\n`;
      });
      
      csv += `\nASSIGNMENTS\n`;
      csv += `Title,Score,Max Points,Percentage,Due Date,Status\n`;
      details.assignments.forEach(assignment => {
        const percentage = assignment.max_points > 0 ? ((assignment.score / assignment.max_points) * 100).toFixed(1) : 0;
        const isLate = assignment.due_date && assignment.submitted_at && new Date(assignment.submitted_at) > new Date(assignment.due_date);
        let status = 'Not Submitted';
        if (assignment.submitted_at) {
          status = isLate ? 'Late' : 'Completed';
        }
        csv += `"${assignment.title}",${assignment.score || 0},${assignment.max_points},${percentage}%,"${assignment.due_date ? new Date(assignment.due_date).toLocaleDateString() : 'No due date'}","${status}"\n`;
      });

      // Download the file
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `grade_report_${student.first_name}_${student.last_name}_${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);

      showNotification('Student grade report downloaded successfully!', 'success');
    }

    function emailStudentGrades(studentId) {
      showNotification('Email grade report feature coming soon!', 'info');
    }

    function downloadGrades() {
      if (!window.currentStudentGrades) {
        showNotification('No grade data available to download', 'warning');
        return;
      }

      // Generate CSV content
      let csv = 'Student Name,Email,Assessment Average,Assignment Average,Overall Grade,Letter Grade\n';
      
      window.currentStudentGrades.forEach(student => {
        csv += `"${student.first_name} ${student.last_name}","${student.email}",${student.assessment_average},${student.assignment_average},${student.overall_average},"${student.letter_grade}"\n`;
      });

      // Download CSV file
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `grades_program_${window.currentProgramId}_${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);

      showNotification('Grades exported successfully!', 'success');
    }

    function printGrades() {
      if (!window.currentStudentGrades) {
        showNotification('No grade data available to print', 'warning');
        return;
      }

      // Create print-friendly content
      const printContent = `
        <html>
          <head>
            <title>Student Grades Report</title>
            <style>
              body { font-family: Arial, sans-serif; margin: 20px; }
              table { width: 100%; border-collapse: collapse; margin: 20px 0; }
              th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
              th { background-color: #f5f5f5; }
              .header { margin-bottom: 20px; }
              .stats { background: #f9f9f9; padding: 15px; margin: 20px 0; }
            </style>
          </head>
          <body>
            <div class="header">
              <h1>Student Grades Report</h1>
              <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
            </div>
            
            <div class="stats">
              <h3>Class Statistics</h3>
              <p><strong>Class Average:</strong> ${window.currentGradeStats.class_average}%</p>
              <p><strong>Highest Grade:</strong> ${window.currentGradeStats.highest_grade}%</p>
              <p><strong>Lowest Grade:</strong> ${window.currentGradeStats.lowest_grade}%</p>
              <p><strong>Total Students:</strong> ${window.currentGradeStats.total_students}</p>
            </div>
            
            <table>
              <thead>
                <tr>
                  <th>Student Name</th>
                  <th>Email</th>
                  <th>Assessment Avg</th>
                  <th>Assignment Avg</th>
                  <th>Overall Grade</th>
                  <th>Letter Grade</th>
                </tr>
              </thead>
              <tbody>
                ${window.currentStudentGrades.map(student => `
                  <tr>
                    <td>${student.first_name} ${student.last_name}</td>
                    <td>${student.email}</td>
                    <td>${student.assessment_average}%</td>
                    <td>${student.assignment_average}%</td>
                    <td>${student.overall_average}%</td>
                    <td>${student.letter_grade}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </body>
        </html>
      `;

      // Open print window
      const printWindow = window.open('', '_blank');
      printWindow.document.write(printContent);
      printWindow.document.close();
      printWindow.print();
    }

    function saveAndCloseGrades() {
      const notes = document.getElementById('gradingNotes').value;
      
      if (notes.trim()) {
        // TODO: Save grading notes to database
        console.log('Saving grading notes:', notes);
        showNotification('Grading notes saved successfully!', 'success');
      }
      
      closeManageGradesModal();
    }

    function viewStudents(programId) {
      console.log('👥 Viewing Students for program:', programId);
      
      // Store current program ID
      window.currentViewStudentsProgramId = programId;
      
      // Show modal
      const modal = document.getElementById('viewStudentsModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      
      // Reset modal state
      document.getElementById('studentsList').innerHTML = `
        <div class="p-8 text-center text-gray-500">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-tplearn-green mx-auto mb-2"></div>
          <p>Loading students...</p>
        </div>
      `;
      
      // Fetch students data
      fetchProgramStudents(programId);
    }

    function fetchProgramStudents(programId) {
      const apiUrl = `../../api/grades.php?action=program_students&program_id=${programId}`;
      
      fetch(apiUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
        }
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.error) {
          throw new Error(data.error);
        }
        
        // Store students data globally
        window.currentStudentsList = data.students;
        
        // Update modal subtitle
        document.getElementById('studentsModalSubtitle').textContent = `${data.students.length} students enrolled`;
        
        // Render students list
        renderStudentsList(data.students);
      })
      .catch(error => {
        console.error('Error fetching students:', error);
        document.getElementById('studentsList').innerHTML = `
          <div class="p-8 text-center text-red-500">
            <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-lg font-medium">Error Loading Students</p>
            <p class="text-sm">Failed to load student list: ${error.message}</p>
          </div>
        `;
      });
    }

    function renderStudentsList(students) {
      if (!students || students.length === 0) {
        document.getElementById('studentsList').innerHTML = `
          <div class="p-8 text-center text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <p class="text-lg font-medium">No Students Enrolled</p>
            <p class="text-sm">This program has no enrolled students yet.</p>
          </div>
        `;
        return;
      }

      let studentsHtml = `
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrollment Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance Rate</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
      `;

      students.forEach(student => {
        const initials = student.first_name.charAt(0) + student.last_name.charAt(0);
        const statusClass = student.enrollment_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
        const attendanceClass = student.attendance_rate >= 80 ? 'text-green-600' : student.attendance_rate >= 60 ? 'text-yellow-600' : 'text-red-600';
        
        studentsHtml += `
          <tr class="hover:bg-gray-50 student-row" data-student-name="${student.full_name.toLowerCase()}" data-student-email="${student.email.toLowerCase()}" data-enrollment-status="${student.enrollment_status}">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-tplearn-green rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                  ${initials}
                </div>
                <div>
                  <div class="text-sm font-medium text-gray-900">${student.full_name}</div>
                  <div class="text-sm text-gray-500">${student.username}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${student.email}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">${new Date(student.enrollment_date).toLocaleDateString()}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">
                ${student.enrollment_status.charAt(0).toUpperCase() + student.enrollment_status.slice(1)}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium ${attendanceClass}">${student.attendance_rate}%</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              <div class="flex space-x-2">
                <button onclick="viewStudentProfile(${student.user_id})" class="text-blue-600 hover:text-blue-900" title="View Profile">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                  </svg>
                </button>
                <button onclick="emailStudent('${student.email}', '${student.full_name}')" class="text-green-600 hover:text-green-900" title="Send Email">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        `;
      });

      studentsHtml += `
            </tbody>
          </table>
        </div>
      `;

      document.getElementById('studentsList').innerHTML = studentsHtml;
    }

    function filterStudents() {
      const searchTerm = document.getElementById('studentSearch').value.toLowerCase();
      const statusFilter = document.getElementById('enrollmentStatusFilter').value;
      
      const rows = document.querySelectorAll('.student-row');
      
      rows.forEach(row => {
        const name = row.getAttribute('data-student-name');
        const email = row.getAttribute('data-student-email');
        const status = row.getAttribute('data-enrollment-status');
        
        const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;
        
        if (matchesSearch && matchesStatus) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    function closeViewStudentsModal() {
      const modal = document.getElementById('viewStudentsModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    function viewStudentProfile(userId) {
      console.log('Viewing profile for student:', userId);
      showNotification('Student profile feature coming soon!', 'info');
    }

    function emailStudent(email, name) {
      console.log('Emailing student:', email, name);
      showNotification('Email feature coming soon!', 'info');
    }

    function exportStudentList() {
      console.log('Exporting student list for program:', window.currentViewStudentsProgramId);
      showNotification('Export feature coming soon!', 'info');
    }

    function emailAllStudents() {
      console.log('Emailing all students in program:', window.currentViewStudentsProgramId);
      showNotification('Email all feature coming soon!', 'info');
    }

    function viewProgramStream(programId) {
      console.log('📚 Viewing Program Stream for program:', programId);
      // Redirect to the program stream page
      window.location.href = `tutor-program-stream.php?program_id=${programId}`;
    }

    // ========== ATTENDANCE MANAGEMENT FUNCTIONS ==========

    function closeAttendanceManagementModal() {
      const modal = document.getElementById('attendanceManagementModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    function generateSessionDates(program) {
      console.log('generateSessionDates called with program:', program);
      
      const sessionSelect = document.getElementById('sessionDateSelect');
      sessionSelect.innerHTML = '<option value="">Select a session date...</option>';

      if (!program.start_date || !program.end_date || !program.days) {
        sessionSelect.innerHTML = '<option value="">No session schedule available</option>';
        console.error('Missing program data:', {
          start_date: program.start_date,
          end_date: program.end_date, 
          days: program.days
        });
        return;
      }

      // Parse program days - handle both full and abbreviated day names
      const daysOfWeek = program.days.split(',').map(d => d.trim().toLowerCase());
      console.log('Days of week parsed:', daysOfWeek);
      
      const dayMap = {
        // Full names
        'monday': 1, 'tuesday': 2, 'wednesday': 3, 'thursday': 4,
        'friday': 5, 'saturday': 6, 'sunday': 0,
        // Abbreviated names
        'mon': 1, 'tue': 2, 'wed': 3, 'thu': 4,
        'fri': 5, 'sat': 6, 'sun': 0
      };

      // Convert to day numbers
      const programDays = daysOfWeek.map(day => dayMap[day]).filter(d => d !== undefined);
      console.log('Program days mapped to numbers:', programDays);

      if (programDays.length === 0) {
        sessionSelect.innerHTML = '<option value="">Invalid schedule configuration</option>';
        console.error('Invalid day configuration:', program.days, 'parsed as:', daysOfWeek);
        return;
      }

      // Parse dates
      const startDate = new Date(program.start_date);
      const endDate = new Date(program.end_date);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      console.log('Date range:', {
        startDate: startDate,
        endDate: endDate,
        today: today
      });

      // Generate all session dates
      const pastSessions = [];
      const currentSession = [];
      const futureSessions = [];

      let currentDate = new Date(startDate);
      let sessionCount = 0;
      
      while (currentDate <= endDate) {
        const dayOfWeek = currentDate.getDay();
        if (programDays.includes(dayOfWeek)) {
          sessionCount++;
          const sessionDate = new Date(currentDate);
          const dateStr = sessionDate.toISOString().split('T')[0];
          const displayDate = sessionDate.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
          });

          const sessionInfo = {
            date: dateStr,
            display: `${displayDate} (${program.start_time} - ${program.end_time})`
          };

          if (sessionDate < today) {
            pastSessions.push(sessionInfo);
          } else if (sessionDate.getTime() === today.getTime()) {
            currentSession.push(sessionInfo);
          } else {
            futureSessions.push(sessionInfo);
          }
        }
        currentDate.setDate(currentDate.getDate() + 1);
      }

      console.log('Sessions generated:', {
        total: sessionCount,
        past: pastSessions.length,
        current: currentSession.length,
        future: futureSessions.length
      });

      // Add current session first
      if (currentSession.length > 0) {
        const option = document.createElement('option');
        option.value = currentSession[0].date;
        option.textContent = `📍 TODAY - ${currentSession[0].display}`;
        option.selected = true;
        sessionSelect.appendChild(option);
      }

      // Add future sessions
      if (futureSessions.length > 0) {
        const futureOptgroup = document.createElement('optgroup');
        futureOptgroup.label = 'Upcoming Sessions';
        futureSessions.forEach(session => {
          const option = document.createElement('option');
          option.value = session.date;
          option.textContent = session.display;
          futureOptgroup.appendChild(option);
        });
        sessionSelect.appendChild(futureOptgroup);
      }

      // Add past sessions
      if (pastSessions.length > 0) {
        const pastOptgroup = document.createElement('optgroup');
        pastOptgroup.label = 'Past Sessions';
        pastSessions.reverse().forEach(session => {
          const option = document.createElement('option');
          option.value = session.date;
          option.textContent = session.display;
          pastOptgroup.appendChild(option);
        });
        sessionSelect.appendChild(pastOptgroup);
      }

      // Add event listener for session change
      sessionSelect.addEventListener('change', function() {
        if (this.value && this.value !== 'no-sessions') {
          loadSessionAttendance(programId, this.value);
        }
      });

      // Auto-load current session if available, otherwise load first session
      if (currentSession.length > 0) {
        loadSessionAttendance(program.id, currentSession[0].date);
      } else if (sessionSelect.options.length > 1) {
        loadSessionAttendance(program.id, sessionSelect.options[1].value);
      }

      // Check for saved attendance in each session option
      checkSavedAttendanceForSessions(program.id, sessionSelect);
    }

    function checkSavedAttendanceForSessions(programId, sessionSelect) {
      // Check which sessions have saved attendance and mark them visually
      fetch(`../../api/get-saved-sessions.php?program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.saved_sessions) {
            data.saved_sessions.forEach(sessionDate => {
              const option = sessionSelect.querySelector(`option[value="${sessionDate}"]`);
              if (option && !option.textContent.includes('✓')) {
                option.textContent = `✓ ${option.textContent}`;
                option.style.backgroundColor = '#dcfce7'; // light green background
                option.title = 'Attendance has been saved for this session';
              }
            });
          }
        })
        .catch(error => {
          console.log('Could not check saved sessions:', error);
        });
    }

    function loadSessionAttendance(programId, sessionDate) {
      if (!sessionDate) {
        document.getElementById('attendanceStudentList').innerHTML = 
          '<div class="p-8 text-center text-gray-500">Please select a session date</div>';
        return;
      }

      console.log('Loading attendance for:', programId, sessionDate);
      
      // Show loading state
      document.getElementById('attendanceStudentList').innerHTML = 
        '<div class="p-8 text-center text-gray-500">Loading students...</div>';

      // Update session time info
      document.getElementById('sessionTimeInfo').innerHTML = 
        '<span class="text-gray-600">Time:</span><p class="font-medium">Loading...</p>';

      // Fetch attendance data
      fetch(`../../api/get-session-attendance.php?program_id=${programId}&session_date=${sessionDate}`)
        .then(response => response.json())
        .then(data => {
          console.log('API Response:', data);
          
          if (data.success) {
            // Update session time info
            if (data.session_info && data.session_info.start_time && data.session_info.end_time) {
              document.getElementById('sessionTimeInfo').innerHTML = 
                `<span class="text-gray-600">Time:</span><p class="font-medium">${data.session_info.start_time} - ${data.session_info.end_time}</p>`;
            }
            
            // Merge students with their attendance data
            const studentsWithAttendance = data.students.map(student => {
              const attendanceRecord = data.attendance_data.find(a => a.student_user_id == student.user_id);
              console.log(`Student ${student.user_id}: attendance record:`, attendanceRecord);
              return {
                ...student,
                status: attendanceRecord ? attendanceRecord.status : 'present', // default to present if no record
                notes: attendanceRecord ? attendanceRecord.notes : '',
                marked_at: attendanceRecord ? attendanceRecord.marked_at : null,
                session_id: attendanceRecord ? attendanceRecord.session_id : null
              };
            });
            
            console.log('Students with attendance data:', studentsWithAttendance);
            renderStudentAttendanceList(studentsWithAttendance, sessionDate);
            updateAttendanceCounts();
          } else {
            showNotification(data.message || data.error || 'Failed to load attendance', 'error');
            document.getElementById('attendanceStudentList').innerHTML = 
              `<div class="p-8 text-center text-gray-500">Error: ${data.message || data.error || 'Failed to load data'}</div>`;
          }
        })
        .catch(error => {
          console.error('Error loading attendance:', error);
          showNotification('Error loading attendance data', 'error');
          document.getElementById('attendanceStudentList').innerHTML = 
            '<div class="p-8 text-center text-gray-500">Error loading data. Please try again.</div>';
        });
    }

    function renderStudentAttendanceList(students, sessionDate) {
      console.log('Rendering student list:', students);
      
      const listContainer = document.getElementById('attendanceStudentList');
      
      if (!students || students.length === 0) {
        listContainer.innerHTML = '<div class="p-8 text-center text-gray-500">No students enrolled in this program</div>';
        // Reset counts
        document.getElementById('expectedCount').textContent = '0 students';
        document.getElementById('presentCount').textContent = '0';
        document.getElementById('absentCount').textContent = '0';
        return;
      }

      listContainer.innerHTML = '';
      
      students.forEach(student => {
        const studentRow = document.createElement('div');
        studentRow.className = 'flex items-center justify-between p-4 hover:bg-gray-50';
        studentRow.dataset.studentId = student.user_id;
        
        studentRow.innerHTML = `
          <div class="flex items-center space-x-4 flex-1">
            <div class="w-10 h-10 bg-tplearn-green text-white rounded-full flex items-center justify-center font-semibold">
              ${student.first_name.charAt(0)}${student.last_name.charAt(0)}
            </div>
            <div>
              <p class="font-medium text-gray-900">${student.first_name} ${student.last_name}</p>
              <p class="text-sm text-gray-600">${student.email}</p>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <select class="attendance-status border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                    data-student-id="${student.user_id}"
                    onchange="updateAttendanceCounts()">
              <option value="present" ${(student.status === 'present') ? 'selected' : ''}>Present</option>
              <option value="absent" ${(student.status === 'absent') ? 'selected' : ''}>Absent</option>
            </select>
          </div>
        `;
        
        listContainer.appendChild(studentRow);
      });

      document.getElementById('expectedCount').textContent = `${students.length} students`;
      
      // Update counts after rendering
      updateAttendanceCounts();
    }

    function updateAttendanceCounts() {
      const statusSelects = document.querySelectorAll('.attendance-status');
      let present = 0, absent = 0;

      statusSelects.forEach(select => {
        const status = select.value;
        if (status === 'present') present++;
        else if (status === 'absent') absent++;
      });

      document.getElementById('presentCount').textContent = present;
      document.getElementById('absentCount').textContent = absent;
    }

    function markAllPresent() {
      if (!confirm('Mark all students as present for this session?')) return;

      const statusSelects = document.querySelectorAll('.attendance-status');
      statusSelects.forEach(select => {
        select.value = 'present';
      });

      updateAttendanceCounts();
      createToast('All students marked as present', 'success');
    }

    function markAllAbsent() {
      if (!confirm('Mark all students as absent for this session?')) return;

      const statusSelects = document.querySelectorAll('.attendance-status');
      statusSelects.forEach(select => {
        select.value = 'absent';
      });

      updateAttendanceCounts();
      createToast('All students marked as absent', 'warning');
    }

    function createToast(message, type = 'info') {
      const toast = document.createElement('div');
      const bgColors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
      };

      toast.className = `fixed bottom-4 right-4 ${bgColors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-y-0 opacity-100`;
      toast.textContent = message;

      document.body.appendChild(toast);

      setTimeout(() => {
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
      }, 4000);
    }

    function exportAttendanceReport() {
      const sessionDate = document.getElementById('sessionDateSelect').value;
      if (!sessionDate) {
        showNotification('Please select a session date first', 'warning');
        return;
      }

      const programName = document.getElementById('attendanceModalProgramName').textContent;
      const students = [];
      
      document.querySelectorAll('#attendanceStudentList > div').forEach(row => {
        const name = row.querySelector('p.font-medium')?.textContent || '';
        const email = row.querySelector('p.text-sm')?.textContent || '';
        const status = row.querySelector('.attendance-status')?.value || '';
        
        if (name) {
          students.push({ name, email, status });
        }
      });

      if (students.length === 0) {
        showNotification('No attendance data to export', 'warning');
        return;
      }

      // Generate CSV
      let csv = 'Student Name,Email,Status,Date\n';
      students.forEach(student => {
        csv += `"${student.name}","${student.email}","${student.status}","${sessionDate}"\n`;
      });

      // Download
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `attendance_${programName.replace(/\s+/g, '_')}_${sessionDate}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);

      createToast('Attendance report exported successfully', 'success');
    }

    function sendAbsentNotices() {
      const absentStudents = [];
      document.querySelectorAll('.attendance-status').forEach(select => {
        if (select.value === 'absent') {
          const row = select.closest('div[data-student-id]');
          const name = row.querySelector('p.font-medium')?.textContent || '';
          const email = row.querySelector('p.text-sm')?.textContent || '';
          absentStudents.push({ name, email });
        }
      });

      if (absentStudents.length === 0) {
        showNotification('No absent students to notify', 'info');
        return;
      }

      if (!confirm(`Send absence notices to ${absentStudents.length} student(s)?`)) return;

      // TODO: Implement actual email sending via API
      createToast(`Absence notices sent to ${absentStudents.length} student(s)`, 'success');
    }

    function showAttendanceSavedState() {
      // Add visual feedback that attendance has been saved
      const modal = document.getElementById('attendanceManagementModal');
      const saveBtn = modal.querySelector('button[onclick="saveAttendanceData()"]');
      
      // Show saved state temporarily
      const originalBtnHtml = saveBtn.innerHTML;
      saveBtn.innerHTML = `
        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
        </svg>
        Saved Successfully!
      `;
      saveBtn.className = saveBtn.className.replace('bg-blue-600', 'bg-green-600');
      
      // Reset button after 3 seconds
      setTimeout(() => {
        saveBtn.innerHTML = originalBtnHtml;
        saveBtn.className = saveBtn.className.replace('bg-green-600', 'bg-blue-600');
      }, 3000);
    }

    function saveAttendanceData() {
      const programId = window.currentProgramId;
      const sessionDate = document.getElementById('sessionDateSelect').value;

      if (!programId || !sessionDate) {
        showNotification('Missing program or session information', 'error');
        return;
      }

      // Collect attendance data
      const attendanceData = [];
      document.querySelectorAll('.attendance-status').forEach(select => {
        const studentId = parseInt(select.dataset.studentId);
        const status = select.value;
        
        console.log(`Collecting attendance: Student ${studentId} = ${status}`);
        
        attendanceData.push({
          student_user_id: studentId,
          status: status,
          arrival_time: null,
          notes: null
        });
      });

      if (attendanceData.length === 0) {
        showNotification('No attendance data to save', 'warning');
        return;
      }

      console.log('Saving attendance data:', {
        program_id: programId,
        session_date: sessionDate,
        attendance_data: attendanceData
      });

      // Show saving indicator
      const saveBtn = event.target;
      const originalText = saveBtn.textContent;
      saveBtn.textContent = 'Saving...';
      saveBtn.disabled = true;

      // Send to API
      fetch('../../api/save-attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          program_id: programId,
          session_date: sessionDate,
          attendance_data: attendanceData
        })
      })
      .then(response => {
        console.log('Save API response status:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Save API response data:', data);
        
        if (data.success) {
          createToast(`Attendance saved successfully for ${data.saved_count || attendanceData.length} students`, 'success');
          
          // Show success state in the modal
          showAttendanceSavedState();
          
          // Reload the attendance data to show saved status
          loadSessionAttendance(programId, sessionDate);
          
          // Update the session dropdown to show saved indicator
          const sessionSelect = document.getElementById('sessionDateSelect');
          const currentOption = sessionSelect.querySelector(`option[value="${sessionDate}"]`);
          if (currentOption && !currentOption.textContent.includes('✓')) {
            currentOption.textContent = `✓ ${currentOption.textContent}`;
            currentOption.style.backgroundColor = '#dcfce7';
            currentOption.title = 'Attendance has been saved for this session';
          }
          
          // Optional: Auto-close modal after successful save
          // Uncomment the next two lines if you want the modal to auto-close
          // setTimeout(() => {
          //   closeAttendanceManagementModal();
          // }, 2000);
        } else {
          showNotification(data.error || data.message || 'Failed to save attendance', 'error');
          console.error('Save failed:', data);
        }
      })
      .catch(error => {
        console.error('Error saving attendance:', error);
        showNotification('Error saving attendance data', 'error');
      })
      .finally(() => {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
      });
    }

    // ========== END ATTENDANCE FUNCTIONS ==========

    // Header functions
    function openNotifications() {
      showNotification('Notifications panel coming soon!', 'info');
    }

    function openMessages() {
      showNotification('Messages panel coming soon!', 'info');
    }

    // Notification system
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm transition-all duration-300 ${getNotificationClass(type)}`;
      notification.innerHTML = `
        <div class="flex items-start">
          <div class="flex-shrink-0">
            ${getNotificationIcon(type)}
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium text-white">${message}</p>
          </div>
          <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
        </div>
      `;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        if (notification.parentNode) {
          notification.classList.add('opacity-0', 'transform', 'translate-x-full');
          setTimeout(() => notification.remove(), 300);
        }
      }, 5000);
    }
    
    function getNotificationClass(type) {
      switch (type) {
        case 'success': return 'bg-green-600 text-white border-l-4 border-green-400';
        case 'error': return 'bg-red-600 text-white border-l-4 border-red-400';
        case 'warning': return 'bg-yellow-600 text-white border-l-4 border-yellow-400';
        default: return 'bg-blue-600 text-white border-l-4 border-blue-400';
      }
    }
    
    function getNotificationIcon(type) {
      const iconClass = 'w-5 h-5 text-white';
      switch (type) {
        case 'success':
          return `<svg class="${iconClass}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>`;
        case 'error':
          return `<svg class="${iconClass}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>`;
        case 'warning':
          return `<svg class="${iconClass}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>`;
        default:
          return `<svg class="${iconClass}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>`;
      }
    }

    // Initialize with programs tab active
    document.addEventListener('DOMContentLoaded', function() {
      switchTab('programs');
      
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

      if (sidebar) {
        const navLinks = sidebar.querySelectorAll('a');
        navLinks.forEach(link => {
          link.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
              setTimeout(closeMobileMenu, 100);
            }
          });
        });
      }

      window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
          closeMobileMenu();
        }
      });
    });

    console.log('✅ Tutor Programs - Loaded Successfully!');
  </script>
</body>

</html>
