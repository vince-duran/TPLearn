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
  $notificationCount = count($notifications);
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
  
  // Role-based colors
  $roleColors = [
    'student' => 'bg-green-600',
    'admin' => 'bg-blue-600', 
    'tutor' => 'bg-emerald-600'
  ];
  
  $avatarColor = $roleColors[$userRole] ?? 'bg-gray-600';
  
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
        <button onclick="openNotifications()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tplearn-green">
          <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
          </svg>
        </button>
        <?php if ($notificationCount > 0): ?>
          <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">
            <?= $notificationCount > 99 ? '99+' : $notificationCount ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Messages -->
      <div class="relative">
        <button onclick="openMessages()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tplearn-green">
          <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
          </svg>
        </button>
        <?php if ($messageCount > 0): ?>
          <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">
            <?= $messageCount > 99 ? '99+' : $messageCount ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Profile -->
      <div class="flex items-center space-x-3">
        <div class="hidden sm:block text-right">
          <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($userName) ?></p>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($userId) ?></p>
        </div>
        
        <!-- Profile Avatar -->
        <div class="relative">
          <button onclick="toggleProfileDropdown()" class="<?= $avatarColor ?> w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" style="background-color: <?= $userRole === 'student' ? '#059669' : ($userRole === 'admin' ? '#2563eb' : '#10b981') ?>; min-width: 2.5rem; min-height: 2.5rem;">
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

// TPLearn SweetAlert2 Helper Functions (Global)
if (typeof Swal !== 'undefined') {
  window.TPAlert = {
    success: (title, text = '') => {
      return Swal.fire({
        title: title,
        text: text,
        icon: 'success',
        confirmButtonText: 'Great!',
        confirmButtonColor: '#10b981',
        timer: 3000,
        timerProgressBar: true,
        toast: false,
        position: 'center'
      });
    },
    
    error: (title, text = '') => {
      return Swal.fire({
        title: title,
        text: text,
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#ef4444'
      });
    },
    
    warning: (title, text = '') => {
      return Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        confirmButtonText: 'OK',
        confirmButtonColor: '#f59e0b'
      });
    },
    
    info: (title, text = '') => {
      return Swal.fire({
        title: title,
        text: text,
        icon: 'info',
        confirmButtonText: 'OK',
        confirmButtonColor: '#3b82f6'
      });
    },
    
    confirm: (title, text = '', confirmText = 'Yes', cancelText = 'Cancel') => {
      return Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
      });
    },
    
    loading: (title = 'Processing...', text = 'Please wait') => {
      return Swal.fire({
        title: title,
        text: text,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
    },
    
    toast: (message, type = 'success') => {
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer)
          toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
      });
      
      return Toast.fire({
        icon: type,
        title: message
      });
    },
    
    close: () => {
      Swal.close();
    }
  };
}
</script>

<?php
}
?>