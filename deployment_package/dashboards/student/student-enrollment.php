<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
require_once '../../includes/schedule-conflict.php';
require_once '../../assets/icons.php';
requireRole('student');

// Get current user data from session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? 'Student';

// Check if user_id is available
if (!$user_id) {
  header('Location: ../../login.php');
  exit();
}

// Get student data for display name
$student_data = getStudentDashboardData($user_id);
$display_name = $student_data['name'] ?? $user_name;

// Handle enrollment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_program'])) {
  // CSRF protection (simple token check)
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $error_message = "Invalid request. Please try again.";
  } else {
    $program_id = (int)$_POST['program_id'];

    // Check enrollment eligibility (capacity and duplicate enrollment)
    $eligibility_check = validateEnrollmentEligibility($_SESSION['user_id'], $program_id);
    
    if (!$eligibility_check['eligible']) {
      $error_message = "Enrollment Failed: " . $eligibility_check['reason'];
      $_SESSION['enrollment_error'] = $error_message; // Store in session
    } else {
      // Check for schedule conflicts
      $conflict_check = checkScheduleConflict($_SESSION['user_id'], $program_id);
      
      if ($conflict_check['has_conflict']) {
        $conflicting_programs = array_map(function($conflict) {
          return $conflict['program_name'];
        }, $conflict_check['conflicting_programs']);
        
        $error_message = "Schedule Conflict Detected! This program overlaps with your enrolled program(s): " . 
                        implode(', ', $conflicting_programs) . ". " .
                        "Please choose a different program or contact support to resolve the conflict.";
        $_SESSION['enrollment_error'] = $error_message; // Store in session
      } else {
        // No conflicts, proceed with enrollment process
        $_SESSION['enrollment_program_id'] = $program_id;
        header('Location: enrollment-process.php?program_id=' . $program_id);
        exit();
      }
    }
  }
}

// Generate CSRF token
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

// Handle success and error messages - ensure only one shows at a time
$success_message = '';
$error_message = '';

// Priority 1: Check for enrollment success (highest priority)
if (isset($_GET['success']) && $_GET['success'] === 'enrollment_confirmed') {
  if (isset($_SESSION['enrollment_success'])) {
    $enrollment_data = $_SESSION['enrollment_success'];
    $success_message = "Enrollment Confirmed! You have successfully enrolled in '{$enrollment_data['program_name']}'. {$enrollment_data['message']}";
    unset($_SESSION['enrollment_success']); // Clear the session data
  } else {
    $success_message = "Enrollment confirmed successfully! Your application is now pending review.";
  }
  
  // Clear any conflicting error parameters from URL
  if (isset($_GET['error'])) {
    // Redirect to clean URL with only success parameter
    header('Location: student-enrollment.php?success=enrollment_confirmed');
    exit();
  }
}
// Priority 2: Check for error messages from URL parameters (only if no success)
elseif (isset($_GET['error']) && empty($success_message)) {
  switch ($_GET['error']) {
    case 'missing_parameters':
      $error_message = "Enrollment Failed: Missing required enrollment parameters.";
      break;
    case 'program_not_found':
      $error_message = "Enrollment Failed: The requested program was not found.";
      break;
    case 'enrollment_not_eligible':
      $error_message = "Enrollment Failed: " . (isset($_GET['message']) ? urldecode($_GET['message']) : "You are not eligible to enroll in this program.");
      break;
    case 'schedule_conflict':
      $error_message = "Enrollment Failed: " . (isset($_GET['message']) ? urldecode($_GET['message']) : "Schedule conflict detected with your enrolled programs.");
      break;
    case 'database_error':
      $error_message = "Enrollment Failed: A database error occurred. Please try again.";
      break;
    default:
      $error_message = "Enrollment Failed: An error occurred. Please try again.";
      break;
  }
}
// Priority 3: Check for session-stored error messages (lowest priority)
elseif (empty($success_message) && empty($error_message) && isset($_SESSION['enrollment_error'])) {
  $error_message = "Enrollment Failed: " . $_SESSION['enrollment_error'];
  unset($_SESSION['enrollment_error']); // Clear after displaying
}

// Get filter parameters from URL
$status_filter = $_GET['status'] ?? 'all';
$modality_filter = $_GET['modality'] ?? '';
$search_filter = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9; // 3x3 grid

// Build filters array for getPrograms function
$filters = [];
if (!empty($status_filter) && $status_filter !== 'all') {
  $filters['status'] = $status_filter;
}
if (!empty($modality_filter)) {
  $filters['session_type'] = $modality_filter;
}
if (!empty($search_filter)) {
  $filters['search'] = $search_filter;
}

// Get programs with enrollment data for student
$programs_data = getStudentAvailablePrograms($user_id, $filters, $page, $per_page);
$programs = $programs_data['programs'];
$total_programs = $programs_data['total'];
$total_pages = ceil($total_programs / $per_page);

/**
 * Get available programs for student enrollment with enrollment status
 */
function getStudentAvailablePrograms($student_id, $filters = [], $page = 1, $per_page = 9)
{
  global $conn;

  try {
    $offset = ($page - 1) * $per_page;

    // Base query with enrollment status check
    $sql = "SELECT p.id, p.name, p.description, p.fee, p.status, p.age_group,
                   p.max_students, p.session_type, p.location, p.start_date, p.end_date,
                   p.start_time, p.end_time, p.days, p.difficulty_level, p.category,
                   p.duration_weeks, p.tutor_id, p.cover_image,
                   CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                   COUNT(e_count.id) as enrolled_count,
                   e_student.status as enrollment_status,
                   e_student.enrollment_date,
                   CASE 
                     WHEN p.end_date < CURDATE() THEN 'ended'
                     WHEN p.start_date <= CURDATE() AND p.end_date >= CURDATE() THEN 'ongoing'
                     WHEN p.start_date > CURDATE() THEN 'upcoming'
                     ELSE 'upcoming'
                   END as calculated_status
            FROM programs p
            LEFT JOIN users u ON p.tutor_id = u.id
            LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
            LEFT JOIN enrollments e_count ON p.id = e_count.program_id 
                     AND e_count.status IN ('pending', 'active')
            LEFT JOIN enrollments e_student ON p.id = e_student.program_id 
                     AND e_student.student_user_id = ?
            WHERE p.status = 'active'";

    $params = [$student_id];
    $param_types = "i";

    // Apply filters
    if (!empty($filters['status'])) {
      $status = $filters['status'];
      if ($status === 'upcoming') {
        $sql .= " AND p.start_date > CURDATE()";
      } elseif ($status === 'ongoing') {
        $sql .= " AND (p.start_date <= CURDATE() AND p.end_date >= CURDATE())";
      } elseif ($status === 'ended') {
        $sql .= " AND p.end_date < CURDATE()";
      }
    }

    if (!empty($filters['session_type'])) {
      $sql .= " AND p.session_type = ?";
      $params[] = $filters['session_type'];
      $param_types .= "s";
    }

    if (!empty($filters['search'])) {
      $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
      $search_term = '%' . $filters['search'] . '%';
      $params[] = $search_term;
      $params[] = $search_term;
      $params[] = $search_term;
      $param_types .= "sss";
    }

    $sql .= " GROUP BY p.id, p.name, p.description, p.fee, p.status, p.age_group,
                       p.max_students, p.session_type, p.location, p.start_date, p.end_date,
                       p.start_time, p.end_time, p.days, p.difficulty_level, p.category,
                       p.duration_weeks, p.tutor_id, p.cover_image, tp.first_name, tp.last_name,
                       e_student.status, e_student.enrollment_date
              ORDER BY p.start_date ASC, p.name ASC
              LIMIT ? OFFSET ?";

    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $programs = [];
    while ($row = $result->fetch_assoc()) {
      $programs[] = $row;
    }

    // Get total count for pagination
    $count_sql = "SELECT COUNT(DISTINCT p.id) as total
                  FROM programs p
                  LEFT JOIN users u ON p.tutor_id = u.id
                  LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
                  WHERE p.status = 'active'";

    $count_params = [];
    $count_param_types = "";

    // Apply same filters for count
    if (!empty($filters['status']) && is_array($filters['status'])) {
      $status_conditions = [];
      foreach ($filters['status'] as $status) {
        if ($status === 'upcoming') {
          $status_conditions[] = "p.start_date > CURDATE()";
        } elseif ($status === 'ongoing') {
          $status_conditions[] = "(p.start_date <= CURDATE() AND p.end_date >= CURDATE())";
        } elseif ($status === 'ended') {
          $status_conditions[] = "p.end_date < CURDATE()";
        }
      }
      if (!empty($status_conditions)) {
        $count_sql .= " AND (" . implode(" OR ", $status_conditions) . ")";
      }
    }

    if (!empty($filters['session_type'])) {
      $count_sql .= " AND p.session_type = ?";
      $count_params[] = $filters['session_type'];
      $count_param_types .= "s";
    }

    if (!empty($filters['search'])) {
      $count_sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
      $search_term = '%' . $filters['search'] . '%';
      $count_params[] = $search_term;
      $count_params[] = $search_term;
      $count_params[] = $search_term;
      $count_param_types .= "sss";
    }

    $count_stmt = $conn->prepare($count_sql);
    if (!empty($count_params)) {
      $count_stmt->bind_param($count_param_types, ...$count_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];

    return [
      'programs' => $programs,
      'total' => $total
    ];
  } catch (Exception $e) {
    error_log("Error fetching student available programs: " . $e->getMessage());
    return [
      'programs' => [],
      'total' => 0
    ];
  }
}

/**
 * Enroll student in a program
 */
function enrollStudent($student_id, $program_id)
{
  global $conn;

  try {
    // Check if student is already enrolled
    $check_sql = "SELECT id, status FROM enrollments 
                  WHERE student_user_id = ? AND program_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $student_id, $program_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();

    if ($existing) {
      return [
        'success' => false,
        'message' => 'You are already enrolled in this program. Status: ' . ucfirst($existing['status'])
      ];
    }

    // Check for schedule conflicts
    $conflict_result = checkScheduleConflict($student_id, $program_id);
    if ($conflict_result['has_conflict']) {
      $conflicting_programs = array_map(function($conflict) {
        return $conflict['program_name'];
      }, $conflict_result['conflicting_programs']);
      
      return [
        'success' => false,
        'message' => 'Schedule conflict detected! This program overlaps with your enrolled program(s): ' . 
                    implode(', ', $conflicting_programs) . 
                    '. Please choose a different program or contact support to resolve the conflict.'
      ];
    }

    // Check if program exists and is available with enhanced capacity checking
    $program_sql = "SELECT id, name, max_students, status, start_date, end_date,
                           (SELECT COUNT(DISTINCT id) FROM enrollments WHERE program_id = ? AND status IN ('pending', 'active')) as enrolled_count
                    FROM programs WHERE id = ? AND status = 'active'";
    $program_stmt = $conn->prepare($program_sql);
    $program_stmt->bind_param("ii", $program_id, $program_id);
    $program_stmt->execute();
    $program = $program_stmt->get_result()->fetch_assoc();

    if (!$program) {
      return [
        'success' => false,
        'message' => 'Program not found or not available for enrollment.'
      ];
    }

    // Enhanced capacity check with exact count
    $actual_capacity = (int)$program['max_students'];
    $actual_enrolled = (int)$program['enrolled_count'];
    
    if ($actual_enrolled >= $actual_capacity) {
      return [
        'success' => false,
        'message' => "Program is at full capacity ($actual_enrolled/$actual_capacity). No more spots available."
      ];
    }

    // Check if program has ended or is ongoing (only upcoming programs can be enrolled)
    $current_date = date('Y-m-d');
    if ($program['end_date'] && $program['end_date'] < $current_date) {
      return [
        'success' => false,
        'message' => 'This program has already ended. Enrollment is not available.'
      ];
    }

    // Check if program is ongoing (started but not ended)
    if (
      $program['start_date'] && $program['start_date'] <= $current_date &&
      (!$program['end_date'] || $program['end_date'] >= $current_date)
    ) {
      return [
        'success' => false,
        'message' => 'This program is already in progress. Enrollment is only available for upcoming programs.'
      ];
    }

    // Insert enrollment
    $insert_sql = "INSERT INTO enrollments (student_user_id, program_id, enrollment_date, status) 
                   VALUES (?, ?, CURDATE(), 'pending')";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ii", $student_id, $program_id);

    if ($insert_stmt->execute()) {
      return [
        'success' => true,
        'message' => 'Enrollment request submitted successfully!'
      ];
    } else {
      return [
        'success' => false,
        'message' => 'Failed to submit enrollment request. Please try again.'
      ];
    }
  } catch (Exception $e) {
    error_log("Error enrolling student: " . $e->getMessage());
    return [
      'success' => false,
      'message' => 'An error occurred while processing your enrollment request.'
    ];
  }
}

/**
 * Get status badge configuration for display
 */
function getStatusBadge($status)
{
  $statusConfigs = [
    'upcoming' => [
      'bg' => 'bg-blue-100',
      'text' => 'text-blue-800',
      'label' => 'Upcoming'
    ],
    'ongoing' => [
      'bg' => 'bg-green-100',
      'text' => 'text-green-800',
      'label' => 'Ongoing'
    ],
    'ended' => [
      'bg' => 'bg-gray-100',
      'text' => 'text-gray-800',
      'label' => 'Ended'
    ],
    'pending' => [
      'bg' => 'bg-yellow-100',
      'text' => 'text-yellow-800',
      'label' => 'Pending'
    ],
    'active' => [
      'bg' => 'bg-green-100',
      'text' => 'text-green-800',
      'label' => 'Enrolled'
    ]
  ];

  return $statusConfigs[$status] ?? $statusConfigs['upcoming'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Program Enrollment - Student Dashboard - TPLearn</title>
    <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'tplearn-green': '#10b981',
            'tplearn-light-green': '#34d399',
          }
        }
      }
    }

    // Store CSRF token for JavaScript use
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
  </script>
  <style>
    /* Status badge styles */
    .status-badge {
      font-size: 0.75rem;
      font-weight: 500;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      display: inline-flex;
      align-items: center;
    }

    /* Alert styles */
    .alert {
      padding: 1rem;
      border-radius: 0.5rem;
      margin-bottom: 1rem;
    }

    .alert-success {
      background-color: #d1fae5;
      border-color: #a7f3d0;
      color: #065f46;
    }

    .alert-error {
      background-color: #fee2e2;
      border-color: #fecaca;
      color: #991b1b;
    }

    /* Enhanced hover effects for program cards */
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

    /* Pulse animation for enrollment buttons */
    @keyframes pulse-green {
      0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
      70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
    }

    .btn-pulse:hover {
      animation: pulse-green 2s infinite;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/student-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/student-header-standard.php';
      renderStudentHeader('Program Enrollment', 'Enroll in available programs');
      ?>

      <!-- Main Content Area -->
      <main class="p-6">

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
          <div id="success-message" class="mb-6 bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-lg flex items-center transition-all duration-500 ease-in-out">
            <svg class="w-4 h-4 text-green-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <div class="flex-1">
              <p class="font-normal text-sm"><?php echo $success_message; ?></p>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
          <div id="error-message" class="mb-6 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg flex items-center transition-all duration-500 ease-in-out">
            <svg class="w-4 h-4 text-red-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <div class="flex-1">
              <p class="font-normal text-sm">
                <?php 
                // Format error message to make "Enrollment Failed" bold and clean up duplicates
                $formatted_message = $error_message;
                
                // Handle various duplicate patterns
                if (strpos($formatted_message, 'Enrollment Failed:') !== false) {
                  // Remove duplicate "Enrollment Failed:" patterns
                  $formatted_message = preg_replace('/Enrollment Failed:\s*Enrollment Failed:\s*/', 'Enrollment Failed: ', $formatted_message);
                  
                  // Replace single "Enrollment Failed:" with bold version
                  $formatted_message = preg_replace('/^Enrollment Failed:\s*/', '<strong>Enrollment Failed:</strong> ', $formatted_message);
                }
                
                // Also handle "Schedule Conflict Detected!" case
                if (strpos($formatted_message, 'Schedule Conflict Detected!') !== false) {
                  $formatted_message = preg_replace('/^Schedule Conflict Detected!\s*/', '<strong>Schedule Conflict Detected!</strong> ', $formatted_message);
                }
                
                echo $formatted_message; 
                ?>
              </p>
            </div>
          </div>
        <?php endif; ?>

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
                <div class="relative flex-shrink-0" style="min-width: 140px; z-index: 50;">
                  <select name="status" id="statusFilter" class="bg-white border border-gray-300 rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-full" onchange="this.form.submit()" style="position: relative; z-index: 50; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: none;">
                    <option value="all" <?php echo $status_filter === 'all' || empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
                    <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                  </select>
                  <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="currentColor" viewBox="0 0 20 20" style="z-index: 51;">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </div>

                <!-- Modality Filter -->
                <div class="relative flex-shrink-0" style="min-width: 140px; z-index: 40;">
                  <select name="modality" id="modalityFilter" class="bg-white border border-gray-300 rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-full" onchange="this.form.submit()" style="position: relative; z-index: 40; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: none;">
                    <option value="" <?php echo empty($modality_filter) ? 'selected' : ''; ?>>All Modalities</option>
                    <option value="online" <?php echo $modality_filter === 'online' ? 'selected' : ''; ?>>Online</option>
                    <option value="physical" <?php echo $modality_filter === 'physical' ? 'selected' : ''; ?>>In-Person</option>
                    <option value="hybrid" <?php echo $modality_filter === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                  </select>
                  <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="currentColor" viewBox="0 0 20 20" style="z-index: 41;">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>

              <!-- Clear Filters Button -->
              <a href="?" class="px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 hover:border-tplearn-green focus:outline-none focus:ring-2 focus:ring-green-400">
                Clear Filters
              </a>
            </form>
          </div>
        </div>

        <!-- Results Summary -->
        <div class="mb-6 flex justify-between items-center">
          <p class="text-sm text-gray-600">
            Showing <?php echo count($programs); ?> of <?php echo $total_programs; ?> programs
            <?php if (!empty($search_filter)): ?>
              for "<?php echo htmlspecialchars($search_filter); ?>"
            <?php endif; ?>
          </p>

          <?php if ($total_pages > 1): ?>
            <div class="text-sm text-gray-600">
              Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Programs Grid -->
        <?php if (empty($programs)): ?>
          <!-- Empty State -->
          <div class="text-center py-12">
            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
              <?= icon('academic-cap', '2xl muted') ?>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No programs available right now</h3>
            <p class="text-gray-500 mb-4">
              <?php if (!empty($search_filter) || !empty($modality_filter) || $status_filter !== 'all'): ?>
                Try adjusting your search criteria or filters.
              <?php else: ?>
                Check back later for new programs or contact our admissions team.
              <?php endif; ?>
            </p>
            <a href="?status=all" class="inline-flex items-center px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors">
              <?= iconWithSpacing('arrow-path', 'sm', 'secondary') ?>
              Reset Filters
            </a>
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($programs as $program): ?>
              <?php
              $status_badge = getStatusBadge($program['calculated_status']);
              $enrollment_badge = $program['enrollment_status'] ? getStatusBadge($program['enrollment_status']) : null;

              // Debug: Show what enrollment status we have
              // echo "<!-- DEBUG: Program ID=" . $program['id'] . ", enrollment_status = '" . ($program['enrollment_status'] ?? 'NULL') . "' -->";

              // Calculate enrollment percentage
              $enrollment_percentage = $program['max_students'] > 0 ?
                min(100, ($program['enrolled_count'] / $program['max_students']) * 100) : 0;

              // Format schedule
              $start_time = $program['start_time'] ?
                date('g:i A', strtotime($program['start_time'])) : '9:00 AM';
              $end_time = $program['end_time'] ?
                date('g:i A', strtotime($program['end_time'])) : '10:00 AM';

              // Determine if enrollment is available - Only upcoming programs can be enrolled
              // Use consistent enrollment status from the same source as the badge
              $is_enrolled = !empty($program['enrollment_status']);
              $has_capacity = $program['enrolled_count'] < $program['max_students'];

              $can_enroll = !$is_enrolled &&
                $program['calculated_status'] === 'upcoming' &&
                $has_capacity;

              // Category gradients to match admin design
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
                      <span class="inline-block <?= $status_badge['bg'] ?> <?= $status_badge['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm transform group-hover:scale-105 transition-transform duration-300">
                        <?= htmlspecialchars($status_badge['label']) ?>
                      </span>
                    </div>
                    
                    <!-- Enrollment Status Badge (only show for non-ended programs) -->
                    <?php if ($enrollment_badge && $program['calculated_status'] !== 'ended'): ?>
                      <div class="absolute top-4 right-4">
                        <span class="inline-block <?= $enrollment_badge['bg'] ?> <?= $enrollment_badge['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm transform group-hover:scale-105 transition-transform duration-300">
                          <?= htmlspecialchars($enrollment_badge['label']) ?>
                        </span>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <!-- Use gradient background (fallback) -->
                  <div class="h-48 bg-gradient-to-br <?= $gradient ?> relative rounded-t-lg transition-all duration-300 group-hover:bg-gradient-to-bl">
                    <div class="absolute inset-0 bg-black bg-opacity-10 rounded-t-lg group-hover:bg-opacity-20 transition-all duration-300"></div>
                    <div class="absolute top-4 left-4">
                      <span class="inline-block <?= $status_badge['bg'] ?> <?= $status_badge['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm transform group-hover:scale-105 transition-transform duration-300">
                        <?= htmlspecialchars($status_badge['label']) ?>
                      </span>
                    </div>

                    <!-- Enrollment Status Badge (only show for non-ended programs) -->
                    <?php if ($enrollment_badge && $program['calculated_status'] !== 'ended'): ?>
                      <div class="absolute top-4 right-4">
                        <span class="inline-block <?= $enrollment_badge['bg'] ?> <?= $enrollment_badge['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm transform group-hover:scale-105 transition-transform duration-300">
                          <?= htmlspecialchars($enrollment_badge['label']) ?>
                        </span>
                      </div>
                    <?php endif; ?>

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
                      <span class="font-medium capitalize"><?= htmlspecialchars($program['session_type'] ?? 'Online') ?></span>
                      <span class="mx-2">•</span>
                      <span><?= htmlspecialchars($program['location'] ?? 'Online') ?></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                      <?= iconWithSpacing('clock', 'sm', 'secondary') ?>
                      <?php
                      // Calculate duration from duration_weeks or dates
                      $durationText = '8 weeks'; // Default
                      if (!empty($program['duration_weeks'])) {
                        $durationText = (int)$program['duration_weeks'] . ' weeks';
                      } elseif (!empty($program['start_date']) && !empty($program['end_date'])) {
                        $startDate = new DateTime($program['start_date']);
                        $endDate = new DateTime($program['end_date']);
                        $interval = $startDate->diff($endDate);
                        $weeks = floor($interval->days / 7);
                        $durationText = $weeks > 0 ? $weeks . ' weeks' : $interval->days . ' days';
                      }
                      ?>
                      <?= $durationText ?>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                      <?php
                      // Format time properly - use same logic as the $start_time and $end_time calculated above
                      $days = $program['days'] ?? 'Mon, Wed, Fri';
                      ?>
                      <?= htmlspecialchars($days) ?> • <?= $start_time ?>-<?= $end_time ?>
                    </div>
                    <div class="flex items-center text-sm text-gray-600 group-hover:text-gray-700 transition-colors duration-300">
                      <?= iconWithSpacing('users', 'sm', 'secondary') ?>
                      <?= $program['enrolled_count'] ?>/<?= $program['max_students'] ?> students
                      <?php
                      $enrollment_percentage = $program['max_students'] > 0 ?
                        ($program['enrolled_count'] / $program['max_students']) * 100 : 0;
                      $available_slots = $program['max_students'] - $program['enrolled_count'];
                      ?>
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2 group-hover:bg-gray-300 transition-colors duration-300">
                        <div class="bg-tplearn-green h-2 rounded-full group-hover:bg-green-500 transition-all duration-300" style="width: <?= min($enrollment_percentage, 100) ?>%"></div>
                      </div>
                      <?php if ($available_slots <= 3 && $available_slots > 0): ?>
                        <span class="ml-2 text-xs text-orange-600 font-medium group-hover:text-orange-700 transition-colors duration-300">
                          <?= $available_slots ?> left
                        </span>
                      <?php elseif ($available_slots <= 0): ?>
                        <span class="ml-2 text-xs text-red-600 font-medium group-hover:text-red-700 transition-colors duration-300">Full</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Program Description -->
                  <p class="text-sm text-gray-600 mb-4 group-hover:text-gray-700 transition-colors duration-300"><?= htmlspecialchars(substr($program['description'] ?? 'No description available.', 0, 120)) ?><?= strlen($program['description'] ?? '') > 120 ? '...' : '' ?></p>

                  <!-- Price and Action -->
                  <div class="flex items-center justify-between">
                    <div class="text-xl font-bold text-tplearn-green group-hover:text-green-600 transition-colors duration-300">
                      ₱<?= number_format((float)($program['fee'] ?? 0), 0) ?>
                    </div>

                    <div class="flex items-center space-x-2">
                      <!-- View Details button for all programs -->
                      <button onclick="viewProgramDetails(<?php echo $program['id']; ?>)"
                        class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 hover:shadow-md transform hover:scale-105 transition-all duration-200"
                        title="View program details">
                        <?= actionIcon('eye') ?>
                      </button>

                      <!-- Main action button -->
                      <?php if ($can_enroll): ?>
                        <form method="POST" class="inline">
                          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                          <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                          <button type="submit" name="enroll_program"
                            class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 hover:shadow-lg transform hover:scale-105 transition-all duration-200 btn-glow btn-pulse"
                            onclick="handleConfirmClick(this, 'Confirm Action', 'Are you sure you want to enroll in this program?')">
                            Enroll Now
                          </button>
                        </form>
                      <?php elseif ($is_enrolled): ?>
                        <?php
                        // Map enrollment status display names
                        $status_display = $program['enrollment_status'];
                        if ($status_display === 'cancelled') {
                          $status_display = 'inactive';
                        }
                        
                        // Set appropriate CSS classes based on status
                        $status_classes = 'px-4 py-2 rounded-lg text-sm font-medium transform hover:scale-105 transition-all duration-200';
                        if (in_array($program['enrollment_status'], ['active', 'completed'])) {
                          $status_classes .= ' bg-green-100 text-green-800 hover:bg-green-200';
                        } elseif ($program['enrollment_status'] === 'pending') {
                          $status_classes .= ' bg-yellow-100 text-yellow-800 hover:bg-yellow-200';
                        } elseif ($program['enrollment_status'] === 'cancelled') {
                          $status_classes .= ' bg-gray-100 text-gray-800 hover:bg-gray-200';
                        } else {
                          $status_classes .= ' bg-blue-100 text-blue-800 hover:bg-blue-200';
                        }
                        ?>
                        <span class="<?php echo $status_classes; ?>">
                          <?php echo ucfirst($status_display); ?>
                        </span>
                      <?php elseif (!$has_capacity): ?>
                        <span class="bg-red-100 text-red-800 px-4 py-2 rounded-lg text-sm font-medium"
                          title="<?php echo $program['enrolled_count']; ?>/<?php echo $program['max_students']; ?> enrolled">
                          Full (<?php echo $program['enrolled_count']; ?>/<?php echo $program['max_students']; ?>)
                        </span>
                      <?php elseif ($program['calculated_status'] === 'ongoing'): ?>
                        <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
                          In Progress
                        </span>
                      <?php elseif ($program['calculated_status'] === 'ended'): ?>
                        <span class="bg-gray-100 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">
                          Ended
                        </span>
                      <?php else: ?>
                        <button onclick="viewProgramDetails(<?php echo $program['id']; ?>)"
                          class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition-colors">
                          Learn More
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

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

  <script>
    // Mobile menu functionality
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileCloseButton = document.getElementById('mobile-close-button');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-menu-overlay');

    function openMobileMenu() {
      if (sidebar) sidebar.classList.remove('-translate-x-full');
      if (overlay) overlay.classList.remove('hidden');
    }

    function closeMobileMenu() {
      if (sidebar) sidebar.classList.add('-translate-x-full');
      if (overlay) overlay.classList.add('hidden');
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

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
      setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => {
          alert.remove();
        }, 300);
      }, 5000);
    });

    // Clear filters functionality
    function clearFilters() {
      const url = new URL(window.location);
      url.searchParams.delete('search');
      url.searchParams.delete('status');
      url.searchParams.delete('modality');
      url.searchParams.delete('page');
      window.location.href = url.toString();
    }

    // Modal functionality for program details
    function viewProgramDetails(programId) {
      console.log('=== MODAL DEBUG ===');
      console.log('Program ID:', programId);
      console.log('===================');

      // Fetch program data and populate modal - include student ID for enrollment status
      fetch(`../../api/programs_crud.php?action=get&id=${programId}&student_id=<?php echo $_SESSION['user_id']; ?>`)
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
      console.log('=== POPULATE MODAL DEBUG ===');
      console.log('Program data:', program);
      console.log('API Enrollment Status:', program.enrollment_status);
      console.log('Type:', typeof program.enrollment_status);
      console.log('============================');
      try {
        // Set title
        safeSetContent('programDetailsTitle', program.name || 'Untitled Program');

        // Set header gradient based on category
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
        document.getElementById('programDetailsHeader').className = `mb-6 relative h-48 rounded-lg overflow-hidden bg-gradient-to-br ${gradient}`;

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
        safeSetContent('programDetailsFee', `₱${Number(program.fee || 0).toLocaleString('en-US', {maximumFractionDigits: 0})}`);

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

        // Handle action button based on program status and enrollment eligibility
        const actionContainer = document.getElementById('programDetailsAction');
        if (actionContainer) {
          console.log('=== ACTION BUTTON DEBUG ===');
          console.log('API Enrollment Status:', program.enrollment_status);
          console.log('Truthy check:', !!program.enrollment_status);
          console.log('===========================');

          // Check if student is already enrolled (from API data)
          if (program.enrollment_status) {
            console.log('Showing enrollment status badge:', program.enrollment_status);
            
            // Map enrollment status display names
            let statusDisplay = program.enrollment_status;
            if (statusDisplay === 'cancelled') {
              statusDisplay = 'inactive';
            }
            
            // Set appropriate CSS classes based on status
            let statusClasses = 'px-4 py-2 rounded-lg text-sm font-medium';
            if (['active', 'completed'].includes(program.enrollment_status)) {
              statusClasses += ' bg-green-100 text-green-800';
            } else if (program.enrollment_status === 'pending') {
              statusClasses += ' bg-yellow-100 text-yellow-800';
            } else if (program.enrollment_status === 'cancelled') {
              statusClasses += ' bg-gray-100 text-gray-800';
            } else {
              statusClasses += ' bg-blue-100 text-blue-800';
            }
            
            // Student is already enrolled
            actionContainer.innerHTML = `
              <span class="${statusClasses}">
                ${statusDisplay.charAt(0).toUpperCase() + statusDisplay.slice(1)}
              </span>
            `;
          } else if (enrolledCount >= maxStudents) {
            // Program is full
            actionContainer.innerHTML = `
              <span class="bg-red-100 text-red-800 px-4 py-2 rounded-lg text-sm font-medium">
                Full
              </span>
            `;
          } else if (program.calculated_status === 'ongoing') {
            // Program is in progress
            actionContainer.innerHTML = `
              <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
                In Progress
              </span>
            `;
          } else if (program.calculated_status === 'ended') {
            // Program has ended
            actionContainer.innerHTML = `
              <span class="bg-gray-100 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">
                Ended
              </span>
            `;
          } else if (program.calculated_status === 'upcoming') {
            // Can enroll - show enrollment form
            actionContainer.innerHTML = `
              <form method="POST" class="inline">
                <input type="hidden" name="csrf_token" value="${window.csrfToken}">
                <input type="hidden" name="program_id" value="${program.id}">
                <button type="submit" name="enroll_program"
                  class="bg-tplearn-green text-white px-6 py-3 rounded-lg font-medium hover:bg-green-600 transition-colors"
                  onclick="handleConfirmClick(this, 'Confirm Action', 'Are you sure you want to enroll in this program?')">
                  Enroll Now
                </button>
              </form>
            `;
          }
        }

      } catch (error) {
        console.error('Error populating modal:', error);
        TPAlert.error('Error', 'Error displaying program details. Please try again.');
      }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
      const modal = document.getElementById('programDetailsModal');
      if (event.target === modal) {
        closeProgramDetailsModal();
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        const modal = document.getElementById('programDetailsModal');
        if (!modal.classList.contains('hidden')) {
          closeProgramDetailsModal();
        }
      }
    });

    // Auto-hide messages after 5 seconds
    function hideMessage(messageId) {
      const messageElement = document.getElementById(messageId);
      if (messageElement) {
        messageElement.style.opacity = '0';
        messageElement.style.transform = 'translateY(-10px)';
        setTimeout(() => {
          messageElement.style.display = 'none';
        }, 500);
      }
    }

    // Auto-hide messages when page loads
    document.addEventListener('DOMContentLoaded', function() {
      const successMessage = document.getElementById('success-message');
      const errorMessage = document.getElementById('error-message');
      
      if (successMessage) {
        setTimeout(() => {
          hideMessage('success-message');
        }, 5000); // Hide after 5 seconds
      }
      
      if (errorMessage) {
        setTimeout(() => {
          hideMessage('error-message');
        }, 7000); // Hide error messages after 7 seconds (give more time to read)
      }
    });
  </script>

  <!-- Program Details Modal -->
  <div id="programDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
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

        <!-- Enrollment Actions -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-semibold text-gray-900">Ready to enroll?</h4>
              <p class="text-sm text-gray-600">Join this program and start your learning journey!</p>
            </div>
            <div id="programDetailsAction">
              <!-- Action button will be inserted here -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Handle confirmation clicks for enrollment
    function handleConfirmClick(button, title, message) {
      // Prevent default form submission
      event.preventDefault();
      
      // Show confirmation dialog
      if (confirm(message)) {
        // If confirmed, submit the form
        button.closest('form').submit();
      }
      // If not confirmed, do nothing (form won't submit)
    }

    // Notification and message functions are handled by header.php
    // No need for custom functions here

    // Mobile menu functionality (if exists)
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    if (mobileMenuButton) {
      mobileMenuButton.addEventListener('click', function() {
        console.log('Mobile menu clicked');
      });
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
      if (event.target.classList.contains('fixed') && event.target.classList.contains('bg-black')) {
        event.target.remove();
      }
    });
  </script>
</body>

</html>