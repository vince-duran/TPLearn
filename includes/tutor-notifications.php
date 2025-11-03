<?php
// Include data helpers for getTutorNotifications function
require_once __DIR__ . '/data-helpers.php';

// Get notifications for the current tutor user
$tutor_user_id = $_SESSION['user_id'] ?? null;
if ($tutor_user_id) {
  $notifications = getTutorNotifications($tutor_user_id, 10);
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
    <?php if ($unread_count > 0): ?>
      <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">
        <?= $unread_count ?>
      </span>
    <?php endif; ?>
  </button>

  <!-- Notifications Dropdown -->
  <div id="notification-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-96 overflow-hidden">
    <!-- Header -->
    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-gray-900">Notifications</h3>
        <div class="flex space-x-1">
          <button id="filter-all" onclick="filterNotifications('all')" 
                  class="px-2 md:px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700 hover:bg-green-200 transition-colors">
            All
          </button>
          <button id="filter-unread" onclick="filterNotifications('unread')" 
                  class="px-2 md:px-3 py-1 text-xs font-medium rounded-full text-gray-600 hover:bg-gray-100 transition-colors">
            Unread
          </button>
        </div>
      </div>
    </div>

    <!-- Notifications List -->
    <div class="max-h-64 overflow-y-auto">
      <?php if (empty($notifications)): ?>
        <div id="no-notifications" class="px-4 py-8 text-center text-gray-500">
          <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM4 15h8v-2H4v2zM4 11h10V9H4v2zM4 7h12V5H4v2z"></path>
          </svg>
          <p class="text-sm font-medium">No new notifications</p>
        </div>
      <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
          <?php 
            $timeText = $notification['time'];
            $isUnread = (strpos($timeText, 'hour') !== false || strpos($timeText, 'minute') !== false || strpos($timeText, 'Just now') !== false);
            $unreadClass = $isUnread ? 'unread' : 'read';
          ?>
          <div class="notification-item <?= $unreadClass ?> px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0" 
               onclick="handleNotificationClick('<?= htmlspecialchars($notification['url']) ?>', this)"
               data-id="<?= htmlspecialchars($notification['message']) ?>">
            <div class="flex items-start space-x-3">
              <!-- Notification Icon -->
              <div class="flex-shrink-0 mt-0.5">
                <div class="w-8 h-8 rounded-full bg-<?= $notification['color'] ?>-100 flex items-center justify-center">
                  <?php
                  $iconClass = '';
                  switch($notification['icon']) {
                    case 'user-plus':
                      $iconClass = 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z';
                      break;
                    case 'document-text':
                      $iconClass = 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
                      break;
                    case 'video-camera':
                      $iconClass = 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z';
                      break;
                    case 'currency-dollar':
                      $iconClass = 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1';
                      break;
                    default:
                      $iconClass = 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
                  }
                  ?>
                  <svg class="w-4 h-4 text-<?= $notification['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconClass ?>"></path>
                  </svg>
                </div>
              </div>

              <!-- Notification Content -->
              <div class="flex-1 min-w-0">
                <p class="notification-message text-sm <?= $isUnread ? 'font-medium text-gray-900' : 'text-gray-700' ?>">
                  <?= htmlspecialchars($notification['message']) ?>
                </p>
                <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($notification['time']) ?></p>
              </div>

              <!-- Unread indicator -->
              <?php if ($isUnread): ?>
                <div class="flex-shrink-0">
                  <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="px-4 py-2 bg-gray-50 border-t border-gray-200">
      <button onclick="window.location.href='tutor-notifications.php'" 
              class="text-xs text-green-600 hover:text-green-700 font-medium">
        View All Notifications
      </button>
    </div>
  </div>
</div>