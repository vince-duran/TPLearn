<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$message = '';
$success = false;
$token = '';
$valid_token = false;

// Check if token is provided and valid
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    if (!empty($token)) {
        // Check if token exists and is not expired
        $stmt = $conn->prepare("
            SELECT pr.user_id, pr.used_at, u.email,
                   CASE 
                     WHEN u.role = 'student' THEN sp.first_name
                     WHEN u.role = 'tutor' THEN tp.first_name
                     ELSE u.username
                   END as first_name
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
            LEFT JOIN tutor_profiles tp ON u.id = tp.user_id AND u.role = 'tutor'
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $reset_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($reset_data && !$reset_data['used_at']) {
            $valid_token = true;
        } elseif ($reset_data && $reset_data['used_at']) {
            $message = 'This password reset link has already been used.';
        } else {
            $message = 'Invalid or expired password reset link.';
        }
    } else {
        $message = 'Invalid password reset link.';
    }
} else {
    $message = 'No password reset token provided.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($password)) {
        $message = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
    } else {
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        
        try {
            // Update user password
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param('si', $hashed_password, $reset_data['user_id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Mark reset token as used
            $markUsedStmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
            $markUsedStmt->bind_param('s', $token);
            $markUsedStmt->execute();
            $markUsedStmt->close();
            
            $conn->commit();
            
            $success = true;
            $message = 'Your password has been successfully reset! You can now log in with your new password.';
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Password reset failed: " . $e->getMessage());
            $message = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - TPLearn</title>
    <link rel="stylesheet" href="assets/standard-ui.css">
    <link rel="stylesheet" href="assets/tailwind.min.css">
    <style>
        /* TPLearn Custom Colors */
        :root {
            --tplearn-green: #10b981;
            --tplearn-light-green: #34d399;
            --tplearn-green-50: #ecfdf5;
            --tplearn-green-100: #d1fae5;
            --tplearn-green-600: #059669;
        }
        
        .bg-tplearn-green { background-color: var(--tplearn-green) !important; }
        .text-tplearn-green { color: var(--tplearn-green) !important; }
        .border-tplearn-green { border-color: var(--tplearn-green) !important; }
        .hover\:bg-tplearn-green-600:hover { background-color: var(--tplearn-green-600) !important; }
        .focus\:ring-tplearn-green:focus { 
            --tw-ring-opacity: 1 !important;
            --tw-ring-color: var(--tplearn-green) !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
        }
        .focus\:border-tplearn-green:focus { border-color: var(--tplearn-green) !important; }
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
                        <h1 class="text-2xl font-bold text-gray-800">Reset Password</h1>
                        <p class="text-sm text-gray-600">Create a new password for your account</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
        <div class="max-w-md w-full space-y-8">
            
            <?php if ($success): ?>
                <!-- Success Message -->
                <div class="bg-green-50 border-l-4 border-green-400 p-6 rounded-lg text-center">
                    <div class="flex items-center justify-center mb-4">
                        <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-green-800 mb-2">Password Reset Successfully!</h2>
                    <p class="text-green-700 mb-6"><?= htmlspecialchars($message) ?></p>
                    <div class="space-y-3">
                        <a href="login.php" class="w-full inline-block bg-tplearn-green text-white py-3 px-6 rounded hover:bg-tplearn-green-600 transition font-medium">
                            Go to Login
                        </a>
                        <p class="text-sm text-gray-600">
                            You can now log in using your new password
                        </p>
                    </div>
                </div>
            <?php elseif ($valid_token): ?>
                <!-- Reset Password Form -->
                <div class="bg-white p-8 rounded-lg shadow-lg">
                    <div class="text-center mb-6">
                        <svg class="w-12 h-12 text-blue-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Create New Password</h2>
                        <p class="text-gray-600">Enter a new password for your TPLearn account</p>
                    </div>

                    <?php if (!empty($message) && !$success): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 text-red-700 p-4 mb-6 rounded">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6" id="resetForm">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                New Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    required
                                    minlength="8"
                                    class="w-full px-3 py-3 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-tplearn-green transition-colors"
                                    placeholder="Enter new password" />
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                    <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Password must be at least 8 characters long
                            </p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                Confirm New Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    required
                                    minlength="8"
                                    class="w-full px-3 py-3 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-tplearn-green transition-colors"
                                    placeholder="Confirm new password" />
                                <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                    <svg id="eyeIconConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-1 text-sm hidden"></div>
                        </div>

                        <button 
                            type="submit"
                            class="w-full bg-tplearn-green text-white py-3 px-6 rounded-md hover:bg-tplearn-green-600 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:ring-offset-2 transition-colors font-medium">
                            Reset Password
                        </button>
                    </form>

                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-600">
                            Remember your password? 
                            <a href="login.php" class="text-tplearn-green hover:underline font-medium">Back to Login</a>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Error State -->
                <div class="bg-red-50 border-l-4 border-red-400 p-6 rounded-lg text-center">
                    <div class="flex items-center justify-center mb-4">
                        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-red-800 mb-2">Reset Link Invalid</h2>
                    <p class="text-red-700 mb-6"><?= htmlspecialchars($message) ?></p>
                    <div class="space-y-3">
                        <a href="forgot-password.php" class="w-full inline-block bg-blue-500 text-white py-2 px-6 rounded hover:bg-blue-600 transition">
                            Request New Reset Link
                        </a>
                        <a href="login.php" class="w-full inline-block bg-gray-500 text-white py-2 px-6 rounded hover:bg-gray-600 transition">
                            Back to Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Password Toggle and Validation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeIconConfirm = document.getElementById('eyeIconConfirm');
            const passwordMatch = document.getElementById('passwordMatch');

            // Password toggle functionality
            function setupPasswordToggle(toggleBtn, passwordField, eyeIconEl) {
                toggleBtn.addEventListener('click', function() {
                    const isPassword = passwordField.type === 'password';
                    passwordField.type = isPassword ? 'text' : 'password';

                    if (isPassword) {
                        eyeIconEl.innerHTML = `
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"></path>
                        `;
                    } else {
                        eyeIconEl.innerHTML = `
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        `;
                    }
                });
            }

            setupPasswordToggle(togglePassword, passwordInput, eyeIcon);
            setupPasswordToggle(toggleConfirmPassword, confirmPasswordInput, eyeIconConfirm);

            // Password matching validation
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (confirmPassword === '') {
                    passwordMatch.classList.add('hidden');
                    return;
                }

                passwordMatch.classList.remove('hidden');

                if (password === confirmPassword) {
                    passwordMatch.textContent = 'Passwords match';
                    passwordMatch.className = 'mt-1 text-sm text-green-600';
                } else {
                    passwordMatch.textContent = 'Passwords do not match';
                    passwordMatch.className = 'mt-1 text-sm text-red-600';
                }
            }

            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            passwordInput.addEventListener('input', checkPasswordMatch);

            // Form validation
            document.getElementById('resetForm').addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return;
                }

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return;
                }
            });
        });
    </script>
</body>
</html>