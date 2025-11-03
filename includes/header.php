<?php
/**
 * Unified Header Component for TPLearn System
 * 
 * @param string $title - Page title
 * @param string $subtitle - Optional subtitle (for welcome messages, etc.)
 * @param string $userRole - Current user role (student, admin, tutor)
 * @param string $userName - Current user's name
 * @param array $notifications - Notification data array
 * @param array $messages - Messages data array
 */

function renderHeader($title, $subtitle = '', $userRole = 'student', $userName = 'User', $notifications = [], $messages = []) {
  // Start with 0 count - JavaScript will calculate and display the correct unread count
  // This prevents flickering from showing total count first, then updating to unread count
  $notificationCount = 0;
  $messageCount = count($messages);
  
  // Generate current day and date
  $currentDate = date('l, F j, Y'); // e.g., "Tuesday, October 15, 2024"
  
  // Get user initials for avatar
  $initials = '';
  $nameParts = explode(' ', $userName);
  foreach ($nameParts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
  }
  $initials = substr($initials, 0, 2); // Max 2 initials
  
  // Role-based colors - All avatars are now green
  $roleColors = [
    'student' => 'bg-green-500',
    'admin' => 'bg-green-500', 
    'tutor' => 'bg-green-500'
  ];
  
  $avatarColor = $roleColors[$userRole] ?? 'bg-green-500';
  
  // Get user ID from session
  $userId = $_SESSION['username'] ?? 'USER-ID';
?>

<!-- Top Header -->
<header class="bg-white shadow-sm border-b border-gray-200 px-4 lg:px-6 py-4">
  <div class="flex justify-between items-center">
    <div class="flex items-center">
      <!-- Mobile menu button -->
      <button id="mobile-menu-button" class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-green-500 mr-3 transition-colors">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <div>
        <h1 class="text-xl lg:text-2xl font-bold text-gray-800"><?= htmlspecialchars($title) ?></h1>
        <p class="text-sm text-gray-600"><?= htmlspecialchars($currentDate) ?></p>
      </div>
    </div>
    
    <div class="flex items-center space-x-4">
      <!-- Notifications -->
      <div class="relative">
        <button onclick="toggleNotificationDropdown()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tplearn-green">
          <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
          </svg>
        </button>
        <?php if ($notificationCount > 0): ?>
          <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">
            <?= $notificationCount > 99 ? '99+' : $notificationCount ?>
          </span>
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
            
            /* Compact SweetAlert2 styles */
            .tplearn-compact-alert {
              font-size: 13px !important;
              max-width: 320px !important;
              min-width: 280px !important;
              border-radius: 10px !important;
              box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12) !important;
            }
            
            .tplearn-compact-title {
              font-size: 15px !important;
              font-weight: 600 !important;
              margin-bottom: 4px !important;
              padding: 0 !important;
              line-height: 1.2 !important;
            }
            
            .tplearn-compact-content {
              font-size: 12px !important;
              margin: 0 !important;
              padding: 0 !important;
              line-height: 1.3 !important;
              color: #6b7280 !important;
            }
            
            .tplearn-compact-alert .swal2-icon {
              width: 40px !important;
              height: 40px !important;
              margin: 6px auto 10px !important;
              border-width: 2px !important;
            }
            
            .tplearn-compact-alert .swal2-icon.swal2-success [class^='swal2-success-line'] {
              height: 2px !important;
            }
            
            .tplearn-compact-alert .swal2-icon.swal2-success .swal2-success-ring {
              width: 40px !important;
              height: 40px !important;
            }
            
            .tplearn-compact-alert .swal2-actions {
              margin: 10px 0 2px !important;
              gap: 6px !important;
            }
            
            .tplearn-compact-alert .swal2-confirm,
            .tplearn-compact-alert .swal2-cancel {
              font-size: 12px !important;
              padding: 6px 12px !important;
              border-radius: 5px !important;
              font-weight: 500 !important;
              min-width: 60px !important;
            }
            
            .tplearn-compact-alert .swal2-timer-progress-bar {
              height: 2px !important;
            }
            
            @media (max-width: 480px) {
              .tplearn-compact-alert {
                max-width: 85vw !important;
                min-width: 260px !important;
                margin: 0 7.5vw !important;
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
              <?php foreach ($notifications as $index => $notification): ?>
                <?php 
                // Determine if notification is "unread" (consider recent notifications as unread)
                // Check if notification is recent (include recent days for better UX)
                $timeText = $notification['time'];
                $isUnread = (
                  strpos($timeText, 'hour') !== false || 
                  strpos($timeText, 'hours') !== false || 
                  strpos($timeText, 'minute') !== false || 
                  strpos($timeText, 'minutes') !== false || 
                  strpos($timeText, 'Just now') !== false ||
                  strpos($timeText, 'second') !== false ||
                  strpos($timeText, 'seconds') !== false ||
                  strpos($timeText, '1 day ago') !== false ||
                  strpos($timeText, '2 day') !== false
                );
                $unreadClass = $isUnread ? 'unread' : 'read';
                ?>
                <a href="<?= htmlspecialchars($notification['url']) ?>" 
                   class="notification-item <?= $unreadClass ?> block px-4 py-3 hover:bg-gray-50 border-b border-gray-50 transition-colors cursor-pointer" 
                   data-type="<?= htmlspecialchars($notification['type']) ?>"
                   data-notification-id="<?= md5($notification['message'] . $notification['time']) ?>"
                   onclick="markAsRead(this, event)">
                  <div class="flex items-start space-x-3">
                    <?php
                    // Set color based on notification type
                    $color_classes = [
                      'blue' => 'bg-blue-500',
                      'red' => 'bg-red-500',
                      'yellow' => 'bg-yellow-500',
                      'orange' => 'bg-orange-500',
                      'green' => 'bg-green-500',
                      'purple' => 'bg-purple-500'
                    ];
                    $dot_color = $color_classes[$notification['color']] ?? 'bg-blue-500';
                    ?>
                    <div class="notification-dot w-2 h-2 <?= $dot_color ?> rounded-full mt-2 flex-shrink-0 <?= $isUnread ? 'animate-pulse' : '' ?>"></div>
                    <div class="flex-1">
                      <p class="notification-text text-sm <?= $isUnread ? 'text-gray-900 font-semibold' : 'text-gray-800 font-medium' ?> hover:text-blue-600 transition-colors"><?= htmlspecialchars($notification['message']) ?></p>
                      <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($notification['time']) ?></p>
                    </div>
                    <?php if ($isUnread): ?>
                      <div class="unread-indicator w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-2"></div>
                    <?php endif; ?>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="px-4 py-6 text-center" id="no-notifications">
                <div class="text-gray-400 mb-2">
                  <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                  </svg>
                </div>
                <p class="text-sm text-gray-500">No new notifications</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Profile -->
      <div class="flex items-center space-x-3">
        <div class="hidden sm:block text-right">
          <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($userName) ?></p>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($userId) ?></p>
        </div>
        
        <!-- Profile Avatar -->
        <div class="relative">
          <button onclick="toggleProfileDropdown()" class="<?= $avatarColor ?> w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" style="background-color: #10b981; min-width: 2.5rem; min-height: 2.5rem;">
            <?= $initials ?>
          </button>
          
          <!-- Profile Dropdown -->
          <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 border border-gray-200">
            <a href="<?= $userRole === 'admin' ? 'profile.php' : ($userRole . '-profile.php') ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
              <i class="fas fa-user w-4 mr-2"></i>
              My Profile
            </a>
            <?php if ($userRole === 'admin'): ?>
              <a href="admin-tools.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                <i class="fas fa-cog w-4 mr-2"></i>
                Settings
              </a>
            <?php endif; ?>
            <div class="border-t border-gray-100"></div>
            <a href="../../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
              <i class="fas fa-sign-out-alt w-4 mr-2"></i>
              Logout
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Header JavaScript -->
<script>
// Profile dropdown functionality
function toggleProfileDropdown() {
  const dropdown = document.getElementById('profile-dropdown');
  dropdown.classList.toggle('hidden');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
  const dropdown = document.getElementById('profile-dropdown');
  const button = event.target.closest('button[onclick="toggleProfileDropdown()"]');
  
  if (!button && !dropdown.contains(event.target)) {
    dropdown.classList.add('hidden');
  }
});

// Notification and message functions (to be implemented)
function openNotifications() {
  // Implementation for notification panel
  console.log('Opening notifications...');
}

function openMessages() {
  // Implementation for messages panel
  console.log('Opening messages...');
}

// Notification dropdown functions
function toggleNotificationDropdown() {
  const dropdown = document.getElementById('notification-dropdown');
  if (dropdown) {
    dropdown.classList.toggle('hidden');
  }
}

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
    } else if (type === 'unread') {
      notification.style.display = 'none';
    }
  });
  
  // Show/hide no notifications message
  if (noNotificationsMsg) {
    if (visibleCount === 0) {
      noNotificationsMsg.style.display = 'block';
      noNotificationsMsg.querySelector('p').textContent = type === 'unread' ? 'No unread notifications' : 'No notifications';
    } else {
      noNotificationsMsg.style.display = 'none';
    }
  }
  
  // Update notification count when filtering
  updateNotificationCount();
}

// Mark notification as read
function markAsRead(element, event) {
  // Get notification ID
  const notificationId = element.getAttribute('data-notification-id');
  
  // Get or create read notifications array from localStorage (role-specific key)
  const storageKey = '<?= $userRole ?>ReadNotifications';
  let readNotifications = JSON.parse(localStorage.getItem(storageKey) || '[]');
  
  // Add this notification to read list if not already there
  if (!readNotifications.includes(notificationId)) {
    readNotifications.push(notificationId);
    localStorage.setItem(storageKey, JSON.stringify(readNotifications));
  }
  
  // Update visual state immediately
  markNotificationAsReadVisually(element);
  
  // Update notification count
  updateNotificationCount();
  
  // Allow the link to proceed normally
  return true;
}

// Mark notification as read visually
function markNotificationAsReadVisually(element) {
  // Remove unread class and add read class
  element.classList.remove('unread');
  element.classList.add('read');
  
  // Update text styling
  const textElement = element.querySelector('.notification-text');
  if (textElement) {
    textElement.className = textElement.className.replace('text-gray-900 font-semibold', 'text-gray-800 font-medium');
  }
  
  // Remove unread indicator
  const unreadIndicator = element.querySelector('.unread-indicator');
  if (unreadIndicator) {
    unreadIndicator.remove();
  }
  
  // Remove pulse animation from dot
  const dot = element.querySelector('.notification-dot');
  if (dot) {
    dot.classList.remove('animate-pulse');
  }
}

// Update notification count in badge
function updateNotificationCount() {
  const storageKey = '<?= $userRole ?>ReadNotifications';
  const readNotifications = JSON.parse(localStorage.getItem(storageKey) || '[]');
  const allNotifications = document.querySelectorAll('.notification-item');
  let unreadCount = 0;
  
  // Count notifications that are unread AND not in the read list
  allNotifications.forEach((notification) => {
    const notificationId = notification.getAttribute('data-notification-id');
    if (notification.classList.contains('unread') && !readNotifications.includes(notificationId)) {
      unreadCount++;
    }
  });
  
  console.log('Updating notification count:', unreadCount, 'Read notifications:', readNotifications);
  
  // Update badge using ID
  const badge = document.getElementById('notification-badge');
  if (unreadCount > 0) {
    if (badge) {
      badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
      badge.style.display = 'flex';
    } else {
      // Create badge if it doesn't exist
      const notificationButton = document.querySelector('button[onclick="toggleNotificationDropdown()"]');
      if (notificationButton && notificationButton.parentElement) {
        const newBadge = document.createElement('span');
        newBadge.id = 'notification-badge';
        newBadge.className = 'absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium';
        newBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
        notificationButton.parentElement.appendChild(newBadge);
      }
    }
  } else {
    if (badge) {
      badge.style.display = 'none';
    }
  }
}

// Initialize read status on page load
document.addEventListener('DOMContentLoaded', function() {
  const storageKey = '<?= $userRole ?>ReadNotifications';
  const readNotifications = JSON.parse(localStorage.getItem(storageKey) || '[]');
  
  // Mark previously read notifications
  readNotifications.forEach(notificationId => {
    const notification = document.querySelector(`[data-notification-id="${notificationId}"]`);
    if (notification && notification.classList.contains('unread')) {
      markNotificationAsReadVisually(notification);
    }
  });
  
  // Update count immediately - no delays to prevent flickering
  console.log('Initializing notification system...');
  updateNotificationCount();
  
  // Also update after a short delay to ensure all elements are ready
  setTimeout(updateNotificationCount, 10);
});

// Run notification count update immediately when script loads (before DOMContentLoaded)
// This prevents any flicker from showing incorrect counts
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    setTimeout(updateNotificationCount, 1);
  });
} else {
  // DOM already loaded, run immediately
  updateNotificationCount();
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
  const notificationDropdown = document.getElementById('notification-dropdown');
  const notificationButton = event.target.closest('button[onclick="toggleNotificationDropdown()"]');
  
  if (notificationDropdown && !notificationButton && !notificationDropdown.contains(event.target)) {
    notificationDropdown.classList.add('hidden');
  }
});
</script>



<?php
}
?>