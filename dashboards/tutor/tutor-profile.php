<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireRole('tutor');

// Fetch tutor data from database
$user_id = getCurrentUserId();
$tutor_data = null;
$error_message = null;

try {
    // Get tutor profile with user information
    $sql = "SELECT 
                tp.*,
                u.username,
                u.email as user_email,
                u.created_at,
                u.last_login
            FROM tutor_profiles tp 
            JOIN users u ON tp.user_id = u.id 
            WHERE tp.user_id = ? AND u.status = 'active'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = "Tutor profile not found. Please contact support.";
    } else {
        $tutor_data = $result->fetch_assoc();
        
        // Format creation date for display
        if (!empty($tutor_data['created_at'])) {
            $created = new DateTime($tutor_data['created_at']);
            $tutor_data['member_since'] = $created->format('F j, Y');
        }
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Set default values if tutor data is missing
if (!$tutor_data) {
    $tutor_data = [
        'first_name' => 'Tutor',
        'middle_name' => '',
        'last_name' => 'User',
        'member_since' => 'Not specified',
        'gender' => '',
        'suffix' => '',
        'contact_number' => 'Not specified',
        'address' => 'Not specified',
        'bachelor_degree' => 'Not specified',
        'specializations' => 'Not specified',
        'bio' => 'Not specified',
        'user_email' => 'Not specified'
    ];
}

// Helper function to get initials
function getInitials($firstName, $lastName) {
  $first = !empty($firstName) ? strtoupper($firstName[0]) : '';
  $last = !empty($lastName) ? strtoupper($lastName[0]) : '';
  return $first . $last ?: 'T';
}

// Get tutor stats (placeholder values - implement these functions in your data-helpers.php)
$total_programs = 0; // Placeholder for tutor's programs count
$total_students = 0; // Placeholder for tutor's students count
$total_assessments = 0; // Placeholder for tutor's assessments count
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tutor Profile - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'tplearn-green': '#10b981',
            'tplearn-light-green': '#34d399',
          }
        }
      }
    };
  </script>
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
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/tutor-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/header.php';
      renderHeader(
        'Tutor Profile',
        '',
        'tutor',
        trim(($tutor_data['first_name'] ?? 'Tutor') . ' ' . ($tutor_data['last_name'] ?? 'User')),
        [], // notifications array - to be implemented
        []  // messages array - to be implemented
      );
      ?>

      <!-- Main Content Area -->
      <main class="p-4 lg:p-6">
        <!-- Success/Error Messages -->
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
              <div class="w-24 h-24 bg-tplearn-green rounded-full flex items-center justify-center text-white text-2xl font-bold">
                <?= getInitials($tutor_data['first_name'] ?? '', $tutor_data['last_name'] ?? '') ?>
              </div>
              <button onclick="changeProfilePicture()" class="absolute bottom-0 right-0 bg-tplearn-green text-white p-2 rounded-full hover:bg-green-600 transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                </svg>
              </button>
            </div>

            <!-- Profile Info -->
            <div class="flex-1">
              <h2 class="text-2xl font-bold text-gray-900">
                <?= htmlspecialchars(trim(($tutor_data['first_name'] ?? '') . ' ' . ($tutor_data['last_name'] ?? '')) ?: 'Tutor User') ?>
              </h2>
              <p class="text-gray-600 mb-2"><?= htmlspecialchars($tutor_data['user_email']) ?></p>
              <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                Active Tutor
              </span>
            </div>

            <!-- Stats -->
            <div class="flex space-x-6 text-center">
              <div>
                <div class="text-2xl font-bold text-purple-600"><?= $total_programs ?></div>
                <div class="text-sm text-gray-500">Programs</div>
              </div>
              <div>
                <div class="text-2xl font-bold text-blue-600"><?= $total_students ?></div>
                <div class="text-sm text-gray-500">Students</div>
              </div>
              <div>
                <div class="text-2xl font-bold text-green-600"><?= $total_assessments ?></div>
                <div class="text-sm text-gray-500">Assessments</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Profile Information -->
          <div class="lg:col-span-2">
            <div class="profile-card p-6 mb-6">
              <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Profile Information</h3>
                <button onclick="editProfile()" class="text-tplearn-green hover:text-green-700 font-medium">
                  Edit
                </button>
              </div>

              <!-- Personal Information -->
              <div class="mb-6">
                <h4 class="text-md font-medium text-gray-800 mb-4">Personal Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($tutor_data['first_name']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($tutor_data['middle_name']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($tutor_data['last_name']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($tutor_data['gender'] ?: 'Not specified') ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Suffix</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($tutor_data['suffix'] ?: 'Not specified') ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($tutor_data['contact_number']) ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Address -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                  <?= htmlspecialchars($tutor_data['address']) ?>
                </div>
              </div>

              <!-- Professional Information -->
              <div class="mb-6">
                <h4 class="text-md font-medium text-gray-800 mb-4">Professional Information</h4>
                <div class="grid grid-cols-1 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bachelor's Degree</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($tutor_data['bachelor_degree']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Specializations</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 min-h-[60px]">
                      <?= htmlspecialchars($tutor_data['specializations']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 min-h-[60px]">
                      <?= htmlspecialchars($tutor_data['bio']) ?>
                    </div>
                  </div>
                </div>
              </div>
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
                  <p class="font-medium">#<?= str_pad($user_id, 3, '0', STR_PAD_LEFT) ?></p>
                </div>
                <div>
                  <span class="text-sm text-gray-500">Member Since</span>
                  <p class="font-medium"><?= htmlspecialchars($tutor_data['member_since']) ?></p>
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
                <button onclick="editProfile()" class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                    </svg>
                    <div>
                      <p class="font-medium text-gray-900">Edit Profile</p>
                      <p class="text-sm text-gray-500">Update your personal information</p>
                    </div>
                  </div>
                </button>
                
                <a href="../tutor/tutor-programs.php" class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors block">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                      <p class="font-medium text-gray-900">My Programs</p>
                      <p class="text-sm text-gray-500">Check your programs and students</p>
                    </div>
                  </div>
                </a>
                
                <a href="../tutor/tutor-students.php" class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors block">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                      <p class="font-medium text-gray-900">My Students</p>
                      <p class="text-sm text-gray-500">View and manage students</p>
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

  <!-- Edit Profile Modal -->
  <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center p-4 min-h-screen">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Edit Profile</h2>
        <button onclick="closeEditModal()" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>

      <!-- Modal Content -->
      <form id="editProfileForm" class="p-6">
        <!-- Personal Information Section -->
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-6 text-gray-800">Personal Information</h2>

          <!-- Name Fields -->
          <div class="grid md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">First Name <span class="text-red-500">*</span></label>
              <input type="text" id="edit_first_name" value="<?php echo htmlspecialchars($tutor_data['first_name']); ?>" 
                required maxlength="50" pattern="[a-zA-Z\s'-]{2,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter first name">
            </div>
            <div>
              <label class="block mb-1 text-sm">Last Name <span class="text-red-500">*</span></label>
              <input type="text" id="edit_last_name" value="<?php echo htmlspecialchars($tutor_data['last_name']); ?>" 
                required maxlength="50" pattern="[a-zA-Z\s'-]{2,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter last name">
            </div>
            <div>
              <label class="block mb-1 text-sm">Middle Name</label>
              <input type="text" id="edit_middle_name" value="<?php echo htmlspecialchars($tutor_data['middle_name']); ?>" 
                maxlength="50" pattern="[a-zA-Z\s'-]{1,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter middle name (optional)">
            </div>
          </div>

          <!-- Gender and Suffix -->
          <div class="grid md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">Gender</label>
              <select id="edit_gender" 
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Gender</option>
                <option value="Male" <?php echo ($tutor_data['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($tutor_data['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo ($tutor_data['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>
            <div>
              <label class="block mb-1 text-sm">Suffix</label>
              <input type="text" id="edit_suffix" value="<?php echo htmlspecialchars($tutor_data['suffix']); ?>" 
                maxlength="20" pattern="[a-zA-Z\s.]{1,20}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Jr., Sr., III, etc. (optional)">
            </div>
          </div>

          <!-- Contact Information -->
          <div class="grid md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">Contact Number <span class="text-red-500">*</span></label>
              <input type="tel" id="edit_contact_number" value="<?php echo htmlspecialchars($tutor_data['contact_number']); ?>" 
                required pattern="[+]?[0-9]{10,15}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter contact number">
            </div>
            <div>
              <label class="block mb-1 text-sm">Email Address</label>
              <input type="email" id="edit_email" value="<?php echo htmlspecialchars($tutor_data['user_email']); ?>" 
                readonly class="w-full border border-gray-300 px-3 py-2 rounded bg-gray-50"
                placeholder="Email address (read-only)">
            </div>
          </div>

          <!-- Address -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Address <span class="text-red-500">*</span></label>
            <textarea id="edit_address" rows="3" required maxlength="500"
              placeholder="Enter your complete address"
              class="w-full border border-gray-300 px-3 py-2 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($tutor_data['address']); ?></textarea>
          </div>
        </div>

        <!-- Professional Information Section -->
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-6 text-gray-800">Professional Information</h2>

          <!-- Bachelor's Degree -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Bachelor's Degree <span class="text-red-500">*</span></label>
            <input type="text" id="edit_bachelor_degree" value="<?php echo htmlspecialchars($tutor_data['bachelor_degree']); ?>" 
              required maxlength="200"
              class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter your bachelor's degree">
          </div>

          <!-- Specializations -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Specializations <span class="text-red-500">*</span></label>
            <textarea id="edit_specializations" rows="3" required maxlength="500"
              placeholder="Enter your areas of specialization"
              class="w-full border border-gray-300 px-3 py-2 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($tutor_data['specializations']); ?></textarea>
          </div>

          <!-- Bio -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Bio</label>
            <textarea id="edit_bio" rows="4" maxlength="1000"
              placeholder="Tell us about yourself and your teaching philosophy (optional)"
              class="w-full border border-gray-300 px-3 py-2 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($tutor_data['bio']); ?></textarea>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex flex-col sm:flex-row gap-3 pt-6 border-t border-gray-200">
          <button type="submit" class="flex-1 bg-tplearn-green text-white px-6 py-3 rounded-lg font-medium hover:bg-green-600 transition-colors">
            Save Changes
          </button>
          <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-100 text-gray-700 px-6 py-3 rounded-lg font-medium hover:bg-gray-200 transition-colors">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Profile Management Functions
function editProfile() {
  document.getElementById('editModal').classList.remove('hidden');
  document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
  document.getElementById('editModal').classList.add('hidden');
  document.getElementById('editModal').classList.remove('flex');
}

function changeProfilePicture() {
  alert('Profile picture upload functionality will be implemented soon.');
}

// Form Submission
document.getElementById('editProfileForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = {
    first_name: document.getElementById('edit_first_name').value,
    middle_name: document.getElementById('edit_middle_name').value,
    last_name: document.getElementById('edit_last_name').value,
    gender: document.getElementById('edit_gender').value,
    suffix: document.getElementById('edit_suffix').value,
    contact_number: document.getElementById('edit_contact_number').value,
    address: document.getElementById('edit_address').value,
    bachelor_degree: document.getElementById('edit_bachelor_degree').value,
    specializations: document.getElementById('edit_specializations').value,
    bio: document.getElementById('edit_bio').value
  };
  
  // Show loading state
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const originalText = submitBtn.textContent;
  submitBtn.textContent = 'Saving...';
  submitBtn.disabled = true;
  
  // API call to update tutor profile
  fetch('../../api/tutor-profile.php', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(formData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Show success message
      showNotification('Profile updated successfully!', 'success');
      
      // Close modal and reload page to reflect changes
      closeEditModal();
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      throw new Error(data.error || 'Unknown error occurred');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showNotification('Error updating profile: ' + error.message, 'error');
  })
  .finally(() => {
    // Reset button state
    submitBtn.textContent = originalText;
    submitBtn.disabled = false;
  });
});

// Notification System
function showNotification(message, type = 'info') {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll('.notification');
  existingNotifications.forEach(notification => notification.remove());
  
  // Create notification element
  const notification = document.createElement('div');
  notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white font-medium max-w-sm`;
  
  // Set notification style based on type
  switch(type) {
    case 'success':
      notification.classList.add('bg-green-500');
      break;
    case 'error':
      notification.classList.add('bg-red-500');
      break;
    case 'warning':
      notification.classList.add('bg-yellow-500');
      break;
    default:
      notification.classList.add('bg-blue-500');
  }
  
  notification.textContent = message;
  
  // Add to document
  document.body.appendChild(notification);
  
  // Show notification with animation
  setTimeout(() => {
    notification.classList.add('transform', 'translate-x-0');
    notification.style.transform = 'translateX(0)';
  }, 100);
  
  // Auto-hide after 5 seconds
  setTimeout(() => {
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 5000);
}

// Close modal on outside click
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeEditModal();
  }
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && !document.getElementById('editModal').classList.contains('hidden')) {
    closeEditModal();
  }
});
</script>

</body>
</html>
                  <?= htmlspecialchars($tutor_data['first_name'] . ' ' . $tutor_data['last_name']) ?>
                  <?php if (!empty($tutor_data['suffix'])): ?>
                    <?= ' ' . htmlspecialchars($tutor_data['suffix']) ?>
                  <?php endif; ?>
                </h1>
                <p class="text-tplearn-gray mb-2"><?= htmlspecialchars($tutor_data['user_email']) ?></p>
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                  <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                    </svg>
                    Member since <?= htmlspecialchars($tutor_data['member_since']) ?>
                  </span>
                  <?php if (!empty($tutor_data['gender'])): ?>
                  <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                    </svg>
                    <?= htmlspecialchars($tutor_data['gender']) ?>
                  </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 mt-4 lg:mt-0">
              <button onclick="editProfile()" class="btn-primary flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Profile
              </button>
            </div>
          </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Main Profile Information -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Personal Information -->
            <div class="profile-card p-6">
              <h2 class="text-xl font-semibold text-gray-900 mb-6">Personal Information</h2>
              
              <div class="grid md:grid-cols-2 gap-6">
                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                  <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">


      <!-- Modal Content -->
      <form id="editProfileForm" class="p-6">
        <!-- Personal Information Section -->
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-6 text-gray-800">Personal Information</h2>

          <!-- Name Fields -->
          <div class="grid md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">First Name <span class="text-red-500">*</span></label>
              <input type="text" id="edit_first_name" value="<?php echo htmlspecialchars($tutor_data['first_name']); ?>" 
                required maxlength="50" pattern="[a-zA-Z\s'-]{2,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter first name">
            </div>
            <div>
              <label class="block mb-1 text-sm">Last Name <span class="text-red-500">*</span></label>
              <input type="text" id="edit_last_name" value="<?php echo htmlspecialchars($tutor_data['last_name']); ?>" 
                required maxlength="50" pattern="[a-zA-Z\s'-]{2,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter last name">
            </div>
            <div>
              <label class="block mb-1 text-sm">Middle Name</label>
              <input type="text" id="edit_middle_name" value="<?php echo htmlspecialchars($tutor_data['middle_name']); ?>" 
                maxlength="50" pattern="[a-zA-Z\s'-]{1,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter middle name (optional)">
            </div>
          </div>

          <!-- Gender and Suffix -->
          <div class="grid md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">Gender</label>
              <select id="edit_gender" 
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Gender</option>
                <option value="Male" <?php echo ($tutor_data['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($tutor_data['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo ($tutor_data['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>
            <div>
              <label class="block mb-1 text-sm">Suffix</label>
              <input type="text" id="edit_suffix" value="<?php echo htmlspecialchars($tutor_data['suffix']); ?>" 
                maxlength="20" pattern="[a-zA-Z\s.]{1,20}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Jr., Sr., III, etc. (optional)">
            </div>
          </div>

          <!-- Contact Information -->
          <div class="grid md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">Contact Number</label>
              <input type="text" id="edit_contact_number" value="<?php echo htmlspecialchars($tutor_data['contact_number']); ?>" 
                maxlength="20" pattern="[\+]?[0-9\s\-\(\)]{10,20}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter contact number">
            </div>
            <div>
              <label class="block mb-1 text-sm">Email Address</label>
              <input type="email" id="edit_email" value="<?php echo htmlspecialchars($tutor_data['user_email']); ?>" 
                readonly
                class="w-full border border-gray-300 px-3 py-2 rounded bg-gray-100 cursor-not-allowed"
                placeholder="Email address (read-only)">
              <small class="text-gray-500">Email cannot be changed. Contact support if needed.</small>
            </div>
          </div>

          <!-- Address -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Address</label>
            <textarea id="edit_address" rows="3" 
              class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter full address"><?php echo htmlspecialchars($tutor_data['address']); ?></textarea>
          </div>
        </div>

        <!-- Professional Information Section -->
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-6 text-gray-800">Professional Information</h2>

          <!-- Bachelor's Degree -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Bachelor's Degree</label>
            <input type="text" id="edit_bachelor_degree" value="<?php echo htmlspecialchars($tutor_data['bachelor_degree']); ?>" 
              maxlength="100"
              class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter your bachelor's degree">
          </div>

          <!-- Specializations -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Specializations</label>
            <textarea id="edit_specializations" rows="3" 
              class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter your specializations and expertise areas"><?php echo htmlspecialchars($tutor_data['specializations']); ?></textarea>
          </div>

          <!-- Bio -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Bio</label>
            <textarea id="edit_bio" rows="4" 
              class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Tell us about yourself, your teaching experience, and approach"><?php echo htmlspecialchars($tutor_data['bio']); ?></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
          <button type="button" onclick="closeEditModal()" 
            class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
            Cancel
          </button>
          <button type="button" onclick="saveProfile()" 
            class="px-6 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Profile editing functions
    function editProfile() {
      document.getElementById('editModal').classList.remove('hidden');
      document.getElementById('editModal').classList.add('flex');
      document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
      document.getElementById('editModal').classList.add('hidden');
      document.getElementById('editModal').classList.remove('flex');
      document.body.style.overflow = 'auto';
    }

    function changeProfilePicture() {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            const avatarDiv = document.querySelector('.w-24.h-24.bg-tplearn-green');
            avatarDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Picture" class="w-full h-full object-cover rounded-full">`;
            showSuccessMessage('Profile picture updated successfully!');
          };
          reader.readAsDataURL(file);
        }
      };
      input.click();
    }

    async function saveProfile() {
      try {
        // Show loading state
        const saveButton = document.querySelector('button[onclick="saveProfile()"]');
        const originalText = saveButton.textContent;
        saveButton.textContent = 'Saving...';
        saveButton.disabled = true;

        // Get all form values
        const formData = {
          first_name: document.getElementById('edit_first_name').value.trim(),
          middle_name: document.getElementById('edit_middle_name').value.trim(),
          last_name: document.getElementById('edit_last_name').value.trim(),
          gender: document.getElementById('edit_gender').value.trim(),
          suffix: document.getElementById('edit_suffix').value.trim(),
          contact_number: document.getElementById('edit_contact_number').value.trim(),
          address: document.getElementById('edit_address').value.trim(),
          bachelor_degree: document.getElementById('edit_bachelor_degree').value.trim(),
          specializations: document.getElementById('edit_specializations').value.trim(),
          bio: document.getElementById('edit_bio').value.trim()
        };

        // Client-side validation
        if (!formData.first_name || !formData.last_name) {
          throw new Error('First Name and Last Name are required.');
        }

        // Send update request
        const response = await fetch('../../api/tutor-profile.php', {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.ok && result.success) {
          showSuccessMessage('Profile updated successfully!');
          closeEditModal();
          
          // Refresh the page to show updated data
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          throw new Error(result.error || 'Failed to update profile');
        }

      } catch (error) {
        console.error('Error updating profile:', error);
        showErrorMessage(error.message || 'Failed to update profile. Please try again.');
      } finally {
        // Reset button state
        const saveButton = document.querySelector('button[onclick="saveProfile()"]');
        if (saveButton) {
          saveButton.textContent = 'Save Changes';
          saveButton.disabled = false;
        }
      }
    }

    // Notification functions
    function showSuccessMessage(message) {
      showNotification(message, 'success');
    }

    function showErrorMessage(message) {
      showNotification(message, 'error');
    }

    function showNotification(message, type = 'success') {
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white transform transition-transform duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
      }`;
      notification.innerHTML = `
        <div class="flex items-center justify-between">
          <span>${message}</span>
          <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
        </div>
      `;
      
      document.body.appendChild(notification);
      
      // Show notification
      setTimeout(() => {
        notification.classList.remove('translate-x-full');
      }, 100);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        if (notification.parentElement) {
          notification.classList.add('translate-x-full');
          setTimeout(() => notification.remove(), 300);
        }
      }, 5000);
    }

    // Quick action functions
    function openChangePasswordModal() {
      showNotification('Change password feature coming soon!', 'info');
    }

    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const mobileCloseButton = document.getElementById('mobile-close-button');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('mobile-menu-overlay');
      
      function openMobileMenu() {
        if (sidebar && overlay) {
          sidebar.classList.remove('-translate-x-full');
          overlay.classList.remove('hidden');
        }
      }

      function closeMobileMenu() {
        if (sidebar && overlay) {
          sidebar.classList.add('-translate-x-full');
          overlay.classList.add('hidden');
        }
      }

      // Event listeners
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
              setTimeout(closeMobileMenu, 100);
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