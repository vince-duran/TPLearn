<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/data-helpers.php';
require_once '../../assets/icons.php';
requireRole('student');

// Get program ID and payment option from URL parameters
$program_id = $_GET['program_id'] ?? $_SESSION['enrollment_program']['id'] ?? null;
$payment_option = $_GET['option'] ?? $_SESSION['payment_option'] ?? '1';

if (!$program_id) {
  $_SESSION['error_message'] = "Invalid enrollment session. Please start again.";
  header('Location: student-enrollment.php');
  exit();
}

// Get program data from database or session
if (isset($_SESSION['enrollment_program']) && $_SESSION['enrollment_program']['id'] == $program_id) {
  $program = $_SESSION['enrollment_program'];
} else {
  // Fetch from database
  $program_sql = "
        SELECT p.*, 
               COUNT(e.id) as enrolled_count,
               CASE 
                   WHEN p.start_date > CURDATE() THEN 'upcoming'
                   WHEN p.start_date <= CURDATE() AND (p.end_date IS NULL OR p.end_date >= CURDATE()) THEN 'ongoing'
                   WHEN p.end_date < CURDATE() THEN 'ended'
                   ELSE 'upcoming'
               END as calculated_status
        FROM programs p
        LEFT JOIN enrollments e ON p.id = e.program_id AND e.status IN ('pending', 'active')
        WHERE p.id = ? AND p.status = 'active'
        GROUP BY p.id
    ";

  $stmt = $conn->prepare($program_sql);
  $stmt->bind_param("i", $program_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $program = $result->fetch_assoc();

  if (!$program) {
    $_SESSION['error_message'] = "Program not found.";
    header('Location: student-enrollment.php');
    exit();
  }
}

// Store payment option in session
$_SESSION['payment_option'] = $payment_option;

// Calculate payment amount based on selected option
$total_fee = (float)$program['fee'];
$payment_amount = $total_fee;
$payment_description = "Full Payment";

if ($payment_option == '2') {
  $payment_amount = $total_fee / 2;
  $payment_description = "First Payment (2 payments total)";
  $balance_due = $total_fee / 2;
} elseif ($payment_option == '3') {
  $payment_amount = $total_fee / 3;
  $payment_description = "First Payment (3 payments total)";
  $balance_due = ($total_fee / 3) * 2;
} else {
  $balance_due = 0;
}

// Fetch payment methods from database
$ewalletAccounts = getAllEWalletAccounts();
$bankAccounts = getAllBankAccounts();
$cashSettings = getCashPaymentInstructions();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Method - <?php echo htmlspecialchars($program['name']); ?> - TPLearn</title>
  <link rel="stylesheet" href="../../assets/tailwind.min.css?v=<?= filemtime(__DIR__ . '/../../assets/tailwind.min.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* Custom styles */
    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 14px;
    }

    .step-active {
      background-color: #10b981;
      color: white;
    }

    .step-completed {
      background-color: #10b981;
      color: white;
    }

    .step-inactive {
      background-color: #e5e7eb;
      color: #6b7280;
    }

    .step-line {
      height: 2px;
      flex: 1;
      margin: 0 1rem;
    }

    .step-line-active {
      background-color: #10b981;
    }

    .step-line-inactive {
      background-color: #e5e7eb;
    }

    .payment-method {
      cursor: pointer;
      transition: all 0.2s ease;
      border: 2px solid #e5e7eb;
    }

    .payment-method:hover {
      border-color: #10b981;
      background-color: #f0fdf4;
    }

    .payment-method.selected {
      border-color: #10b981;
      background-color: #f0fdf4;
    }

    .upload-area {
      border: 2px dashed #d1d5db;
      transition: all 0.3s ease;
    }

    .upload-area:hover {
      border-color: #10b981;
      background-color: #f0fdf4;
    }

    .upload-area.dragover {
      border-color: #10b981;
      background-color: #f0fdf4;
    }

    .balance-input {
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      padding: 0.5rem 0.75rem;
      transition: border-color 0.2s ease;
    }

    .balance-input:focus {
      outline: none;
      border-color: #10b981;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen">
  <!-- Main Container -->
  <div class="flex">

    <?php include '../../includes/student-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1">
      <!-- Top Header -->
      <header class="bg-white shadow-sm border-b border-gray-200 px-4 lg:px-6 py-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <!-- Mobile menu button -->
            <button id="mobile-menu-button" class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-tplearn-green mr-3">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <div>
              <h1 class="text-xl lg:text-2xl font-bold text-gray-800">Enrollment</h1>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Notifications -->
            <div class="relative">
              <button onclick="openNotifications()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
                </svg>
              </button>
              <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">1</span>
            </div>

            <!-- Messages -->
            <div class="relative">
              <button onclick="openMessages()" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                  <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                </svg>
              </button>
              <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
            </div>

            <!-- Profile -->
            <div class="flex items-center space-x-2">
              <span class="text-sm font-medium text-gray-700">Maria Santos</span>
              <div class="w-8 h-8 bg-tplearn-green rounded-full flex items-center justify-center text-white font-semibold text-sm">
                M
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content Area -->
      <main class="p-6 max-w-4xl mx-auto">
        <!-- Progress Steps -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-6">
          <div class="flex items-center justify-between">
            <!-- Step 1 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-completed">1</div>
              <span class="text-xs mt-2 text-center font-medium text-gray-700">Program Details</span>
            </div>

            <!-- Line 1 -->
            <div class="step-line step-line-active"></div>

            <!-- Step 2 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-completed">2</div>
              <span class="text-xs mt-2 text-center font-medium text-gray-700">Payment Options</span>
            </div>

            <!-- Line 2 -->
            <div class="step-line step-line-active"></div>

            <!-- Step 3 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-active">3</div>
              <span class="text-xs mt-2 text-center font-medium text-tplearn-green">Payment Method</span>
            </div>

            <!-- Line 3 -->
            <div class="step-line step-line-inactive"></div>

            <!-- Step 4 -->
            <div class="flex flex-col items-center">
              <div class="step-circle step-inactive">4</div>
              <span class="text-xs mt-2 text-center font-medium text-gray-500">Confirmation</span>
            </div>
          </div>
        </div>

        <!-- Payment Method -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-2">Payment Method</h2>
          <p class="text-gray-600 mb-2">Program: <strong><?php echo htmlspecialchars($program['name']); ?></strong></p>
          <p class="text-gray-600 mb-6">Payment: <strong><?php echo $payment_description; ?></strong></p>

          <!-- Payment Method Selection -->
          <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Method</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Cash -->
              <div class="payment-method rounded-lg p-4 text-center" onclick="selectPaymentMethod('cash')">
                <div class="text-gray-600 mb-2">
                  <svg class="w-8 h-8 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"></path>
                  </svg>
                </div>
                <span class="text-sm font-medium text-gray-700">Cash</span>
              </div>

              <!-- E-Wallet -->
              <div class="payment-method selected rounded-lg p-4 text-center" onclick="selectPaymentMethod('ewallet')">
                <div class="text-gray-600 mb-2">
                  <svg class="w-8 h-8 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                  </svg>
                </div>
                <span class="text-sm font-medium text-gray-700">E-Wallet</span>
              </div>

              <!-- Bank Transfer -->
              <div class="payment-method rounded-lg p-4 text-center" onclick="selectPaymentMethod('bank')">
                <div class="text-gray-600 mb-2">
                  <svg class="w-8 h-8 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v8H4V6z"></path>
                    <path d="M6 8h8v2H6V8z"></path>
                  </svg>
                </div>
                <span class="text-sm font-medium text-gray-700">Bank Transfer</span>
              </div>
            </div>
          </div>

          <!-- Payment Instructions -->
          <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Instructions</h3>
            <div class="bg-gray-50 rounded-lg p-4" id="payment-instructions">
              <p class="text-gray-700 mb-2"><strong>Send your payment to:</strong></p>
              <div id="dynamic-payment-info">
                <!-- Payment info will be populated by JavaScript based on selected method -->
              </div>
            </div>
          </div>

          <!-- Amount Due -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Balance Due (₱)</label>
              <input type="text" value="<?php echo number_format($payment_amount, 0, '.', ''); ?>" readonly class="balance-input w-full bg-gray-50" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Balance (₱)</label>
              <input type="text" value="<?php echo number_format($balance_due, 0, '.', ''); ?>" readonly class="balance-input w-full bg-gray-50" />
            </div>
          </div>

          <!-- Reference Number -->
          <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Reference Number</h3>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number *</label>
              <input
                type="text"
                id="referenceNumber"
                name="reference_number"
                placeholder="Enter your payment reference number (e.g., GC-20240924-1234)"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-tplearn-green focus:border-tplearn-green"
                required />
              <p class="text-xs text-gray-500 mt-1">
                Enter the reference number from your payment transaction (e.g., from E-Wallet or bank transfer)
              </p>
            </div>
          </div>

          <!-- Upload Payment Receipt -->
          <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Upload Payment Receipt</h3>

            <div class="upload-area rounded-lg p-8 text-center" id="uploadArea">
              <div class="text-gray-400 mb-4">
                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
              </div>
              <p class="text-gray-600 mb-2">
                <button onclick="document.getElementById('fileInput').click()" class="text-tplearn-green hover:text-green-700 font-medium">Upload a file</button>
                or drag and drop
              </p>
              <p class="text-sm text-gray-500">PNG, JPG, PDF up to 10MB</p>
              <input type="file" id="fileInput" class="hidden" accept=".png,.jpg,.jpeg,.pdf" onchange="handleFileSelect(event)">
            </div>

            <!-- Selected File Display -->
            <div id="selectedFile" class="hidden mt-4 p-4 bg-green-50 rounded-lg border border-green-200">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <svg class="w-8 h-8 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                  <div>
                    <p class="text-sm font-medium text-green-800" id="fileName"></p>
                    <p class="text-xs text-green-600" id="fileSize"></p>
                  </div>
                </div>
                <button onclick="removeFile()" class="text-red-500 hover:text-red-700">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-between items-center pt-6 border-t border-gray-200">
            <button onclick="goBack()" class="flex items-center px-6 py-3 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
              </svg>
              Back
            </button>

            <button id="submitBtn" onclick="submitPayment()" disabled class="flex items-center px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed transition-all duration-200">
              Submit Payment
              <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script>
    // Payment method data from database
    const ewalletAccounts = <?php echo json_encode($ewalletAccounts); ?>;
    const bankAccounts = <?php echo json_encode($bankAccounts); ?>;
    const cashSettings = <?php echo json_encode($cashSettings); ?>;
    
    let selectedPaymentMethod = 'ewallet';
    let uploadedFile = null;

    function selectPaymentMethod(method) {
      // Remove previous selection
      document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('selected');
      });

      // Add selection to clicked method
      event.currentTarget.classList.add('selected');
      selectedPaymentMethod = method;

      // Update payment instructions based on method
      updatePaymentInstructions(method);
    }

    function updatePaymentInstructions(method) {
      const instructionsDiv = document.getElementById('dynamic-payment-info');
      let instructions = '';

      switch (method) {
        case 'cash':
          instructions = `
            <p class="text-gray-700 mb-2"><strong>Cash Payment:</strong></p>
            <p class="text-gray-700 mb-1">Visit our office at ${cashSettings.address}</p>
            <p class="text-gray-700 mb-1">Office Hours: ${cashSettings.hours}</p>
            ${cashSettings.contact_person ? `<p class="text-gray-700 mb-1">Contact: ${cashSettings.contact_person}</p>` : ''}
            ${cashSettings.phone_number ? `<p class="text-gray-700 mb-1">Phone: ${cashSettings.phone_number}</p>` : ''}
            ${cashSettings.additional_instructions ? `<p class="text-gray-700 mt-2 text-sm">${cashSettings.additional_instructions}</p>` : ''}
          `;
          break;
        case 'ewallet':
          instructions = `
            <p class="text-gray-700 mb-2"><strong>E-Wallet Payment Options:</strong></p>
            <div class="space-y-3">
          `;
          
          // Generate instructions from database data
          ewalletAccounts.forEach(account => {
            instructions += `
              <div class="bg-white p-3 rounded border">
                <p class="text-gray-700 font-semibold">${account.provider}</p>
                <p class="text-gray-700 mb-1"><strong>Number:</strong> ${account.account_number}</p>
                <p class="text-gray-700"><strong>Account Name:</strong> ${account.account_name}</p>
              </div>
            `;
          });
          
          instructions += `</div>`;
          break;
        case 'bank':
          instructions = `
            <p class="text-gray-700 mb-2"><strong>Bank Transfer Options:</strong></p>
            <div class="space-y-3">
          `;
          
          // Generate instructions from database data
          bankAccounts.forEach(account => {
            instructions += `
              <div class="bg-white p-3 rounded border">
                <p class="text-gray-700 font-semibold">${account.bank_name}</p>
                <p class="text-gray-700 mb-1"><strong>Account Number:</strong> ${account.account_number}</p>
                <p class="text-gray-700"><strong>Account Name:</strong> ${account.account_name}</p>
              </div>
            `;
          });
          
          instructions += `</div>`;
          break;
      }

      instructionsDiv.innerHTML = instructions;
    }

    function handleFileSelect(event) {
      const file = event.target.files[0];
      if (file) {
        uploadedFile = file;

        // Show selected file
        document.getElementById('selectedFile').classList.remove('hidden');
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatFileSize(file.size);

        // Enable submit button
        updateSubmitButton();
      }
    }

    function removeFile() {
      uploadedFile = null;
      document.getElementById('selectedFile').classList.add('hidden');
      document.getElementById('fileInput').value = '';
      updateSubmitButton();
    }

    function updateSubmitButton() {
      const submitBtn = document.getElementById('submitBtn');
      const referenceNumber = document.getElementById('referenceNumber').value.trim();

      if (uploadedFile && selectedPaymentMethod && referenceNumber) {
        submitBtn.disabled = false;
        submitBtn.className = 'flex items-center px-6 py-3 bg-tplearn-green text-white rounded-lg hover:bg-green-700 transition-all duration-200';
      } else {
        submitBtn.disabled = true;
        submitBtn.className = 'flex items-center px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed transition-all duration-200';
      }
    }

    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function goBack() {
      window.location.href = `enrollment-process.php?program_id=<?php echo $program['id']; ?>`;
    }

    function submitPayment() {
      const referenceNumber = document.getElementById('referenceNumber').value.trim();

      if (uploadedFile && selectedPaymentMethod && referenceNumber) {
        // Create a form to submit the file to the confirmation page
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `enrollment-confirmation.php?program_id=<?php echo $program['id']; ?>&option=<?php echo $payment_option; ?>&method=${selectedPaymentMethod}&reference=${encodeURIComponent(referenceNumber)}`;
        form.enctype = 'multipart/form-data';
        
        // Add the file input
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'payment_receipt';
        fileInput.files = document.getElementById('fileInput').files;
        form.appendChild(fileInput);
        
        // Add the confirm_enrollment field that the confirmation page expects
        const confirmInput = document.createElement('input');
        confirmInput.type = 'hidden';
        confirmInput.name = 'confirm_enrollment';
        confirmInput.value = '1';
        form.appendChild(confirmInput);
        
        // Add method flag for debugging
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = 'payment_data_submitted';
        methodInput.value = '1';
        form.appendChild(methodInput);
        
        // Submit the form
        document.body.appendChild(form);
        form.submit();
      }
    }

    // Drag and drop functionality
    const uploadArea = document.getElementById('uploadArea');

    uploadArea.addEventListener('dragover', function(e) {
      e.preventDefault();
      uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
      e.preventDefault();
      uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
      e.preventDefault();
      uploadArea.classList.remove('dragover');

      const files = e.dataTransfer.files;
      if (files.length > 0) {
        const file = files[0];
        document.getElementById('fileInput').files = files;
        handleFileSelect({
          target: {
            files: [file]
          }
        });
      }
    });

    // Notification functions
    function openNotifications() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Notifications</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-blue-50 rounded-lg">
              <p class="text-sm text-blue-800">New program available: Reading Fundamentals</p>
              <p class="text-xs text-blue-600 mt-1">2 hours ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    function openMessages() {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Messages</h3>
            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Welcome to TPLearn! Please complete your profile.</p>
              <p class="text-xs text-gray-600 mt-1">1 day ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">Your enrollment application is under review.</p>
              <p class="text-xs text-gray-600 mt-1">2 days ago</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-800">New semester starts next month. Prepare your documents.</p>
              <p class="text-xs text-gray-600 mt-1">3 days ago</p>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    // Mobile menu functionality
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
      console.log('Mobile menu clicked');
    });

    // Reference number validation
    document.getElementById('referenceNumber').addEventListener('input', function() {
      updateSubmitButton();
    });

    // Initialize
    updateSubmitButton();
    updatePaymentInstructions('ewallet'); // Load default payment method instructions
  </script>
</body>

</html>