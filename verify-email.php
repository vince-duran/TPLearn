<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/email-verification.php';

$message = '';
$success = false;
$redirectUrl = '';

// Check if token is provided
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    if (empty($token)) {
        $message = 'Invalid verification link.';
    } else {
        // Verify the token
        $result = verifyEmailToken($token);
        
        if ($result['success']) {
            $success = true;
            $message = $result['message'];
            $redirectUrl = 'login.php';
        } else {
            $message = $result['error'];
        }
    }
} else {
    $message = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - TPLearn</title>
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
                        <h1 class="text-2xl font-bold text-gray-800">Email Verification</h1>
                        <p class="text-sm text-gray-600">TPLearn Account Verification</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <?php if ($success): ?>
                    <!-- Success State -->
                    <div class="bg-green-50 border-l-4 border-green-400 p-6 rounded-lg">
                        <div class="flex items-center justify-center mb-4">
                            <svg class="w-16 h-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-green-800 mb-4">Email Verified Successfully!</h2>
                        <p class="text-green-700 mb-6"><?= htmlspecialchars($message) ?></p>
                        
                        <div class="space-y-4">
                            <a href="<?= htmlspecialchars($redirectUrl) ?>" 
                               class="w-full inline-block bg-tplearn-green text-white py-3 px-6 rounded-lg hover:bg-tplearn-green-600 transition-colors duration-200 font-medium">
                                Go to Login
                            </a>
                            <p class="text-sm text-gray-600">
                                You can now log in to your TPLearn account using your User ID and password.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Error State -->
                    <div class="bg-red-50 border-l-4 border-red-400 p-6 rounded-lg">
                        <div class="flex items-center justify-center mb-4">
                            <svg class="w-16 h-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-red-800 mb-4">Verification Failed</h2>
                        <p class="text-red-700 mb-6"><?= htmlspecialchars($message) ?></p>
                        
                        <div class="space-y-4">
                            <a href="register.php" 
                               class="w-full inline-block bg-blue-500 text-white py-3 px-6 rounded-lg hover:bg-blue-600 transition-colors duration-200 font-medium">
                                Student Registration
                            </a>
                            <a href="tutor-register.php" 
                               class="w-full inline-block bg-tplearn-green text-white py-3 px-6 rounded-lg hover:bg-tplearn-green-600 transition-colors duration-200 font-medium">
                                Tutor Registration
                            </a>
                            <p class="text-sm text-gray-600">
                                If you continue to have problems, please contact support.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($success && !empty($redirectUrl)): ?>
    <!-- Auto-redirect after 5 seconds -->
    <script>
        setTimeout(function() {
            window.location.href = '<?= htmlspecialchars($redirectUrl) ?>';
        }, 5000);
    </script>
    <?php endif; ?>
</body>
</html>