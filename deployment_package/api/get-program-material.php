<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/data-helpers.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get material ID from request
$material_id = $_GET['material_id'] ?? null;

if (!$material_id || !is_numeric($material_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid material ID']);
    exit;
}

try {
    // Get material information including assignment data if applicable
    $stmt = $conn->prepare("
        SELECT 
            pm.*,
            f.id as file_id,
            f.filename,
            f.original_filename,
            f.file_path,
            f.file_size,
            f.mime_type,
            f.upload_type,
            f.created_at as file_created_at,
            u.username as uploader_username,
            CASE 
                WHEN u.role = 'tutor' THEN tp.first_name
                WHEN u.role = 'student' THEN sp.first_name
                ELSE u.username
            END as uploader_first_name,
            CASE 
                WHEN u.role = 'tutor' THEN tp.last_name
                WHEN u.role = 'student' THEN sp.last_name
                ELSE ''
            END as uploader_last_name,
            p.name as program_name,
            p.tutor_id as program_tutor_id,
            pm.assignment_instructions as material_assignment_instructions,
            a.max_score as assignment_max_score,
            a.passing_score as assignment_passing_score,
            a.due_date as assignment_due_date,
            a.instructions as assignment_instructions,
            ass.id as assessment_id,
            ass.title as assessment_title,
            ass.description as assessment_description,
            ass.instructions as assessment_instructions,
            ass.total_points as assessment_total_points,
            ass.due_date as assessment_due_date
        FROM program_materials pm
        INNER JOIN file_uploads f ON pm.file_upload_id = f.id
        INNER JOIN users u ON f.user_id = u.id
        INNER JOIN programs p ON pm.program_id = p.id
        LEFT JOIN tutor_profiles tp ON u.id = tp.user_id AND u.role = 'tutor'
        LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
        LEFT JOIN assignments a ON pm.id = a.material_id
        LEFT JOIN assessments ass ON pm.id = ass.material_id
        WHERE pm.id = ?
    ");
    
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    $material = $stmt->get_result()->fetch_assoc();

    if (!$material) {
        http_response_code(404);
        echo json_encode(['error' => 'Material not found']);
        exit;
    }

    // Check access permissions
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    $has_access = false;

    if ($user_role === 'admin') {
        $has_access = true;
    } elseif ($user_role === 'tutor') {
        // Tutor can access materials from their programs
        $has_access = ($material['program_tutor_id'] == $user_id);
    } elseif ($user_role === 'student') {
        // Student can access materials from programs they're enrolled in
        $stmt = $conn->prepare("
            SELECT e.id 
            FROM enrollments e 
            WHERE e.program_id = ? AND e.student_user_id = ? AND e.status = 'active'
        ");
        $stmt->bind_param('ii', $material['program_id'], $user_id);
        $stmt->execute();
        $has_access = $stmt->get_result()->num_rows > 0;
    }

    if (!$has_access) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Format the response
    $response = [
        'id' => $material['id'],
        'title' => $material['title'],
        'description' => $material['description'],
        'material_type' => $material['material_type'],
        'is_required' => (bool)$material['is_required'],
        'created_at' => $material['created_at'],
        // Assignment-specific fields (if it's an assignment)
        'assignment' => $material['assignment_max_score'] ? [
            'max_score' => (int)$material['assignment_max_score'],
            'passing_score' => (int)$material['assignment_passing_score'],
            'due_date' => $material['assignment_due_date'],
            'instructions' => $material['assignment_instructions'] ?: $material['material_assignment_instructions']
        ] : ($material['material_type'] === 'assignment' ? [
            'max_score' => (int)$material['max_score'],
            'passing_score' => 60, // Default
            'due_date' => $material['due_date'],
            'instructions' => $material['material_assignment_instructions']
        ] : null),
        // Assessment-specific fields (if it has an attached assessment)
        'assessment' => $material['assessment_id'] ? [
            'id' => (int)$material['assessment_id'],
            'title' => $material['assessment_title'],
            'description' => $material['assessment_description'],
            'instructions' => $material['assessment_instructions'],
            'total_points' => (float)$material['assessment_total_points'],
            'due_date' => (
                $material['assessment_due_date'] && 
                $material['assessment_due_date'] !== '0000-00-00 00:00:00' && 
                $material['assessment_due_date'] !== ''
            ) ? $material['assessment_due_date'] : null
        ] : null,
        // Use assessment due date if available and valid, otherwise use material/assignment due date
        'due_date' => (
            $material['assessment_due_date'] && 
            $material['assessment_due_date'] !== '0000-00-00 00:00:00' && 
            $material['assessment_due_date'] !== '' 
        ) ? $material['assessment_due_date'] : (
            ($material['assignment_due_date'] && 
             $material['assignment_due_date'] !== '0000-00-00 00:00:00' && 
             $material['assignment_due_date'] !== ''
            ) ? $material['assignment_due_date'] : (
                ($material['due_date'] && 
                 $material['due_date'] !== '0000-00-00 00:00:00' && 
                 $material['due_date'] !== ''
                ) ? $material['due_date'] : null
            )
        ),
        'file' => [
            'id' => $material['file_id'],
            'filename' => $material['filename'],
            'original_filename' => $material['original_filename'],
            'file_size' => (int)$material['file_size'],
            'file_size_formatted' => formatFileSize($material['file_size']),
            'mime_type' => $material['mime_type'],
            'upload_type' => $material['upload_type'],
            'created_at' => $material['file_created_at'],
            'upload_date' => date('M j, Y', strtotime($material['file_created_at']))
        ],
        'uploader' => [
            'username' => $material['uploader_username'],
            'name' => trim($material['uploader_first_name'] . ' ' . $material['uploader_last_name']) ?: $material['uploader_username']
        ],
        'program' => [
            'id' => $material['program_id'],
            'name' => $material['program_name']
        ]
    ];

    echo json_encode(['success' => true, 'material' => $response]);

} catch (Exception $e) {
    error_log("Get material error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>