<?php
// Include data helpers for getUserNotifications function
require_once __DIR__ . '/data-helpers.php';

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

<!-- Notifications -->
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