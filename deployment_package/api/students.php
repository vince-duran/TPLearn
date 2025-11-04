<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated and has appropriate role
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - not authenticated']);
    exit;
}

// Allow both admin and tutor roles, but tutors have restricted access
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'];

if (!in_array($user_role, ['admin', 'tutor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - insufficient permissions']);
    exit;
}

// Get the action parameter
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_student_details':
        getStudentDetails();
        break;
    case 'update_student':
        // Only admin can update students
        if ($user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied - admin only']);
            exit;
        }
        updateStudent();
        break;
    case 'deactivate_student':
        // Only admin can deactivate students
        if ($user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied - admin only']);
            exit;
        }
        deactivateStudent();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getStudentDetails() {
    global $pdo;
    
    $studentId = $_GET['id'] ?? '';
    
    if (empty($studentId)) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    // Log the student ID being requested for debugging
    error_log("API: Requesting student details for ID: " . $studentId);
    
    try {
        // First, check if the student exists at all
        $checkStmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
        $checkStmt->execute([$studentId]);
        $userCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userCheck) {
            error_log("API: No user found with ID: " . $studentId);
            echo json_encode(['success' => false, 'message' => 'User not found with ID: ' . $studentId]);
            return;
        }
        
        if ($userCheck['role'] !== 'student') {
            error_log("API: User ID " . $studentId . " is not a student (role: " . $userCheck['role'] . ")");
            echo json_encode(['success' => false, 'message' => 'User is not a student (role: ' . $userCheck['role'] . ')']);
            return;
        }

        // If user is a tutor, check if they have access to this student
        global $user_role, $user_id;
        if ($user_role === 'tutor') {
            $tutorAccessStmt = $pdo->prepare("
                SELECT COUNT(*) as has_access 
                FROM enrollments e 
                JOIN programs p ON e.program_id = p.id 
                WHERE e.student_user_id = ? AND p.tutor_id = ? AND e.status = 'active'
            ");
            $tutorAccessStmt->execute([$studentId, $user_id]);
            $accessCheck = $tutorAccessStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($accessCheck['has_access'] == 0) {
                error_log("API: Tutor $user_id does not have access to student $studentId");
                echo json_encode(['success' => false, 'message' => 'Access denied - student not in your programs']);
                return;
            }
        }
        
        // Get detailed student information with all fields
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.user_id,
                u.username,
                u.email,
                u.created_at,
                u.status as user_status,
                sp.first_name,
                sp.last_name,
                sp.middle_name,
                sp.gender,
                sp.birthday,
                sp.age,
                sp.province,
                sp.city,
                sp.barangay,
                sp.zip_code,
                sp.subdivision,
                sp.street,
                sp.house_number,
                sp.address,
                sp.medical_notes,
                sp.is_pwd,
                sp.user_id as profile_user_id,
                sp.student_id,
                pp.contact_number,
                pp.full_name as parent_name,
                pp.facebook_name as parent_facebook,
                pp.address as parent_address,
                COUNT(DISTINCT e.id) as enrolled_programs,
                COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.id END) as active_programs,
                MAX(e.created_at) as last_enrollment,
                CASE 
                    WHEN COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.id END) > 0 THEN 'active'
                    WHEN COUNT(DISTINCT e.id) > 0 AND COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.id END) = 0 THEN 'inactive'
                    ELSE 'pending'
                END as calculated_status
            FROM users u
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            LEFT JOIN parent_profiles pp ON u.id = pp.student_user_id
            LEFT JOIN enrollments e ON u.id = e.student_user_id
            WHERE u.id = ? AND u.role = 'student'
            GROUP BY u.id
        ");
        
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            error_log("API: Student details query returned no results for ID: " . $studentId);
            echo json_encode(['success' => false, 'message' => 'Student details not found for ID: ' . $studentId]);
            return;
        }
        
        error_log("API: Successfully found student: " . ($student['first_name'] ?? 'No name') . " " . ($student['last_name'] ?? ''));
        
        // Get enrollment details
        $enrollmentQuery = "
            SELECT 
                e.id,
                e.status,
                e.created_at,
                e.enrollment_date,
                p.name as program_title,
                p.description as program_description,
                p.duration_weeks as duration,
                p.fee as price,
                COALESCE(pay_status.payment_status, 'unpaid') as payment_status
            FROM enrollments e
            JOIN programs p ON e.program_id = p.id
            LEFT JOIN (
                SELECT 
                    enrollment_id,
                    CASE 
                        WHEN COUNT(CASE WHEN status = 'validated' THEN 1 END) > 0 THEN 'paid'
                        WHEN COUNT(CASE WHEN status = 'pending' THEN 1 END) > 0 THEN 'pending'
                        ELSE 'unpaid'
                    END as payment_status
                FROM payments 
                GROUP BY enrollment_id
            ) pay_status ON e.id = pay_status.enrollment_id
            WHERE e.student_user_id = ?";
        
        // If tutor, only show enrollments in their programs
        $queryParams = [$studentId];
        if ($user_role === 'tutor') {
            $enrollmentQuery .= " AND p.tutor_id = ?";
            $queryParams[] = $user_id;
        }
        
        $enrollmentQuery .= " ORDER BY e.created_at DESC";
        
        $enrollmentStmt = $pdo->prepare($enrollmentQuery);
        $enrollmentStmt->execute($queryParams);
        $enrollments = $enrollmentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment history
        $paymentStmt = $pdo->prepare("
            SELECT 
                pay.id,
                pay.amount,
                pay.payment_method,
                pay.status,
                pay.created_at,
                p.name as program_title
            FROM payments pay
            JOIN enrollments e ON pay.enrollment_id = e.id
            JOIN programs p ON e.program_id = p.id
            WHERE e.student_user_id = ?
            ORDER BY pay.created_at DESC
            LIMIT 10
        ");
        
        $paymentStmt->execute([$studentId]);
        $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add additional data to student object
        $student['enrollments'] = $enrollments;
        $student['payments'] = $payments;
        $student['total_payments'] = count($payments);
        $student['paid_amount'] = array_sum(array_column($payments, 'amount'));
        
        echo json_encode([
            'success' => true, 
            'student' => $student
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in getStudentDetails: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateStudent() {
    global $pdo;
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $studentId = $input['id'] ?? '';
    $firstName = $input['first_name'] ?? '';
    $lastName = $input['last_name'] ?? '';
    $middleName = $input['middle_name'] ?? '';
    $birthday = $input['birthday'] ?? '';
    $age = $input['age'] ?? '';
    $email = $input['email'] ?? '';
    $province = $input['province'] ?? '';
    $city = $input['city'] ?? '';
    $barangay = $input['barangay'] ?? '';
    $zipCode = $input['zip_code'] ?? '';
    $subdivision = $input['subdivision'] ?? '';
    $street = $input['street'] ?? '';
    $houseNumber = $input['house_number'] ?? '';
    $medicalNotes = $input['medical_notes'] ?? '';
    $isPwd = isset($input['is_pwd']) ? (bool)$input['is_pwd'] : false;
    
    if (empty($studentId)) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    // Basic validation
    if (empty($firstName) || empty($lastName)) {
        echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
        return;
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update user email if provided
        if (!empty($email)) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ? AND role = 'student'");
            $stmt->execute([$email, $studentId]);
        }
        
        // Update student profile - use UPDATE instead of INSERT ON DUPLICATE KEY UPDATE
        $profileStmt = $pdo->prepare("
            UPDATE student_profiles SET
                first_name = ?,
                last_name = ?,
                middle_name = ?,
                birthday = ?,
                age = ?,
                province = ?,
                city = ?,
                barangay = ?,
                zip_code = ?,
                subdivision = ?,
                street = ?,
                house_number = ?,
                medical_notes = ?,
                is_pwd = ?
            WHERE user_id = ?
        ");
        
        $result = $profileStmt->execute([
            $firstName, $lastName, $middleName, $birthday, $age,
            $province, $city, $barangay, $zipCode, $subdivision, $street, $houseNumber,
            $medicalNotes, $isPwd, $studentId
        ]);
        
        // Check if the update affected any rows
        if ($profileStmt->rowCount() === 0) {
            // If no rows were updated, the student profile doesn't exist, so create it
            $insertStmt = $pdo->prepare("
                INSERT INTO student_profiles (
                    user_id, first_name, last_name, middle_name, birthday, age,
                    province, city, barangay, zip_code, subdivision, street, house_number,
                    medical_notes, is_pwd
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $studentId, $firstName, $lastName, $middleName, $birthday, $age,
                $province, $city, $barangay, $zipCode, $subdivision, $street, $houseNumber,
                $medicalNotes, $isPwd
            ]);
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error in updateStudent: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update student: ' . $e->getMessage()]);
    }
}

function deactivateStudent() {
    global $pdo;
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $studentId = $input['id'] ?? '';
    
    if (empty($studentId)) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    try {
        // Update user status to inactive
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'student'");
        $result = $stmt->execute([$studentId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Student deactivated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Student not found or already inactive']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in deactivateStudent: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate student']);
    }
}
?>