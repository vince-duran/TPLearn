<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
require_once '../../assets/icons.php';
requireRole('student');

// Get program ID from URL parameter or session
$program_id = $_GET['program_id'] ?? $_SESSION['enrollment_program_id'] ?? null;

if (!$program_id) {
  header('Location: student-enrollment.php');
  exit();
}

// Get program data from database
$program_sql = "
    SELECT p.*, 
           COUNT(e.id) as enrolled_count,
           CASE 
               WHEN p.start_date > CURDATE() THEN 'upcoming'
               WHEN p.start_date <= CURDATE() AND (p.end_date IS NULL OR p.end_date >= CURDATE()) THEN 'ongoing'
               WHEN p.end_date < CURDATE() THEN 'ended'
               ELSE 'upcoming'
           END as calculated_status
    FROM programs p
    LEFT JOIN enrollments e ON p.id = e.program_id AND e.status IN ('pending', 'active')
    WHERE p.id = ? AND p.status = 'active'
    GROUP BY p.id
";

$stmt = $conn->prepare($program_sql);
$stmt->bind_param("i", $program_id);
$stmt->execute();
$result = $stmt->get_result();
$program = $result->fetch_assoc();

if (!$program) {
  $_SESSION['error_message'] = "Program not found or not available for enrollment.";
  header('Location: student-enrollment.php');
  exit();
}

// Check if program is eligible for enrollment
if ($program['calculated_status'] !== 'upcoming') {
  $_SESSION['error_message'] = "This program is not available for enrollment.";
  header('Location: student-enrollment.php');
  exit();
}

// Check if program is full
if ($program['enrolled_count'] >= $program['max_students']) {
  $_SESSION['error_message'] = "This program is full.";
  header('Location: student-enrollment.php');
  exit();
}

// Store program data in session for use in next steps
$_SESSION['enrollment_program'] = $program;

// Get current user data from session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? 'Student';

// Get student data for display name
$student_data = getStudentDashboardData($user_id);
$display_name = $student_data['name'] ?? $user_name;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Enrollment - <?php echo htmlspecialchars($program['name']); ?> - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* Custom styles */
    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 14px;
    }

    .step-active {
      background-color: #10b981;
      color: white;
    }

    .step-completed {
      background-color: #10b981;
      color: white;
    }

    .step-inactive {
      background-color: #e5e7eb;
      color: #6b7280;
    }

    .step-line {
      height: 2px;
      flex: 1;
      margin: 0 1rem;
    }

    .step-line-active {
      background-color: #10b981;
    }

    .step-line-inactive {
      background-color: #e5e7eb;
    }

    .payment-option {
      cursor: pointer;
      transition: all 0.2s ease;
      border: 2px solid #e5e7eb;
    }

    .payment-option:hover {
      border-color: #10b981;
      background-color: #f0fdf4;
    }

    .payment-option.selected {
      border-color: #10b981;
      background-color: #f0fdf4;
    }

    .payment-option.selected::before {
      content: '✓';
      position: absolute;
      top: 1rem;
      right: 1rem;
      color: #10b981;
      font-weight: bold;
      font-size: 1.2rem;
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
      // Direct header call using variables already defined
      require_once '../../includes/header.php';
      
      // Use variables that are already defined above
      $notifications = getUserNotifications($user_id, 10);
      
      renderHeader(
        'Enrollment',
        'Complete your program enrollment',
        'student',
        $display_name,
        $notifications,
        []
      );
      ?>

      <!-- Main Content Area -->
      <main class="p-6 max-w-4xl mx-auto">
        <!-- Progress Steps -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-6">
          <div class="flex items-center justify-between">
            <!-- Step 1 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-completed">1</div>
              <span class="text-xs mt-2 text-center font-medium text-gray-700">Program Details</span>
            </div>

            <!-- Line 1 -->
            <div class="step-line step-line-active"></div>

            <!-- Step 2 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-active">2</div>
              <span class="text-xs mt-2 text-center font-medium text-tplearn-green">Payment Options</span>
            </div>

            <!-- Line 2 -->
            <div class="step-line step-line-inactive"></div>

            <!-- Step 3 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-inactive">3</div>
              <span class="text-xs mt-2 text-center font-medium text-gray-500">Payment Method</span>
            </div>

            <!-- Line 3 -->
            <div class="step-line step-line-inactive"></div>

            <!-- Step 4 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-inactive">4</div>
              <span class="text-xs mt-2 text-center font-medium text-gray-500">Confirmation</span>
            </div>
          </div>
        </div>

        <!-- Payment Options -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-2">Payment Options</h2>
          <p class="text-gray-600 mb-2">Program: <strong><?php echo htmlspecialchars($program['name']); ?></strong></p>
          <p class="text-gray-600 mb-8">Please select a payment option:</p>

          <!-- Option 1: Full Payment -->
          <div class="payment-option relative rounded-lg p-6 mb-4" onclick="selectPaymentOption(1)">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Option 1</h3>
            <h4 class="text-md font-medium text-gray-800 mb-2">Full Payment</h4>
            <p class="text-2xl font-bold text-tplearn-green mb-2">₱<?php echo number_format($program['fee']); ?></p>
            <p class="text-sm text-gray-600">Pay the full amount now and complete your enrollment.</p>
          </div>

          <!-- Option 2: Two Payments -->
          <div class="payment-option relative rounded-lg p-6 mb-4" onclick="selectPaymentOption(2)">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Option 2</h3>
            <h4 class="text-md font-medium text-gray-800 mb-2">Two Payments</h4>
            <p class="text-lg font-bold text-tplearn-green mb-2">
              <?php
              require_once '../../includes/data-helpers.php';
              $amounts2 = calculateExactInstallments($program['fee'], 2);
              echo '₱' . number_format($amounts2[0], 0) . ' + ₱' . number_format($amounts2[1], 0);
              ?>
            </p>
            <p class="text-sm text-gray-600">Balance due after 2 weeks</p>
          </div>

          <!-- Option 3: Three Payments -->
          <div class="payment-option relative rounded-lg p-6 mb-8" onclick="selectPaymentOption(3)">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Option 3</h3>
            <h4 class="text-md font-medium text-gray-800 mb-2">Three Payments</h4>
            <p class="text-lg font-bold text-tplearn-green mb-2">
              <?php
              $amounts3 = calculateExactInstallments($program['fee'], 3);
              echo '₱' . number_format($amounts3[0], 0) . ' + ₱' . number_format($amounts3[1], 0) . ' + ₱' . number_format($amounts3[2], 0);
              ?>
            </p>
            <p class="text-sm text-gray-600">Staggered payments</p>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-between items-center pt-6 border-t border-gray-200">
            <button onclick="goBack()" class="flex items-center px-6 py-3 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
              </svg>
              Back
            </button>

            <button id="continueBtn" onclick="continueToPayment()" disabled class="flex items-center px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed transition-all duration-200">
              Continue
              <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script>
    let selectedOption = null;

    function selectPaymentOption(option) {
      // Remove previous selection
      document.querySelectorAll('.payment-option').forEach(el => {
        el.classList.remove('selected');
      });

      // Add selection to clicked option
      event.currentTarget.classList.add('selected');
      selectedOption = option;

      // Enable continue button
      const continueBtn = document.getElementById('continueBtn');
      continueBtn.disabled = false;
      continueBtn.className = 'flex items-center px-6 py-3 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-all duration-200';
    }

    function continueToPayment() {
      if (selectedOption) {
        // Store the selected payment option in session and redirect to payment method page
        window.location.href = `payment-method.php?program_id=<?php echo $program['id']; ?>&option=${selectedOption}`;
      }
    }

    function goBack() {
      // Redirect back to enrollment page
      window.location.href = 'student-enrollment.php';
    }

    // Notification and message functions are handled by header.php
    // No need for custom functions here

    // Mobile menu functionality
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
      console.log('Mobile menu clicked');
    });

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
      if (event.target.classList.contains('fixed') && event.target.classList.contains('bg-black')) {
        event.target.remove();
      }
    });

  </script>
  
  <!-- Notification JavaScript is included by renderHeader() -->
</body>

</html>