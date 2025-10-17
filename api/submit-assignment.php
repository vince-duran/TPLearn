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
    // Get the assignment ID and student ID from the request
    $material_id = $_POST['assignment_id'] ?? null; // This is actually material_id from program_materials
    $student_id = $_SESSION['user_id'];
    $comments = $_POST['comments'] ?? '';

    if (!$material_id) {
        throw new Exception('Material ID is required');
    }

    // First, get the actual assignment ID from the assignments table
    $stmt = $conn->prepare("
        SELECT a.id as assignment_id, a.material_id, a.due_date,
               pm.title, pm.description, p.name as program_name, p.tutor_id,
               e.status as enrollment_status, e.id as enrollment_id
        FROM assignments a
        JOIN program_materials pm ON a.material_id = pm.id
        JOIN programs p ON pm.program_id = p.id
        JOIN enrollments e ON p.id = e.program_id 
        WHERE pm.id = ? AND pm.material_type = 'assignment' 
        AND e.student_user_id = ? AND e.status = 'active'
    ");
    $stmt->bind_param('ii', $material_id, $student_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();

    // Debug logging
    error_log("Submit Assignment Debug:");
    error_log("Material ID: " . $material_id);
    error_log("Student ID: " . $student_id);
    error_log("Assignment found: " . ($assignment ? 'YES' : 'NO'));
    if ($assignment) {
        error_log("Assignment data: " . print_r($assignment, true));
    }

    if (!$assignment) {
        // Let's check each step to provide better error message
        $stmt = $conn->prepare("SELECT id, material_id FROM assignments WHERE material_id = ?");
        $stmt->bind_param('i', $material_id);
        $stmt->execute();
        $assignment_check = $stmt->get_result()->fetch_assoc();
        
        if (!$assignment_check) {
            throw new Exception('No assignment record found for this material (material_id: ' . $material_id . ')');
        }
        
        $stmt = $conn->prepare("SELECT pm.id FROM program_materials pm WHERE pm.id = ? AND pm.material_type = 'assignment'");
        $stmt->bind_param('i', $material_id);
        $stmt->execute();
        $material_check = $stmt->get_result()->fetch_assoc();
        
        if (!$material_check) {
            throw new Exception('Material not found or not an assignment (material_id: ' . $material_id . ')');
        }
        
        $stmt = $conn->prepare("SELECT e.student_user_id, e.status FROM enrollments e JOIN program_materials pm ON e.program_id = pm.program_id WHERE pm.id = ? AND e.student_user_id = ?");
        $stmt->bind_param('ii', $material_id, $student_id);
        $stmt->execute();
        $enrollment_check = $stmt->get_result()->fetch_assoc();
        
        if (!$enrollment_check) {
            throw new Exception('Student not enrolled in this program (student_id: ' . $student_id . ', material_id: ' . $material_id . ')');
        }
        
        if ($enrollment_check['status'] !== 'active') {
            throw new Exception('Student enrollment is not active (status: ' . $enrollment_check['status'] . ')');
        }
        
        throw new Exception('Assignment not found or you do not have access to it (unknown reason)');
    }

    // Now we have the real assignment_id to use for submissions
    $assignment_id = $assignment['assignment_id'];

    // Check if assignment is past due and late submissions are not allowed
    $is_late = false;
    if ($assignment['due_date'] && new DateTime() > new DateTime($assignment['due_date'])) {
        $is_late = true;
        // Note: Late submissions are currently allowed by default since allow_late_submissions column doesn't exist
        // TODO: Add allow_late_submissions column to assignments table if this feature is needed
    }

    // Check if student has already submitted this assignment
    $stmt = $conn->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_user_id = ?");
    $stmt->bind_param('ii', $assignment_id, $student_id);
    $stmt->execute();
    $existing_submission = $stmt->get_result()->fetch_assoc();

    if ($existing_submission) {
        throw new Exception('You have already submitted this assignment');
    }

    // Handle file upload if provided
    $file_upload_id = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['submission_file'];
        
        // Validate file size (10MB limit)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            throw new Exception('File size exceeds 10MB limit');
        }

        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            throw new Exception('File type not allowed. Please upload PDF, DOC, DOCX, TXT, JPG, JPEG, or PNG files');
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/assignments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $filename = 'assignment_' . $assignment_id . '_student_' . $student_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to upload file');
        }

        // Insert file record into file_uploads table
        $stmt = $conn->prepare("
            INSERT INTO file_uploads 
            (user_id, filename, original_filename, file_path, file_size, mime_type, upload_type, related_id) 
            VALUES (?, ?, ?, ?, ?, ?, 'assignment', ?)
        ");
        $stmt->bind_param(
            'isssisi',
            $student_id,
            $filename,
            $file['name'],
            $file_path,
            $file['size'],
            $file['type'],
            $material_id
        );
        
        if (!$stmt->execute()) {
            // Clean up uploaded file if database insert fails
            unlink($file_path);
            throw new Exception('Failed to save file information');
        }
        
        $file_upload_id = $conn->insert_id;
    }

    // Create assignment submission record
    $stmt = $conn->prepare("
        INSERT INTO assignment_submissions 
        (assignment_id, student_user_id, enrollment_id, file_upload_id, submission_text, status) 
        VALUES (?, ?, ?, ?, ?, 'submitted')
    ");
    $stmt->bind_param(
        'iiiis',
        $assignment_id,
        $student_id,
        $assignment['enrollment_id'],
        $file_upload_id,
        $comments
    );

    if (!$stmt->execute()) {
        // Clean up uploaded file if submission insert fails
        if ($file_upload_id) {
            $conn->query("DELETE FROM file_uploads WHERE id = $file_upload_id");
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        throw new Exception('Failed to submit assignment');
    }

    $submission_id = $conn->insert_id;

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Assignment submitted successfully!',
        'submission_id' => $submission_id,
        'is_late' => $is_late,
        'submitted_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
