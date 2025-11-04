<?php
require_once '../../includes/student-header-standard.php';
$user_id = $_SESSION['user_id'];
$notifications = getUserNotifications($user_id, 50); // Get more notifications for full page
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications - TPLearn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'tplearn-green': '#2ECC71',
                        'tplearn-dark-green': '#27AE60'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">

<?php 
renderStudentHeader('All Notifications', 'View all your recent notifications');
?>

<div class="flex min-h-screen bg-gray-50">
    <!-- Sidebar -->
    <div class="w-64 bg-tplearn-green shadow-lg">
        <div class="p-6">
            <div class="flex items-center space-x-3">
                <div class="bg-white p-2 rounded-lg">
                    <svg class="w-8 h-8 text-tplearn-green" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">TPLearn</h1>
                    <p class="text-sm text-green-100">Tisa at Pera's Academic and Tutorial Services</p>
                </div>
            </div>
        </div>
        
        <nav class="mt-6">
            <a href="../student/student.php" class="flex items-center px-6 py-3 text-white hover:bg-green-600 transition-colors">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                </svg>
                Home
            </a>
            <a href="../student/student-academics.php" class="flex items-center px-6 py-3 text-white hover:bg-green-600 transition-colors">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3z"></path>
                </svg>
                Academics
            </a>
            <a href="../student/student-payments.php" class="flex items-center px-6 py-3 text-white hover:bg-green-600 transition-colors">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"></path>
                </svg>
                Payments
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">All Notifications</h2>
                        <span class="text-sm text-gray-500"><?= count($notifications) ?> total</span>
                    </div>
                </div>
                
                <div class="divide-y divide-gray-100">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <a href="<?= htmlspecialchars($notification['url']) ?>" class="block px-6 py-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-start space-x-4">
                                    <?php
                                    // Set color based on notification type
                                    $color_classes = [
                                        'blue' => 'bg-blue-500',
                                        'red' => 'bg-red-500',
                                        'yellow' => 'bg-yellow-500',
                                        'orange' => 'bg-orange-500',
                                        'green' => 'bg-green-500',
                                        'purple' => 'bg-purple-500'
                                    ];
                                    $dot_color = $color_classes[$notification['color']] ?? 'bg-blue-500';
                                    
                                    // Set icon based on notification type
                                    $type_icons = [
                                        'payment' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"></path></svg>',
                                        'assignment' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path><path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a2 2 0 002 2h4a2 2 0 002-2V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path></svg>',
                                        'material' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"></path><path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>',
                                        'session' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path></svg>'
                                    ];
                                    $icon = $type_icons[$notification['type']] ?? $type_icons['assignment'];
                                    ?>
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 <?= $dot_color ?> rounded-full flex items-center justify-center text-white">
                                            <?= $icon ?>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?= htmlspecialchars($notification['time']) ?>
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="px-6 py-12 text-center">
                            <div class="text-gray-400 mb-4">
                                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM4 15h8v-2H4v2zM4 11h10V9H4v2zM4 7h12V5H4v2z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No notifications yet</h3>
                            <p class="text-gray-500">You'll see your notifications here when you have new assignments, materials, or payment reminders.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notification-dropdown');
    dropdown.classList.toggle('hidden');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notification-dropdown');
    const button = event.target.closest('button[onclick="toggleNotificationDropdown()"]');
    
    if (!button && !dropdown.contains(event.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>

</body>
</html>