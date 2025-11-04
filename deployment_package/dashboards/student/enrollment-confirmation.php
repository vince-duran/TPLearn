<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/data-helpers.php';
require_once '../../includes/schedule-conflict.php';
requireRole('student');

// Get parameters from URL
$programId = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
$paymentOption = isset($_GET['option']) ? $_GET['option'] : '1';
$paymentMethod = isset($_GET['method']) ? $_GET['method'] : 'gcash';
$referenceNumber = isset($_GET['reference']) ? trim($_GET['reference']) : null;

// Debug: Log parameters
error_log("Enrollment Confirmation Debug - Program ID: $programId, Option: $paymentOption, Method: $paymentMethod, Reference: $referenceNumber");

// Validate required parameters
if (!$programId || !$paymentOption || !$paymentMethod || !$referenceNumber) {
  error_log("Missing parameters - redirecting to enrollment page");
  header('Location: student-enrollment.php?error=missing_parameters');
  exit();
}

// Get current user data from session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? 'Student';

// Get student data for display name
$student_data = getStudentDashboardData($user_id);
$display_name = $student_data['name'] ?? $user_name;

// Check enrollment eligibility (capacity and duplicate enrollment)
$eligibilityCheck = validateEnrollmentEligibility($_SESSION['user_id'], $programId);
if (!$eligibilityCheck['eligible']) {
  error_log("Enrollment not eligible: " . $eligibilityCheck['reason']);
  $errorParam = urlencode($eligibilityCheck['reason']);
  header("Location: student-enrollment.php?error=enrollment_not_eligible&message={$errorParam}");
  exit();
}

// Check for schedule conflicts
$conflictCheck = checkScheduleConflict($_SESSION['user_id'], $programId);
if ($conflictCheck['has_conflict']) {
  $conflicting_programs = array_map(function($conflict) {
    return $conflict['program_name'];
  }, $conflictCheck['conflicting_programs']);
  
  $errorParam = urlencode('Schedule conflict with: ' . implode(', ', $conflicting_programs));
  header("Location: student-enrollment.php?error=schedule_conflict&message={$errorParam}");
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
    'duration_weeks' => $programData['duration_weeks'],
    'schedule' => ($programData['days'] ?? '') . ($programData['start_time'] ? ', ' . $programData['start_time'] . ' - ' . ($programData['end_time'] ?? '') : ''),
    'start_date' => $programData['start_date'] ?? null
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

/**
 * Send enrollment confirmation email to student
 */
function sendEnrollmentConfirmationEmail($userId, $enrollmentId, $program, $totalFee, $initialPayment, $paymentPlan, $paymentMethod, $referenceNumber) {
  global $conn;
  
  try {
    // Get student email and name
    $studentQuery = "SELECT u.email, u.username, 
                            COALESCE(sp.first_name, SUBSTRING_INDEX(u.username, '-', 1)) as first_name,
                            COALESCE(sp.last_name, SUBSTRING_INDEX(u.username, '-', -1)) as last_name
                     FROM users u 
                     LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                     WHERE u.id = ?";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    if (!$student) {
      error_log("Student not found for enrollment confirmation email");
      return false;
    }
    
    $studentName = trim($student['first_name'] . ' ' . $student['last_name']) ?: $student['username'];
    $studentEmail = $student['email'];
    
    // Email content
    $subject = "Enrollment Confirmation - " . $program['name'] . " - TPLearn";
    
    $emailBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Enrollment Confirmation</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
            .header { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
            .header p { margin: 5px 0 0 0; opacity: 0.9; }
            .content { padding: 30px 20px; }
            .success-icon { text-align: center; margin-bottom: 20px; }
            .success-icon div { display: inline-block; width: 60px; height: 60px; background: #10b981; border-radius: 50%; position: relative; }
            .success-icon div::after { content: '✓'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 24px; font-weight: bold; }
            .enrollment-details { background: #f8fafc; border-radius: 8px; padding: 20px; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 5px 0; border-bottom: 1px solid #e5e7eb; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { font-weight: 600; color: #374151; }
            .detail-value { color: #1f2937; text-align: right; }
            .important-note { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; margin: 20px 0; }
            .important-note h3 { margin: 0 0 10px 0; color: #92400e; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #6b7280; font-size: 14px; }
            .button { display: inline-block; background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 10px 0; }
            .button:hover { background: #059669; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 TPLearn</h1>
                <p>Enrollment Confirmation</p>
            </div>
            
            <div class='content'>
                <div class='success-icon'>
                    <div></div>
                </div>
                
                <h2 style='text-align: center; color: #1f2937; margin-bottom: 10px;'>Enrollment Successful!</h2>
                <p style='text-align: center; color: #6b7280; margin-bottom: 30px;'>Hello {$studentName}, your enrollment has been confirmed.</p>
                
                <div class='enrollment-details'>
                    <h3 style='margin: 0 0 15px 0; color: #1f2937;'>📋 Enrollment Details</h3>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Enrollment ID:</span>
                        <span class='detail-value'><strong>#{$enrollmentId}</strong></span>
                    </div>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Program:</span>
                        <span class='detail-value'>{$program['name']}</span>
                    </div>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Duration:</span>
                        <span class='detail-value'>{$program['duration_weeks']} weeks</span>
                    </div>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Payment Plan:</span>
                        <span class='detail-value'>{$paymentPlan}</span>
                    </div>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Payment Method:</span>
                        <span class='detail-value'>{$paymentMethod}</span>
                    </div>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Reference Number:</span>
                        <span class='detail-value'><code style='background: #e5e7eb; padding: 2px 6px; border-radius: 4px; font-family: monospace;'>{$referenceNumber}</code></span>
                    </div>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Total Fee:</span>
                        <span class='detail-value'><strong>₱" . number_format($totalFee, 2) . "</strong></span>
                    </div>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Initial Payment:</span>
                        <span class='detail-value'>₱" . number_format($initialPayment, 2) . "</span>
                    </div>
                    
                    <div class='detail-row'>
                        <span class='detail-label'>Remaining Balance:</span>
                        <span class='detail-value'><strong>₱" . number_format($totalFee - $initialPayment, 2) . "</strong></span>
                    </div>
                </div>
                
                <div class='important-note'>
                    <h3>⚠️ Important Information</h3>
                    <ul style='margin: 0; padding-left: 20px;'>
                        <li>Your enrollment is currently <strong>pending review</strong></li>
                        <li>You will receive an email notification once approved</li>
                        <li>Please keep this confirmation for your records</li>
                        <li>Contact support if you have any questions</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . $_SERVER['HTTP_HOST'] . "/TPLearn/dashboards/student/student-enrollment.php' class='button'>View My Enrollments</a>
                </div>
                
                <p style='color: #6b7280; font-size: 14px; text-align: center; margin-top: 30px;'>
                    Thank you for choosing TPLearn for your educational journey!
                </p>
            </div>
            
            <div class='footer'>
                <p><strong>TPLearn Academic Platform</strong></p>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>If you need assistance, contact our support team.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: TPLearn <noreply@tplearn.com>" . "\r\n";
    $headers .= "Reply-To: support@tplearn.com" . "\r\n";
    
    // Send email
    $mailSent = mail($studentEmail, $subject, $emailBody, $headers);
    
    if ($mailSent) {
      error_log("Enrollment confirmation email sent successfully to: " . $studentEmail);
      return true;
    } else {
      error_log("Failed to send enrollment confirmation email to: " . $studentEmail);
      return false;
    }
    
  } catch (Exception $e) {
    error_log("Error sending enrollment confirmation email: " . $e->getMessage());
    return false;
  }
}

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

    // Send enrollment confirmation email
    sendEnrollmentConfirmationEmail($userId, $enrollmentId, $currentProgram, $totalFee, $initialPayment, $paymentPlanText, $paymentMethodDisplay, $referenceNumber);

    // Create enrollment notification for the user
    try {
      require_once __DIR__ . '/../../includes/notification-helpers.php';
      
      $notification_result = createEnrollmentNotification($userId, $currentProgram['name'], $totalFee);
      
      if (!$notification_result['success']) {
        error_log("Failed to create enrollment notification: " . $notification_result['error']);
      } else {
        error_log("Enrollment notification sent successfully. Email sent: " . ($notification_result['email_sent'] ? 'Yes' : 'No'));
      }

      // Create admin notification for new enrollment
      $student_name = $firstName . ' ' . $lastName;
      $admin_notification_result = createAdminEnrollmentNotification(
        $userId, 
        $student_name, 
        $currentProgram['name'], 
        '' // No tutor assigned yet
      );

      if (!$admin_notification_result['success']) {
        error_log("Failed to create admin enrollment notification: " . $admin_notification_result['error']);
      } else {
        error_log("Admin enrollment notification sent successfully. Notifications created: " . 
          $admin_notification_result['notifications_created'] . 
          ", Admin emails sent: " . $admin_notification_result['admin_email_result']['emails_sent']);
      }

    } catch (Exception $e) {
      error_log("Error sending enrollment notification: " . $e->getMessage());
    }

    // Store success message in session for display on enrollment page
    $_SESSION['enrollment_success'] = [
      'program_name' => $currentProgram['name'],
      'enrollment_id' => $enrollmentId,
      'message' => 'Your enrollment has been successfully submitted and is pending review.',
      'confirmation_sent' => true
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
    
    /* Enhanced animations */
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }
    
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    @keyframes ping {
      0% { transform: scale(1); opacity: 1; }
      75%, 100% { transform: scale(1.2); opacity: 0; }
    }
    
    .animate-slideInUp { animation: slideInUp 0.6s ease-out; }
    .animate-fadeIn { animation: fadeIn 0.8s ease-in; }
    .animate-pulse { animation: pulse 2s infinite; }
    .animate-bounce { animation: bounce 1s infinite; }
    .animate-ping { animation: ping 1s infinite; }
    
    /* Success notification styles */
    .notification-toast {
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #10b981, #059669);
      color: white;
      padding: 16px 20px;
      border-radius: 8px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      transform: translateX(400px);
      transition: transform 0.3s ease-in-out;
    }
    
    .notification-toast.show {
      transform: translateX(0);
    }
    
    /* Progress indicator */
    .progress-ring {
      transform: rotate(-90deg);
    }
    
    .progress-ring-circle {
      transition: stroke-dasharray 0.35s;
      transform: rotate(-90deg);
      transform-origin: 50% 50%;
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
      renderStudentHeader('Enrollment Confirmation', 'Review and confirm your enrollment details');
      ?>
      ?>
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
            <?php 
            // Get notifications for the current user
            $student_user_id = $_SESSION['user_id'] ?? null;
            if ($student_user_id) {
              $notifications = getUserNotifications($student_user_id, 10);
              $unread_count = 0;
              foreach ($notifications as $notification) {
                // Consider notifications from today or containing "hour" or "minutes" as unread
                $timeText = $notification['time'];
                $isUnread = (strpos($timeText, 'hour') !== false || strpos($timeText, 'minute') !== false || strpos($timeText, 'Just now') !== false);
                if ($isUnread) {
                  $unread_count++;
                }
              }
            } else {
              $notifications = [];
              $unread_count = 0;
            }
            ?>
            <div class="relative">
              <button onclick="toggleNotifications()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
                </svg>
              </button>
              <?php if ($unread_count > 0): ?>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"><?php echo $unread_count; ?></span>
              <?php endif; ?>
              
              <!-- Notification Dropdown -->
              <div id="notification-dropdown" class="hidden absolute right-0 mt-2 bg-white rounded-lg shadow-lg py-2 z-50 border border-gray-200" style="width: 600px; min-width: 500px; max-width: 95vw;">
                <style>
                  @media (max-width: 768px) {
                    #notification-dropdown {
                      width: 400px !important;
                      min-width: 350px !important;
                    }
                  }
                  @media (max-width: 480px) {
                    #notification-dropdown {
                      width: calc(100vw - 40px) !important;
                      min-width: 300px !important;
                      right: 20px !important;
                    }
                  }
                </style>
                <div class="px-4 py-3 border-b border-gray-200">
                  <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Notifications</h3>
                    <div class="flex space-x-1">
                      <button onclick="filterNotifications('all')" id="filter-all" class="px-2 md:px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700 hover:bg-green-200 transition-colors">
                        All
                      </button>
                      <button onclick="filterNotifications('unread')" id="filter-unread" class="px-2 md:px-3 py-1 text-xs font-medium rounded-full text-gray-600 hover:bg-gray-100 transition-colors">
                        Unread
                      </button>
                    </div>
                  </div>
                </div>
                <div class="max-h-64 overflow-y-auto" id="notifications-container">
                  <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                      <?php 
                      // Determine if notification is unread based on time
                      $timeText = $notification['time'];
                      $isUnread = (strpos($timeText, 'hour') !== false || strpos($timeText, 'minute') !== false || strpos($timeText, 'Just now') !== false);
                      $unreadClass = $isUnread ? 'unread' : 'read';
                      ?>
                      <div class="notification-item px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 <?php echo $unreadClass; ?>" 
                           onclick="handleNotificationClick('<?php echo htmlspecialchars($notification['url']); ?>', this)">
                        <div class="flex items-start space-x-3">
                          <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full bg-<?php echo $notification['color']; ?>-100 flex items-center justify-center">
                              <i class="fas fa-<?php echo $notification['icon']; ?> text-<?php echo $notification['color']; ?>-600 text-sm"></i>
                            </div>
                          </div>
                          <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900 notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $notification['time']; ?></p>
                          </div>
                          <?php if ($isUnread): ?>
                            <div class="flex-shrink-0">
                              <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="px-4 py-8 text-center text-gray-500" id="no-notifications">
                      <i class="fas fa-bell-slash text-2xl mb-2"></i>
                      <p>No notifications yet</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
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
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-8 animate-slideInUp">
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
            <!-- Enhanced Success Message -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-6 mb-8 relative overflow-hidden">
              <!-- Success Animation Background -->
              <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-green-100 rounded-full opacity-20 animate-pulse"></div>
              <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-16 h-16 bg-emerald-100 rounded-full opacity-30 animate-bounce"></div>
              
              <div class="relative">
                <div class="flex items-center mb-4">
                  <!-- Animated Success Icon -->
                  <div class="flex-shrink-0 mr-4">
                    <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center relative animate-pulse">
                      <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <!-- Success Ring Animation -->
                      <div class="absolute inset-0 border-2 border-green-300 rounded-full animate-ping"></div>
                    </div>
                  </div>
                  
                  <!-- Success Title -->
                  <div class="flex-1">
                    <h3 class="text-xl font-bold text-green-800 mb-1">🎉 Enrollment Successful!</h3>
                    <p class="text-green-700">Your application has been submitted and is being processed.</p>
                  </div>
                </div>
                
                <!-- Enrollment ID Badge -->
                <div class="bg-white border border-green-200 rounded-lg p-4 mb-4">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-600">Enrollment ID</p>
                      <p class="text-lg font-bold text-green-800 font-mono">#{<?php echo $enrollmentId ?? 'PENDING'; ?>}</p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-600">Status</p>
                      <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                        <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2 animate-pulse"></span>
                        Pending Review
                      </span>
                    </div>
                  </div>
                </div>
                
                <!-- Program & Payment Summary -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                  <div class="bg-white border border-green-200 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800 mb-2">📚 Program Details</h4>
                    <p class="text-sm text-gray-600 mb-1">Program: <span class="font-medium text-gray-800"><?php echo htmlspecialchars($currentProgram['name']); ?></span></p>
                    <p class="text-sm text-gray-600">Duration: <span class="font-medium text-gray-800"><?php echo $currentProgram['duration_weeks']; ?> weeks</span></p>
                  </div>
                  
                  <div class="bg-white border border-green-200 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800 mb-2">💳 Payment Summary</h4>
                    <p class="text-sm text-gray-600 mb-1">Plan: <span class="font-medium text-gray-800"><?php echo $paymentPlanText; ?></span></p>
                    <p class="text-sm text-gray-600">Method: <span class="font-medium text-gray-800"><?php echo $paymentMethodDisplay; ?></span></p>
                  </div>
                </div>
                
                <!-- Balance Information -->
                <?php if (isset($actualBalance) && $actualBalance > 0): ?>
                  <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                      <svg class="w-5 h-5 text-orange-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                      </svg>
                      <div>
                        <p class="font-semibold text-orange-800">Remaining Balance: ₱<?php echo number_format($actualBalance, 2); ?></p>
                        <p class="text-sm text-orange-700">Please complete your payment to activate your enrollment.</p>
                      </div>
                    </div>
                  </div>
                <?php elseif (isset($actualBalance) && $actualBalance <= 0): ?>
                  <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                      <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <div>
                        <p class="font-semibold text-green-800">✅ Payment Complete!</p>
                        <p class="text-sm text-green-700">No remaining balance. Your enrollment is fully paid.</p>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
                
                <!-- Confirmation Email Notice -->
                <?php if (isset($_SESSION['enrollment_success']['confirmation_sent']) && $_SESSION['enrollment_success']['confirmation_sent']): ?>
                  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                      <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                      </svg>
                      <div>
                        <p class="font-semibold text-blue-800">📧 Confirmation Email Sent</p>
                        <p class="text-sm text-blue-700">A detailed confirmation has been sent to your registered email address.</p>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
                
                <!-- Next Steps Timeline -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 mt-4">
                  <h4 class="font-semibold text-gray-800 mb-4">📋 What Happens Next?</h4>
                  <div class="space-y-4">
                    <div class="flex items-start">
                      <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                      </div>
                      <div>
                        <p class="font-medium text-gray-900">Enrollment Submitted</p>
                        <p class="text-sm text-gray-600">Your application has been received and is in the system.</p>
                        <p class="text-xs text-green-600 font-medium">✓ Completed</p>
                      </div>
                    </div>
                    
                    <div class="flex items-start">
                      <div class="flex-shrink-0 w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center mr-3 animate-pulse">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                      </div>
                      <div>
                        <p class="font-medium text-gray-900">Review & Verification</p>
                        <p class="text-sm text-gray-600">Our team will review your application and payment details.</p>
                        <p class="text-xs text-yellow-600 font-medium">⏳ In Progress (1-2 business days)</p>
                      </div>
                    </div>
                    
                    <div class="flex items-start">
                      <div class="flex-shrink-0 w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                          <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                        </svg>
                      </div>
                      <div>
                        <p class="font-medium text-gray-900">Approval Notification</p>
                        <p class="text-sm text-gray-600">You'll receive an email confirmation once approved.</p>
                        <p class="text-xs text-gray-500 font-medium">⌛ Pending</p>
                      </div>
                    </div>
                    
                    <div class="flex items-start">
                      <div class="flex-shrink-0 w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                      </div>
                      <div>
                        <p class="font-medium text-gray-900">Program Access</p>
                        <p class="text-sm text-gray-600">Access to program materials and schedule information.</p>
                        <p class="text-xs text-gray-500 font-medium">⌛ Pending</p>
                      </div>
                    </div>
                  </div>
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

            <!-- Schedule -->
            <?php if (!empty($currentProgram['schedule'])): ?>
            <div class="detail-row">
              <span class="detail-label">Schedule:</span>
              <span class="detail-value"><?php echo htmlspecialchars($currentProgram['schedule']); ?></span>
            </div>
            <?php endif; ?>

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

            <!-- Initial Payment -->
            <div class="detail-row">
              <span class="detail-label">Initial Payment:</span>
              <span class="detail-value">₱<?php echo number_format($initialPayment, 2); ?></span>
            </div>

            <!-- Remaining Balance -->
            <div class="detail-row">
              <span class="detail-label">Remaining Balance:</span>
              <span class="detail-value <?php echo $remainingBalance > 0 ? 'text-orange-600' : 'text-green-600'; ?>">
                ₱<?php echo number_format($remainingBalance, 2); ?>
              </span>
            </div>

            <!-- Next Payment Due -->
            <?php if ($remainingBalance > 0): ?>
            <div class="detail-row">
              <span class="detail-label">Next Payment Due:</span>
              <span class="detail-value">
                <?php 
                // Calculate next payment due date (30 days from enrollment)
                $nextPaymentDate = date('M j, Y', strtotime('+30 days'));
                echo $nextPaymentDate;
                ?>
              </span>
            </div>
            <?php endif; ?>

            <!-- Start Date -->
            <div class="detail-row">
              <span class="detail-label">Start Date:</span>
              <span class="detail-value">
                <?php 
                // Show program start date if available, otherwise estimate
                $startDate = $currentProgram['start_date'] ?? date('M j, Y', strtotime('+7 days'));
                echo is_string($startDate) ? $startDate : date('M j, Y', strtotime($startDate));
                ?>
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
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 w-full max-w-2xl">
                <button onclick="downloadEnrollmentReceipt()" class="flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  Download Receipt
                </button>
                <button onclick="viewEnrollments()" class="flex items-center justify-center px-6 py-3 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  View My Enrollments
                </button>
                <button onclick="goHome()" class="flex items-center justify-center px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                  </svg>
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

      return (await TPAlert.confirm('Confirm Action', message)).isConfirmed;
    }

    function viewEnrollments() {
      window.location.href = 'student-enrollment.php';
    }

    function goHome() {
      window.location.href = 'student.php';
    }

    // Download enrollment receipt as PDF
    function downloadEnrollmentReceipt() {
      // Create the receipt content
      const enrollmentId = '<?php echo $enrollmentId ?? 'N/A'; ?>';
      const programName = '<?php echo addslashes($currentProgram['name']); ?>';
      const studentName = '<?php echo addslashes($display_name); ?>';
      const totalFee = '₱<?php echo number_format($totalFee, 2); ?>';
      const initialPayment = '₱<?php echo number_format($initialPayment, 2); ?>';
      const remainingBalance = '₱<?php echo number_format($remainingBalance, 2); ?>';
      const paymentPlan = '<?php echo addslashes($paymentPlanText); ?>';
      const paymentMethod = '<?php echo addslashes($paymentMethodDisplay); ?>';
      const referenceNumber = '<?php echo addslashes($referenceNumber); ?>';
      const currentDate = new Date().toLocaleDateString();
      
      // Generate PDF using window.print with custom styling
      const receiptWindow = window.open('', '_blank');
      const receiptContent = `
        <!DOCTYPE html>
        <html>
        <head>
          <title>Enrollment Receipt - ${programName}</title>
          <style>
            body { 
              font-family: 'Arial', sans-serif; 
              margin: 0; 
              padding: 20px; 
              background: white; 
              color: #333; 
            }
            .receipt-container { 
              max-width: 600px; 
              margin: 0 auto; 
              border: 2px solid #10b981; 
              border-radius: 10px; 
              overflow: hidden; 
            }
            .header { 
              background: linear-gradient(135deg, #10b981, #059669); 
              color: white; 
              padding: 30px 20px; 
              text-align: center; 
            }
            .header h1 { 
              margin: 0; 
              font-size: 32px; 
              font-weight: bold; 
            }
            .header p { 
              margin: 10px 0 0 0; 
              font-size: 18px; 
              opacity: 0.9; 
            }
            .content { 
              padding: 30px; 
            }
            .enrollment-id { 
              text-align: center; 
              background: #f0fdf4; 
              border: 1px solid #bbf7d0; 
              border-radius: 8px; 
              padding: 20px; 
              margin-bottom: 30px; 
            }
            .enrollment-id h2 { 
              margin: 0; 
              color: #166534; 
              font-size: 24px; 
            }
            .enrollment-id p { 
              margin: 5px 0 0 0; 
              color: #15803d; 
              font-size: 16px; 
            }
            .details-section { 
              margin-bottom: 30px; 
            }
            .details-section h3 { 
              color: #374151; 
              border-bottom: 2px solid #10b981; 
              padding-bottom: 10px; 
              margin-bottom: 20px; 
            }
            .detail-row { 
              display: flex; 
              justify-content: space-between; 
              margin: 12px 0; 
              padding: 8px 0; 
              border-bottom: 1px solid #e5e7eb; 
            }
            .detail-row:last-child { 
              border-bottom: none; 
            }
            .detail-label { 
              font-weight: 600; 
              color: #4b5563; 
            }
            .detail-value { 
              color: #1f2937; 
              text-align: right; 
            }
            .total-row { 
              background: #f9fafb; 
              padding: 15px; 
              border-radius: 8px; 
              margin: 20px 0; 
            }
            .total-row .detail-label, 
            .total-row .detail-value { 
              font-size: 18px; 
              font-weight: bold; 
              color: #10b981; 
            }
            .footer { 
              background: #f8fafc; 
              padding: 20px; 
              text-align: center; 
              border-top: 1px solid #e5e7eb; 
              color: #6b7280; 
            }
            .status-badge { 
              display: inline-block; 
              background: #fef3c7; 
              color: #92400e; 
              padding: 6px 12px; 
              border-radius: 20px; 
              font-size: 14px; 
              font-weight: 600; 
              margin-top: 10px; 
            }
            .reference-number { 
              background: #f3f4f6; 
              border: 1px solid #d1d5db; 
              border-radius: 6px; 
              padding: 8px; 
              font-family: 'Courier New', monospace; 
              font-weight: bold; 
            }
            @media print {
              body { margin: 0; padding: 10px; }
              .receipt-container { border: 1px solid #ccc; }
            }
          </style>
        </head>
        <body>
          <div class="receipt-container">
            <div class="header">
              <h1>🎓 TPLearn</h1>
              <p>Official Enrollment Receipt</p>
            </div>
            
            <div class="content">
              <div class="enrollment-id">
                <h2>Enrollment ID: #${enrollmentId}</h2>
                <p>Issued on: ${currentDate}</p>
                <div class="status-badge">⏳ Pending Review</div>
              </div>
              
              <div class="details-section">
                <h3>👤 Student Information</h3>
                <div class="detail-row">
                  <span class="detail-label">Student Name:</span>
                  <span class="detail-value">${studentName}</span>
                </div>
              </div>
              
              <div class="details-section">
                <h3>📚 Program Information</h3>
                <div class="detail-row">
                  <span class="detail-label">Program:</span>
                  <span class="detail-value">${programName}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Duration:</span>
                  <span class="detail-value"><?php echo $currentProgram['duration_weeks']; ?> weeks</span>
                </div>
              </div>
              
              <div class="details-section">
                <h3>💳 Payment Information</h3>
                <div class="detail-row">
                  <span class="detail-label">Payment Plan:</span>
                  <span class="detail-value">${paymentPlan}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Payment Method:</span>
                  <span class="detail-value">${paymentMethod}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Reference Number:</span>
                  <span class="detail-value"><span class="reference-number">${referenceNumber}</span></span>
                </div>
              </div>
              
              <div class="details-section">
                <h3>💰 Fee Breakdown</h3>
                <div class="detail-row">
                  <span class="detail-label">Total Program Fee:</span>
                  <span class="detail-value">${totalFee}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Initial Payment:</span>
                  <span class="detail-value">${initialPayment}</span>
                </div>
                <div class="total-row">
                  <div class="detail-row">
                    <span class="detail-label">Remaining Balance:</span>
                    <span class="detail-value">${remainingBalance}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="footer">
              <p><strong>TPLearn Academic Platform</strong></p>
              <p>This is an official enrollment receipt. Please keep for your records.</p>
              <p>For questions or support, contact: support@tplearn.com</p>
            </div>
          </div>
          
          <script>
            window.onload = function() {
              window.print();
              window.onafterprint = function() {
                window.close();
              };
            };
          </script>
        </body>
        </html>
      `;
      
      receiptWindow.document.write(receiptContent);
      receiptWindow.document.close();
    }

    // Show success notification on page load if enrollment was successful
    <?php if ($enrollmentSuccess): ?>
    document.addEventListener('DOMContentLoaded', function() {
      // Show success toast
      showSuccessNotification();
      
      // Add entrance animations to success elements
      const successElements = document.querySelectorAll('.bg-gradient-to-r');
      successElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        setTimeout(() => {
          element.style.transition = 'all 0.6s ease-out';
          element.style.opacity = '1';
          element.style.transform = 'translateY(0)';
        }, index * 200);
      });
    });
    
    function showSuccessNotification() {
      // Create toast notification
      const toast = document.createElement('div');
      toast.className = 'notification-toast';
      toast.innerHTML = `
        <div class="flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
          </svg>
          <div>
            <p class="font-semibold">Enrollment Successful!</p>
            <p class="text-sm opacity-90">Confirmation email sent</p>
          </div>
          <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
        </div>
      `;
      
      document.body.appendChild(toast);
      
      // Show the toast
      setTimeout(() => {
        toast.classList.add('show');
      }, 500);
      
      // Auto-hide after 5 seconds
      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
          if (toast.parentElement) {
            toast.remove();
          }
        }, 300);
      }, 5000);
    }
    <?php endif; ?>

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

    // Notification dropdown functions
    function toggleNotifications() {
      const dropdown = document.getElementById('notification-dropdown');
      dropdown.classList.toggle('hidden');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
      const dropdown = document.getElementById('notification-dropdown');
      const button = event.target.closest('button[onclick="toggleNotifications()"]');
      
      if (!button && !dropdown.contains(event.target)) {
        dropdown.classList.add('hidden');
      }
    });

    // Filter notifications
    function filterNotifications(type) {
      const allButton = document.getElementById('filter-all');
      const unreadButton = document.getElementById('filter-unread');
      const notifications = document.querySelectorAll('.notification-item');
      const noNotificationsMsg = document.getElementById('no-notifications');
      
      // Update button styles
      if (type === 'all') {
        allButton.className = 'px-2 md:px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700 hover:bg-green-200 transition-colors';
        unreadButton.className = 'px-2 md:px-3 py-1 text-xs font-medium rounded-full text-gray-600 hover:bg-gray-100 transition-colors';
      } else {
        unreadButton.className = 'px-2 md:px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700 hover:bg-green-200 transition-colors';
        allButton.className = 'px-2 md:px-3 py-1 text-xs font-medium rounded-full text-gray-600 hover:bg-gray-100 transition-colors';
      }
      
      let visibleCount = 0;
      
      // Filter notifications
      notifications.forEach(notification => {
        if (type === 'all') {
          notification.style.display = 'block';
          visibleCount++;
        } else if (type === 'unread' && notification.classList.contains('unread')) {
          notification.style.display = 'block';
          visibleCount++;
        } else {
          notification.style.display = 'none';
        }
      });
      
      // Show/hide no notifications message
      if (noNotificationsMsg) {
        noNotificationsMsg.style.display = visibleCount === 0 ? 'block' : 'none';
      }
    }

    // Handle notification click
    function handleNotificationClick(url, element) {
      // Mark as read
      markNotificationAsRead(element);
      
      // Wait a bit then navigate
      setTimeout(() => {
        window.location.href = url;
      }, 100);
    }

    // Mark notification as read
    function markNotificationAsRead(element) {
      const notificationId = element.dataset.id || Math.random().toString(36).substr(2, 9);
      
      // Mark as read in localStorage
      let readNotifications = JSON.parse(localStorage.getItem('readNotifications') || '[]');
      if (!readNotifications.includes(notificationId)) {
        readNotifications.push(notificationId);
        localStorage.setItem('readNotifications', JSON.stringify(readNotifications));
      }
      
      // Update visual state
      markNotificationAsReadVisually(element);
    }

    // Mark notification as read visually
    function markNotificationAsReadVisually(element) {
      element.classList.remove('unread');
      element.classList.add('read');
      
      // Remove unread indicator
      const unreadDot = element.querySelector('.bg-blue-500');
      if (unreadDot) {
        unreadDot.remove();
      }
      
      // Update notification count
      updateNotificationCount();
    }

    // Update notification count badge
    function updateNotificationCount() {
      const unreadNotifications = document.querySelectorAll('.notification-item.unread');
      const badge = document.querySelector('.bg-red-500');
      
      if (unreadNotifications.length === 0) {
        if (badge) {
          badge.style.display = 'none';
        }
      } else {
        if (badge) {
          badge.textContent = unreadNotifications.length;
          badge.style.display = 'flex';
        }
      }
    }

    // Initialize notification read states from localStorage
    document.addEventListener('DOMContentLoaded', function() {
      const readNotifications = JSON.parse(localStorage.getItem('readNotifications') || '[]');
      const allNotifications = document.querySelectorAll('.notification-item');
      
      allNotifications.forEach((notification) => {
        const notificationId = notification.dataset.id || notification.querySelector('.notification-message')?.textContent?.trim();
        if (readNotifications.includes(notificationId)) {
          markNotificationAsReadVisually(notification);
        }
      });
    });
  </script>
</body>

</html>