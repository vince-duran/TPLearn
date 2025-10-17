<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SweetAlert2 Demo - Admin Dashboard - TPLearn</title>
    <link rel="stylesheet" href="../../assets/tailwind.min.css">
    <?php include '../../includes/common-scripts.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <div class="lg:ml-64 flex-1">
            <?php 
            require_once '../../includes/header.php';
            renderHeader(
                'SweetAlert2 Demo',
                'Test all available notification types',
                'admin',
                $_SESSION['name'] ?? 'Admin',
                [],
                []
            );
            ?>
            
            <main class="p-6">
                <div class="max-w-4xl mx-auto">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">SweetAlert2 Functions Demo</h2>
                        <p class="text-gray-600 mb-8">Click the buttons below to test different SweetAlert2 notifications used throughout the TPLearn system.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Success Alerts -->
                            <div class="space-y-3">
                                <h3 class="font-semibold text-green-800">Success Alerts</h3>
                                <button onclick="TPAlert.success('Success!', 'Operation completed successfully')" 
                                        class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    Success Alert
                                </button>
                                <button onclick="TPAlert.saveSuccess('Student Profile')" 
                                        class="w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                                    Save Success
                                </button>
                                <button onclick="TPAlert.toast('Data saved successfully!', 'success')" 
                                        class="w-full px-4 py-2 bg-green-400 text-white rounded-lg hover:bg-green-500 transition-colors">
                                    Success Toast
                                </button>
                            </div>
                            
                            <!-- Error Alerts -->
                            <div class="space-y-3">
                                <h3 class="font-semibold text-red-800">Error Alerts</h3>
                                <button onclick="TPAlert.error('Error!', 'Something went wrong')" 
                                        class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                    Error Alert
                                </button>
                                <button onclick="TPAlert.networkError()" 
                                        class="w-full px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                                    Network Error
                                </button>
                                <button onclick="TPAlert.toast('Something went wrong!', 'error')" 
                                        class="w-full px-4 py-2 bg-red-400 text-white rounded-lg hover:bg-red-500 transition-colors">
                                    Error Toast
                                </button>
                            </div>
                            
                            <!-- Warning Alerts -->
                            <div class="space-y-3">
                                <h3 class="font-semibold text-yellow-800">Warning Alerts</h3>
                                <button onclick="TPAlert.warning('Warning!', 'Please check your input')" 
                                        class="w-full px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                                    Warning Alert
                                </button>
                                <button onclick="TPAlert.toast('Please fill all required fields', 'warning')" 
                                        class="w-full px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors">
                                    Warning Toast
                                </button>
                            </div>
                            
                            <!-- Info Alerts -->
                            <div class="space-y-3">
                                <h3 class="font-semibold text-blue-800">Info Alerts</h3>
                                <button onclick="TPAlert.info('Information', 'Here is some helpful information')" 
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    Info Alert
                                </button>
                                <button onclick="TPAlert.toast('New feature available!', 'info')" 
                                        class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                                    Info Toast
                                </button>
                            </div>
                            
                            <!-- Confirmation Alerts -->
                            <div class="space-y-3">
                                <h3 class="font-semibold text-purple-800">Confirmations</h3>
                                <button onclick="demoConfirm()" 
                                        class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                                    Confirm Action
                                </button>
                                <button onclick="demoDeleteConfirm()" 
                                        class="w-full px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                                    Delete Confirm
                                </button>
                            </div>
                            
                            <!-- Loading States -->
                            <div class="space-y-3">
                                <h3 class="font-semibold text-gray-800">Loading States</h3>
                                <button onclick="demoLoading()" 
                                        class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                    Loading Demo
                                </button>
                                <button onclick="demoProcessing()" 
                                        class="w-full px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                                    Processing Demo
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                            <h3 class="font-semibold text-gray-800 mb-2">Usage Instructions</h3>
                            <p class="text-gray-600 text-sm">
                                To use SweetAlert2 in any TPLearn page, include the common scripts file: 
                                <code class="bg-gray-200 px-2 py-1 rounded text-xs">&lt;?php include 'includes/common-scripts.php'; ?&gt;</code>
                                <br><br>
                                Then use any of the TPAlert functions like: 
                                <code class="bg-gray-200 px-2 py-1 rounded text-xs">TPAlert.success('Title', 'Message')</code>
                            </p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="../../assets/admin-sidebar.js"></script>
    
    <script>
        async function demoConfirm() {
            const result = await TPAlert.confirm(
                'Are you sure?', 
                'This action will update the student record.'
            );
            
            if (result.isConfirmed) {
                TPAlert.success('Confirmed!', 'Action has been completed.');
            } else {
                TPAlert.info('Cancelled', 'No changes were made.');
            }
        }
        
        async function demoDeleteConfirm() {
            const result = await TPAlert.deleteConfirm('student profile');
            
            if (result.isConfirmed) {
                TPAlert.success('Deleted!', 'The student profile has been removed.');
            }
        }
        
        function demoLoading() {
            TPAlert.loading('Saving Data', 'Please wait while we save your changes...');
            
            // Simulate async operation
            setTimeout(() => {
                TPAlert.success('Saved!', 'Your data has been saved successfully.');
            }, 3000);
        }
        
        function demoProcessing() {
            TPAlert.loading('Generating Report', 'Creating your custom report...');
            
            // Simulate processing
            setTimeout(() => {
                TPAlert.close();
                TPAlert.success('Report Ready!', 'Your report has been generated and is ready to download.');
            }, 2500);
        }
    </script>
</body>
</html>