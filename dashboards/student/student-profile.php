<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireRole('student');

// Fetch student data from database
$user_id = getCurrentUserId();
$student_data = null;
$error_message = null;

try {
    // Get student profile with user information and parent details
    $sql = "SELECT 
                sp.*,
                u.username,
                u.email as user_email,
                u.created_at,
                u.last_login,
                pp.full_name as parent_guardian_name,
                pp.facebook_name,
                pp.contact_number as phone,
                pp.address as parent_address
            FROM student_profiles sp 
            JOIN users u ON sp.user_id = u.id 
            LEFT JOIN parent_profiles pp ON sp.user_id = pp.student_user_id
            WHERE sp.user_id = ? AND u.status = 'active'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = "Student profile not found. Please contact support.";
    } else {
        $student_data = $result->fetch_assoc();
        
        // Calculate age from birthday
        if (!empty($student_data['birthday'])) {
            $birthday = new DateTime($student_data['birthday']);
            $today = new DateTime();
            $age = $today->diff($birthday)->y;
            $student_data['calculated_age'] = $age;
            $student_data['age_display'] = $age . ' years old';
            
            // Format birthday for display
            $student_data['birthday_display'] = $birthday->format('F j, Y');
        } else {
            $student_data['age_display'] = 'Not specified';
            $student_data['birthday_display'] = 'Not specified';
        }
        
        // Use email from profile, fallback to user email
        if (empty($student_data['email']) && !empty($student_data['user_email'])) {
            $student_data['email'] = $student_data['user_email'];
        }
        
        // Map database fields to expected field names
        if (!empty($student_data['address'])) {
            $student_data['home_address'] = $student_data['address'];
        }
        if (!empty($student_data['medical_notes'])) {
            $student_data['medical_history'] = $student_data['medical_notes'];
        }
        // Convert is_pwd (0/1) to pwd_status (Yes/No)
        $student_data['pwd_status'] = (isset($student_data['is_pwd']) && $student_data['is_pwd'] == 1) ? 'Yes' : 'No';
        
        // Set default values for empty fields
        $defaults = [
            'first_name' => 'Not specified',
            'middle_name' => '',
            'last_name' => 'Not specified',
            'pwd_status' => 'No',
            'medical_history' => 'N/A',
            'parent_guardian_name' => 'Not specified',
            'facebook_name' => '',
            'email' => 'Not specified',
            'home_address' => 'Not specified',
            'phone' => 'Not specified'
        ];
        
        foreach ($defaults as $key => $default) {
            if (empty($student_data[$key])) {
                $student_data[$key] = $default;
            }
        }
    }
    
} catch (Exception $e) {
    $error_message = "Error loading profile: " . $e->getMessage();
    error_log("Student profile error for user $user_id: " . $e->getMessage());
}

// If there's an error or no data, create a minimal profile for display
if ($error_message || !$student_data) {
    $student_data = [
        'first_name' => 'Student',
        'middle_name' => '',
        'last_name' => 'User',
        'birthday_display' => 'Not specified',
        'age_display' => 'Not specified',
        'pwd_status' => 'No',
        'medical_history' => 'N/A',
        'parent_guardian_name' => 'Not specified',
        'facebook_name' => '',
        'email' => 'Not specified',
        'home_address' => 'Not specified',
        'phone' => 'Not specified'
    ];
}

// Helper function to get initials
function getInitials($firstName, $lastName) {
  $first = !empty($firstName) ? strtoupper($firstName[0]) : '';
  $last = !empty($lastName) ? strtoupper($lastName[0]) : '';
  return $first . $last ?: 'S';
}

// Get student stats (you can implement these functions in your data-helpers.php)
$enrolled_programs = 0; // Placeholder for student's enrolled programs count
$completed_assessments = 0; // Placeholder for completed assessments count
$total_attendance = 0; // Placeholder for attendance percentage
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Profile - TPLearn</title>
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

    <?php include '../../includes/student-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/header.php';
      renderHeader(
        'Student Profile',
        '',
        'student',
        trim(($student_data['first_name'] ?? 'Student') . ' ' . ($student_data['last_name'] ?? 'User')),
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
                <?= getInitials($student_data['first_name'] ?? '', $student_data['last_name'] ?? '') ?>
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
                <?= htmlspecialchars(trim(($student_data['first_name'] ?? '') . ' ' . ($student_data['last_name'] ?? '')) ?: 'Student User') ?>
              </h2>
              <p class="text-gray-600 mb-2"><?= htmlspecialchars($student_data['age_display']) ?></p>
              <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                Active Student
              </span>
            </div>

            <!-- Stats -->
            <div class="flex space-x-6 text-center">
              <div>
                <div class="text-2xl font-bold text-purple-600"><?= $enrolled_programs ?></div>
                <div class="text-sm text-gray-500">Programs</div>
              </div>
              <div>
                <div class="text-2xl font-bold text-blue-600"><?= $completed_assessments ?></div>
                <div class="text-sm text-gray-500">Assessments</div>
              </div>
              <div>
                <div class="text-2xl font-bold text-green-600"><?= $total_attendance ?>%</div>
                <div class="text-sm text-gray-500">Attendance</div>
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
                      <?= htmlspecialchars($student_data['first_name']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['middle_name']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['last_name']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['gender'] ?: 'Not specified') ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Suffix</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['suffix'] ?: 'Not specified') ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Birthday</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['birthday_display']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">PWD Status</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['pwd_status']) ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Address -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Home Address</label>
                <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                  <?= htmlspecialchars($student_data['home_address']) ?>
                </div>
              </div>

              <!-- Medical History -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Medical History</label>
                <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 min-h-[60px]">
                  <?= htmlspecialchars($student_data['medical_history']) ?>
                </div>
              </div>

              <!-- Parent Information -->
              <div class="mb-6">
                <h4 class="text-md font-medium text-gray-800 mb-4">Parent/Guardian Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Parent's Full Name</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['parent_guardian_name']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Facebook Name</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['facebook_name']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['phone']) ?>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                      <?= htmlspecialchars($student_data['email']) ?>
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
                  <span class="text-sm text-gray-500">Student ID</span>
                  <p class="font-medium">TP2025-<?= str_pad($user_id, 3, '0', STR_PAD_LEFT) ?></p>
                </div>
                <div>
                  <span class="text-sm text-gray-500">Member Since</span>
                  <p class="font-medium"><?= date('F j, Y', strtotime($student_data['created_at'] ?? 'now')) ?></p>
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
                
                <a href="../student/academics.php" class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors block">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                      <p class="font-medium text-gray-900">View Academics</p>
                      <p class="text-sm text-gray-500">Check your programs and progress</p>
                    </div>
                  </div>
                </a>
                
                <a href="../student/student-payments.php" class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors block">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                      <p class="font-medium text-gray-900">Payment History</p>
                      <p class="text-sm text-gray-500">View your payment records</p>
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
        <!-- Child's Details Section -->
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-6 text-gray-800">Child's Details</h2>

          <!-- Name Fields -->
          <div class="grid md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">First Name <span class="text-red-500">*</span></label>
              <input type="text" id="edit_first_name" value="<?php echo htmlspecialchars($student_data['first_name']); ?>" 
                required maxlength="50" pattern="[a-zA-Z\s'-]{2,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter first name">
            </div>
            <div>
              <label class="block mb-1 text-sm">Last Name <span class="text-red-500">*</span></label>
              <input type="text" id="edit_last_name" value="<?php echo htmlspecialchars($student_data['last_name']); ?>" 
                required maxlength="50" pattern="[a-zA-Z\s'-]{2,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter last name">
            </div>
            <div>
              <label class="block mb-1 text-sm">Middle Name</label>
              <input type="text" id="edit_middle_name" value="<?php echo htmlspecialchars($student_data['middle_name']); ?>" 
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
                <option value="Male" <?php echo ($student_data['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($student_data['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo ($student_data['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>
            <div>
              <label class="block mb-1 text-sm">Suffix</label>
              <input type="text" id="edit_suffix" value="<?php echo htmlspecialchars($student_data['suffix']); ?>" 
                maxlength="20" pattern="[a-zA-Z\s.]{1,20}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Jr., Sr., III, etc. (optional)">
            </div>
          </div>

          <!-- Birthday, Age, PWD Status -->
          <div class="grid md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">Birthday <span class="text-red-500">*</span></label>
              <input type="date" id="edit_birthday" value="<?php echo !empty($student_data['birthday']) ? date('Y-m-d', strtotime($student_data['birthday'])) : ''; ?>" 
                required class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block mb-1 text-sm">Age</label>
              <input type="number" id="edit_age" class="w-full border border-gray-300 px-3 py-2 rounded bg-gray-50" readonly>
            </div>
            <div>
              <label class="block mb-1 text-sm">Are you PWD?</label>
              <div class="flex items-center gap-4 mt-2">
                <label class="flex items-center gap-1 text-sm">
                  <input type="radio" name="edit_pwd" value="Yes" class="accent-green-500" 
                    <?php echo $student_data['pwd_status'] === 'Yes' ? 'checked' : ''; ?>> Yes
                </label>
                <label class="flex items-center gap-1 text-sm">
                  <input type="radio" name="edit_pwd" value="No" class="accent-green-500" 
                    <?php echo $student_data['pwd_status'] !== 'Yes' ? 'checked' : ''; ?>> No
                </label>
              </div>
            </div>
          </div>

          <!-- Address Information -->
          <div class="mt-6">
            <h4 class="text-md font-medium mb-3 text-gray-700">Address Information</h4>
            
            <!-- Province, City, Barangay -->
            <div class="grid md:grid-cols-3 gap-4 mb-4">
              <div>
                <label class="block mb-1 text-sm">Province <span class="text-red-500">*</span></label>
                <select id="edit_province" required
                  class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option value="">Select Province</option>
                </select>
              </div>
              <div>
                <label class="block mb-1 text-sm">City/Municipality <span class="text-red-500">*</span></label>
                <select id="edit_city" required disabled
                  class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option value="">Select City/Municipality</option>
                </select>
              </div>
              <div>
                <label class="block mb-1 text-sm">Barangay <span class="text-red-500">*</span></label>
                <select id="edit_barangay" required disabled
                  class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option value="">Select Barangay</option>
                </select>
              </div>
            </div>
            
            <!-- Zip Code, Subdivision -->
            <div class="grid md:grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block mb-1 text-sm">Zip Code <span class="text-red-500">*</span></label>
                <input type="text" id="edit_zip_code" value="<?php echo htmlspecialchars($student_data['zip_code'] ?? ''); ?>" 
                  required pattern="[0-9]{4}" maxlength="4"
                  class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g., 1234">
              </div>
              <div>
                <label class="block mb-1 text-sm">Subdivision/Village</label>
                <input type="text" id="edit_subdivision" value="<?php echo htmlspecialchars($student_data['subdivision'] ?? ''); ?>" 
                  maxlength="100"
                  class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Subdivision or village name (optional)">
              </div>
            </div>
            
            <!-- Street, House Number -->
            <div class="grid md:grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block mb-1 text-sm">Street <span class="text-red-500">*</span></label>
                <input type="text" id="edit_street" value="<?php echo htmlspecialchars($student_data['street'] ?? ''); ?>" 
                  required maxlength="200"
                  class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Street name">
              </div>
              <div>
                <label class="block mb-1 text-sm">House Number/Unit <span class="text-red-500">*</span></label>
                <input type="text" id="edit_house_number" value="<?php echo htmlspecialchars($student_data['house_number'] ?? ''); ?>" 
                  required maxlength="50"
                  class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="House number, unit, or building">
              </div>
            </div>
            
            <!-- Complete Address (read-only display) -->
            <div class="mb-4">
              <label class="block mb-1 text-sm">Complete Home Address</label>
              <textarea id="edit_home_address" rows="3" readonly
                class="w-full border border-gray-300 px-3 py-2 rounded bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Complete address will be auto-generated"><?php echo htmlspecialchars($student_data['address'] ?? ''); ?></textarea>
            </div>
          </div>

          <!-- Medical History -->
          <div class="mt-4">
            <label class="block mb-1 text-sm">Medical History</label>
            <textarea id="edit_medical_history" rows="2" maxlength="500"
              placeholder="Please list any allergies, conditions, or medications (optional)"
              class="w-full border border-gray-300 px-3 py-2 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($student_data['medical_history']); ?></textarea>
          </div>
        </div>

        <!-- Parent's Details Section -->
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-6 text-gray-800">Parent's Details</h2>

          <!-- Parent's Full Name -->
          <div class="mb-4">
            <label class="block mb-1 text-sm">Parent's Full Name <span class="text-red-500">*</span></label>
            <input type="text" id="edit_parent_guardian_name" value="<?php echo htmlspecialchars($student_data['parent_guardian_name']); ?>" 
              required maxlength="100" pattern="[a-zA-Z\s'-]{2,100}"
              class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter parent/guardian full name">
          </div>

          <!-- Facebook Name and Contact Number -->
          <div class="grid md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block mb-1 text-sm">Facebook Name</label>
              <input type="text" id="edit_facebook_name" value="<?php echo htmlspecialchars($student_data['facebook_name']); ?>" 
                maxlength="50" pattern="[a-zA-Z0-9\s._-]{2,50}"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Facebook name (optional)">
            </div>
            <div>
              <label class="block mb-1 text-sm">Contact Number <span class="text-red-500">*</span></label>
              <input type="tel" id="edit_phone" value="<?php echo htmlspecialchars($student_data['phone']); ?>" 
                required pattern="^(09|\+639|639)\d{9}$" maxlength="15"
                class="w-full border border-gray-300 px-3 py-2 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="e.g., 09123456789 or +639123456789">
            </div>
          </div>

          <!-- Account Details Section -->
          <h3 class="text-lg font-semibold mt-6 mb-3 text-gray-800">Account Details</h3>
          <div class="mb-4">
            <label class="block mb-1 text-sm">Email Address <span class="text-red-500">*</span></label>
            <input type="email" id="edit_email" value="<?php echo htmlspecialchars($student_data['email']); ?>" 
              readonly
              class="w-full border border-gray-300 px-3 py-2 rounded bg-gray-100 text-gray-600 cursor-not-allowed"
              placeholder="name@example.com">
            <p class="text-xs text-gray-500 mt-1">Email address cannot be changed. Contact administrator if you need to update your email.</p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
          <button type="button" onclick="closeEditModal()" class="px-6 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button type="button" onclick="saveProfile()" class="px-6 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors">
            Save Changes
          </button>
        </div>
      </form>
      </div>
    </div>
  </div>

  <!-- Email Verification Modal -->
  <div id="emailVerificationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="flex justify-between items-center p-6 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-900">Verify New Email Address</h2>
          <button type="button" onclick="closeEmailVerificationModal()" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        
        <div class="p-6">
          <div id="email-verification-content">
            <!-- Content will be populated by JavaScript -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Loading spinner utility
    function showLoadingSpinner(message = 'Loading...') {
      const spinner = document.createElement('div');
      spinner.id = 'loadingSpinner';
      spinner.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      spinner.innerHTML = `
        <div class="bg-white rounded-lg p-6 text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-tplearn-green mx-auto mb-4"></div>
          <p class="text-gray-600">${message}</p>
        </div>
      `;
      document.body.appendChild(spinner);
      return spinner;
    }

    function hideLoadingSpinner() {
      const spinner = document.getElementById('loadingSpinner');
      if (spinner) {
        spinner.remove();
      }
    }

    // Location dropdown functionality
    async function loadProvinces() {
      try {
        const response = await fetch('../../api/locations.php?action=provinces');
        const data = await response.json();
        
        if (data.success) {
          const provinceSelect = document.getElementById('edit_province');
          
          // Clear and populate province dropdown
          provinceSelect.innerHTML = '<option value="">Select Province</option>';
          data.data.forEach(province => {
            // Display province name in title case for better readability
            const displayName = province.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
            provinceSelect.innerHTML += `<option value="${province}">${displayName}</option>`;
          });
          
          console.log(`Loaded ${data.count} provinces from comprehensive PSA data`);
          return true;
        } else {
          console.error('Failed to load provinces:', data.error);
          return false;
        }
      } catch (error) {
        console.error('Error loading provinces:', error);
        return false;
      }
    }
    
    async function loadCities(province) {
      try {
        const response = await fetch(`../../api/locations.php?action=cities&province=${encodeURIComponent(province)}`);
        const data = await response.json();
        
        if (data.success) {
          const citySelect = document.getElementById('edit_city');
          const barangaySelect = document.getElementById('edit_barangay');
          
          // Clear and populate city dropdown
          citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
          data.data.forEach(city => {
            // Display city name in title case for better readability
            const displayName = city.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
            citySelect.innerHTML += `<option value="${city}">${displayName}</option>`;
          });
          citySelect.disabled = false;
          
          // Clear and disable barangay dropdown
          barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
          barangaySelect.disabled = true;
          
          console.log(`Loaded ${data.count} cities/municipalities for ${data.province}`);
          return true;
        } else {
          console.error('Failed to load cities:', data.error);
          return false;
        }
      } catch (error) {
        console.error('Error loading cities:', error);
        return false;
      }
    }
    
    async function loadBarangays(province, city) {
      try {
        const response = await fetch(`../../api/locations.php?action=barangays&province=${encodeURIComponent(province)}&city=${encodeURIComponent(city)}`);
        const data = await response.json();
        
        if (data.success) {
          const barangaySelect = document.getElementById('edit_barangay');
          
          // Clear and populate barangay dropdown
          barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
          data.data.forEach(barangay => {
            // Display barangay name in title case for better readability
            const displayName = barangay.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
            barangaySelect.innerHTML += `<option value="${barangay}">${displayName}</option>`;
          });
          barangaySelect.disabled = false;
          
          console.log(`Loaded ${data.count} barangays for ${data.city}, ${data.province}`);
          return true;
        } else {
          console.error('Failed to load barangays:', data.error);
          return false;
        }
      } catch (error) {
        console.error('Error loading barangays:', error);
        return false;
      }
    }

    // Function to set up location dropdowns with current values
    async function setupLocationDropdowns() {
      const currentProvince = "<?php echo htmlspecialchars($student_data['province'] ?? ''); ?>";
      const currentCity = "<?php echo htmlspecialchars($student_data['city'] ?? ''); ?>";
      const currentBarangay = "<?php echo htmlspecialchars($student_data['barangay'] ?? ''); ?>";
      
      // Load provinces first
      await loadProvinces();
      
      // Set current province if exists
      if (currentProvince) {
        const provinceSelect = document.getElementById('edit_province');
        provinceSelect.value = currentProvince.toUpperCase();
        
        // Load cities for current province
        await loadCities(currentProvince);
        
        if (currentCity) {
          const citySelect = document.getElementById('edit_city');
          citySelect.value = currentCity.toUpperCase();
          
          // Load barangays for current city
          await loadBarangays(currentProvince, currentCity);
          
          if (currentBarangay) {
            const barangaySelect = document.getElementById('edit_barangay');
            barangaySelect.value = currentBarangay.toUpperCase();
          }
        }
      }
      
      // Set up event listeners for cascading dropdowns
      document.getElementById('edit_province').addEventListener('change', async function() {
        const province = this.value;
        if (province) {
          await loadCities(province);
        } else {
          // Clear dependent dropdowns
          document.getElementById('edit_city').innerHTML = '<option value="">Select City/Municipality</option>';
          document.getElementById('edit_city').disabled = true;
          document.getElementById('edit_barangay').innerHTML = '<option value="">Select Barangay</option>';
          document.getElementById('edit_barangay').disabled = true;
        }
        updateCompleteAddress();
      });
      
      document.getElementById('edit_city').addEventListener('change', async function() {
        const city = this.value;
        const province = document.getElementById('edit_province').value;
        if (city && province) {
          await loadBarangays(province, city);
        } else {
          // Clear barangay dropdown
          document.getElementById('edit_barangay').innerHTML = '<option value="">Select Barangay</option>';
          document.getElementById('edit_barangay').disabled = true;
        }
        updateCompleteAddress();
      });
      
      document.getElementById('edit_barangay').addEventListener('change', function() {
        updateCompleteAddress();
      });
    }

    // Profile functions
    function editProfile() {
      document.getElementById('editModal').classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      
      // Initialize location dropdowns and other functionality after modal is shown
      setTimeout(async () => {
        updateAgeField();
        setupAddressAutoUpdate();
        await setupLocationDropdowns();
      }, 100);
    }
    
    // Auto-update complete address when address fields change
    function setupAddressAutoUpdate() {
      const addressFields = ['edit_house_number', 'edit_street', 'edit_subdivision', 'edit_barangay', 'edit_city', 'edit_province', 'edit_zip_code'];
      
      addressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
          // Listen for both input and change events to handle text inputs and select dropdowns
          field.addEventListener('input', updateCompleteAddress);
          field.addEventListener('change', updateCompleteAddress);
        }
      });
    }
    
    function generateCompleteAddress() {
      const houseNumber = document.getElementById('edit_house_number').value.trim();
      const street = document.getElementById('edit_street').value.trim();
      const subdivision = document.getElementById('edit_subdivision').value.trim();
      const barangay = document.getElementById('edit_barangay').value.trim();
      const city = document.getElementById('edit_city').value.trim();
      const province = document.getElementById('edit_province').value.trim();
      const zipCode = document.getElementById('edit_zip_code').value.trim();
      
      let address = '';
      
      if (houseNumber) address += houseNumber;
      if (street) address += (address ? ' ' : '') + street;
      if (subdivision) address += (address ? ', ' : '') + subdivision;
      if (barangay) address += (address ? ', ' : '') + 'Brgy. ' + barangay;
      if (city) address += (address ? ', ' : '') + city;
      if (province) address += (address ? ', ' : '') + province;
      if (zipCode) address += (address ? ' ' : '') + zipCode;
      
      return address;
    }
    
    function updateCompleteAddress() {
      const completeAddress = generateCompleteAddress();
      const addressField = document.getElementById('edit_home_address');
      if (addressField) {
        addressField.value = completeAddress;
      }
    }

    function closeEditModal() {
      document.getElementById('editModal').classList.add('hidden');
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
            const img = document.querySelector('.profile-avatar img');
            img.src = e.target.result;
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

        // Get all form values (excluding email since it's read-only)
        const pwdRadio = document.querySelector('input[name="edit_pwd"]:checked');
        
        const formData = {
          first_name: document.getElementById('edit_first_name').value.trim(),
          middle_name: document.getElementById('edit_middle_name').value.trim(),
          last_name: document.getElementById('edit_last_name').value.trim(),
          gender: document.getElementById('edit_gender').value.trim(),
          suffix: document.getElementById('edit_suffix').value.trim(),
          birthday: document.getElementById('edit_birthday').value,
          pwd_status: pwdRadio ? pwdRadio.value : 'No',
          medical_history: document.getElementById('edit_medical_history').value.trim(),
          parent_guardian_name: document.getElementById('edit_parent_guardian_name').value.trim(),
          facebook_name: document.getElementById('edit_facebook_name').value.trim(),
          phone: document.getElementById('edit_phone').value.trim(),
          // Address fields
          province: document.getElementById('edit_province').value.trim(),
          city: document.getElementById('edit_city').value.trim(),
          barangay: document.getElementById('edit_barangay').value.trim(),
          zip_code: document.getElementById('edit_zip_code').value.trim(),
          subdivision: document.getElementById('edit_subdivision').value.trim(),
          street: document.getElementById('edit_street').value.trim(),
          house_number: document.getElementById('edit_house_number').value.trim(),
          home_address: generateCompleteAddress()
        };

        // Client-side validation
        if (!formData.first_name || !formData.last_name) {
          throw new Error('First Name and Last Name are required.');
        }

        // Validate required address fields
        if (!formData.province || !formData.city || !formData.barangay || !formData.zip_code || !formData.street || !formData.house_number) {
          throw new Error('Province, City, Barangay, Zip Code, Street, and House Number are required.');
        }

        // Validate zip code format
        if (formData.zip_code && !/^[0-9]{4}$/.test(formData.zip_code)) {
          throw new Error('Zip code must be exactly 4 digits.');
        }

        // Validate Philippine mobile number format if provided
        if (formData.phone && formData.phone !== 'Not specified') {
          const phoneRegex = /^(09|\+639|639)\d{9}$/;
          if (!phoneRegex.test(formData.phone)) {
            throw new Error('Please enter a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).');
          }
        }

        // Send to API
        const response = await fetch('../../api/student-profile.php', {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (!response.ok) {
          throw new Error(result.error || 'Failed to update profile');
        }

        // Success - update the display and close modal
        await updateProfileDisplay(formData);
        closeEditModal();
        
        showSuccessMessage(result.message || 'Profile updated successfully!');

      } catch (error) {
        console.error('Error updating profile:', error);
        showErrorMessage(error.message || 'An error occurred while updating your profile. Please try again.');
      } finally {
        // Reset button state
        const saveButton = document.querySelector('button[onclick="saveProfile()"]');
        if (saveButton) {
          saveButton.textContent = 'Save Changes';
          saveButton.disabled = false;
        }
      }
    }

    async function updateProfileDisplay(data) {
      // Instead of manually updating elements, refresh the page to get updated data from server
      // This ensures all data is consistent and properly formatted
      setTimeout(() => {
        window.location.reload();
      }, 1000); // Small delay to show success message first
      
      return; // Skip the manual update below
      
      // Legacy manual update code (keeping for reference but not used)
      // Update profile header
      const fullName = `${data.first_name} ${data.middle_name} ${data.last_name}`;
      document.querySelector('.profile-header h2').textContent = fullName.trim();

      // Calculate age from birthday
      let age = 0;
      let ageDisplay = 'Not specified';
      if (data.birthday) {
        const birthDate = new Date(data.birthday);
        const today = new Date();
        age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
          age--;
        }
        ageDisplay = `${age} years old`;
      }

      // Update personal information display
      const personalInfo = document.querySelectorAll('.info-card')[0].querySelectorAll('.info-value');
      personalInfo[0].textContent = data.first_name;
      personalInfo[1].textContent = data.middle_name;
      personalInfo[2].textContent = data.last_name;
      personalInfo[3].textContent = formatDate(data.birthday);
      personalInfo[4].textContent = `${age} years old`;
      personalInfo[5].textContent = data.pwd_status;
      personalInfo[6].textContent = data.medical_history || 'N/A';

      // Update parent/guardian information display
      const guardianInfo = document.querySelectorAll('.info-card')[1].querySelectorAll('.info-value');
      guardianInfo[0].textContent = data.parent_guardian_name;
      guardianInfo[1].textContent = data.facebook_name;
      guardianInfo[2].textContent = data.phone;
      guardianInfo[3].textContent = data.email;
      guardianInfo[4].textContent = data.home_address;

      // Update age in profile header (removing gender references)
      const ageSpan = document.querySelector('.profile-header .text-white\\/90 span:first-child');
      if (ageSpan) {
        ageSpan.innerHTML = ageSpan.innerHTML.replace(/\d+ years old/, `${age} years old`);
      }
    }

    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }

    function showSuccessMessage(message) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 transform transition-all duration-300 scale-95">
          <div class="text-center">
            <svg class="w-16 h-16 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Success!</h3>
            <p class="text-gray-600 mb-6">${message}</p>
            <button onclick="this.closest('.fixed').remove()" class="w-full px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-colors">
              OK
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      
      // Animate in
      setTimeout(() => {
        const modalContent = modal.querySelector('.bg-white');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
      }, 10);
      
      // Auto close after 3 seconds
      setTimeout(() => {
        if (modal.parentNode) {
          modal.remove();
        }
      }, 3000);
    }

    function showErrorMessage(message) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 transform transition-all duration-300 scale-95">
          <div class="text-center">
            <svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 19c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Error</h3>
            <p class="text-gray-600 mb-6">${message}</p>
            <button onclick="this.closest('.fixed').remove()" class="w-full px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-700 transition-colors">
              OK
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      
      // Animate in
      setTimeout(() => {
        const modalContent = modal.querySelector('.bg-white');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
      }, 10);
    }

    // Notification functions
    function openNotifications() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Notifications</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-blue-50 rounded-lg">
              <p class="text-sm text-blue-800">Profile update request submitted</p>
              <p class="text-xs text-blue-600 mt-1">2 hours ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    function openMessages() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Messages</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Welcome to TPLearn! Please complete your profile.</p>
              <p class="text-xs text-gray-600 mt-1">1 day ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Your enrollment application is under review.</p>
              <p class="text-xs text-gray-600 mt-1">2 days ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">New semester starts next month. Prepare your documents.</p>
              <p class="text-xs text-gray-600 mt-1">3 days ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    // Age calculation functionality
    function calculateAge(birthDate) {
      const today = new Date();
      const birth = new Date(birthDate);
      let age = today.getFullYear() - birth.getFullYear();
      const monthDiff = today.getMonth() - birth.getMonth();
      
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
      }
      
      return age;
    }

    // Update age when birthday changes
    function updateAgeField() {
      const birthdayField = document.getElementById('edit_birthday');
      const ageField = document.getElementById('edit_age');
      
      if (birthdayField && ageField) {
        birthdayField.addEventListener('change', function() {
          if (this.value) {
            const age = calculateAge(this.value);
            ageField.value = age;
          } else {
            ageField.value = '';
          }
        });
        
        // Set initial age if birthday is already set
        if (birthdayField.value) {
          const age = calculateAge(birthdayField.value);
          ageField.value = age;
        }
      }
    }

    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize age calculation
      updateAgeField();
      
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const mobileCloseButton = document.getElementById('mobile-close-button');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('mobile-menu-overlay');

      function openMobileMenu() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
      }

      function closeMobileMenu() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
      }

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
              setTimeout(closeMobileMenu, 100); // Small delay for better UX
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

    // Email verification modal functions
    function showEmailVerificationModal(newEmail) {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.id = 'email-verification-modal';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 transform transition-all duration-300 scale-95">
          <div class="text-center">
            <svg class="w-16 h-16 text-blue-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Email Verification Required</h3>
            <p class="text-gray-600 mb-4">We've sent a verification code to:</p>
            <p class="text-blue-600 font-semibold mb-6">${newEmail}</p>
            
            <div class="mb-4">
              <label for="verification-code" class="block text-sm font-medium text-gray-700 mb-2">Enter Verification Code</label>
              <input type="text" id="verification-code" class="w-full p-3 border border-gray-300 rounded-lg text-center text-lg tracking-widest" placeholder="000000" maxlength="6">
            </div>
            
            <div class="flex space-x-3">
              <button onclick="verifyEmailChange()" class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Verify
              </button>
              <button onclick="cancelEmailChange()" class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-700 transition-colors">
                Cancel
              </button>
            </div>
            
            <div class="mt-4">
              <button onclick="resendVerificationCode()" class="text-blue-500 hover:text-blue-700 text-sm">
                Resend Code
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      
      // Animate in
      setTimeout(() => {
        const modalContent = modal.querySelector('.bg-white');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
        // Focus on verification code input
        document.getElementById('verification-code').focus();
      }, 10);
      
      // Auto-format verification code input
      const codeInput = document.getElementById('verification-code');
      codeInput.addEventListener('input', function(e) {
        // Only allow numbers
        this.value = this.value.replace(/[^0-9]/g, '');
        
        // Auto-submit if 6 digits entered
        if (this.value.length === 6) {
          setTimeout(() => verifyEmailChange(), 100);
        }
      });
      
      // Handle Enter key
      codeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          verifyEmailChange();
        }
      });
    }

    function verifyEmailChange() {
      const codeInput = document.getElementById('verification-code');
      const code = codeInput.value.trim();
      
      if (code.length !== 6) {
        showErrorMessage('Please enter a 6-digit verification code.');
        return;
      }
      
      // Show loading state
      const verifyBtn = document.querySelector('#email-verification-modal button');
      const originalText = verifyBtn.textContent;
      verifyBtn.textContent = 'Verifying...';
      verifyBtn.disabled = true;
      
      fetch('../api/email-change.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'verify_change',
          verification_code: code
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          closeEmailVerificationModal();
          showSuccessMessage('Email address updated successfully!');
          
          // Update the email field in the form
          document.getElementById('edit_email').value = data.new_email;
          
          // Update the profile display if needed
          location.reload();
        } else {
          showErrorMessage(data.message || 'Verification failed. Please try again.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred. Please try again.');
      })
      .finally(() => {
        verifyBtn.textContent = originalText;
        verifyBtn.disabled = false;
      });
    }

    function cancelEmailChange() {
      if (confirm('Are you sure you want to cancel the email change?')) {
        fetch('../api/email-change.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action: 'cancel_change'
          })
        })
        .then(response => response.json())
        .then(data => {
          closeEmailVerificationModal();
          if (data.success) {
            showSuccessMessage('Email change cancelled.');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          closeEmailVerificationModal();
        });
      }
    }

    function resendVerificationCode() {
      fetch('../api/email-change.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'resend_code'
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showSuccessMessage('Verification code resent to your email.');
        } else {
          showErrorMessage(data.message || 'Failed to resend code. Please try again.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showErrorMessage('An error occurred. Please try again.');
      });
    }

    function closeEmailVerificationModal() {
      const modal = document.getElementById('email-verification-modal');
      if (modal) {
        modal.remove();
      }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
      if (event.target.classList.contains('fixed') && event.target.classList.contains('bg-black')) {
        event.target.remove();
      }
    });
  </script>
</body>

</html>
