<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Disable error display to prevent HTML output interfering with JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log errors, just don't display them

// Enable CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check if user is logged in and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Tutor access required.']);
    exit();
}

try {
    // Start output buffering to catch any unexpected output
    ob_start();
    
    // Log incoming data for debugging
    error_log("=== UPLOAD MATERIAL API CALLED ===");
    error_log("Upload attempt - POST data: " . print_r($_POST, true));
    error_log("Upload attempt - FILES data: " . print_r($_FILES, true));
    
    // Check specifically for assessment file
    if (isset($_FILES['assessmentFile'])) {
        error_log("✅ assessmentFile detected in FILES array!");
        error_log("Assessment file details: " . print_r($_FILES['assessmentFile'], true));
    } else {
        error_log("❌ No assessmentFile in FILES array");
    }
    
    // Check for assessment metadata
    $assessment_keys = ['assessmentTitle', 'assessmentDescription', 'assessmentDueDate', 'assessmentTotalPoints'];
    foreach ($assessment_keys as $key) {
        if (isset($_POST[$key])) {
            error_log("✅ $key = " . $_POST[$key]);
        } else {
            error_log("❌ $key not found in POST");
        }
    }
    error_log("=== END UPLOAD DATA LOG ===");
    
    // Validate required fields
    if (empty($_POST['program_id']) || empty($_POST['title']) || empty($_POST['material_type'])) {
        throw new Exception('Missing required fields: program_id, title, and material_type are required.');
    }

    $program_id = intval($_POST['program_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $material_type = $_POST['material_type'];
    $tutor_id = $_SESSION['user_id'];
    
    // Validate material type (assessment removed as standalone type, only available as attachment)
    $valid_types = ['document', 'video', 'quiz', 'assignment', 'other'];
    if (!in_array($material_type, $valid_types)) {
        throw new Exception('Invalid material type. Allowed types: ' . implode(', ', $valid_types));
    }

    // Verify that the tutor is assigned to this program
    $stmt = $conn->prepare("SELECT id FROM programs WHERE id = ? AND tutor_id = ?");
    $stmt->bind_param('ii', $program_id, $tutor_id);
    $stmt->execute();
    $program_check = $stmt->get_result()->fetch_assoc();
    
    if (!$program_check) {
        throw new Exception('You are not authorized to upload materials to this program.');
    }

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error or no file selected.');
    }

    $file = $_FILES['file'];
    $original_name = $file['name'];
    $tmp_name = $file['tmp_name'];
    $file_size = $file['size'];
    $mime_type = $file['type'];

    // Validate file size (max 50MB)
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file_size > $max_size) {
        throw new Exception('File size exceeds 50MB limit.');
    }

    // Validate file type
    $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'txt'];
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions));
    }

    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/program_materials/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($tmp_name, $file_path)) {
        throw new Exception('Failed to move uploaded file.');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert into file_uploads table
        $stmt = $conn->prepare("
            INSERT INTO file_uploads (user_id, filename, original_filename, file_path, file_size, mime_type, upload_type, related_id) 
            VALUES (?, ?, ?, ?, ?, ?, 'program_material', ?)
        ");
        $stmt->bind_param('isssisi', $tutor_id, $filename, $original_name, $file_path, $file_size, $mime_type, $program_id);
        $stmt->execute();
        
        $file_upload_id = $conn->insert_id;

        // Get the highest sort_order for this program and increment
        $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM program_materials WHERE program_id = ?");
        $stmt->bind_param('i', $program_id);
        $stmt->execute();
        $sort_order = $stmt->get_result()->fetch_assoc()['next_order'];

        // Insert into program_materials table
        $due_date = null;
        $max_score = null;
        $allow_late_submission = 1; // Default to allow late submissions
        $assignment_instructions = null;
        
        // Handle assignment-specific fields
        if ($material_type === 'assignment') {
            if (!empty($_POST['due_date'])) {
                $due_date = $_POST['due_date'];
            }
            // Handle both max_score and total_points (JavaScript sends total_points)
            if (!empty($_POST['max_score'])) {
                $max_score = floatval($_POST['max_score']);
            } elseif (!empty($_POST['total_points'])) {
                $max_score = floatval($_POST['total_points']);
            }
            // Handle both allow_late_submission and allow_late_submissions
            if (isset($_POST['allow_late_submission'])) {
                $allow_late_submission = intval($_POST['allow_late_submission']);
            } elseif (isset($_POST['allow_late_submissions'])) {
                $allow_late_submission = intval($_POST['allow_late_submissions']);
            }
            if (!empty($_POST['assignment_instructions'])) {
                $assignment_instructions = trim($_POST['assignment_instructions']);
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO program_materials (program_id, file_upload_id, title, description, material_type, due_date, max_score, allow_late_submission, assignment_instructions, sort_order, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iissssdisii', $program_id, $file_upload_id, $title, $description, $material_type, $due_date, $max_score, $allow_late_submission, $assignment_instructions, $sort_order, $tutor_id);
        $stmt->execute();

        $material_id = $conn->insert_id;



        // Handle assessment attachment if provided (for additional assessment files)
        // Check both 'assessmentFile' and 'assessment_file' (server may convert camelCase to snake_case)
        $assessment_file_key = null;
        if (isset($_FILES['assessmentFile']) && $_FILES['assessmentFile']['error'] === UPLOAD_ERR_OK) {
            $assessment_file_key = 'assessmentFile';
        } elseif (isset($_FILES['assessment_file']) && $_FILES['assessment_file']['error'] === UPLOAD_ERR_OK) {
            $assessment_file_key = 'assessment_file';
        }
        
        if ($assessment_file_key) {
            error_log("✅ Processing assessment attachment using key: $assessment_file_key");
            
            // Process assessment file upload
            $assessment_file = $_FILES[$assessment_file_key];
            $assessment_original_name = $assessment_file['name'];
            $assessment_temp_path = $assessment_file['tmp_name'];
            $assessment_file_size = $assessment_file['size'];
            $assessment_mime_type = $assessment_file['type'];
            
            error_log("Assessment file: $assessment_original_name, Size: $assessment_file_size bytes");
            
            // Generate unique filename for assessment
            $assessment_file_extension = pathinfo($assessment_original_name, PATHINFO_EXTENSION);
            $assessment_filename = uniqid() . '_assessment.' . $assessment_file_extension;
            $assessment_file_path = '../uploads/program_materials/' . $assessment_filename;
            
            // Move assessment file
            if (!move_uploaded_file($assessment_temp_path, $assessment_file_path)) {
                throw new Exception('Failed to move uploaded assessment file.');
            }
            
            // Get assessment details from POST data
            $assessment_title = trim($_POST['assessmentTitle'] ?? $_POST['assessment_title'] ?? $assessment_original_name);
            $assessment_description = trim($_POST['assessmentDescription'] ?? $_POST['assessment_description'] ?? '');
            
            // Handle due date - convert from HTML datetime-local format to MySQL datetime
            $assessment_due_date = null;
            $due_date_raw = $_POST['assessmentDueDate'] ?? $_POST['assessment_due_date'] ?? null;
            
            // Only process if we have a valid non-empty date
            if (!empty($due_date_raw) && $due_date_raw !== '' && strlen(trim($due_date_raw)) > 0) {
                // Validate the format (should be YYYY-MM-DDTHH:MM or YYYY-MM-DD HH:MM)
                if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $due_date_raw)) {
                    // Convert from "YYYY-MM-DDTHH:MM" to "YYYY-MM-DD HH:MM:SS"
                    $assessment_due_date = str_replace('T', ' ', $due_date_raw);
                    if (substr_count($assessment_due_date, ':') === 1) {
                        $assessment_due_date .= ':00'; // Add seconds if not present
                    }
                    error_log("Due date conversion: $due_date_raw -> $assessment_due_date");
                } else {
                    error_log("Invalid due date format received: $due_date_raw");
                }
            } else {
                error_log("No due date provided or empty date");
            }
            
            $assessment_total_points = !empty($_POST['assessmentTotalPoints']) ? floatval($_POST['assessmentTotalPoints']) : (!empty($_POST['assessment_points']) ? floatval($_POST['assessment_points']) : 100);
            
            error_log("Assessment details - Title: $assessment_title, Due: $assessment_due_date, Points: $assessment_total_points");
            
            // Create assessment record linked to the main material
            $stmt = $conn->prepare("
                INSERT INTO assessments (material_id, title, description, instructions, total_points, due_date, file_name, file_path, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('isssdsssi', $material_id, $assessment_title, $assessment_description, $assessment_description, $assessment_total_points, $assessment_due_date, $assessment_original_name, $assessment_file_path, $tutor_id);
            $stmt->execute();
            
            $assessment_id = $conn->insert_id;
            
            error_log("Assessment record created with ID: $assessment_id, linked to material ID: $material_id");
        }

        // Commit transaction
        $conn->commit();

        // Prepare response data
        $response_data = [
            'material_id' => $material_id,
            'file_upload_id' => $file_upload_id,
            'title' => $title,
            'filename' => $original_name,
            'file_size' => formatFileSize($file_size),
            'material_type' => $material_type,
            'upload_date' => date('Y-m-d H:i:s')
        ];
        
        // Add assessment info if created
        if (isset($assessment_id)) {
            $response_data['assessment_attached'] = true;
            $response_data['assessment_id'] = $assessment_id;
            $response_data['assessment_title'] = $assessment_title;
        }

        // Clean any unexpected output and return success response
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => isset($assessment_id) ? 
                'Material uploaded successfully with assessment attachment!' : 
                'Material uploaded successfully!',
            'data' => $response_data
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        // Delete uploaded file if database insertion failed
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        throw $e;
    }

} catch (Exception $e) {
    error_log("Material upload error: " . $e->getMessage());
    http_response_code(400);
    
    // Clean any unexpected output and return error response
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>