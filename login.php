<?php
// Bootstrap - handles Railway/Local compatibility
require_once __DIR__ . '/bootstrap.php';

// Start buffering BEFORE any includes to prevent accidental output (BOM/whitespace) from breaking redirects
ob_start();

session_start();

// Include required files
tpl_require('includes/db.php');
tpl_require('includes/email-verification.php');
tpl_require('assets/icons.php');

$error = '';

// Simple rate limit in session (resets after success)
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;

// Enhanced validation and security
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Rate limiting with progressive delays
  $attempts = $_SESSION['login_attempts'] ?? 0;
  if ($attempts >= 5) {
    usleep(500000 + ($attempts - 5) * 200000); // Increasing delay
  }

  // Check for excessive attempts
  if ($attempts >= 10) {
    $error = 'Too many failed login attempts. Please try again later.';
  } else {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';

    // Input validation
    if ($usernameOrEmail === '') {
      $error = 'Please enter your Student/Tutor ID or email address.';
    } elseif ($password === '') {
      $error = 'Please enter your password.';
    } elseif (strlen($usernameOrEmail) > 100) {
      $error = 'Username or email is too long.';
    } elseif (strlen($password) > 128) {
      $error = 'Password is too long.';
    } else {
      // Accept username OR email - check all statuses to provide appropriate messages
      $sql  = "SELECT id, username, email, password, role, status, email_verified FROM users WHERE (username = ? OR email = ?) LIMIT 1";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ss", $usernameOrEmail, $usernameOrEmail);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
          // Check email verification first
          if (!$user['email_verified']) {
            $_SESSION['login_attempts'] = $attempts + 1;
            $_SESSION['unverified_email'] = $user['email']; // Store for resend functionality
            $error = 'Please verify your email address before logging in. <a href="resend-verification.php" class="text-blue-600 hover:underline">Resend verification email</a>';
            $_SESSION['error_contains_html'] = true; // Flag to allow HTML in error message
          } elseif ($user['status'] === 'inactive') {
            // Account deactivated
            $_SESSION['login_attempts'] = $attempts + 1;
            $error = 'Your account has been deactivated. Please contact support for assistance.';
          } elseif ($user['status'] === 'active' || $user['status'] === 'pending') {
            // Success - reset attempts and log user in (allow pending accounts)
            $_SESSION['login_attempts'] = 0;
            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email']    = $user['email'];
            $_SESSION['role']     = $user['role'];

            // Get the actual name based on role
            $name = '';
            if ($user['role'] === 'tutor') {
              $nameSql = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM tutor_profiles WHERE user_id = ?";
              $nameStmt = $conn->prepare($nameSql);
              $nameStmt->bind_param("i", $user['id']);
              $nameStmt->execute();
              $nameResult = $nameStmt->get_result();
              if ($nameRow = $nameResult->fetch_assoc()) {
                $name = $nameRow['full_name'];
              }
              $nameStmt->close();
            } elseif ($user['role'] === 'student') {
              $nameSql = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM student_profiles WHERE user_id = ?";
              $nameStmt = $conn->prepare($nameSql);
              $nameStmt->bind_param("i", $user['id']);
              $nameStmt->execute();
              $nameResult = $nameStmt->get_result();
              if ($nameRow = $nameResult->fetch_assoc()) {
                $name = $nameRow['full_name'];
              }
              $nameStmt->close();
            }
            
            $_SESSION['name'] = $name ?: $user['username'];

            // Log successful login
            $logSql = "INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'login', 'User logged in successfully')";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param("i", $user['id']);
            $logStmt->execute();
            $logStmt->close();

            // Role-based redirects
            $redirectMap = [
              'admin'   => '/dashboards/admin/admin.php',
              'tutor'   => '/dashboards/tutor/tutor.php',
              'student' => '/dashboards/student/student.php',
            ];

            $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $target = $redirectMap[$user['role']] ?? '/dashboards/student/student.php';
            $location = $basePath . $target;

            if (headers_sent($file, $line)) {
              ob_end_clean();
              echo "<div class='max-w-md mx-auto mt-8 p-6 bg-yellow-50 border border-yellow-200 rounded-lg'>
                      <h3 class='text-lg font-semibold text-yellow-800 mb-2'>Redirect Notice</h3>
                      <p class='text-yellow-700 mb-4'>Login successful! Headers were already sent, but you can continue manually.</p>
                      <a href='" . htmlspecialchars($location, ENT_QUOTES) . "' class='inline-block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition'>Go to Dashboard</a>
                    </div>";
              exit;
            }

            header("Location: " . $location);
            exit;
          } // End of status === 'active' check
        } else {
          // Invalid password
          $_SESSION['login_attempts'] = $attempts + 1;
          $error = 'Invalid password. Please try again.';

          // Log failed login attempt
          $logSql = "INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'failed_login', 'Invalid password attempt')";
          $logStmt = $conn->prepare($logSql);
          $logStmt->bind_param("i", $user['id']);
          $logStmt->execute();
          $logStmt->close();
        }
      } else {
        // User not found or inactive
        $_SESSION['login_attempts'] = $attempts + 1;
        $error = 'User ID or email not found, or account is inactive.';
      }

      $stmt->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Login - TPLearn</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="assets/tailwind.min.css">
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen p-6">

  <div class="grid grid-cols-1 md:grid-cols-2 bg-white rounded-2xl shadow-2xl overflow-hidden max-w-4xl w-full">

    <!-- Left Panel -->
    <div class="hidden md:flex bg-gradient-to-br from-green-400 to-green-600 flex-col items-center justify-center text-white p-12">
      <h2 class="text-3xl font-bold mb-2">TPLearn</h2>
      <p class="text-lg text-center">Tisa at Pisara's Academic Tutoring Services</p>
      <img src="assets/logo.png" alt="TPLearn Logo" class="h-24 w-24 mt-10 rounded-full shadow-lg" />
    </div>

    <!-- Login Form -->
    <div class="p-8 md:p-12">
      <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Welcome Back</h2>

      <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <?php if (isset($_SESSION['error_contains_html']) && $_SESSION['error_contains_html']): ?>
              <?= $error ?>
              <?php unset($_SESSION['error_contains_html']); ?>
            <?php else: ?>
              <?= htmlspecialchars($error) ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (($_SESSION['login_attempts'] ?? 0) >= 3): ?>
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-lg text-sm">
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            Multiple failed attempts detected. Please verify your credentials carefully.
          </div>
        </div>
      <?php endif; ?>

      <form method="POST" id="loginForm" class="space-y-6" autocomplete="on" novalidate>
        <!-- Student ID / Tutor ID / Email -->
        <div>
          <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Student/Tutor ID or Email Address <span class="text-red-500">*</span></label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v12.75A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0z"></path>
              </svg>
            </span>
            <input
              type="text"
              name="username"
              id="username"
              required
              maxlength="100"
              autocomplete="username"
              value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES) ?>"
              class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
              placeholder="Enter your Student/Tutor ID or email address" />
          </div>
          <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
        </div>

        <!-- Password -->
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"></path>
              </svg>
            </span>
            <input
              type="password"
              name="password"
              id="password"
              required
              maxlength="128"
              autocomplete="current-password"
              class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
              placeholder="Enter your password" />
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition-colors" aria-label="Toggle password visibility">
              <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
              </svg>
            </button>
          </div>
          <div class="error-message text-red-500 text-sm mt-1 hidden"></div>
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
            <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
          </div>
          <div class="text-sm">
            <a href="forgot-password.php" class="text-green-600 hover:text-green-500 hover:underline">Forgot password?</a>
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" id="submitBtn" class="w-full py-3 bg-green-500 text-white font-semibold rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
          <span id="submitText">Login</span>
          <span id="submitLoader" class="hidden">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Signing in...
          </span>
        </button>

        <!-- Register Links -->
        <div class="text-center text-sm text-gray-600">
          <p class="mb-3">Don't have an account?</p>
          <div class="flex flex-row justify-center space-x-4">
            <a href="register.php" class="inline-flex items-center justify-center px-4 py-2 border-2 border-green-500 text-green-600 rounded-lg hover:bg-green-50 transition-colors duration-200 font-medium">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
              </svg>
              Register as Student
            </a>
            <a href="tutor-register.php" class="inline-flex items-center justify-center px-4 py-2 border-2 border-blue-500 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors duration-200 font-medium">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
              </svg>
              Register as Tutor
            </a>
          </div>
        </div>

        <!-- Help Text -->
        <div class="text-center text-xs text-gray-500 mt-4">
          <p>Need help? Contact support at <a href="mailto:support@tplearn.com" class="text-green-600 hover:underline">support@tplearn.com</a></p>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('loginForm');
      const usernameInput = document.getElementById('username');
      const passwordInput = document.getElementById('password');
      const submitBtn = document.getElementById('submitBtn');
      const submitText = document.getElementById('submitText');
      const submitLoader = document.getElementById('submitLoader');
      const togglePassword = document.getElementById('togglePassword');
      const eyeIcon = document.getElementById('eyeIcon');

      // Password visibility toggle
      togglePassword.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';

        if (isPassword) {
          // Show "eye-slash" icon when password is visible
          eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"></path>
          `;
        } else {
          // Show "eye" icon when password is hidden
          eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
          `;
        }
      });

      // Real-time validation
      function showError(input, message) {
        input.classList.add('border-red-500', 'bg-red-50');
        input.classList.remove('border-gray-300', 'border-green-500', 'bg-green-50');

        const errorContainer = input.parentElement.parentElement.querySelector('.error-message');
        if (errorContainer) {
          errorContainer.textContent = message;
          errorContainer.classList.remove('hidden');
        }
      }

      function showSuccess(input) {
        input.classList.add('border-green-500', 'bg-green-50');
        input.classList.remove('border-red-500', 'bg-red-50', 'border-gray-300');

        const errorContainer = input.parentElement.parentElement.querySelector('.error-message');
        if (errorContainer) {
          errorContainer.classList.add('hidden');
        }
      }

      function clearValidation(input) {
        input.classList.remove('border-red-500', 'bg-red-50', 'border-green-500', 'bg-green-50');
        input.classList.add('border-gray-300');

        const errorContainer = input.parentElement.parentElement.querySelector('.error-message');
        if (errorContainer) {
          errorContainer.classList.add('hidden');
        }
      }

      function validateUsername(input) {
        const value = input.value.trim();

        if (!value) {
          showError(input, 'Please enter your User ID or email address.');
          return false;
        }

        if (value.length > 100) {
          showError(input, 'Username or email is too long.');
          return false;
        }

        // Basic format validation
        if (value.includes('@')) {
          // Email format
          const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
          if (!emailPattern.test(value)) {
            showError(input, 'Please enter a valid email address.');
            return false;
          }
        } else {
          // User ID format (TPT/TPS/TPA + year + number)
          const userIdPattern = /^TP[TSA]\d{4}-\d{3}$/;
          if (!userIdPattern.test(value)) {
            showError(input, 'User ID should be in format TPT2025-001, TPS2024-001, or TPA2024-001.');
            return false;
          }
        }

        showSuccess(input);
        return true;
      }

      function validatePassword(input) {
        const value = input.value;

        if (!value) {
          showError(input, 'Please enter your password.');
          return false;
        }

        if (value.length > 128) {
          showError(input, 'Password is too long.');
          return false;
        }

        showSuccess(input);
        return true;
      }

      // Add event listeners for real-time validation
      usernameInput.addEventListener('blur', () => validateUsername(usernameInput));
      passwordInput.addEventListener('blur', () => validatePassword(passwordInput));

      // Clear validation on input
      usernameInput.addEventListener('input', () => clearValidation(usernameInput));
      passwordInput.addEventListener('input', () => clearValidation(passwordInput));

      // Form submission with validation
      form.addEventListener('submit', function(e) {
        e.preventDefault();

        const isUsernameValid = validateUsername(usernameInput);
        const isPasswordValid = validatePassword(passwordInput);

        if (isUsernameValid && isPasswordValid) {
          // Show loading state
          submitBtn.disabled = true;
          submitText.classList.add('hidden');
          submitLoader.classList.remove('hidden');

          // Submit the form
          this.submit();
        } else {
          // Focus first invalid field
          if (!isUsernameValid) {
            usernameInput.focus();
          } else if (!isPasswordValid) {
            passwordInput.focus();
          }
        }
      });

      // Auto-focus username field
      usernameInput.focus();

      // Handle "Remember Me" functionality
      const rememberCheckbox = document.getElementById('remember');

      // Load saved username if available
      const savedUsername = localStorage.getItem('tplearn_remembered_username');
      if (savedUsername && rememberCheckbox) {
        usernameInput.value = savedUsername;
        rememberCheckbox.checked = true;
      }

      // Save/remove username based on checkbox
      if (rememberCheckbox) {
        rememberCheckbox.addEventListener('change', function() {
          if (this.checked && usernameInput.value.trim()) {
            localStorage.setItem('tplearn_remembered_username', usernameInput.value.trim());
          } else {
            localStorage.removeItem('tplearn_remembered_username');
          }
        });

        // Update localStorage when username changes
        usernameInput.addEventListener('input', function() {
          if (rememberCheckbox.checked && this.value.trim()) {
            localStorage.setItem('tplearn_remembered_username', this.value.trim());
          }
        });
      }

      // Prevent multiple form submissions
      let isSubmitting = false;
      form.addEventListener('submit', function(e) {
        if (isSubmitting) {
          e.preventDefault();
          return false;
        }
        isSubmitting = true;
      });

      // Add keyboard shortcuts
      document.addEventListener('keydown', function(e) {
        // Alt + U to focus username
        if (e.altKey && e.key === 'u') {
          e.preventDefault();
          usernameInput.focus();
        }
        // Alt + P to focus password
        if (e.altKey && e.key === 'p') {
          e.preventDefault();
          passwordInput.focus();
        }
      });
    });
  </script>
</body>

</html>
<?php
// Flush any buffered output at the very end
ob_end_flush();