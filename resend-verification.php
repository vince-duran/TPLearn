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
        // Try to resend verification email
        $result = resendVerificationEmail($email);
        
        if ($result['success']) {
            $success = true;
            $message = 'Verification email has been sent! Please check your inbox.';
        } else {
            $message = $result['error'];
        }
    }
} else {
    // Pre-fill email if available from session
    $email = $_SESSION['registration_email'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification Email - TPLearn</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">Resend Verification Email</h1>
                        <p class="text-sm text-gray-600">Get a new verification link</p>
                    </div>
                </div>
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="login.php" class="text-gray-600 hover:text-tplearn-green transition-colors duration-200">Login</a>
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
                    <p class="text-green-700 mb-4"><?= htmlspecialchars($message) ?></p>
                    <p class="text-sm text-green-600 mb-6">
                        Sent to: <strong><?= htmlspecialchars($email) ?></strong>
                    </p>
                    <div class="space-y-3">
                        <a href="login.php" class="w-full inline-block bg-tplearn-green text-white py-2 px-6 rounded hover:bg-tplearn-green-600 transition">
                            Go to Login
                        </a>
                        <button onclick="location.reload()" class="w-full bg-gray-500 text-white py-2 px-6 rounded hover:bg-gray-600 transition">
                            Send Another Email
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Resend Form -->
                <div class="bg-white p-8 rounded-lg shadow-lg">
                    <div class="text-center mb-6">
                        <svg class="w-12 h-12 text-blue-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Resend Verification Email</h2>
                        <p class="text-gray-600">Enter your email address to receive a new verification link</p>
                    </div>

                    <?php if (!empty($message) && !$success): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 text-red-700 p-4 mb-6 rounded">
                            <?= htmlspecialchars($message) ?>
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
                        </div>

                        <button 
                            type="submit"
                            class="w-full bg-tplearn-green text-white py-3 px-6 rounded-md hover:bg-tplearn-green-600 focus:outline-none focus:ring-2 focus:ring-tplearn-green focus:ring-offset-2 transition-colors font-medium">
                            Send Verification Email
                        </button>
                    </form>

                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-600">
                            Remember your login details? 
                            <a href="login.php" class="text-tplearn-green hover:underline font-medium">Login here</a>
                        </p>
                    </div>

                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600">
                            Need to register? 
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