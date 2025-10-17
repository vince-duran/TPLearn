<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/data-helpers.php';
requireRole('student');

// Get parameters from URL
$programId = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
$paymentOption = isset($_GET['option']) ? $_GET['option'] : '1';
$paymentMethod = isset($_GET['method']) ? $_GET['method'] : 'gcash';
$referenceNumber = isset($_GET['reference']) ? trim($_GET['reference']) : null;

// Validate required parameters
if (!$programId || !$paymentOption || !$paymentMethod || !$referenceNumber) {
  header('Location: student-enrollment.php?error=missing_parameters');
  exit();
}

// Check enrollment eligibility (capacity and duplicate enrollment)
$eligibilityCheck = validateEnrollmentEligibility($_SESSION['user_id'], $programId);
if (!$eligibilityCheck['eligible']) {
  $errorParam = urlencode($eligibilityCheck['reason']);
  header("Location: student-enrollment.php?error=enrollment_not_eligible&message={$errorParam}");
  exit();
}

// Get program data from database
$db = new Database($conn);
try {
  $programQuery = "SELECT * FROM programs WHERE id = ? AND status = 'active'";
  $programData = $db->getRow($programQuery, [$programId], 'i');

  if (!$programData) {
    header('Location: student-enrollment.php?error=program_not_found');
    exit();
  }

  // Format program data for display
  $currentProgram = [
    'id' => $programData['id'],
    'name' => $programData['name'],
    'price' => $programData['fee'],
    'description' => $programData['description'],
    'duration_weeks' => $programData['duration_weeks']
  ];
} catch (Exception $e) {
  error_log("Error fetching program data: " . $e->getMessage());
  header('Location: student-enrollment.php?error=database_error');
  exit();
}

// Calculate payment details based on option
$paymentPlanText = '';
$initialPayment = $currentProgram['price'];
$totalFee = $currentProgram['price'];

switch ($paymentOption) {
  case '1':
    $paymentPlanText = 'Full Payment';
    $initialPayment = $totalFee;
    break;
  case '2':
    $paymentPlanText = 'Two Payments';
    $initialPayment = $totalFee / 2;
    break;
  case '3':
    $paymentPlanText = 'Three Payments';
    $initialPayment = $totalFee / 3;
    break;
}

// Calculate remaining balance
$remainingBalance = $totalFee - $initialPayment;

// Format payment method display and map to database values
$paymentMethodDisplay = ucfirst($paymentMethod);
$dbPaymentMethod = $paymentMethod; // Default mapping

if ($paymentMethod === 'gcash') {
  $paymentMethodDisplay = 'GCash';
  $dbPaymentMethod = 'gcash';
} elseif ($paymentMethod === 'bpi') {
  $paymentMethodDisplay = 'BPI';
  $dbPaymentMethod = 'bpi';
} elseif ($paymentMethod === 'seabank') {
  $paymentMethodDisplay = 'SeaBank';
  $dbPaymentMethod = 'seabank';
} elseif ($paymentMethod === 'cash') {
  $paymentMethodDisplay = 'Cash';
  $dbPaymentMethod = 'cash';
}

// Handle enrollment processing if form was submitted
$enrollmentSuccess = false;
$enrollmentError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_enrollment'])) {
  // Debug logging
  error_log("=== ENROLLMENT CONFIRMATION DEBUG ===");
  error_log("POST data: " . print_r($_POST, true));
  error_log("FILES data: " . print_r($_FILES, true));
  error_log("Method used: " . ($_POST['payment_data_submitted'] ?? 'direct_confirmation'));
  
  try {
    // Start transaction
    $conn->autocommit(false);

    // Get current user ID
    $userId = $_SESSION['user_id'];

    // Create enrollment record
    $enrollmentQuery = "INSERT INTO enrollments (student_user_id, program_id, enrollment_date, status, total_fee) VALUES (?, ?, CURDATE(), 'pending', ?)";
    $enrollmentStmt = $conn->prepare($enrollmentQuery);
    $enrollmentStmt->bind_param('iid', $userId, $programId, $totalFee);
    $enrollmentStmt->execute();

    $enrollmentId = $conn->insert_id;

    // Generate and create payment schedule based on payment option
    $paymentSchedule = generatePaymentSchedule($enrollmentId, $totalFee, (int)$paymentOption, $dbPaymentMethod);
    $paymentCreated = createPaymentSchedule($paymentSchedule);

    if (!$paymentCreated) {
      throw new Exception('Failed to create payment schedule');
    }

    // Store reference number and payment method for the first payment (the one that was paid)
    $updateRefQuery = "UPDATE payments SET reference_number = ?, payment_method = ? WHERE enrollment_id = ? AND installment_number = 1";
    $updateRefStmt = $conn->prepare($updateRefQuery);
    $updateRefStmt->bind_param('ssi', $referenceNumber, $dbPaymentMethod, $enrollmentId);
    $updateRefStmt->execute();

    // Handle payment proof file upload if provided
    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
      $receipt_file = $_FILES['payment_receipt'];
      
      // Get the first payment ID for this enrollment
      $paymentIdQuery = "SELECT id FROM payments WHERE enrollment_id = ? AND installment_number = 1";
      $paymentIdStmt = $conn->prepare($paymentIdQuery);
      $paymentIdStmt->bind_param('i', $enrollmentId);
      $paymentIdStmt->execute();
      $paymentIdResult = $paymentIdStmt->get_result();
      
      if ($paymentIdResult && $paymentIdResult->num_rows > 0) {
        $paymentRow = $paymentIdResult->fetch_assoc();
        $firstPaymentId = $paymentRow['id'];
        
        // Upload the file
        $upload_dir = '../../uploads/payment_receipts/';
        if (!is_dir($upload_dir)) {
          mkdir($upload_dir, 0777, true);
        }
        
        // Validate file
        $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'application/pdf'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($file_info, $receipt_file['tmp_name']);
        finfo_close($file_info);
        
        if (in_array($file_type, $allowed_types) && $receipt_file['size'] <= 10 * 1024 * 1024) {
          // Generate unique filename
          $extension = pathinfo($receipt_file['name'], PATHINFO_EXTENSION);
          $receipt_filename = 'receipt_' . $firstPaymentId . '_' . time() . '.' . $extension;
          $receipt_path = $upload_dir . $receipt_filename;
          
          if (move_uploaded_file($receipt_file['tmp_name'], $receipt_path)) {
            // Save attachment record to database
            $attachment_sql = "INSERT INTO payment_attachments (payment_id, filename, original_filename, file_size, mime_type, created_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())";
            $attachment_stmt = $conn->prepare($attachment_sql);
            $attachment_stmt->bind_param('issis', $firstPaymentId, $receipt_filename, $receipt_file['name'], $receipt_file['size'], $file_type);
            $attachment_stmt->execute();
          }
        }
      }
    }

    // Commit transaction
    $conn->commit();
    $conn->autocommit(true);

    $enrollmentSuccess = true;

    // Get the actual balance after enrollment
    $actualBalance = getEnrollmentBalance($enrollmentId);

    // Store success message in session for display on enrollment page
    $_SESSION['enrollment_success'] = [
      'program_name' => $currentProgram['name'],
      'enrollment_id' => $enrollmentId,
      'message' => 'Your enrollment has been successfully submitted and is pending review.'
    ];

    // Redirect to enrollment page with success message
    header('Location: student-enrollment.php?success=enrollment_confirmed');
    exit();
  } catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $conn->autocommit(true);
    $enrollmentError = "Enrollment failed. Please try again.";
    error_log("Enrollment error: " . $e->getMessage());
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Enrollment Confirmation - <?php echo $currentProgram['name']; ?> - TPLearn</title>
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

    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 0;
      border-bottom: 1px solid #f3f4f6;
    }

    .detail-row:last-child {
      border-bottom: none;
    }

    .detail-label {
      color: #6b7280;
      font-weight: 500;
    }

    .detail-value {
      color: #1f2937;
      font-weight: 600;
      text-align: right;
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
              <div class="step-circle step-completed">2</div>
              <span class="text-xs mt-2 text-center font-medium text-gray-700">Payment Options</span>
            </div>

            <!-- Line 2 -->
            <div class="step-line step-line-active"></div>

            <!-- Step 3 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-completed">3</div>
              <span class="text-xs mt-2 text-center font-medium text-gray-700">Payment Method</span>
            </div>

            <!-- Line 3 -->
            <div class="step-line step-line-active"></div>

            <!-- Step 4 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-active">4</div>
              <span class="text-xs mt-2 text-center font-medium text-tplearn-green">Confirmation</span>
            </div>
          </div>
        </div>

        <!-- Enrollment Confirmation -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-8">Enrollment Confirmation</h2>

          <?php if ($enrollmentError): ?>
            <!-- Error Message -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-8">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-red-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-red-800 text-sm"><?php echo htmlspecialchars($enrollmentError); ?></p>
              </div>
            </div>
          <?php elseif ($enrollmentSuccess): ?>
            <!-- Success Message -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-8">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-green-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <div class="text-green-800 text-sm">
                  <p class="font-semibold mb-2">✅ Enrollment successful!</p>
                  <p>Your application has been submitted and is pending review.</p>
                  <?php if (isset($actualBalance) && $actualBalance > 0): ?>
                    <p class="mt-2 p-2 bg-orange-100 text-orange-800 rounded">
                      <strong>Remaining Balance:</strong> ₱<?php echo number_format($actualBalance, 2); ?>
                      <br><small>Please complete your payment to activate your enrollment.</small>
                    </p>
                  <?php elseif (isset($actualBalance) && $actualBalance <= 0): ?>
                    <p class="mt-2 p-2 bg-green-100 text-green-800 rounded">
                      <strong>✅ Payment Complete!</strong> No remaining balance.
                    </p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <!-- Information Notice -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-blue-800 text-sm">Please review your enrollment details below:</p>
              </div>
            </div>
          <?php endif; ?>

          <!-- Enrollment Details -->
          <div class="space-y-0">
            <!-- Program -->
            <div class="detail-row">
              <span class="detail-label">Program:</span>
              <span class="detail-value"><?php echo htmlspecialchars($currentProgram['name']); ?></span>
            </div>

            <!-- Duration -->
            <div class="detail-row">
              <span class="detail-label">Duration:</span>
              <span class="detail-value"><?php echo $currentProgram['duration_weeks']; ?> weeks</span>
            </div>

            <!-- Payment Plan -->
            <div class="detail-row">
              <span class="detail-label">Payment Plan:</span>
              <span class="detail-value"><?php echo $paymentPlanText; ?></span>
            </div>

            <!-- Payment Method -->
            <div class="detail-row">
              <span class="detail-label">Payment Method:</span>
              <span class="detail-value"><?php echo $paymentMethodDisplay; ?></span>
            </div>

            <!-- Reference Number -->
            <div class="detail-row">
              <span class="detail-label">Reference Number:</span>
              <span class="detail-value font-mono bg-gray-100 px-2 py-1 rounded text-sm"><?php echo htmlspecialchars($referenceNumber); ?></span>
            </div>

            <!-- Total Program Fee -->
            <div class="detail-row">
              <span class="detail-label">Total Program Fee:</span>
              <span class="detail-value">₱<?php echo number_format($totalFee, 2); ?></span>
            </div>

            <!-- Initial Payment -->
            <div class="detail-row">
              <span class="detail-label">Initial Payment:</span>
              <span class="detail-value">₱<?php echo number_format($initialPayment, 2); ?></span>
            </div>

            <!-- Balance after Payment -->
            <div class="detail-row">
              <span class="detail-label">Balance after Payment:</span>
              <span class="detail-value <?php echo $remainingBalance > 0 ? 'text-orange-600' : 'text-green-600'; ?>">
                ₱<?php echo number_format($remainingBalance, 2); ?>
                <?php if ($remainingBalance > 0): ?>
                  <small class="block text-xs text-gray-500 mt-1">
                    <?php
                    if ($paymentOption == '2') {
                      echo "1 more payment required";
                    } elseif ($paymentOption == '3') {
                      echo "2 more payments required";
                    }
                    ?>
                  </small>
                <?php else: ?>
                  <small class="block text-xs text-gray-500 mt-1">Fully paid</small>
                <?php endif; ?>
              </span>
            </div>
          </div>

          <!-- Terms and Conditions -->
          <div class="mt-8 p-6 bg-gray-50 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Terms and Conditions</h3>
            <div class="space-y-3 text-sm text-gray-700">
              <p>• Payment receipts must be submitted within 24 hours of enrollment.</p>
              <p>• Enrollment is subject to verification of payment and documents.</p>
              <p>• Refunds are only available according to our refund policy.</p>
              <p>• Students must attend at least 80% of sessions to receive certification.</p>
              <p>• Class schedules may be subject to change with prior notice.</p>
            </div>

            <div class="mt-6">
              <label class="flex items-start">
                <input type="checkbox" id="termsCheckbox" class="mt-1 h-4 w-4 text-tplearn-green border-gray-300 rounded focus:ring-tplearn-green">
                <span class="ml-2 text-sm text-gray-700">
                  I agree to the <a href="#" class="text-tplearn-green hover:text-green-700 underline">Terms and Conditions</a> and confirm that all information provided is accurate.
                </span>
              </label>
            </div>
          </div>

          <!-- Action Buttons -->
          <?php if (!$enrollmentSuccess): ?>
            <form method="POST" enctype="multipart/form-data" class="pt-8 border-t border-gray-200 mt-8" onsubmit="return confirmEnrollment()">
              <div class="flex justify-between items-center">
                <button type="button" onclick="goBack()" class="flex items-center px-6 py-3 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                  </svg>
                  Back
                </button>

                <button type="submit" name="confirm_enrollment" id="confirmBtn" disabled class="flex items-center px-8 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed transition-all duration-200">
                  Confirm Enrollment
                  <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                </button>
              </div>
            </form>
          <?php else: ?>
            <div class="flex justify-center items-center pt-8 border-t border-gray-200 mt-8">
              <div class="space-x-4">
                <button onclick="viewEnrollments()" class="px-6 py-3 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors">
                  View My Enrollments
                </button>
                <button onclick="goHome()" class="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                  Go to Dashboard
                </button>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

  <script>
    // Check if terms are accepted
    document.getElementById('termsCheckbox').addEventListener('change', function() {
      const confirmBtn = document.getElementById('confirmBtn');
      if (this.checked) {
        confirmBtn.disabled = false;
        confirmBtn.className = 'flex items-center px-8 py-3 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-all duration-200';
      } else {
        confirmBtn.disabled = true;
        confirmBtn.className = 'flex items-center px-8 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed transition-all duration-200';
      }
    });

    function goBack() {
      window.history.back();
    }

    function confirmEnrollment() {
      // Show confirmation dialog
      const programName = '<?php echo addslashes($currentProgram['name']); ?>';
      const totalAmount = '₱<?php echo number_format($totalFee, 2); ?>';
      const paymentMethod = '<?php echo ucfirst($dbPaymentMethod); ?>';
      const paymentPlan = '<?php echo addslashes($paymentPlanText); ?>';

      const message = `Are you sure you want to confirm your enrollment?

Program: ${programName}
Total Fee: ${totalAmount}
Payment Method: ${paymentMethod}
Payment Plan: ${paymentPlan}

This action cannot be undone.`;

      return confirm(message);
    }

    function viewEnrollments() {
      window.location.href = 'student-enrollment.php';
    }

    function goHome() {
      window.location.href = 'student.php';
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