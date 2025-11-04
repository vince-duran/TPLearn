// Tutor Notification dropdown functions
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
  
  // Mark as read in localStorage (using tutor-specific key)
  let readNotifications = JSON.parse(localStorage.getItem('tutorReadNotifications') || '[]');
  if (!readNotifications.includes(notificationId)) {
    readNotifications.push(notificationId);
    localStorage.setItem('tutorReadNotifications', JSON.stringify(readNotifications));
  }
  
  // Update visual state
  markNotificationAsReadVisually(element);
}

// Mark notification as read visually
function markNotificationAsReadVisually(element) {
  element.classList.remove('unread');
  element.classList.add('read');
  
  // Update text styling
  const message = element.querySelector('.notification-message');
  if (message) {
    message.classList.remove('font-medium', 'text-gray-900');
    message.classList.add('text-gray-700');
  }
  
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

// Initialize notification read states from localStorage (tutor-specific)
document.addEventListener('DOMContentLoaded', function() {
  const readNotifications = JSON.parse(localStorage.getItem('tutorReadNotifications') || '[]');
  const allNotifications = document.querySelectorAll('.notification-item');
  
  allNotifications.forEach((notification) => {
    const notificationId = notification.dataset.id || notification.querySelector('.notification-message')?.textContent?.trim();
    if (readNotifications.includes(notificationId)) {
      markNotificationAsReadVisually(notification);
    }
  });
});