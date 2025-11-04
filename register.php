<?php
// Show errors during development (remove in production)
if (!isset($_ENV['RAILWAY_ENVIRONMENT'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

session_start();

// Include Railway path configuration
require_once __DIR__ . '/config/railway-paths.php';

// Use safe includes for Railway compatibility
try {
    safe_require('includes/db.php'); // provides $conn (mysqli)
    safe_require('includes/data-helpers.php'); // provides duplicate checking functions
    safe_require('includes/email-verification.php'); // provides email verification functions
    safe_require('assets/icons.php'); // provides standardized icon functions
} catch (Exception $e) {
    // Fallback for Railway deployment
    if (isset($_ENV['RAILWAY_ENVIRONMENT'])) {
        require_once '/app/includes/db.php';
        require_once '/app/includes/data-helpers.php';
        require_once '/app/includes/email-verification.php';
        require_once '/app/assets/icons.php';
    } else {
        die("Configuration error: " . $e->getMessage());
    }
}

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
function generateStudentNumber()
{
  $year = date("Y");
  $rand = str_pad((string)rand(1, 999), 3, "0", STR_PAD_LEFT);
  return "TP{$year}-{$rand}";
}

/* ---------- Page state ---------- */
$studentNumber = generateStudentNumber();
$showModal = false;
$errors = [];
$old = [
  'first_name'           => '',
  'last_name'            => '',
  'middle_name'          => '',
  'birthday'             => '',
  'age'                  => '',
  'pwd'                  => 'No',
  // Student address fields
  'province'             => '',
  'city'                 => '',
  'barangay'             => '',
  'zip_code'             => '',
  'subdivision'          => '',
  'street'               => '',
  'house_number'         => '',
  'medical'              => '',
  'parent_name'          => '',
  'facebook_name'        => '',
  'contact_number'       => '',
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
  $old['age']                  = trim($_POST['age'] ?? '');
  $old['pwd']                  = ($_POST['pwd'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
  
  // Student address fields
  $old['province']             = trim($_POST['province'] ?? '');
  $old['city']                 = trim($_POST['city'] ?? '');
  $old['barangay']             = trim($_POST['barangay'] ?? '');
  $old['zip_code']             = trim($_POST['zip_code'] ?? '');
  $old['subdivision']          = trim($_POST['subdivision'] ?? '');
  $old['street']               = trim($_POST['street'] ?? '');
  $old['house_number']         = trim($_POST['house_number'] ?? '');
  
  $old['medical']              = trim($_POST['medical'] ?? '');
  $old['parent_name']          = trim($_POST['parent_name'] ?? '');
  

  
  $old['facebook_name']        = trim($_POST['facebook_name'] ?? '');
  $old['contact_number']       = trim($_POST['contact_number'] ?? '');
  $old['email']                = trim($_POST['email'] ?? '');

  $password              = $_POST['password'] ?? '';
  $confirm               = $_POST['confirm_password'] ?? '';

  // comprehensive validation

  // Name validation - letters, spaces, apostrophes, hyphens only
  if ($old['first_name'] === '') {
    $errors['first_name'] = 'First name is required.';
  } elseif (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $old['first_name'])) {
    $errors['first_name'] = 'First name must be 2-50 characters and contain only letters, spaces, apostrophes, or hyphens.';
  }

  if ($old['last_name'] === '') {
    $errors['last_name'] = 'Last name is required.';
  } elseif (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $old['last_name'])) {
    $errors['last_name'] = 'Last name must be 2-50 characters and contain only letters, spaces, apostrophes, or hyphens.';
  }

  if ($old['middle_name'] !== '' && !preg_match("/^[a-zA-Z\s'-]{1,50}$/", $old['middle_name'])) {
    $errors['middle_name'] = 'Middle name must be 1-50 characters and contain only letters, spaces, apostrophes, or hyphens.';
  }

  // Gender validation (optional)
  if ($old['gender'] !== '' && !in_array($old['gender'], ['Male', 'Female', 'Other'])) {
    $errors['gender'] = 'Please select a valid gender option.';
  }

  // Suffix validation (optional)
  if ($old['suffix'] !== '' && !preg_match("/^[a-zA-Z\s.]{1,20}$/", $old['suffix'])) {
    $errors['suffix'] = 'Suffix must be 1-20 characters and contain only letters, spaces, or periods.';
  }

  // Birthday validation
  if ($old['birthday'] === '') {
    $errors['birthday'] = 'Birthday is required.';
  } else {
    $birthDate = DateTime::createFromFormat('Y-m-d', $old['birthday']);
    $today = new DateTime();
    $minDate = (new DateTime())->modify('-100 years');
    $maxDate = (new DateTime())->modify('-3 years'); // Minimum age of 3

    if (!$birthDate || $birthDate->format('Y-m-d') !== $old['birthday']) {
      $errors['birthday'] = 'Please enter a valid date.';
    } elseif ($birthDate > $today) {
      $errors['birthday'] = 'Birthday cannot be in the future.';
    } elseif ($birthDate < $minDate) {
      $errors['birthday'] = 'Please enter a realistic birth date.';
    } elseif ($birthDate > $maxDate) {
      $errors['birthday'] = 'Student must be at least 3 years old.';
    }
  }

  // Student Address validation
  if ($old['province'] === '') {
    $errors['province'] = 'Province is required.';
  }
  
  if ($old['city'] === '') {
    $errors['city'] = 'City/Municipality is required.';
  }
  
  if ($old['barangay'] === '') {
    $errors['barangay'] = 'Barangay is required.';
  }
  
  if ($old['zip_code'] === '') {
    $errors['zip_code'] = 'Zip code is required.';
  } elseif (!preg_match('/^[0-9]{4}$/', $old['zip_code'])) {
    $errors['zip_code'] = 'Zip code must be exactly 4 digits.';
  }
  
  if ($old['street'] === '') {
    $errors['street'] = 'Street is required.';
  } elseif (strlen($old['street']) < 2 || strlen($old['street']) > 100) {
    $errors['street'] = 'Street must be between 2 and 100 characters.';
  }
  
  if ($old['house_number'] === '') {
    $errors['house_number'] = 'House number/unit is required.';
  } elseif (strlen($old['house_number']) < 1 || strlen($old['house_number']) > 50) {
    $errors['house_number'] = 'House number/unit must be between 1 and 50 characters.';
  }
  
  // Subdivision is optional, but validate if provided
  if ($old['subdivision'] !== '' && strlen($old['subdivision']) > 100) {
    $errors['subdivision'] = 'Subdivision/village name cannot exceed 100 characters.';
  }

  // Medical notes validation (optional but limited)
  if ($old['medical'] !== '' && strlen($old['medical']) > 500) {
    $errors['medical'] = 'Medical notes cannot exceed 500 characters.';
  }

  // Parent/Guardian validation
  if ($old['parent_name'] === '') {
    $errors['parent_name'] = 'Parent/Guardian name is required.';
  } elseif (!preg_match("/^[a-zA-Z\s'-]{2,100}$/", $old['parent_name'])) {
    $errors['parent_name'] = 'Parent/Guardian name must be 2-100 characters and contain only letters, spaces, apostrophes, or hyphens.';
  }

  // Facebook name validation (optional)
  if ($old['facebook_name'] !== '' && (!preg_match("/^[a-zA-Z0-9\s._-]{2,50}$/", $old['facebook_name']) || strlen($old['facebook_name']) > 50)) {
    $errors['facebook_name'] = 'Facebook name must be 2-50 characters and contain only letters, numbers, spaces, dots, underscores, or hyphens.';
  }

  // Email validation
  if ($old['email'] === '') {
    $errors['email'] = 'Email address is required.';
  } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
  } elseif (strlen($old['email']) > 100) {
    $errors['email'] = 'Email address cannot exceed 100 characters.';
  } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $old['email'])) {
    $errors['email'] = 'Please enter a properly formatted email address.';
  }

  // Philippine mobile number validation (more flexible)
  if ($old['contact_number'] === '') {
    $errors['contact_number'] = 'Contact number is required.';
  } elseif (!preg_match('/^(09|\+639|639)\d{9}$/', $old['contact_number'])) {
    $errors['contact_number'] = 'Please enter a valid Philippine mobile number (e.g., 09123456789, +639123456789).';
  }

  // Password validation
  if ($password === '') {
    $errors['password'] = 'Password is required.';
  } elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long.';
  } elseif (strlen($password) > 128) {
    $errors['password'] = 'Password cannot exceed 128 characters.';
  } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
    $errors['password'] = 'Password must contain at least one lowercase letter, one uppercase letter, and one number.';
  } elseif (preg_match('/(.)\1{2,}/', $password)) {
    $errors['password'] = 'Password cannot contain more than 2 consecutive identical characters.';
  }

  if ($confirm === '') {
    $errors['confirm'] = 'Please confirm your password.';
  } elseif ($password !== $confirm) {
    $errors['confirm'] = 'Passwords do not match.';
  }

  // Additional security checks
  if (!$errors) {
    // Check for common weak passwords
    $weakPasswords = ['password', '12345678', 'qwerty123', 'abc12345', 'password123'];
    if (in_array(strtolower($password), $weakPasswords)) {
      $errors['password'] = 'This password is too common. Please choose a stronger password.';
    }

    // Check if password contains parts of email or name
    $emailPart = explode('@', $old['email'])[0];
    if (strlen($emailPart) > 3 && stripos($password, $emailPart) !== false) {
      $errors['password'] = 'Password should not contain parts of your email address.';
    }
    if (strlen($old['first_name']) > 2 && stripos($password, $old['first_name']) !== false) {
      $errors['password'] = 'Password should not contain your first name.';
    }
    if (strlen($old['last_name']) > 2 && stripos($password, $old['last_name']) !== false) {
      $errors['password'] = 'Password should not contain your last name.';
    }
  }

  // compute age if blank (readonly in UI but also compute server-side)
  if ($old['birthday'] !== '') {
    $dob = date_create($old['birthday']);
    if ($dob) {
      $old['age'] = (new DateTime())->diff($dob)->y;
    }
  }

  if (!$errors) {
    // Use the new comprehensive duplicate checking and user creation system
    $isPwd = ($old['pwd'] === 'Yes') ? 1 : 0;
    $ageVal = ($old['age'] === '' ? null : (int)$old['age']);
    
    // Construct full address from components
    $fullAddress = trim($old['house_number'] . ' ' . $old['street'] . 
                   ($old['subdivision'] ? ', ' . $old['subdivision'] : '') . 
                   ', ' . $old['barangay'] . ', ' . $old['city'] . ', ' . $old['province'] . ' ' . $old['zip_code']);
    
    // Prepare user data for the new creation function
    $userData = [
      'username' => $studentNumber,
      'email' => $old['email'],
      'password' => $password,
      'role' => 'student',
      'profile' => [
        'first_name' => $old['first_name'],
        'last_name' => $old['last_name'],
        'middle_name' => $old['middle_name'],
        'gender' => $old['gender'],
        'suffix' => $old['suffix'],
        'birthday' => $old['birthday'],
        'age' => $ageVal,
        'is_pwd' => $isPwd,
        'address' => $fullAddress,
        'medical_notes' => $old['medical'],
        // Individual address components
        'province' => $old['province'],
        'city' => $old['city'],
        'barangay' => $old['barangay'],
        'zip_code' => $old['zip_code'],
        'subdivision' => $old['subdivision'],
        'street' => $old['street'],
        'house_number' => $old['house_number']
      ]
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
            $errors['name'] = 'A user with this full name already exists.';
          } elseif (strpos($duplicate, 'Username') !== false) {
            $errors['username'] = 'This username is already taken.';
          }
        }
      } else {
        $errors['save'] = $result['message'];
      }
    } else {
      // User created successfully, now create parent profile and send verification email
      try {
        $userId = $result['internal_id']; // Use internal database ID for foreign key
        
        // Create parent profile (without separate address)
        $pp = $conn->prepare("
          INSERT INTO parent_profiles (student_user_id, full_name, facebook_name, contact_number, address)
          VALUES (?, ?, ?, ?, NULL)
        ");
        $pp->bind_param("isss", $userId, $old['parent_name'], $old['facebook_name'], $old['contact_number']);
        $pp->execute();
        $pp->close();
        
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
        $errors['save'] = 'Registration failed during parent profile creation. Please try again.';
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
  <title>Student Registration - TPLearn</title>
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
            <h1 class="text-2xl font-bold text-gray-800">Student Registration</h1>
            <p class="text-sm text-gray-600">Join TPLearn as a student</p>
          </div>
        </div>
        <nav class="hidden md:flex items-center space-x-6">
          <a href="tutor-register.php" class="text-gray-600 hover:text-tplearn-green transition-colors duration-200">Tutor Registration</a>
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

      <!-- CHILD'S DETAILS -->
      <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-tplearn-green to-tplearn-light-green px-8 py-6">
          <h2 class="text-2xl font-bold text-white flex items-center">
            <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
            </svg>
            Student Information
          </h2>
          <p class="text-green-100 mt-1">Tell us about the student</p>
        </div>

        <div class="p-8">

        <form method="POST" novalidate id="registrationForm">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

          <!-- Name Fields Row -->
          <div class="flex flex-row gap-3">
            <div class="flex-1">
              <label class="block mb-2 text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
              <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($old['first_name']) ?>"
                required maxlength="50" pattern="[a-zA-Z\s'-]{2,50}"
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
                maxlength="50" pattern="[a-zA-Z\s'-]{1,50}"
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
                required maxlength="50" pattern="[a-zA-Z\s'-]{2,50}"
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

          <!-- Full Name Duplicate Error -->
          <?php if (isset($errors['name'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded p-3 text-sm mt-4">
              <?= icon('exclamation-triangle', 'w-4 h-4 inline mr-2') ?>
              <?= htmlspecialchars($errors['name']) ?>
            </div>
          <?php endif; ?>

          <!-- Gender, Birthday, Age Row -->
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
              <label class="block mb-2 text-sm font-medium text-gray-700">Birthday <span class="text-red-500">*</span></label>
              <input type="date" name="birthday" id="birthday" value="<?= htmlspecialchars($old['birthday']) ?>"
                required min="<?= date('Y-m-d', strtotime('-100 years')) ?>" max="<?= date('Y-m-d', strtotime('-3 years')) ?>"
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['birthday']) ? 'border-red-500 bg-red-50' : '' ?>">
              <?php if (isset($errors['birthday'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['birthday']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
            <div class="w-32">
              <label class="block mb-2 text-sm font-medium text-gray-700">Age</label>
              <input type="number" name="age" id="age" value="<?= htmlspecialchars($old['age']) ?>"
                class="w-full px-3 py-3 border border-gray-300 rounded-md bg-gray-50" readonly>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
          </div>

          <!-- Student Address Fields -->
          <div class="mt-4">
            <h4 class="text-md font-medium mb-3 text-gray-700">Address Information</h4>
            
            <!-- Province and City/Municipality -->
            <div class="grid md:grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700">Province <span class="text-red-500">*</span></label>
                <select name="province" id="province" required 
                  class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['province']) ? 'border-red-500 bg-red-50' : '' ?>">
                  <option value="">Select Province</option>
                </select>
                <?php if (isset($errors['province'])): ?>
                  <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['province']) ?></p>
                <?php endif; ?>
                <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
              </div>
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700">City/Municipality <span class="text-red-500">*</span></label>
                <select name="city" id="city" required disabled
                  class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['city']) ? 'border-red-500 bg-red-50' : '' ?>">
                  <option value="">Select City/Municipality</option>
                </select>
                <?php if (isset($errors['city'])): ?>
                  <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['city']) ?></p>
                <?php endif; ?>
                <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
              </div>
            </div>

            <!-- Barangay and Zip Code -->
            <div class="grid md:grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700">Barangay <span class="text-red-500">*</span></label>
                <select name="barangay" id="barangay" required disabled
                  class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['barangay']) ? 'border-red-500 bg-red-50' : '' ?>">
                  <option value="">Select Barangay</option>
                </select>
                <?php if (isset($errors['barangay'])): ?>
                  <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['barangay']) ?></p>
                <?php endif; ?>
                <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
              </div>
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700">Zip Code <span class="text-red-500">*</span></label>
                <input type="text" name="zip_code" id="zip_code" value="<?= htmlspecialchars($old['zip_code'] ?? '') ?>"
                  required pattern="[0-9]{4}" maxlength="4"
                  class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['zip_code']) ? 'border-red-500 bg-red-50' : '' ?>"
                  placeholder="e.g., 1234">
                <?php if (isset($errors['zip_code'])): ?>
                  <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['zip_code']) ?></p>
                <?php endif; ?>
                <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
              </div>
            </div>

            <!-- Subdivision and Street -->
            <div class="grid md:grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700">Subdivision/Village</label>
                <input type="text" name="subdivision" id="subdivision" value="<?= htmlspecialchars($old['subdivision'] ?? '') ?>"
                  maxlength="100"
                  class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                  placeholder="Subdivision or village name (optional)">
                <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
              </div>
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700">Street <span class="text-red-500">*</span></label>
                <input type="text" name="street" id="street" value="<?= htmlspecialchars($old['street'] ?? '') ?>"
                  required maxlength="100"
                  class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['street']) ? 'border-red-500 bg-red-50' : '' ?>"
                  placeholder="Street name">
                <?php if (isset($errors['street'])): ?>
                  <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['street']) ?></p>
                <?php endif; ?>
                <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
              </div>
            </div>

            <!-- House Number -->
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-700">House Number/Unit <span class="text-red-500">*</span></label>
              <input type="text" name="house_number" id="house_number" value="<?= htmlspecialchars($old['house_number'] ?? '') ?>"
                required maxlength="50"
                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['house_number']) ? 'border-red-500 bg-red-50' : '' ?>"
                placeholder="House number, unit, or building">
              <?php if (isset($errors['house_number'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['house_number']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
            </div>
          </div>

          <!-- Medical History and PWD Row -->
          <div class="flex flex-row gap-4 mt-4">
            <div class="flex-1">
              <label class="block mb-2 text-sm font-medium text-gray-700">Medical History</label>
              <textarea name="medical" id="medical" rows="2" maxlength="500"
                placeholder="Please list any allergies, conditions, or medications (optional)"
                class="w-full px-3 py-3 border border-gray-300 rounded-md text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['medical']) ? 'border-red-500 bg-red-50' : '' ?>"><?= htmlspecialchars($old['medical']) ?></textarea>
              <?php if (isset($errors['medical'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['medical']) ?></p>
              <?php endif; ?>
              <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
              <div class="text-xs text-gray-500 mt-1">
                <span id="medical-count">0</span>/500 characters
              </div>
            </div>
            <div class="w-40">
              <label class="block mb-2 text-sm font-medium text-gray-700">Are you PWD?</label>
              <div class="flex items-center gap-2 mt-3">
                <label class="flex items-center gap-1 text-sm">
                  <input type="radio" name="pwd" value="Yes" class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300"
                    <?= $old['pwd'] === 'Yes' ? 'checked' : ''; ?>> Yes
                </label>
                <label class="flex items-center gap-1 text-sm">
                  <input type="radio" name="pwd" value="No" class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300"
                    <?= $old['pwd'] !== 'Yes' ? 'checked' : ''; ?>> No
                </label>
              </div>
            </div>
          </div>
      </div>

      <!-- PARENT'S DETAILS -->
      <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-tplearn-green to-tplearn-light-green px-8 py-6">
          <h2 class="text-2xl font-bold text-white flex items-center">
            <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path>
            </svg>
            Parent/Guardian Information
          </h2>
          <p class="text-green-100 mt-1">Contact details and account setup</p>
        </div>

        <div class="p-8">

        <div class="mb-4">
          <label class="block mb-2 text-sm font-medium text-gray-700">Parent's Full Name <span class="text-red-500">*</span></label>
          <input type="text" name="parent_name" id="parent_name" value="<?= htmlspecialchars($old['parent_name']) ?>"
            required maxlength="100" pattern="[a-zA-Z\s'-]{2,100}"
            class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['parent_name']) ? 'border-red-500 bg-red-50' : '' ?>"
            placeholder="Enter parent/guardian full name">
          <?php if (isset($errors['parent_name'])): ?>
            <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['parent_name']) ?></p>
          <?php endif; ?>
          <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Facebook Name</label>
            <input type="text" name="facebook_name" id="facebook_name" value="<?= htmlspecialchars($old['facebook_name']) ?>"
              maxlength="50" pattern="[a-zA-Z0-9\s._-]{2,50}"
              class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['facebook_name']) ? 'border-red-500 bg-red-50' : '' ?>"
              placeholder="Facebook name (optional)">
            <?php if (isset($errors['facebook_name'])): ?>
              <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['facebook_name']) ?></p>
            <?php endif; ?>
            <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
          </div>
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Contact Number <span class="text-red-500">*</span></label>
            <input type="tel" name="contact_number" id="contact_number" value="<?= htmlspecialchars($old['contact_number']) ?>"
              required pattern="^(09|\+639|639)\d{9}$" maxlength="13"
              class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['contact_number']) ? 'border-red-500 bg-red-50' : '' ?>"
              placeholder="e.g., 09123456789 or +639123456789">
            <?php if (isset($errors['contact_number'])): ?>
              <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['contact_number']) ?></p>
            <?php endif; ?>
            <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
          </div>
        </div>

        <!-- ACCOUNT DETAILS -->
        <h3 class="text-lg font-semibold mt-6 mb-3 text-gray-800">Account Details</h3>
        <div class="grid md:grid-cols-1 gap-4">
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Email Address <span class="text-red-500">*</span></label>
            <input type="email" name="email" id="email" value="<?= htmlspecialchars($old['email']) ?>"
              required maxlength="100" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
              class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors <?= isset($errors['email']) ? 'border-red-500 bg-red-50' : '' ?>"
              placeholder="name@example.com">
            <?php if (isset($errors['email'])): ?>
              <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['email']) ?></p>
            <?php endif; ?>
            <div class="error-message text-red-500 text-xs mt-1 hidden"></div>
          </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4 mt-4">
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
            <div class="relative">
              <input type="password" name="password" id="password" required minlength="8" maxlength="128"
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
        </form>
      </div>
    </div>

    <!-- SUCCESS MODAL -->
    <?php if ($showModal): ?>
      <div class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full text-center">
          <div class="text-blue-600 text-4xl mb-4">📧</div>
          <h2 class="text-xl font-bold mb-2">Registration Successful!</h2>
          <p class="mb-1">Your Student Number is</p>
          <p class="text-green-600 font-semibold text-lg mb-4"><?= htmlspecialchars($studentNumber) ?></p>
          
          <?php if (isset($_SESSION['show_verification_link']) && $_SESSION['show_verification_link'] && isset($_SESSION['simulated_verification_url'])): ?>
            <!-- Development Mode - Show verification link directly -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
              <h3 class="font-semibold text-yellow-800 mb-2">🚀 Development Mode</h3>
              <p class="text-yellow-700 text-sm mb-3">
                Email simulation is active. Click the button below to verify your email:
              </p>
              <a href="<?= htmlspecialchars($_SESSION['simulated_verification_url']) ?>" 
                 class="inline-block bg-yellow-500 text-white py-2 px-4 rounded hover:bg-yellow-600 transition text-sm">
                Verify Email Now
              </a>
            </div>
            <?php 
            // Clear the simulation data
            unset($_SESSION['show_verification_link']);
            unset($_SESSION['simulated_verification_url']);
            ?>
          <?php endif; ?>
          
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
            Use your Student Number <strong><?= htmlspecialchars($studentNumber) ?></strong> as your username for login after verification.
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

        // Character counters
        const textareas = [{
            element: document.getElementById('medical'),
            counter: document.getElementById('medical-count')
          }
        ];

        textareas.forEach(({
          element,
          counter
        }) => {
          if (element && counter) {
            element.addEventListener('input', () => {
              counter.textContent = element.value.length;
            });
            // Initialize count
            counter.textContent = element.value.length;
          }
        });

        // Password visibility toggles
        const passwordToggles = [{
            input: document.getElementById('password'),
            toggle: document.getElementById('togglePassword'),
            icon: document.getElementById('eyeIcon')
          },
          {
            input: document.getElementById('confirm_password'),
            toggle: document.getElementById('toggleConfirmPassword'),
            icon: document.getElementById('eyeIconConfirm')
          }
        ];

        passwordToggles.forEach(({
          input,
          toggle,
          icon
        }) => {
          if (toggle && input && icon) {
            toggle.addEventListener('click', () => {
              const isPassword = input.type === 'password';
              input.type = isPassword ? 'text' : 'password';

              if (isPassword) {
                icon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.758 6.758M9.878 9.878l-6.363 6.364m0 0l3.535-3.536m0 0L9.878 9.878"></path>
              `;
              } else {
                icon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
              `;
              }
            });
          }
        });

        // Real-time validation functions
        function validateName(input, errorContainer) {
          const value = input.value.trim();
          const pattern = /^[a-zA-Z\s'-]{2,50}$/;

          if (!value) {
            showError(input, errorContainer, 'This field is required.');
            return false;
          }
          if (!pattern.test(value)) {
            showError(input, errorContainer, 'Must be 2-50 characters and contain only letters, spaces, apostrophes, or hyphens.');
            return false;
          }

          showSuccess(input, errorContainer);
          return true;
        }

        function validateEmail(input, errorContainer) {
          const value = input.value.trim();
          const pattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

          if (!value) {
            showError(input, errorContainer, 'Email address is required.');
            return false;
          }
          if (!pattern.test(value)) {
            showError(input, errorContainer, 'Please enter a valid email address.');
            return false;
          }

          showSuccess(input, errorContainer);
          return true;
        }

        function validatePhone(input, errorContainer) {
          const value = input.value.trim();
          const pattern = /^(09|\+639|639)\d{9}$/;

          if (!value) {
            showError(input, errorContainer, 'Contact number is required.');
            return false;
          }
          if (!pattern.test(value)) {
            showError(input, errorContainer, 'Please enter a valid Philippine mobile number.');
            return false;
          }

          showSuccess(input, errorContainer);
          return true;
        }

        function validateAddress(input, errorContainer) {
          const value = input.value.trim();

          if (!value) {
            showError(input, errorContainer, 'Address is required.');
            return false;
          }
          if (value.length < 10) {
            showError(input, errorContainer, 'Address must be at least 10 characters.');
            return false;
          }
          if (value.length > 255) {
            showError(input, errorContainer, 'Address cannot exceed 255 characters.');
            return false;
          }

          showSuccess(input, errorContainer);
          return true;
        }

        function validateBirthday(input, errorContainer) {
          const value = input.value;

          if (!value) {
            showError(input, errorContainer, 'Birthday is required.');
            return false;
          }

          const birthDate = new Date(value);
          const today = new Date();
          const minDate = new Date();
          minDate.setFullYear(today.getFullYear() - 100);
          const maxDate = new Date();
          maxDate.setFullYear(today.getFullYear() - 3);

          if (birthDate > today) {
            showError(input, errorContainer, 'Birthday cannot be in the future.');
            return false;
          }
          if (birthDate < minDate) {
            showError(input, errorContainer, 'Please enter a realistic birth date.');
            return false;
          }
          if (birthDate > maxDate) {
            showError(input, errorContainer, 'Student must be at least 3 years old.');
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

          // Don't show anything if confirm password is empty
          if (!confirm) {
            if (matchIndicator) matchIndicator.classList.add('hidden');
            return true;
          }

          // Show the match indicator when there's content
          if (matchIndicator) matchIndicator.classList.remove('hidden');

          if (password === confirm && confirm.length > 0) {
            if (matchIcon) {
              matchIcon.textContent = '✓';
              matchIcon.className = 'mr-2 text-green-500';
            }
            if (matchText) {
              matchText.textContent = 'Passwords match';
              matchText.className = 'text-green-500';
            }
            return true;
          } else if (confirm.length > 0) {
            if (matchIcon) {
              matchIcon.textContent = '✗';
              matchIcon.className = 'mr-2 text-red-500';
            }
            if (matchText) {
              matchText.textContent = 'Passwords do not match';
              matchText.className = 'text-red-500';
            }
            return false;
          }
          
          return true;
        }

        function updateRequirement(id, met) {
          const element = document.getElementById(id);
          if (element) {
            const icon = element.querySelector('span');
            if (icon) {
              if (met) {
                icon.textContent = '✓';
                element.className = 'flex items-center text-green-600';
              } else {
                icon.textContent = '✗';
                element.className = 'flex items-center text-gray-500';
              }
            }
          }
        }

        function showError(input, errorContainer, message) {
          input.classList.remove('border-green-500', 'bg-green-50');
          input.classList.add('border-red-500', 'bg-red-50');
          errorContainer.textContent = message;
          errorContainer.classList.remove('hidden');
        }

        function showSuccess(input, errorContainer) {
          input.classList.remove('border-red-500', 'bg-red-50');
          input.classList.add('border-green-500', 'bg-green-50');
          errorContainer.classList.add('hidden');
        }

        // Set up real-time validation
        const validationRules = [{
            input: 'first_name',
            validator: validateName
          },
          {
            input: 'last_name',
            validator: validateName
          },
          {
            input: 'middle_name',
            validator: (input, errorContainer) => {
              if (!input.value.trim()) return true; // Optional field
              return validateName(input, errorContainer);
            }
          },
          {
            input: 'email',
            validator: validateEmail
          },
          {
            input: 'contact_number',
            validator: validatePhone
          },
          {
            input: 'parent_name',
            validator: validateName
          },
          {
            input: 'birthday',
            validator: validateBirthday
          },
          {
            input: 'password',
            validator: validatePassword
          }
        ];

        validationRules.forEach(({
          input: inputId,
          validator
        }) => {
          const input = document.getElementById(inputId);
          const errorContainer = input?.parentElement.querySelector('.error-message');

          if (input && errorContainer) {
            // Blur event - show errors
            input.addEventListener('blur', () => {
              if (inputId === 'password') {
                validator(input, errorContainer, true); // Show errors on blur
              } else {
                validator(input, errorContainer);
              }
            });
            
            // Input event - update indicators but don't show errors while typing
            input.addEventListener('input', () => {
              // Clear error styling on input
              if (input.classList.contains('border-red-500')) {
                input.classList.remove('border-red-500', 'bg-red-50');
                input.classList.add('border-gray-300');
              }
              
              // For password input, run validation without showing errors
              if (inputId === 'password') {
                validator(input, errorContainer, false); // Don't show errors while typing
              }
            });
          }
        });

        // Special handling for password confirmation
        const confirmPassword = document.getElementById('confirm_password');
        const confirmErrorContainer = confirmPassword?.parentElement.querySelector('.error-message');

        if (confirmPassword && confirmErrorContainer) {
          confirmPassword.addEventListener('input', validatePasswordMatch);
          confirmPassword.addEventListener('blur', () => {
            if (!validatePasswordMatch()) {
              showError(confirmPassword, confirmErrorContainer, 'Passwords do not match.');
            } else {
              showSuccess(confirmPassword, confirmErrorContainer);
            }
          });
        }

        // Form submission
        form.addEventListener('submit', function(e) {
          e.preventDefault();

          // Validate all fields
          let isValid = true;

          validationRules.forEach(({
            input: inputId,
            validator
          }) => {
            const input = document.getElementById(inputId);
            const errorContainer = input?.parentElement.querySelector('.error-message');

            if (input && errorContainer) {
              if (inputId === 'password') {
                if (!validator(input, errorContainer, true)) { // Show errors on form submit
                  isValid = false;
                }
              } else {
                if (!validator(input, errorContainer)) {
                  isValid = false;
                }
              }
            }
          });

          // Validate password confirmation
          if (!validatePasswordMatch()) {
            isValid = false;
          }

          if (isValid) {
            // Show loading state
            submitBtn.disabled = true;
            submitText.classList.add('hidden');
            submitLoader.classList.remove('hidden');

            // Submit form
            form.submit();
          } else {
            // Scroll to first error
            const firstError = form.querySelector('.border-red-500');
            if (firstError) {
              firstError.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
              });
              firstError.focus();
            }
          }
        });

        // Initialize character counts
        textareas.forEach(({
          element,
          counter
        }) => {
          if (element && counter) {
            counter.textContent = element.value.length;
          }
        });

        // Initialize password strength indicator  
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
          // Simple direct event listener for password strength
          passwordInput.addEventListener('input', function() {
            const value = this.value;
            
            // Check each requirement
            const hasLength = value.length >= 8;
            const hasLowercase = /[a-z]/.test(value);
            const hasUppercase = /[A-Z]/.test(value);
            const hasNumber = /\d/.test(value);
            const hasSymbol = /[!@#$%^&*(),.?":{}|<>]/.test(value);
            
            // Update each requirement indicator
            updatePasswordRequirement('req-length', hasLength);
            updatePasswordRequirement('req-lowercase', hasLowercase);
            updatePasswordRequirement('req-uppercase', hasUppercase);
            updatePasswordRequirement('req-number', hasNumber);
            updatePasswordRequirement('req-symbol', hasSymbol);
          });
          
          // Initialize on page load
          passwordInput.dispatchEvent(new Event('input'));
        }
        
        // Simple function to update password requirements
        function updatePasswordRequirement(elementId, isMet) {
          const element = document.getElementById(elementId);
          if (element) {
            const span = element.querySelector('span');
            if (span) {
              if (isMet) {
                span.textContent = '✓';
                element.className = 'flex items-center text-green-600';
              } else {
                span.textContent = '✗';
                element.className = 'flex items-center text-gray-500';
              }
            }
          }
        }

        // Initialize password strength indicator
        const passwordErrorContainer = passwordInput?.parentElement.querySelector('.error-message');
        if (passwordInput && passwordErrorContainer) {
          // Trigger initial password validation to set up the strength indicator
          validatePassword(passwordInput, passwordErrorContainer, false);
          
          // Add additional direct event listener to ensure it works
          passwordInput.addEventListener('keyup', function() {
            validatePassword(this, passwordErrorContainer, false);
          });
        }
      });

      // Auto-calc age from birthday (enhanced)
      const bday = document.getElementById('birthday');
      const age = document.getElementById('age');

      function calcAge() {
        if (!bday.value) {
          age.value = '';
          return;
        }
        const dob = new Date(bday.value);
        if (isNaN(dob.getTime())) {
          age.value = '';
          return;
        }
        const today = new Date();
        let a = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) a--;
        age.value = a >= 0 ? a : '';
      }
      bday?.addEventListener('change', calcAge);
      calcAge();

      // Location dropdown functionality with enhanced search
      async function loadProvinces() {
        try {
          const response = await fetch('api/locations.php?action=provinces');
          const data = await response.json();
          
          if (data.success) {
            const studentProvince = document.getElementById('province');
            
            // Clear and populate student province dropdown
            studentProvince.innerHTML = '<option value="">Select Province</option>';
            data.data.forEach(province => {
              // Display province name in title case for better readability
              const displayName = province.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
              studentProvince.innerHTML += `<option value="${province}">${displayName}</option>`;
            });
            
            console.log(`Loaded ${data.count} provinces from comprehensive PSA data`);
          } else {
            console.error('Failed to load provinces:', data.error);
          }
        } catch (error) {
          console.error('Error loading provinces:', error);
        }
      }
      
      async function loadCities(province, targetCityId, targetBarangayId) {
        try {
          const response = await fetch(`api/locations.php?action=cities&province=${encodeURIComponent(province)}`);
          const data = await response.json();
          
          if (data.success) {
            const citySelect = document.getElementById(targetCityId);
            const barangaySelect = document.getElementById(targetBarangayId);
            
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
          } else {
            console.error('Failed to load cities:', data.error);
          }
        } catch (error) {
          console.error('Error loading cities:', error);
        }
      }
      
      async function loadBarangays(province, city, targetBarangayId) {
        try {
          const response = await fetch(`api/locations.php?action=barangays&province=${encodeURIComponent(province)}&city=${encodeURIComponent(city)}`);
          const data = await response.json();
          
          if (data.success) {
            const barangaySelect = document.getElementById(targetBarangayId);
            
            // Clear and populate barangay dropdown
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            data.data.forEach(barangay => {
              // Display barangay name in title case for better readability
              const displayName = barangay.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
              barangaySelect.innerHTML += `<option value="${barangay}">${displayName}</option>`;
            });
            barangaySelect.disabled = false;
            
            console.log(`Loaded ${data.count} barangays for ${data.city}, ${data.province}`);
          } else {
            console.error('Failed to load barangays:', data.error);
          }
        } catch (error) {
          console.error('Error loading barangays:', error);
        }
      }
      
      // Add search functionality to province dropdowns
      function addProvinceSearch(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Convert select to a searchable dropdown
        select.addEventListener('focus', function() {
          // Store original options for filtering
          if (!this.dataset.originalOptions) {
            const options = Array.from(this.options).slice(1); // Skip the first "Select..." option
            this.dataset.originalOptions = JSON.stringify(options.map(opt => ({
              value: opt.value,
              text: opt.textContent
            })));
          }
        });
        
        select.addEventListener('keyup', function(e) {
          if (e.key === 'Enter' || e.key === 'Tab') return;
          
          const searchTerm = this.value.toLowerCase();
          const originalOptions = JSON.parse(this.dataset.originalOptions || '[]');
          
          // Clear current options except the first one
          this.innerHTML = '<option value="">Select Province</option>';
          
          // Filter and add matching options
          const filteredOptions = originalOptions.filter(option => 
            option.text.toLowerCase().includes(searchTerm) ||
            option.value.toLowerCase().includes(searchTerm)
          );
          
          filteredOptions.slice(0, 20).forEach(option => { // Limit to first 20 matches
            const optionEl = document.createElement('option');
            optionEl.value = option.value;
            optionEl.textContent = option.text;
            this.appendChild(optionEl);
          });
          
          // Show dropdown
          this.size = Math.min(filteredOptions.length + 1, 6);
          this.style.position = 'absolute';
          this.style.zIndex = '1000';
        });
        
        select.addEventListener('blur', function() {
          setTimeout(() => {
            this.size = 1;
            this.style.position = '';
            this.style.zIndex = '';
          }, 150);
        });
      }
      
      // Student address dropdowns
      document.getElementById('province')?.addEventListener('change', function() {
        if (this.value) {
          loadCities(this.value, 'city', 'barangay');
        } else {
          document.getElementById('city').innerHTML = '<option value="">Select City/Municipality</option>';
          document.getElementById('city').disabled = true;
          document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
          document.getElementById('barangay').disabled = true;
        }
      });
      
      document.getElementById('city')?.addEventListener('change', function() {
        const province = document.getElementById('province').value;
        if (this.value && province) {
          loadBarangays(province, this.value, 'barangay');
        } else {
          document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
          document.getElementById('barangay').disabled = true;
        }
      });
      
      // Load provinces on page load and add search functionality
      loadProvinces().then(() => {
        // Add search functionality to province dropdowns
        addProvinceSearch('province');
      });
    </script>
</body>

</html>
