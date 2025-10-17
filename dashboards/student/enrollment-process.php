<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
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
      <!-- Top Header -->
      <header class="bg-white shadow-sm border-b border-gray-200 px-4 lg:px-6 py-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <!-- Mobile menu button -->
            <button id="mobile-menu-button" class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-tplearn-green mr-3">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <div>
              <h1 class="text-xl lg:text-2xl font-bold text-gray-800">Enrollment</h1>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Notifications -->
            <div class="relative">
              <button onclick="openNotifications()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
                </svg>
              </button>
              <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">1</span>
            </div>

            <!-- Messages -->
            <div class="relative">
              <button onclick="openMessages()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                  <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                </svg>
              </button>
              <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
            </div>

            <!-- Profile -->
            <div class="flex items-center space-x-2">
              <span class="text-sm font-medium text-gray-700">Maria Santos</span>
              <div class="w-8 h-8 bg-tplearn-green rounded-full flex items-center justify-center text-white font-semibold text-sm">
                M
              </div>
            </div>
          </div>
        </div>
      </header>

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

    // Notification functions
    function openNotifications() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Notifications</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-blue-50 rounded-lg">
              <p class="text-sm text-blue-800">New program available: Reading Fundamentals</p>
              <p class="text-xs text-blue-600 mt-1">2 hours ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    function openMessages() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Messages</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Welcome to TPLearn! Please complete your profile.</p>
              <p class="text-xs text-gray-600 mt-1">1 day ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Your enrollment application is under review.</p>
              <p class="text-xs text-gray-600 mt-1">2 days ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">New semester starts next month. Prepare your documents.</p>
              <p class="text-xs text-gray-600 mt-1">3 days ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

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
</body>

</html>