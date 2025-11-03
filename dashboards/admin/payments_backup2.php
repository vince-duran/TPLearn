<?php
require_once __DIR__ . '/../../assets/icons.php';
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('admin');

// Handle filter parameters
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

// Get payment data
$payments = getPayments($status_filter ?: null, null, $search_filter ?: null);
$payment_stats = getPaymentStats();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments - Admin Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* Custom styles */
    .stat-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .welcome-card {
      background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
      border-radius: 16px;
      position: relative;
      overflow: hidden;
    }

    .program-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease;
    }

    .program-card:hover {
      transform: translateY(-2px);
    }

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
      background-color: #fee2e2;
      color: #991b1b;
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

    .installment-locked {
      background-color: #f3f4f6;
      color: #6b7280;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/header.php';
      renderHeader(
        'Payments',
        '',
        'admin',
        $_SESSION['name'] ?? 'Admin',
        []
      );
      ?>

      <!-- Main Content Area -->
      <main class="p-6">
        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
          <div class="p-4">
            <form method="GET" action="" class="flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <!-- Search Input -->
                <div class="relative">
                  <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                  </svg>
                  <input type="text" name="search" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Search payments..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent w-64">
                </div>

                <!-- Status Filter -->
                <div class="relative">
                  <select name="status" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" onchange="this.form.submit()">
                    <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
                    <option value="validated" <?php echo $status_filter === 'validated' ? 'selected' : ''; ?>>Validated</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="pending_validation" <?php echo $status_filter === 'pending_validation' ? 'selected' : ''; ?>>Pending Validation</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                  </select>
                  <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>

              <!-- Clear Filters Button -->
              <a href="?" class="px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                Clear Filters
              </a>
            </form>
          </div>
        </div>

        <!-- Payments Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Payment Management</h2>
            <p class="text-sm text-gray-600 mt-1">Review and manage student payments</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Installment</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($payments)): ?>
                  <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                      <div class="flex flex-col items-center">
                        <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="font-medium">No payments found</p>
                        <p class="text-sm">No payment records match your current filters</p>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($payments as $payment): ?>
                    <?php
                    // Generate student initials
                    $name_parts = explode(' ', $payment['student_name']);
                    $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

                    // Determine status styling with overdue detection
                    $payment_status = $payment['payment_status'] ?? $payment['status'];

                    // Determine status display properties
                    $statusColor = 'gray';
                    $statusText = 'Unknown';
                    $statusClass = 'status-unknown';
                    $urgencyClass = '';

                    switch ($payment_status) {
                      case 'validated':
                        $statusColor = 'green';
                        $statusText = 'Validated';
                        $statusClass = 'status-validated';
                        $urgencyClass = '';
                        break;
                      case 'overdue':
                        $statusColor = 'red';
                        $statusText = 'Overdue';
                        if (isset($payment['days_overdue']) && $payment['days_overdue'] > 0) {
                          $statusText = "Overdue ({$payment['days_overdue']} days)";
                        }
                        $statusClass = 'status-overdue';
                        $urgencyClass = 'bg-red-50 border-l-4 border-red-400';
                        break;
                      case 'due_today':
                        $statusColor = 'orange';
                        $statusText = 'Due Today';
                        $statusClass = 'status-due-today';
                        $urgencyClass = 'bg-orange-50 border-l-4 border-orange-400';
                        break;
                      case 'rejected':
                        $statusColor = 'red';
                        $statusText = 'Rejected';
                        $statusClass = 'status-rejected';
                        $urgencyClass = 'bg-red-50 border-l-4 border-red-400';
                        break;
                      case 'pending_validation':
                      case 'pending_verification':
                        $statusColor = 'yellow';
                        $statusText = 'Pending Validation';
                        $statusClass = 'status-pending-validation';
                        $urgencyClass = 'bg-yellow-50 border-l-4 border-yellow-400';
                        break;
                      case 'pending':
                      case 'pending_payment':
                        $statusColor = 'blue';
                        $statusText = 'Payment Due';
                        $statusClass = 'status-pending';
                        $urgencyClass = 'bg-blue-50 border-l-4 border-blue-400';
                        break;
                      default:
                        $statusColor = 'gray';
                        $statusText = ucfirst($payment['status']);
                        $statusClass = 'status-unknown';
                        $urgencyClass = 'bg-gray-50 border-l-4 border-gray-400';
                    }

                    // Format dates
                    $payment_date = date('n/j/Y', strtotime($payment['payment_date']));
                    $due_date = $payment['due_date'] ? date('n/j/Y', strtotime($payment['due_date'])) : date('n/j/Y', strtotime($payment['payment_date']));

                    // Create action buttons based on status
                    $actionButton = '';
                    if ($payment_status === 'pending_validation' || $payment_status === 'pending_verification') {
                      $actionButton = '<div class="flex space-x-3">
                                        <button onclick="showValidateModal(\'' . htmlspecialchars($payment['payment_id']) . '\', this.closest(\'tr\'))" class="text-green-600 hover:text-green-800 text-xs font-medium">Validate</button>
                                        <button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\', this.closest(\'tr\'))" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                                      </div>';
                    } elseif ($payment['status'] === 'validated') {
                      $actionButton = '<div class="flex space-x-3">
                                        <button onclick="showReceiptModal(\'' . htmlspecialchars($payment['payment_id']) . '\', this.closest(\'tr\'))" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Receipt</button>
                                        <button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\', this.closest(\'tr\'))" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                                      </div>';
                    } elseif ($payment['status'] === 'rejected') {
                      $actionButton = '<div class="flex space-x-3">
                                        <button onclick="triggerStudentResubmit(\'' . htmlspecialchars($payment['payment_id']) . '\', \'' . addslashes(htmlspecialchars($payment['program_name'])) . '\', ' . floatval($payment['amount']) . ')" class="text-orange-600 hover:text-orange-800 text-xs font-medium">Resubmit</button>
                                        <button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\', this.closest(\'tr\'))" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                                      </div>';
                    } else {
                      $actionButton = '<button onclick="showDetailsModal(\'' . htmlspecialchars($payment['payment_id']) . '\', this.closest(\'tr\'))" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>';
                    }
                    ?>
                    <tr class="payment-row <?php echo $urgencyClass; ?>">
                      <!-- Payment ID -->
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                        <?php echo htmlspecialchars($payment['payment_id']); ?>
                      </td>
                      
                      <!-- Date -->
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center text-sm text-gray-900">
                          <svg class="w-4 h-4 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                          </svg>
                          <?php echo htmlspecialchars($payment_date); ?>
                        </div>
                      </td>

                      <!-- Student -->
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                            <span class="text-xs font-medium text-gray-600"><?= $initials ?></span>
                          </div>
                          <div>
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($payment['student_name'] ?? 'Unknown Student') ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($payment['student_user_id'] ?? '') ?></div>
                          </div>
                        </div>
                      </td>
                      
                      <!-- Amount -->
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ₱<?php echo number_format($payment['amount'], 2); ?>
                      </td>
                      
                      <!-- Program -->
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($payment['program_name'] ?: 'N/A'); ?>
                      </td>
                      
                      <!-- Installment -->
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $installmentNumber = $payment['installment_number'] ?? 1;
                        $totalInstallments = $payment['total_installments'] ?? 1;

                        // Determine installment badge color based on status
                        $installmentBadgeClass = 'installment-pending';
                        if ($payment['status'] === 'validated') {
                          $installmentBadgeClass = 'installment-completed';
                        } elseif ($payment_status === 'overdue') {
                          $installmentBadgeClass = 'installment-locked';
                        }
                        ?>
                        <?php if ($totalInstallments > 1): ?>
                          <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium installment-badge <?php echo $installmentBadgeClass; ?>">
                              <?php echo htmlspecialchars($installmentNumber . ' of ' . $totalInstallments); ?>
                            </span>
                          </div>
                        <?php else: ?>
                          <span class="text-sm text-gray-500">Full Payment</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Due Date -->
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="flex items-center">
                          <svg class="w-4 h-4 text-gray-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                          </svg>
                          <?php echo htmlspecialchars($due_date); ?>
                        </div>
                      </td>
                      
                      <!-- Status -->
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center justify-between">
                          <div class="flex items-center">
                            <div class="w-2 h-2 bg-<?php echo $statusColor; ?>-400 rounded-full mr-2"></div>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                          </div>
                          <?php 
                          // Add proof indicator
                          $hasProof = hasPaymentAttachment($payment['id']);
                          if ($hasProof): ?>
                            <div class="flex items-center" title="Payment proof uploaded">
                              <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                              </svg>
                            </div>
                          <?php else: ?>
                            <div class="flex items-center" title="No payment proof">
                              <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                              </svg>
                            </div>
                          <?php endif; ?>
                        </div>
                      </td>
                      
                      <!-- Action -->
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

        <!-- Pagination -->
        <div class="mt-6 flex items-center justify-between">
          <div class="text-sm text-gray-700">
            Showing <span class="font-medium"><?= count($payments) > 0 ? 1 : 0 ?></span> to <span class="font-medium"><?= count($payments) ?></span> of <span class="font-medium"><?= count($payments) ?></span> results
          </div>
          <div class="flex items-center space-x-2">
            <button class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-500 hover:bg-gray-50">Previous</button>
            <button class="px-3 py-1 bg-tplearn-green text-white rounded text-sm">1</button>
            <button class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50">2</button>
            <button class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50">3</button>
            <button class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50">Next</button>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Validate Payment Modal -->
  <div id="validateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[95vh] overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-800">Validate Payment</h2>
        <button onclick="closeValidateModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6">
        <!-- Payment Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <!-- Student -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Student</label>
            <p id="validateStudentName" class="text-sm text-gray-800 font-medium">Emma Torres</p>
          </div>

          <!-- Program -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Program</label>
            <p id="validateProgramName" class="text-sm text-gray-800 font-medium">Computer Programming</p>
          </div>

          <!-- Payment ID -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment ID</label>
            <p id="validatePaymentId" class="text-sm text-gray-800 font-mono">PAY-20230510-008</p>
          </div>

          <!-- Payment Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
            <p id="validatePaymentDate" class="text-sm text-gray-800">2023-05-15</p>
          </div>
        </div>

        <!-- Payment Amount -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Amount</label>
          <p id="validatePaymentAmount" class="text-2xl font-bold text-purple-600">₱6,000</p>
        </div>

        <!-- Reference Number -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
          <p id="validateReferenceNumber" class="text-sm text-gray-800 font-mono">-</p>
        </div>

        <!-- Payment Proof -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Payment Proof</label>
          <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
            <!-- Receipt file info -->
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center">
                <svg class="w-8 h-8 text-blue-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <div>
                  <p id="validateReceiptName" class="text-sm font-medium text-gray-800">payment_receipt.jpg</p>
                  <p class="text-xs text-gray-500">Uploaded payment proof</p>
                </div>
              </div>
              <button onclick="openProofFullsize()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View Full Size
              </button>
            </div>
            
            <!-- Image preview -->
            <div id="validateReceiptPreview" class="mt-3 rounded-lg overflow-hidden border border-gray-200 bg-white">
              <img id="validateReceiptImage" src="" alt="Payment Receipt" 
                   class="w-full max-h-96 object-contain" style="display: none;"
                   onerror="this.style.display='none'; document.getElementById('validateReceiptError').style.display='block';">
              <div id="validateReceiptError" class="p-4 text-center text-gray-500" style="display: none;">
                <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <p>Payment proof preview not available</p>
                <p class="text-xs">Click "View Full Size" to open payment proof</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Method -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
          <p id="validatePaymentMethod" class="text-sm text-gray-800">Bank Transfer</p>
        </div>

        <!-- Validation Notes -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Validation Notes (Optional)</label>
          <textarea id="validationNotes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-transparent" placeholder="Add notes about this payment validation..."></textarea>
        </div>

        <!-- Rejection Section -->
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div class="flex items-center mb-2">
            <svg class="w-5 h-5 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <h3 class="text-sm font-medium text-yellow-800">Reject Payment</h3>
          </div>
          <p class="text-xs text-yellow-700 mb-3">If this payment is invalid or needs correction, you can reject it with a reason.</p>
          <textarea id="rejectionReason" rows="2" class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent text-sm" placeholder="Reason for rejection (required if rejecting)..."></textarea>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="flex justify-between p-6 border-t border-gray-200 bg-gray-50">
        <button onclick="processPaymentRejection()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center">
          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
          Reject Payment
        </button>
        <div class="flex space-x-3">
          <button onclick="closeValidateModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button onclick="processPaymentValidation()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
            </svg>
            Validate Payment
          </button>
        </div>
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
                  <div id="receiptStudentId" class="text-gray-800 font-mono">TPS2025-210</div>
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
      <div class="p-8 overflow-y-auto flex-1 min-h-0">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Left Column -->
          <div class="space-y-6">
            <!-- Payment Information -->
            <div class="bg-blue-50 rounded-lg p-6">
              <h3 class="text-lg font-semibold text-blue-800 mb-4">Payment Information</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Payment ID:</span>
                  <span id="detailsPaymentId" class="text-sm font-mono font-medium text-gray-800">PAY-20250924-001</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Amount:</span>
                  <span id="detailsAmount" class="text-sm font-bold text-blue-600">₱6,000.00</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Payment Method:</span>
                  <span id="detailsPaymentMethod" class="text-sm font-medium text-gray-800">Bank Transfer</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Reference Number:</span>
                  <span id="detailsReferenceNumber" class="text-sm font-mono font-medium text-gray-800">REF123456789</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Status:</span>
                  <span id="detailsStatus" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Validated
                  </span>
                </div>
              </div>
            </div>

            <!-- Student Information -->
            <div class="bg-green-50 rounded-lg p-6">
              <h3 class="text-lg font-semibold text-green-800 mb-4">Student Information</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Name:</span>
                  <span id="detailsStudentName" class="text-sm font-medium text-gray-800">John Doe</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">User ID:</span>
                  <span id="detailsStudentUserID" class="text-sm text-gray-800">TPS2025-001</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Program:</span>
                  <span id="detailsProgramName" class="text-sm font-medium text-gray-800">Computer Programming</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">Installment:</span>
                  <span id="detailsInstallment" class="text-sm font-medium text-gray-800">1 of 3</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="space-y-6">
            <!-- Timeline -->
            <div class="bg-gray-50 rounded-lg p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Payment Timeline</h3>
                <button onclick="showCompleteTimeline()" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-colors flex items-center shadow-md">
                  <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                  </svg>
                  More Details
                </button>
              </div>
              <div class="space-y-4">
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800">Payment Created</p>
                    <p id="detailsCreatedAt" class="text-xs text-gray-600">September 20, 2025 at 10:30 AM</p>
                  </div>
                </div>
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800">Payment Date</p>
                    <p id="detailsPaymentDate" class="text-xs text-gray-600">September 22, 2025 at 2:15 PM</p>
                  </div>
                </div>
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800">Validated</p>
                    <p id="detailsValidatedAt" class="text-xs text-gray-600">September 24, 2025 at 9:45 AM</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div class="bg-yellow-50 rounded-lg p-6">
              <h3 class="text-lg font-semibold text-yellow-800 mb-4">Notes</h3>
              <div id="detailsNotes" class="text-sm text-gray-700">
                <p>Payment validated successfully. All documents verified.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer - Always Visible -->
      <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50 flex-shrink-0">
        <button onclick="closeDetailsModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Close
        </button>
        <button id="detailsGenerateReceiptButton" onclick="generateReceiptFromDetails()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center hidden">
          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 001-1v-1a1 1 0 00-1-1H4v2a1 1 0 001 1z" clip-rule="evenodd"></path>
          </svg>
          Generate Receipt
        </button>
        <button id="detailsValidateButton" onclick="validatePaymentFromDetails()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors hidden">
          Validate Payment
        </button>
      </div>
    </div>
  </div>

  <!-- Complete Payment Timeline Modal -->
  <div id="completeTimelineModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-5xl w-full max-h-[95vh] overflow-hidden shadow-2xl flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50 flex-shrink-0">
        <div>
          <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <svg class="w-6 h-6 mr-3 text-green-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
            </svg>
            Complete Payment Timeline
          </h2>
          <p id="timelinePaymentId" class="text-green-600 font-mono text-sm mt-1">Loading...</p>
        </div>
        <button onclick="closeCompleteTimelineModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      
      <!-- Content -->
      <div id="completeTimelineContent" class="p-8 overflow-y-auto flex-1 min-h-0">
        <!-- Content will be loaded dynamically -->
        <div class="flex items-center justify-center min-h-[400px]">
          <div class="text-center">
            <svg class="animate-spin h-8 w-8 text-green-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-600">Loading complete payment timeline...</p>
          </div>
        </div>
      </div>
      
      <!-- Modal Footer -->
      <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50 flex-shrink-0">
        <button onclick="closeCompleteTimelineModal()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Close
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
        'metrobank': 'Bank Transfer',
        'landbank': 'Bank Transfer',
        'unionbank': 'Bank Transfer',
        'pnb': 'Bank Transfer',
        'bank_transfer': 'Bank Transfer',
        
        // Other methods
        'check': 'Check'
      };
      return methodMap[method] || (method ? method.charAt(0).toUpperCase() + method.slice(1) : 'N/A');
    }

    // Close modal functions
    function closeDetailsModal() {
      const modal = document.getElementById('detailsModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = 'auto';
    }

    function closeCompleteTimelineModal() {
      const modal = document.getElementById('completeTimelineModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = 'auto';
    }

    // Payment Details Modal Functions
    function showDetailsModal(paymentId, row) {
      const modal = document.getElementById('detailsModal');
      const detailsContent = document.querySelector('#detailsModal .p-8');
      
      // Store the payment ID for use in other functions
      window.currentPaymentId = paymentId;
      
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
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-600">Loading payment details...</p>
          </div>
        </div>
      `;
      
      // Show modal
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.style.overflow = 'hidden';

      // Load both payment details and history simultaneously
      Promise.all([
        fetch(`../../api/payments.php?action=get_payment_details&id=${actualPaymentId}`).then(r => r.json()),
        fetch(`../../api/payment-history.php?payment_id=${encodeURIComponent(paymentId)}`).then(r => r.json())
      ])
      .then(([paymentResponse, historyResponse]) => {
        console.log('API responses:', { paymentResponse, historyResponse });
        
        // Handle different response structures
        const payment = paymentResponse.success ? paymentResponse.payment : paymentResponse;
        const history = historyResponse.success ? historyResponse.history : [];
        
        if (payment && historyResponse.success) {
          renderPaymentDetails(payment, history);
        } else {
          throw new Error(paymentResponse.error || historyResponse.error || 'Failed to load payment details');
        }
      })
      .catch(error => {
        console.error('Error loading payment details:', error);
        detailsContent.innerHTML = `
          <div class="text-center py-8">
            <div class="text-red-600 mb-4">
              <svg class="w-12 h-12 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Payment Details</h3>
            <p class="text-gray-600">${error.message}</p>
          </div>
        `;
      });
    }

    // Placeholder functions for other buttons to prevent errors
    function showReceiptModal(paymentId, row) {
      alert('Receipt functionality - Payment ID: ' + paymentId);
    }

    function showValidateModal(paymentId, row) {
      alert('Validate functionality - Payment ID: ' + paymentId);
    }

    function showCompleteTimeline(paymentId) {
      console.log('Opening complete timeline for payment:', paymentId);
      
      // Validate payment ID
      if (!paymentId || paymentId === 'undefined') {
        TPAlert.error('Error', ' Payment ID is missing. Please try refreshing the page.');
        return;
      }
      
      const modal = document.getElementById('completeTimelineModal');
      const paymentIdElement = document.getElementById('timelinePaymentId');
      const contentElement = document.getElementById('completeTimelineContent');
      
      // Show modal
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.style.overflow = 'hidden';
      
      // Set payment ID
      paymentIdElement.textContent = paymentId;
      
      // Show loading state
      contentElement.innerHTML = `
        <div class="flex items-center justify-center p-8">
          <div class="text-center">
            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-600">Loading complete payment timeline...</p>
          </div>
        </div>
      `;
      
      // Extract numeric ID for API calls
      let actualPaymentId = paymentId;
      const matches = paymentId.toString().match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }

      // Load timeline data
      Promise.all([
        fetch(`../../api/payments.php?action=get_payment_details&id=${actualPaymentId}`).then(r => r.json()),
        fetch(`../../api/payment-history.php?payment_id=${encodeURIComponent(paymentId)}`).then(r => r.json())
      ])
      .then(([paymentResponse, historyResponse]) => {
        console.log('Timeline API responses:', { paymentResponse, historyResponse });
        
        // Handle different response structures
        const paymentData = paymentResponse.success ? paymentResponse.payment : paymentResponse;
        const historyData = historyResponse.success ? historyResponse.history : [];
        
        if (paymentData && historyResponse.success) {
          renderCompleteTimeline(paymentData, historyData);
        } else {
          throw new Error(paymentResponse.error || historyResponse.error || 'Failed to load data');
        }
      })
      .catch(error => {
        console.error('Error loading complete timeline:', error);
        contentElement.innerHTML = `
          <div class="text-center py-8">
            <div class="text-red-600 mb-4">
              <svg class="w-12 h-12 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Timeline</h3>
            <p class="text-gray-600">${error.message}</p>
          </div>
        `;
      });
    }

    function renderPaymentDetails(payment, history) {
      const detailsContent = document.querySelector('#detailsModal .p-8');
      
      // Function to format dates consistently
      function formatDateTime(timestamp) {
        if (!timestamp) return 'N/A';
        const date = new Date(timestamp);
        return date.toLocaleDateString('en-US', {
          month: 'long',
          day: 'numeric',
          year: 'numeric'
        }) + ' at ' + date.toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: true
        });
      }

      detailsContent.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Payment Overview Panel -->
          <div class="space-y-6">
            <!-- Payment Information -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-green-900 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-3 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"></path>
                </svg>
                Payment Information
              </h3>
              <div class="grid grid-cols-1 gap-4 text-sm">
                <div>
                  <span class="text-green-700 font-medium">Payment ID:</span>
                  <div class="text-green-900 font-semibold">${window.currentPaymentId}</div>
                </div>
                <div>
                  <span class="text-green-700 font-medium">Amount:</span>
                  <div class="text-green-900 font-semibold text-lg">₱${parseFloat(payment.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                </div>
                ${payment.status === 'awaiting' ? '' : `
                <div>
                  <span class="text-green-700 font-medium">Payment Method:</span>
                  <div class="text-green-900">${getPaymentMethodDisplay(payment.payment_method)}</div>
                </div>
                <div>
                  <span class="text-green-700 font-medium">Reference Number:</span>
                  <div class="text-green-900">${payment.reference_number || 'N/A'}</div>
                </div>
                `}
                <div>
                  <span class="text-green-700 font-medium">Status:</span>
                  <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-1 ${
                    payment.status === 'validated' ? 'bg-green-100 text-green-800' :
                    payment.status === 'rejected' ? 'bg-red-100 text-red-800' :
                    payment.status === 'pending_validation' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-gray-100 text-gray-800'
                  }">
                    ${payment.status === 'validated' ? '✓ Validated' :
                      payment.status === 'rejected' ? '✗ Rejected' :
                      payment.status === 'pending_validation' ? '⏳ Pending Validation' :
                      '⏳ ' + (payment.status ? payment.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Pending')}
                  </div>
                </div>
              </div>
            </div>

            <!-- Student Information -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-3 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                </svg>
                Student Information
              </h3>
              <div class="grid grid-cols-1 gap-4 text-sm">
                <div>
                  <span class="text-blue-700 font-medium">Name:</span>
                  <div class="text-blue-900">${payment.student_name || 'N/A'}</div>
                </div>
                <div>
                  <span class="text-blue-700 font-medium">User ID:</span>
                  <div class="text-blue-900 font-mono">${payment.student_id || 'N/A'}</div>
                </div>
                <div>
                  <span class="text-blue-700 font-medium">Program:</span>
                  <div class="text-blue-900">${payment.program_name || 'N/A'}</div>
                </div>
                ${payment.installment_number ? `
                <div>
                  <span class="text-blue-700 font-medium">Installment:</span>
                  <div class="text-blue-900">${payment.installment_number} of ${payment.total_installments || 1}</div>
                </div>
                ` : ''}
              </div>
            </div>
          </div>

          <!-- Timeline & Status Panel -->
          <div class="space-y-6">
            <!-- Payment Timeline -->
            <div class="bg-white border border-gray-200 p-6 rounded-xl">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                  <svg class="w-6 h-6 mr-3 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                  </svg>
                  Payment Timeline
                </h3>
                <button onclick="showCompleteTimeline(window.currentPaymentId)" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center space-x-2">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                  </svg>
                  <span>More</span>
                </button>
              </div>
              <div class="flow-root">
                <ul class="space-y-6">
                  ${history.length > 0 ? history.map((event, index) => {
                    // Get icon and color based on action type
                    let iconData = { color: 'bg-gray-500', icon: '●', title: 'Unknown Action' };
                    
                    switch(event.action) {
                      case 'created':
                        iconData = { color: 'bg-blue-500', icon: '●', title: 'Payment Created' };
                        break;
                      case 'payment_submitted':
                        iconData = { color: 'bg-yellow-500', icon: '●', title: 'Payment Submitted' };
                        break;
                      case 'validated':
                        iconData = { color: 'bg-green-500', icon: '●', title: 'Payment Validated' };
                        break;
                      case 'rejected':
                        iconData = { color: 'bg-red-500', icon: '●', title: 'Payment Rejected' };
                        break;
                      case 'resubmitted':
                        iconData = { color: 'bg-purple-500', icon: '●', title: 'Payment Resubmitted' };
                        break;
                    }

                    return `
                      <li>
                        <div class="relative pb-8">
                          <div class="relative flex items-start space-x-3">
                            <div class="relative">
                              <div class="h-3 w-3 ${iconData.color} rounded-full ring-8 ring-white"></div>
                            </div>
                            <div class="min-w-0 flex-1 py-0">
                              <div class="text-sm font-medium text-gray-900">${iconData.title}</div>
                              <div class="mt-0.5 text-xs text-gray-600">${formatDateTime(event.created_at || event.timestamp)}</div>
                            </div>
                          </div>
                        </div>
                      </li>
                    `;
                  }).join('') : '<li class="text-center py-4 text-gray-500">No timeline data available</li>'}
                </ul>
              </div>
            </div>

            <!-- Additional Information -->
            ${payment.notes ? `
            <div class="bg-yellow-50 border border-yellow-200 p-6 rounded-xl">
              <h3 class="text-lg font-semibold text-yellow-900 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-3 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                Notes
              </h3>
              <div class="text-sm text-yellow-800">${payment.notes}</div>
            </div>
            ` : ''}
          </div>
        </div>
      `;
    }

    function renderCompleteTimeline(payment, history) {
      const contentElement = document.getElementById('completeTimelineContent');
      
      // Simple timeline rendering to match Payment Details format
      function getTimelineIcon(action) {
        switch (action) {
          case 'created':
            return { color: 'bg-blue-500', title: 'Payment Created' };
          case 'payment_submitted':
            return { color: 'bg-yellow-500', title: 'Payment Submitted' };
          case 'validated':
            return { color: 'bg-green-500', title: 'Payment Validated' };
          case 'rejected':
            return { color: 'bg-red-500', title: 'Payment Rejected' };
          case 'resubmitted':
            return { color: 'bg-purple-500', title: 'Payment Resubmitted' };
          default:
            return { color: 'bg-gray-500', title: 'Unknown Action' };
        }
      }

      function formatDateTime(timestamp) {
        if (!timestamp) return 'N/A';
        const date = new Date(timestamp);
        return date.toLocaleDateString('en-US', {
          month: 'long',
          day: 'numeric',
          year: 'numeric'
        }) + ' at ' + date.toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: true
        });
      }

      function formatStatusText(status) {
        if (!status) return '';
        return status
          .replace(/_/g, ' ')
          .replace(/\b\w/g, l => l.toUpperCase());
      }

      const content = `
        <div class="max-w-4xl mx-auto">
          <!-- Payment Overview -->
          <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-xl mb-8 border border-green-200">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-bold text-gray-900">${payment.program_name || 'Payment Program'}</h3>
                <p class="text-green-600 font-mono text-lg">${payment.payment_id || ('PAY-' + new Date(payment.created_at).toISOString().slice(0,10).replace(/-/g,'') + '-' + String(payment.id).padStart(3, '0'))}</p>
              </div>
              <div class="text-right">
                <div class="text-3xl font-bold text-gray-900">₱${parseFloat(payment.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                <div class="text-sm text-gray-600">Installment ${payment.installment_number || 1} of ${payment.total_installments || 1}</div>
              </div>
            </div>
          </div>

          <!-- Complete Timeline -->
          <div class="bg-white border border-gray-200 p-6 rounded-xl">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-6 h-6 mr-3 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
              </svg>
              Complete Payment Timeline
            </h3>
            <div class="flow-root">
              <ul class="space-y-6">
                ${history.length > 0 ? history.map((event, index) => {
                  const iconData = getTimelineIcon(event.action);
                  const newStatus = event.new_status || event.status;
                  const oldStatus = event.old_status;
                  const timestamp = event.created_at || event.timestamp;
                  const isLatest = index === history.length - 1;
                  
                  // Calculate relative time
                  const eventDate = new Date(timestamp);
                  const now = new Date();
                  const diffMs = now - eventDate;
                  const diffMins = Math.floor(diffMs / 60000);
                  const diffHours = Math.floor(diffMs / 3600000);
                  const diffDays = Math.floor(diffMs / 86400000);
                  
                  let relativeTime = '';
                  if (diffMins < 1) {
                    relativeTime = 'Just now';
                  } else if (diffMins < 60) {
                    relativeTime = diffMins + ' minute' + (diffMins === 1 ? '' : 's') + ' ago';
                  } else if (diffHours < 24) {
                    relativeTime = diffHours + ' hour' + (diffHours === 1 ? '' : 's') + ' ago';
                  } else if (diffDays < 7) {
                    relativeTime = diffDays + ' day' + (diffDays === 1 ? '' : 's') + ' ago';
                  } else {
                    relativeTime = formatDateTime(timestamp).split(' at ')[0];
                  }
                  
                  return '<li><div class="relative">' +
                    '<div class="flex items-start space-x-3">' +
                    '<div class="flex-shrink-0">' +
                      '<div class="w-3 h-3 ' + iconData.color + ' rounded-full mt-1.5 ' + 
                      (isLatest ? 'ring-2 ring-green-200 ring-offset-1' : '') + '"></div>' +
                    '</div>' +
                    '<div class="min-w-0 flex-1 pb-6">' +
                      '<div class="flex items-center justify-between">' +
                        '<div class="text-sm font-medium text-gray-900">' + iconData.title +
                        (isLatest ? ' <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 ml-2">Latest</span>' : '') +
                        '</div>' +
                        '<div class="text-xs text-gray-500">' + relativeTime + '</div>' +
                      '</div>' +
                      
                      // Status transition display
                      (oldStatus && newStatus && oldStatus !== newStatus ? 
                        '<div class="mt-2 flex items-center space-x-2 text-xs">' +
                          '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">' + 
                            formatStatusText(oldStatus) + 
                          '</span>' +
                          '<svg class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20">' +
                            '<path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>' +
                          '</svg>' +
                          '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs ' + 
                            (iconData.color.replace('bg-', 'bg-').replace('-500', '-100')) + ' ' + 
                            (iconData.color.replace('bg-', 'text-').replace('-500', '-800')) + '">' + 
                            formatStatusText(newStatus) + 
                          '</span>' +
                        '</div>' : '') +
                      
                      // Full timestamp
                      '<div class="text-xs text-gray-500 mt-2">' + formatDateTime(timestamp) + '</div>' +
                      
                      // Event description/notes
                      (event.notes ? 
                        '<div class="text-sm text-gray-600 mt-2 bg-gray-50 p-3 rounded-lg border-l-4 ' + 
                        iconData.color.replace('bg-', 'border-') + '">' + event.notes + '</div>' : '') +
                      
                      // Additional details
                      '<div class="mt-3 space-y-1">' +
                        (event.performed_by ? 
                          '<div class="flex items-center text-xs text-gray-500">' +
                            '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">' +
                              '<path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>' +
                            '</svg>' +
                            'Performed by: ' + event.performed_by +
                          '</div>' : '') +
                        
                        (event.reference_number ? 
                          '<div class="flex items-center text-xs text-gray-500">' +
                            '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">' +
                              '<path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>' +
                            '</svg>' +
                            'Reference: <code class="ml-1 px-1.5 py-0.5 bg-gray-200 text-gray-800 rounded font-mono text-xs">' + 
                            event.reference_number + '</code>' +
                          '</div>' : '') +
                        
                        (event.amount ? 
                          '<div class="flex items-center text-xs text-gray-500">' +
                            '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">' +
                              '<path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>' +
                              '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>' +
                            '</svg>' +
                            'Amount: ₱' + parseFloat(event.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2}) +
                          '</div>' : '') +
                      '</div>' +
                    '</div>' +
                    '</div></div></li>';
                }).join('') : '<li class="text-center py-8 text-gray-500">No timeline data available</li>'}
              </ul>
            </div>
          </div>
        </div>
      `;

      contentElement.innerHTML = content;
    }

    // Placeholder functions for other buttons to prevent errors
    function showReceiptModal(paymentId, row) {
      alert('Receipt functionality - Payment ID: ' + paymentId);
    }

    function showValidateModal(paymentId, row) {
      alert('Validate functionality - Payment ID: ' + paymentId);
    }

    // Add interactive functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Action button handlers
      document.querySelectorAll('button[class*="text-"]').forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const action = this.textContent.trim();
          const row = this.closest('tr');
          const paymentId = row.querySelector('td:first-child').textContent;

          console.log(`Action: ${action} for Payment ID: ${paymentId}`);

          // Handle different actions
          switch (action) {
            case 'View':
              TPAlert.info(`Viewing payment details for ${paymentId}`, 'Payment Details');
              break;
            case 'Receipt':
              showReceiptModal(paymentId, row);
              break;
            case 'Validate':
              showValidateModal(paymentId, row);
              break;
            case 'Remind':
              TPAlert.confirm('Confirm Action', `Send payment reminder for ${paymentId}?`,
                'Send Reminder',
                'Send Reminder',
                'Cancel'
              )).isConfirmed.then((result) => {
                if (result.isConfirmed) {
                  TPAlert.success(`Reminder sent for payment ${paymentId}`, 'Reminder Sent');
                }
              });
              break;
          }
        });
      });
    });

    // Global variable to store current payment ID for modals
    let currentPaymentId = null;

    // Complete Payment Timeline Functions
    function showCompleteTimeline(paymentId) {
      console.log('Opening complete timeline for payment:', paymentId);
      
      // Validate payment ID
      if (!paymentId || paymentId === 'undefined') {
        TPAlert.error('Error', ' Payment ID is missing. Please try refreshing the page.');
        return;
      }
      
      const modal = document.getElementById('completeTimelineModal');
      const paymentIdElement = document.getElementById('timelinePaymentId');
      const contentElement = document.getElementById('completeTimelineContent');
      
      // Show modal
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.style.overflow = 'hidden';
      
      // Set payment ID
      paymentIdElement.textContent = paymentId;
      
      // Show loading state
      contentElement.innerHTML = `
        <div class="flex items-center justify-center p-8">
          <div class="text-center">
            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-600">Loading complete payment timeline...</p>
          </div>
        </div>
      `;
      
      // Extract numeric ID for API calls
      let actualPaymentId = paymentId;
      const matches = paymentId.toString().match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }

      // Load timeline data
      Promise.all([
        fetch(`../../api/payments.php?action=get_payment_details&id=${actualPaymentId}`).then(r => r.json()),
        fetch(`../../api/payment-history.php?payment_id=${encodeURIComponent(paymentId)}`).then(r => r.json())
      ])
      .then(([paymentResponse, historyResponse]) => {
        console.log('Timeline API responses:', { paymentResponse, historyResponse });
        
        // Handle different response structures
        const paymentData = paymentResponse.success ? paymentResponse.payment : paymentResponse;
        const historyData = historyResponse.success ? historyResponse.history : [];
        
        if (paymentData && historyResponse.success) {
          renderCompleteTimeline(paymentData, historyData);
        } else {
          throw new Error(paymentResponse.error || historyResponse.error || 'Failed to load data');
        }
      })
      .catch(error => {
        console.error('Error loading complete timeline:', error);
        contentElement.innerHTML = `
          <div class="text-center py-8">
            <div class="text-red-600 mb-4">
              <svg class="w-12 h-12 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Timeline</h3>
            <p class="text-gray-600">${error.message}</p>
          </div>
        `;
      });
    }

    function closeCompleteTimelineModal() {
      const modal = document.getElementById('completeTimelineModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = 'auto';
    }

    function renderCompleteTimeline(payment, history) {
      const contentElement = document.getElementById('completeTimelineContent');
      
      // Simple timeline rendering to match student interface
      function getTimelineIcon(action) {
        switch (action) {
          case 'created':
            return { color: 'bg-blue-500', title: 'Payment Created' };
          case 'payment_submitted':
            return { color: 'bg-yellow-500', title: 'Payment Submitted' };
          case 'validated':
            return { color: 'bg-green-500', title: 'Payment Validated' };
          case 'rejected':
            return { color: 'bg-red-500', title: 'Payment Rejected' };
          case 'resubmitted':
            return { color: 'bg-purple-500', title: 'Payment Resubmitted' };
          default:
            return { color: 'bg-gray-500', title: 'Unknown Action' };
        }
      }

      function formatDateTime(timestamp) {
        if (!timestamp) return 'N/A';
        const date = new Date(timestamp);
        return date.toLocaleDateString('en-US', {
          month: 'long',
          day: 'numeric',
          year: 'numeric'
        }) + ' at ' + date.toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: true
        });
      }

      function formatStatusText(status) {
        if (!status) return '';
        return status
          .replace(/_/g, ' ')
          .replace(/\b\w/g, l => l.toUpperCase());
      }

      const content = `
        <div class="max-w-4xl mx-auto">
          <!-- Payment Overview -->
          <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-xl mb-8 border border-green-200">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-bold text-gray-900">${payment.program_name || 'Payment Program'}</h3>
                <p class="text-green-600 font-mono text-lg">${payment.payment_id || ('PAY-' + new Date(payment.created_at).toISOString().slice(0,10).replace(/-/g,'') + '-' + String(payment.id).padStart(3, '0'))}</p>
              </div>
              <div class="text-right">
                <div class="text-3xl font-bold text-gray-900">₱${parseFloat(payment.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                <div class="text-sm text-gray-600">Installment ${payment.installment_number || 1} of ${payment.total_installments || 1}</div>
              </div>
            </div>
          </div>

          <!-- Complete Timeline -->
          <div class="bg-white border border-gray-200 p-6 rounded-xl">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-6 h-6 mr-3 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
              </svg>
              Complete Payment Timeline
            </h3>
            <div class="flow-root">
              <ul class="space-y-6">
                ${history.length > 0 ? history.map((event, index) => {
                  const iconData = getTimelineIcon(event.action);
                  const newStatus = event.new_status || event.status;
                  const oldStatus = event.old_status;
                  const timestamp = event.created_at || event.timestamp;
                  const isLatest = index === history.length - 1;
                  
                  // Calculate relative time
                  const eventDate = new Date(timestamp);
                  const now = new Date();
                  const diffMs = now - eventDate;
                  const diffMins = Math.floor(diffMs / 60000);
                  const diffHours = Math.floor(diffMs / 3600000);
                  const diffDays = Math.floor(diffMs / 86400000);
                  
                  let relativeTime = '';
                  if (diffMins < 1) {
                    relativeTime = 'Just now';
                  } else if (diffMins < 60) {
                    relativeTime = diffMins + ' minute' + (diffMins === 1 ? '' : 's') + ' ago';
                  } else if (diffHours < 24) {
                    relativeTime = diffHours + ' hour' + (diffHours === 1 ? '' : 's') + ' ago';
                  } else if (diffDays < 7) {
                    relativeTime = diffDays + ' day' + (diffDays === 1 ? '' : 's') + ' ago';
                  } else {
                    relativeTime = formatDateTime(timestamp).split(' at ')[0];
                  }
                  
                  return '<li><div class="relative">' +
                    '<div class="flex items-start space-x-3">' +
                    '<div class="flex-shrink-0">' +
                      '<div class="w-3 h-3 ' + iconData.color + ' rounded-full mt-1.5 ' + 
                      (isLatest ? 'ring-2 ring-green-200 ring-offset-1' : '') + '"></div>' +
                    '</div>' +
                    '<div class="min-w-0 flex-1 pb-6">' +
                      '<div class="flex items-center justify-between">' +
                        '<div class="text-sm font-medium text-gray-900">' + iconData.title +
                        (isLatest ? ' <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 ml-2">Latest</span>' : '') +
                        '</div>' +
                        '<div class="text-xs text-gray-500">' + relativeTime + '</div>' +
                      '</div>' +
                      
                      // Status transition display
                      (oldStatus && newStatus && oldStatus !== newStatus ? 
                        '<div class="mt-2 flex items-center space-x-2 text-xs">' +
                          '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">' + 
                            formatStatusText(oldStatus) + 
                          '</span>' +
                          '<svg class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20">' +
                            '<path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>' +
                          '</svg>' +
                          '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs ' + 
                            (iconData.color.replace('bg-', 'bg-').replace('-500', '-100')) + ' ' + 
                            (iconData.color.replace('bg-', 'text-').replace('-500', '-800')) + '">' + 
                            formatStatusText(newStatus) + 
                          '</span>' +
                        '</div>' : '') +
                      
                      // Full timestamp
                      '<div class="text-xs text-gray-500 mt-2">' + formatDateTime(timestamp) + '</div>' +
                      
                      // Event description/notes
                      (event.notes ? 
                        '<div class="text-sm text-gray-600 mt-2 bg-gray-50 p-3 rounded-lg border-l-4 ' + 
                        iconData.color.replace('bg-', 'border-') + '">' + event.notes + '</div>' : '') +
                      
                      // Additional details
                      '<div class="mt-3 space-y-1">' +
                        (event.performed_by ? 
                          '<div class="flex items-center text-xs text-gray-500">' +
                            '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">' +
                              '<path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>' +
                            '</svg>' +
                            'Performed by: ' + event.performed_by +
                          '</div>' : '') +
                        
                        (event.reference_number ? 
                          '<div class="flex items-center text-xs text-gray-500">' +
                            '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">' +
                              '<path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>' +
                            '</svg>' +
                            'Reference: <code class="ml-1 px-1.5 py-0.5 bg-gray-200 text-gray-800 rounded font-mono text-xs">' + 
                            event.reference_number + '</code>' +
                          '</div>' : '') +
                        
                        (event.amount ? 
                          '<div class="flex items-center text-xs text-gray-500">' +
                            '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">' +
                              '<path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>' +
                              '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>' +
                            '</svg>' +
                            'Amount: ₱' + parseFloat(event.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2}) +
                          '</div>' : '') +
                      '</div>' +
                    '</div>' +
                    '</div></div></li>';
                }).join('') : '<li class="text-center py-8 text-gray-500">No timeline data available</li>'}
              </ul>
            </div>
          </div>
        </div>
      `;

      contentElement.innerHTML = content;
    }

    function renderTimelineError() {
      const contentElement = document.getElementById('completeTimelineContent');
      contentElement.innerHTML = `
        <div class="flex items-center justify-center min-h-[400px]">
          <div class="text-center">
            <svg class="w-12 h-12 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-gray-600">Failed to load payment timeline</p>
            <p class="text-sm text-gray-500 mt-2">Please try again or contact support if the problem persists</p>
          </div>
        </div>
      `;
    }

    // Receipt Modal Functions
    function showReceiptModal(paymentId, row) {
      // Extract data from the table row
      const studentName = row.querySelector('td:nth-child(3) .text-sm.font-medium').textContent;
      const studentId = row.querySelector('td:nth-child(3) .text-xs').textContent;
      const programName = row.querySelector('td:nth-child(5)').textContent.trim();
      const amount = row.querySelector('td:nth-child(4)').textContent;
      const dueDate = row.querySelector('td:nth-child(7)').textContent.replace(/\s+/g, ' ').trim();
      const status = row.querySelector('td:nth-child(8) .status-badge').textContent;

      // Create the professional receipt modal
      const modal = document.createElement('div');
      modal.id = 'receiptModal';
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
          <!-- Header with Close Button -->
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Payment Receipt</h2>
            <button onclick="closeReceiptModal()" class="text-gray-400 hover:text-gray-600">
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
                    <span class="font-semibold">${formatDate(new Date())}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Payment Method:</span>
                    <span class="font-semibold">Credit Card (Visa)</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Transaction ID:</span>
                    <span class="font-semibold">TXN-${paymentId.replace('PAY-', '')}</span>
                  </div>
                </div>
              </div>
              
              <!-- Right Column -->
              <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Student Information</h3>
                <div class="space-y-3">
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Student Name:</span>
                    <span class="font-semibold">${studentName}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Student ID:</span>
                    <span class="font-semibold">${studentId}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Program:</span>
                    <span class="font-semibold">${programName}</span>
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
                  <span class="font-medium">₱${(parseFloat(amount.replace('₱', '').replace(',', '')) * 0.8).toLocaleString()}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Materials Fee:</span>
                  <span class="font-medium">₱${(parseFloat(amount.replace('₱', '').replace(',', '')) * 0.15).toLocaleString()}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Activity Fee:</span>
                  <span class="font-medium">₱${(parseFloat(amount.replace('₱', '').replace(',', '')) * 0.05).toLocaleString()}</span>
                </div>
                <div class="border-t border-gray-200 pt-3 mt-3">
                  <div class="flex justify-between text-lg font-bold">
                    <span>Total Amount Paid:</span>
                    <span class="text-green-600">${amount}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Payment Status -->
            <div class="${status.includes('Validated') ? 'bg-green-50 border-green-200' : status.includes('Pending') ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200'} border rounded-lg p-4 mb-6">
              <div class="flex items-center">
                <svg class="w-6 h-6 ${status.includes('Validated') ? 'text-green-600' : status.includes('Pending') ? 'text-yellow-600' : 'text-red-600'} mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                  <p class="font-semibold ${status.includes('Validated') ? 'text-green-800' : status.includes('Pending') ? 'text-yellow-800' : 'text-red-800'}">Payment ${status.includes('Validated') ? 'Successfully Validated' : status.includes('Pending') ? 'Pending Validation' : 'Requires Attention'}</p>
                  <p class="text-sm ${status.includes('Validated') ? 'text-green-600' : status.includes('Pending') ? 'text-yellow-600' : 'text-red-600'}">This receipt serves as ${status.includes('Validated') ? 'proof of payment' : 'payment record'}</p>
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
            <button onclick="downloadReceiptPDF('${paymentId}')" class="flex-1 bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition-colors font-medium">
              📄 Download PDF
            </button>
            <button onclick="closeReceiptModal()" class="flex-1 bg-gray-300 text-gray-700 py-3 px-6 rounded-lg hover:bg-gray-400 transition-colors font-medium">
              Close
            </button>
          </div>
        </div>
      `;

      // Remove existing modal if any
      const existingModal = document.getElementById('receiptModal');
      if (existingModal) {
        existingModal.remove();
      }

      document.body.appendChild(modal);
      document.body.style.overflow = 'hidden';
    }

    function closeReceiptModal() {
      const modal = document.getElementById('receiptModal');
      if (modal) {
        modal.remove();
      }
      document.body.style.overflow = 'auto';
    }

    function downloadReceiptPDF(paymentId) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
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
      const receiptContent = document.querySelector('#receiptModal .bg-white.border-2');

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
                  <span class="detail-value">${formatDate(new Date())}</span>
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
                  <span class="detail-value">Student Name</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Student ID:</span>
                  <span class="detail-value">STU-2025-001</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Program:</span>
                  <span class="detail-value">Program Name</span>
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

    function formatDate(date) {
      const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      };
      return date.toLocaleDateString('en-US', options);
    }

    // Function to get user-friendly payment method display names
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

    // Validate Payment Modal Functions
    function showValidateModal(paymentId, row) {
      console.log('showValidateModal called with:', { paymentId, row });
      
      let studentName, programName, amount, dueDate, paymentData = null;

      if (row) {
        console.log('Getting data from table row');
        // Extract data from the table row (when called from table)
        studentName = row.querySelector('td:nth-child(3) .text-sm.font-medium').textContent;
        programName = row.querySelector('td:nth-child(5)').textContent.trim();
        amount = row.querySelector('td:nth-child(4)').textContent;
        dueDate = row.querySelector('td:nth-child(7)').textContent.replace(/\s+/g, ' ').trim();
      } else {
        console.log('Getting data from stored payment data');
        // Use stored payment data (when called from details modal)
        paymentData = window.tempPaymentDataForValidation || window.currentDetailsPaymentData;
        if (paymentData) {
          studentName = paymentData.student_name || 'N/A';
          programName = paymentData.program_name || 'N/A';
          amount = '₱' + parseFloat(paymentData.amount || 0).toLocaleString();
          dueDate = paymentData.due_date || 'N/A';
          // Clear the temporary data after use
          window.tempPaymentDataForValidation = null;
        } else {
          TPAlert.error('Payment data not available. Please refresh the page and try again.', 'Data Error');
          return;
        }
      }

      const finalPaymentId = row ? paymentId : window.currentDetailsPaymentId;
      console.log('Final payment ID to use:', finalPaymentId);

      // Extract reference number from payment data if available
      let referenceNumber = 'N/A';
      if (!row && paymentData && paymentData.reference_number) {
        referenceNumber = paymentData.reference_number;
      }

      // Populate modal with data (reference number and payment method will be updated via API call if needed)
      document.getElementById('validateStudentName').textContent = studentName;
      document.getElementById('validateProgramName').textContent = programName;
      document.getElementById('validatePaymentId').textContent = finalPaymentId;
      document.getElementById('validatePaymentDate').textContent = dueDate;
      document.getElementById('validatePaymentAmount').textContent = amount;
      document.getElementById('validateReferenceNumber').textContent = referenceNumber;
      
      // Set payment method - will be updated via API call if needed
      const initialPaymentMethod = paymentData ? paymentData.payment_method : null;
      document.getElementById('validatePaymentMethod').textContent = getPaymentMethodDisplay(initialPaymentMethod);

      // If called from table row, fetch reference number and payment method from database
      if (row) {
        fetchPaymentDetails(finalPaymentId);
      }
      document.getElementById('validateReceiptName').textContent = 'payment_receipt.jpg';

      // Load receipt image
      console.log('Loading receipt image for payment ID:', finalPaymentId);
      loadReceiptImage(finalPaymentId);

      // Clear form fields
      document.getElementById('validationNotes').value = '';
      document.getElementById('rejectionReason').value = '';

      // Store current row for updates
      window.currentValidationRow = row;
      window.currentValidationPaymentId = row ? paymentId : window.currentDetailsPaymentId;

      // Show modal
      document.getElementById('validateModal').classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }

    function closeValidateModal() {
      document.getElementById('validateModal').classList.add('hidden');
      document.body.style.overflow = 'auto';

      // Clear stored data
      window.currentValidationRow = null;
      window.currentValidationPaymentId = null;
    }

    function fetchPaymentDetails(paymentId) {
      // Extract actual payment ID from formatted payment_id (PAY-YYYYMMDD-XXX)
      let actualPaymentId = paymentId;
      const matches = paymentId.toString().match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }

      console.log('Fetching payment details for payment ID:', actualPaymentId);

      fetch(`../../api/payments.php?action=get_payment_details&id=${actualPaymentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.payment) {
            // Update reference number
            if (data.payment.reference_number) {
              document.getElementById('validateReferenceNumber').textContent = data.payment.reference_number;
              console.log('Reference number updated:', data.payment.reference_number);
            }
            
            // Update payment method
            if (data.payment.payment_method) {
              document.getElementById('validatePaymentMethod').textContent = getPaymentMethodDisplay(data.payment.payment_method);
              console.log('Payment method updated:', data.payment.payment_method, '→', getPaymentMethodDisplay(data.payment.payment_method));
            }
          } else {
            console.log('No payment details found or API error:', data);
          }
        })
        .catch(error => {
          console.error('Error fetching payment details:', error);
        });
    }

    function processPaymentValidation() {
      const paymentId = window.currentValidationPaymentId;
      const notes = document.getElementById('validationNotes').value;

      console.log('Validating payment:', {
        paymentId: paymentId,
        notes: notes,
        currentRow: window.currentValidationRow
      });

      if (!paymentId) {
        TPAlert.error('Payment ID not found. Please close this modal and try again.', 'Validation Error');
        return;
      }

      // Remove confirm dialog - validation happens through modal review

      // Show loading state
      const validateBtn = document.querySelector('button[onclick="processPaymentValidation()"]');
      const originalText = validateBtn.innerHTML;
      validateBtn.innerHTML = `
        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Validating...
      `;
      validateBtn.disabled = true;

      // Create request payload
      const payload = {
        action: 'validate_payment',
        payment_id: paymentId,
        status: 'validated'
      };

      if (notes && notes.trim()) {
        payload.notes = notes.trim();
      }

      console.log('Sending validation request:', payload);

      // Send API request with credentials so session cookie is included
      fetch('../../api/payments.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams(payload)
        })
        .then(async response => {
          console.log('Response status:', response.status);
          let text = await response.text();
          console.log('Raw response:', text);

          let data = null;
          try {
            data = JSON.parse(text || '{}');
          } catch (e) {
            throw new Error('Invalid JSON response from server: ' + e.message + '\n' + text);
          }

          if (!response.ok || !data.success) {
            const serverMsg = data && data.error ? data.error : (data && data.message ? data.message : ('HTTP ' + response.status));
            throw new Error(serverMsg);
          }

          // Update the row status immediately
          const row = window.currentValidationRow;
          if (row) {
            const statusCell = row.querySelector('td:nth-child(7)');
            const validationCell = row.querySelector('td:nth-child(8)');
            const actionsCell = row.querySelector('td:nth-child(9)');

            if (statusCell) {
              statusCell.innerHTML = `
                <div class="flex items-center">
                  <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                  <span class="status-badge status-validated">Validated</span>
                </div>
              `;
            }

            if (validationCell) {
              const today = new Date().toLocaleDateString();
              validationCell.innerHTML = `
                <div class="flex items-center">
                  <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                  <div class="text-xs text-green-600">
                    <div class="font-medium text-green-600">Validated</div>
                    <div class="text-green-500">${today}</div>
                  </div>
                </div>
              `;
            }

            if (actionsCell) {
              actionsCell.innerHTML = `
                <div class="flex space-x-2">
                  <button onclick="showReceiptModal('${paymentId}', this.closest('tr'))" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Receipt</button>
                  <button onclick="showDetailsModal('${paymentId}', this.closest('tr'))" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Details</button>
                </div>
              `;
            }
          }

          // Show success message and close modal
          TPAlert.success(data.message || (`Payment ${paymentId} validated successfully!`), 'Payment Validated');
          closeValidateModal();

          // Restore button state
          validateBtn.innerHTML = originalText;
          validateBtn.disabled = false;

          // Force a small delay to ensure database changes are committed and available
          setTimeout(() => {
            // Pre-warm the payment data cache by making a test API call
            fetch(`../../api/payments.php?action=get_payment_details&id=${paymentId.match(/PAY-\d{8}-(\d+)/)?.[1] || paymentId}`, {
              method: 'GET',
              credentials: 'same-origin',
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(response => response.json()).then(data => {
              if (data.success) {
                console.log('Payment validation completed and data refreshed, receipt should be ready');
              }
            }).catch(err => {
              console.log('Data refresh failed but validation succeeded:', err);
            });
          }, 200);
        })
        .catch(error => {
          console.error('Validation error:', error);

          let errorMessage = 'Unknown error occurred';
          if (error.message) {
            errorMessage = error.message;
          }

          TPAlert.error(`Error validating payment: ${errorMessage}\n\nCheck browser console for more details.`, 'Validation Failed');

          // Restore button state
          validateBtn.innerHTML = originalText;
          validateBtn.disabled = false;
        });
    }

    function processPaymentRejection() {
      const paymentId = window.currentValidationPaymentId;
      const rejectionReason = document.getElementById('rejectionReason').value.trim();

      if (!rejectionReason) {
        TPAlert.warning('Please provide a reason for rejection.', 'Reason Required');
        document.getElementById('rejectionReason').focus();
        return;
      }

      if (!paymentId) {
        TPAlert.error('Payment ID not found', 'Error');
        return;
      }

      TPAlert.confirm('Confirm Action', `Reject payment ${paymentId}?\n\nReason: ${rejectionReason}`,
        'Reject Payment',
        'Reject',
        'Cancel'
      )).isConfirmed.then((result) => {
        if (result.isConfirmed) {
        // Show loading state
        const rejectBtn = document.querySelector('button[onclick="processPaymentRejection()"]');
        const originalText = rejectBtn.innerHTML;
        rejectBtn.innerHTML = `
          <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Rejecting...
        `;
        rejectBtn.disabled = true;

        // Create request payload
        const payload = {
          action: 'reject_payment',
          payment_id: paymentId,
          rejection_reason: rejectionReason
        };

        // Send API request
        fetch('../../api/payments.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(payload)
          })
          .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text().then(text => {
              console.log('Response text:', text);
              try {
                return JSON.parse(text);
              } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text that failed to parse:', text);
                throw new Error(`Invalid JSON response: ${text.substring(0, 200)}...`);
              }
            });
          })
          .then(data => {
            if (data.success) {
              // Update the row status immediately
              const row = window.currentValidationRow;
              if (row) {
                const statusCell = row.querySelector('td:nth-child(7)');
                const validationCell = row.querySelector('td:nth-child(8)');
                const actionsCell = row.querySelector('td:nth-child(9)');

                if (statusCell) {
                  statusCell.innerHTML = `
                    <div class="flex items-center">
                      <div class="w-2 h-2 bg-red-400 rounded-full mr-2"></div>
                      <span class="status-badge status-rejected">Rejected</span>
                    </div>
                  `;
                }

                if (validationCell) {
                  const today = new Date().toLocaleDateString();
                  validationCell.innerHTML = `
                    <div class="flex items-center">
                      <div class="w-2 h-2 bg-red-400 rounded-full mr-2"></div>
                      <div class="text-xs text-red-600">
                        <div class="font-medium text-red-600">Rejected</div>
                        <div class="text-red-500">${today}</div>
                      </div>
                    </div>
                  `;
                }

                if (actionsCell) {
                  // Get program name and amount for resubmit button
                  const programCell = row.querySelector('td:nth-child(3)');
                  const amountCell = row.querySelector('td:nth-child(4)');
                  const programName = programCell ? programCell.textContent.trim() : 'Program';
                  const amountText = amountCell ? amountCell.textContent.trim() : '₱0';
                  const amount = amountText.replace(/[₱,]/g, '');
                  
                  actionsCell.innerHTML = `
                    <div class="flex space-x-2">
                      <button onclick="triggerStudentResubmit('${paymentId}', '${programName}', ${amount})" class="text-orange-600 hover:text-orange-800 text-xs font-medium">Resubmit</button>
                      <button onclick="viewPaymentDetails('${paymentId}')" class="text-gray-600 hover:text-gray-800 text-xs font-medium">Details</button>
                    </div>
                  `;
                }
              }

              TPAlert.success(`Payment ${paymentId} rejected successfully!\n\nReason: ${rejectionReason}`, 'Payment Rejected');
              closeValidateModal();

              // Restore button state
              rejectBtn.innerHTML = originalText;
              rejectBtn.disabled = false;
            } else {
              throw new Error(data.message || 'Failed to reject payment');
            }
          })
          .catch(error => {
            console.error('Error rejecting payment:', error);
            TPAlert.error('Error rejecting payment: ' + error.message, 'Rejection Failed');

            // Restore button state
            rejectBtn.innerHTML = originalText;
            rejectBtn.disabled = false;
          });
        }
      });
    }

    function viewReceipt() {
      // Create a sample receipt for demonstration
      showReceiptModal('PAY-2025-001', document.querySelector('tbody tr'));
    }

    // Function to load receipt image in validation modal
    function loadReceiptImage(paymentId) {
      console.log('loadReceiptImage called with paymentId:', paymentId);
      
      if (!paymentId) {
        console.log('No paymentId provided to loadReceiptImage');
        return;
      }
      
      const receiptImage = document.getElementById('validateReceiptImage');
      const receiptError = document.getElementById('validateReceiptError');
      const receiptName = document.getElementById('validateReceiptName');
      
      if (!receiptImage || !receiptError || !receiptName) {
        console.error('Receipt elements not found:', {
          receiptImage: !!receiptImage,
          receiptError: !!receiptError,
          receiptName: !!receiptName
        });
        return;
      }
      
      // Hide both initially
      receiptImage.style.display = 'none';
      receiptError.style.display = 'none';
      
      console.log('Fetching payment proof for payment:', paymentId);
      const apiUrl = `../../api/payments.php?action=get_receipt_attachment&payment_id=${encodeURIComponent(paymentId)}`;
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
            receiptName.textContent = attachment.original_name || attachment.filename;
            
            // Check if it's an image
            if (attachment.mime_type && attachment.mime_type.startsWith('image/')) {
              // Use the simplified file serving API with better authentication
              const imageUrl = `../../api/serve-receipt.php?id=${attachment.id}`;
              receiptImage.src = imageUrl;
              receiptImage.style.display = 'block';
              
              // Store image URL for fullsize view
              window.currentReceiptImagePath = imageUrl;
            } else {
              // Not an image, show error state with download link
              receiptError.innerHTML = `
                <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <p>File preview not available</p>
                <p class="text-xs mb-2">File type: ${attachment.mime_type}</p>
                <a href="../../api/serve-receipt.php?id=${attachment.id}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Download File</a>
              `;
              receiptError.style.display = 'block';
            }
          } else {
            // No attachment found or error
            receiptError.innerHTML = `
              <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
              </svg>
              <p>No payment proof found</p>
              <p class="text-xs">Student has not uploaded payment proof</p>
            `;
            receiptError.style.display = 'block';
            receiptName.textContent = 'No payment proof found';
          }
        })
        .catch(error => {
          console.error('Error loading receipt:', error);
          console.error('Full error details:', error.message, error.stack);
          receiptError.innerHTML = `
            <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
            </svg>
            <p>Error loading payment proof</p>
            <p class="text-xs">Error: ${error.message}</p>
            <p class="text-xs">Please check console for details</p>
          `;
          receiptError.style.display = 'block';
          receiptName.textContent = 'Error loading payment proof';
        });
    }

    // Function to open payment proof in full size
    function openProofFullsize() {
      const imagePath = window.currentReceiptImagePath;
      if (imagePath) {
        // Create a modal overlay to show full size image
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4';
        overlay.style.cursor = 'pointer';
        
        const img = document.createElement('img');
        img.src = imagePath;
        img.className = 'max-w-full max-h-full object-contain';
        img.style.cursor = 'pointer';
        
        overlay.appendChild(img);
        
        // Close on click
        overlay.addEventListener('click', () => {
          document.body.removeChild(overlay);
        });
        
        document.body.appendChild(overlay);
      } else {
        TPAlert.warning('Payment proof image not available', 'No Image');
      }
    }

    function attachRowEventListeners(row) {
      // Re-attach event listeners to action buttons in the row
      row.querySelectorAll('button[class*="text-"]').forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const action = this.textContent.trim();
          const paymentId = row.querySelector('td:first-child').textContent;

          // Handle different actions
          switch (action) {
            case 'View':
              TPAlert.info(`Viewing payment details for ${paymentId}`, 'Payment Details');
              break;
            case 'Receipt':
              showReceiptModal(paymentId, row);
              break;
            case 'Validate':
              showValidateModal(paymentId, row);
              break;
            case 'Remind':
              TPAlert.confirm('Confirm Action', `Send payment reminder for ${paymentId}?`,
                'Send Reminder',
                'Send Reminder',
                'Cancel'
              )).isConfirmed.then((result) => {
                if (result.isConfirmed) {
                  TPAlert.success(`Reminder sent for payment ${paymentId}`, 'Reminder Sent');
                }
              });
              break;
          }
        });
      });
    }

    // Close validate modal when clicking outside
    document.getElementById('validateModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeValidateModal();
      }
    });

    // Receipt Modal Functions
    // Receipt Modal Functions
    function generateReceipt(paymentId) {
      // Show receipt modal with database data
      showReceiptModal(paymentId, null);
    }

    function showReceiptModal(paymentId, row) {
      // Show loading state
      const modal = document.getElementById('receiptModal');
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';

      // Show loading in receipt content
      const receiptContent = document.getElementById('receiptContent');
      receiptContent.innerHTML = `
        <div class="flex items-center justify-center p-8">
          <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="text-gray-600">Loading receipt details...</span>
        </div>
      `;

      // Extract actual payment ID from the formatted payment_id (PAY-YYYYMMDD-XXX)
      let actualPaymentId = paymentId;
      const matches = paymentId.match(/PAY-\d{8}-(\d+)/);
      if (matches) {
        actualPaymentId = matches[1];
      }

      // Fetch payment details from database
      fetch(`../../api/payments.php?action=get_payment_details&id=${actualPaymentId}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          if (data.success && data.payment) {
            const payment = data.payment;

            // Generate receipt content with enhanced design matching student format
            const paymentDate = payment.payment_date || new Date().toISOString().split('T')[0];
            const dateFormatted = paymentDate.replace(/-/g, '');
            const receiptNum = `REC-${dateFormatted}-${payment.id.toString().padStart(3, '0')}`;
            
            const receiptDate = new Date().toLocaleDateString('en-US', {
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            });

            // Update the modal content with the new enhanced format
            receiptContent.innerHTML = `
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
                      <div class="font-mono font-medium text-gray-800">${receiptNum}</div>
                    </div>
                    <div class="text-right">
                      <div class="text-gray-600">Date:</div>
                      <div class="font-medium text-gray-800">${receiptDate}</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Student and Program Information -->
              <div class="mb-8">
                <div class="bg-gray-50 p-6 rounded-lg">
                  <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-300 pb-2">STUDENT INFORMATION</h3>
                  <div class="grid grid-cols-2 gap-6">
                    <div>
                      <div class="mb-3">
                        <span class="text-gray-600 font-medium">Full Name:</span>
                        <div class="text-gray-800 font-semibold">${payment.student_name || 'N/A'}</div>
                      </div>
                      <div class="mb-3">
                        <span class="text-gray-600 font-medium">Student ID:</span>
                        <div class="text-gray-800 font-mono">${payment.student_id || 'N/A'}</div>
                      </div>
                    </div>
                    <div>
                      <div class="mb-3">
                        <span class="text-gray-600 font-medium">Program:</span>
                        <div class="text-gray-800 font-semibold">${payment.program_name || 'N/A'}</div>
                      </div>
                      <div class="mb-3">
                        <span class="text-gray-600 font-medium">Installment:</span>
                        <div class="text-gray-800 font-medium">${payment.installment_number || 1} of ${payment.total_installments || 1}</div>
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
                        <div class="text-gray-800 font-mono text-sm">${paymentId}</div>
                      </div>
                      <div class="mb-3">
                        <span class="text-gray-600 font-medium">Payment Method:</span>
                        <div class="text-gray-800 font-medium">${getPaymentMethodDisplay(payment.payment_method)}</div>
                      </div>
                    </div>
                    <div>
                      <div class="mb-3">
                        <span class="text-gray-600 font-medium">Reference Number:</span>
                        <div class="text-gray-800 font-mono text-sm">${payment.reference_number || 'N/A'}</div>
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
                      <span class="text-3xl font-bold text-tplearn-green">₱${parseFloat(payment.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
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
                  <p class="text-xs text-gray-400">Generated on: ${new Date().toLocaleString()}</p>
                </div>
              </div>
            `;

            // Store current payment ID for PDF generation
            window.currentReceiptPaymentId = paymentId;
            window.currentReceiptData = payment;

          } else {
            throw new Error(data.message || 'Failed to fetch payment details');
          }
        })
        .catch(error => {
          console.error('Error fetching payment details:', error);
          receiptContent.innerHTML = `
            <div class="text-center p-8">
              <div class="text-red-600 mb-4">
                <svg class="w-12 h-12 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 mb-2">Error Loading Receipt</h3>
              <p class="text-gray-600 mb-4">${error.message}</p>
              <button onclick="closeReceiptModal()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">Close</button>
            </div>
          `;
        });
    }

    function closeReceiptModal() {
      document.getElementById('receiptModal').classList.add('hidden');
      document.body.style.overflow = 'auto';
      window.currentReceiptPaymentId = null;
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

    async function downloadReceiptPDF() {
      const paymentId = window.currentReceiptPaymentId;
      const paymentData = window.currentReceiptData;

      if (!paymentId || !paymentData) {
        TPAlert.error('No payment data available for PDF generation.', 'PDF Generation Error');
        return;
      }

      try {
        // Show loading state
        const downloadBtn = document.querySelector('button[onclick="downloadReceiptPDF()"]');
        const originalText = downloadBtn.innerHTML;
        downloadBtn.innerHTML = `
          <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Generating PDF...
        `;
        downloadBtn.disabled = true;

        // Create PDF content from receipt modal
        const receiptContent = document.getElementById('receiptContent');

        // Use html2pdf library for PDF generation
        const opt = {
          margin: [0.5, 0.5, 0.5, 0.5],
          filename: `TPLearn_Receipt_${paymentId}.pdf`,
          image: {
            type: 'jpeg',
            quality: 0.98
          },
          html2canvas: {
            scale: 3,
            useCORS: true,
            letterRendering: true,
            allowTaint: false
          },
          jsPDF: {
            unit: 'in',
            format: 'letter',
            orientation: 'portrait',
            compress: false
          }
        };

        // Check if html2pdf is available
        if (typeof html2pdf !== 'undefined') {
          await html2pdf().set(opt).from(receiptContent).save();
        } else {
          // Fallback: Create a comprehensive text-based download using database data
          const receiptDate = new Date().toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          });

          const receiptNumber = 'REC-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + paymentData.id.toString().padStart(3, '0');

          const receiptText = `
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

                              TPLearn
                 Tisa at Pepara's Academic and Tutorial Services

                        OFFICIAL PAYMENT RECEIPT

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Receipt #: ${receiptNumber}
Date: ${receiptDate}

STUDENT INFORMATION
═══════════════════
Full Name: ${paymentData.student_name || 'N/A'}
Student ID: ${paymentData.student_id || 'N/A'}

PAYMENT INFORMATION
═══════════════════
Payment ID: ${paymentId}
Amount: ₱${parseFloat(paymentData.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}
${paymentData.status !== 'awaiting' ? `Payment Method: ${getPaymentMethodDisplay(paymentData.payment_method)}` : ''}
${paymentData.reference_number ? `Reference #: ${paymentData.reference_number}` : ''}

PROGRAM INFORMATION
═══════════════════
Program: ${paymentData.program_name || 'N/A'}
${paymentData.installment_number ? `Installment: ${paymentData.installment_number} of ${paymentData.total_installments || 1}` : 'Payment Type: Full Payment'}

PAYMENT STATUS
══════════════
Status: ✓ VALIDATED
${paymentData.validated_at ? `Validated Date: ${new Date(paymentData.validated_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}` : ''}
${paymentData.validator_name ? `Validated By: ${paymentData.validator_name}` : ''}
${paymentData.notes ? `Notes: ${paymentData.notes}` : ''}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Thank you for your payment!
This is an official receipt generated by TPLearn system.
For inquiries, please contact TPLearn Support.

Generated on ${receiptDate} by TPLearn Payment Management System

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          `;

          const blob = new Blob([receiptText], {
            type: 'text/plain'
          });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `TPLearn_Receipt_${paymentId}.txt`;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);
        }

        // Restore button state
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
      } catch (error) {
        console.error('Error generating PDF:', error);
        TPAlert.error('Error generating PDF. Please try again.', 'PDF Generation Failed');

        // Restore button state
        const downloadBtn = document.querySelector('button[onclick="downloadReceiptPDF()"]');
        if (downloadBtn) {
          downloadBtn.innerHTML = 'Download PDF';
          downloadBtn.disabled = false;
        }
      }
    }

    // Details Modal Functions
    function viewPaymentDetails(paymentId) {
      // Show details modal with database data (no need to find table row)
      showDetailsModal(paymentId, null);
    }

    function formatDate(date) {
      const options = {
        year: 'numeric',
        month: 'long', 
        day: 'numeric'
      };
      return date.toLocaleDateString('en-US', options);
    }

    function notifyStudentForResubmit(paymentId) {
      // In a real implementation, this would send notification to student
      TPAlert.info(`Student notification sent for payment ${paymentId}.\n\nIn production, this would:\n• Send email to student\n• Create in-app notification\n• Log the admin action\n• Allow student to access resubmit modal from their dashboard`, 'Student Notified');
      closeAdminResubmitModal();
    }
  </script>

  <!-- Include SweetAlert2 and Common Scripts -->
  <?php include '../../includes/common-scripts.php'; ?>

  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>
</body>

</html>
