<?php
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('admin');

// Get current admin data from session
$admin_user_id = $_SESSION['user_id'] ?? null;

// Check if user_id is available
if (!$admin_user_id) {
  header('Location: ../../login.php');
  exit();
}

// Get admin's profile data
$user_data = getUserByUserId($admin_user_id);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action'])) {
    switch ($_POST['action']) {
      case 'update_profile':
        $update_data = [
          'username' => trim($_POST['username']),
          'email' => trim($_POST['email']),
          'first_name' => trim($_POST['first_name']),
          'middle_name' => trim($_POST['middle_name']),
          'last_name' => trim($_POST['last_name']),
          'contact_number' => trim($_POST['contact_number']),
          'address' => trim($_POST['address']),
          'bio' => trim($_POST['bio']),
        ];

        $result = updateAdminProfile($admin_user_id, $update_data);
        
        if ($result['success']) {
          $success_message = 'Profile updated successfully!';
          // Refresh profile data
          $user_data = getUserByUserId($admin_user_id);
        } else {
          $error_message = $result['message'] ?? 'Failed to update profile. Please try again.';
        }
        break;

      case 'update_password':
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
          $error_message = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
          $error_message = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
          $error_message = 'New password must be at least 6 characters long.';
        } else {
          $result = updateAdminPassword($admin_user_id, $current_password, $new_password);
          if ($result['success']) {
            $success_message = 'Password updated successfully!';
          } else {
            $error_message = $result['message'] ?? 'Failed to update password.';
          }
        }
        break;

      case 'update_profile_picture':
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
          $result = updateAdminProfilePicture($admin_user_id, $_FILES['profile_picture']);
          if ($result['success']) {
            $success_message = 'Profile picture updated successfully!';
            // Refresh profile data
            $user_data = getUserByUserId($admin_user_id);
          } else {
            $error_message = $result['message'] ?? 'Failed to update profile picture.';
          }
        } else {
          $error_message = 'Please select a valid image file.';
        }
        break;
    }
  }
}

// Set default values if user data is missing
$profile_data = $user_data ?: [
  'username' => '',
  'email' => '',
  'first_name' => '',
  'middle_name' => '',
  'last_name' => '',
  'contact_number' => '',
  'address' => '',
  'bio' => '',
  'profile_picture' => null,
  'created_at' => date('Y-m-d H:i:s'),
];

// Helper function to get initials
function getInitials($firstName, $lastName) {
  $first = !empty($firstName) ? strtoupper($firstName[0]) : '';
  $last = !empty($lastName) ? strtoupper($lastName[0]) : '';
  return $first . $last ?: 'A';
}

// Calculate stats
$total_users = getTotalUsersCount();
$total_programs = getTotalProgramsCount();
$total_revenue = getTotalRevenue();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - Admin Dashboard - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    .profile-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
    }
    
    .stat-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease;
    }
    
    .stat-card:hover {
      transform: translateY(-2px);
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <div class="flex">
    <?php include '../../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <!-- Header -->
      <header class="bg-white shadow-sm border-b border-gray-200 px-4 lg:px-6 py-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <button class="lg:hidden p-2 rounded-md text-gray-600 hover:bg-gray-100 mr-2" onclick="toggleSidebar()">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
              </svg>
            </button>
            <div>
              <h1 class="text-xl lg:text-2xl font-bold text-gray-800">My Profile</h1>
              <p class="text-sm text-gray-500"><?= date('l, F j, Y') ?></p>
            </div>
          </div>
          
          <div class="flex items-center space-x-4">
            <!-- Notifications -->
            <div class="relative">
              <button class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
                </svg>
              </button>
            </div>

            <!-- Profile -->
            <div class="flex items-center space-x-2">
              <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($profile_data['username'] ?: 'Admin') ?></span>
              <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                A
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content Area -->
      <main class="p-4 lg:p-6">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
          <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <?= htmlspecialchars($success_message) ?>
          </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
          <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            <?= htmlspecialchars($error_message) ?>
          </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-card p-6 mb-6">
          <div class="flex flex-col md:flex-row items-start md:items-center space-y-4 md:space-y-0 md:space-x-6">
            <!-- Profile Picture -->
            <div class="relative">
              <?php if (!empty($profile_data['profile_picture'])): ?>
                <img src="../../<?= htmlspecialchars($profile_data['profile_picture']) ?>" alt="Profile Picture" class="w-24 h-24 rounded-full object-cover border-4 border-purple-100">
              <?php else: ?>
                <div class="w-24 h-24 bg-purple-500 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                  <?= getInitials($profile_data['first_name'] ?? '', $profile_data['last_name'] ?? '') ?>
                </div>
              <?php endif; ?>
              <button onclick="document.getElementById('profilePictureInput').click()" class="absolute bottom-0 right-0 bg-tplearn-green text-white p-2 rounded-full hover:bg-green-600 transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                </svg>
              </button>
            </div>

            <!-- Profile Info -->
            <div class="flex-1">
              <h2 class="text-2xl font-bold text-gray-900">
                <?= htmlspecialchars(trim(($profile_data['first_name'] ?? '') . ' ' . ($profile_data['last_name'] ?? '')) ?: $profile_data['username'] ?: 'Admin User') ?>
              </h2>
              <p class="text-gray-600 mb-2">System Administrator</p>
              <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                Active
              </span>
            </div>

            <!-- Stats -->
            <div class="flex space-x-6 text-center">
              <div>
                <div class="text-2xl font-bold text-purple-600"><?= $total_users ?></div>
                <div class="text-sm text-gray-500">Users</div>
              </div>
              <div>
                <div class="text-2xl font-bold text-blue-600"><?= $total_programs ?></div>
                <div class="text-sm text-gray-500">Programs</div>
              </div>
              <div>
                <div class="text-2xl font-bold text-green-600">₱<?= number_format($total_revenue, 2) ?></div>
                <div class="text-sm text-gray-500">Revenue</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Profile Information -->
          <div class="lg:col-span-2">
            <div class="profile-card p-6">
              <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Profile Information</h3>
                <button onclick="toggleEdit()" id="editBtn" class="text-tplearn-green hover:text-green-700 font-medium">
                  Edit
                </button>
              </div>

              <form method="POST" id="profileForm">
                <input type="hidden" name="action" value="update_profile">
                
                <!-- Personal Information -->
                <div class="mb-6">
                  <h4 class="text-md font-medium text-gray-800 mb-4">Personal Information</h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                      <input type="text" name="first_name" value="<?= htmlspecialchars($profile_data['first_name'] ?? '') ?>" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent profile-input" 
                             readonly>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                      <input type="text" name="middle_name" value="<?= htmlspecialchars($profile_data['middle_name'] ?? '') ?>" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent profile-input" 
                             readonly>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                      <input type="text" name="last_name" value="<?= htmlspecialchars($profile_data['last_name'] ?? '') ?>" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent profile-input" 
                             readonly>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                      <input type="text" name="username" value="<?= htmlspecialchars($profile_data['username'] ?? '') ?>" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent profile-input" 
                             readonly>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                      <input type="email" name="email" value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent profile-input" 
                             readonly>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                      <input type="text" name="contact_number" value="<?= htmlspecialchars($profile_data['contact_number'] ?? '') ?>" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent profile-input" 
                             readonly>
                    </div>
                  </div>
                </div>

                <!-- Address -->
                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                  <textarea name="address" rows="3" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent profile-input" 
                            readonly><?= htmlspecialchars($profile_data['address'] ?? '') ?></textarea>
                </div>

                <!-- Bio -->
                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                  <textarea name="bio" rows="4" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent profile-input" 
                            readonly><?= htmlspecialchars($profile_data['bio'] ?? '') ?></textarea>
                </div>

                <!-- Save Button -->
                <div class="flex justify-end" id="saveButtonContainer" style="display: none;">
                  <div class="space-x-2">
                    <button type="button" onclick="cancelEdit()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                      Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600">
                      Save Changes
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Account Information -->
            <div class="profile-card p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h3>
              
              <div class="space-y-3">
                <div>
                  <span class="text-sm text-gray-500">User ID</span>
                  <p class="font-medium"><?= htmlspecialchars($user_data['user_id'] ?? '#N/A') ?></p>
                </div>
                <div>
                  <span class="text-sm text-gray-500">Member Since</span>
                  <p class="font-medium"><?= date('F j, Y', strtotime($profile_data['created_at'])) ?></p>
                </div>
                <div>
                  <span class="text-sm text-gray-500">Account Status</span>
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                    Active
                  </span>
                </div>
              </div>
            </div>

            <!-- Quick Actions -->
            <div class="profile-card p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
              
              <div class="space-y-3">
                <button onclick="openPasswordModal()" class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                      <p class="font-medium text-gray-900">Change Password</p>
                      <p class="text-sm text-gray-500">Update your account password</p>
                    </div>
                  </div>
                </button>
                
                <a href="../admin/admin-tools.php" class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors block">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                      <p class="font-medium text-gray-900">Admin Tools</p>
                      <p class="text-sm text-gray-500">Manage users and system settings</p>
                    </div>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Hidden Profile Picture Upload Form -->
  <form method="POST" enctype="multipart/form-data" id="profilePictureForm" style="display: none;">
    <input type="hidden" name="action" value="update_profile_picture">
    <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" onchange="submitProfilePicture()">
  </form>

  <!-- Password Change Modal -->
  <div id="passwordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Change Password</h3>
        <button onclick="closePasswordModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="update_password">
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
            <input type="password" name="current_password" required 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
            <input type="password" name="new_password" required minlength="6"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-transparent">
          </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
          <button type="button" onclick="closePasswordModal()" 
                  class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
            Cancel
          </button>
          <button type="submit" 
                  class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600">
            Update Password
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="../../assets/admin-sidebar.js"></script>
  <script>
    let isEditing = false;

    function toggleEdit() {
      isEditing = !isEditing;
      const inputs = document.querySelectorAll('.profile-input');
      const editBtn = document.getElementById('editBtn');
      const saveContainer = document.getElementById('saveButtonContainer');

      if (isEditing) {
        inputs.forEach(input => input.removeAttribute('readonly'));
        editBtn.textContent = 'Cancel';
        saveContainer.style.display = 'block';
      } else {
        inputs.forEach(input => input.setAttribute('readonly', true));
        editBtn.textContent = 'Edit';
        saveContainer.style.display = 'none';
        // Reset form to original values
        location.reload();
      }
    }

    function cancelEdit() {
      location.reload();
    }

    function submitProfilePicture() {
      document.getElementById('profilePictureForm').submit();
    }

    function openPasswordModal() {
      document.getElementById('passwordModal').classList.remove('hidden');
      document.getElementById('passwordModal').classList.add('flex');
    }

    function closePasswordModal() {
      document.getElementById('passwordModal').classList.add('hidden');
      document.getElementById('passwordModal').classList.remove('flex');
    }

    // Close modal when clicking outside
    document.getElementById('passwordModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closePasswordModal();
      }
    });
  </script>
</body>

</html>