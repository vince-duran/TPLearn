<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('student');

// Get current student's payments
$student_username = $_SESSION['username'];
$student_payments = getStudentPayments($student_username);
$currentDate = date('l, F j, Y');

// Fetch payment methods from database
$ewalletAccounts = getAllEWalletAccounts();
$bankAccounts = getAllBankAccounts();
$cashSettings = getCashPaymentInstructions();

// Calculate payment statistics
$total_payments = count($student_payments);
$total_paid = 0;
$pending_validation = 0;
$validated_count = 0;
$rejected_count = 0;
$overdue_count = 0;

// Separate payments into pending (need action) and completed (history)
$pending_payments = [];
$completed_payments = [];

// Safety check for student payments
if ($student_payments && is_array($student_payments)) {
  foreach ($student_payments as $payment) {
    $total_paid += floatval($payment['amount']);

    switch ($payment['status']) { // Use calculated status from the database query
      case 'validated':
        $validated_count++;
        $completed_payments[] = $payment; // Validated payments go to history
        break;
      case 'pending_validation':
        $pending_validation++;
        $pending_payments[] = $payment; // Pending payments need action
        break;
      case 'rejected':
        $rejected_count++;
        $pending_payments[] = $payment; // Rejected payments need resubmission
        break;
      case 'overdue':
        $overdue_count++;
        $pending_payments[] = $payment; // Overdue payments need immediate action - highest priority
        break;
      case 'due':
      case 'due_today':
        $pending_payments[] = $payment; // Due payments need to be paid
        break;
      default:
        $pending_payments[] = $payment; // Any other status needs action
        break;
    }
  }
} // End of if statement for safety check

// Function to check if an installment is payable based on previous installments
function isInstallmentPayable($currentPayment, $allPayments)
{
  // Check if we have valid data
  if (!$allPayments || !is_array($allPayments) || empty($allPayments)) {
    return true; // If no payments data, allow payment
  }

  $currentInstallment = intval($currentPayment['installment_number'] ?? 1);
  $programName = $currentPayment['program_name'];

  // If it's the first installment, it's always payable
  if ($currentInstallment <= 1) {
    return true;
  }

  // Check if all previous installments for the same program have been validated
  for ($i = 1; $i < $currentInstallment; $i++) {
    $previousInstallmentFound = false;
    $previousInstallmentValidated = false;

    foreach ($allPayments as $payment) {
      if (
        $payment['program_name'] === $programName &&
        intval($payment['installment_number'] ?? 1) === $i
      ) {
        $previousInstallmentFound = true;
        if ($payment['status'] === 'validated') {
          $previousInstallmentValidated = true;
        }
        break;
      }
    }

    // If any previous installment is not validated, current installment is not payable
    if ($previousInstallmentFound && !$previousInstallmentValidated) {
      return false;
    }
  }

  return true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments - Student Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* Custom styles for payments table */
    .payment-row {
      transition: all 0.2s ease;
    }

    .payment-row:hover {
      background-color: #f9fafb;
    }

    .status-badge {
      font-size: 0.75rem;
      font-weight: 500;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      display: inline-flex;
      align-items: center;
    }

    .status-validated {
      background-color: #d1fae5;
      color: #065f46;
    }

    .status-overdue {
      background-color: #fef2f2;
      color: #b91c1c;
      font-weight: 600;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% {
        opacity: 1;
      }
      50% {
        opacity: 0.8;
      }
    }

    .status-pending {
      background-color: #dbeafe;
      color: #1e40af;
    }

    .status-pending-validation {
      background-color: #fef3c7;
      color: #92400e;
    }

    .status-rejected {
      background-color: #fee2e2;
      color: #991b1b;
    }

    .status-due-today {
      background-color: #fed7aa;
      color: #9a3412;
    }

    .status-due {
      background-color: #dbeafe;
      color: #1e40af;
    }

    /* Installment badge styles */
    .installment-badge {
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }

    .installment-pending {
      background-color: #dbeafe;
      color: #1e40af;
    }

    .installment-completed {
      background-color: #d1fae5;
      color: #065f46;
    }

    /* Locked installment styles */
    .installment-locked {
      background-color: #f3f4f6;
      color: #6b7280;
    }

    .locked-payment-row {
      opacity: 0.6;
    }

    .locked-button {
      position: relative;
    }

    .locked-button:hover::after {
      content: attr(title);
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      background-color: #374151;
      color: white;
      padding: 0.5rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      white-space: nowrap;
      z-index: 10;
      margin-bottom: 0.25rem;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex min-h-screen">

    <?php include '../../includes/student-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1 flex flex-col min-h-screen">
      <!-- Fixed Header -->
      <div class="flex-shrink-0">
        <?php 
        require_once '../../includes/header.php';
        renderHeader(
          'Payments',
          $currentDate,
          'student',
          $_SESSION['name'] ?? 'Student',
          [], // notifications array - to be implemented
          []  // messages array - to be implemented
        );
        ?>

        <!-- Tab Navigation - Connected to Header -->
        <div class="bg-white border-b border-gray-200 px-4 lg:px-6">
          <nav class="flex space-x-8">
            <button id="make-payment-tab" onclick="switchTab('make-payment')" class="py-4 px-1 border-b-2 border-tplearn-green text-tplearn-green font-medium text-sm whitespace-nowrap">
              Make Payment
            </button>
            <button id="payment-history-tab" onclick="switchTab('payment-history')" class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
              Payment History
            </button>
          </nav>
        </div>
      </div>

      <!-- Scrollable Main Content Area -->
      <main class="flex-1 p-4 lg:p-6">

        <!-- Make Payment Tab -->
        <div id="make-payment-content" class="tab-content">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-medium text-gray-900">Pending Payments</h2>
              <p class="text-sm text-gray-600 mt-1">Payments that require action: upcoming due dates and rejected payments</p>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Installment</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (empty($pending_payments)): ?>
                    <tr>
                      <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                          <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                          </svg>
                          <p class="font-medium">All payments are up to date!</p>
                          <p class="text-sm">No pending or rejected payments</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($pending_payments as $payment): ?>
                      <?php
                      // Check if this installment is payable based on previous installments
                      $isPayable = isInstallmentPayable($payment, $student_payments);

                      // Determine status display properties for pending payments
                      $statusColor = 'gray';
                      $statusText = 'Unknown';
                      $statusClass = 'status-unknown';
                      $actionButton = '';
                      $urgencyClass = '';

                      switch ($payment['status']) {
                        case 'rejected':
                          $statusColor = 'red';
                          $statusText = 'Rejected - Resubmit Required';
                          $statusClass = 'status-rejected';
                          $urgencyClass = 'bg-red-50 border-l-4 border-red-400';
                          $actionButton = '<div class="flex space-x-1">
                                            <button onclick="resubmitPayment(\'' . htmlspecialchars($payment['payment_id']) . '\', \'' . addslashes(htmlspecialchars($payment['program_name'])) . '\', ' . floatval($payment['amount']) . ')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs font-medium">Resubmit</button>
                                            <button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\')" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                                          </div>';
                          break;
                        case 'pending_validation':
                          $statusColor = 'yellow';
                          $statusText = 'Pending Validation';
                          $statusClass = 'status-pending-validation';
                          $urgencyClass = 'bg-yellow-50 border-l-4 border-yellow-400';
                          $actionButton = '<button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\')" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>';
                          break;
                        case 'overdue':
                          $statusColor = 'red';
                          $statusText = 'Overdue - Pay Now';
                          $statusClass = 'status-overdue';
                          $urgencyClass = 'bg-red-50 border-l-4 border-red-400';
                          if ($isPayable) {
                            $actionButton = '<div class="flex space-x-1">
                                              <button onclick="payNow(\'' . htmlspecialchars($payment['program_name']) . '\', ' . floatval($payment['amount']) . ', \'' . htmlspecialchars($payment['payment_id']) . '\', ' . intval($payment['installment_number'] ?? 1) . ', ' . intval($payment['total_installments'] ?? 1) . ')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs font-medium">Pay Now</button>
                                              <button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\')" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                                            </div>';
                          } else {
                            $actionButton = '<div class="text-center">
                                              <button disabled class="bg-gray-300 text-gray-500 px-3 py-1.5 rounded text-xs font-medium cursor-not-allowed" title="Complete previous installments first">Locked</button>
                                              <p class="text-xs text-gray-500 mt-1">Previous payment required</p>
                                            </div>';
                          }
                          break;
                        case 'due_today':
                          $statusColor = 'orange';
                          $statusText = 'Due Today - Pay Now';
                          $statusClass = 'status-due-today';
                          $urgencyClass = 'bg-orange-50 border-l-4 border-orange-400';
                          if ($isPayable) {
                            $actionButton = '<div class="flex space-x-1">
                                              <button onclick="payNow(\'' . htmlspecialchars($payment['program_name']) . '\', ' . floatval($payment['amount']) . ', \'' . htmlspecialchars($payment['payment_id']) . '\', ' . intval($payment['installment_number'] ?? 1) . ', ' . intval($payment['total_installments'] ?? 1) . ')" class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1.5 rounded text-xs font-medium">Pay Now</button>
                                              <button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\')" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                                            </div>';
                          } else {
                            $actionButton = '<div class="text-center">
                                              <button disabled class="bg-gray-300 text-gray-500 px-3 py-1.5 rounded text-xs font-medium cursor-not-allowed" title="Complete previous installments first">Locked</button>
                                              <p class="text-xs text-gray-500 mt-1">Previous payment required</p>
                                            </div>';
                          }
                          break;
                        case 'due':
                          $statusColor = 'blue';
                          $statusText = 'Payment Due';
                          $statusClass = 'status-due';
                          $urgencyClass = 'bg-blue-50 border-l-4 border-blue-400';
                          if ($isPayable) {
                            $actionButton = '<div class="flex space-x-1">
                                              <button onclick="payNow(\'' . htmlspecialchars($payment['program_name']) . '\', ' . floatval($payment['amount']) . ', \'' . htmlspecialchars($payment['payment_id']) . '\', ' . intval($payment['installment_number'] ?? 1) . ', ' . intval($payment['total_installments'] ?? 1) . ')" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-xs font-medium">Pay Now</button>
                                              <button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\')" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                                            </div>';
                          } else {
                            $actionButton = '<div class="text-center">
                                              <button disabled class="bg-gray-300 text-gray-500 px-3 py-1.5 rounded text-xs font-medium cursor-not-allowed" title="Complete previous installments first">Locked</button>
                                              <p class="text-xs text-gray-500 mt-1">Previous payment required</p>
                                            </div>';
                          }
                          break;
                        default:
                          $statusColor = 'gray';
                          $statusText = 'Unknown Status';
                          $statusClass = 'status-unknown';
                          $urgencyClass = 'bg-gray-50 border-l-4 border-gray-400';
                          $actionButton = '';
                      }

                      // Format dates
                      $paymentDate = date('n/j/Y', strtotime($payment['payment_date']));
                      $dueDate = isset($payment['due_date']) ? date('n/j/Y', strtotime($payment['due_date'])) : 'N/A';

                      // Check if this installment is payable
                      $isPayable = isInstallmentPayable($payment, $student_payments);
                      $lockedRowClass = !$isPayable ? 'locked-payment-row' : '';
                      ?>
                      <tr class="payment-row <?php echo $urgencyClass; ?> <?php echo $lockedRowClass; ?>">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                          <?php echo htmlspecialchars($payment['payment_id']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center text-sm text-gray-900">
                            <svg class="w-4 h-4 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo htmlspecialchars($paymentDate); ?>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          ₱<?php echo number_format($payment['amount'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <?php echo htmlspecialchars($payment['program_name'] ?: 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <?php
                          $installmentNumber = $payment['installment_number'] ?? 1;
                          $totalInstallments = $payment['total_installments'] ?? 1;

                          // Determine installment badge color based on status and payability
                          $installmentBadgeClass = 'installment-pending';
                          if ($payment['status'] === 'validated') {
                            $installmentBadgeClass = 'installment-completed';
                          } elseif (!$isPayable && in_array($payment['status'], ['overdue', 'due_today', 'due'])) {
                            $installmentBadgeClass = 'installment-locked';
                          }
                          ?>
                          <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium installment-badge <?php echo $installmentBadgeClass; ?>">
                              <?php echo htmlspecialchars($installmentNumber . ' of ' . $totalInstallments); ?>
                              <?php if (!$isPayable && in_array($payment['status'], ['overdue', 'due_today', 'due'])): ?>
                                <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                  <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                </svg>
                              <?php endif; ?>
                            </span>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo htmlspecialchars($dueDate); ?>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="w-2 h-2 bg-<?php echo $statusColor; ?>-400 rounded-full mr-2"></div>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                          <?php echo $actionButton; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Payment History Tab -->
        <div id="payment-history-content" class="tab-content hidden">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-medium text-gray-900">Payment History</h2>
              <p class="text-sm text-gray-600 mt-1">Completed and validated payments</p>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Installment</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (empty($completed_payments)): ?>
                    <tr>
                      <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                          <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                          </svg>
                          <p class="font-medium">No completed payments found</p>
                          <p class="text-sm">Validated payments will appear here</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($completed_payments as $payment): ?>
                      <?php
                      // Payment History only shows validated payments, so status is always validated
                      $statusColor = 'green';
                      $statusText = 'Validated';
                      $statusClass = 'status-validated';
                      $actionButton = '<div class="flex space-x-2">
                                        <button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\')" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                                        <button onclick="downloadReceipt(\'' . htmlspecialchars($payment['payment_id']) . '\')" class="text-tplearn-green hover:text-green-800 text-xs font-medium">Receipt</button>
                                      </div>';

                      // Format date
                      $paymentDate = date('n/j/Y', strtotime($payment['payment_date']));
                      ?>
                      <tr class="payment-row">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                          <?php echo htmlspecialchars($payment['payment_id']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center text-sm text-gray-900">
                            <svg class="w-4 h-4 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo htmlspecialchars($paymentDate); ?>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          ₱<?php echo number_format($payment['amount'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <?php echo htmlspecialchars($payment['program_name'] ?: 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <?php
                          $installmentNumber = $payment['installment_number'] ?? 1;
                          $totalInstallments = $payment['total_installments'] ?? 1;
                          ?>
                          <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium installment-badge installment-completed">
                              <?php echo htmlspecialchars($installmentNumber . ' of ' . $totalInstallments); ?>
                            </span>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <?php echo htmlspecialchars($payment['payment_method'] ?: 'Not specified'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="w-2 h-2 bg-<?php echo $statusColor; ?>-400 rounded-full mr-2"></div>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                          <?php echo $actionButton; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Details Modal -->
  <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-4xl w-full max-h-[95vh] overflow-hidden shadow-2xl flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-slate-50 flex-shrink-0">
        <h2 class="text-xl font-semibold text-gray-800">Payment Details</h2>
        <button onclick="closeDetailsModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      
      <!-- Details Content -->
      <div id="detailsContent" class="p-8 overflow-y-auto flex-1 min-h-0">
        <!-- Content will be loaded dynamically -->
      </div>
      
      <!-- Modal Footer -->
      <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50 flex-shrink-0">
        <button onclick="closeDetailsModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- Receipt Modal -->
  <div id="receiptModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
        <h2 class="text-xl font-semibold text-gray-800">Payment Receipt</h2>
        <button onclick="closeReceiptModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Receipt Content -->
      <div id="receiptContent" class="p-8 bg-white overflow-y-auto max-h-[60vh]">
        <!-- Receipt Header -->
        <div class="text-center mb-8">
          <div class="flex items-center justify-center mb-4">
            <div class="w-16 h-16 bg-tplearn-green rounded-full flex items-center justify-center">
              <span class="text-white text-2xl font-bold">TP</span>
            </div>
          </div>
          <h1 class="text-2xl font-bold text-gray-800 mb-2">TPLearn</h1>
          <p class="text-sm text-gray-600">Tisa at Pisara's Academic Tutoring Services</p>
          <p class="text-xs text-gray-500">Blk 2 Lot 47 Carissa 4A, Kaypian, City of San Jose Del Monte, Bulacan</p>
          
          <div class="mt-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800">OFFICIAL PAYMENT RECEIPT</h2>
          </div>
          
          <div class="border-t border-b border-gray-200 py-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div class="text-left">
                <div class="text-gray-600">Receipt #:</div>
                <div id="receiptNumber" class="font-mono font-medium text-gray-800">REC-20251015-011</div>
              </div>
              <div class="text-right">
                <div class="text-gray-600">Date:</div>
                <div id="receiptDate" class="font-medium text-gray-800">October 15, 2025</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Student and Program Information in a clean table format -->
        <div class="mb-8">
          <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-300 pb-2">STUDENT INFORMATION</h3>
            <div class="grid grid-cols-2 gap-6">
              <div>
                <div class="mb-3">
                  <span class="text-gray-600 font-medium">Full Name:</span>
                  <div id="receiptStudentName" class="text-gray-800 font-semibold">Fourth Garcia</div>
                </div>
                <div class="mb-3">
                  <span class="text-gray-600 font-medium">Student ID:</span>
                  <div id="receiptStudentId" class="text-gray-800 font-mono">TP2025-210</div>
                </div>
              </div>
              <div>
                <div class="mb-3">
                  <span class="text-gray-600 font-medium">Program:</span>
                  <div id="receiptProgramName" class="text-gray-800 font-semibold">Advanced Mathematics Course</div>
                </div>
                <div class="mb-3">
                  <span class="text-gray-600 font-medium">Installment:</span>
                  <div id="receiptInstallment" class="text-gray-800 font-medium">1 of 3</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-8">
          <div class="bg-green-50 p-6 rounded-lg border border-green-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-green-300 pb-2">PAYMENT DETAILS</h3>
            
            <div class="grid grid-cols-2 gap-6 mb-4">
              <div>
                <div class="mb-3">
                  <span class="text-gray-600 font-medium">Payment ID:</span>
                  <div id="receiptPaymentId" class="text-gray-800 font-mono text-sm">PAY-20251015-011</div>
                </div>
                <div class="mb-3">
                  <span class="text-gray-600 font-medium">Payment Method:</span>
                  <div id="receiptPaymentMethod" class="text-gray-800 font-medium">gcash</div>
                </div>
              </div>
              <div>
                <div class="mb-3">
                  <span class="text-gray-600 font-medium">Reference Number:</span>
                  <div id="receiptReferenceNumber" class="text-gray-800 font-mono text-sm">REF123456789</div>
                </div>
                <div class="mb-3">
                  <span class="text-gray-600 font-medium">Status:</span>
                  <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                      ✓ Validated
                    </span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Amount - Highlighted -->
            <div class="border-t border-green-300 pt-4">
              <div class="flex justify-between items-center">
                <span class="text-lg font-semibold text-gray-700">Amount Paid:</span>
                <span id="receiptAmount" class="text-3xl font-bold text-tplearn-green">₱834.00</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-300 pt-6 text-center">
          <p class="text-sm text-gray-700 font-medium mb-2">This is an official receipt for your payment.</p>
          <p class="text-xs text-gray-500 mb-1">Thank you for choosing TPLearn!</p>
          <p class="text-xs text-gray-500">For inquiries, please contact Email: tplearnph@gmail.com</p>
          
          <div class="mt-4 pt-4 border-t border-gray-200">
            <p class="text-xs text-gray-400">Generated on: <span id="receiptGeneratedDate">${new Date().toLocaleString()}</span></p>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
        <button onclick="closeReceiptModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Close
        </button>
        <button onclick="printReceipt()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zM5 14H4v-3h1v3zm2 2v-4h6v4H7zm8-2h1v-3h-1v3z" clip-rule="evenodd"></path>
          </svg>
          Print
        </button>
        <button onclick="downloadReceiptPDF()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
          Download PDF
        </button>
      </div>
    </div>
  </div>

  <script>
    // Payment method display function
    function getPaymentMethodDisplay(method) {
      const methodMap = {
        // Cash payments
        'cash': 'Cash',
        
        // E-Wallet payments
        'gcash': 'E-Wallet',
        'maya': 'E-Wallet', 
        'ewallet': 'E-Wallet',
        
        // Bank transfers
        'bpi': 'Bank Transfer',
        'bdo': 'Bank Transfer',
        'seabank': 'Bank Transfer',
        'bank': 'Bank Transfer',
        'bank_transfer': 'Bank Transfer'
      };
      return methodMap[method] || 'N/A';
    }

    // Tab switching functionality
    function switchTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
      });

      // Remove active class from all tabs
      document.querySelectorAll('[id$="-tab"]').forEach(tab => {
        tab.classList.remove('border-tplearn-green', 'text-tplearn-green');
        tab.classList.add('border-transparent', 'text-gray-500');
      });

      // Show selected tab content
      document.getElementById(tabName + '-content').classList.remove('hidden');

      // Add active class to selected tab
      const activeTab = document.getElementById(tabName + '-tab');
      activeTab.classList.add('border-tplearn-green', 'text-tplearn-green');
      activeTab.classList.remove('border-transparent', 'text-gray-500');
    }

    // Payment functionality
    function payNow(program, amount, paymentId, installmentNumber = 1, totalInstallments = 1) {
      console.log('PayNow called with Payment ID:', paymentId);
      
      // Calculate remaining balance after this payment
      const currentAmount = parseFloat(amount);
      const remainingInstallments = totalInstallments - installmentNumber;

      // For installment plans, estimate remaining balance
      // Note: This is an approximation since installment amounts may vary slightly
      const estimatedBalance = remainingInstallments > 0 ? remainingInstallments * currentAmount : 0;

      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
      modal.innerHTML = `
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[95vh] shadow-2xl flex flex-col">
          <!-- Fixed Header -->
          <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
            <div>
              <h2 class="text-xl font-semibold text-gray-900">Payment - ${program}</h2>
            </div>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <!-- Scrollable Content -->
          <div class="flex-1 overflow-y-auto p-6 space-y-8">
            <!-- Payment ID -->
            <div class="bg-tplearn-green border border-tplearn-green rounded-lg p-4">
              <div class="flex items-center">
                <svg class="w-5 h-5 text-white mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"></path>
                </svg>
                <div>
                  <h4 class="font-medium text-white">Payment ID</h4>
                  <p class="text-white font-mono font-semibold text-lg">${paymentId}</p>
                  <p class="text-xs text-green-100">Please save this ID for your reference and include it in any future inquiries regarding this payment.</p>
                </div>
              </div>
            </div>

            <!-- Amount Due -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Amount Due (₱)</label>
                <input type="text" value="₱${parseFloat(amount).toLocaleString()}" readonly class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-lg font-semibold text-tplearn-green" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Balance (₱)</label>
                <input type="text" value="₱${estimatedBalance.toLocaleString()}" readonly class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-lg font-semibold text-gray-600" id="balanceAmount" />
                <p class="text-xs text-gray-500 mt-1">Estimated remaining balance (${remainingInstallments} installment${remainingInstallments !== 1 ? 's' : ''} remaining)</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Program</label>
                <input type="text" value="${program}" readonly class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg" />
              </div>
            </div>

            <!-- Payment Method Selection -->
            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Select Payment Method</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Cash -->
                <div class="payment-method-modal cursor-pointer rounded-lg p-4 text-center border-2 border-gray-200 hover:border-tplearn-green transition-colors" onclick="selectPaymentMethodInModal('cash')">
                  <div class="text-gray-600 mb-2">
                    <svg class="w-8 h-8 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"></path>
                    </svg>
                  </div>
                  <span class="text-sm font-medium text-gray-700">Cash</span>
                </div>

                <!-- E-Wallet -->
                <div class="payment-method-modal cursor-pointer rounded-lg p-4 text-center border-2 border-gray-200 hover:border-tplearn-green transition-colors" onclick="selectPaymentMethodInModal('ewallet')">
                  <div class="text-gray-600 mb-2">
                    <svg class="w-8 h-8 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                    </svg>
                  </div>
                  <span class="text-sm font-medium text-gray-700">E-Wallet</span>
                </div>

                <!-- Bank Transfer -->
                <div class="payment-method-modal cursor-pointer rounded-lg p-4 text-center border-2 border-gray-200 hover:border-tplearn-green transition-colors" onclick="selectPaymentMethodInModal('bank')">
                  <div class="text-gray-600 mb-2">
                    <svg class="w-8 h-8 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v8H4V6z"></path>
                      <path d="M6 8h8v2H6V8z"></path>
                    </svg>
                  </div>
                  <span class="text-sm font-medium text-gray-700">Bank Transfer</span>
                </div>
              </div>
            </div>

            <!-- Payment Instructions -->
            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Instructions</h3>
              <div class="bg-gray-50 rounded-lg p-4" id="payment-instructions-modal">
                <p class="text-gray-700 mb-2"><strong>E-Wallet Payment Options:</strong></p>
                <!-- Dynamic content will be loaded here -->
              </div>
            </div>

            <!-- Reference Number -->
            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Reference Number</h3>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number *</label>
                <input
                  type="text"
                  id="paymentReferenceNumber"
                  name="reference_number"
                  placeholder="Enter your payment reference number (e.g., GC-20240924-1234)"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-tplearn-green"
                  required />
                <p class="text-xs text-gray-500 mt-1">
                  Enter the reference number from your payment transaction (e.g., from GCash, bank transfer, etc.)
                </p>
              </div>
            </div>

            <!-- Upload Payment Proof -->
            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-900 mb-2">Upload Payment Proof <span class="text-red-500">*</span></h3>
              <p class="text-sm text-gray-600 mb-4">Payment proof is required to submit your payment. Please upload a screenshot or receipt of your transaction.</p>
              
              <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-tplearn-green transition-colors" id="uploadArea-modal">
                <div class="text-gray-400 mb-4">
                  <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                  </svg>
                </div>
                <p class="text-gray-600 mb-2">
                  <button onclick="document.getElementById('fileInput-modal').click()" class="text-tplearn-green hover:text-green-700 font-medium">Upload a file</button>
                  or drag and drop
                </p>
                <p class="text-sm text-gray-500">PNG, JPG, PDF up to 10MB</p>
                <input type="file" id="fileInput-modal" class="hidden" accept=".png,.jpg,.jpeg,.pdf" onchange="handleFileSelectModal(event)">
              </div>

              <!-- Selected File Display -->
              <div id="selectedFile-modal" class="hidden mt-4 p-4 bg-green-50 rounded-lg border border-green-200">
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <svg class="w-8 h-8 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                      <p class="text-sm font-medium text-green-800" id="fileName-modal"></p>
                      <p class="text-xs text-green-600" id="fileSize-modal"></p>
                    </div>
                  </div>
                  <button onclick="removeFileModal()" class="text-red-500 hover:text-red-700">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Fixed Footer -->
          <div class="px-6 py-4 border-t border-gray-200 flex-shrink-0">
            <!-- Action Buttons -->
            <div class="flex space-x-3">
              <button onclick="this.closest('.fixed').remove()" class="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
              </button>
              <button id="submitPaymentBtn" onclick="submitPaymentNow('${paymentId}', '${program}', ${amount})" disabled class="flex-1 px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed transition-colors">
                Upload Payment Proof
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);

      // Initialize with default payment method (E-Wallet)
      setTimeout(() => {
        selectPaymentMethodInModal('ewallet');
      }, 100);

      // Add event listeners for validation
      const referenceInput = modal.querySelector('#paymentReferenceNumber');
      referenceInput.addEventListener('input', validatePaymentForm);
    }

    function selectPaymentMethodDetails(method) {
      // Remove active class from all buttons
      document.querySelectorAll('.payment-method-details-btn').forEach(btn => {
        btn.classList.remove('border-blue-500', 'bg-blue-50');
        btn.classList.add('border-gray-200');
        btn.querySelector('div').classList.remove('text-blue-700');
        btn.querySelector('div').classList.add('text-gray-900');
      });

      // Add active class to selected button
      event.target.closest('.payment-method-details-btn').classList.add('border-blue-500', 'bg-blue-50');
      event.target.closest('.payment-method-details-btn').classList.remove('border-gray-200');
      event.target.closest('.payment-method-details-btn').querySelector('div').classList.add('text-blue-700');
      event.target.closest('.payment-method-details-btn').querySelector('div').classList.remove('text-gray-900');
    }

    function processPaymentDetails(program, amount) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
      modal.innerHTML = `
        <div class="bg-white rounded-xl p-6 max-w-md w-full shadow-2xl">
          <div class="text-center">
            <div class="bg-green-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2">Payment Submitted Successfully!</h3>
            <p class="text-gray-600 mb-2">Program: ${program}</p>
            <p class="text-gray-600 mb-2">Amount: ₱${amount.toLocaleString()}</p>
            <p class="text-gray-600 mb-4">Payment ID: PAY-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}${String(new Date().getDate()).padStart(2, '0')}-${Math.floor(Math.random() * 1000).toString().padStart(3, '0')}</p>
            <p class="text-sm text-gray-500 mb-4">Your payment is now pending validation and will be processed within 1-2 business days.</p>
            <button onclick="this.closest('.fixed').remove(); location.reload();" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
              Close
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);

      // Close the payment details modal
      setTimeout(() => {
        document.querySelectorAll('.fixed').forEach(modal => {
          if (modal.innerHTML.includes('Payment Details')) {
            modal.remove();
          }
        });
      }, 100);
    }

    // Payment method data from database
    const ewalletAccounts = <?php echo json_encode($ewalletAccounts); ?>;
    const bankAccounts = <?php echo json_encode($bankAccounts); ?>;
    const cashSettings = <?php echo json_encode($cashSettings); ?>;

    // Payment submission functionality
    let selectedPaymentMethod = '';
    let selectedResubmitPaymentMethod = 'ewallet';
    let resubmitUploadedFile = null;

    function handleResubmitFileSelect(event) {
      const file = event.target.files[0];
      if (file) {
        // Validate file type
        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
          alert('Please upload only PNG, JPG, or PDF files.');
          return;
        }

        // Validate file size (10MB max)
        const maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if (file.size > maxSize) {
          alert('File size must be less than 10MB.');
          return;
        }

        resubmitUploadedFile = file;

        // Show selected file
        document.getElementById('resubmitSelectedFile').classList.remove('hidden');
        document.getElementById('resubmitFileName').textContent = file.name;
        document.getElementById('resubmitFileSize').textContent = formatFileSize(file.size);

        // Hide upload area
        document.getElementById('resubmitUploadArea').style.display = 'none';

        validateResubmitForm();
      }
    }

    function removeResubmitFile() {
      resubmitUploadedFile = null;
      document.getElementById('resubmitSelectedFile').classList.add('hidden');
      document.getElementById('resubmitUploadArea').style.display = 'block';
      document.getElementById('resubmitFileInput').value = '';
      validateResubmitForm();
    }

    function validateResubmitForm() {
      const referenceNumber = document.getElementById('resubmitReferenceNumber')?.value?.trim();
      const submitBtn = document.getElementById('resubmitPaymentBtn');

      if (referenceNumber && selectedResubmitPaymentMethod && resubmitUploadedFile && submitBtn) {
        submitBtn.disabled = false;
        submitBtn.className = 'flex items-center px-6 py-3 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-all duration-200';
      } else if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.className = 'flex items-center px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed transition-all duration-200';
      }
    }

    function setupResubmitDragAndDrop() {
      const uploadArea = document.getElementById('resubmitUploadArea');

      if (uploadArea) {
        uploadArea.addEventListener('dragover', function(e) {
          e.preventDefault();
          uploadArea.classList.add('border-tplearn-green', 'bg-green-50');
        });

        uploadArea.addEventListener('dragleave', function(e) {
          e.preventDefault();
          uploadArea.classList.remove('border-tplearn-green', 'bg-green-50');
        });

        uploadArea.addEventListener('drop', function(e) {
          e.preventDefault();
          uploadArea.classList.remove('border-tplearn-green', 'bg-green-50');

          const files = e.dataTransfer.files;
          if (files.length > 0) {
            const file = files[0];
            document.getElementById('resubmitFileInput').files = files;
            handleResubmitFileSelect({
              target: {
                files: [file]
              }
            });
          }
        });
      }
    }

    function processResubmission(paymentId, program, amount) {
      const referenceNumber = document.getElementById('resubmitReferenceNumber').value.trim();

      if (!referenceNumber || !selectedResubmitPaymentMethod || !resubmitUploadedFile) {
        alert('Please fill in all required fields and upload payment proof.');
        return;
      }

      // Disable submit button
      const submitBtn = document.getElementById('resubmitPaymentBtn');
      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Processing...
      `;

      // Simulate API call (replace with actual API endpoint)
      setTimeout(() => {
        // Close current modal
        document.querySelectorAll('.fixed').forEach(modal => {
          if (modal.innerHTML.includes('Resubmit Payment')) {
            modal.remove();
          }
        });

        // Show success message
        const successModal = document.createElement('div');
        successModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
        successModal.innerHTML = `
          <div class="bg-white rounded-xl p-6 max-w-md w-full shadow-2xl">
            <div class="text-center">
              <div class="bg-tplearn-green bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-tplearn-green" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
              </div>
              <h3 class="text-lg font-semibold mb-2">Payment Resubmitted Successfully!</h3>
              <p class="text-gray-600 mb-2">Program: ${program}</p>
              <p class="text-gray-600 mb-2">Amount: ₱${parseFloat(amount).toLocaleString()}</p>
              <p class="text-gray-600 mb-2">Reference: ${referenceNumber}</p>
              <p class="text-sm text-gray-500 mb-4">Your payment has been resubmitted and is now pending validation.</p>
              <button onclick="this.closest('.fixed').remove(); location.reload();" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-700">
                Close
              </button>
            </div>
          </div>
        `;
        document.body.appendChild(successModal);
      }, 2000);
    }

    function selectPaymentMethodInModal(method) {
      selectedPaymentMethod = method;

      // Remove active class from all payment methods
      document.querySelectorAll('.payment-method-modal').forEach(btn => {
        btn.classList.remove('border-tplearn-green', 'bg-green-50');
        btn.classList.add('border-gray-200');
      });

      // Add active class to selected method
      event.target.closest('.payment-method-modal').classList.remove('border-gray-200');
      event.target.closest('.payment-method-modal').classList.add('border-tplearn-green', 'bg-green-50');

      // Update payment instructions based on selected method
      const instructionsDiv = document.getElementById('payment-instructions-modal');
      let instructions = '';

      switch (method) {
        case 'ewallet':
          // Generate instructions for all E-Wallet accounts
          instructions = `<p class="text-gray-700 mb-2"><strong>E-Wallet Payment Options:</strong></p>`;
          if (ewalletAccounts.length > 0) {
            instructions += `<div class="space-y-3">`;
            ewalletAccounts.forEach(account => {
              instructions += `
                <div class="bg-white p-3 rounded border">
                  <p class="text-gray-700 font-semibold">${account.provider}</p>
                  <p class="text-gray-700 mb-1"><strong>Number:</strong> ${account.account_number}</p>
                  <p class="text-gray-700"><strong>Account Name:</strong> ${account.account_name}</p>
                </div>
              `;
            });
            instructions += `</div>`;
          } else {
            instructions += `<p class="text-gray-700">No E-Wallet accounts configured.</p>`;
          }
          break;
        case 'bank':
          // Generate instructions for all Bank accounts
          instructions = `<p class="text-gray-700 mb-2"><strong>Bank Transfer Options:</strong></p>`;
          if (bankAccounts.length > 0) {
            instructions += `<div class="space-y-3">`;
            bankAccounts.forEach(account => {
              instructions += `
                <div class="bg-white p-3 rounded border">
                  <p class="text-gray-700 font-semibold">${account.bank_name}</p>
                  <p class="text-gray-700 mb-1"><strong>Account Number:</strong> ${account.account_number}</p>
                  <p class="text-gray-700"><strong>Account Name:</strong> ${account.account_name}</p>
                </div>
              `;
            });
            instructions += `</div>`;
          } else {
            instructions += `<p class="text-gray-700">No Bank accounts configured.</p>`;
          }
          break;
        case 'cash':
          instructions = `
            <p class="text-gray-700 mb-2"><strong>Cash Payment Instructions:</strong></p>
            <p class="text-gray-700 mb-1">Please visit our office during business hours:</p>
            <p class="text-gray-700 mb-1"><strong>Address:</strong> ${cashSettings.address}</p>
            <p class="text-gray-700 mb-1"><strong>Hours:</strong> ${cashSettings.hours}</p>
            ${cashSettings.contact_person ? `<p class="text-gray-700 mb-1"><strong>Contact:</strong> ${cashSettings.contact_person}</p>` : ''}
            ${cashSettings.phone_number ? `<p class="text-gray-700 mb-1"><strong>Phone:</strong> ${cashSettings.phone_number}</p>` : ''}
            ${cashSettings.additional_instructions ? `<p class="text-gray-700 mt-2 text-sm">${cashSettings.additional_instructions}</p>` : ''}
          `;
          break;
      }

      instructionsDiv.innerHTML = instructions;
      validatePaymentForm();
    }

    function validatePaymentForm() {
      const referenceNumber = document.getElementById('paymentReferenceNumber')?.value?.trim();
      const fileInput = document.getElementById('fileInput-modal');
      const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
      const submitBtn = document.getElementById('submitPaymentBtn');

      if (referenceNumber && selectedPaymentMethod && hasFile && submitBtn) {
        submitBtn.disabled = false;
        submitBtn.className = 'flex-1 px-6 py-3 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors';
        submitBtn.textContent = 'Submit Payment';
      } else if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.className = 'flex-1 px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed transition-colors';
        
        // Update button text to indicate what's missing
        if (!referenceNumber) {
          submitBtn.textContent = 'Enter Reference Number';
        } else if (!selectedPaymentMethod) {
          submitBtn.textContent = 'Select Payment Method';
        } else if (!hasFile) {
          submitBtn.textContent = 'Upload Payment Proof';
        } else {
          submitBtn.textContent = 'Submit Payment';
        }
      }
    }

    async function submitPaymentNow(paymentId, program, amount) {
      const referenceNumber = document.getElementById('paymentReferenceNumber').value.trim();
      const fileInput = document.getElementById('fileInput-modal');
      const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

      if (!referenceNumber || !selectedPaymentMethod) {
        alert('Please enter reference number and select payment method');
        return;
      }
      
      if (!hasFile) {
        alert('Please upload payment proof before submitting');
        return;
      }

      // Disable submit button
      const submitBtn = document.getElementById('submitPaymentBtn');
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Processing...';

      try {
        // File upload is now required, always use FormData
        const formData = new FormData();
        formData.append('payment_id', paymentId);
        formData.append('reference_number', referenceNumber);
        formData.append('payment_method', selectedPaymentMethod);
        formData.append('receipt', fileInput.files[0]);
        formData.append('is_resubmission', 'false');

        const response = await fetch('/TPLearn/api/submit-payment.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          // Close current modal
          document.querySelectorAll('.fixed').forEach(modal => modal.remove());

          // Show success message
          showPaymentSuccess(result.program_name, result.amount, result.reference_number);

          // Refresh page after a delay
          setTimeout(() => {
            location.reload();
          }, 2000);
        } else {
          throw new Error(result.error || 'Payment submission failed');
        }
      } catch (error) {
        console.error('Payment submission error:', error);
        alert('Error submitting payment: ' + error.message);

        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit Payment';
      }
    }

    function showPaymentSuccess(program, amount, referenceNumber) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
      modal.innerHTML = `
        <div class="bg-white rounded-xl p-6 max-w-md w-full shadow-2xl">
          <div class="text-center">
            <div class="bg-tplearn-green bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-tplearn-green" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2 text-gray-900">Payment Submitted Successfully!</h3>
            <p class="text-gray-600 mb-2"><strong>Program:</strong> ${program}</p>
            <p class="text-gray-600 mb-2"><strong>Amount:</strong> ₱${parseFloat(amount).toLocaleString()}</p>
            <p class="text-gray-600 mb-4"><strong>Reference:</strong> ${referenceNumber}</p>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
              <p class="text-sm text-yellow-800">Your payment is now <strong>Pending Validation</strong> and will be processed within 1-2 business days.</p>
            </div>
            <button onclick="this.closest('.fixed').remove();" class="px-6 py-3 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors">
              Close
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    // Receipt viewing functionality
    function viewReceipt(paymentId) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
      modal.innerHTML = `
        <div class="bg-white rounded-xl p-8 max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
          <!-- Header with Close Button -->
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Payment Receipt</h2>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <!-- Receipt Content -->
          <div class="bg-white border-2 border-gray-200 rounded-lg p-8">
            <!-- Company Header -->
            <div class="text-center mb-8">
              <div class="flex items-center justify-center mb-4">
                <img src="../../assets/logo.png" alt="TPLearn Logo" class="h-12 w-12 mr-3">
                <h1 class="text-3xl font-bold text-blue-600">TPLearn</h1>
              </div>
              <p class="text-lg text-gray-600 font-medium">Official Payment Receipt</p>
              <p class="text-sm text-gray-500 mt-2">Learning Management System</p>
            </div>
            
            <!-- Receipt Details -->
            <div class="grid grid-cols-2 gap-8 mb-8">
              <!-- Left Column -->
              <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Information</h3>
                <div class="space-y-3">
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Receipt No:</span>
                    <span class="font-semibold">#REC-${paymentId}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Payment ID:</span>
                    <span class="font-semibold">${paymentId}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Date Issued:</span>
                    <span class="font-semibold">September 22, 2025</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Payment Method:</span>
                    <span class="font-semibold">Credit Card (Visa)</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Transaction ID:</span>
                    <span class="font-semibold">TXN-789456123</span>
                  </div>
                </div>
              </div>
              
              <!-- Right Column -->
              <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Student Information</h3>
                <div class="space-y-3">
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Student Name:</span>
                    <span class="font-semibold">Maria Santos</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Student ID:</span>
                    <span class="font-semibold">STU-2025-001</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Program:</span>
                    <span class="font-semibold">Kindergarten Program</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Academic Year:</span>
                    <span class="font-semibold">2024-2025</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Semester:</span>
                    <span class="font-semibold">First Semester</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Payment Breakdown -->
            <div class="border-t border-gray-200 pt-6 mb-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Breakdown</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">Tuition Fee (Monthly):</span>
                  <span class="font-medium">₱1,000.00</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Materials Fee:</span>
                  <span class="font-medium">₱200.00</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Activity Fee:</span>
                  <span class="font-medium">₱50.00</span>
                </div>
                <div class="border-t border-gray-200 pt-3 mt-3">
                  <div class="flex justify-between text-lg font-bold">
                    <span>Total Amount Paid:</span>
                    <span class="text-green-600">₱1,250.00</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Payment Status -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
              <div class="flex items-center">
                <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                  <p class="font-semibold text-green-800">Payment Successfully Validated</p>
                  <p class="text-sm text-green-600">This receipt serves as proof of payment</p>
                </div>
              </div>
            </div>
            
            <!-- Footer Information -->
            <div class="text-center text-sm text-gray-500 border-t border-gray-200 pt-4">
              <p class="mb-2">TPLearn Learning Management System</p>
              <p class="mb-2">📧 support@tplearn.com | 📞 (02) 8123-4567</p>
              <p>🌐 www.tplearn.com</p>
              <p class="mt-3 font-medium">Thank you for your payment!</p>
            </div>
          </div>
          
          <!-- Action Buttons -->
          <div class="flex space-x-4 mt-6">
            <button onclick="printReceipt()" class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-lg hover:bg-blue-700 transition-colors font-medium">
              🖨️ Print Receipt
            </button>
            <button onclick="downloadReceipt('${paymentId}')" class="flex-1 bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition-colors font-medium">
              📄 Download PDF
            </button>
            <button onclick="this.closest('.fixed').remove()" class="flex-1 bg-gray-300 text-gray-700 py-3 px-6 rounded-lg hover:bg-gray-400 transition-colors font-medium">
              Close
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    function downloadReceipt(paymentId) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
      modal.innerHTML = `
        <div class="bg-white rounded-xl p-6 max-w-md w-full shadow-2xl">
          <div class="text-center">
            <svg class="w-12 h-12 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="text-lg font-semibold mb-2">Download Started</h3>
            <p class="text-gray-600 mb-4">Receipt ${paymentId}.pdf is being downloaded...</p>
            <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              Close
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      setTimeout(() => modal.remove(), 3000);
    }

    function printReceipt() {
      // Create a new window for printing
      const printWindow = window.open('', '_blank');

      // Get the receipt content (excluding the modal overlay and buttons)
      const receiptContent = document.querySelector('.fixed .bg-white.rounded-lg .bg-white.border-2');

      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>TPLearn Payment Receipt</title>
          <style>
            body { 
              font-family: Arial, sans-serif; 
              margin: 20px; 
              line-height: 1.6;
            }
            .receipt-container { 
              max-width: 800px; 
              margin: 0 auto; 
              border: 2px solid #e5e7eb;
              border-radius: 8px;
              padding: 40px;
            }
            .header { 
              text-center; 
              margin-bottom: 40px; 
            }
            .logo-section {
              display: flex;
              align-items: center;
              justify-content: center;
              margin-bottom: 20px;
            }
            .company-name { 
              font-size: 36px; 
              font-weight: bold; 
              color: #2563eb;
              margin-left: 15px;
            }
            .receipt-title { 
              font-size: 24px; 
              color: #4b5563; 
              font-weight: 600;
            }
            .subtitle { 
              color: #6b7280; 
              margin-top: 10px;
            }
            .details-grid { 
              display: grid; 
              grid-template-columns: 1fr 1fr; 
              gap: 40px; 
              margin-bottom: 40px; 
            }
            .section-title { 
              font-size: 20px; 
              font-weight: 600; 
              margin-bottom: 20px; 
              color: #111827;
            }
            .detail-row { 
              display: flex; 
              justify-content: space-between; 
              margin-bottom: 12px; 
            }
            .detail-label { 
              color: #4b5563; 
              font-weight: 500;
            }
            .detail-value { 
              font-weight: 600; 
            }
            .breakdown-section { 
              border-top: 2px solid #e5e7eb; 
              padding-top: 30px; 
              margin-bottom: 30px; 
            }
            .total-row { 
              border-top: 2px solid #e5e7eb; 
              padding-top: 15px; 
              margin-top: 15px; 
              font-size: 20px; 
              font-weight: bold;
            }
            .total-amount { 
              color: #059669; 
            }
            .status-section { 
              background-color: #f0fdf4; 
              border: 2px solid #bbf7d0; 
              border-radius: 8px; 
              padding: 20px; 
              margin-bottom: 30px;
              display: flex;
              align-items: center;
            }
            .status-icon { 
              color: #059669; 
              margin-right: 15px; 
              font-size: 24px;
            }
            .status-title { 
              font-weight: 600; 
              color: #065f46; 
            }
            .status-subtitle { 
              color: #059669; 
              font-size: 14px;
            }
            .footer { 
              text-align: center; 
              color: #6b7280; 
              border-top: 2px solid #e5e7eb; 
              padding-top: 20px; 
              font-size: 14px;
            }
            .footer p { 
              margin: 8px 0; 
            }
            .thank-you { 
              font-weight: 600; 
              margin-top: 15px;
            }
            @media print {
              body { margin: 0; }
              .receipt-container { border: none; }
            }
          </style>
        </head>
        <body>
          <div class="receipt-container">
            <div class="header">
              <div class="logo-section">
                <span class="company-name">TPLearn</span>
              </div>
              <div class="receipt-title">Official Payment Receipt</div>
              <div class="subtitle">Learning Management System</div>
            </div>
            
            <div class="details-grid">
              <div>
                <div class="section-title">Payment Information</div>
                <div class="detail-row">
                  <span class="detail-label">Receipt No:</span>
                  <span class="detail-value">#REC-${Math.random().toString().substr(2, 8)}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Payment ID:</span>
                  <span class="detail-value">PAY-2025-001</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Date Issued:</span>
                  <span class="detail-value">${new Date().toLocaleDateString()}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Payment Method:</span>
                  <span class="detail-value">Credit Card (Visa)</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Transaction ID:</span>
                  <span class="detail-value">TXN-789456123</span>
                </div>
              </div>
              
              <div>
                <div class="section-title">Student Information</div>
                <div class="detail-row">
                  <span class="detail-label">Student Name:</span>
                  <span class="detail-value">Maria Santos</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Student ID:</span>
                  <span class="detail-value">STU-2025-001</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Program:</span>
                  <span class="detail-value">Kindergarten Program</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Academic Year:</span>
                  <span class="detail-value">2024-2025</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Semester:</span>
                  <span class="detail-value">First Semester</span>
                </div>
              </div>
            </div>
            
            <div class="breakdown-section">
              <div class="section-title">Payment Breakdown</div>
              <div class="detail-row">
                <span class="detail-label">Tuition Fee (Monthly):</span>
                <span class="detail-value">₱1,000.00</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Materials Fee:</span>
                <span class="detail-value">₱200.00</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Activity Fee:</span>
                <span class="detail-value">₱50.00</span>
              </div>
              <div class="detail-row total-row">
                <span>Total Amount Paid:</span>
                <span class="total-amount">₱1,250.00</span>
              </div>
            </div>
            
            <div class="status-section">
              <div class="status-icon">✓</div>
              <div>
                <div class="status-title">Payment Successfully Validated</div>
                <div class="status-subtitle">This receipt serves as proof of payment</div>
              </div>
            </div>
            
            <div class="footer">
              <p>TPLearn Learning Management System</p>
              <p>📧 support@tplearn.com | 📞 (02) 8123-4567</p>
              <p>🌐 www.tplearn.com</p>
              <p class="thank-you">Thank you for your payment!</p>
            </div>
          </div>
        </body>
        </html>
      `);

      printWindow.document.close();

      // Wait for content to load then print
      setTimeout(() => {
        printWindow.print();
        printWindow.close();
      }, 500);
    }

    function resubmitPayment(paymentId, program, amount) {
      console.log('Resubmit function called with:', paymentId, program, amount);
      
      // Extract actual payment ID from formatted payment_id (PAY-YYYYMMDD-XXX)
      let actualPaymentId = paymentId;
      const matches = paymentId.toString().match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }
      
      // Default values in case API fails
      let rejectionReason = 'Invalid receipt. Please resubmit with clearer image.';
      let paymentDetails = {
        payment_id: paymentId,
        program_name: program,
        amount: amount,
        student_name: 'N/A',
        student_id: 'N/A'
      };
      
      // Try to get the actual rejection reason and payment details via AJAX
      fetch(`/TPLearn/api/payments.php?action=get_rejection_reason&payment_id=${actualPaymentId}`)
        .then(response => response.json())
        .then(data => {
          console.log('API Response:', data); // Debug logging
          if (data.success) {
            rejectionReason = data.rejection_reason || rejectionReason;
            if (data.payment_details) {
              paymentDetails = {
                ...paymentDetails,
                ...data.payment_details
              };
              console.log('Updated payment details:', paymentDetails); // Debug logging
              console.log('Balance calculations:', {
                enrollment_fee: paymentDetails.enrollment_fee,
                paid_amount: paymentDetails.paid_amount,
                current_balance: paymentDetails.current_balance,
                payment_amount: paymentDetails.amount,
                balance_after_payment: paymentDetails.balance_after_payment
              }); // Debug logging
            }
            console.log('Fetched payment details:', paymentDetails);
            console.log('Fetched rejection reason:', rejectionReason);
          } else {
            console.warn('API returned error:', data.error);
          }
        })
        .catch(error => {
          console.log('Could not fetch rejection reason, using defaults:', error);
        })
        .finally(() => {
          showResubmitModal(paymentDetails, rejectionReason);
        });
      
      function showResubmitModal(details, reason) {
      
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
      modal.id = 'resubmit-modal';
      modal.innerHTML = `
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[95vh] overflow-y-auto shadow-2xl">
          <!-- Header -->
          <div class="px-8 py-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Resubmit Payment</h2>
                <div class="text-gray-600">
                  <p class="text-sm">Program: <span class="font-medium">${details.program_name}</span></p>
                  <p class="text-sm">Payment: <span class="font-medium">Resubmit Payment</span></p>
                  <p class="text-sm">Amount: <span class="font-medium">₱${parseFloat(details.amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</span></p>
                </div>
              </div>
              <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
          </div>

          <div class="px-8 py-6 space-y-6">
            <!-- Rejection Reason Alert -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <svg class="w-5 h-5 text-red-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-red-800">Resubmitting Rejected Payment</h3>
                  <div class="mt-2 text-sm text-red-700">
                    <p>Your previous payment was rejected for the following reason:</p>
                    <p class="mt-1 font-semibold bg-red-100 p-2 rounded border-l-4 border-red-500">"${reason}"</p>
                    <p class="mt-2">Please address this issue and resubmit your payment with the correct information or documentation.</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment ID -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div class="flex items-center">
                <svg class="w-5 h-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"></path>
                </svg>
                <div>
                  <h4 class="font-medium text-blue-900">Payment ID</h4>
                  <p class="text-blue-600 font-mono">${details.payment_id}</p>
                  <p class="text-xs text-blue-600">Please save this ID for your reference and include it in all communications regarding this payment.</p>
                </div>
              </div>
            </div>

            <!-- Payment Validation Required -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <svg class="w-5 h-5 text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-yellow-800">Payment Validation Required</h3>
                  <div class="mt-2 text-sm text-yellow-700">
                    <p>After submitting your payment, it will be marked as "Pending Validation" until an administrator reviews and validates it. This typically takes 1-2 business days.</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Method -->
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Method</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <button onclick="selectResubmitPaymentMethod('cash')" class="payment-method-btn flex flex-col items-center p-4 border-2 border-gray-300 rounded-lg hover:border-teal-500 hover:bg-teal-50 transition-colors">
                  <svg class="w-8 h-8 text-gray-600 mb-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"></path>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">Cash</span>
                </button>
                <button onclick="selectResubmitPaymentMethod('ewallet')" class="payment-method-btn flex flex-col items-center p-4 border-2 border-teal-500 bg-teal-50 rounded-lg transition-colors">
                  <svg class="w-8 h-8 text-gray-600 mb-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                  </svg>
                  <span class="text-sm font-medium text-teal-700">E-Wallet</span>
                </button>
                <button onclick="selectResubmitPaymentMethod('bank')" class="payment-method-btn flex flex-col items-center p-4 border-2 border-gray-300 rounded-lg hover:border-teal-500 hover:bg-teal-50 transition-colors">
                  <svg class="w-8 h-8 text-gray-600 mb-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v8H4V6z"></path>
                    <path d="M6 8h8v2H6V8z"></path>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">Bank Transfer</span>
                </button>
              </div>
            </div>

            <!-- Payment Instructions -->
            <div id="resubmit-payment-instructions" class="bg-gray-100 rounded-lg p-4 mb-6">
              <p class="text-sm font-medium text-gray-700 mb-2">E-Wallet Payment Options:</p>
              <!-- Dynamic content will be loaded here -->
            </div>

            <!-- Balance Due and Balance after Payment -->
            <div class="grid grid-cols-2 gap-6 mb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Balance Due (₱)</label>
                <input type="text" value="${parseFloat(details.amount).toLocaleString()}" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Balance after Payment (₱)</label>
                <input type="text" value="${parseFloat(details.balance_after_payment || (details.current_balance - details.amount) || 0).toLocaleString()}" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
              </div>
            </div>

            <!-- Payment Reference Number -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number *</label>
              <input
                type="text"
                id="resubmitReferenceNumber"
                placeholder="Enter your payment reference number (e.g., GC-20240924-1234)"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                required
              />
              <p class="text-xs text-gray-500 mt-1">Enter the reference number from your payment transaction (e.g., from GCash, bank transfer, etc.)</p>
            </div>

            <!-- Upload Payment Proof -->
            <div class="mb-8">
              <h3 class="text-sm font-medium text-gray-700 mb-4">Upload Payment Proof</h3>
              <div class="resubmit-drag-drop border-2 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-teal-400 hover:bg-teal-50 transition-colors cursor-pointer">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <p class="text-gray-600 mb-2">
                  <span class="text-teal-600 font-medium cursor-pointer" onclick="document.getElementById('resubmitReceiptUpload').click()">Upload a file</span>
                  or drag and drop
                </p>
                <p class="text-xs text-gray-500">PNG, JPG, PDF up to 10MB</p>
                <input type="file" class="hidden" id="resubmitReceiptUpload" accept="image/*,application/pdf">
              </div>
            </div>
            <!-- Action Buttons -->
            <div class="flex justify-end items-center pt-6 border-t border-gray-200">
              <button id="resubmitSubmitBtn" onclick="processResubmission('${details.payment_id}')" class="flex items-center px-8 py-3 bg-gray-400 text-white rounded-lg cursor-not-allowed transition-colors" disabled>
                Submit Payment
                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);

      // Set default payment method to E-Wallet
      selectedResubmitPaymentMethod = 'ewallet';

      // Apply visual selection to E-Wallet button by default
      setTimeout(() => {
        const ewalletBtn = modal.querySelector('.payment-method-btn[onclick*="ewallet"]');
        if (ewalletBtn) {
          ewalletBtn.classList.remove('border-gray-300');
          ewalletBtn.classList.add('border-teal-500', 'bg-teal-50');
          
          const icon = ewalletBtn.querySelector('svg');
          if (icon) icon.classList.add('text-teal-600');
          
          const label = ewalletBtn.querySelector('span');
          if (label) label.classList.add('text-teal-700');
        }
        
        // Initialize payment instructions for default method (ewallet)
        selectResubmitPaymentMethod('ewallet');
        
        // Trigger initial validation
        validateResubmitForm();
      }, 100);

      // Add event listeners for validation
      const referenceInput = modal.querySelector('#resubmitReferenceNumber');
      if (referenceInput) {
        referenceInput.addEventListener('input', validateResubmitForm);
      }

      // Setup drag and drop for file upload
      setupResubmitDragAndDrop();
      }
    }

    function selectResubmitPaymentMethod(method) {
      selectedResubmitPaymentMethod = method;
      
      // Remove active class from all buttons
      document.querySelectorAll('#resubmit-modal .payment-method-btn').forEach(btn => {
        btn.classList.remove('border-teal-500', 'bg-teal-50');
        btn.classList.add('border-gray-300');
        const span = btn.querySelector('span');
        if (span) {
          span.classList.remove('text-teal-700');
          span.classList.add('text-gray-700');
        }
      });

      // Add active class to selected button
      const selectedBtn = document.querySelector(`#resubmit-modal .payment-method-btn[onclick="selectResubmitPaymentMethod('${method}')"]`);
      if (selectedBtn) {
        selectedBtn.classList.add('border-teal-500', 'bg-teal-50');
        selectedBtn.classList.remove('border-gray-300');
        const span = selectedBtn.querySelector('span');
        if (span) {
          span.classList.add('text-teal-700');
          span.classList.remove('text-gray-700');
        }
      }

      // Update payment instructions
      const instructionsDiv = document.getElementById('resubmit-payment-instructions');
      let instructions = '';
      
      switch (method) {
        case 'ewallet':
          // Generate instructions for all E-Wallet accounts
          instructions = `<p class="text-sm font-medium text-gray-700 mb-2">E-Wallet Payment Options:</p>`;
          if (ewalletAccounts.length > 0) {
            instructions += `<div class="space-y-2">`;
            ewalletAccounts.forEach(account => {
              instructions += `
                <div class="bg-white p-2 rounded border">
                  <p class="text-sm font-semibold text-gray-800">${account.provider}</p>
                  <p class="text-sm text-gray-700">Number: ${account.account_number}</p>
                  <p class="text-sm text-gray-700">Name: ${account.account_name}</p>
                </div>
              `;
            });
            instructions += `</div>`;
          } else {
            instructions += `<p class="text-sm text-gray-700">No E-Wallet accounts configured.</p>`;
          }
          break;
        case 'bank':
          // Generate instructions for all Bank accounts
          instructions = `<p class="text-sm font-medium text-gray-700 mb-2">Bank Transfer Options:</p>`;
          if (bankAccounts.length > 0) {
            instructions += `<div class="space-y-2">`;
            bankAccounts.forEach(account => {
              instructions += `
                <div class="bg-white p-2 rounded border">
                  <p class="text-sm font-semibold text-gray-800">${account.bank_name}</p>
                  <p class="text-sm text-gray-700">Account: ${account.account_number}</p>
                  <p class="text-sm text-gray-700">Name: ${account.account_name}</p>
                </div>
              `;
            });
            instructions += `</div>`;
          } else {
            instructions += `<p class="text-sm text-gray-700">No Bank accounts configured.</p>`;
          }
          break;
        case 'cash':
          instructions = `
            <p class="text-sm font-medium text-gray-700 mb-2">Pay in person at:</p>
            <p class="text-sm font-semibold text-gray-800">${cashSettings.contact_person || 'TPLearn Office'}</p>
            <p class="text-sm font-semibold text-gray-800">${cashSettings.address || 'Office Address Not Available'}</p>
            <p class="text-sm text-gray-600 mt-2">Office hours: ${cashSettings.hours || 'Please contact for hours'}</p>
            ${cashSettings.phone_number ? `<p class="text-sm text-gray-600">Phone: ${cashSettings.phone_number}</p>` : ''}
            ${cashSettings.additional_instructions ? `<p class="text-sm text-gray-600 mt-2">${cashSettings.additional_instructions}</p>` : ''}
          `;
          break;
      }
      
      if (instructionsDiv) {
        instructionsDiv.innerHTML = instructions;
      }

      validateResubmitForm();
    }

    function validateResubmitForm() {
      const modal = document.getElementById('resubmit-modal');
      if (!modal) return;
      
      const referenceInput = modal.querySelector('#resubmitReferenceNumber');
      const fileInput = modal.querySelector('#resubmitReceiptUpload');
      const submitBtn = modal.querySelector('#resubmitSubmitBtn');

      const hasReference = referenceInput && referenceInput.value.trim() !== '';
      // Check both file input and stored file from drag & drop
      const hasFile = (fileInput && fileInput.files && fileInput.files.length > 0) || modal.selectedFile;
      const hasPaymentMethod = selectedResubmitPaymentMethod !== null && selectedResubmitPaymentMethod !== '';

      console.log('Validation check:', { 
        hasReference, 
        hasFile, 
        hasPaymentMethod, 
        selectedResubmitPaymentMethod,
        fileInputFiles: fileInput ? fileInput.files.length : 0,
        storedFile: modal.selectedFile ? modal.selectedFile.name : 'none'
      });

      if (submitBtn) {
        if (hasReference && hasFile && hasPaymentMethod) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
          submitBtn.classList.add('bg-tplearn-green', 'hover:bg-green-700');
          console.log('Submit button enabled');
        } else {
          submitBtn.disabled = true;
          submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
          submitBtn.classList.remove('bg-tplearn-green', 'hover:bg-green-700');
          console.log('Submit button disabled - missing:', { 
            reference: !hasReference, 
            file: !hasFile, 
            paymentMethod: !hasPaymentMethod 
          });
        }
      } else {
        console.error('Submit button not found!');
      }
    }

    function setupResubmitDragAndDrop() {
      const modal = document.getElementById('resubmit-modal');
      if (!modal) return;
      
      const dropZone = modal.querySelector('.resubmit-drag-drop');
      const fileInput = modal.querySelector('#resubmitReceiptUpload');

      if (!dropZone || !fileInput) return;

      // Store the selected file globally for the modal
      let selectedFile = null;

      dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-blue-400', 'bg-blue-50');
      });

      dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-blue-400', 'bg-blue-50');
      });

      dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-blue-400', 'bg-blue-50');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
          selectedFile = files[0];
          // Store file reference on the modal for later access
          modal.selectedFile = selectedFile;
          handleResubmitFileSelect(files[0]);
        }
      });

      fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
          selectedFile = e.target.files[0];
          modal.selectedFile = selectedFile;
          handleResubmitFileSelect(e.target.files[0]);
        }
      });
    }

    function handleResubmitFileSelect(file) {
      const modal = document.getElementById('resubmit-modal');
      if (!modal) return;
      
      // Validate file type
      const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
      if (!allowedTypes.includes(file.type)) {
        alert('Please upload only PNG, JPG, or JPEG files.');
        return;
      }

      // Validate file size (10MB max)
      const maxSize = 10 * 1024 * 1024;
      if (file.size > maxSize) {
        alert('File size must be less than 10MB.');
        return;
      }
      
      const dropZone = modal.querySelector('.resubmit-drag-drop');
      
      dropZone.innerHTML = `
        <div class="text-green-600">
          <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          <p class="font-medium">${file.name}</p>
          <p class="text-sm text-gray-500">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
          <button onclick="clearResubmitFile()" class="mt-2 px-3 py-1 bg-red-100 text-red-600 rounded text-sm hover:bg-red-200">Remove</button>
        </div>
      `;
      
      validateResubmitForm();
    }

    function clearResubmitFile() {
      const modal = document.getElementById('resubmit-modal');
      if (!modal) return;
      
      const fileInput = modal.querySelector('#resubmitReceiptUpload');
      const dropZone = modal.querySelector('.resubmit-drag-drop');
      
      if (fileInput) fileInput.value = '';
      
      if (dropZone) {
        dropZone.innerHTML = `
          <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
          </svg>
          <p class="text-gray-600 mb-2">Upload a file or drag and drop</p>
          <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
          <input type="file" class="hidden" id="resubmitReceiptUpload" accept="image/*">
          <button onclick="document.getElementById('resubmitReceiptUpload').click()" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Choose File
          </button>
        `;
        
        // Re-attach event listener for the new file input
        setupResubmitDragAndDrop();
      }
      
      validateResubmitForm();
    }

    function processResubmission(paymentId) {
      const modal = document.getElementById('resubmit-modal');
      if (!modal) return;
      
      const referenceInput = modal.querySelector('#resubmitReferenceNumber');
      const fileInput = modal.querySelector('#resubmitReceiptUpload');

      // Get file from either file input or stored file (drag & drop)
      const selectedFile = (fileInput && fileInput.files.length > 0) ? fileInput.files[0] : modal.selectedFile;

      if (!referenceInput || !referenceInput.value.trim() || !selectedFile || !selectedResubmitPaymentMethod) {
        alert('Please fill in all required fields and upload payment proof.');
        return;
      }

      console.log('Processing resubmission:', {
        paymentId: paymentId,
        referenceNumber: referenceInput.value,
        paymentMethod: selectedResubmitPaymentMethod,
        file: selectedFile
      });

      // Show processing state
      const submitBtn = modal.querySelector('#resubmitSubmitBtn');
      if (submitBtn) {
        submitBtn.innerHTML = `
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Processing...
        `;
        submitBtn.disabled = true;
      }

      // Create FormData for file upload
      const formData = new FormData();
      formData.append('payment_id', paymentId);
      formData.append('reference_number', referenceInput.value.trim());
      formData.append('payment_method', selectedResubmitPaymentMethod);
      formData.append('is_resubmission', 'true');
      formData.append('receipt', selectedFile);

      // Send to API
      fetch('/TPLearn/api/submit-payment.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Show success message
          const successModal = document.createElement('div');
          successModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
          successModal.innerHTML = `
            <div class="bg-white rounded-xl p-6 max-w-md w-full shadow-2xl">
              <div class="flex items-center justify-center mb-4">
                <div class="flex items-center justify-center w-12 h-12 bg-green-100 rounded-full">
                  <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                </div>
              </div>
              <h3 class="text-lg font-semibold text-center text-gray-900 mb-2">Payment Resubmitted!</h3>
              <div class="space-y-2 text-sm text-gray-600 mb-4">
                <p><strong>Payment ID:</strong> ${data.payment_id}</p>
                <p><strong>Reference Number:</strong> ${data.reference_number}</p>
                <p><strong>Amount:</strong> ₱${data.amount.toLocaleString()}</p>
                <p><strong>Status:</strong> Pending Validation</p>
              </div>
              <p class="text-sm text-gray-600 text-center mb-4">Your payment has been resubmitted and is now pending validation by an administrator.</p>
              <button onclick="this.closest('.fixed').remove(); location.reload();" 
                      class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                OK
              </button>
            </div>
          `;
          
          // Remove resubmit modal
          modal.remove();
          document.body.appendChild(successModal);
        } else {
          throw new Error(data.error || 'Failed to resubmit payment');
        }
      })
      .catch(error => {
        console.error('Resubmission error:', error);
        alert('Error resubmitting payment: ' + error.message);
        
        // Re-enable submit button
        if (submitBtn) {
          submitBtn.innerHTML = 'Submit Payment';
          submitBtn.disabled = false;
        }
      });
    }

    // Notification and message functions
    function openNotifications() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
      modal.innerHTML = `
        <div class="bg-white rounded-xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">Notifications</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3 overflow-y-auto max-h-64">
            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
              <h4 class="font-semibold text-red-800">Payment Overdue</h4>
              <p class="text-red-700 text-sm">Your Kindergarten Program payment is overdue. Please pay immediately.</p>
              <p class="text-red-600 text-xs mt-1">2 days ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    function openMessages() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
      modal.innerHTML = `
        <div class="bg-white rounded-xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">Messages</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3 overflow-y-auto max-h-64">
            <div class="bg-blue-50 border-l-4 border-blue-500 p-3">
              <h4 class="font-semibold text-blue-800">From: Finance Team</h4>
              <p class="text-blue-700 text-sm">Payment reminder for your enrolled programs.</p>
              <p class="text-blue-600 text-xs mt-1">1 day ago</p>
            </div>
            <div class="bg-green-50 border-l-4 border-green-500 p-3">
              <h4 class="font-semibold text-green-800">From: Academic Office</h4>
              <p class="text-green-700 text-sm">Payment confirmed for Reading Fundamentals program.</p>
              <p class="text-green-600 text-xs mt-1">3 days ago</p>
            </div>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3">
              <h4 class="font-semibold text-yellow-800">From: Support Team</h4>
              <p class="text-yellow-700 text-sm">Need help with payments? Contact our support team.</p>
              <p class="text-yellow-600 text-xs mt-1">1 week ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    // File handling functions for payment modal
    function handleFileSelectModal(event) {
      const file = event.target.files[0];
      if (file) {
        // Validate file type
        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
          alert('Please upload only PNG, JPG, or PDF files.');
          return;
        }

        // Validate file size (10MB max)
        const maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if (file.size > maxSize) {
          alert('File size must be less than 10MB.');
          return;
        }

        // Show selected file
        document.getElementById('selectedFile-modal').classList.remove('hidden');
        document.getElementById('fileName-modal').textContent = file.name;
        document.getElementById('fileSize-modal').textContent = formatFileSize(file.size);

        // Hide upload area
        document.getElementById('uploadArea-modal').style.display = 'none';
        
        // Validate the form to enable/disable submit button
        validatePaymentForm();
      }
    }

    function removeFileModal() {
      document.getElementById('selectedFile-modal').classList.add('hidden');
      document.getElementById('uploadArea-modal').style.display = 'block';
      document.getElementById('fileInput-modal').value = '';
      
      // Validate the form to disable submit button since file is removed
      validatePaymentForm();
    }

    // Payment Details Modal Functions
    function showDetailsModal(paymentId) {
      const modal = document.getElementById('detailsModal');
      const detailsContent = document.getElementById('detailsContent');
      
      // Extract actual payment ID from formatted payment_id (PAY-YYYYMMDD-XXX)
      let actualPaymentId = paymentId;
      const matches = paymentId.toString().match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }
      
      // Show loading state
      detailsContent.innerHTML = `
        <div class="flex items-center justify-center p-8">
          <div class="text-center">
            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-600">Loading payment details...</p>
          </div>
        </div>
      `;
      
      // Show modal
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      
      // Load payment details from API
      fetch(`../../api/payments.php?action=get_payment_details&id=${actualPaymentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.payment) {
            const payment = data.payment;
            
            // Format dates properly
            const formatDateTime = (dateString) => {
              if (!dateString || dateString === '0000-00-00 00:00:00') return 'N/A';
              const date = new Date(dateString);
              return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
              }) + ' at ' + date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
              });
            };
            
            detailsContent.innerHTML = `
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Payment Overview Panel -->
                <div class="space-y-6">
                  <!-- Payment Information -->
                  <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl">
                    <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                      <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"></path>
                      </svg>
                      Payment Information
                    </h3>
                    <div class="space-y-3 text-sm">
                      <div>
                        <span class="text-blue-700 font-medium">Payment ID:</span>
                        <div class="text-blue-900 font-semibold">${paymentId}</div>
                      </div>
                      <div>
                        <span class="text-blue-700 font-medium">Amount:</span>
                        <div class="text-blue-900 font-semibold text-lg">₱${parseFloat(payment.amount || 0).toLocaleString()}</div>
                      </div>
                      ${payment.payment_method ? `
                      <div>
                        <span class="text-blue-700 font-medium">Payment Method:</span>
                        <div class="text-blue-900">${getPaymentMethodDisplay(payment.payment_method)}</div>
                      </div>
                      ` : ''}
                      ${payment.reference_number ? `
                      <div>
                        <span class="text-blue-700 font-medium">Reference Number:</span>
                        <div class="text-blue-900">${payment.reference_number}</div>
                      </div>
                      ` : ''}
                      <div>
                        <span class="text-blue-700 font-medium">Status:</span>
                        <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-1 ${
                          payment.status === 'validated' ? 'bg-green-100 text-green-800' :
                          payment.status === 'rejected' ? 'bg-red-100 text-red-800' :
                          payment.status === 'pending' && payment.reference_number ? 'bg-yellow-100 text-yellow-800' :
                          'bg-blue-100 text-blue-800'
                        }">
                          ${payment.status === 'validated' ? '✓ Validated' :
                            payment.status === 'rejected' ? '✗ Rejected' :
                            payment.status === 'pending' && payment.reference_number ? '⏳ Pending Validation' :
                            '💳 Awaiting Payment'}
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Student Information -->
                  <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-xl">
                    <h3 class="text-lg font-semibold text-green-900 mb-4 flex items-center">
                      <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                      </svg>
                      Student Information
                    </h3>
                    <div class="space-y-3 text-sm">
                      <div>
                        <span class="text-green-700 font-medium">Full Name:</span>
                        <div class="text-green-900 text-base">${payment.student_name || 'N/A'}</div>
                      </div>
                      <div>
                        <span class="text-green-700 font-medium">Student ID:</span>
                        <div class="text-green-900">${payment.student_id || 'N/A'}</div>
                      </div>
                      <div>
                        <span class="text-green-700 font-medium">Program Enrolled:</span>
                        <div class="text-green-900 text-base font-medium">${payment.program_name || 'N/A'}</div>
                      </div>
                      ${payment.installment_number ? `
                      <div>
                        <span class="text-green-700 font-medium">Installment:</span>
                        <div class="text-green-900">${payment.installment_number} of ${payment.total_installments || 1}</div>
                      </div>
                      ` : ''}
                    </div>
                  </div>
                </div>

                <!-- Timeline & Status Panel -->
                <div class="space-y-6">
                  <!-- Payment Timeline -->
                  <div class="bg-white border border-gray-200 p-6 rounded-xl">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                      <svg class="w-6 h-6 mr-3 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                      </svg>
                      Payment Timeline
                    </h3>
                    <div class="space-y-4">
                      <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                          <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                        </div>
                        <div>
                          <div class="text-sm font-medium text-gray-900">Payment Created</div>
                          <div class="text-sm text-gray-600">${formatDateTime(payment.created_at)}</div>
                        </div>
                      </div>
                      
                      ${payment.payment_date && payment.payment_date !== '0000-00-00 00:00:00' ? `
                        <div class="flex items-start space-x-3">
                          <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-yellow-500 rounded-full mt-2"></div>
                          </div>
                          <div>
                            <div class="text-sm font-medium text-gray-900">Payment Submitted</div>
                            <div class="text-sm text-gray-600">${formatDateTime(payment.payment_date)}</div>
                          </div>
                        </div>
                      ` : ''}
                      
                      ${payment.status === 'validated' && payment.validated_at ? `
                        <div class="flex items-start space-x-3">
                          <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                          </div>
                          <div>
                            <div class="text-sm font-medium text-gray-900">Payment Validated</div>
                            <div class="text-sm text-gray-600">${formatDateTime(payment.validated_at)}</div>
                            ${payment.validator_name ? `<div class="text-sm text-gray-500">by ${payment.validator_name}</div>` : ''}
                          </div>
                        </div>
                      ` : payment.status === 'rejected' && payment.validated_at ? `
                        <div class="flex items-start space-x-3">
                          <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-red-500 rounded-full mt-2"></div>
                          </div>
                          <div>
                            <div class="text-sm font-medium text-gray-900">Payment Rejected</div>
                            <div class="text-sm text-gray-600">${formatDateTime(payment.validated_at)}</div>
                            ${payment.validator_name ? `<div class="text-sm text-gray-500">by ${payment.validator_name}</div>` : ''}
                          </div>
                        </div>
                      ` : ''}
                    </div>
                  </div>

                  <!-- Additional Details -->
                  <div class="bg-gray-50 p-6 rounded-xl">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                      <svg class="w-6 h-6 mr-3 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                      </svg>
                      Additional Details
                    </h3>
                    <div class="space-y-3 text-sm">
                      ${payment.due_date ? `
                        <div>
                          <span class="text-gray-700 font-medium">Due Date:</span>
                          <div class="text-gray-900">${formatDateTime(payment.due_date)}</div>
                        </div>
                      ` : ''}
                      
                      ${payment.notes ? `
                        <div>
                          <span class="text-gray-700 font-medium">${payment.status === 'rejected' ? 'Rejection Reason:' : 'Admin Notes:'}</span>
                          <div class="text-gray-900 ${payment.status === 'rejected' ? 'bg-red-50 border border-red-200' : 'bg-white'} p-3 rounded-lg border mt-1">${payment.notes}</div>
                        </div>
                      ` : ''}

                      <!-- Payment Proof - Only show if payment has reference number (was submitted) -->
                      ${payment.reference_number ? `
                      <div class="pt-3 border-t border-gray-200">
                        <span class="text-gray-700 font-medium">Payment Proof:</span>
                        <div id="detailsPaymentProofContainer" class="mt-3">
                          <div class="flex items-center justify-between p-4 bg-white border-2 border-blue-200 rounded-lg">
                            <div class="flex items-center space-x-3">
                              <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                  <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                </svg>
                              </div>
                              <div>
                                <p class="text-sm font-medium text-gray-800" id="detailsReceiptName">Loading...</p>
                                <p class="text-xs text-gray-500">Uploaded payment proof</p>
                              </div>
                            </div>
                            <button onclick="openDetailsPaymentProofFullsize()" class="px-3 py-1 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                              View Full Size
                            </button>
                          </div>
                        </div>
                      </div>
                      ` : ''}
                    </div>
                  </div>
                </div>
              </div>
            `;
            
            // Load the actual payment proof attachment data
            if (payment.reference_number) {
              loadStudentPaymentProof(paymentId);
            }
          } else {
            detailsContent.innerHTML = `
              <div class="flex items-center justify-center p-8">
                <div class="text-center">
                  <svg class="w-16 h-16 text-red-400 mb-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  <p class="text-gray-600 mb-2">Failed to load payment details</p>
                  <p class="text-sm text-gray-500">${data.error || 'Unknown error'}</p>
                </div>
              </div>
            `;
          }
        })
        .catch(error => {
          console.error('Error loading payment details:', error);
          detailsContent.innerHTML = `
            <div class="flex items-center justify-center p-8">
              <div class="text-center">
                <svg class="w-16 h-16 text-red-400 mb-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-gray-600 mb-2">Error loading payment details</p>
                <p class="text-sm text-gray-500">${error.message}</p>
              </div>
            </div>
          `;
        });
    }
    
    function viewPaymentProof(paymentId) {
      console.log('Opening payment proof for payment ID:', paymentId);
      
      // Extract actual payment ID if needed
      let actualPaymentId = paymentId;
      const matches = paymentId.toString().match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }

      // Fetch payment proof information first
      fetch(`../../api/payments.php?action=get_receipt_attachment&payment_id=${actualPaymentId}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.attachment) {
            showPaymentProofModal(data.attachment, actualPaymentId);
          } else {
            alert('Payment proof not found or error loading attachment: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error loading payment proof:', error);
          alert('Error loading payment proof: ' + error.message);
        });
    }

    function showPaymentProofModal(attachment, paymentId) {
      // Create modal for displaying payment proof image
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4';
      modal.innerHTML = `
        <div class="max-w-5xl w-full bg-white rounded-lg overflow-hidden shadow-2xl flex flex-col" style="max-height: 90vh;">
          <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50 flex-shrink-0">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Payment Proof</h3>
                <p class="text-sm text-gray-600">${attachment.original_name || attachment.filename || 'Receipt Image'}</p>
                ${attachment.file_size ? `<p class="text-xs text-gray-500">${formatFileSize(attachment.file_size)}</p>` : ''}
              </div>
            </div>
            <button onclick="this.closest('.fixed').remove(); document.body.style.overflow = 'auto';" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
              </svg>
            </button>
          </div>
          
          <div class="p-4 overflow-auto flex items-center justify-center bg-gray-100 flex-1 min-h-0">
            <div class="max-w-full max-h-full">
              ${attachment.mime_type && attachment.mime_type.startsWith('image/') ? 
                `<img src="../../api/serve-receipt.php?id=${attachment.id}" 
                     alt="Payment Proof" 
                     class="max-w-full max-h-full object-contain rounded-lg shadow-lg"
                     onload="console.log('Payment proof image loaded successfully')"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                 <div style="display: none;" class="text-center p-8">
                   <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                   </svg>
                   <p class="text-gray-600">Error loading image</p>
                   <p class="text-sm text-gray-500">The payment proof image could not be loaded</p>
                 </div>` :
                `<div class="text-center p-12">
                   <svg class="w-20 h-20 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                     <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                   </svg>
                 </div>`
              }
            </div>
          </div>
          
          <div class="flex justify-between items-center p-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <div class="text-sm text-gray-500">
              ${attachment.mime_type ? `Type: ${attachment.mime_type}` : ''}
              ${attachment.upload_date ? ` • Uploaded: ${new Date(attachment.upload_date).toLocaleDateString()}` : ''}
            </div>
            <div class="flex space-x-3">
              <button onclick="this.closest('.fixed').remove(); document.body.style.overflow = 'auto';" 
                      class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                Close
              </button>
              <a href="../../api/serve-receipt.php?id=${attachment.id}" target="_blank"
                 class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                </svg>
                Download
              </a>
            </div>
          </div>
        </div>
      `;

      document.body.appendChild(modal);
      document.body.style.overflow = 'hidden';

      // Close modal when clicking outside
      modal.addEventListener('click', function(e) {
        if (e.target === modal) {
          modal.remove();
          document.body.style.overflow = 'auto';
        }
      });

      // Close with ESC key
      const handleEscKey = function(e) {
        if (e.key === 'Escape') {
          modal.remove();
          document.body.style.overflow = 'auto';
          document.removeEventListener('keydown', handleEscKey);
        }
      };
      document.addEventListener('keydown', handleEscKey);
    }

    function loadStudentPaymentProof(paymentId) {
      console.log('Loading payment proof for details modal:', paymentId);
      
      // Extract numeric payment ID for API call
      let actualPaymentId = paymentId;
      const matches = paymentId.toString().match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }

      const receiptName = document.getElementById('detailsReceiptName');
      if (receiptName) {
        receiptName.textContent = 'Loading...';
      }

      console.log('Fetching payment proof for payment:', actualPaymentId);
      const apiUrl = `../../api/payments.php?action=get_receipt_attachment&payment_id=${encodeURIComponent(actualPaymentId)}`;
      console.log('API URL:', apiUrl);

      // Fetch payment proof info
      fetch(apiUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => {
          console.log('API Response status:', response.status);
          return response.json();
        })
        .then(data => {
          console.log('API Response data:', data);
          if (data.success && data.attachment) {
            const attachment = data.attachment;
            
            // Store attachment globally for fullsize view
            window.currentDetailsAttachment = attachment;
            window.currentDetailsPaymentId = paymentId;
            
            if (receiptName) {
              receiptName.textContent = attachment.original_name || attachment.filename || 'Receipt Image';
            }
            
            console.log('Payment proof loaded successfully');
          } else {
            // No attachment found or error
            if (receiptName) {
              receiptName.textContent = 'No payment proof found';
            }
            
            const container = document.getElementById('detailsPaymentProofContainer');
            if (container) {
              container.innerHTML = `
                <div class="flex items-center justify-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg">
                  <div class="text-center">
                    <div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center mx-auto mb-2">
                      <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                      </svg>
                    </div>
                    <p class="text-sm text-gray-600">No payment proof found</p>
                    <p class="text-xs text-gray-500">Student has not uploaded payment proof</p>
                  </div>
                </div>
              `;
            }
          }
        })
        .catch(error => {
          console.error('Error loading receipt:', error);
          console.error('Full error details:', error.message, error.stack);
          
          if (receiptName) {
            receiptName.textContent = 'Error loading payment proof';
          }
          
          const container = document.getElementById('detailsPaymentProofContainer');
          if (container) {
            container.innerHTML = `
              <div class="flex items-center justify-center p-4 bg-red-50 border-2 border-red-200 rounded-lg">
                <div class="text-center">
                  <div class="w-10 h-10 bg-red-200 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                    </svg>
                  </div>
                  <p class="text-sm text-red-700">Error loading payment proof</p>
                  <p class="text-xs text-red-600">Error: ${error.message}</p>
                  <p class="text-xs text-red-600">Please check console for details</p>
                </div>
              </div>
            `;
          }
        });
    }

    // Function to open payment proof in fullsize for details modal
    function openDetailsPaymentProofFullsize() {
      const attachment = window.currentDetailsAttachment;
      if (!attachment) {
        alert('No payment proof available');
        return;
      }

      const paymentId = window.currentDetailsPaymentId;
      
      // Extract numeric payment ID from formatted string
      let actualPaymentId = paymentId;
      const matches = paymentId?.match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }

      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4';
      
      modal.innerHTML = `
        <div class="max-w-5xl w-full bg-white rounded-lg overflow-hidden shadow-2xl flex flex-col" style="max-height: 90vh;">
          <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50 flex-shrink-0">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Payment Proof</h3>
                <p class="text-sm text-gray-600">${attachment.original_name || attachment.filename || 'Receipt Image'}</p>
                ${attachment.file_size ? `<p class="text-xs text-gray-500">${formatFileSize(attachment.file_size)}</p>` : ''}
              </div>
            </div>
            <button onclick="this.closest('.fixed').remove(); document.body.style.overflow = 'auto';" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
              </svg>
            </button>
          </div>
          
          <div class="p-4 overflow-auto flex items-center justify-center bg-gray-100 flex-1 min-h-0">
            <div class="max-w-full max-h-full">
              ${attachment.mime_type && attachment.mime_type.startsWith('image/') ? 
                `<img src="../../api/serve-receipt.php?id=${attachment.id}" 
                     alt="Payment Proof" 
                     class="max-w-full max-h-full object-contain rounded-lg shadow-lg"
                     onload="console.log('Payment proof image loaded successfully')"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                 <div style="display: none;" class="text-center p-8">
                   <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                   </svg>
                   <p class="text-gray-600">Error loading image</p>
                   <p class="text-sm text-gray-500">The payment proof image could not be loaded</p>
                 </div>` :
                `<div class="text-center p-12">
                   <svg class="w-20 h-20 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                     <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                   </svg>
                   <p class="text-xl text-gray-600 mb-2">${attachment.original_name}</p>
                   <p class="text-sm text-gray-500 mb-6">File cannot be previewed</p>
                 </div>`
              }
            </div>
          </div>
          
          <div class="flex justify-between items-center p-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <div class="text-sm text-gray-500">
              ${attachment.mime_type ? `Type: ${attachment.mime_type}` : ''}
              ${attachment.upload_date ? ` • Uploaded: ${new Date(attachment.upload_date).toLocaleDateString()}` : ''}
            </div>
            <div class="flex space-x-3">
              <button onclick="this.closest('.fixed').remove(); document.body.style.overflow = 'auto';" 
                      class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                Close
              </button>
              <a href="../../api/serve-receipt.php?id=${attachment.id}" target="_blank"
                 class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                </svg>
                Download
              </a>
            </div>
          </div>
        </div>
      `;

      document.body.appendChild(modal);
      document.body.style.overflow = 'hidden';
    }

    function closeDetailsModal() {
      const modal = document.getElementById('detailsModal');
      modal.classList.add('hidden');
      document.body.style.overflow = 'auto';
    }

    function downloadReceipt(paymentId) {
      // Show the receipt modal and populate it with payment data
      showReceiptModal(paymentId);
    }

    function showReceiptModal(paymentId) {
      // Extract actual payment ID from formatted payment_id (PAY-YYYYMMDD-XXX)
      let actualPaymentId = paymentId;
      const matches = paymentId.toString().match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }

      // Store payment ID for PDF generation
      window.currentReceiptPaymentId = actualPaymentId;

      // Load payment details from API
      fetch(`../../api/payments.php?action=get_payment_details&id=${actualPaymentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const payment = data.payment;
            
            // Generate receipt number based on payment date and ID
            const paymentDate = payment.payment_date || new Date().toISOString().split('T')[0];
            const dateFormatted = paymentDate.replace(/-/g, '');
            const receiptNum = `REC-${dateFormatted}-${actualPaymentId.toString().padStart(3, '0')}`;
            
            // Populate receipt fields
            document.getElementById('receiptNumber').textContent = receiptNum;
            document.getElementById('receiptDate').textContent = formatReceiptDate(payment.payment_date) || formatReceiptDate(new Date().toISOString().split('T')[0]);
            document.getElementById('receiptStudentName').textContent = payment.student_name || 'N/A';
            document.getElementById('receiptStudentId').textContent = payment.student_id || 'N/A';
            document.getElementById('receiptPaymentId').textContent = payment.payment_id || paymentId;
            document.getElementById('receiptProgramName').textContent = payment.program_name || 'N/A';
            document.getElementById('receiptInstallment').textContent = `${payment.installment_number || 1} of ${payment.total_installments || 1}`;
            document.getElementById('receiptAmount').textContent = `₱${parseFloat(payment.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            document.getElementById('receiptPaymentMethod').textContent = getPaymentMethodDisplay(payment.payment_method);
            document.getElementById('receiptReferenceNumber').textContent = payment.reference_number || 'N/A';
            
            // Update the generated date in footer
            document.getElementById('receiptGeneratedDate').textContent = new Date().toLocaleString();

            // Store payment data for PDF generation
            window.currentReceiptData = payment;

            // Show the modal
            document.getElementById('receiptModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
          } else {
            alert('Failed to load payment details: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error loading payment details:', error);
          alert('Failed to load payment details. Please try again.');
        });
    }

    function formatReceiptDate(dateStr) {
      if (!dateStr || dateStr === '0000-00-00') return 'N/A';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { 
        year: 'numeric',
        month: 'long', 
        day: 'numeric'
      });
    }

    function closeReceiptModal() {
      document.getElementById('receiptModal').classList.add('hidden');
      document.body.style.overflow = 'auto';
      window.currentReceiptPaymentId = null;
      window.currentReceiptData = null;
    }

    function printReceipt() {
      const receiptContent = document.getElementById('receiptContent');
      const printWindow = window.open('', '_blank');

      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>TPLearn Payment Receipt</title>
          <style>
            body { 
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
              margin: 20px; 
              line-height: 1.6; 
              color: #333;
            }
            .receipt-container { 
              max-width: 700px; 
              margin: 0 auto; 
              padding: 20px; 
              border: 2px solid #10b981;
              border-radius: 10px;
            }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .text-right { text-align: right; }
            .text-2xl { font-size: 1.5rem; font-weight: bold; }
            .text-lg { font-size: 1.125rem; font-weight: bold; }
            .text-sm { font-size: 0.875rem; }
            .text-xs { font-size: 0.75rem; }
            .mb-2 { margin-bottom: 0.5rem; }
            .mb-3 { margin-bottom: 0.75rem; }
            .mb-4 { margin-bottom: 1rem; }
            .mb-6 { margin-bottom: 1.5rem; }
            .mb-8 { margin-bottom: 2rem; }
            .mt-4 { margin-top: 1rem; }
            .mt-6 { margin-top: 1.5rem; }
            .pt-4 { padding-top: 1rem; }
            .pt-6 { padding-top: 1.5rem; }
            .p-6 { padding: 1.5rem; }
            .grid-cols-2 { 
              display: grid; 
              grid-template-columns: 1fr 1fr; 
              gap: 2rem; 
            }
            .border-t { 
              border-top: 2px solid #e5e7eb; 
              padding-top: 1rem; 
            }
            .border-b { 
              border-bottom: 2px solid #e5e7eb; 
              padding-bottom: 1rem; 
            }
            .font-bold { font-weight: bold; }
            .font-medium { font-weight: 500; }
            .font-semibold { font-weight: 600; }
            .font-mono { font-family: 'Courier New', monospace; }
            .bg-tplearn-green { 
              background-color: #10b981; 
              color: white;
              width: 4rem;
              height: 4rem;
              border-radius: 50%;
              display: inline-flex;
              align-items: center;
              justify-content: center;
              margin: 0 auto;
            }
            .text-tplearn-green { color: #10b981; }
            .bg-gray-50 { 
              background-color: #f9fafb; 
              padding: 1.5rem;
              border-radius: 8px;
              border: 1px solid #e5e7eb;
            }
            .bg-green-50 { 
              background-color: #f0fdf4; 
              padding: 1.5rem;
              border-radius: 8px;
              border: 1px solid #bbf7d0;
            }
            .text-gray-800 { color: #1f2937; }
            .text-gray-700 { color: #374151; }
            .text-gray-600 { color: #4b5563; }
            .text-gray-500 { color: #6b7280; }
            .text-gray-400 { color: #9ca3af; }
            .flex { display: flex; }
            .items-center { align-items: center; }
            .justify-center { justify-content: center; }
            .justify-between { justify-content: space-between; }
            .rounded-lg { border-radius: 8px; }
            .border-green-300 { border-color: #86efac; }
            .border-gray-300 { border-color: #d1d5db; }
            .border-gray-200 { border-color: #e5e7eb; }
            @media print {
              body { margin: 0; }
              .no-print { display: none; }
              .receipt-container { border: 1px solid #333; }
            }
          </style>
        </head>
        <body>
          <div class="receipt-container">
            ${receiptContent.innerHTML}
          </div>
        </body>
        </html>
      `);

      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
      printWindow.close();
    }

    function downloadReceiptPDF() {
      const paymentId = window.currentReceiptPaymentId;
      const paymentData = window.currentReceiptData;

      if (!paymentId || !paymentData) {
        alert('No payment data available for PDF generation.');
        return;
      }

      // For now, redirect to the API endpoint that generates the receipt
      const receiptWindow = window.open(
        `../../api/payments.php?action=generate_receipt&payment_id=${paymentId}`,
        '_blank',
        'width=800,height=600,scrollbars=yes,resizable=yes'
      );
      
      if (!receiptWindow) {
        alert('Please allow popups to download the receipt.');
      }
    }

    function getStatusBadge(status, referenceNumber) {
      switch (status) {
        case 'validated':
          return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">✓ Validated</span>';
        case 'rejected':
          return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">✗ Rejected</span>';
        case 'pending':
          if (referenceNumber) {
            return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">⏳ Pending Validation</span>';
          } else {
            return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">💳 Awaiting Payment</span>';
          }
        default:
          return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' + (status || 'Unknown') + '</span>';
      }
    }

    function formatDate(dateStr) {
      if (!dateStr || dateStr === '0000-00-00') return 'N/A';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric'
      });
    }

    function formatDateTime(dateStr) {
      if (!dateStr || dateStr === '0000-00-00 00:00:00') return 'N/A';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
      });
    }

    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Initialize with Make Payment tab active
    window.addEventListener('DOMContentLoaded', function() {
      switchTab('make-payment');
      
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
  </script>
</body>

</html>