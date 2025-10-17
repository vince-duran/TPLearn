<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
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

    // Store program ID in session and redirect to enrollment process
    $_SESSION['enrollment_program_id'] = $program_id;
    header('Location: enrollment-process.php?program_id=' . $program_id);
    exit();
  }
}

// Generate CSRF token
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

// Handle success messages from URL parameters or session
$success_message = '';
$error_message = '';

// Check for enrollment success
if (isset($_GET['success']) && $_GET['success'] === 'enrollment_confirmed') {
  // Clear any error messages when we have success
  $error_message = '';

  if (isset($_SESSION['enrollment_success'])) {
    $enrollment_data = $_SESSION['enrollment_success'];
    $success_message = "🎉 Enrollment Confirmed! You have successfully enrolled in '{$enrollment_data['program_name']}'. {$enrollment_data['message']}";
    unset($_SESSION['enrollment_success']); // Clear the session data
  } else {
    $success_message = "🎉 Enrollment confirmed successfully! Your application is now pending review.";
  }
}
// Check for error messages from URL parameters (only if no success message)
elseif (isset($_GET['error'])) {
  switch ($_GET['error']) {
    case 'missing_parameters':
      $error_message = "Missing required enrollment parameters.";
      break;
    case 'program_not_found':
      $error_message = "The requested program was not found.";
      break;
    case 'enrollment_not_eligible':
      $error_message = isset($_GET['message']) ? urldecode($_GET['message']) : "You are not eligible to enroll in this program.";
      break;
    case 'database_error':
      $error_message = "A database error occurred. Please try again.";
      break;
    default:
      $error_message = "An error occurred. Please try again.";
      break;
  }
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
                   p.duration_weeks, p.tutor_id,
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
                       p.duration_weeks, p.tutor_id, tp.first_name, tp.last_name,
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

    // Check if program exists and is available
    $program_sql = "SELECT id, name, max_students, status, start_date, end_date,
                           (SELECT COUNT(*) FROM enrollments WHERE program_id = ? AND status IN ('pending', 'active')) as enrolled_count
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

    // Check if program is full
    if ($program['enrolled_count'] >= $program['max_students']) {
      return [
        'success' => false,
        'message' => 'Program is full. No more spots available.'
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
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/student-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/header.php';
      renderHeader(
        'Program Enrollment',
        '',
        'student',
        $display_name,
        [], // notifications array - to be implemented
        []  // messages array - to be implemented
      );
      ?>

      <!-- Main Content Area -->
      <main class="p-6">

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
          <div class="alert alert-success">
            <?= iconWithSpacing('check-circle', 'md', 'success', 'mr-2') ?>
            <?php echo htmlspecialchars($success_message); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
          <div class="alert alert-error">
            <?= iconWithSpacing('exclamation-triangle', 'md', 'warning', 'mr-2') ?>
            <?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>

        <!-- Search and Filter Bar -->
        <div class="mb-6">
          <form method="GET" class="flex flex-col sm:flex-row gap-4">
            <!-- Search Input -->
            <div class="flex-1 relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <?= actionIcon('magnifying-glass', 'md', 'muted') ?>
              </div>
              <input type="text" name="search" value="<?php echo htmlspecialchars($search_filter); ?>"
                placeholder="Search programs..."
                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm placeholder-gray-500 focus:ring-2 focus:ring-tplearn-green focus:border-tplearn-green">
            </div>

            <!-- Status Filter -->
            <div class="relative">
              <select name="status" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 text-sm focus:ring-2 focus:ring-tplearn-green focus:border-tplearn-green">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Programs</option>
                <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                <?= actionIcon('chevron-down', 'sm', 'muted') ?>
              </div>
            </div>

            <!-- Modality Filter -->
            <div class="relative">
              <select name="modality" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 text-sm focus:ring-2 focus:ring-tplearn-green focus:border-tplearn-green">
                <option value="">All Modalities</option>
                <option value="online" <?php echo $modality_filter === 'online' ? 'selected' : ''; ?>>Online</option>
                <option value="physical" <?php echo $modality_filter === 'physical' ? 'selected' : ''; ?>>In-Person</option>
                <option value="hybrid" <?php echo $modality_filter === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                <?= actionIcon('chevron-down', 'sm', 'muted') ?>
              </div>
            </div>

            <!-- Search Button -->
            <button type="submit" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors">
              <?= iconWithSpacing('magnifying-glass', 'sm', 'secondary', 'mr-1') ?>
              Search
            </button>
          </form>
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

              <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-100">
                <!-- Program Image -->
                <div class="h-48 bg-gradient-to-br <?= $gradient ?> relative rounded-t-lg">
                  <div class="absolute inset-0 bg-black bg-opacity-10 rounded-t-lg"></div>
                  <div class="absolute top-4 left-4">
                    <span class="inline-block <?= $status_badge['bg'] ?> <?= $status_badge['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm">
                      <?= htmlspecialchars($status_badge['label']) ?>
                    </span>
                  </div>

                  <!-- Enrollment Status Badge -->
                  <?php if ($enrollment_badge): ?>
                    <div class="absolute top-4 right-4">
                      <span class="inline-block <?= $enrollment_badge['bg'] ?> <?= $enrollment_badge['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm">
                        <?= htmlspecialchars($enrollment_badge['label']) ?>
                      </span>
                    </div>
                  <?php endif; ?>

                  <!-- Book Icon -->
                  <div class="absolute inset-0 flex items-center justify-center">
                    <?= icon('book-open', '3xl text-white opacity-80') ?>
                  </div>
                </div>

                <!-- Program Details -->
                <div class="p-6">
                  <h3 class="text-lg font-semibold text-gray-900 mb-3 line-clamp-2"><?= htmlspecialchars($program['name']) ?></h3>

                  <!-- Program Info -->
                  <div class="space-y-3 mb-4">
                    <div class="flex items-center text-sm text-gray-600">
                      <?= iconWithSpacing('user', 'sm', 'secondary') ?>
                      <?= htmlspecialchars($program['age_group'] ?? 'All Ages') ?>
                    </div>
                    <div class="flex items-center text-sm text-blue-600">
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
                    <div class="flex items-center text-sm text-gray-600">
                      <?= iconWithSpacing('users', 'sm', 'secondary') ?>
                      <?= $program['enrolled_count'] ?>/<?= $program['max_students'] ?> students
                      <?php
                      $enrollment_percentage = $program['max_students'] > 0 ?
                        ($program['enrolled_count'] / $program['max_students']) * 100 : 0;
                      $available_slots = $program['max_students'] - $program['enrolled_count'];
                      ?>
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                        <div class="bg-tplearn-green h-2 rounded-full" style="width: <?= min($enrollment_percentage, 100) ?>%"></div>
                      </div>
                      <?php if ($available_slots <= 3 && $available_slots > 0): ?>
                        <span class="ml-2 text-xs text-orange-600 font-medium">
                          <?= $available_slots ?> left
                        </span>
                      <?php elseif ($available_slots <= 0): ?>
                        <span class="ml-2 text-xs text-red-600 font-medium">Full</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Program Description -->
                  <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars(substr($program['description'] ?? 'No description available.', 0, 120)) ?><?= strlen($program['description'] ?? '') > 120 ? '...' : '' ?></p>

                  <!-- Price and Action -->
                  <div class="flex items-center justify-between">
                    <div class="text-xl font-bold text-tplearn-green">
                      ₱<?= number_format((float)($program['fee'] ?? 0), 0) ?>
                    </div>

                    <div class="flex items-center space-x-2">
                      <!-- View Details button for all programs -->
                      <button onclick="viewProgramDetails(<?php echo $program['id']; ?>)"
                        class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors"
                        title="View program details">
                        <?= actionIcon('eye') ?>
                      </button>

                      <!-- Main action button -->
                      <?php if ($can_enroll): ?>
                        <form method="POST" class="inline">
                          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                          <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                          <button type="submit" name="enroll_program"
                            class="bg-tplearn-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition-colors"
                            onclick="return confirm('Are you sure you want to enroll in this program?')">
                            Enroll Now
                          </button>
                        </form>
                      <?php elseif ($is_enrolled): ?>
                        <span class="bg-green-100 text-green-800 px-4 py-2 rounded-lg text-sm font-medium">
                          <?php echo ucfirst($program['enrollment_status']); ?>
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
          alert(`Error loading program details: ${error.message}. Please try again.`);
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
            // Student is already enrolled
            actionContainer.innerHTML = `
              <span class="bg-green-100 text-green-800 px-4 py-2 rounded-lg text-sm font-medium">
                ${program.enrollment_status.charAt(0).toUpperCase() + program.enrollment_status.slice(1)}
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
                  onclick="return confirm('Are you sure you want to enroll in this program?')">
                  Enroll Now
                </button>
              </form>
            `;
          }
        }

      } catch (error) {
        console.error('Error populating modal:', error);
        alert('Error displaying program details. Please try again.');
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
</body>

</html>