<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
require_once '../../assets/icons.php';
requireRole('tutor');

// Get current user data from session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['name'] ?? 'Tutor';

// Check if user_id is available
if (!$user_id) {
  header('Location: ../../login.php');
  exit();
}

// Get tutor data for display name
$tutor_data = getTutorDashboardData($user_id);
$display_name = $tutor_data['name'] ?? $user_name;

// Get all notifications for tutor
$notifications = getTutorNotifications($user_id, 50); // Get more notifications for the full page
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - Tutor Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* Custom styles for notifications */
    .notification-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .notification-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .notification-unread {
      border-left: 4px solid #10b981;
      background: linear-gradient(to right, #f0fdf4, #ffffff);
    }

    .notification-read {
      opacity: 0.8;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/tutor-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 lg:ml-64">
      <?php 
      require_once '../../includes/tutor-header-standard.php';
      renderTutorHeader('Notifications', 'View and manage your notifications');
      ?>

      <!-- Notifications Content -->
      <main class="p-6">
        
        <!-- Filter Section -->
        <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="p-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-gray-800">Filter Notifications</h2>
              <div class="flex space-x-2">
                <button onclick="filterNotificationsByType('all')" id="filter-all" 
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors">
                  All
                </button>
                <button onclick="filterNotificationsByType('unread')" id="filter-unread" 
                        class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                  Unread Only
                </button>
                <button onclick="markAllAsRead()" 
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors">
                  Mark All Read
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Notifications List -->
        <div class="space-y-4" id="notifications-list">
          <?php if (empty($notifications)): ?>
            <!-- Empty State -->
            <div class="text-center py-12">
              <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM4 15h8v-2H4v2zM4 11h10V9H4v2zM4 7h12V5H4v2z"></path>
                </svg>
              </div>
              <h3 class="text-lg font-medium text-gray-900 mb-2">No notifications yet</h3>
              <p class="text-gray-500">When you receive notifications, they'll appear here.</p>
            </div>
          <?php else: ?>
            <?php foreach ($notifications as $index => $notification): ?>
              <?php 
                $timeText = $notification['time'];
                $isUnread = (strpos($timeText, 'hour') !== false || strpos($timeText, 'minute') !== false || strpos($timeText, 'Just now') !== false);
                $unreadClass = $isUnread ? 'notification-unread' : 'notification-read';
                $notificationId = 'notif-' . $index;
              ?>
              <div class="notification-card <?= $unreadClass ?> notification-item <?= $isUnread ? 'unread' : 'read' ?>" 
                   data-id="<?= htmlspecialchars($notification['message']) ?>" 
                   data-url="<?= htmlspecialchars($notification['url']) ?>"
                   id="<?= $notificationId ?>">
                <div class="p-6">
                  <div class="flex items-start space-x-4">
                    <!-- Notification Icon -->
                    <div class="flex-shrink-0">
                      <div class="w-12 h-12 rounded-full bg-<?= $notification['color'] ?>-100 flex items-center justify-center">
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
                            $iconClass = 'M15 10l4.553-2.276A1 1 0 0021 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z';
                            break;
                          case 'currency-dollar':
                            $iconClass = 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1';
                            break;
                          default:
                            $iconClass = 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
                        }
                        ?>
                        <svg class="w-6 h-6 text-<?= $notification['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconClass ?>"></path>
                        </svg>
                      </div>
                    </div>

                    <!-- Notification Content -->
                    <div class="flex-1 min-w-0">
                      <p class="notification-message text-lg <?= $isUnread ? 'font-semibold text-gray-900' : 'font-medium text-gray-700' ?>">
                        <?= htmlspecialchars($notification['message']) ?>
                      </p>
                      <div class="flex items-center justify-between mt-2">
                        <p class="text-sm text-gray-500">
                          <i class="fas fa-clock mr-1"></i>
                          <?= htmlspecialchars($notification['time']) ?>
                        </p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?= $notification['color'] ?>-100 text-<?= $notification['color'] ?>-800">
                          <?= ucfirst($notification['type']) ?>
                        </span>
                      </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col space-y-2">
                      <?php if ($isUnread): ?>
                        <button onclick="markSingleAsRead('<?= $notificationId ?>')" 
                                class="text-sm text-green-600 hover:text-green-700 font-medium">
                          Mark as Read
                        </button>
                      <?php endif; ?>
                      <button onclick="viewNotification('<?= htmlspecialchars($notification['url']) ?>')" 
                              class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                        View Details
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Load More Button -->
        <?php if (count($notifications) >= 50): ?>
          <div class="text-center mt-8">
            <button class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium">
              Load More Notifications
            </button>
          </div>
        <?php endif; ?>

      </main>
    </div>
  </div>

  <!-- Include Tutor Notification JavaScript -->
  <script src="../../includes/tutor-notifications.js"></script>
  
  <script>
    // Additional page-specific JavaScript
    function filterNotificationsByType(type) {
      const allBtn = document.getElementById('filter-all');
      const unreadBtn = document.getElementById('filter-unread');
      const notifications = document.querySelectorAll('.notification-item');
      
      // Update button styles
      if (type === 'all') {
        allBtn.className = 'px-4 py-2 text-sm font-medium rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors';
        unreadBtn.className = 'px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:bg-gray-100 transition-colors';
      } else {
        unreadBtn.className = 'px-4 py-2 text-sm font-medium rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors';
        allBtn.className = 'px-4 py-2 text-sm font-medium rounded-lg text-gray-600 hover:bg-gray-100 transition-colors';
      }
      
      // Filter notifications
      notifications.forEach(notification => {
        if (type === 'all') {
          notification.closest('.notification-card').style.display = 'block';
        } else if (type === 'unread' && notification.classList.contains('unread')) {
          notification.closest('.notification-card').style.display = 'block';
        } else if (type === 'unread') {
          notification.closest('.notification-card').style.display = 'none';
        }
      });
    }

    function markAllAsRead() {
      const notifications = document.querySelectorAll('.notification-item.unread');
      notifications.forEach(notification => {
        markNotificationAsReadVisually(notification);
      });
      
      // Show success message
      showNotification('All notifications marked as read', 'success');
    }

    function markSingleAsRead(notificationId) {
      const notification = document.getElementById(notificationId).querySelector('.notification-item');
      markNotificationAsReadVisually(notification);
      showNotification('Notification marked as read', 'success');
    }

    function viewNotification(url) {
      window.location.href = url;
    }

    // Simple notification system for this page
    function showNotification(message, type = 'info', duration = 3000) {
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white font-medium max-w-sm ${
        type === 'success' ? 'bg-green-500' :
        type === 'error' ? 'bg-red-500' :
        type === 'warning' ? 'bg-yellow-500' :
        'bg-blue-500'
      }`;
      notification.textContent = message;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.remove();
      }, duration);
    }
  </script>

</body>
</html>