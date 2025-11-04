<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/email-verification.php';

$message = '';
$success = false;
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        // Check if user exists and get user details
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.role, u.email_verified,
                   CASE 
                     WHEN u.role = 'student' THEN sp.first_name
                     WHEN u.role = 'tutor' THEN tp.first_name
                     ELSE u.username
                   END as first_name
            FROM users u
            LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
            LEFT JOIN tutor_profiles tp ON u.id = tp.user_id AND u.role = 'tutor'
            WHERE u.email = ?
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            $message = 'No account found with that email address.';
        } elseif (!$user['email_verified']) {
            $message = 'Please verify your email address first before resetting your password. <a href="resend-verification.php" class="text-blue-600 hover:underline">Resend verification email</a>';
        } else {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing reset tokens for this user
            $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $deleteStmt->bind_param('i', $user['id']);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Insert new reset token
            $insertStmt = $conn->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ");
            $insertStmt->bind_param('iss', $user['id'], $token, $expires_at);
            
            if ($insertStmt->execute()) {
                $insertStmt->close();
                
                // Send password reset email
                $resetLink = "http://localhost/TPLearn/reset-password.php?token=" . urlencode($token);
                $firstName = $user['first_name'] ?: $user['username'];
                
                $emailResult = sendPasswordResetEmail($email, $firstName, $resetLink);
                
                if ($emailResult) {
                    $success = true;
                    $message = 'Password reset instructions have been sent to your email address. Please check your inbox and follow the instructions to reset your password.';
                } else {
                    $message = 'Failed to send password reset email. Please try again later.';
                }
            } else {
                $insertStmt->close();
                $message = 'Failed to generate password reset request. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - TPLearn</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">Forgot Password</h1>
                        <p class="text-sm text-gray-600">Reset your account password</p>
                    </div>
                </div>
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="login.php" class="text-gray-600 hover:text-tplearn-green transition-colors duration-200">Back to Login</a>
                </nav>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-green-800 mb-2">Email Sent!</h2>
                    <p class="text-green-700 mb-4"><?= $message ?></p>
                    <p class="text-sm text-green-600 mb-6">
                        Sent to: <strong><?= htmlspecialchars($email) ?></strong>
                    </p>
                    <div class="space-y-3">
                        <a href="login.php" class="w-full inline-block bg-tplearn-green text-white py-2 px-6 rounded hover:bg-tplearn-green-600 transition">
                            Back to Login
                        </a>
                        <button onclick="location.reload()" class="w-full bg-gray-500 text-white py-2 px-6 rounded hover:bg-gray-600 transition">
                            Send Another Reset Email
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Forgot Password Form -->
                <div class="bg-white p-8 rounded-lg shadow-lg">
                    <div class="text-center mb-6">
                        <svg class="w-12 h-12 text-blue-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Forgot Your Password?</h2>
                        <p class="text-gray-600">Enter your email address and we'll send you a link to reset your password</p>
                    </div>

                    <?php if (!empty($message) && !$success): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 text-red-700 p-4 mb-6 rounded">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                required
                                value="<?= htmlspecialchars($email) ?>"
                                class="w-full px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:border-tplearn-green transition-colors"
                                placeholder="Enter your email address" />
                            <p class="mt-1 text-sm text-gray-500">
                                We'll send password reset instructions to this email
                            </p>
                        </div>

                        <button 
                            type="submit"
                            class="w-full bg-tplearn-green text-white py-3 px-6 rounded-md hover:bg-tplearn-green-600 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:ring-offset-2 transition-colors font-medium">
                            Send Reset Instructions
                        </button>
                    </form>

                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-600">
                            Remember your password? 
                            <a href="login.php" class="text-tplearn-green hover:underline font-medium">Back to Login</a>
                        </p>
                    </div>

                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600">
                            Don't have an account? 
                            <a href="register.php" class="text-blue-600 hover:underline font-medium">Student Registration</a> | 
                            <a href="tutor-register.php" class="text-tplearn-green hover:underline font-medium">Tutor Registration</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>