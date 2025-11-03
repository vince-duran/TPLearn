<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('student');

// Get payment ID from URL parameter
$payment_id = $_GET['payment_id'] ?? '';

if (empty($payment_id)) {
  header('Location: student-payments.php?error=invalid_payment_id');
  exit();
}

// Get payment history
$historyData = getPaymentHistory($payment_id);

if (isset($historyData['error'])) {
  header('Location: student-payments.php?error=' . urlencode($historyData['error']));
  exit();
}

$payment = $historyData['payment'];
$history = $historyData['history'];
$total_events = $historyData['total_events'];

// Verify this payment belongs to the current student
$current_username = $_SESSION['username'];
if ($payment['student_username'] !== $current_username) {
  header('Location: student-payments.php?error=access_denied');
  exit();
}

$currentDate = date('l, F j, Y');

// Helper function to get status color
function getStatusColor($status) {
  switch ($status) {
    case 'validated': return 'text-green-600 bg-green-100';
    case 'pending_validation': return 'text-yellow-600 bg-yellow-100';
    case 'rejected': return 'text-red-600 bg-red-100';
    case 'pending': return 'text-blue-600 bg-blue-100';
    default: return 'text-gray-600 bg-gray-100';
  }
}

// Helper function to get action icon and color
function getActionIcon($action) {
  switch ($action) {
    case 'created': 
      return ['icon' => 'plus-circle', 'color' => 'text-blue-500'];
    case 'payment_submitted': 
      return ['icon' => 'upload', 'color' => 'text-yellow-500'];
    case 'validated': 
      return ['icon' => 'check-circle', 'color' => 'text-green-500'];
    case 'rejected': 
      return ['icon' => 'x-circle', 'color' => 'text-red-500'];
    case 'updated': 
      return ['icon' => 'edit', 'color' => 'text-purple-500'];
    default: 
      return ['icon' => 'clock', 'color' => 'text-gray-500'];
  }
}

// Helper function to format action names
function formatActionName($action) {
  switch ($action) {
    case 'created': return 'Payment Created';
    case 'payment_submitted': return 'Payment Proof Submitted';
    case 'validated': return 'Payment Validated';
    case 'rejected': return 'Payment Rejected';
    case 'updated': return 'Payment Updated';
    default: return ucfirst(str_replace('_', ' ', $action));
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment History - <?= htmlspecialchars($payment_id) ?> - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="../../assets/tplearn-tailwind.css?v=<?= filemtime(__DIR__ . '/../../assets/tplearn-tailwind.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gradient-to-br from-blue-50 via-white to-purple-50 min-h-screen">
  
  <!-- Navigation -->
  <nav class="bg-tplearn-green shadow-lg">
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex justify-between items-center py-4">
        <div class="flex items-center space-x-4">
          <img src="../../assets/tplearn-logo.png" alt="TPLearn" class="h-8 w-8 rounded">
          <h1 class="text-xl font-bold text-white">Payment History</h1>
        </div>
        <div class="flex items-center space-x-4 text-white">
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
            <button onclick="toggleNotifications()" class="p-2 rounded-full bg-green-600 hover:bg-green-700 transition-colors">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
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
          
          <span class="text-sm"><?= $currentDate ?></span>
          <span class="text-sm">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</span>
        </div>
      </div>
    </div>
  </nav>

  <!-- Sidebar and Main Content -->
  <div class="flex">
    
    <!-- Sidebar -->
    <aside class="w-64 bg-tplearn-green min-h-screen">
      <nav class="mt-8">
        <a href="../student/student-dashboard.php" class="flex items-center px-6 py-3 text-white hover:bg-tplearn-light-green transition-colors">
          <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
          </svg>
          Home
        </a>
        <a href="student-academics.php" class="flex items-center px-6 py-3 text-white hover:bg-tplearn-light-green transition-colors">
          <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          Academics
        </a>
        <a href="student-payments.php" class="flex items-center px-6 py-3 text-white bg-tplearn-light-green transition-colors">
          <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
          </svg>
          Payments
        </a>
        <a href="student-enrollment.php" class="flex items-center px-6 py-3 text-white hover:bg-tplearn-light-green transition-colors">
          <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
          </svg>
          Enrollment
        </a>
        <a href="student-profile.php" class="flex items-center px-6 py-3 text-white hover:bg-tplearn-light-green transition-colors">
          <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
          </svg>
          Profile
        </a>
      </nav>
      
      <div class="absolute bottom-0 w-64">
        <a href="../../logout.php" class="flex items-center px-6 py-4 text-white bg-red-600 hover:bg-red-700 transition-colors">
          <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"></path>
          </svg>
          Logout
        </a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8">
      
      <!-- Breadcrumb -->
      <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
          <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
              <a href="student-payments.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-tplearn-green">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                </svg>
                Payments
              </a>
            </li>
            <li>
              <div class="flex items-center">
                <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Payment History</span>
              </div>
            </li>
          </ol>
        </nav>
      </div>

      <!-- Payment Overview Card -->
      <div class="bg-white rounded-xl shadow-lg border border-gray-100 mb-8">
        <div class="bg-gradient-to-r from-tplearn-green to-tplearn-light-green px-8 py-6 rounded-t-xl">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($payment_id) ?></h2>
              <p class="text-green-100 mt-1">Payment History & Timeline</p>
            </div>
            <div class="text-right">
              <div class="text-white text-sm opacity-90">Current Status</div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white <?= getStatusColor($payment['status']) ?>">
                <?= ucfirst(str_replace('_', ' ', $payment['status'])) ?>
              </span>
            </div>
          </div>
        </div>
        
        <div class="p-8">
          <div class="grid md:grid-cols-3 gap-8">
            
            <!-- Payment Details -->
            <div class="md:col-span-2">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Information</h3>
              <div class="grid md:grid-cols-2 gap-6">
                <div>
                  <div class="space-y-4">
                    <div>
                      <span class="text-sm font-medium text-gray-600">Program</span>
                      <p class="text-gray-900 font-medium"><?= htmlspecialchars($payment['program_name']) ?></p>
                    </div>
                    <div>
                      <span class="text-sm font-medium text-gray-600">Amount</span>
                      <p class="text-2xl font-bold text-tplearn-green">₱<?= number_format($payment['amount'], 2) ?></p>
                    </div>
                    <div>
                      <span class="text-sm font-medium text-gray-600">Payment Method</span>
                      <p class="text-gray-900"><?= htmlspecialchars($payment['payment_method']) ?></p>
                    </div>
                  </div>
                </div>
                <div>
                  <div class="space-y-4">
                    <div>
                      <span class="text-sm font-medium text-gray-600">Installment</span>
                      <p class="text-gray-900"><?= $payment['installment_number'] ?> of <?= $payment['total_installments'] ?></p>
                    </div>
                    <div>
                      <span class="text-sm font-medium text-gray-600">Due Date</span>
                      <p class="text-gray-900"><?= date('M j, Y', strtotime($payment['due_date'])) ?></p>
                    </div>
                    <?php if ($payment['reference_number']): ?>
                    <div>
                      <span class="text-sm font-medium text-gray-600">Reference Number</span>
                      <p class="text-gray-900 font-mono text-sm"><?= htmlspecialchars($payment['reference_number']) ?></p>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Quick Stats -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Timeline Summary</h3>
              <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600">Total Events</span>
                  <span class="font-semibold text-gray-900"><?= $total_events ?></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600">Created</span>
                  <span class="text-sm text-gray-900"><?= date('M j, Y', strtotime($payment['created_at'])) ?></span>
                </div>
                <?php if ($payment['validated_at']): ?>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600">Validated</span>
                  <span class="text-sm text-gray-900"><?= date('M j, Y', strtotime($payment['validated_at'])) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600">Last Update</span>
                  <span class="text-sm text-gray-900"><?= date('M j, Y g:i A', strtotime($payment['updated_at'])) ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment Timeline -->
      <div class="bg-white rounded-xl shadow-lg border border-gray-100">
        <div class="px-8 py-6 border-b border-gray-200">
          <h3 class="text-xl font-semibold text-gray-900">Payment Timeline</h3>
          <p class="text-gray-600 mt-1">Complete history of all actions performed on this payment</p>
        </div>
        
        <div class="p-8">
          <div class="flow-root">
            <ul class="-mb-8">
              <?php foreach ($history as $index => $event): ?>
                <?php $actionData = getActionIcon($event['action']); ?>
                <li>
                  <div class="relative pb-8">
                    <!-- Timeline line (except for last item) -->
                    <?php if ($index < count($history) - 1): ?>
                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                    <?php endif; ?>
                    
                    <div class="relative flex space-x-3">
                      <!-- Action Icon -->
                      <div>
                        <span class="h-8 w-8 rounded-full <?= $actionData['color'] ?> bg-white border-2 border-current flex items-center justify-center ring-8 ring-white">
                          <?php
                          $iconPath = '';
                          switch ($actionData['icon']) {
                            case 'plus-circle':
                              $iconPath = 'M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z';
                              break;
                            case 'upload':
                              $iconPath = 'M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L4.414 9H17a1 1 0 100-2H4.414l1.879-1.879z';
                              break;
                            case 'check-circle':
                              $iconPath = 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z';
                              break;
                            case 'x-circle':
                              $iconPath = 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z';
                              break;
                            default:
                              $iconPath = 'M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z';
                          }
                          ?>
                          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="<?= $iconPath ?>" clip-rule="evenodd"></path>
                          </svg>
                        </span>
                      </div>
                      
                      <!-- Event Details -->
                      <div class="min-w-0 flex-1">
                        <div class="bg-gray-50 rounded-lg p-4">
                          <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-medium text-gray-900"><?= formatActionName($event['action']) ?></h4>
                            <time class="text-xs text-gray-500"><?= date('M j, Y g:i A', strtotime($event['timestamp'])) ?></time>
                          </div>
                          
                          <div class="flex items-center space-x-4 mb-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getStatusColor($event['status']) ?>">
                              <?= ucfirst(str_replace('_', ' ', $event['status'])) ?>
                            </span>
                            <span class="text-sm text-gray-600">by <span class="font-medium"><?= htmlspecialchars($event['performed_by']) ?></span></span>
                          </div>
                          
                          <?php if ($event['notes']): ?>
                          <p class="text-sm text-gray-700 mb-3"><?= htmlspecialchars($event['notes']) ?></p>
                          <?php endif; ?>
                          
                          <?php if ($event['reference_number']): ?>
                          <div class="bg-white border rounded p-2">
                            <span class="text-xs text-gray-500">Reference:</span>
                            <span class="font-mono text-sm text-gray-900 ml-2"><?= htmlspecialchars($event['reference_number']) ?></span>
                          </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
      
      <!-- Action Buttons -->
      <div class="mt-8 flex items-center space-x-4">
        <a href="student-payments.php" 
           class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium transition-colors flex items-center">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          Back to Payments
        </a>
        
        <?php if ($payment['status'] == 'rejected'): ?>
        <a href="student-payments.php?resubmit=<?= urlencode($payment_id) ?>" 
           class="bg-tplearn-green hover:bg-tplearn-green-600 text-white px-6 py-2 rounded-lg font-medium transition-colors flex items-center">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
          </svg>
          Resubmit Payment
        </a>
        <?php endif; ?>
      </div>

    </main>
  </div>

  <!-- Include notification JavaScript functions -->
  <script src="../../includes/student-notifications.js"></script>
</body>
</html>