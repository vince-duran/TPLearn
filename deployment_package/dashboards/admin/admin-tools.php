<?php
require_once __DIR__ . '/../../assets/icons.php';
require_once '../../includes/auth.php';
require_once '../../includes/data-helpers.php';
requireRole('admin');

// Test database connection early
global $conn;
if (!isset($conn) || $conn->connect_error) {
    $message = 'Database connection failed. Please check your database configuration.';
    $messageType = 'error';
} else {
    // Test if we can perform basic operations
    $test_query = $conn->query("SELECT 1");
    if (!$test_query) {
        $message = 'Database query test failed: ' . $conn->error;
        $messageType = 'error';
    }
}

// Handle form submissions
if (empty($message)) {
    $message = '';
}
$messageType = $messageType ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $result = addNewUser($_POST['name'], $_POST['email'], $_POST['role'], $_POST['password']);
                if ($result['success']) {
                    $message = 'User account created successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'edit_user':
                $result = updateUser($_POST['user_id'], $_POST['name'], $_POST['email'], $_POST['role'], $_POST['status']);
                if ($result['success']) {
                    $message = 'User account updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'deactivate_user':
                $result = deactivateUser($_POST['user_id']);
                if ($result['success']) {
                    $message = 'User account deactivated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'reset_password':
                $result = resetUserPassword($_POST['email']);
                if ($result['success']) {
                    $emailStatus = isset($result['email_sent']) && $result['email_sent'] 
                        ? ' Email notification sent to user.' 
                        : ' Please manually provide the new password to the user.';
                    $message = 'Password reset successfully! New password: ' . $result['new_password'] . $emailStatus;
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'add_ewallet':
                // Check if custom provider is selected
                $provider = $_POST['provider'];
                if ($provider === 'Other' && !empty($_POST['custom_provider'])) {
                    $provider = $_POST['custom_provider'];
                }
                $result = addEWalletAccount($provider, $_POST['account_number'], $_POST['account_name']);
                if ($result['success']) {
                    $message = 'E-Wallet account added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'add_bank':
                // Check if custom bank name is selected
                $bankName = $_POST['bank_name'];
                if ($bankName === 'Other' && !empty($_POST['custom_bank_name'])) {
                    $bankName = $_POST['custom_bank_name'];
                }
                $result = addBankAccount($bankName, $_POST['account_number'], $_POST['account_name']);
                if ($result['success']) {
                    $message = 'Bank account added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'update_ewallet':
                // Check if custom provider is selected
                $provider = $_POST['provider'];
                if ($provider === 'Other' && !empty($_POST['custom_provider'])) {
                    $provider = $_POST['custom_provider'];
                }
                $result = updateEWalletAccount($_POST['id'], $provider, $_POST['account_number'], $_POST['account_name']);
                if ($result['success']) {
                    $message = 'E-Wallet account updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'update_bank':
                // Check if custom bank name is selected
                $bankName = $_POST['bank_name'];
                if ($bankName === 'Other' && !empty($_POST['custom_bank_name'])) {
                    $bankName = $_POST['custom_bank_name'];
                }
                $result = updateBankAccount($_POST['id'], $bankName, $_POST['account_number'], $_POST['account_name']);
                if ($result['success']) {
                    $message = 'Bank account updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'delete_ewallet':
                $result = deleteEWalletAccount($_POST['id']);
                if ($result['success']) {
                    $message = 'E-Wallet account deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'delete_bank':
                $result = deleteBankAccount($_POST['id']);
                if ($result['success']) {
                    $message = 'Bank account deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'update_cash_setting':
                $result = updateCashSetting($_POST['setting_key'], $_POST['setting_value']);
                if ($result['success']) {
                    $message = 'Cash payment setting updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $result['message'];
                    $messageType = 'error';
                }
                break;
                
            case 'update_cash_settings':
                // Update multiple cash settings at once
                $settings = [
                    'office_address' => $_POST['office_address'],
                    'business_hours' => $_POST['business_hours'],
                    'contact_person' => $_POST['contact_person'],
                    'phone_number' => $_POST['phone_number'],
                    'additional_instructions' => $_POST['additional_instructions']
                ];
                
                $allSuccess = true;
                $errorMessages = [];
                
                foreach ($settings as $key => $value) {
                    $result = updateCashSetting($key, $value);
                    if (!$result['success']) {
                        $allSuccess = false;
                        $errorMessages[] = $result['message'];
                    }
                }
                
                if ($allSuccess) {
                    $message = 'Cash payment settings updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating cash settings: ' . implode(', ', $errorMessages);
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Determine which tab to show (default to accounts, but use current_tab if provided)
$currentTab = 'accounts';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_tab'])) {
    $currentTab = $_POST['current_tab'];
}

// Check and create missing tables
function checkAndCreateTables() {
    global $conn;
    $tablesCreated = [];
    
    try {
        // Check if ewallet_accounts table exists
        $checkEWallet = $conn->query("SHOW TABLES LIKE 'ewallet_accounts'");
        if (!$checkEWallet) {
            throw new Exception("Failed to check ewallet_accounts table: " . $conn->error);
        }
        if ($checkEWallet->num_rows == 0) {
            if (!$conn->query("CREATE TABLE ewallet_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                provider VARCHAR(50) NOT NULL,
                account_number VARCHAR(100) NOT NULL,
                account_name VARCHAR(100) NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_provider (provider),
                INDEX idx_is_active (is_active)
            )")) {
                throw new Exception("Failed to create ewallet_accounts table: " . $conn->error);
            }
            $tablesCreated[] = 'ewallet_accounts';
        }
        
        // Check if bank_accounts table exists
        $checkBank = $conn->query("SHOW TABLES LIKE 'bank_accounts'");
        if (!$checkBank) {
            throw new Exception("Failed to check bank_accounts table: " . $conn->error);
        }
        if ($checkBank->num_rows == 0) {
            if (!$conn->query("CREATE TABLE bank_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bank_name VARCHAR(100) NOT NULL,
                account_number VARCHAR(100) NOT NULL,
                account_name VARCHAR(100) NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bank_name (bank_name),
                INDEX idx_is_active (is_active)
            )")) {
                throw new Exception("Failed to create bank_accounts table: " . $conn->error);
            }
            $tablesCreated[] = 'bank_accounts';
        }
        
        // Check if cash_settings table exists
        $checkCash = $conn->query("SHOW TABLES LIKE 'cash_settings'");
        if (!$checkCash) {
            throw new Exception("Failed to check cash_settings table: " . $conn->error);
        }
        if ($checkCash->num_rows == 0) {
            if (!$conn->query("CREATE TABLE cash_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(50) NOT NULL UNIQUE,
                setting_value TEXT NOT NULL,
                setting_description VARCHAR(255),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_setting_key (setting_key),
                INDEX idx_is_active (is_active)
            )")) {
                throw new Exception("Failed to create cash_settings table: " . $conn->error);
            }
            
            // Insert default cash settings
            if (!$conn->query("INSERT INTO cash_settings (setting_key, setting_value, setting_description) VALUES
                ('office_address', 'Tisa, Labangon, Cebu City', 'Office address for cash payments'),
                ('business_hours', 'Monday-Friday, 8:00 AM - 5:00 PM', 'Business hours for cash payments'),
                ('contact_person', 'Administrative Office', 'Contact person for cash payments'),
                ('phone_number', '+63 XXX-XXX-XXXX', 'Contact phone number'),
                ('additional_instructions', 'Please bring a valid ID when making cash payments. Receipt will be provided upon payment.', 'Additional payment instructions')")) {
                error_log("Warning: Failed to insert default cash settings: " . $conn->error);
            }
            
            $tablesCreated[] = 'cash_settings';
        }
        
        // Set notification message if tables were created
        if (!empty($tablesCreated)) {
            $_SESSION['message'] = 'Database tables automatically created: ' . implode(', ', $tablesCreated);
            $_SESSION['messageType'] = 'info';
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating tables: " . $e->getMessage());
        $_SESSION['message'] = 'Database setup encountered an issue. Please check server logs.';
        $_SESSION['messageType'] = 'error';
        return false;
    }
}

// Create tables if they don't exist
checkAndCreateTables();

// Fetch all users for the manage accounts table
try {
    $users = getAllUsers();
    if (empty($users) && empty($message)) {
        $message = 'No users found. Please check your database connection.';
        $messageType = 'info';
    }
} catch (Exception $e) {
    $users = [];
    if (empty($message)) {
        $message = 'Database error: Unable to load user accounts. ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch payment methods data with error handling
try {
    $ewalletAccounts = getAllEWalletAccounts();
} catch (Exception $e) {
    $ewalletAccounts = [];
    error_log("E-Wallet accounts error: " . $e->getMessage());
    if (empty($message)) {
        $message = 'Error loading E-Wallet accounts: ' . $e->getMessage();
        $messageType = 'error';
    }
}

try {
    $bankAccounts = getAllBankAccounts();
} catch (Exception $e) {
    $bankAccounts = [];
    error_log("Bank accounts error: " . $e->getMessage());
    if (empty($message)) {
        $message = 'Error loading Bank accounts: ' . $e->getMessage();
        $messageType = 'error';
    }
}

try {
    $cashSettings = getAllCashSettings();
} catch (Exception $e) {
    $cashSettings = [];
    error_log("Cash settings error: " . $e->getMessage());
    if (empty($message)) {
        $message = 'Error loading Cash settings: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Helper functions for user display
function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function getRoleColor($role) {
    switch ($role) {
        case 'admin': return 'bg-purple-500';
        case 'tutor': return 'bg-blue-500';
        case 'student': return 'bg-tplearn-green';
        default: return 'bg-gray-500';
    }
}

function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin': return 'bg-purple-100 text-purple-800';
        case 'tutor': return 'bg-blue-100 text-blue-800';
        case 'student': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'inactive': return 'bg-red-100 text-red-800';
        case 'suspended': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Tools - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  
  <style>
    /* Custom styles */
    .stat-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .welcome-card {
      background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
      border-radius: 16px;
      position: relative;
      overflow: hidden;
    }

    .program-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
      transition: transform 0.2s ease;
    }

    .program-card:hover {
      transform: translateY(-2px);
    }

    .progress-bar {
      background: #f3f4f6;
      border-radius: 9999px;
      height: 8px;
      overflow: hidden;
    }

    .progress-fill {
      background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
      height: 100%;
      border-radius: 9999px;
      transition: width 0.3s ease;
    }

    /* Tab styles */
    .tab-active {
      border-bottom: 2px solid #10b981;
      color: #10b981;
    }

    .tab-inactive {
      color: #6b7280;
      border-bottom: 2px solid transparent;
    }

    .tab-inactive:hover {
      color: #374151;
    }

    /* Plan card styles */
    .plan-card {
      transition: all 0.2s ease;
    }

    .plan-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    /* Discount badge */
    .discount-badge {
      background: linear-gradient(45deg, #10b981, #34d399);
    }

    /* Form styles */
    .form-control {
      transition: all 0.2s ease;
    }

    .form-control:focus {
      border-color: #10b981;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    /* Button styles */
    .btn-primary {
      background: linear-gradient(45deg, #10b981, #34d399);
      transition: all 0.2s ease;
    }

    .btn-primary:hover {
      background: linear-gradient(45deg, #059669, #10b981);
      transform: translateY(-1px);
    }

    .btn-danger {
      background: linear-gradient(45deg, #ef4444, #f87171);
      transition: all 0.2s ease;
    }

    .btn-danger:hover {
      background: linear-gradient(45deg, #dc2626, #ef4444);
      transform: translateY(-1px);
    }

    /* Modal styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .modal-content {
      background: white;
      border-radius: 12px;
      padding: 24px;
      max-width: 500px;
      width: 90%;
      max-height: 90vh;
      transform: scale(0.9);
      transition: transform 0.3s ease;
    }

    .modal-overlay.active .modal-content {
      transform: scale(1);
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/admin-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="lg:ml-64 flex-1">
      <?php 
      require_once '../../includes/header.php';
      renderHeader(
        'Admin Tools',
        '',
        'admin',
        $_SESSION['name'] ?? 'Admin',
        [], // notifications array - to be implemented
        []  // messages array - to be implemented
      );
      ?>

      <!-- Admin Tools Content -->
      <main class="p-6">
        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
          <div id="alertMessage" class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
            <?php if ($messageType === 'success'): ?>
              <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?= htmlspecialchars($message) ?>
              </div>
            <?php elseif ($messageType === 'info'): ?>
              <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <?= htmlspecialchars($message) ?>
              </div>
            <?php else: ?>
              <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <?= htmlspecialchars($message) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
      <main class="p-6">
        <!-- Tab Navigation -->
        <div class="bg-white rounded-t-lg shadow-sm border border-gray-200 border-b-0">
          <div class="flex border-b border-gray-200">
            <button id="tab-accounts" class="px-6 py-3 text-sm font-medium tab-active" onclick="switchTab('accounts')">
              Manage Accounts
            </button>
            <button id="tab-reset-passwords" class="px-6 py-3 text-sm font-medium tab-inactive" onclick="switchTab('reset-passwords')">
              Reset Passwords
            </button>
            <button id="tab-payment-methods" class="px-6 py-3 text-sm font-medium tab-inactive" onclick="switchTab('payment-methods')">
              Payment Methods
            </button>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-b-lg shadow-sm border border-gray-200 border-t-0">

          <!-- Manage Accounts Tab -->
          <div id="content-accounts" class="p-6">
            <?php if ($message): ?>
              <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
                <?= htmlspecialchars($message) ?>
              </div>
            <?php endif; ?>
            
            <div class="flex justify-between items-center mb-6">
              <h2 class="text-lg font-semibold text-gray-800">Manage Accounts</h2>
              <button onclick="openModal('addAccountModal')" class="flex items-center px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                Add New Account
              </button>
            </div>

            <!-- Accounts Table -->
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (empty($users)): ?>
                    <tr>
                      <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                        No users found
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($users as $user): ?>
                      <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                              <div class="h-10 w-10 rounded-full <?= getRoleColor($user['role']) ?> flex items-center justify-center">
                                <span class="text-sm font-medium text-white"><?= getInitials($user['username']) ?></span>
                              </div>
                            </div>
                            <div class="ml-4">
                              <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['username']) ?></div>
                              <div class="text-sm text-gray-500">
                                Last login: <?= $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never' ?>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getRoleBadgeClass($user['role']) ?>">
                            <?= ucfirst($user['role']) ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getStatusBadgeClass($user['status']) ?>">
                            <?= ucfirst($user['status']) ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <div class="flex space-x-2">
                            <button onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= $user['role'] ?>', '<?= $user['status'] ?>')" class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                            <?php if ($user['status'] === 'active'): ?>
                              <button onclick="openDeactivateModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" class="text-red-600 hover:text-red-800 font-medium">Deactivate</button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Reset Passwords Tab -->
          <div id="content-reset-passwords" class="p-6 hidden">
            <div class="mb-6">
              <h2 class="text-lg font-semibold text-gray-800">Reset Student Passwords</h2>
            </div> <!-- Password Reset Options Section -->
            <div class="bg-gray-50 rounded-lg p-6 mb-6">
              <div class="flex items-center mb-4">
                <svg class="w-5 h-5 text-tplearn-green mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <h3 class="text-md font-semibold text-gray-800">Password Reset Options</h3>
              </div>

              <!-- Single Student Reset -->
              <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="current_tab" value="reset-passwords">
                <div class="mb-6">
                  <label for="student-email" class="block text-sm font-medium text-gray-700 mb-2">Student Email</label>
                  <input type="email" name="email" id="student-email" placeholder="Enter student's email" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>

                  <div class="mt-3">
                    <label class="flex items-center">
                      <input type="checkbox" name="send_email" class="form-checkbox h-4 w-4 text-tplearn-green">
                      <span class="ml-2 text-sm text-gray-600">Send password reset email to student</span>
                    </label>
                  </div>

                  <button type="submit" class="mt-4 px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
                    Reset Password
                  </button>
                </div>
              </form>
            </div>
          </div>

          <!-- Payment Methods Tab -->
          <div id="content-payment-methods" class="p-6 hidden">
            <div class="mb-6">
              <h2 class="text-lg font-semibold text-gray-800">Payment Methods Configuration</h2>
            </div>

            <!-- Cash Payment Settings Section -->
            <div class="bg-gray-50 rounded-lg p-6 mb-6">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                  <svg class="w-5 h-5 text-tplearn-green mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"></path>
                  </svg>
                  <h3 class="text-md font-semibold text-gray-800">Cash Payment Settings</h3>
                </div>
                <button onclick="editCashSettings()" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
                  Edit Cash Settings
                </button>
              </div>

              <div class="bg-white p-4 rounded border">
                <div class="space-y-3">
                  <div>
                    <p class="font-semibold text-gray-800">Office Address</p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($cashSettings['office_address']['setting_value'] ?? 'Tisa, Labangon, Cebu City') ?></p>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800">Business Hours</p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($cashSettings['business_hours']['setting_value'] ?? 'Monday-Friday, 8:00 AM - 5:00 PM') ?></p>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800">Contact Person</p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($cashSettings['contact_person']['setting_value'] ?? 'Administrative Office') ?></p>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800">Phone Number</p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($cashSettings['phone_number']['setting_value'] ?? '+63 XXX-XXX-XXXX') ?></p>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800">Additional Instructions</p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($cashSettings['additional_instructions']['setting_value'] ?? 'Please bring a valid ID when making cash payments. Receipt will be provided upon payment.') ?></p>
                  </div>
                </div>
              </div>
            </div>

            <!-- E-Wallet Accounts Section -->
            <div class="bg-gray-50 rounded-lg p-6 mb-6">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                  <svg class="w-5 h-5 text-tplearn-green mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                  </svg>
                  <h3 class="text-md font-semibold text-gray-800">E-Wallet Accounts</h3>
                </div>
                <button onclick="openModal('addEWalletModal')" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
                  Add E-Wallet Account
                </button>
              </div>

              <div class="space-y-3" id="ewalletAccounts">
                <!-- E-Wallet accounts from database -->
                <?php if (empty($ewalletAccounts)): ?>
                  <div class="bg-white p-4 rounded border text-center text-gray-500">
                    No E-Wallet accounts configured yet.
                  </div>
                <?php else: ?>
                  <?php foreach ($ewalletAccounts as $account): ?>
                    <div class="bg-white p-4 rounded border flex items-center justify-between">
                      <div>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($account['provider']) ?></p>
                        <p class="text-sm text-gray-600">Number: <?= htmlspecialchars($account['account_number']) ?></p>
                        <p class="text-sm text-gray-600">Name: <?= htmlspecialchars($account['account_name']) ?></p>
                      </div>
                      <div class="flex space-x-2">
                        <button onclick="editEWallet(<?= $account['id'] ?>, '<?= addslashes($account['provider']) ?>', '<?= addslashes($account['account_number']) ?>', '<?= addslashes($account['account_name']) ?>')" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">Edit</button>
                        <button onclick="deleteEWallet(<?= $account['id'] ?>)" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">Delete</button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Bank Transfer Accounts Section -->
            <div class="bg-gray-50 rounded-lg p-6">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                  <svg class="w-5 h-5 text-tplearn-green mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v8H4V6z"></path>
                    <path d="M6 8h8v2H6V8z"></path>
                  </svg>
                  <h3 class="text-md font-semibold text-gray-800">Bank Transfer Accounts</h3>
                </div>
                <button onclick="openModal('addBankModal')" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
                  Add Bank Account
                </button>
              </div>

              <div class="space-y-3" id="bankAccounts">
                <!-- Bank accounts from database -->
                <?php if (empty($bankAccounts)): ?>
                  <div class="bg-white p-4 rounded border text-center text-gray-500">
                    No Bank accounts configured yet.
                  </div>
                <?php else: ?>
                  <?php foreach ($bankAccounts as $account): ?>
                    <div class="bg-white p-4 rounded border flex items-center justify-between">
                      <div>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($account['bank_name']) ?></p>
                        <p class="text-sm text-gray-600">Account Number: <?= htmlspecialchars($account['account_number']) ?></p>
                        <p class="text-sm text-gray-600">Account Name: <?= htmlspecialchars($account['account_name']) ?></p>
                      </div>
                      <div class="flex space-x-2">
                        <button onclick="editBank(<?= $account['id'] ?>, '<?= addslashes($account['bank_name']) ?>', '<?= addslashes($account['account_number']) ?>', '<?= addslashes($account['account_name']) ?>')" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">Edit</button>
                        <button onclick="deleteBank(<?= $account['id'] ?>)" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">Delete</button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <!-- Modal Overlays -->

  <!-- Add New Account Modal -->
  <div id="addAccountModal" class="modal-overlay">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-800">Add New Account</h3>
        <button onclick="closeModal('addAccountModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      <form method="POST" id="addAccountForm">
        <input type="hidden" name="action" value="add_user">
        <input type="hidden" name="current_tab" value="accounts">
        <div class="space-y-4">
          <div>
            <label for="newUserName" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input type="text" name="name" id="newUserName" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
          <div>
            <label for="newUserEmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input type="email" name="email" id="newUserEmail" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
          <div>
            <label for="newUserRole" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select name="role" id="newUserRole" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
              <option value="">Select Role</option>
              <option value="admin">Administrator</option>
              <option value="tutor">Tutor</option>
              <option value="student">Student</option>
            </select>
          </div>
          <div>
            <label for="newUserPassword" class="block text-sm font-medium text-gray-700 mb-1">Temporary Password</label>
            <input type="password" name="password" id="newUserPassword" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button type="button" onclick="closeModal('addAccountModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
            Create Account
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div id="editUserModal" class="modal-overlay">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-800">Edit User Account</h3>
        <button onclick="closeModal('editUserModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      <form method="POST" id="editUserForm">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="user_id" id="editUserId">
        <input type="hidden" name="current_tab" value="accounts">
        <div class="space-y-4">
          <div>
            <label for="editUserName" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input type="text" name="name" id="editUserName" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
          <div>
            <label for="editUserEmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input type="email" name="email" id="editUserEmail" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
          <div>
            <label for="editUserRole" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select name="role" id="editUserRole" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
              <option value="admin">Administrator</option>
              <option value="tutor">Tutor</option>
              <option value="student">Student</option>
            </select>
          </div>
          <div>
            <label for="editUserStatus" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" id="editUserStatus" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button type="button" onclick="closeModal('editUserModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Deactivate User Modal -->
  <div id="deactivateUserModal" class="modal-overlay">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-red-800">Deactivate User Account</h3>
        <button onclick="closeModal('deactivateUserModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      <div class="mb-6">
        <div class="flex items-center mb-4">
          <svg class="w-12 h-12 text-red-500 mr-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
          </svg>
          <div>
            <h4 class="text-lg font-medium text-gray-900">Are you sure?</h4>
            <p class="text-sm text-gray-600">You are about to deactivate <span id="deactivateUserName" class="font-medium"></span>. This action will:</p>
          </div>
        </div>
        <ul class="text-sm text-gray-600 space-y-1 ml-16">
          <li>• Prevent the user from logging in</li>
          <li>• Remove access to all programs and content</li>
          <li>• Send a notification email to the user</li>
          <li>• Keep all user data for potential reactivation</li>
        </ul>
      </div>
      <form method="POST" id="deactivateUserForm">
        <input type="hidden" name="action" value="deactivate_user">
        <input type="hidden" name="user_id" id="deactivateUserId">
        <input type="hidden" name="current_tab" value="accounts">
      </form>
      <div class="flex justify-end space-x-3">
        <button type="button" onclick="closeModal('deactivateUserModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="button" onclick="document.getElementById('deactivateUserForm').submit()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors btn-danger">
          Deactivate Account
        </button>
      </div>
    </div>
  </div>

  <!-- Reset Password Confirmation Modal -->
  <div id="resetPasswordModal" class="modal-overlay">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-800">Password Reset Confirmation</h3>
        <button onclick="closeModal('resetPasswordModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      <div class="mb-6">
        <div class="flex items-center mb-4">
          <svg class="w-12 h-12 text-green-500 mr-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
          </svg>
          <div>
            <h4 class="text-lg font-medium text-gray-900">Password Reset Successful</h4>
            <p class="text-sm text-gray-600">A new password has been generated and sent to the student's email address.</p>
          </div>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-gray-700"><strong>Email:</strong> <span id="resetEmailAddress"></span></p>
          <p class="text-sm text-gray-700 mt-1"><strong>New Password:</strong> <span id="newPassword" class="font-mono bg-white px-2 py-1 rounded border"></span></p>
        </div>
      </div>
      <div class="flex justify-end">
        <button type="button" onclick="closeModal('resetPasswordModal')" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- Add E-Wallet Account Modal -->
  <div id="addEWalletModal" class="modal-overlay">
    <div class="modal-content max-h-[90vh] flex flex-col">
      <!-- Fixed Header -->
      <div class="flex justify-between items-center mb-6 flex-shrink-0">
        <h3 class="text-lg font-semibold text-gray-800">Add E-Wallet Account</h3>
        <button onclick="closeModal('addEWalletModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      
      <!-- Scrollable Content -->
      <div class="flex-1 overflow-y-auto pr-2">
        <form id="addEWalletForm" method="POST">
          <input type="hidden" name="action" value="add_ewallet">
          <input type="hidden" name="current_tab" value="payment-methods">
          <div class="space-y-4">
            <div>
              <label for="ewalletProvider" class="block text-sm font-medium text-gray-700 mb-1">E-Wallet Provider</label>
              <select name="provider" id="ewalletProvider" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required onchange="toggleCustomEWalletProvider()">
                <option value="">Select Provider</option>
                <option value="GCash">GCash</option>
                <option value="Maya">Maya</option>
                <option value="GrabPay">GrabPay</option>
                <option value="ShopeePay">ShopeePay</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div id="customEWalletProvider" class="hidden">
              <label for="customEWalletName" class="block text-sm font-medium text-gray-700 mb-1">Custom Provider Name</label>
              <input type="text" name="custom_provider" id="customEWalletName" placeholder="e.g., Coins.ph, UnionBank Online" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none">
            </div>
            <div>
              <label for="ewalletNumber" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
              <input type="text" name="account_number" id="ewalletNumber" placeholder="e.g., 0917-123-4567" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
            </div>
            <div>
              <label for="ewalletAccountName" class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
              <input type="text" name="account_name" id="ewalletAccountName" placeholder="e.g., Tisa at Pagara" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
            </div>
          </div>
        </form>
      </div>
      
      <!-- Fixed Footer -->
      <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200 flex-shrink-0">
        <button type="button" onclick="closeModal('addEWalletModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="submit" form="addEWalletForm" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
          Add E-Wallet Account
        </button>
      </div>
    </div>
  </div>

  <!-- Add Bank Account Modal -->
  <div id="addBankModal" class="modal-overlay">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-800">Add Bank Account</h3>
        <button onclick="closeModal('addBankModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      <form id="addBankForm" method="POST">
        <input type="hidden" name="action" value="add_bank">
        <input type="hidden" name="current_tab" value="payment-methods">
        <div class="space-y-4">
          <div>
            <label for="bankName" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
            <select name="bank_name" id="bankName" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required onchange="toggleCustomBankName()">
              <option value="">Select Bank</option>
              <option value="BPI">Bank of the Philippine Islands (BPI)</option>
              <option value="BDO">Banco de Oro (BDO)</option>
              <option value="Metrobank">Metropolitan Bank (Metrobank)</option>
              <option value="PNB">Philippine National Bank (PNB)</option>
              <option value="UnionBank">Union Bank of the Philippines</option>
              <option value="Security Bank">Security Bank</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div id="customBankName" class="hidden">
            <label for="customBankNameInput" class="block text-sm font-medium text-gray-700 mb-1">Custom Bank Name</label>
            <input type="text" name="custom_bank_name" id="customBankNameInput" placeholder="e.g., Landbank, RCBC, Chinabank" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none">
          </div>
          <div>
            <label for="bankAccountNumber" class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
            <input type="text" name="account_number" id="bankAccountNumber" placeholder="e.g., 1234-5678-90" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
          <div>
            <label for="bankAccountName" class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
            <input type="text" name="account_name" id="bankAccountName" placeholder="e.g., Tisa at Pagara Academic Services" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button type="button" onclick="closeModal('addBankModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
            Add Bank Account
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit E-Wallet Account Modal -->
  <div id="editEWalletModal" class="modal-overlay">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-800">Edit E-Wallet Account</h3>
        <button onclick="closeModal('editEWalletModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      <form id="editEWalletForm" method="POST">
        <input type="hidden" name="action" value="update_ewallet">
        <input type="hidden" name="id" id="editEWalletId">
        <input type="hidden" name="current_tab" value="payment-methods">
        <div class="space-y-4">
          <div>
            <label for="editEWalletProvider" class="block text-sm font-medium text-gray-700 mb-1">E-Wallet Provider</label>
            <select name="provider" id="editEWalletProvider" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required onchange="toggleEditCustomEWalletProvider()">
              <option value="">Select Provider</option>
              <option value="GCash">GCash</option>
              <option value="Maya">Maya</option>
              <option value="GrabPay">GrabPay</option>
              <option value="ShopeePay">ShopeePay</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div id="editCustomEWalletProvider" class="hidden">
            <label for="editCustomEWalletName" class="block text-sm font-medium text-gray-700 mb-1">Custom Provider Name</label>
            <input type="text" name="custom_provider" id="editCustomEWalletName" placeholder="e.g., Coins.ph, UnionBank Online" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none">
          </div>
          <div>
            <label for="editEWalletNumber" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
            <input type="text" name="account_number" id="editEWalletNumber" placeholder="e.g., 0917-123-4567" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
          <div>
            <label for="editEWalletAccountName" class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
            <input type="text" name="account_name" id="editEWalletAccountName" placeholder="e.g., Tisa at Pagara" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button type="button" onclick="closeModal('editEWalletModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            Update E-Wallet Account
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Bank Account Modal -->
  <div id="editBankModal" class="modal-overlay">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-800">Edit Bank Account</h3>
        <button onclick="closeModal('editBankModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      <form id="editBankForm" method="POST">
        <input type="hidden" name="action" value="update_bank">
        <input type="hidden" name="id" id="editBankId">
        <input type="hidden" name="current_tab" value="payment-methods">
        <div class="space-y-4">
          <div>
            <label for="editBankName" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
            <select name="bank_name" id="editBankName" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required onchange="toggleEditCustomBankName()">
              <option value="">Select Bank</option>
              <option value="BPI">Bank of the Philippine Islands (BPI)</option>
              <option value="BDO">Banco de Oro (BDO)</option>
              <option value="Metrobank">Metropolitan Bank (Metrobank)</option>
              <option value="PNB">Philippine National Bank (PNB)</option>
              <option value="UnionBank">Union Bank of the Philippines</option>
              <option value="Security Bank">Security Bank</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div id="editCustomBankName" class="hidden">
            <label for="editCustomBankNameInput" class="block text-sm font-medium text-gray-700 mb-1">Custom Bank Name</label>
            <input type="text" name="custom_bank_name" id="editCustomBankNameInput" placeholder="e.g., Landbank, RCBC, Chinabank" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none">
          </div>
          <div>
            <label for="editBankAccountNumber" class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
            <input type="text" name="account_number" id="editBankAccountNumber" placeholder="e.g., 1234-5678-90" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
          <div>
            <label for="editBankAccountName" class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
            <input type="text" name="account_name" id="editBankAccountName" placeholder="e.g., Tisa at Pagara Academic Services" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button type="button" onclick="closeModal('editBankModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            Update Bank Account
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Cash Settings Modal -->
  <div id="editCashModal" class="modal-overlay">
    <div class="modal-content max-h-[90vh] flex flex-col">
      <!-- Fixed Header -->
      <div class="flex justify-between items-center mb-6 flex-shrink-0">
        <h3 class="text-lg font-semibold text-gray-800">Edit Cash Payment Settings</h3>
        <button onclick="closeModal('editCashModal')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
      
      <!-- Scrollable Content -->
      <div class="flex-1 overflow-y-auto pr-2">
        <form id="editCashForm" method="POST">
          <input type="hidden" name="action" value="update_cash_settings">
          <input type="hidden" name="current_tab" value="payment-methods">
          <div class="space-y-4">
            <div>
              <label for="cashOfficeAddress" class="block text-sm font-medium text-gray-700 mb-1">Office Address</label>
              <input type="text" name="office_address" id="cashOfficeAddress" value="<?= htmlspecialchars($cashSettings['office_address']['setting_value'] ?? 'Tisa, Labangon, Cebu City') ?>" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
            </div>
            <div>
              <label for="cashBusinessHours" class="block text-sm font-medium text-gray-700 mb-1">Business Hours</label>
              <input type="text" name="business_hours" id="cashBusinessHours" value="<?= htmlspecialchars($cashSettings['business_hours']['setting_value'] ?? 'Monday-Friday, 8:00 AM - 5:00 PM') ?>" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
            </div>
            <div>
              <label for="cashContactPerson" class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
              <input type="text" name="contact_person" id="cashContactPerson" value="<?= htmlspecialchars($cashSettings['contact_person']['setting_value'] ?? 'Administrative Office') ?>" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
            </div>
            <div>
              <label for="cashPhoneNumber" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
              <input type="text" name="phone_number" id="cashPhoneNumber" value="<?= htmlspecialchars($cashSettings['phone_number']['setting_value'] ?? '+63 XXX-XXX-XXXX') ?>" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required>
            </div>
            <div>
              <label for="cashAdditionalInstructions" class="block text-sm font-medium text-gray-700 mb-1">Additional Instructions</label>
              <textarea name="additional_instructions" id="cashAdditionalInstructions" rows="3" class="form-control w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none" required><?= htmlspecialchars($cashSettings['additional_instructions']['setting_value'] ?? 'Please bring a valid ID when making cash payments. Receipt will be provided upon payment.') ?></textarea>
            </div>
          </div>
        </form>
      </div>
      
      <!-- Fixed Footer -->
      <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200 flex-shrink-0">
        <button type="button" onclick="closeModal('editCashModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="submit" form="editCashForm" class="px-4 py-2 bg-tplearn-green text-white rounded-lg hover:bg-green-600 transition-colors btn-primary">
          Update Settings
        </button>
      </div>
    </div>
  </div>

  <!-- Include mobile menu JavaScript -->
  <script src="../../assets/admin-sidebar.js"></script>

  <!-- Tab Switching JavaScript -->
  <script>
    function switchTab(tabName) {
      // Hide all tab contents
      document.getElementById('content-accounts').classList.add('hidden');
      document.getElementById('content-reset-passwords').classList.add('hidden');
      document.getElementById('content-payment-methods').classList.add('hidden');

      // Remove active class from all tabs
      document.getElementById('tab-accounts').className = 'px-6 py-3 text-sm font-medium tab-inactive';
      document.getElementById('tab-reset-passwords').className = 'px-6 py-3 text-sm font-medium tab-inactive';
      document.getElementById('tab-payment-methods').className = 'px-6 py-3 text-sm font-medium tab-inactive';

      // Show selected tab content and mark tab as active
      if (tabName === 'accounts') {
        document.getElementById('content-accounts').classList.remove('hidden');
        document.getElementById('tab-accounts').className = 'px-6 py-3 text-sm font-medium tab-active';
      } else if (tabName === 'reset-passwords') {
        document.getElementById('content-reset-passwords').classList.remove('hidden');
        document.getElementById('tab-reset-passwords').className = 'px-6 py-3 text-sm font-medium tab-active';
      } else if (tabName === 'payment-methods') {
        document.getElementById('content-payment-methods').classList.remove('hidden');
        document.getElementById('tab-payment-methods').className = 'px-6 py-3 text-sm font-medium tab-active';
      }
    }

    // Modal Functions
    function openModal(modalId) {
      document.getElementById(modalId).classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.remove('active');
      document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
      if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
        document.body.style.overflow = 'auto';
      }
    });

    // Account Management Functions
    function openEditModal(id, name, email, role, status) {
      document.getElementById('editUserId').value = id;
      document.getElementById('editUserName').value = name;
      document.getElementById('editUserEmail').value = email;
      document.getElementById('editUserRole').value = role;
      document.getElementById('editUserStatus').value = status;
      openModal('editUserModal');
    }

    function openDeactivateModal(id, userName) {
      document.getElementById('deactivateUserId').value = id;
      document.getElementById('deactivateUserName').textContent = userName;
      openModal('deactivateUserModal');
    }



    // Password Reset Functions
    function resetSinglePassword() {
      const email = document.getElementById('student-email').value;
      if (!email) {
        alert('Please enter a student email address.');
        return;
      }

      // Generate random password for demo
      const newPassword = generateRandomPassword();
      document.getElementById('resetEmailAddress').textContent = email;
      document.getElementById('newPassword').textContent = newPassword;

      // Clear the form
      document.getElementById('student-email').value = '';

      openModal('resetPasswordModal');
    }

    // Utility Functions
    function generateRandomPassword() {
      const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
      let result = '';
      for (let i = 0; i < 8; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      return result;
    }

    // Toggle custom provider input fields
    function toggleCustomEWalletProvider() {
      const select = document.getElementById('ewalletProvider');
      const customDiv = document.getElementById('customEWalletProvider');
      const customInput = document.getElementById('customEWalletName');
      
      if (select.value === 'Other') {
        customDiv.classList.remove('hidden');
        customInput.required = true;
      } else {
        customDiv.classList.add('hidden');
        customInput.required = false;
        customInput.value = '';
      }
    }

    function toggleCustomBankName() {
      const select = document.getElementById('bankName');
      const customDiv = document.getElementById('customBankName');
      const customInput = document.getElementById('customBankNameInput');
      
      if (select.value === 'Other') {
        customDiv.classList.remove('hidden');
        customInput.required = true;
      } else {
        customDiv.classList.add('hidden');
        customInput.required = false;
        customInput.value = '';
      }
    }

    // Edit modal toggle functions
    function toggleEditCustomEWalletProvider() {
      const select = document.getElementById('editEWalletProvider');
      const customDiv = document.getElementById('editCustomEWalletProvider');
      const customInput = document.getElementById('editCustomEWalletName');
      
      if (select.value === 'Other') {
        customDiv.classList.remove('hidden');
        customInput.required = true;
      } else {
        customDiv.classList.add('hidden');
        customInput.required = false;
        customInput.value = '';
      }
    }

    function toggleEditCustomBankName() {
      const select = document.getElementById('editBankName');
      const customDiv = document.getElementById('editCustomBankName');
      const customInput = document.getElementById('editCustomBankNameInput');
      
      if (select.value === 'Other') {
        customDiv.classList.remove('hidden');
        customInput.required = true;
      } else {
        customDiv.classList.add('hidden');
        customInput.required = false;
        customInput.value = '';
      }
    }

    // Payment Methods Functions
    function editEWallet(id, provider, accountNumber, accountName) {
      // Populate the edit form
      document.getElementById('editEWalletId').value = id;
      document.getElementById('editEWalletNumber').value = accountNumber;
      document.getElementById('editEWalletAccountName').value = accountName;
      
      // Handle provider selection - check if it's a predefined option or custom
      const providerSelect = document.getElementById('editEWalletProvider');
      const customDiv = document.getElementById('editCustomEWalletProvider');
      const customInput = document.getElementById('editCustomEWalletName');
      
      // Check if provider is in predefined options
      const predefinedOptions = ['GCash', 'Maya', 'GrabPay', 'ShopeePay'];
      if (predefinedOptions.includes(provider)) {
        providerSelect.value = provider;
        customDiv.classList.add('hidden');
        customInput.required = false;
      } else {
        providerSelect.value = 'Other';
        customDiv.classList.remove('hidden');
        customInput.value = provider;
        customInput.required = true;
      }
      
      // Open the modal
      openModal('editEWalletModal');
    }

    function deleteEWallet(id) {
      if (confirm('Are you sure you want to delete this E-Wallet account?')) {
        // Create form to submit delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="delete_ewallet">
          <input type="hidden" name="id" value="${id}">
          <input type="hidden" name="current_tab" value="payment-methods">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    function editBank(id, bankName, accountNumber, accountName) {
      // Populate the edit form
      document.getElementById('editBankId').value = id;
      document.getElementById('editBankAccountNumber').value = accountNumber;
      document.getElementById('editBankAccountName').value = accountName;
      
      // Handle bank name selection - check if it's a predefined option or custom
      const bankSelect = document.getElementById('editBankName');
      const customDiv = document.getElementById('editCustomBankName');
      const customInput = document.getElementById('editCustomBankNameInput');
      
      // Check if bank name is in predefined options
      const predefinedBanks = ['BPI', 'BDO', 'Metrobank', 'PNB', 'UnionBank', 'Security Bank'];
      if (predefinedBanks.includes(bankName)) {
        bankSelect.value = bankName;
        customDiv.classList.add('hidden');
        customInput.required = false;
      } else {
        bankSelect.value = 'Other';
        customDiv.classList.remove('hidden');
        customInput.value = bankName;
        customInput.required = true;
      }
      
      // Open the modal
      openModal('editBankModal');
    }

    function deleteBank(id) {
      if (confirm('Are you sure you want to delete this Bank account?')) {
        // Create form to submit delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="delete_bank">
          <input type="hidden" name="id" value="${id}">
          <input type="hidden" name="current_tab" value="payment-methods">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    // Cash Settings Functions
    function editCashSettings() {
      openModal('editCashModal');
    }

    // Initialize with appropriate tab based on PHP variable
    document.addEventListener('DOMContentLoaded', function() {
      switchTab('<?= $currentTab ?>');
      
      // Auto-dismiss alert messages after 2 seconds
      const alertMessage = document.getElementById('alertMessage');
      if (alertMessage) {
        setTimeout(function() {
          alertMessage.style.opacity = '0';
          alertMessage.style.transition = 'opacity 0.5s ease-out';
          setTimeout(function() {
            alertMessage.remove();
          }, 500);
        }, 2000);
      }
    });
  </script>
</body>

</html>