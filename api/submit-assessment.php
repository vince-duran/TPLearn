<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get the assessment ID (material_id) and student ID from the request
    $assessment_id = $_POST['assessment_id'] ?? null;
    $student_id = $_SESSION['user_id'];
    $comments = $_POST['comments'] ?? '';

    if (!$assessment_id) {
        throw new Exception('Assessment ID is required');
    }

    error_log("=== ASSESSMENT SUBMISSION START ===");
    error_log("Assessment ID: " . $assessment_id);
    error_log("Student ID: " . $student_id);

    // Get assessment details and verify student has access
    $stmt = $conn->prepare("
        SELECT pm.*, p.name as program_name, p.tutor_id,
               e.status as enrollment_status
        FROM program_materials pm
        JOIN programs p ON pm.program_id = p.id
        JOIN enrollments e ON p.id = e.program_id 
        WHERE pm.id = ? 
        AND pm.material_type = 'assessment' 
        AND e.student_user_id = ? 
        AND e.status = 'active'
    ");
    $stmt->bind_param('ii', $assessment_id, $student_id);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();

    error_log("Assessment found: " . ($assessment ? 'YES' : 'NO'));

    if (!$assessment) {
        // Detailed error checking
        $stmt = $conn->prepare("SELECT id, title, material_type FROM program_materials WHERE id = ?");
        $stmt->bind_param('i', $assessment_id);
        $stmt->execute();
        $material_check = $stmt->get_result()->fetch_assoc();
        
        if (!$material_check) {
            throw new Exception('Assessment not found (assessment_id: ' . $assessment_id . ')');
        }
        
        if ($material_check['material_type'] !== 'assessment') {
            throw new Exception('Material is not an assessment (type: ' . $material_check['material_type'] . ')');
        }
        
        $stmt = $conn->prepare("
            SELECT e.student_user_id, e.status 
            FROM enrollments e 
            JOIN program_materials pm ON e.program_id = pm.program_id 
            WHERE pm.id = ? AND e.student_user_id = ?
        ");
        $stmt->bind_param('ii', $assessment_id, $student_id);
        $stmt->execute();
        $enrollment_check = $stmt->get_result()->fetch_assoc();
        
        if (!$enrollment_check) {
            throw new Exception('Student not enrolled in this program');
        }
        
        if ($enrollment_check['status'] !== 'active') {
            throw new Exception('Student enrollment is not active (status: ' . $enrollment_check['status'] . ')');
        }
        
        throw new Exception('Assessment not found or you do not have access to it');
    }

    // Check if assessment is past due and late submissions are not allowed
    $is_late = false;
    if ($assessment['due_date'] && new DateTime() > new DateTime($assessment['due_date'])) {
        $is_late = true;
        if (!$assessment['allow_late_submission']) {
            throw new Exception('Assessment deadline has passed and late submissions are not allowed');
        }
    }

    // Check if student has already submitted this assessment
    $stmt = $conn->prepare("
        SELECT id FROM assessment_submissions 
        WHERE assessment_id = ? AND student_id = ?
    ");
    $stmt->bind_param('ii', $assessment_id, $student_id);
    $stmt->execute();
    $existing_submission = $stmt->get_result()->fetch_assoc();

    if ($existing_submission) {
        throw new Exception('You have already submitted this assessment');
    }

    error_log("Validation passed, processing file upload...");

    // Handle file upload if provided
    $file_upload_id = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['submission_file'];
        
        error_log("File upload detected: " . $file['name']);
        
        // Validate file size (10MB limit)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            throw new Exception('File size exceeds 10MB limit');
        }

        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            throw new Exception('File type not allowed. Please upload PDF, DOC, DOCX, TXT, JPG, or PNG files');
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/assessments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
            error_log("Created upload directory: " . $upload_dir);
        }

        // Generate unique filename
        $filename = 'assessment_' . $assessment_id . '_student_' . $student_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            error_log("Failed to move uploaded file to: " . $file_path);
            throw new Exception('Failed to upload file');
        }

        error_log("File uploaded successfully to: " . $file_path);

        // Insert file record into file_uploads table
        $stmt = $conn->prepare("
            INSERT INTO file_uploads 
            (user_id, filename, original_name, file_path, file_size, mime_type, upload_purpose, related_id) 
            VALUES (?, ?, ?, ?, ?, ?, 'assessment_submission', ?)
        ");
        $stmt->bind_param(
            'isssisi',
            $student_id,
            $filename,
            $file['name'],
            $file_path,
            $file['size'],
            $file['type'],
            $assessment_id
        );
        
        if (!$stmt->execute()) {
            // Clean up uploaded file if database insert fails
            unlink($file_path);
            error_log("Failed to insert file record: " . $stmt->error);
            throw new Exception('Failed to save file information');
        }
        
        $file_upload_id = $conn->insert_id;
        error_log("File record created with ID: " . $file_upload_id);
    } else {
        error_log("No file uploaded or upload error: " . ($_FILES['submission_file']['error'] ?? 'No file'));
    }

    // Create assessment submission record in assessment_submissions table
    $stmt = $conn->prepare("
        INSERT INTO assessment_submissions 
        (assessment_id, student_id, file_upload_id, submission_text, is_late, status) 
        VALUES (?, ?, ?, ?, ?, 'submitted')
    ");
    $stmt->bind_param(
        'iiisi',
        $assessment_id,
        $student_id,
        $file_upload_id,
        $comments,
        $is_late
    );

    if (!$stmt->execute()) {
        // Clean up uploaded file if submission insert fails
        if ($file_upload_id) {
            $conn->query("DELETE FROM file_uploads WHERE id = $file_upload_id");
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        error_log("Failed to create submission record: " . $stmt->error);
        throw new Exception('Failed to submit assessment');
    }

    $submission_id = $conn->insert_id;
    error_log("Assessment submission created with ID: " . $submission_id);
    error_log("=== ASSESSMENT SUBMISSION SUCCESS ===");

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Assessment submitted successfully!',
        'submission_id' => $submission_id,
        'is_late' => $is_late,
        'submitted_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Assessment submission error: " . $e->getMessage());
    error_log("=== ASSESSMENT SUBMISSION FAILED ===");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
