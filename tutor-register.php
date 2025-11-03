<?php
// Show errors during development (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/includes/db.php'; // provides $conn (mysqli)
require_once __DIR__ . '/includes/data-helpers.php'; // provides duplicate checking functions
require_once __DIR__ . '/includes/email-verification.php'; // provides email verification functions
require_once __DIR__ . '/assets/icons.php'; // provides standardized icon functions

/* ---------- Helpers ---------- */
function csrf_token()
{
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check()
{
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
      http_response_code(400);
      exit('Invalid CSRF token');
    }
  }
}
function generateTutorNumber()
{
  $year = date("Y");
  $rand = str_pad((string)rand(1, 999), 3, "0", STR_PAD_LEFT);
  return "TPT{$year}-{$rand}";
}

/* ---------- Page state ---------- */
$tutorNumber = generateTutorNumber();
$showModal = false;
$errors = [];
$old = [
  'first_name'           => '',
  'last_name'            => '',
  'middle_name'          => '',
  'gender'               => '',
  'suffix'               => '',
  'birthday'             => '',
  'contact_number'       => '',
  'subjects'             => '',
  'qualifications'       => '',
  'email'                => '',
];

/* ---------- Handle POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // collect inputs
  $old['first_name']           = trim($_POST['first_name'] ?? '');
  $old['last_name']            = trim($_POST['last_name'] ?? '');
  $old['middle_name']          = trim($_POST['middle_name'] ?? '');
  $old['gender']               = trim($_POST['gender'] ?? '');
  $old['suffix']               = trim($_POST['suffix'] ?? '');
  $old['birthday']             = trim($_POST['birthday'] ?? '');
  $old['contact_number']       = trim($_POST['contact_number'] ?? '');
  $old['subjects']             = trim($_POST['subjects'] ?? '');
  $old['qualifications']       = trim($_POST['qualifications'] ?? '');
  $old['email']                = trim($_POST['email'] ?? '');

  $password              = $_POST['password'] ?? '';
  $confirm               = $_POST['confirm_password'] ?? '';

  // comprehensive validation

  // Name validation - letters, spaces, apostrophes, hyphens only
  if ($old['first_name'] === '') {
    $errors['first_name'] = 'First name is required.';
  } elseif (!preg_match("/^[a-zA-Z\s\-']{1,50}$/", $old['first_name'])) {
    $errors['first_name'] = 'First name can only contain letters, spaces, hyphens, and apostrophes.';
  }

  if ($old['last_name'] === '') {
    $errors['last_name'] = 'Last name is required.';
  } elseif (!preg_match("/^[a-zA-Z\s\-']{1,50}$/", $old['last_name'])) {
    $errors['last_name'] = 'Last name can only contain letters, spaces, hyphens, and apostrophes.';
  }

  if ($old['middle_name'] && !preg_match("/^[a-zA-Z\s\-']{1,50}$/", $old['middle_name'])) {
    $errors['middle_name'] = 'Middle name can only contain letters, spaces, hyphens, and apostrophes.';
  }

  // Subject validation
  if ($old['subjects'] === '') {
    $errors['subjects'] = 'At least one subject is required.';
  }

  // Email validation
  if ($old['email'] === '') {
    $errors['email'] = 'Email is required.';
  } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
  }

  // Password validation
  if ($password === '') {
    $errors['password'] = 'Password is required.';
  } elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
  } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/', $password)) {
    $errors['password'] = 'Password must contain uppercase, lowercase, number, and special character.';
  }

  if ($confirm === '') {
    $errors['confirm'] = 'Please confirm your password.';
  } elseif ($password !== $confirm) {
    $errors['confirm'] = 'Passwords do not match.';
  }

  // If no errors, proceed with registration
  if (empty($errors)) {
    // Generate tutor number
    $tutorNumber = generateTutorNumber();
    
    // Prepare user data for creation
    $userData = [
      'username' => $tutorNumber,
      'email' => $old['email'],
      'password' => $password,
      'role' => 'tutor',
      'first_name' => $old['first_name'],
      'last_name' => $old['last_name'],
      'middle_name' => $old['middle_name'],
      'suffix' => $old['suffix'],
      'gender' => $old['gender'],
      'birthday' => $old['birthday'],
      'contact_number' => $old['contact_number']
    ];
    
    // Create user with comprehensive duplicate checking
    $result = createUserWithDuplicateCheck($userData);
    
    if (!$result['success']) {
      // Handle duplicate or creation errors
      if (isset($result['duplicates'])) {
        // Parse specific duplicate types for better error messages
        foreach ($result['duplicates'] as $duplicate) {
          if (strpos($duplicate, 'Email') !== false) {
            $errors['email'] = 'This email is already registered.';
          } elseif (strpos($duplicate, 'Full name') !== false) {
            $errors['name'] = 'A tutor with this full name already exists.';
          } elseif (strpos($duplicate, 'Username') !== false) {
            $errors['username'] = 'This tutor ID is already taken.';
          }
        }
      } else {
        $errors['save'] = $result['message'];
      }
    } else {
      // User created successfully, now create tutor profile and send verification email
      try {
        $userId = $result['internal_id']; // Use internal database ID for foreign key
        
        // Create tutor profile
        $tp = $conn->prepare("
          INSERT INTO tutor_profiles (user_id, first_name, last_name, middle_name, gender, birthday, suffix, contact_number, specializations, bachelor_degree, bio)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $bio = ""; // Default empty bio
        $birthday = !empty($old['birthday']) ? $old['birthday'] : null;
        $tp->bind_param("issssssssss", $userId, $old['first_name'], $old['last_name'], $old['middle_name'], $old['gender'], $birthday, $old['suffix'], $old['contact_number'], $old['subjects'], $old['qualifications'], $bio);
        $tp->execute();
        $tp->close();
        
        // Generate verification token and send email
        $token = generateVerificationToken();
        $firstName = $old['first_name'];
        
        if (createEmailVerification($userId, $old['email'], $token)) {
          $emailResult = sendVerificationEmail($old['email'], $firstName, $token);
          
          if ($emailResult['success']) {
            $showModal = true;
            $_SESSION['registration_email'] = $old['email']; // Store for resend functionality
            
            // If in simulation mode, store the verification URL for display
            if (isset($emailResult['simulation']) && $emailResult['simulation']) {
              $_SESSION['show_verification_link'] = true;
            }
          } else {
            $errors['email'] = 'Account created but failed to send verification email. Please contact support.';
          }
        } else {
          $errors['save'] = 'Account created but failed to create verification record. Please contact support.';
        }
        
      } catch (mysqli_sql_exception $e) {
        $errors['save'] = 'Registration failed during tutor profile creation. Please try again.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, maximum-scale=5" />
  <title>Tutor Registration - TPLearn</title>
  <link rel="stylesheet" href="assets/standard-ui.css">
  <link rel="stylesheet" href="assets/tailwind.min.css">
  <style>
    /* TPLearn Custom Colors - Ensure these work even if Tailwind config fails */
    :root {
      --tplearn-green: #10b981;
      --tplearn-light-green: #34d399;
      --tplearn-green-50: #ecfdf5;
      --tplearn-green-100: #d1fae5;
      --tplearn-green-600: #059669;
      --tplearn-green-700: #047857;
    }

    /* Fallback for TPLearn colors - More comprehensive */
    .border-tplearn-green { border-color: var(--tplearn-green) !important; }
    .text-tplearn-green { color: var(--tplearn-green) !important; }
    .bg-tplearn-green { background-color: var(--tplearn-green) !important; }
    .bg-tplearn-light-green { background-color: var(--tplearn-light-green) !important; }
    .bg-tplearn-green-100 { background-color: var(--tplearn-green-100) !important; }
    .focus\:ring-tplearn-green:focus { 
      --tw-ring-opacity: 1 !important;
      --tw-ring-color: var(--tplearn-green) !important;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
    }
    .focus\:border-tplearn-green:focus { border-color: var(--tplearn-green) !important; }
    .hover\:text-tplearn-green:hover { color: var(--tplearn-green) !important; }
    .hover\:text-tplearn-green-600:hover { color: var(--tplearn-green-600) !important; }
    .accent-tplearn-green { accent-color: var(--tplearn-green) !important; }
    
    /* Gradient backgrounds with better fallbacks */
    .bg-gradient-to-r.from-tplearn-green.to-tplearn-light-green {
      background: var(--tplearn-green) !important;
      background: linear-gradient(to right, var(--tplearn-green), var(--tplearn-light-green)) !important;
    }
    .hover\:from-tplearn-green-600.hover\:to-tplearn-green:hover {
      background: linear-gradient(to right, var(--tplearn-green-600), var(--tplearn-green)) !important;
    }
    
    /* Ensure form styling works */
    .tplearn-input-style {
      width: 100% !important;
      border: 1px solid #d1d5db !important;
      padding: 0.75rem 1rem !important;
      border-radius: 0.5rem !important;
      transition: all 0.2s !important;
    }
    .tplearn-input-style:focus {
      outline: none !important;
      border-color: var(--tplearn-green) !important;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
    }

    /* Enhanced responsive styles */
    @media (max-width: 640px) {
      .mobile-padding {
        padding: 1rem;
      }

      .mobile-text {
        font-size: 0.875rem;
      }

      .mobile-input {
        padding: 0.75rem;
      }

      .mobile-button {
        padding: 1rem;
        font-size: 1rem;
      }
    }

    @media (min-width: 641px) and (max-width: 1024px) {
      .tablet-layout {
        max-width: 95%;
      }

      .tablet-padding {
        padding: 1.5rem;
      }
    }

    @media (min-width: 1025px) {
      .desktop-layout {
        max-width: 1200px;
      }

      .desktop-padding {
        padding: 2rem;
      }
    }

    /* Touch-friendly elements */
    .touch-target {
      min-height: 44px;
      min-width: 44px;
    }

    /* Improved focus states for accessibility */
    .focus-enhanced:focus {
      outline: 2px solid #10b981;
      outline-offset: 2px;
    }

    /* Responsive typography */
    .responsive-title {
      font-size: clamp(1.5rem, 4vw, 2rem);
    }

    .responsive-subtitle {
      font-size: clamp(1.125rem, 3vw, 1.25rem);
    }

    /* Loading states */
    .loading-skeleton {
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200% 100%;
      animation: loading 1.5s infinite;
    }

    @keyframes loading {
      0% {
        background-position: 200% 0;
      }

      100% {
        background-position: -200% 0;
      }
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Header -->
  <header class="bg-white shadow-lg border-b-4 border-tplearn-green">
    <div class="container mx-auto px-4 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <img src="assets/logonew.png" alt="TPLearn" class="h-12 w-auto">
          <div>
            <h1 class="text-2xl font-bold text-gray-800">Tutor Registration</h1>
            <p class="text-sm text-gray-600">Join TPLearn as a tutor</p>
          </div>
        </div>
        <nav class="hidden md:flex items-center space-x-6">
          <a href="login.php" class="text-gray-600 hover:text-tplearn-green transition-colors duration-200">Login</a>
        </nav>
      </div>
    </div>
  </header>

  <!-- Mobile-first responsive container -->
  <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
    <div class="w-full max-w-sm sm:max-w-md md:max-w-2xl lg:max-w-4xl xl:max-w-5xl mx-auto space-y-8"
      style="margin-top: 2rem; margin-bottom: 2rem;">

      <!-- Errors -->
      <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 text-red-700 rounded-lg p-4 text-sm shadow-sm">
          <div class="flex items-center mb-2">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <h3 class="font-semibold">Please correct the following errors:</h3>
          </div>
          <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $msg): ?>
              <li><?= htmlspecialchars($msg) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- TUTOR DETAILS -->
      <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-tplearn-green to-tplearn-light-green px-8 py-6">
          <h2 class="text-2xl font-bold text-white flex items-center">
            <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
            </svg>
            Tutor Information
          </h2>
          <p class="text-green-100 text-sm mt-1">Share your expertise and join our teaching community</p>
        </div>

        <form id="registrationForm" method="POST" class="p-8" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

          <!-- Name Fields Row -->
          <div class="flex flex-row gap-3">
            <div class="flex-1">
              <label class="block mb-2 text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
              <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($old['first_name']) ?>"
                required maxlength="50" pattern="[a-zA-Z\s\-']{1,50}"
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['first_name']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="Enter first name">
              <?php if (isset($errors['first_name'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['first_name']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
            <div class="flex-1">
              <label class="block mb-2 text-sm font-medium text-gray-700">Middle Name</label>
              <input type="text" name="middle_name" id="middle_name" value="<?= htmlspecialchars($old['middle_name']) ?>"
                maxlength="50" pattern="[a-zA-Z\s\-']{1,50}"
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['middle_name']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="Middle name (optional)">
              <?php if (isset($errors['middle_name'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['middle_name']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
            <div class="flex-1">
              <label class="block mb-2 text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
              <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($old['last_name']) ?>"
                required maxlength="50" pattern="[a-zA-Z\s\-']{1,50}"
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['last_name']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="Enter last name">
              <?php if (isset($errors['last_name'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['last_name']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
            <div class="w-24">
              <label class="block mb-2 text-sm font-medium text-gray-700">Suffix</label>
              <input type="text" name="suffix" id="suffix" value="<?= htmlspecialchars($old['suffix'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                maxlength="10" pattern="[a-zA-Z\s.]{1,10}"
                class="w-full px-2 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['suffix']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="Jr., Sr.">
              <?php if (isset($errors['suffix'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['suffix']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
          </div>

          <!-- Gender, Birthday, Contact Row -->
          <div class="flex flex-row gap-4 mt-4">
            <div class="flex-1">
              <label class="block mb-2 text-sm font-medium text-gray-700">Gender</label>
              <select name="gender" id="gender" 
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['gender']) ? 'border-red-500 bg-red-50' : '' ?>">
                <option value="">Select Gender</option>
                <option value="Male" <?= ($old['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($old['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= ($old['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
              <?php if (isset($errors['gender'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['gender']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
            <div class="flex-1">
              <label class="block mb-2 text-sm font-medium text-gray-700">Birthday</label>
              <input type="date" name="birthday" id="birthday" value="<?= htmlspecialchars($old['birthday']) ?>"
                min="<?= date('Y-m-d', strtotime('-100 years')) ?>" max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['birthday']) ? 'border-red-500 bg-red-50' : '' ?>">
              <?php if (isset($errors['birthday'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['birthday']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
            <div class="flex-1">
              <label class="block mb-2 text-sm font-medium text-gray-700">Contact Number</label>
              <input type="tel" name="contact_number" id="contact_number" value="<?= htmlspecialchars($old['contact_number']) ?>"
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['contact_number']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="e.g., 09123456789">
              <?php if (isset($errors['contact_number'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['contact_number']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
          </div>

          <!-- Tutor-specific fields -->
          <div class="mt-4">
            <h4 class="text-md font-medium mb-3 text-gray-700">Teaching Information</h4>
            
            <div>
              <label class="block mb-2 text-sm font-medium text-gray-700">Subjects (comma-separated) <span class="text-red-500">*</span></label>
              <input type="text" name="subjects" id="subjects" value="<?= htmlspecialchars($old['subjects']) ?>" required
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['subjects']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="e.g., Math, Physics, Chemistry">
              <?php if (isset($errors['subjects'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['subjects']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>

            <div class="mt-4">
              <label class="block mb-2 text-sm font-medium text-gray-700">Bachelor's Degree</label>
              <textarea name="qualifications" id="qualifications" rows="3" value="<?= htmlspecialchars($old['qualifications']) ?>"
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['qualifications']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="Enter your bachelor's degree (e.g., Bachelor of Science in Mathematics, Bachelor of Arts in English)"><?= htmlspecialchars($old['qualifications']) ?></textarea>
              <?php if (isset($errors['qualifications'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['qualifications']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
          </div>

          <!-- Account Details -->
          <div class="mt-4">
            <h4 class="text-md font-medium mb-3 text-gray-700">Account Information</h4>
            
            <div>
              <label class="block mb-2 text-sm font-medium text-gray-700">Email Address <span class="text-red-500">*</span></label>
              <input type="email" name="email" id="email" value="<?= htmlspecialchars($old['email']) ?>" required
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['email']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="name@example.com">
              <?php if (isset($errors['email'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['email']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>

            <div class="grid md:grid-cols-2 gap-4 mt-4">
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input type="password" name="password" id="password" required
                    class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['password']) ? 'border-red-500 bg-red-50' : '' ?>"
                    placeholder="Minimum 8 characters">
                  <button type="button" id="togglePassword" class="absolute right-2 top-2 text-gray-500 hover:text-gray-700">
                    <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                  </button>
                </div>
                <?php if (isset($errors['password'])): ?>
                  <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['password']) ?></p>
                <?php endif; ?>
                <div class="error-message text-red-500 text-xs mt-1 hidden"></div>

                <!-- Simple Password Requirements -->
                <div class="mt-2 text-xs space-y-1">
                  <div id="req-length" class="flex items-center text-gray-500">
                    <span class="mr-2 w-3">✗</span> At least 8 characters
                  </div>
                  <div id="req-lowercase" class="flex items-center text-gray-500">
                    <span class="mr-2 w-3">✗</span> One lowercase letter
                  </div>
                  <div id="req-uppercase" class="flex items-center text-gray-500">
                    <span class="mr-2 w-3">✗</span> One uppercase letter
                  </div>
                  <div id="req-number" class="flex items-center text-gray-500">
                    <span class="mr-2 w-3">✗</span> One number
                  </div>
                  <div id="req-symbol" class="flex items-center text-gray-500">
                    <span class="mr-2 w-3">✗</span> One special character (!@#$%^&*)
                  </div>
                </div>
              </div>
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input type="password" name="confirm_password" id="confirm_password" required
                    class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['confirm']) ? 'border-red-500 bg-red-50' : '' ?>"
                    placeholder="Re-enter password">
                  <button type="button" id="toggleConfirmPassword" class="absolute right-2 top-2 text-gray-500 hover:text-gray-700">
                    <svg id="eyeIconConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                  </button>
                </div>
                <?php if (isset($errors['confirm'])): ?>
                  <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['confirm']) ?></p>
                <?php endif; ?>
                <div class="error-message text-red-500 text-xs mt-1 hidden"></div>

                <!-- Password match indicator -->
                <div id="password-match" class="mt-2 text-xs hidden">
                  <div class="flex items-center">
                    <span id="match-icon" class="mr-2"></span>
                    <span id="match-text"></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-6">
              <button type="submit" id="submitBtn" class="w-full bg-gradient-to-r from-tplearn-green to-tplearn-light-green text-white py-3 rounded-lg hover:from-tplearn-green-600 hover:to-tplearn-green transition-all duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed font-semibold">
                <span id="submitText">Submit Registration</span>
                <span id="submitLoader" class="hidden">
                  <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Processing...
                </span>
              </button>
              <p class="text-center text-sm text-gray-600 mt-2">
                Already have an account?
                <a href="login.php" class="text-tplearn-green hover:text-tplearn-green-600 hover:underline font-medium">Log in</a>
              </p>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- SUCCESS MODAL -->
    <?php if ($showModal): ?>
      <div class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full text-center">
          <div class="text-blue-600 text-4xl mb-4">📧</div>
          <h2 class="text-xl font-bold mb-2">Registration Successful!</h2>
          <p class="mb-1">Your Tutor Number is</p>
          <p class="text-green-600 font-semibold text-lg mb-4"><?= htmlspecialchars($tutorNumber) ?></p>
          
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-blue-800 mb-2">📧 Email Verification Required</h3>
            <p class="text-blue-700 text-sm mb-3">
              We've sent a verification email to:<br>
              <strong><?= htmlspecialchars($old['email']) ?></strong>
            </p>
            <p class="text-blue-600 text-sm">
              Please check your email and click the verification link to activate your account.
            </p>
          </div>
          
          <p class="text-sm text-gray-500 mb-6">
            Use your Tutor Number <strong><?= htmlspecialchars($tutorNumber) ?></strong> as your username for login after verification.
          </p>
          
          <div class="space-y-3">
            <a href="login.php" class="w-full inline-block bg-green-500 text-white py-2 px-6 rounded hover:bg-green-600 transition">
              Go to Login
            </a>
            <a href="resend-verification.php" class="w-full inline-block bg-blue-500 text-white py-2 px-6 rounded hover:bg-blue-600 transition">
              Resend Verification Email
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>

  <script>
    // Test if JavaScript is running
    console.log('JavaScript is loading...');
    
    // Enhanced form validation and user experience
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOMContentLoaded fired');
      const form = document.getElementById('registrationForm');
      const submitBtn = document.getElementById('submitBtn');
      const submitText = document.getElementById('submitText');
      const submitLoader = document.getElementById('submitLoader');

      // Password visibility toggles
      const password = document.getElementById('password');
      const confirmPassword = document.getElementById('confirm_password');
      const togglePassword = document.getElementById('togglePassword');
      const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
      const eyeIcon = document.getElementById('eyeIcon');
      const eyeIconConfirm = document.getElementById('eyeIconConfirm');

      [togglePassword, toggleConfirmPassword].forEach(btn => {
        if (!btn) return;
        btn.addEventListener('click', function() {
          const input = btn === togglePassword ? password : confirmPassword;
          if (!input) return;
          input.type = input.type === 'password' ? 'text' : 'password';
        });
      });

      // Form utility functions
      function showError(input, errorContainer, message) {
        input.classList.add('border-red-500', 'bg-red-50');
        input.classList.remove('border-green-500', 'bg-green-50');
        errorContainer.textContent = message;
        errorContainer.classList.remove('hidden');
      }

      function showSuccess(input, errorContainer) {
        input.classList.add('border-green-500', 'bg-green-50');
        input.classList.remove('border-red-500', 'bg-red-50');
        errorContainer.textContent = '';
        errorContainer.classList.add('hidden');
      }

      function clearErrors(input, errorContainer) {
        input.classList.remove('border-red-500', 'bg-red-50', 'border-green-500', 'bg-green-50');
        errorContainer.textContent = '';
        errorContainer.classList.add('hidden');
      }

      // Helper to update requirement indicators
      function updateRequirement(id, met) {
        const el = document.getElementById(id);
        if (!el) return;
        const span = el.querySelector('span');
        if (!span) return;
        if (met) {
          span.textContent = '✓';
          el.className = 'flex items-center text-green-600';
        } else {
          span.textContent = '✗';
          el.className = 'flex items-center text-gray-500';
        }
      }

      // Validation functions
      function validateName(input, errorContainer, fieldName) {
        const value = input.value.trim();
        
        if (!value) {
          showError(input, errorContainer, `${fieldName} is required.`);
          return false;
        }
        if (!/^[a-zA-Z\s\-']{1,50}$/.test(value)) {
          showError(input, errorContainer, `${fieldName} can only contain letters, spaces, hyphens, and apostrophes.`);
          return false;
        }
        
        showSuccess(input, errorContainer);
        return true;
      }

      function validateEmail(input, errorContainer) {
        const value = input.value.trim();
        
        if (!value) {
          showError(input, errorContainer, 'Email is required.');
          return false;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          showError(input, errorContainer, 'Please enter a valid email address.');
          return false;
        }
        
        showSuccess(input, errorContainer);
        return true;
      }

      function validateSubjects(input, errorContainer) {
        const value = input.value.trim();
        
        if (!value) {
          showError(input, errorContainer, 'At least one subject is required.');
          return false;
        }
        
        showSuccess(input, errorContainer);
        return true;
      }

      function validatePassword(input, errorContainer, showErrors = true) {
        const value = input.value;
        
        const requirements = {
          length: value.length >= 8,
          lowercase: /[a-z]/.test(value),
          uppercase: /[A-Z]/.test(value),
          number: /\d/.test(value),
          symbol: /[!@#$%^&*(),.?":{}|<>]/.test(value)
        };

        // Always update requirement indicators dynamically
        updateRequirement('req-length', requirements.length);
        updateRequirement('req-lowercase', requirements.lowercase);
        updateRequirement('req-uppercase', requirements.uppercase);
        updateRequirement('req-number', requirements.number);
        updateRequirement('req-symbol', requirements.symbol);

        const allMet = Object.values(requirements).every(req => req);

        // Only show errors if explicitly requested (on blur or form submit)
        if (!value && showErrors) {
          showError(input, errorContainer, 'Password is required.');
          return false;
        }
        if (!allMet && showErrors && value) {
          showError(input, errorContainer, 'Password must meet all requirements.');
          return false;
        }

        // Clear errors if all requirements are met
        if (allMet && value) {
          showSuccess(input, errorContainer);
        } else if (!showErrors) {
          // Clear any existing errors during typing
          input.classList.remove('border-red-500', 'bg-red-50');
          errorContainer.classList.add('hidden');
        }

        validatePasswordMatch(); // Always check match when password changes
        return allMet && value.length > 0;
      }

      function validatePasswordMatch() {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const matchIndicator = document.getElementById('password-match');
        const matchIcon = document.getElementById('match-icon');
        const matchText = document.getElementById('match-text');

        if (!confirm || !matchIndicator) {
          if (matchIndicator) matchIndicator.classList.add('hidden');
          return true;
        }

        matchIndicator.classList.remove('hidden');

        if (password === confirm) {
          matchIcon.textContent = '✓';
          matchIcon.className = 'mr-2 text-green-500';
          matchText.textContent = 'Passwords match';
          matchText.className = 'text-green-600';
          return true;
        } else {
          matchIcon.textContent = '✗';
          matchIcon.className = 'mr-2 text-red-500';
          matchText.textContent = 'Passwords do not match';
          matchText.className = 'text-red-600';
          return false;
        }
      }

      // Validation rules for each field
      const validationRules = [
        { id: 'first_name', validator: (input, errorContainer) => validateName(input, errorContainer, 'First name') },
        { id: 'last_name', validator: (input, errorContainer) => validateName(input, errorContainer, 'Last name') },
        { id: 'middle_name', validator: (input, errorContainer) => input.value.trim() === '' || validateName(input, errorContainer, 'Middle name') },
        { id: 'subjects', validator: validateSubjects },
        { id: 'email', validator: validateEmail },
        { id: 'password', validator: validatePassword },
      ];

      // Add event listeners for real-time validation
      validationRules.forEach(rule => {
        const input = document.getElementById(rule.id);
        const errorContainer = input ? input.parentElement.querySelector('.error-message') : null;

        if (input && errorContainer) {
          // Validate on blur (when user leaves field)
          input.addEventListener('blur', () => rule.validator(input, errorContainer));
          
          // Clear errors on focus (when user starts typing)
          input.addEventListener('focus', () => {
            if (rule.id !== 'password') { // Don't clear password indicators on focus
              clearErrors(input, errorContainer);
            }
          });
        }
      });

      // Special handling for password fields
      if (password) {
        // Dynamic password validation (without showing errors during typing)
        password.addEventListener('input', function() {
          const errorContainer = this.parentElement.parentElement.querySelector('.error-message');
          validatePassword(this, errorContainer, false);
        });
        
        // Also listen for keyup for more responsive updates
        password.addEventListener('keyup', function() {
          const errorContainer = this.parentElement.parentElement.querySelector('.error-message');
          validatePassword(this, errorContainer, false);
        });

        // Show errors on blur
        password.addEventListener('blur', function() {
          const errorContainer = this.parentElement.parentElement.querySelector('.error-message');
          validatePassword(this, errorContainer, true);
        });

        // Initialize password requirements on page load
        setTimeout(() => {
          const errorContainer = password.parentElement.parentElement.querySelector('.error-message');
          validatePassword(password, errorContainer, false);
        }, 100);
      }

      if (confirmPassword) {
        confirmPassword.addEventListener('input', validatePasswordMatch);
        confirmPassword.addEventListener('keyup', validatePasswordMatch);
      }

      // Form submission handling
      if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
          console.log('Form submission started');
          
          let isValid = true;
          
          // Validate all fields
          validationRules.forEach(rule => {
            const input = document.getElementById(rule.id);
            const errorContainer = input ? input.parentElement.querySelector('.error-message') || input.parentElement.parentElement?.querySelector('.error-message') : null;
            
            if (input && errorContainer) {
              if (!rule.validator(input, errorContainer)) {
                isValid = false;
              }
            }
          });

          // Check password match
          if (!validatePasswordMatch()) {
            isValid = false;
          }

          if (!isValid) {
            e.preventDefault();
            console.log('Form validation failed');
            
            // Scroll to first error
            const firstError = form.querySelector('.border-red-500');
            if (firstError) {
              firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
              firstError.focus();
            }
            return;
          }

          console.log('Form validation passed, submitting...');
          
          // Show loading state
          if (submitText && submitLoader) {
            submitText.classList.add('hidden');
            submitLoader.classList.remove('hidden');
            submitBtn.disabled = true;
          }
        });
      }

    });
  </script>
</body>

</html>
