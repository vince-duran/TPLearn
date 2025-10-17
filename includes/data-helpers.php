<?php

/**
 * Data Helper Functions for TPLearn
 * Provides database-driven data retrieval for all dashboard components
 * Replaces hardcoded/placeholder data with real database queries
 */

require_once __DIR__ . '/db.php';

/**
 * =================================
 * TIMEZONE UTILITY FUNCTIONS
 * =================================
 */

/**
 * Get a DateTime object with Philippine timezone
 * @param string|null $time Optional time string, defaults to now
 * @return DateTime
 */
function getPSTDateTime($time = 'now') {
    return new DateTime($time, new DateTimeZone('Asia/Manila'));
}

/**
 * Convert UTC DateTime to PST DateTime
 * @param DateTime $utcDateTime
 * @return DateTime
 */
function convertUTCtoPST($utcDateTime) {
    $pstDateTime = clone $utcDateTime;
    $pstDateTime->setTimezone(new DateTimeZone('Asia/Manila'));
    return $pstDateTime;
}

/**
 * Convert PST DateTime to UTC DateTime
 * @param DateTime $pstDateTime
 * @return DateTime
 */
function convertPSTtoUTC($pstDateTime) {
    $utcDateTime = clone $pstDateTime;
    $utcDateTime->setTimezone(new DateTimeZone('UTC'));
    return $utcDateTime;
}

/**
 * Create DateTime from database date/time strings in PST
 * @param string $date Date string (Y-m-d)
 * @param string $time Time string (H:i:s)
 * @return DateTime PST DateTime object
 */
function createPSTDateTimeFromDB($date, $time) {
    return new DateTime($date . ' ' . $time, new DateTimeZone('Asia/Manila'));
}

/**
 * Format DateTime for database storage (always store in PST)
 * @param DateTime $dateTime
 * @return array ['date' => 'Y-m-d', 'time' => 'H:i:s']
 */
function formatDateTimeForDB($dateTime) {
    // Ensure we're working in PST
    if ($dateTime->getTimezone()->getName() !== 'Asia/Manila') {
        $dateTime = convertUTCtoPST($dateTime);
    }
    
    return [
        'date' => $dateTime->format('Y-m-d'),
        'time' => $dateTime->format('H:i:s')
    ];
}

/**
 * Get current PST timestamp for database operations
 * @return string Current timestamp in PST (Y-m-d H:i:s)
 */
function getCurrentPSTTimestamp() {
    return getPSTDateTime()->format('Y-m-d H:i:s');
}

/**
 * Check if a meeting/session is currently live
 * @param string $date Date string (Y-m-d)
 * @param string $time Time string (H:i:s)
 * @param int $duration Duration in minutes
 * @return array ['is_live' => bool, 'is_upcoming' => bool, 'is_past' => bool]
 */
function getMeetingStatus($date, $time, $duration = 60) {
    $now = getPSTDateTime();
    $startTime = createPSTDateTimeFromDB($date, $time);
    $endTime = clone $startTime;
    $endTime->add(new DateInterval('PT' . $duration . 'M'));
    
    $is_live = ($now >= $startTime && $now <= $endTime);
    $is_upcoming = ($now < $startTime);
    $is_past = ($now > $endTime);
    
    return [
        'is_live' => $is_live,
        'is_upcoming' => $is_upcoming,
        'is_past' => $is_past,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'current_time' => $now
    ];
}

/**
 * =================================
 * END TIMEZONE UTILITY FUNCTIONS
 * =================================
 */

/**
 * Generate next User ID based on role with random numbers
 * Format: TP + Role Letter + Year + 3 Random Digits
 * @param string $role User role (admin, tutor, student)
 * @return string Generated User ID (e.g., TPS2025-847)
 */
function generateUserID($role)
{
  global $conn;
  
  try {
    // Use PST timezone for consistent year calculation
    $year = getPSTDateTime()->format('Y');
    $prefix = '';
    
    // Determine prefix based on role (TP = Tisa and Pisara)
    switch ($role) {
      case 'admin':
        $prefix = 'TPA'; // Tisa Pisara Admin
        break;
      case 'tutor':
        $prefix = 'TPT'; // Tisa Pisara Tutor
        break;
      case 'student':
        $prefix = 'TPS'; // Tisa Pisara Student
        break;
      default:
        $prefix = 'TPU'; // Tisa Pisara Unknown
    }
    
    // Generate unique User ID with random 3-digit number
    $maxAttempts = 100; // Prevent infinite loop
    $attempts = 0;
    
    do {
      // Generate random 3-digit number (100-999)
      $randomNumber = sprintf('%03d', rand(100, 999));
      $userId = $prefix . $year . '-' . $randomNumber;
      
      // Check if this User ID already exists
      $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_id = ?");
      $stmt->bind_param('s', $userId);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();
      
      $attempts++;
    } while ($row['count'] > 0 && $attempts < $maxAttempts);
    
    if ($attempts >= $maxAttempts) {
      throw new Exception("Could not generate unique User ID after {$maxAttempts} attempts");
    }
    
    return $userId;
    
  } catch (Exception $e) {
    error_log("Error generating User ID: " . $e->getMessage());
    return null;
  }
}

/**
 * Check for duplicate user accounts before creation
 * Prevents duplicate accounts based on email, username, user_id, and full name
 * @param string $email User's email address
 * @param string $username User's username
 * @param string $user_id User's structured ID (optional, if already generated)
 * @param string $first_name User's first name (optional, for full name checking)
 * @param string $last_name User's last name (optional, for full name checking)
 * @return array Result array with 'is_duplicate' boolean and 'message' string
 */
function checkDuplicateUser($email, $username, $user_id = null, $first_name = null, $last_name = null)
{
  global $conn;
  
  try {
    $duplicates = [];
    
    // Check for duplicate email
    $stmt = $conn->prepare("SELECT id, username, user_id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($emailUser = $result->fetch_assoc()) {
      $duplicates[] = "Email '{$email}' is already registered to user '{$emailUser['username']}' ({$emailUser['user_id']})";
    }
    
    // Check for duplicate username
    $stmt = $conn->prepare("SELECT id, email, user_id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($usernameUser = $result->fetch_assoc()) {
      $duplicates[] = "Username '{$username}' is already taken by user ID '{$usernameUser['user_id']}'";
    }
    
    // Check for duplicate user_id (if provided)
    if ($user_id) {
      $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE user_id = ?");
      $stmt->bind_param('s', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($userIdUser = $result->fetch_assoc()) {
        $duplicates[] = "User ID '{$user_id}' already exists for '{$userIdUser['username']}'";
      }
    }
    
    // Check for duplicate full name (if both first_name and last_name provided)
    if ($first_name && $last_name) {
      // Check in student_profiles
      $stmt = $conn->prepare("
        SELECT u.user_id, u.username, sp.first_name, sp.last_name 
        FROM student_profiles sp 
        JOIN users u ON sp.user_id = u.id 
        WHERE LOWER(TRIM(sp.first_name)) = LOWER(TRIM(?)) 
        AND LOWER(TRIM(sp.last_name)) = LOWER(TRIM(?))
      ");
      $stmt->bind_param('ss', $first_name, $last_name);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($nameUser = $result->fetch_assoc()) {
        $duplicates[] = "Full name '{$first_name} {$last_name}' already exists for student '{$nameUser['username']}' ({$nameUser['user_id']})";
      }
      
      // Check in tutor_profiles
      $stmt = $conn->prepare("
        SELECT u.user_id, u.username, tp.first_name, tp.last_name 
        FROM tutor_profiles tp 
        JOIN users u ON tp.user_id = u.id 
        WHERE LOWER(TRIM(tp.first_name)) = LOWER(TRIM(?)) 
        AND LOWER(TRIM(tp.last_name)) = LOWER(TRIM(?))
      ");
      $stmt->bind_param('ss', $first_name, $last_name);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($nameUser = $result->fetch_assoc()) {
        $duplicates[] = "Full name '{$first_name} {$last_name}' already exists for tutor '{$nameUser['username']}' ({$nameUser['user_id']})";
      }
    }
    
    // Return result
    if (empty($duplicates)) {
      return [
        'is_duplicate' => false,
        'message' => 'No duplicate accounts found. User can be created.',
        'duplicates' => []
      ];
    } else {
      return [
        'is_duplicate' => true,
        'message' => 'Duplicate account detected: ' . implode('; ', $duplicates),
        'duplicates' => $duplicates
      ];
    }
    
  } catch (Exception $e) {
    error_log("Error checking duplicate user: " . $e->getMessage());
    return [
      'is_duplicate' => true,
      'message' => 'Error checking for duplicates: ' . $e->getMessage(),
      'duplicates' => []
    ];
  }
}

/**
 * Create a new user account with duplicate prevention
 * @param array $userData User data array
 * @return array Result array with success status and message
 */
function createUserWithDuplicateCheck($userData)
{
  global $conn;
  
  try {
    // Validate required fields
    $required = ['username', 'email', 'password', 'role'];
    foreach ($required as $field) {
      if (empty($userData[$field])) {
        return [
          'success' => false,
          'message' => "Required field '{$field}' is missing",
          'user_id' => null
        ];
      }
    }
    
    // Generate User ID
    $user_id = generateUserID($userData['role']);
    if (!$user_id) {
      return [
        'success' => false,
        'message' => 'Failed to generate unique User ID',
        'user_id' => null
      ];
    }
    
    // Get full name info if available for duplicate checking
    $first_name = null;
    $last_name = null;
    if (!empty($userData['profile'])) {
      $first_name = $userData['profile']['first_name'] ?? null;
      $last_name = $userData['profile']['last_name'] ?? null;
    }
    
    // Check for duplicates (including full name if provided)
    $duplicateCheck = checkDuplicateUser(
      $userData['email'], 
      $userData['username'], 
      $user_id,
      $first_name,
      $last_name
    );
    
    if ($duplicateCheck['is_duplicate']) {
      return [
        'success' => false,
        'message' => $duplicateCheck['message'],
        'user_id' => null,
        'duplicates' => $duplicateCheck['duplicates']
      ];
    }
    
    // Hash password
    $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    // Start transaction
    $conn->begin_transaction();
    
    // Insert user (with custom status if provided, otherwise 'active')
    $status = $userData['status'] ?? 'active';
    $stmt = $conn->prepare("
      INSERT INTO users (user_id, username, email, password, role, status) 
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssssss', 
      $user_id, 
      $userData['username'], 
      $userData['email'], 
      $hashedPassword, 
      $userData['role'],
      $status
    );
    
    if (!$stmt->execute()) {
      throw new Exception('Failed to create user: ' . $conn->error);
    }
    
    $userId = $conn->insert_id;
    
    // If student, create profile if profile data provided
    if ($userData['role'] === 'student' && !empty($userData['profile'])) {
      $profile = $userData['profile'];
      $stmt = $conn->prepare("
        INSERT INTO student_profiles (user_id, first_name, last_name, middle_name, gender, suffix, birthday, age, is_pwd, address, medical_notes, province, city, barangay, zip_code, subdivision, street, house_number) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      
      $first_name = $profile['first_name'] ?? '';
      $last_name = $profile['last_name'] ?? '';
      $middle_name = $profile['middle_name'] ?? '';
      $gender = $profile['gender'] ?? null;
      $suffix = $profile['suffix'] ?? null;
      $birthday = $profile['birthday'] ?? null;
      $age = $profile['age'] ?? null;
      $is_pwd = $profile['is_pwd'] ?? false;
      $address = $profile['address'] ?? '';
      $medical_notes = $profile['medical_notes'] ?? '';
      $province = $profile['province'] ?? '';
      $city = $profile['city'] ?? '';
      $barangay = $profile['barangay'] ?? '';
      $zip_code = $profile['zip_code'] ?? '';
      $subdivision = $profile['subdivision'] ?? '';
      $street = $profile['street'] ?? '';
      $house_number = $profile['house_number'] ?? '';
      
      $stmt->bind_param('isisssssisssssssss', 
        $userId,
        $first_name,
        $last_name,
        $middle_name,
        $gender,
        $suffix,
        $birthday,
        $age,
        $is_pwd,
        $address,
        $medical_notes,
        $province,
        $city,
        $barangay,
        $zip_code,
        $subdivision,
        $street,
        $house_number
      );
      $stmt->execute();
    }
    
    // If tutor, create profile if profile data provided  
    if ($userData['role'] === 'tutor' && !empty($userData['profile'])) {
      $profile = $userData['profile'];
      $stmt = $conn->prepare("
        INSERT INTO tutor_profiles (user_id, first_name, last_name, middle_name, bachelor_degree, specializations, bio, contact_number, address, cv_document_path, diploma_document_path, tor_document_path, lpt_csc_document_path, other_documents_paths) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      
      $first_name = $profile['first_name'] ?? '';
      $last_name = $profile['last_name'] ?? '';
      $middle_name = $profile['middle_name'] ?? null;
      $bachelor_degree = $profile['bachelor_degree'] ?? '';
      $specializations = $profile['specializations'] ?? '';
      $bio = $profile['bio'] ?? '';
      $contact_number = $profile['contact_number'] ?? '';
      $address = $profile['address'] ?? '';
      $cv_document_path = $profile['cv_document_path'] ?? null;
      $diploma_document_path = $profile['diploma_document_path'] ?? null;
      $tor_document_path = $profile['tor_document_path'] ?? null;
      $lpt_csc_document_path = $profile['lpt_csc_document_path'] ?? null;
      $other_documents_paths = $profile['other_documents_paths'] ?? null;
      
      $stmt->bind_param('isssssssssssss', 
        $userId,
        $first_name,
        $last_name,
        $middle_name,
        $bachelor_degree,
        $specializations,
        $bio,
        $contact_number,
        $address,
        $cv_document_path,
        $diploma_document_path,
        $tor_document_path,
        $lpt_csc_document_path,
        $other_documents_paths
      );
      $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    return [
      'success' => true,
      'message' => "User account created successfully with ID: {$user_id}",
      'user_id' => $user_id,
      'internal_id' => $userId
    ];
    
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error creating user: " . $e->getMessage());
    
    return [
      'success' => false,
      'message' => 'Failed to create user: ' . $e->getMessage(),
      'user_id' => null
    ];
  }
}

/**
 * Get dashboard statistics for admin panel
 * @return array Dashboard stats
 */
function getDashboardStats()
{
  global $conn;

  try {
    $stats = [
      'total_students' => 0,
      'total_tutors' => 0,
      'active_programs' => 0,
      'pending_payments' => 0,
      'total_revenue' => 0,
      'recent_enrollments' => 0
    ];

    // Count total students
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['total_students'] = (int)$row['count'];
    }

    // Count total tutors
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'tutor'");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['total_tutors'] = (int)$row['count'];
    }

    // Count active programs
    $result = $conn->query("SELECT COUNT(*) as count FROM programs WHERE status = 'active'");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['active_programs'] = (int)$row['count'];
    }

    // Count pending payments
    $result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['pending_payments'] = (int)$row['count'];
    }

    // Calculate total revenue
    $result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['total_revenue'] = (float)($row['total'] ?? 0);
    }

    // Count recent enrollments (last 30 days)
    $result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['recent_enrollments'] = (int)$row['count'];
    }

    return $stats;
  } catch (Exception $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
    return [
      'total_students' => 0,
      'total_tutors' => 0,
      'active_programs' => 0,
      'pending_payments' => 0,
      'total_revenue' => 0,
      'recent_enrollments' => 0
    ];
  }
}

/**
 * Get all programs from database with filtering
 * @param array $filters Optional filters: status, search, tutor_id
 * @param int $limit Optional limit
 * @return array List of programs
 */
function getPrograms($filters = [], $limit = null)
{
  global $conn;

  try {
    // Updated query to match actual database schema and include tutor information
    $sql = "SELECT p.id, p.name, p.description, p.age_group, p.duration_weeks, p.fee, 
                   p.category, p.difficulty_level, p.status, p.created_at, p.updated_at,
                   p.max_students, p.session_type, p.location, p.start_date, p.end_date,
                   p.start_time, p.end_time, p.days, p.tutor_id,
                   u.username as tutor_name,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.program_id = p.id AND e.status = 'active') as enrolled_count
            FROM programs p
            LEFT JOIN users u ON p.tutor_id = u.id";

    $conditions = [];
    $params = [];

    // Filter by ID (for single program retrieval)
    if (!empty($filters['id'])) {
      $conditions[] = "p.id = ?";
      $params[] = (int)$filters['id'];
    }

    // Filter by status
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
      $conditions[] = "p.status = ?";
      $params[] = $filters['status'];
    }

    // Search filter
    if (!empty($filters['search'])) {
      $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
      $searchTerm = '%' . $filters['search'] . '%';
      $params[] = $searchTerm;
      $params[] = $searchTerm;
      $params[] = $searchTerm;
    }

    if (!empty($conditions)) {
      $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY p.created_at DESC";

    if ($limit) {
      $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
      $types = str_repeat('s', count($params));
      $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $programs = [];

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $programs[] = $row;
      }
    }

    return $programs;
  } catch (Exception $e) {
    error_log("Error fetching programs: " . $e->getMessage());
    return [];
  }
}

/**
 * Get a single program by ID
 * @param int $id Program ID
 * @return array|null Program data if found, null if not found
 */
function getProgram($id)
{
  global $conn;

  try {
    $sql = "SELECT p.id, p.name, p.description, p.age_group, p.duration_weeks, p.fee, 
                   p.category, p.difficulty_level, p.status, p.created_at, p.updated_at,
                   p.max_students, p.session_type, p.location, p.start_date, p.end_date,
                   p.start_time, p.end_time, p.days, p.tutor_id,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.program_id = p.id AND e.status = 'active') as enrolled_count
            FROM programs p 
            WHERE p.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      return $result->fetch_assoc();
    } else {
      return null;
    }
  } catch (Exception $e) {
    error_log("Error fetching program: " . $e->getMessage());
    return null;
  }
}

/**
 * Create a new program
 * @param array $data Program data
 * @return int|bool Program ID if successful, false if failed
 */
function createProgram($data)
{
  global $conn;

  try {
    // Updated to match actual database schema with all form fields (removed session_id)
    $sql = "INSERT INTO programs (name, description, age_group, duration_weeks, fee, category, 
                                difficulty_level, max_students, session_type, location, 
                                start_date, end_date, start_time, end_time, days,
                                tutor_id, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";

    $stmt = $conn->prepare($sql);

    // Extract values with defaults
    $name = $data['title'] ?? $data['name'] ?? 'Untitled Program';
    $description = $data['description'] ?? 'No description provided';
    $age_group = $data['age_group'] ?? 'All Ages';
    $duration_weeks = $data['duration_weeks'] ?? 8; // Use calculated duration or default
    $fee = $data['fee'] ?? 0;
    $category = $data['category'] ?? 'General';
    $difficulty_level = $data['difficulty_level'] ?? 'beginner';
    $max_students = $data['max_students'] ?? 15;
    $session_type = $data['session_type'] ?? 'in-person';
    $location = $data['location'] ?? 'Location TBD';
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;
    $start_time = $data['start_time'] ?? '09:00:00';
    $end_time = $data['end_time'] ?? '10:00:00';
    $days = $data['days'] ?? 'Mon, Wed, Fri';
    $tutor_id = $data['tutor_id'] ?? null;

    $stmt->bind_param(
      'sssidssisssssssi',
      $name,
      $description,
      $age_group,
      $duration_weeks,
      $fee,
      $category,
      $difficulty_level,
      $max_students,
      $session_type,
      $location,
      $start_date,
      $end_date,
      $start_time,
      $end_time,
      $days,
      $tutor_id
    );

    if ($stmt->execute()) {
      return $conn->insert_id;
    } else {
      throw new Exception('Failed to create program: ' . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("Error creating program: " . $e->getMessage());
    return false;
  }
}

/**
 * Update an existing program
 * @param int $id Program ID
 * @param array $data Program data
 * @return bool True if successful, false if failed
 */
function updateProgram($id, $data)
{
  global $conn;

  try {
    // Updated to match actual database schema with all fields
    $sql = "UPDATE programs SET name = ?, description = ?, age_group = ?, duration_weeks = ?, 
                               fee = ?, category = ?, difficulty_level = ?, max_students = ?,
                               session_type = ?, location = ?, start_date = ?, end_date = ?,
                               start_time = ?, end_time = ?, days = ?,
                               tutor_id = ?, status = ?, updated_at = NOW() 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    // Extract values with current defaults
    $name = $data['title'] ?? $data['name'] ?? 'Updated Program';
    $description = $data['description'] ?? 'Updated description';
    $age_group = $data['age_group'] ?? 'All Ages';
    $duration_weeks = $data['duration_weeks'] ?? 8;
    $fee = $data['fee'] ?? 0;
    $category = $data['category'] ?? 'General';
    $difficulty_level = $data['difficulty_level'] ?? 'beginner';
    $max_students = $data['max_students'] ?? 15;
    $session_type = $data['session_type'] ?? 'in-person';
    $location = $data['location'] ?? 'Location TBD';
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;
    $start_time = $data['start_time'] ?? '09:00:00';
    $end_time = $data['end_time'] ?? '10:00:00';
    $days = $data['days'] ?? 'Mon, Wed, Fri';
    $tutor_id = $data['tutor_id'] ?? null;
    $status = $data['status'] ?? 'active';

    $stmt->bind_param(
      'sssidssisssssssisi',
      $name,
      $description,
      $age_group,
      $duration_weeks,
      $fee,
      $category,
      $difficulty_level,
      $max_students,
      $session_type,
      $location,
      $start_date,
      $end_date,
      $start_time,
      $end_time,
      $days,
      $tutor_id,
      $status,
      $id
    );

    if ($stmt->execute()) {
      return true;
    } else {
      throw new Exception('Failed to update program: ' . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("Error updating program: " . $e->getMessage());
    return false;
  }
}

/**
 * Delete a program
 * @param int $id Program ID
 * @return bool True if successful, false if failed
 */
function deleteProgram($id)
{
  global $conn;

  try {
    // Check if there are any active enrollments
    $checkSql = "SELECT COUNT(*) as count FROM enrollments WHERE program_id = ? AND status = 'active'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
      error_log("Cannot delete program $id - has active enrollments");
      return false;
    }

    // Hard delete from database (since schema doesn't have deleted status)
    $sql = "DELETE FROM programs WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
      return true;
    } else {
      throw new Exception('Failed to delete program: ' . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("Error deleting program: " . $e->getMessage());
    return false;
  }
}

/**
 * Get students with enrollment information
 * @return array List of students
 */
function getStudents($limit = null)
{
  global $conn;

  try {
    // Get basic student info with enrollment and payment information
    $sql = "SELECT u.*, sp.first_name, sp.last_name, sp.address, u.user_id,
                (SELECT COUNT(*) FROM enrollments e WHERE e.student_user_id = u.id) as enrolled_programs,
                (SELECT COUNT(*) FROM enrollments e WHERE e.student_user_id = u.id AND e.status = 'active') as active_programs,
                (SELECT COUNT(*) FROM enrollments e WHERE e.student_user_id = u.id AND e.status IN ('pending', 'active', 'completed')) as total_programs,
                (SELECT MAX(e2.created_at) FROM enrollments e2 WHERE e2.student_user_id = u.id) as last_enrollment,
                (SELECT MAX(p.created_at) FROM payments p JOIN enrollments e ON p.enrollment_id = e.id WHERE e.student_user_id = u.id) as last_payment_activity
                FROM users u
                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                WHERE u.role = 'student'
                ORDER BY u.created_at DESC";

    if ($limit) {
      $sql .= " LIMIT " . (int)$limit;
    }

    $result = $conn->query($sql);
    $students = [];

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        // Get most recent enrollment info for this student
        $enrollment_sql = "SELECT p.name as program_name, 
                                  tutor.username as tutor_name,
                                  e.status as enrollment_status,
                                  e.total_fee as program_fee,
                                  e.created_at as enrollment_date,
                                  e.start_date,
                                  e.end_date,
                                  e.id as enrollment_id
                           FROM enrollments e
                           LEFT JOIN programs p ON e.program_id = p.id
                           LEFT JOIN users tutor ON e.tutor_user_id = tutor.id
                           WHERE e.student_user_id = ?
                           ORDER BY e.created_at DESC
                           LIMIT 1";
        
        $stmt = $conn->prepare($enrollment_sql);
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();
        $enrollment_result = $stmt->get_result();
        
        if ($enrollment_result && $enrollment_data = $enrollment_result->fetch_assoc()) {
          $row = array_merge($row, $enrollment_data);
          
          // Check payment status for active enrollments of this student
          $payment_sql = "SELECT 
                            COUNT(*) as total_payments,
                            COUNT(CASE WHEN p.status = 'validated' THEN 1 END) as validated_payments,
                            COUNT(CASE WHEN p.status = 'overdue' THEN 1 END) as overdue_payments,
                            COUNT(CASE WHEN p.status = 'pending_validation' OR p.status = 'pending' THEN 1 END) as pending_payments,
                            COUNT(CASE WHEN p.status = 'rejected' THEN 1 END) as rejected_payments,
                            SUM(CASE WHEN p.status = 'validated' THEN p.amount ELSE 0 END) as paid_amount,
                            SUM(p.amount) as total_amount_due,
                            MAX(CASE WHEN p.status = 'overdue' THEN p.due_date END) as latest_overdue_date
                          FROM payments p
                          JOIN enrollments e ON p.enrollment_id = e.id
                          WHERE e.student_user_id = ? AND e.status = 'active'";
          
          $payment_stmt = $conn->prepare($payment_sql);
          $payment_stmt->bind_param("i", $row['id']);
          $payment_stmt->execute();
          $payment_result = $payment_stmt->get_result();
          
          if ($payment_result && $payment_data = $payment_result->fetch_assoc()) {
            $row = array_merge($row, $payment_data);
          } else {
            // No payment data
            $row['total_payments'] = 0;
            $row['validated_payments'] = 0;
            $row['overdue_payments'] = 0;
            $row['pending_payments'] = 0;
            $row['rejected_payments'] = 0;
            $row['paid_amount'] = 0;
            $row['total_amount_due'] = 0;
            $row['latest_overdue_date'] = null;
          }
        } else {
          // No enrollment data
          $row['program_name'] = null;
          $row['tutor_name'] = null;
          $row['enrollment_status'] = null;
          $row['program_fee'] = null;
          $row['enrollment_date'] = null;
          $row['start_date'] = null;
          $row['end_date'] = null;
          $row['enrollment_id'] = null;
          $row['total_payments'] = 0;
          $row['validated_payments'] = 0;
          $row['overdue_payments'] = 0;
          $row['pending_payments'] = 0;
          $row['rejected_payments'] = 0;
          $row['paid_amount'] = 0;
          $row['total_amount_due'] = 0;
          $row['latest_overdue_date'] = null;
        }

        // Determine calculated student status based on enrollment activity AND payment status
        $row['calculated_status'] = 'inactive'; // Default
        
        if ($row['active_programs'] > 0) {
          // Has active enrollment(s) - check payment status
          if ($row['overdue_payments'] > 0) {
            $row['calculated_status'] = 'paused'; // Active enrollment but overdue payments only
          } else if ($row['validated_payments'] > 0 || $row['pending_payments'] > 0 || $row['rejected_payments'] > 0) {
            $row['calculated_status'] = 'active'; // Active enrollment with good payment status (including rejected - they can resubmit)
          } else if ($row['total_payments'] == 0) {
            $row['calculated_status'] = 'active'; // New enrollment, no payments yet
          } else {
            $row['calculated_status'] = 'paused'; // Active enrollment but other payment issues
          }
        } else if ($row['enrolled_programs'] > 0 && $row['active_programs'] == 0) {
          // Has enrollments but none are active - check enrollment statuses
          $status_check_sql = "SELECT status, COUNT(*) as count FROM enrollments WHERE student_user_id = ? GROUP BY status";
          $status_stmt = $conn->prepare($status_check_sql);
          $status_stmt->bind_param("i", $row['id']);
          $status_stmt->execute();
          $status_result = $status_stmt->get_result();
          
          $has_completed = false;
          $has_cancelled = false;
          
          while ($status_row = $status_result->fetch_assoc()) {
            if ($status_row['status'] == 'completed') $has_completed = true;
            if ($status_row['status'] == 'cancelled') $has_cancelled = true;
          }
          
          if ($has_completed) {
            $row['calculated_status'] = 'completed';
          } else if ($has_cancelled) {
            $row['calculated_status'] = 'paused';
          } else {
            $row['calculated_status'] = 'inactive';
          }
        } else {
          // No enrollments
          $row['calculated_status'] = 'inactive';
        }
        
        $students[] = $row;
      }
    }

    return $students;
  } catch (Exception $e) {
    error_log("Error fetching students: " . $e->getMessage());
    return [];
  }
}

/**
 * Get students with filters and pagination
 * @param array $filters Filter criteria (search, status, program)
 * @param int $limit Number of students per page
 * @param int $offset Starting position
 * @return array Filtered students
 */
function getStudentsWithFilters($filters = [], $limit = 10, $offset = 0)
{
  global $conn;

  try {
    // Build WHERE clause based on filters
    $whereConditions = ["u.role = 'student'"];
    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
      $whereConditions[] = "(u.username LIKE ? OR u.email LIKE ? OR sp.first_name LIKE ? OR sp.last_name LIKE ? OR CONCAT(sp.first_name, ' ', sp.last_name) LIKE ?)";
      $searchTerm = "%" . $filters['search'] . "%";
      $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
      $types .= "sssss";
    }

    if (!empty($filters['program'])) {
      $whereConditions[] = "EXISTS (SELECT 1 FROM enrollments e JOIN programs p ON e.program_id = p.id WHERE e.student_user_id = u.id AND p.name LIKE ?)";
      $params[] = "%" . $filters['program'] . "%";
      $types .= "s";
    }

    $whereClause = implode(" AND ", $whereConditions);

    // Get students with enrollment and payment information
    $sql = "SELECT u.*, sp.first_name, sp.last_name, sp.address, u.user_id, pp.contact_number,
                (SELECT COUNT(*) FROM enrollments e WHERE e.student_user_id = u.id) as enrolled_programs,
                (SELECT COUNT(*) FROM enrollments e WHERE e.student_user_id = u.id AND e.status = 'active') as active_programs,
                (SELECT COUNT(*) FROM enrollments e WHERE e.student_user_id = u.id AND e.status IN ('pending', 'active', 'completed')) as total_programs,
                (SELECT MAX(e2.created_at) FROM enrollments e2 WHERE e2.student_user_id = u.id) as last_enrollment,
                (SELECT MAX(p.created_at) FROM payments p JOIN enrollments e ON p.enrollment_id = e.id WHERE e.student_user_id = u.id) as last_payment_activity
                FROM users u
                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                LEFT JOIN parent_profiles pp ON u.id = pp.student_user_id
                WHERE $whereClause
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if ($types) {
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        // Get most recent enrollment info for this student
        $enrollment_sql = "SELECT p.name as program_name, 
                                  tutor.username as tutor_name,
                                  e.status as enrollment_status,
                                  e.total_fee as program_fee,
                                  e.created_at as enrollment_date,
                                  e.start_date,
                                  e.end_date,
                                  e.id as enrollment_id
                           FROM enrollments e
                           LEFT JOIN programs p ON e.program_id = p.id
                           LEFT JOIN users tutor ON e.tutor_user_id = tutor.id
                           WHERE e.student_user_id = ?
                           ORDER BY e.created_at DESC
                           LIMIT 1";
        
        $stmt2 = $conn->prepare($enrollment_sql);
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $enrollment_result = $stmt2->get_result();
        
        if ($enrollment_result && $enrollment_data = $enrollment_result->fetch_assoc()) {
          $row = array_merge($row, $enrollment_data);
          
          // Check payment status for active enrollments of this student
          $payment_sql = "SELECT 
                            COUNT(*) as total_payments,
                            COUNT(CASE WHEN p.status = 'validated' THEN 1 END) as validated_payments,
                            COUNT(CASE WHEN p.status = 'overdue' THEN 1 END) as overdue_payments,
                            COUNT(CASE WHEN p.status = 'pending_validation' OR p.status = 'pending' THEN 1 END) as pending_payments,
                            COUNT(CASE WHEN p.status = 'rejected' THEN 1 END) as rejected_payments,
                            SUM(CASE WHEN p.status = 'validated' THEN p.amount ELSE 0 END) as paid_amount,
                            SUM(p.amount) as total_amount_due,
                            MAX(CASE WHEN p.status = 'overdue' THEN p.due_date END) as latest_overdue_date
                          FROM payments p
                          JOIN enrollments e ON p.enrollment_id = e.id
                          WHERE e.student_user_id = ? AND e.status = 'active'";
          
          $payment_stmt = $conn->prepare($payment_sql);
          $payment_stmt->bind_param("i", $row['id']);
          $payment_stmt->execute();
          $payment_result = $payment_stmt->get_result();
          
          if ($payment_result && $payment_data = $payment_result->fetch_assoc()) {
            $row = array_merge($row, $payment_data);
          } else {
            // No payment data
            $row['total_payments'] = 0;
            $row['validated_payments'] = 0;
            $row['overdue_payments'] = 0;
            $row['pending_payments'] = 0;
            $row['rejected_payments'] = 0;
            $row['paid_amount'] = 0;
            $row['total_amount_due'] = 0;
            $row['latest_overdue_date'] = null;
          }
        } else {
          // No enrollment data
          $row['program_name'] = null;
          $row['tutor_name'] = null;
          $row['enrollment_status'] = null;
          $row['program_fee'] = null;
          $row['enrollment_date'] = null;
          $row['start_date'] = null;
          $row['end_date'] = null;
          $row['enrollment_id'] = null;
          $row['total_payments'] = 0;
          $row['validated_payments'] = 0;
          $row['overdue_payments'] = 0;
          $row['pending_payments'] = 0;
          $row['rejected_payments'] = 0;
          $row['paid_amount'] = 0;
          $row['total_amount_due'] = 0;
          $row['latest_overdue_date'] = null;
        }

        // Calculate student status
        $row['calculated_status'] = 'inactive'; // Default
        
        if ($row['active_programs'] > 0) {
          if ($row['overdue_payments'] > 0) {
            $row['calculated_status'] = 'paused';
          } else if ($row['validated_payments'] > 0 || $row['pending_payments'] > 0 || $row['rejected_payments'] > 0) {
            $row['calculated_status'] = 'active';
          } else if ($row['total_payments'] == 0) {
            $row['calculated_status'] = 'active';
          } else {
            $row['calculated_status'] = 'paused';
          }
        } else if ($row['enrolled_programs'] > 0 && $row['active_programs'] == 0) {
          $row['calculated_status'] = 'completed';
        }

        // Apply status filter if specified
        if (!empty($filters['status']) && $row['calculated_status'] !== $filters['status']) {
          continue; // Skip this student
        }
        
        $students[] = $row;
      }
    }

    return $students;
  } catch (Exception $e) {
    error_log("Error fetching students with filters: " . $e->getMessage());
    return [];
  }
}

/**
 * Get total count of students matching filters
 * @param array $filters Filter criteria
 * @return int Total count
 */
function getTotalStudentsCount($filters = [])
{
  global $conn;

  try {
    // Build WHERE clause based on filters (excluding status since it's calculated)
    $whereConditions = ["u.role = 'student'"];
    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
      $whereConditions[] = "(u.username LIKE ? OR u.email LIKE ? OR sp.first_name LIKE ? OR sp.last_name LIKE ? OR CONCAT(sp.first_name, ' ', sp.last_name) LIKE ?)";
      $searchTerm = "%" . $filters['search'] . "%";
      $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
      $types .= "sssss";
    }

    if (!empty($filters['program'])) {
      $whereConditions[] = "EXISTS (SELECT 1 FROM enrollments e JOIN programs p ON e.program_id = p.id WHERE e.student_user_id = u.id AND p.name LIKE ?)";
      $params[] = "%" . $filters['program'] . "%";
      $types .= "s";
    }

    $whereClause = implode(" AND ", $whereConditions);

    // If status filter is specified, we need to get all records and count after calculating status
    if (!empty($filters['status'])) {
      // Get all matching students and filter by calculated status
      $students = getStudentsWithFilters(array_diff_key($filters, ['status' => '']), 999999, 0);
      return count(array_filter($students, function($student) use ($filters) {
        return $student['calculated_status'] === $filters['status'];
      }));
    }

    // Simple count query for other filters
    $sql = "SELECT COUNT(DISTINCT u.id) as total
            FROM users u
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            WHERE $whereClause";

    if (!empty($params)) {
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $result = $stmt->get_result();
    } else {
      $result = $conn->query($sql);
    }

    if ($result && $row = $result->fetch_assoc()) {
      return (int)$row['total'];
    }

    return 0;
  } catch (Exception $e) {
    error_log("Error counting students: " . $e->getMessage());
    return 0;
  }
}

/**
 * Get tutors with program information
 * @return array List of tutors
 */
function getTutors($limit = null)
{
  global $conn;

  try {
    // Updated query to include program information
    $sql = "SELECT u.id as user_id, u.user_id as legacy_user_id, u.username, u.email, u.role, u.status, u.created_at,
                   tp.id as tutor_profile_id, tp.first_name, tp.last_name, tp.middle_name,
                   tp.specializations, tp.bio, tp.contact_number, tp.address,
                   COUNT(p.id) as total_programs,
                   GROUP_CONCAT(p.name SEPARATOR ', ') as assigned_programs
            FROM users u
            LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
            LEFT JOIN programs p ON u.id = p.tutor_id AND p.status = 'active'
            WHERE u.role = 'tutor'
            GROUP BY u.id, u.user_id, u.username, u.email, u.role, u.status, u.created_at,
                     tp.id, tp.first_name, tp.last_name, tp.middle_name,
                     tp.specializations, tp.bio, tp.contact_number, tp.address
            ORDER BY u.created_at DESC";

    if ($limit) {
      $sql .= " LIMIT " . (int)$limit;
    }

    $result = $conn->query($sql);
    $tutors = [];

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        // Create full name from profile or fallback to username
        if (!empty($row['first_name']) && !empty($row['last_name'])) {
          $row['name'] = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
        } else {
          $row['name'] = $row['username'];
        }
        
        // Add phone field for compatibility
        $row['phone'] = $row['contact_number'];
        
        // Set specialization
        $row['specialization'] = $row['specializations'] ?: 'General';
        
        // Set experience based on creation date (guard against missing/invalid created_at)
        $months = 0;
        if (!empty($row['created_at'])) {
          try {
            $created = new DateTime($row['created_at'], new DateTimeZone('Asia/Manila'));
            $now = getPSTDateTime();
            $diff = $now->diff($created);
            $months = $diff->y * 12 + $diff->m;
          } catch (Exception $ex) {
            // If created_at is invalid, treat as new tutor
            $months = 0;
          }
        }

        if ($months < 1) {
          $row['experience'] = 'New tutor';
        } else if ($months < 12) {
          $row['experience'] = $months . ' month' . ($months > 1 ? 's' : '') . ' experience';
        } else {
          $years = floor($months / 12);
          $row['experience'] = $years . ' year' . ($years > 1 ? 's' : '') . ' experience';
        }
        
        // Set student count (may not be present in simplified query)
        $row['student_count'] = isset($row['total_students']) ? $row['total_students'] : 0;
        
        // Set rating (default to 0 since reviews table doesn't exist yet)
        $row['rating'] = 0; // TODO: Implement reviews system later
        
        // Get real programs from database
        $row['programs'] = $row['assigned_programs'] ?: '';
        $row['program_count'] = (int)$row['total_programs'];
        
        // Use the users.id as the primary ID for tutor selection/assignment
        // programs.tutor_id stores the users.id, so the dropdown value must match that.
        $row['profile_id'] = $row['tutor_profile_id'];
        $row['id'] = $row['user_id'];
        
        $tutors[] = $row;
      }
    }

    // If no detailed tutors were found (e.g., missing tutor_profiles rows),
    // fall back to returning basic user records with role='tutor' so the
    // admin UI can still assign tutors to programs.
    if (empty($tutors)) {
      $fallbackSql = "SELECT id as user_id, username, email, role, status, created_at FROM users WHERE role = 'tutor' ORDER BY created_at DESC";
      $fallbackResult = $conn->query($fallbackSql);
      if ($fallbackResult) {
        while ($r = $fallbackResult->fetch_assoc()) {
          $r['name'] = $r['username'];
          $r['specialization'] = 'General';
          $r['profile_id'] = null;
          $r['id'] = $r['user_id'];
          $tutors[] = $r;
        }
      }
    }

    return $tutors;
  } catch (Exception $e) {
    error_log("Error fetching tutors: " . $e->getMessage());
    return [];
  }
}

/**
 * Get payments with related information for admin dashboard
 * @param string|null $status Filter by payment status
 * @param int|null $limit Limit number of results
 * @param string|null $search Search student name or program name
 * @return array List of payments with student and program information
 */
function getPayments($status = null, $limit = null, $search = null)
{
  global $conn;

  try {
    $sql = "SELECT p.*, 
                   e.total_fee as enrollment_total_fee,
                   COALESCE(CONCAT(sp.first_name, ' ', sp.last_name), u.username) as student_name,
                   COALESCE(sp.student_id, u.username) as student_user_id,
                   u.username as student_username,
                   u.email as student_email,
                   pr.name as program_name,
                   pr.id as program_id,
                   validator.username as validator_name,
                   CONCAT('PAY-', DATE_FORMAT(p.created_at, '%Y%m%d'), '-', LPAD(p.id, 3, '0')) as payment_id,
                   CASE 
                     WHEN p.status = 'pending' AND p.reference_number IS NOT NULL THEN 'pending_validation'
                     WHEN p.status = 'pending' AND p.reference_number IS NULL AND p.due_date < CURDATE() THEN 'overdue'
                     WHEN p.status = 'pending' AND p.reference_number IS NULL AND p.due_date = CURDATE() THEN 'due_today'
                     WHEN p.status = 'pending' AND p.reference_number IS NULL AND p.due_date > CURDATE() THEN 'pending_payment'
                     ELSE p.status
                   END as payment_status,
                   DATEDIFF(CURDATE(), p.due_date) as days_overdue,
                   COALESCE(
                     (SELECT COUNT(*) + 1 
                      FROM payments p2 
                      WHERE p2.enrollment_id = p.enrollment_id 
                      AND p2.created_at < p.created_at)
                   , 1) as installment_number,
                   (SELECT COUNT(*) 
                    FROM payments p3 
                    WHERE p3.enrollment_id = p.enrollment_id) as total_installments
            FROM payments p
            INNER JOIN enrollments e ON p.enrollment_id = e.id
            INNER JOIN users u ON e.student_user_id = u.id
            LEFT JOIN student_profiles sp ON e.student_user_id = sp.user_id
            INNER JOIN programs pr ON e.program_id = pr.id
            LEFT JOIN users validator ON p.validated_by = validator.id";

    $conditions = [];
    $params = [];
    $types = '';

    // Handle status filtering with calculated status
    if ($status) {
      switch($status) {
        case 'pending_validation':
          $conditions[] = "p.status = 'pending' AND p.reference_number IS NOT NULL";
          break;
        case 'overdue':
          $conditions[] = "p.status = 'pending' AND p.reference_number IS NULL AND p.due_date < CURDATE()";
          break;
        case 'pending':
          $conditions[] = "(p.status = 'pending' AND p.reference_number IS NULL AND p.due_date >= CURDATE())";
          break;
        case 'validated':
          $conditions[] = "p.status = 'validated'";
          break;
        case 'rejected':
          $conditions[] = "p.status = 'rejected'";
          break;
        default:
          $conditions[] = "p.status = ?";
          $params[] = $status;
          $types .= 's';
      }
    }

    if ($search) {
      $conditions[] = "(COALESCE(CONCAT(sp.first_name, ' ', sp.last_name), u.username) LIKE ? OR pr.name LIKE ? OR COALESCE(sp.student_id, u.username) LIKE ?)";
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
      $types .= 'sss';
    }

    if (!empty($conditions)) {
      $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY p.created_at DESC";

    if ($limit) {
      $sql .= " LIMIT ?";
      $params[] = $limit;
      $types .= 'i';
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];

    while ($row = $result->fetch_assoc()) {
      // Format payment method for display
      $row['payment_method_display'] = ucfirst(str_replace('_', ' ', $row['payment_method']));

      // Calculate remaining balance if applicable
      if ($row['enrollment_total_fee']) {
        $paid_amount = getPaidAmount($row['enrollment_id']);
        $row['remaining_balance'] = max(0, $row['enrollment_total_fee'] - $paid_amount);
      } else {
        $row['remaining_balance'] = 0;
      }

      $payments[] = $row;
    }

    return $payments;
  } catch (Exception $e) {
    error_log("Error fetching payments: " . $e->getMessage());
    return [];
  }
}

/**
 * Get total paid amount for an enrollment
 */
function getPaidAmount($enrollment_id)
{
  global $conn;

  try {
    $sql = "SELECT SUM(amount) as total_paid FROM payments WHERE enrollment_id = ? AND status = 'validated'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $enrollment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['total_paid'] ?? 0;
  } catch (Exception $e) {
    error_log("Error calculating paid amount: " . $e->getMessage());
    return 0;
  }
}

/**
 * Get payment statistics for admin dashboard
 */
function getPaymentStats()
{
  global $conn;

  try {
    $stats = [
      'total_payments' => 0,
      'pending_payments' => 0,
      'validated_payments' => 0,
      'rejected_payments' => 0,
      'overdue_payments' => 0,
      'total_amount' => 0,
      'pending_amount' => 0,
      'validated_amount' => 0,
      'overdue_amount' => 0,
      'monthly_revenue' => 0,
      // Add fields that admin dashboard expects
      'total_collected' => 0,
      'total_pending' => 0,
      'total_revenue' => 0
    ];

    // Get payment counts by status
    $result = $conn->query("
      SELECT 
        status,
        COUNT(*) as count,
        SUM(amount) as total_amount
      FROM payments 
      GROUP BY status
    ");

    while ($row = $result->fetch_assoc()) {
      switch ($row['status']) {
        case 'pending':
          $stats['pending_payments'] = (int)$row['count'];
          $stats['pending_amount'] = (float)$row['total_amount'];
          $stats['total_pending'] = (float)$row['total_amount'];
          break;
        case 'validated':
          $stats['validated_payments'] = (int)$row['count'];
          $stats['validated_amount'] = (float)$row['total_amount'];
          $stats['total_collected'] = (float)$row['total_amount'];
          break;
        case 'rejected':
          $stats['rejected_payments'] = (int)$row['count'];
          break;
        case 'overdue':
          $stats['overdue_payments'] = (int)$row['count'];
          $stats['overdue_amount'] = (float)$row['total_amount'];
          break;
      }
    }

    $stats['total_payments'] = $stats['pending_payments'] + $stats['validated_payments'] + $stats['rejected_payments'] + $stats['overdue_payments'];
    $stats['total_amount'] = $stats['pending_amount'] + $stats['validated_amount'] + $stats['overdue_amount'];
    $stats['total_revenue'] = $stats['total_collected'] + $stats['total_pending'];

    // Get current month revenue using PST timezone
    $current_month = getPSTDateTime()->format('Y-m');
    $result = $conn->query("
      SELECT SUM(amount) as monthly_revenue 
      FROM payments 
      WHERE status = 'validated' 
      AND DATE_FORMAT(validated_at, '%Y-%m') = '$current_month'
    ");

    if ($row = $result->fetch_assoc()) {
      $stats['monthly_revenue'] = (float)($row['monthly_revenue'] ?? 0);
    }

    return $stats;
  } catch (Exception $e) {
    error_log("Error fetching payment stats: " . $e->getMessage());
    return [
      'total_payments' => 0,
      'pending_payments' => 0,
      'validated_payments' => 0,
      'rejected_payments' => 0,
      'total_amount' => 0,
      'pending_amount' => 0,
      'validated_amount' => 0,
      'monthly_revenue' => 0,
      'total_collected' => 0,
      'total_pending' => 0,
      'total_revenue' => 0
    ];
  }
}

/**
 * Get student-specific data for student dashboard
 * @param int $student_id Student ID
 * @return array Student dashboard data
 */
function getStudentDashboardData($student_id)
{
  global $conn;

  try {
    $data = [
      'enrolled_programs' => 0,
      'sessions_today' => 0,
      'unread_messages' => 0,
      'next_payment_due' => null,
      'recent_sessions' => [],
      'active_programs' => [],
      'name' => 'Student',
      'program' => 'General',
      'level' => 'Beginner',
      'overall_progress' => 0
    ];

    // Get student basic info
    $result = $conn->query("SELECT u.email, CONCAT(sp.first_name, ' ', sp.last_name) as name 
                               FROM users u 
                               LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                               WHERE u.id = " . (int)$student_id);
    if ($result && $row = $result->fetch_assoc()) {
      $data['name'] = $row['name'] ?? 'Student';
      $data['email'] = $row['email'];
    }

    // Get enrolled programs
    $result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE student_user_id = " . (int)$student_id . " AND status = 'active'");
    if ($result && $row = $result->fetch_assoc()) {
      $data['enrolled_programs'] = (int)$row['count'];
    }

    // Get sessions today
    $result = $conn->query("SELECT COUNT(*) as count FROM sessions s
                               JOIN enrollments e ON s.enrollment_id = e.id 
                               WHERE e.student_user_id = " . (int)$student_id . " 
                               AND DATE(s.session_date) = CURDATE()
                               AND s.status = 'scheduled'");
    if ($result && $row = $result->fetch_assoc()) {
      $data['sessions_today'] = (int)$row['count'];
    }

    // Get unread messages - check if messages table exists, otherwise default to 0
    $result = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($result && $result->num_rows > 0) {
      // Messages table exists, get real unread count
      $result = $conn->query("SELECT COUNT(*) as count FROM messages 
                                 WHERE recipient_id = " . (int)$student_id . " 
                                 AND status = 'unread'");
      if ($result && $row = $result->fetch_assoc()) {
        $data['unread_messages'] = (int)$row['count'];
      }
    } else {
      // No messaging system yet, set to 0
      $data['unread_messages'] = 0;
    }

    // Get primary program info and overall progress
    $result = $conn->query("SELECT p.name as program_name, p.difficulty_level, 
                                   AVG(CASE WHEN s.status = 'completed' THEN 100 ELSE 0 END) as progress
                               FROM programs p
                               JOIN enrollments e ON p.id = e.program_id
                               LEFT JOIN sessions s ON s.enrollment_id = e.id
                               WHERE e.student_user_id = " . (int)$student_id . " 
                               AND e.status = 'active'
                               GROUP BY p.id, p.name, p.difficulty_level
                               ORDER BY e.enrollment_date DESC
                               LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
      $data['program'] = $row['program_name'];
      $data['level'] = ucfirst($row['difficulty_level']);
      $data['overall_progress'] = (int)$row['progress'];
    }

    // Get active programs with details
    $result = $conn->query("SELECT p.*, e.enrollment_date
                               FROM programs p
                               JOIN enrollments e ON p.id = e.program_id
                               WHERE e.student_user_id = " . (int)$student_id . " 
                               AND e.status = 'active'
                               ORDER BY e.enrollment_date DESC");
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $data['active_programs'][] = $row;
      }
    }

    return $data;
  } catch (Exception $e) {
    error_log("Error fetching student dashboard data: " . $e->getMessage());
    return [
      'enrolled_programs' => 0,
      'sessions_today' => 0,
      'unread_messages' => 0,
      'next_payment_due' => null,
      'recent_sessions' => [],
      'active_programs' => []
    ];
  }
}

/**
 * Get tutor-specific data for tutor dashboard
 * @param int $tutor_id Tutor ID
 * @return array Tutor dashboard data
 */
function getTutorDashboardData($tutor_id)
{
  global $conn;

  try {
    $data = [
      'assigned_programs' => 0,
      'total_students' => 0,
      'sessions_today' => 0,
      'programs' => [],
      'students' => []
    ];

    // Get assigned programs
    $result = $conn->query("SELECT COUNT(*) as count FROM programs WHERE tutor_id = " . (int)$tutor_id . " AND status = 'active'");
    if ($result && $row = $result->fetch_assoc()) {
      $data['assigned_programs'] = (int)$row['count'];
    }

    // Get total students across all programs
    $result = $conn->query("SELECT COUNT(DISTINCT e.student_user_id) as count 
                               FROM enrollments e
                               JOIN programs p ON e.program_id = p.id
                               WHERE p.tutor_id = " . (int)$tutor_id . " 
                               AND e.status = 'active'");
    if ($result && $row = $result->fetch_assoc()) {
      $data['total_students'] = (int)$row['count'];
    }

    // Get sessions today
    $result = $conn->query("SELECT COUNT(*) as count FROM sessions s
                               JOIN enrollments e ON s.enrollment_id = e.id
                               JOIN programs p ON e.program_id = p.id
                               WHERE p.tutor_id = " . (int)$tutor_id . " 
                               AND DATE(s.session_date) = CURDATE()
                               AND s.status = 'scheduled'");
    if ($result && $row = $result->fetch_assoc()) {
      $data['sessions_today'] = (int)$row['count'];
    }

    // Get programs with enrollment counts
    $result = $conn->query("SELECT p.*, 
                               COUNT(e.id) as enrolled_count
                               FROM programs p
                               LEFT JOIN enrollments e ON p.id = e.program_id AND e.status = 'active'
                               WHERE p.tutor_id = " . (int)$tutor_id . "
                               GROUP BY p.id
                               ORDER BY p.created_at DESC");
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $data['programs'][] = $row;
      }
    }

    return $data;
  } catch (Exception $e) {
    error_log("Error fetching tutor dashboard data: " . $e->getMessage());
    return [
      'assigned_programs' => 0,
      'total_students' => 0,
      'sessions_today' => 0,
      'programs' => [],
      'students' => []
    ];
  }
}

/**
 * Get recent activities for a tutor
 * @param int $tutor_id Tutor's user ID
 * @param int $limit Number of activities to return (default: 10)
 * @return array Recent activities
 */
function getTutorRecentActivities($tutor_id, $limit = 10)
{
  global $conn;

  try {
    $activities = [];

    // Get recent enrollments in tutor's programs
    $sql = "SELECT 'enrollment' as activity_type, 
                   CONCAT('New student enrolled in ', p.name) as message,
                   e.created_at as activity_time,
                   'user-plus' as icon
            FROM enrollments e
            JOIN programs p ON e.program_id = p.id
            WHERE p.tutor_id = ? AND e.status = 'active'
            
            UNION ALL
            
            SELECT 'session' as activity_type,
                   CONCAT('Session completed for ', p.name) as message,
                   s.session_date as activity_time,
                   'academic-cap' as icon
            FROM sessions s
            JOIN enrollments e ON s.enrollment_id = e.id
            JOIN programs p ON e.program_id = p.id
            WHERE p.tutor_id = ? AND s.status = 'completed'
            
            UNION ALL
            
            SELECT 'program' as activity_type,
                   CONCAT('Program \"', p.name, '\" was activated') as message,
                   p.updated_at as activity_time,
                   'trophy' as icon
            FROM programs p
            WHERE p.tutor_id = ? AND p.status = 'active'
            
            ORDER BY activity_time DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $tutor_id, $tutor_id, $tutor_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      // Format the time
      $time_diff = time() - strtotime($row['activity_time']);
      
      if ($time_diff < 0) {
        $time_display = 'Recently';
      } elseif ($time_diff < 60) {
        $time_display = 'Just now';
      } elseif ($time_diff < 3600) {
        $time_display = floor($time_diff / 60) . ' minutes ago';
      } elseif ($time_diff < 86400) {
        $time_display = floor($time_diff / 3600) . ' hours ago';
      } else {
        $time_display = floor($time_diff / 86400) . ' days ago';
      }

      $activities[] = [
        'message' => $row['message'],
        'time' => $time_display,
        'icon' => $row['icon']
      ];
    }

    return $activities;
  } catch (Exception $e) {
    error_log("Error fetching tutor activities: " . $e->getMessage());
    return [];
  }
}

/**
 * Get recent activities for a student
 * @param int $student_id Student's user ID
 * @param int $limit Number of activities to return (default: 10)
 * @return array Recent activities
 */
function getStudentRecentActivities($student_id, $limit = 10)
{
  global $conn;

  try {
    $activities = [];

    // Get recent activities for student
    $sql = "SELECT 'session' as activity_type, 
                   CONCAT('Session completed for ', p.name) as message,
                   s.session_date as activity_time,
                   'academic-cap' as icon
            FROM sessions s
            JOIN enrollments e ON s.enrollment_id = e.id
            JOIN programs p ON e.program_id = p.id
            WHERE e.student_user_id = ? AND s.status = 'completed'
            
            UNION ALL
            
            SELECT 'jitsi' as activity_type,
                   CONCAT('Attended ', p.name, ' session') as message,
                   jm.created_at as activity_time,
                   'video-camera' as icon
            FROM jitsi_meetings jm
            JOIN jitsi_participants jp ON jm.id = jp.meeting_id
            JOIN programs p ON jm.program_id = p.id
            JOIN enrollments e ON p.id = e.program_id
            WHERE e.student_user_id = ? AND jp.user_id = ? AND jm.status IN ('completed', 'ended')
            
            UNION ALL
            
            SELECT 'enrollment' as activity_type,
                   CONCAT('Enrolled in ', p.name) as message,
                   e.created_at as activity_time,
                   'trophy' as icon
            FROM enrollments e
            JOIN programs p ON e.program_id = p.id
            WHERE e.student_user_id = ? AND e.status = 'active'
            
            ORDER BY activity_time DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiii', $student_id, $student_id, $student_id, $student_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      // Format the time
      $time_diff = time() - strtotime($row['activity_time']);
      
      if ($time_diff < 0) {
        $time_display = 'Recently';
      } elseif ($time_diff < 60) {
        $time_display = 'Just now';
      } elseif ($time_diff < 3600) {
        $time_display = floor($time_diff / 60) . ' minutes ago';
      } elseif ($time_diff < 86400) {
        $time_display = floor($time_diff / 3600) . ' hours ago';
      } else {
        $time_display = floor($time_diff / 86400) . ' days ago';
      }

      $activities[] = [
        'message' => $row['message'],
        'time' => $time_display,
        'icon' => $row['icon']
      ];
    }

    return $activities;
  } catch (Exception $e) {
    error_log("Error fetching student activities: " . $e->getMessage());
    return [];
  }
}

/**
 * Generate empty state HTML
 * @param string $type Type of empty state
 * @param string $message Custom message
 * @return string HTML for empty state
 */
function getEmptyState($type, $message = null)
{
  $defaults = [
    'programs' => [
      'icon' => 'book-open',
      'title' => 'No Programs Available',
      'message' => 'No tutoring programs have been created yet. Create your first program to get started.'
    ],
    'students' => [
      'icon' => 'users',
      'title' => 'No Students Enrolled',
      'message' => 'No students have registered yet. Students will appear here once they sign up.'
    ],
    'tutors' => [
      'icon' => 'academic-cap',
      'title' => 'No Tutors Available',
      'message' => 'No tutors have been assigned yet. Add tutors to start offering programs.'
    ],
    'payments' => [
      'icon' => 'currency-dollar',
      'title' => 'No Payments Found',
      'message' => 'No payment records exist yet. Payments will appear here once transactions are made.'
    ],
    'enrollments' => [
      'icon' => 'clipboard-document-list',
      'title' => 'No Enrollments Yet',
      'message' => 'No students have enrolled in this program yet.'
    ],
    'sessions' => [
      'icon' => 'calendar-days',
      'title' => 'No Sessions Scheduled',
      'message' => 'No sessions are scheduled for today.'
    ]
  ];

  $config = $defaults[$type] ?? $defaults['programs'];
  $displayMessage = $message ?? $config['message'];

  return '
    <div class="text-center py-12">
        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">' . htmlspecialchars($config['title']) . '</h3>
        <p class="text-gray-500 max-w-md mx-auto">' . htmlspecialchars($displayMessage) . '</p>
    </div>';
}

/**
 * Check if database tables exist and have sample data
 * @return array Status of database tables
 */
function checkDatabaseStatus()
{
  global $conn;

  $tables = ['users', 'programs', 'enrollments', 'payments', 'sessions'];
  $status = [];

  foreach ($tables as $table) {
    try {
      $result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
      if ($result && $row = $result->fetch_assoc()) {
        $status[$table] = (int)$row['count'];
      } else {
        $status[$table] = 0;
      }
    } catch (Exception $e) {
      $status[$table] = 0;
    }
  }

  return $status;
}

/**
 * Calculate program status based on start and end dates using PST timezone
 * @param string|null $startDate Program start date (Y-m-d format)
 * @param string|null $endDate Program end date (Y-m-d format)
 * @param string|null $currentStatus Current database status
 * @return string Calculated status: 'upcoming', 'ongoing', or 'ended'
 */
function calculateProgramStatusFromDates($startDate, $endDate, $currentStatus = null)
{
  // Use PST timezone for consistent date calculation
  $currentDate = getPSTDateTime()->format('Y-m-d');

  // If no dates are set, fall back to current status or default
  if (empty($startDate) || empty($endDate)) {
    // Map old status values to new ones
    $statusMapping = [
      'active' => 'ongoing',
      'inactive' => 'ended',
      'draft' => 'upcoming'
    ];

    return $statusMapping[$currentStatus] ?? 'upcoming';
  }

  // Calculate status based on dates
  if ($currentDate < $startDate) {
    return 'upcoming';
  } elseif ($currentDate >= $startDate && $currentDate <= $endDate) {
    return 'ongoing';
  } else {
    return 'ended';
  }
}

/**
 * Get programs with calculated status based on dates
 * @param array $filters Optional filters for programs
 * @return array Programs with calculated status
 */
function getProgramsWithCalculatedStatus($filters = [])
{
  global $conn;

  try {
    $whereClause = "";
    $params = [];
    $types = "";

    // Build WHERE clause based on filters
    if (!empty($filters['id'])) {
      $whereClause .= ($whereClause ? " AND " : " WHERE ") . "p.id = ?";
      $params[] = $filters['id'];
      $types .= "i";
    }

    if (!empty($filters['search'])) {
      $whereClause .= ($whereClause ? " AND " : " WHERE ") . "(p.name LIKE ? OR p.description LIKE ?)";
      $searchTerm = "%" . $filters['search'] . "%";
      $params[] = $searchTerm;
      $params[] = $searchTerm;
      $types .= "ss";
    }

    if (!empty($filters['tutor_id'])) {
      $whereClause .= ($whereClause ? " AND " : " WHERE ") . "p.tutor_id = ?";
      $params[] = $filters['tutor_id'];
      $types .= "i";
    }

    // Note: We don't filter by status in SQL anymore because we want to filter by calculated status
    // The status filtering will be done after calculating the status in PHP

    // Base query with enhanced information
    $query = "SELECT DISTINCT 
                p.*,
                CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                tp.specializations as tutor_specializations,
                (SELECT COUNT(*) FROM enrollments e1 
                 WHERE e1.program_id = p.id 
                 AND e1.status = 'active') as active_enrollments,
                (SELECT COUNT(*) FROM enrollments e2 
                 WHERE e2.program_id = p.id 
                 AND e2.status = 'pending') as pending_enrollments,
                (SELECT COUNT(*) FROM program_materials pm 
                 WHERE pm.program_id = p.id) as materials_count,
                (SELECT COUNT(*) FROM program_sessions ps 
                 WHERE ps.program_id = p.id) as sessions_count,
                CASE 
                  WHEN p.start_date > CURDATE() THEN 'upcoming'
                  WHEN p.end_date < CURDATE() THEN 'completed'
                  WHEN p.start_date <= CURDATE() AND p.end_date >= CURDATE() THEN 'ongoing'
                  ELSE 'draft'
                END as current_status
              FROM programs p 
              LEFT JOIN tutor_profiles tp ON p.tutor_id = tp.user_id
              LEFT JOIN enrollments e ON p.id = e.program_id
              $whereClause
              GROUP BY p.id 
              ORDER BY 
                CASE 
                  WHEN p.start_date > CURDATE() THEN 1
                  WHEN p.start_date <= CURDATE() AND p.end_date >= CURDATE() THEN 2
                  ELSE 3
                END,
                p.start_date ASC";

    $result = null;
    if (!empty($params)) {
      $stmt = $conn->prepare($query);
      if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
      }
    } else {
      $result = $conn->query($query);
    }
    $programs = [];

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        // Calculate the status based on dates
        $row['calculated_status'] = calculateProgramStatusFromDates(
          $row['start_date'],
          $row['end_date'],
          $row['status']
        );

        // Add enrolled_count alias for compatibility with modal
        $row['enrolled_count'] = $row['active_enrollments'] ?? 0;

        // If filtering by status, apply the filter now
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
          if ($row['calculated_status'] !== $filters['status']) {
            continue; // Skip this program
          }
        }

        $programs[] = $row;
      }
    }

    return $programs;
  } catch (Exception $e) {
    error_log("Error in getProgramsWithCalculatedStatus: " . $e->getMessage());
    return [];
  }
}

/**
 * Get status badge configuration for display
 * @param string $status The calculated status
 * @return array Badge configuration with background, text color, and label
 */
function getStatusBadgeConfig($status)
{
  $statusConfigs = [
    'upcoming' => [
      'bg' => 'bg-blue-100',
      'text' => 'text-blue-800',
      'label' => 'Upcoming',
      'icon' => 'clock'
    ],
    'ongoing' => [
      'bg' => 'bg-green-100',
      'text' => 'text-green-800',
      'label' => 'Ongoing',
      'icon' => 'play'
    ],
    'ended' => [
      'bg' => 'bg-gray-100',
      'text' => 'text-gray-800',
      'label' => 'Ended',
      'icon' => 'check-circle'
    ]
  ];

  return $statusConfigs[$status] ?? $statusConfigs['upcoming'];
}

/**
 * Get student's enrolled programs with progress and session info
 * @param int $student_id Student ID
 * @return array Array of enrolled programs with enhanced data
 */
function getStudentPrograms($student_id)
{
  global $conn;

  try {
    $programs = [];

    $sql = "SELECT p.id, p.name, p.description, p.difficulty_level, p.fee,
                   p.start_date, p.end_date, p.status as program_status,
                   e.enrollment_date, e.status as enrollment_status,
                   CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                   COUNT(s.id) as total_sessions,
                   COUNT(CASE WHEN s.status = 'completed' THEN 1 END) as completed_sessions,
                   MIN(CASE WHEN s.session_date > NOW() AND s.status = 'scheduled' THEN CONCAT(s.session_date, ' ', s.start_time) END) as next_session_datetime
            FROM programs p
            JOIN enrollments e ON p.id = e.program_id
            LEFT JOIN users u ON p.tutor_id = u.id
            LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
            LEFT JOIN sessions s ON s.enrollment_id = e.id
            WHERE e.student_user_id = ? AND e.status = 'active'
            GROUP BY p.id, p.name, p.description, p.difficulty_level, p.fee,
                     p.start_date, p.end_date, p.status, e.enrollment_date, 
                     e.status, tp.first_name, tp.last_name
            ORDER BY e.enrollment_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $progress = 0;
      if ($row['total_sessions'] > 0) {
        $progress = round(($row['completed_sessions'] / $row['total_sessions']) * 100);
      }

      $next_session = 'TBD';
      if ($row['next_session_datetime']) {
        // Set timezone to Philippine Time for consistent display
        date_default_timezone_set('Asia/Manila');
        $next_session = date('M j, g:i A', strtotime($row['next_session_datetime']));
      }

      $programs[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'tutor' => $row['tutor_name'] ?? 'Unassigned',
        'status' => ucfirst($row['enrollment_status']),
        'progress' => $progress,
        'next_session' => $next_session,
        'level' => ucfirst($row['difficulty_level']),
        'fee' => $row['fee']
      ];
    }

    return $programs;
  } catch (Exception $e) {
    error_log("Error fetching student programs: " . $e->getMessage());
    return [];
  }
}

/**
 * Calculate remaining balance for an enrollment
 * @param int $enrollmentId
 * @return float Remaining balance
 */
function getEnrollmentBalance($enrollmentId)
{
  global $conn;

  try {
    // Get total fee for the enrollment
    $enrollmentQuery = "SELECT total_fee FROM enrollments WHERE id = ?";
    $stmt = $conn->prepare($enrollmentQuery);
    $stmt->bind_param('i', $enrollmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrollment = $result->fetch_assoc();

    if (!$enrollment) {
      return 0;
    }

    $totalFee = (float)$enrollment['total_fee'];

    // Get sum of all payments (including pending)
    $paymentsQuery = "SELECT COALESCE(SUM(amount), 0) as paid_amount FROM payments WHERE enrollment_id = ? AND status IN ('pending', 'validated', 'completed')";
    $stmt = $conn->prepare($paymentsQuery);
    $stmt->bind_param('i', $enrollmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_assoc();

    $paidAmount = (float)$payments['paid_amount'];

    return $totalFee - $paidAmount;
  } catch (Exception $e) {
    error_log("Error calculating enrollment balance: " . $e->getMessage());
    return 0;
  }
}

/**
 * Get all enrollments with their balance status for a student
 * @param int $studentUserId
 * @return array Enrollments with balance info
 */
function getStudentEnrollmentsWithBalance($studentUserId)
{
  global $conn;

  try {
    $query = "
      SELECT 
        e.*,
        p.name as program_name,
        p.fee as program_fee,
        COALESCE(SUM(payments.amount), 0) as paid_amount
      FROM enrollments e
      LEFT JOIN programs p ON e.program_id = p.id
      LEFT JOIN payments ON e.id = payments.enrollment_id AND payments.status IN ('pending', 'validated', 'completed')
      WHERE e.student_user_id = ?
      GROUP BY e.id
      ORDER BY e.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $studentUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    $enrollments = [];
    while ($row = $result->fetch_assoc()) {
      $totalFee = (float)$row['total_fee'];
      $paidAmount = (float)$row['paid_amount'];
      $balance = $totalFee - $paidAmount;

      $enrollments[] = [
        'id' => $row['id'],
        'program_name' => $row['program_name'],
        'total_fee' => $totalFee,
        'paid_amount' => $paidAmount,
        'balance' => $balance,
        'status' => $row['status'],
        'enrollment_date' => $row['enrollment_date'],
        'has_balance' => $balance > 0
      ];
    }

    return $enrollments;
  } catch (Exception $e) {
    error_log("Error fetching student enrollments with balance: " . $e->getMessage());
    return [];
  }
}

/**
 * Check if a student has any outstanding balances
 * @param int $studentUserId
 * @return bool True if student has outstanding balance
 */
function studentHasOutstandingBalance($studentUserId)
{
  $enrollments = getStudentEnrollmentsWithBalance($studentUserId);

  foreach ($enrollments as $enrollment) {
    if ($enrollment['has_balance']) {
      return true;
    }
  }

  return false;
}

/**
 * Check if a program has available capacity
 * @param int $programId
 * @return array Array with capacity info
 */
function checkProgramCapacity($programId)
{
  global $conn;

  try {
    $query = "
      SELECT 
        p.max_students as capacity,
        p.name,
        COUNT(e.id) as enrolled_count
      FROM programs p
      LEFT JOIN enrollments e ON p.id = e.program_id AND e.status IN ('pending', 'active')
      WHERE p.id = ? AND p.status = 'active'
      GROUP BY p.id, p.max_students, p.name
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $programId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
      return [
        'exists' => false,
        'has_capacity' => false,
        'available_slots' => 0,
        'capacity' => 0,
        'enrolled_count' => 0
      ];
    }

    $availableSlots = (int)$row['capacity'] - (int)$row['enrolled_count'];

    return [
      'exists' => true,
      'has_capacity' => $availableSlots > 0,
      'available_slots' => $availableSlots,
      'capacity' => (int)$row['capacity'],
      'enrolled_count' => (int)$row['enrolled_count'],
      'program_name' => $row['name']
    ];
  } catch (Exception $e) {
    error_log("Error checking program capacity: " . $e->getMessage());
    return [
      'exists' => false,
      'has_capacity' => false,
      'available_slots' => 0,
      'capacity' => 0,
      'enrolled_count' => 0
    ];
  }
}

/**
 * Check if a student is already enrolled in a program
 * @param int $studentUserId
 * @param int $programId
 * @return array Enrollment status info
 */
function checkStudentEnrollment($studentUserId, $programId)
{
  global $conn;

  try {
    $query = "SELECT id, status, enrollment_date FROM enrollments WHERE student_user_id = ? AND program_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $studentUserId, $programId);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrollment = $result->fetch_assoc();

    if ($enrollment) {
      return [
        'is_enrolled' => true,
        'enrollment_id' => $enrollment['id'],
        'status' => $enrollment['status'],
        'enrollment_date' => $enrollment['enrollment_date']
      ];
    }

    return [
      'is_enrolled' => false,
      'enrollment_id' => null,
      'status' => null,
      'enrollment_date' => null
    ];
  } catch (Exception $e) {
    error_log("Error checking student enrollment: " . $e->getMessage());
    return [
      'is_enrolled' => false,
      'enrollment_id' => null,
      'status' => null,
      'enrollment_date' => null
    ];
  }
}

/**
 * Validate enrollment eligibility (capacity + duplicate check)
 * @param int $studentUserId
 * @param int $programId
 * @return array Validation result
 */
function validateEnrollmentEligibility($studentUserId, $programId)
{
  $capacityCheck = checkProgramCapacity($programId);
  $enrollmentCheck = checkStudentEnrollment($studentUserId, $programId);

  // Check if program exists
  if (!$capacityCheck['exists']) {
    return [
      'eligible' => false,
      'reason' => 'Program not found or inactive',
      'details' => $capacityCheck
    ];
  }

  // Check if student is already enrolled
  if ($enrollmentCheck['is_enrolled']) {
    return [
      'eligible' => false,
      'reason' => 'Already enrolled in this program',
      'details' => $enrollmentCheck
    ];
  }

  // Check if program has capacity
  if (!$capacityCheck['has_capacity']) {
    return [
      'eligible' => false,
      'reason' => 'Program is at full capacity',
      'details' => $capacityCheck
    ];
  }

  return [
    'eligible' => true,
    'reason' => 'Enrollment allowed',
    'details' => [
      'capacity' => $capacityCheck,
      'enrollment' => $enrollmentCheck
    ]
  ];
}

/**
 * Calculate exact installment amounts ensuring no money is lost
 * @param float $totalAmount Total amount to divide
 * @param int $installments Number of installments
 * @return array Array of exact amounts for each installment
 */
function calculateExactInstallments($totalAmount, $installments)
{
  // Work with whole pesos only (round the total amount)
  $totalPesos = intval(round($totalAmount));
  $baseAmount = intval($totalPesos / $installments);
  $remainder = $totalPesos % $installments;

  $amounts = [];
  for ($i = 0; $i < $installments; $i++) {
    // Add 1 peso to the first $remainder installments to distribute the remainder
    $amount = $baseAmount + ($i < $remainder ? 1 : 0);
    $amounts[] = $amount;
  }

  return $amounts;
}

/**
 * Generate payment schedule based on payment plan
 * @param int $enrollmentId
 * @param float $totalFee
 * @param int $paymentOption (1=full, 2=two payments, 3=three payments)
 * @param string $paymentMethod
 * @return array Payment schedule with amounts and due dates
 */
function generatePaymentSchedule($enrollmentId, $totalFee, $paymentOption, $paymentMethod)
{
  $payments = [];
  $baseDate = new DateTime(); // Start from today

  switch ($paymentOption) {
    case 1: // Full Payment
      $payments[] = [
        'enrollment_id' => $enrollmentId,
        'amount' => $totalFee,
        'payment_method' => $paymentMethod,
        'due_date' => $baseDate->format('Y-m-d'),
        'status' => 'pending',
        'notes' => 'Full Payment',
        'installment_number' => 1,
        'total_installments' => 1
      ];
      break;

    case 2: // Two Payments
      $amounts = calculateExactInstallments($totalFee, 2);

      // First payment - due today
      $payments[] = [
        'enrollment_id' => $enrollmentId,
        'amount' => $amounts[0],
        'payment_method' => $paymentMethod,
        'due_date' => $baseDate->format('Y-m-d'),
        'status' => 'pending',
        'notes' => 'Installment 1 of 2',
        'installment_number' => 1,
        'total_installments' => 2
      ];

      // Second payment - due in 2 weeks (14 days)
      $secondDate = clone $baseDate;
      $secondDate->add(new DateInterval('P14D'));
      $payments[] = [
        'enrollment_id' => $enrollmentId,
        'amount' => $amounts[1],
        'payment_method' => $paymentMethod,
        'due_date' => $secondDate->format('Y-m-d'),
        'status' => 'pending',
        'notes' => 'Installment 2 of 2 (due in 2 weeks)',
        'installment_number' => 2,
        'total_installments' => 2
      ];
      break;

    case 3: // Three Payments
      $amounts = calculateExactInstallments($totalFee, 3);

      // First payment - due today
      $payments[] = [
        'enrollment_id' => $enrollmentId,
        'amount' => $amounts[0],
        'payment_method' => $paymentMethod,
        'due_date' => $baseDate->format('Y-m-d'),
        'status' => 'pending',
        'notes' => 'Installment 1 of 3',
        'installment_number' => 1,
        'total_installments' => 3
      ];

      // Second payment - due in 2 weeks (14 days)
      $secondDate = clone $baseDate;
      $secondDate->add(new DateInterval('P14D'));
      $payments[] = [
        'enrollment_id' => $enrollmentId,
        'amount' => $amounts[1],
        'payment_method' => $paymentMethod,
        'due_date' => $secondDate->format('Y-m-d'),
        'status' => 'pending',
        'notes' => 'Installment 2 of 3 (due in 2 weeks)',
        'installment_number' => 2,
        'total_installments' => 3
      ];

      // Third payment - due in 4 weeks (28 days)
      $thirdDate = clone $baseDate;
      $thirdDate->add(new DateInterval('P28D'));
      $payments[] = [
        'enrollment_id' => $enrollmentId,
        'amount' => $amounts[2],
        'payment_method' => $paymentMethod,
        'due_date' => $thirdDate->format('Y-m-d'),
        'status' => 'pending',
        'notes' => 'Installment 3 of 3 (due in 4 weeks)',
        'installment_number' => 3,
        'total_installments' => 3
      ];
      break;
  }

  return $payments;
}

/**
 * Create multiple payment records based on payment schedule
 * @param array $paymentSchedule Payment schedule from generatePaymentSchedule()
 * @return bool Success status
 */
function createPaymentSchedule($paymentSchedule)
{
  global $conn;

  try {
    $conn->autocommit(false);

    $paymentQuery = "INSERT INTO payments (enrollment_id, amount, payment_date, payment_method, status, notes, due_date, installment_number, total_installments) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($paymentQuery);

    foreach ($paymentSchedule as $payment) {
      $stmt->bind_param(
        'idssssii',
        $payment['enrollment_id'],
        $payment['amount'],
        $payment['payment_method'],
        $payment['status'],
        $payment['notes'],
        $payment['due_date'],
        $payment['installment_number'],
        $payment['total_installments']
      );
      $stmt->execute();
    }

    $conn->commit();
    $conn->autocommit(true);
    return true;
  } catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    error_log("Error creating payment schedule: " . $e->getMessage());
    return false;
  }
}

/**
 * Validate a payment (mark as validated/rejected)
 * @param string $payment_id Payment ID
 * @param int $validated_by User ID of validator
 * @param string $status Status (validated/rejected)
 * @param string $notes Optional validation notes
 * @return int Number of affected rows
 */
function validatePayment($payment_id, $validated_by, $status = 'validated', $notes = null)
{
  global $conn;

  try {
    if (!in_array($status, ['validated', 'rejected'])) {
      throw new Exception('Invalid validation status');
    }

    // Extract actual ID from payment_id format (PAY-YYYYMMDD-XXX)
    if (preg_match('/PAY-\d{8}-(\d+)/', $payment_id, $matches)) {
      $actual_id = intval($matches[1]);
    } else if (is_numeric($payment_id)) {
      $actual_id = intval($payment_id);
    } else {
      throw new Exception('Invalid payment ID format');
    }

    // Begin transaction for atomic updates
    $conn->begin_transaction();

    try {
      // First check if payment exists and can be validated
      $check_sql = "SELECT p.*, e.student_user_id 
                    FROM payments p
                    INNER JOIN enrollments e ON p.enrollment_id = e.id
                    WHERE p.id = ? AND p.status = 'pending'
                    FOR UPDATE";
                    
      $check_stmt = $conn->prepare($check_sql);
      if (!$check_stmt) {
        throw new Exception("Failed to prepare payment check: " . $conn->error);
      }
      
      $check_stmt->bind_param('i', $actual_id);
      if (!$check_stmt->execute()) {
        throw new Exception("Failed to check payment: " . $check_stmt->error);
      }
      
      $payment = $check_stmt->get_result()->fetch_assoc();
      if (!$payment) {
        throw new Exception("Payment not found or already processed");
      }

      // Update payment status
      $sql = "UPDATE payments 
              SET status = ?, 
                  validated_by = ?, 
                  validated_at = NOW()";
      $params = [$status, $validated_by];
      $types = "si";

      // Add notes if provided
      if ($notes !== null && trim($notes) !== '') {
        $sql .= ", notes = ?";
        $params[] = trim($notes);
        $types .= "s";
      }

      $sql .= " WHERE id = ?";
      $params[] = $actual_id;
      $types .= "i";

      $stmt = $conn->prepare($sql);
      if (!$stmt) {
        throw new Exception('Failed to prepare payment update statement: ' . $conn->error);
      }

      $stmt->bind_param($types, ...$params);

      if (!$stmt->execute()) {
        throw new Exception('Failed to execute payment update: ' . $stmt->error);
      }

      $affected_rows = $stmt->affected_rows;
      $stmt->close();

      // If payment was validated, check and update enrollment status
      if ($status === 'validated' && $affected_rows > 0) {
        updateEnrollmentStatusOnPaymentValidation($actual_id);
      }

      // Commit transaction
      $conn->commit();

      return $affected_rows;
    } catch (Exception $e) {
      // Rollback transaction on error
      $conn->rollback();
      throw $e;
    }
  } catch (Exception $e) {
    error_log("Error validating payment: " . $e->getMessage());
    throw $e;
  }
}

/**
 * Update enrollment status when a payment is validated
 * Handles both full payments and installment logic
 */
function updateEnrollmentStatusOnPaymentValidation($payment_id)
{
  global $conn;

  try {
    // Get payment details including enrollment information
    $payment_sql = "SELECT p.id, p.enrollment_id, p.amount, p.installment_number, p.total_installments,
                           e.id as enrollment_id, e.status as current_enrollment_status, e.total_fee,
                           pr.name as program_name, pr.fee as program_fee
                    FROM payments p
                    JOIN enrollments e ON p.enrollment_id = e.id
                    JOIN programs pr ON e.program_id = pr.id
                    WHERE p.id = ?";
    
    $stmt = $conn->prepare($payment_sql);
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment_info) {
      error_log("Payment not found for enrollment status update: $payment_id");
      return false;
    }

    $enrollment_id = $payment_info['enrollment_id'];
    $current_status = $payment_info['current_enrollment_status'];
    $installment_number = $payment_info['installment_number'];
    $total_installments = $payment_info['total_installments'];

    // Determine new enrollment status based on payment type
    $new_status = null;
    $reason = '';

    // Check if this is a full payment or installment
    if ($total_installments === null || $total_installments <= 1) {
      // FULL PAYMENT - Activate enrollment immediately
      if ($current_status === 'pending') {
        $new_status = 'active';
        $reason = 'Full payment validated';
      }
    } else {
      // INSTALLMENT PAYMENT
      if ($installment_number === 1) {
        // First installment - Activate enrollment
        if ($current_status === 'pending') {
          $new_status = 'active';
          $reason = 'First installment payment validated';
        }
      } else {
        // Subsequent installments - Check if enrollment should remain active
        // Get all validated payments for this enrollment
        $validated_payments_sql = "SELECT COUNT(*) as validated_count, 
                                          SUM(amount) as total_paid
                                   FROM payments 
                                   WHERE enrollment_id = ? AND status = 'validated'";
        
        $stmt = $conn->prepare($validated_payments_sql);
        $stmt->bind_param("i", $enrollment_id);
        $stmt->execute();
        $payment_summary = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // If enrollment was paused due to missed payments, reactivate it
        if ($current_status === 'paused') {
          $new_status = 'active';
          $reason = "Installment $installment_number payment validated - reactivating enrollment";
        }
      }
    }

    // Update enrollment status if needed
    if ($new_status && $new_status !== $current_status) {
      $update_sql = "UPDATE enrollments 
                     SET status = ? 
                     WHERE id = ?";
      
      $stmt = $conn->prepare($update_sql);
      $stmt->bind_param("si", $new_status, $enrollment_id);
      
      if ($stmt->execute()) {
        error_log("Enrollment $enrollment_id status updated from '$current_status' to '$new_status': $reason");
        
        // Log the enrollment status change
        $log_sql = "INSERT INTO activity_logs (user_id, action, details) 
                    VALUES (?, 'enrollment_status_change', ?)";
        $log_details = "Enrollment $enrollment_id status changed from '$current_status' to '$new_status' due to payment validation. Reason: $reason";
        
        $log_stmt = $conn->prepare($log_sql);
        $student_id = getStudentIdFromEnrollment($enrollment_id);
        $log_stmt->bind_param("is", $student_id, $log_details);
        $log_stmt->execute();
        $log_stmt->close();
        
        return true;
      } else {
        error_log("Failed to update enrollment status: " . $stmt->error);
        return false;
      }
    }

    return true; // No update needed, but processing was successful
  } catch (Exception $e) {
    error_log("Error updating enrollment status on payment validation: " . $e->getMessage());
    return false;
  }
}

/**
 * Check and update enrollment status based on payment failures (for overdue payments)
 * This function should be called periodically to pause enrollments with overdue payments
 */
function checkAndUpdateEnrollmentStatusForOverduePayments()
{
  global $conn;

  try {
    // Find enrollments with overdue installment payments
    $overdue_sql = "SELECT DISTINCT e.id as enrollment_id, e.status as current_status,
                           pr.name as program_name,
                           COUNT(p.id) as overdue_count
                    FROM enrollments e
                    JOIN programs pr ON e.program_id = pr.id
                    JOIN payments p ON e.id = p.enrollment_id
                    WHERE e.status = 'active' 
                      AND p.status = 'overdue'
                      AND p.total_installments > 1
                    GROUP BY e.id, e.status, pr.name
                    HAVING overdue_count > 0";
    
    $result = $conn->query($overdue_sql);
    $updated_count = 0;

    while ($row = $result->fetch_assoc()) {
      $enrollment_id = $row['enrollment_id'];
      
      // Pause the enrollment
      $update_sql = "UPDATE enrollments 
                     SET status = 'paused', updated_at = NOW() 
                     WHERE id = ?";
      
      $stmt = $conn->prepare($update_sql);
      $stmt->bind_param("i", $enrollment_id);
      
      if ($stmt->execute()) {
        $updated_count++;
        error_log("Enrollment $enrollment_id paused due to overdue installment payments");
        
        // Log the status change
        $log_sql = "INSERT INTO activity_logs (user_id, action, details) 
                    VALUES (?, 'enrollment_status_change', ?)";
        $log_details = "Enrollment $enrollment_id paused due to overdue installment payments in program: " . $row['program_name'];
        
        $log_stmt = $conn->prepare($log_sql);
        $student_id = getStudentIdFromEnrollment($enrollment_id);
        $log_stmt->bind_param("is", $student_id, $log_details);
        $log_stmt->execute();
        $log_stmt->close();
      }
      $stmt->close();
    }

    return $updated_count;
  } catch (Exception $e) {
    error_log("Error checking overdue payment enrollments: " . $e->getMessage());
    return 0;
  }
}

/**
 * Helper function to get student ID from enrollment
 */
function getStudentIdFromEnrollment($enrollment_id)
{
  global $conn;
  
  $sql = "SELECT student_user_id FROM enrollments WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $enrollment_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();
  
  return $row ? $row['student_user_id'] : null;
}

/**
 * Get payments for a specific student
 * @param string $student_username Student username
 * @return array Array of payment records for the student
 */
function getStudentPayments($student_username)
{
  global $conn;

  try {
    $sql = "SELECT 
                   p.id,
                   CONCAT('PAY-', DATE_FORMAT(p.created_at, '%Y%m%d'), '-', LPAD(p.id, 3, '0')) as payment_id,
                   e.id as enrollment_id,
                   u.username as student_username,
                   COALESCE(CONCAT(sp.first_name, ' ', sp.last_name), CONCAT(u.username, ' (Student)')) as student_name,
                   sp.student_id,
                   pr.name as program_name,
                   p.amount,
                   p.payment_method,
                   p.payment_date,
                   p.due_date,
                   p.reference_number,
                   p.notes,
                   p.installment_number,
                   p.total_installments,
                   v.username as validated_by_username,
                   COALESCE(v.username, 'System') as validated_by_name,
                   p.validated_at,
                   p.created_at,
                   CASE 
                     WHEN p.status = 'validated' THEN 'validated'
                     WHEN p.status = 'rejected' THEN 'rejected'
                     WHEN p.status = 'pending' AND p.payment_date IS NOT NULL AND p.reference_number IS NOT NULL THEN 'pending_validation'
                     WHEN p.status = 'pending' AND p.due_date < CURDATE() THEN 'overdue'
                     WHEN p.status = 'pending' AND p.due_date = CURDATE() THEN 'due_today'
                     WHEN p.status = 'pending' THEN 'due'
                     ELSE 'due'
                   END as status
            FROM payments p
            LEFT JOIN enrollments e ON p.enrollment_id = e.id
            LEFT JOIN users u ON e.student_user_id = u.id
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            LEFT JOIN programs pr ON e.program_id = pr.id
            LEFT JOIN users v ON p.validated_by = v.id
            WHERE u.username = ?
            ORDER BY p.created_at DESC, p.installment_number ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $stmt->bind_param("s", $student_username);

    if (!$stmt->execute()) {
      throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $payments = [];

    while ($row = $result->fetch_assoc()) {
      $payments[] = $row;
    }

    $stmt->close();
    return $payments;
  } catch (Exception $e) {
    error_log("Error getting student payments: " . $e->getMessage());
    return [];
  }
}

/**
 * Check if a payment has an attachment/proof uploaded
 */
function hasPaymentAttachment($payment_id) {
  global $conn;
  
  try {
    // Extract numeric payment ID if needed
    $numeric_payment_id = $payment_id;
    if (preg_match('/PAY-\d{8}-0*(\d+)/', $payment_id, $matches)) {
      $numeric_payment_id = intval($matches[1]);
    }
    
    $stmt = $conn->prepare("
      SELECT COUNT(*) as count 
      FROM payment_attachments pa 
      JOIN payments p ON pa.payment_id = p.id
      WHERE p.id = ?
    ");
    $stmt->bind_param('i', $numeric_payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] > 0;
  } catch (Exception $e) {
    error_log("Error checking payment attachment: " . $e->getMessage());
    return false;
  }
}

/**
 * Get enrolled programs for a student with progress and stream data
 */
function getStudentEnrolledPrograms($student_id) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("
      SELECT DISTINCT
        p.id,
        p.name,
        p.description,
        p.fee,
        p.session_type,
        p.location,
        p.start_date,
        p.end_date,
        p.start_time,
        p.end_time,
        p.days,
        p.category,
        p.duration_weeks,
        p.age_group,
        p.difficulty_level,
        CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
        MIN(e.status) as enrollment_status,
        MIN(e.enrollment_date) as enrollment_date,
        CASE 
          WHEN p.end_date < CURDATE() THEN 'completed'
          WHEN p.start_date <= CURDATE() AND p.end_date >= CURDATE() THEN 'ongoing'
          WHEN p.start_date > CURDATE() THEN 'upcoming'
          ELSE 'upcoming'
        END as program_status,
        DATEDIFF(CURDATE(), p.start_date) as days_since_start,
        DATEDIFF(p.end_date, p.start_date) as total_program_days,
        -- Calculate sessions attended (this is a placeholder - can be enhanced later)
        0 as sessions_attended,
        -- Calculate total sessions (basic calculation based on duration and days)
        CASE 
          WHEN p.days IS NOT NULL AND p.duration_weeks IS NOT NULL THEN
            (LENGTH(p.days) - LENGTH(REPLACE(p.days, ',', '')) + 1) * p.duration_weeks
          ELSE p.duration_weeks * 3
        END as total_sessions
      FROM enrollments e
      JOIN programs p ON e.program_id = p.id
      LEFT JOIN users u ON p.tutor_id = u.id
      LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
      WHERE e.student_user_id = ? 
        AND e.status IN ('active', 'paused')
      GROUP BY p.id, p.name, p.description, p.fee, p.session_type, p.location, 
               p.start_date, p.end_date, p.start_time, p.end_time, p.days, 
               p.category, p.duration_weeks, p.age_group, p.difficulty_level,
               CONCAT(tp.first_name, ' ', tp.last_name)
      ORDER BY 
        CASE MIN(e.status) 
          WHEN 'active' THEN 1 
          WHEN 'paused' THEN 2 
          ELSE 3 
        END,
        p.start_date ASC
    ");
    
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $programs = [];
    while ($row = $result->fetch_assoc()) {
      // Calculate progress percentage
      $progress_percentage = 0;
      
      if ($row['program_status'] === 'ongoing' && $row['total_program_days'] > 0) {
        $progress_percentage = min(100, max(0, ($row['days_since_start'] / $row['total_program_days']) * 100));
      } elseif ($row['program_status'] === 'completed') {
        $progress_percentage = 100;
      }
      
      $row['progress_percentage'] = round($progress_percentage);
      
      // Format next session info
      $row['next_session'] = calculateNextSession($row);
      
      $programs[] = $row;
    }
    
    $stmt->close();
    return $programs;
    
  } catch (Exception $e) {
    error_log("Error getting student enrolled programs: " . $e->getMessage());
    return [];
  }
}

/**
 * Calculate next session information for a program
 */
function calculateNextSession($program) {
  try {
    $current_date = new DateTime();
    $start_date = new DateTime($program['start_date']);
    $end_date = new DateTime($program['end_date']);
    
    // If program hasn't started yet
    if ($current_date < $start_date) {
      return [
        'date' => $start_date->format('l, M j'),
        'time' => $program['start_time'] ? date('g:i A', strtotime($program['start_time'])) : '9:00 AM',
        'status' => 'upcoming'
      ];
    }
    
    // If program has ended
    if ($current_date > $end_date) {
      return [
        'date' => 'Program Completed',
        'time' => '',
        'status' => 'completed'
      ];
    }
    
    // Parse program days (e.g., "Mon, Wed, Fri")
    $days = explode(',', str_replace(' ', '', $program['days'] ?? 'Mon,Wed,Fri'));
    $day_map = [
      'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 
      'Fri' => 5, 'Sat' => 6, 'Sun' => 0
    ];
    
    // Find next session day
    $next_session_date = null;
    $current_day = (int)$current_date->format('N'); // 1=Monday, 7=Sunday
    if ($current_day == 7) $current_day = 0; // Convert Sunday to 0
    
    // Look for next session within the next 7 days
    for ($i = 0; $i <= 7; $i++) {
      $check_date = clone $current_date;
      $check_date->add(new DateInterval("P{$i}D"));
      $check_day = (int)$check_date->format('N');
      if ($check_day == 7) $check_day = 0;
      
      foreach ($days as $day) {
        if (isset($day_map[$day]) && $day_map[$day] == $check_day) {
          $next_session_date = $check_date;
          break 2;
        }
      }
    }
    
    if ($next_session_date) {
      return [
        'date' => $next_session_date->format('l, M j'),
        'time' => $program['start_time'] ? date('g:i A', strtotime($program['start_time'])) : '9:00 AM',
        'status' => 'scheduled'
      ];
    }
    
    return [
      'date' => 'Schedule TBD',
      'time' => '',
      'status' => 'pending'
    ];
    
  } catch (Exception $e) {
    error_log("Error calculating next session: " . $e->getMessage());
    return [
      'date' => 'Schedule TBD',
      'time' => '',
      'status' => 'pending'
    ];
  }
}

/**
 * Get program stream content for a specific program and student
 */
function getProgramStreamContent($program_id, $student_id) {
  global $conn;
  
  try {
    // Get program details
    $stmt = $conn->prepare("
      SELECT p.*, 
             CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
             e.status as enrollment_status,
             e.enrollment_date
      FROM programs p
      LEFT JOIN users u ON p.tutor_id = u.id
      LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
      LEFT JOIN enrollments e ON p.id = e.program_id AND e.student_user_id = ?
      WHERE p.id = ?
    ");
    
    $stmt->bind_param('ii', $student_id, $program_id);
    $stmt->execute();
    $program = $stmt->get_result()->fetch_assoc();
    
    if (!$program) {
      return null;
    }
    
    // Generate sample sessions based on program schedule
    $sessions = generateProgramSessions($program);
    
    // Get real materials uploaded for this program
    $materials = getProgramMaterials($program_id, 'program_material');
    
    // Generate sample assignments
    $assignments = generateProgramAssignments($program);
    
    return [
      'program' => $program,
      'sessions' => $sessions,
      'materials' => $materials,
      'assignments' => $assignments
    ];
    
  } catch (Exception $e) {
    error_log("Error getting program stream content: " . $e->getMessage());
    return null;
  }
}

/**
 * Generate program sessions based on schedule
 */
function generateProgramSessions($program) {
  $sessions = [];
  
  try {
    $start_date = new DateTime($program['start_date']);
    $end_date = new DateTime($program['end_date']);
    $current_date = new DateTime();
    
    // Parse days (e.g., "Mon, Wed, Fri")
    $days = array_map('trim', explode(',', $program['days'] ?? 'Mon,Wed,Fri'));
    $day_map = [
      'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 
      'Fri' => 5, 'Sat' => 6, 'Sun' => 0
    ];
    
    $session_count = 0;
    $max_sessions = ($program['duration_weeks'] ?? 8) * count($days);
    
    $date = clone $start_date;
    while ($date <= $end_date && $session_count < $max_sessions) {
      $day_name = $date->format('D');
      
      if (in_array($day_name, $days)) {
        $session_count++;
        $is_past = $date < $current_date;
        $is_today = $date->format('Y-m-d') === $current_date->format('Y-m-d');
        
        $sessions[] = [
          'id' => $session_count,
          'title' => "Session {$session_count}: " . generateSessionTitle($program['category'], $session_count),
          'date' => $date->format('Y-m-d'),
          'time' => $program['start_time'] ?? '09:00:00',
          'duration' => calculateSessionDuration($program['start_time'], $program['end_time']),
          'type' => $program['session_type'],
          'status' => $is_past ? 'completed' : ($is_today ? 'today' : 'upcoming'),
          'description' => generateSessionDescription($program['category'], $session_count),
          'meeting_link' => $program['session_type'] === 'online' ? $program['video_call_link'] : null,
          'location' => $program['session_type'] === 'in-person' ? $program['location'] : null
        ];
      }
      
      $date->add(new DateInterval('P1D'));
    }
    
    return $sessions;
    
  } catch (Exception $e) {
    error_log("Error generating program sessions: " . $e->getMessage());
    return [];
  }
}

/**
 * Generate session titles based on category
 */
function generateSessionTitle($category, $session_number) {
  $titles = [
    'Mathematics' => [
      'Introduction to Algebra', 'Linear Equations', 'Quadratic Functions', 'Polynomial Operations',
      'Exponential Functions', 'Logarithmic Functions', 'Trigonometry Basics', 'Advanced Calculus'
    ],
    'Science' => [
      'Scientific Method', 'Atomic Structure', 'Chemical Bonds', 'States of Matter',
      'Ecosystem Dynamics', 'Cellular Biology', 'Genetics Basics', 'Evolution Theory'
    ],
    'Language' => [
      'Grammar Fundamentals', 'Vocabulary Building', 'Reading Comprehension', 'Writing Techniques',
      'Literature Analysis', 'Poetry Appreciation', 'Public Speaking', 'Creative Writing'
    ],
    'Technology' => [
      'Programming Basics', 'Data Structures', 'Algorithms', 'Web Development',
      'Database Design', 'Software Testing', 'Mobile Apps', 'AI Fundamentals'
    ]
  ];
  
  $category_titles = $titles[$category] ?? $titles['Mathematics'];
  $index = ($session_number - 1) % count($category_titles);
  
  return $category_titles[$index];
}

/**
 * Generate session descriptions
 */
function generateSessionDescription($category, $session_number) {
  $descriptions = [
    'Mathematics' => "Explore mathematical concepts with practical applications and problem-solving exercises.",
    'Science' => "Discover scientific principles through experiments and real-world examples.",
    'Language' => "Develop language skills through interactive exercises and discussions.",
    'Technology' => "Learn technology concepts with hands-on coding and project work."
  ];
  
  return $descriptions[$category] ?? $descriptions['Mathematics'];
}

/**
 * Calculate session duration in minutes
 */
function calculateSessionDuration($start_time, $end_time) {
  if (!$start_time || !$end_time) {
    return 90; // Default 90 minutes
  }
  
  $start = strtotime($start_time);
  $end = strtotime($end_time);
  
  return ($end - $start) / 60;
}

/**
 * Generate program materials
 */
function generateProgramMaterials($program) {
  $materials = [];
  $material_count = rand(8, 15);
  
  $material_types = ['PDF', 'Video', 'Audio', 'Document', 'Presentation', 'Worksheet'];
  $base_names = [
    'Introduction Guide', 'Chapter Notes', 'Practice Exercises', 'Video Lecture',
    'Reference Material', 'Study Guide', 'Homework Assignment', 'Supplementary Reading'
  ];
  
  for ($i = 1; $i <= $material_count; $i++) {
    $type = $material_types[array_rand($material_types)];
    $name = $base_names[array_rand($base_names)] . " {$i}";
    
    $materials[] = [
      'id' => $i,
      'title' => $name,
      'type' => $type,
      'size' => rand(100, 5000) . ' KB',
      'uploaded_date' => date('Y-m-d', strtotime("-" . rand(1, 30) . " days")),
      'description' => "Essential material for understanding key concepts in {$program['category']}."
    ];
  }
  
  return $materials;
}

/**
 * Generate program assignments
 */
function generateProgramAssignments($program) {
  $assignments = [];
  $assignment_count = rand(4, 8);
  
  $assignment_types = ['Quiz', 'Project', 'Essay', 'Problem Set', 'Presentation', 'Lab Report'];
  
  for ($i = 1; $i <= $assignment_count; $i++) {
    $type = $assignment_types[array_rand($assignment_types)];
    $due_date = date('Y-m-d', strtotime("+".rand(1, 60)." days"));
    $is_past_due = $due_date < date('Y-m-d');
    
    $assignments[] = [
      'id' => $i,
      'title' => "{$type} {$i}",
      'type' => $type,
      'due_date' => $due_date,
      'status' => $is_past_due ? (rand(0, 1) ? 'submitted' : 'overdue') : 'pending',
      'points' => rand(50, 100),
      'description' => "Complete this {$type} to demonstrate your understanding of the course material.",
      'submission_type' => rand(0, 1) ? 'online' : 'in_person'
    ];
  }
  
  return $assignments;
}

/**
 * Get programs assigned to a specific tutor
 * @param int $tutor_user_id The user ID of the tutor
 * @return array List of programs assigned to the tutor with enrollment and progress information
 */
function getTutorAssignedPrograms($tutor_user_id) {
  global $conn;

  try {
    $sql = "
      SELECT DISTINCT
        p.id,
        p.name,
        p.description,
        p.age_group,
        p.duration_weeks,
        p.fee,
        p.category,
        p.difficulty_level,
        p.status,
        p.max_students,
        p.session_type,
        p.location,
        p.start_date,
        p.end_date,
        p.start_time,
        p.end_time,
        p.days,
        p.created_at,
        p.updated_at,
        -- Calculate program status based on dates
        CASE 
          WHEN p.end_date < CURDATE() THEN 'completed'
          WHEN p.start_date <= CURDATE() AND p.end_date >= CURDATE() THEN 'ongoing'
          WHEN p.start_date > CURDATE() THEN 'upcoming'
          ELSE 'upcoming'
        END as program_status,
        -- Count enrolled students
        COUNT(DISTINCT e.student_user_id) as enrolled_students,
        -- Count materials uploaded for this program
        COUNT(DISTINCT f.id) as materials_count,
        -- Calculate average progress (placeholder - can be enhanced with actual session attendance)
        CASE 
          WHEN p.start_date > CURDATE() THEN 0
          WHEN p.end_date < CURDATE() THEN 100
          ELSE GREATEST(0, LEAST(100, ROUND((DATEDIFF(CURDATE(), p.start_date) / NULLIF(DATEDIFF(p.end_date, p.start_date), 0)) * 100)))
        END as progress_percentage,
        -- Next session calculation (will be replaced with calculateNextSession function)
        'Next Session' as placeholder_next_session,
        -- Session time
        CASE 
          WHEN p.start_time IS NOT NULL AND p.end_time IS NOT NULL THEN 
            CONCAT(TIME_FORMAT(p.start_time, '%l:%i %p'), ' - ', TIME_FORMAT(p.end_time, '%l:%i %p'))
          ELSE 'Time TBD'
        END as session_time
      FROM programs p
      INNER JOIN tutor_profiles tp ON p.tutor_id = tp.user_id
      LEFT JOIN enrollments e ON p.id = e.program_id AND e.status IN ('active', 'paused')
      LEFT JOIN file_uploads f ON p.id = f.related_id AND f.upload_type = 'assignment'
      WHERE tp.user_id = ? 
      GROUP BY p.id, p.name, p.description, p.age_group, p.duration_weeks, p.fee, 
               p.category, p.difficulty_level, p.status, p.max_students, p.session_type, 
               p.location, p.start_date, p.end_date, p.start_time, p.end_time, p.days, 
               p.created_at, p.updated_at
      ORDER BY 
        CASE 
          WHEN p.start_date <= CURDATE() AND p.end_date >= CURDATE() THEN 1  -- ongoing first
          WHEN p.start_date > CURDATE() THEN 2                              -- upcoming second
          WHEN p.end_date < CURDATE() THEN 3                               -- completed last
          ELSE 4
        END,
        p.start_date ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tutor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $programs = [];
    
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        // Use the improved calculateNextSession function
        $nextSessionInfo = calculateNextSession($row);
        
        // Create next session info array
        $row['next_session'] = [
          'date' => $nextSessionInfo['date'],
          'time' => $nextSessionInfo['time'],
          'status' => $nextSessionInfo['status']
        ];
        
        // Calculate students count
        $row['students_count'] = $row['enrolled_students'];
        
        // Determine type for filtering
        $row['type'] = $row['session_type'];
        
        $programs[] = $row;
      }
    }
    
    return $programs;
  } catch (Exception $e) {
    error_log("Error fetching tutor assigned programs: " . $e->getMessage());
    return [];
  }
}

/**
 * Get tutor's full name from user_id
 * @param int $user_id The user ID of the tutor
 * @return string Full name of the tutor
 */
function getTutorFullName($user_id) {
  global $conn;

  try {
    $sql = "SELECT first_name, last_name FROM tutor_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
      return trim($row['first_name'] . ' ' . $row['last_name']) ?: 'Tutor';
    }
    
    return 'Tutor';
  } catch (Exception $e) {
    error_log("Error fetching tutor full name: " . $e->getMessage());
    return 'Tutor';
  }
}

/**
 * Get student's full name from user_id
 * @param int $user_id The user ID of the student
 * @return string Full name of the student
 */
function getStudentFullName($user_id) {
  global $conn;

  try {
    $sql = "SELECT first_name, last_name FROM student_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
      return trim($row['first_name'] . ' ' . $row['last_name']) ?: 'Student';
    }
    
    return 'Student';
  } catch (Exception $e) {
    error_log("Error fetching student full name: " . $e->getMessage());
    return 'Student';
  }
}

/**
 * Get students enrolled in a program
 * @param int $program_id The program ID
 * @return array Array of enrolled students with their details
 */
function getProgramStudents($program_id) {
  global $conn;

  try {
    $sql = "
      SELECT DISTINCT
        e.student_user_id as user_id,
        u.user_id as username,
        sp.first_name,
        sp.last_name,
        u.email,
        MIN(e.enrollment_date) as enrollment_date,
        e.status as enrollment_status,
        MAX(e.id) as enrollment_id,
        -- Calculate attendance rate based on completed sessions
        COALESCE(
          (SELECT COUNT(*) FROM sessions sess 
           INNER JOIN enrollments e2 ON sess.enrollment_id = e2.id 
           WHERE e2.student_user_id = e.student_user_id AND e2.program_id = ? AND sess.status = 'completed') / 
          NULLIF((SELECT COUNT(*) FROM sessions sess 
                  INNER JOIN enrollments e2 ON sess.enrollment_id = e2.id 
                  WHERE e2.student_user_id = e.student_user_id AND e2.program_id = ?), 0) * 100, 
          0
        ) as attendance_rate,
        -- Get latest session status
        (SELECT sess.status FROM sessions sess 
         INNER JOIN enrollments e2 ON sess.enrollment_id = e2.id 
         WHERE e2.student_user_id = e.student_user_id AND e2.program_id = ? 
         ORDER BY sess.session_date DESC LIMIT 1) as latest_session_status
      FROM enrollments e
      INNER JOIN users u ON e.student_user_id = u.id
      INNER JOIN student_profiles sp ON u.id = sp.user_id
      WHERE e.program_id = ? AND e.status IN ('active', 'paused')
      GROUP BY e.student_user_id, u.user_id, sp.first_name, sp.last_name, u.email, e.status
      ORDER BY sp.first_name, sp.last_name
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $program_id, $program_id, $program_id, $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $row['attendance_rate'] = round($row['attendance_rate'], 1);
        // Convert session status to attendance equivalent
        $row['latest_attendance'] = ($row['latest_session_status'] === 'completed') ? '1' : '0';
        $students[] = $row;
      }
    }
    
    return $students;
  } catch (Exception $e) {
    error_log("Error fetching program students: " . $e->getMessage());
    return [];
  }
}

/**
 * Get materials/files uploaded for a program
 * @param int $program_id The program ID
 * @param string $filter Filter value (optional)
 * @param string $filter_type Type of filter: 'upload_type' or 'material_type' (default: 'upload_type')
 * @return array Array of materials/files
 */
function getProgramMaterials($program_id, $filter = null, $filter_type = 'upload_type', $student_user_id = null) {
  global $conn;

  try {
    $sql = "
      SELECT 
        pm.id as material_id,
        pm.title,
        pm.description,
        pm.material_type,
        pm.is_required,
        pm.sort_order,
        pm.created_at,
        f.id as file_id,
        f.filename,
        f.original_filename,
        f.file_path,
        f.file_size,
        f.mime_type,
        f.upload_type,
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
        a.id as assessment_id,
        a.title as assessment_title,
        a.total_points as assessment_total_points,
        a.time_limit as assessment_time_limit";
    
    // Add submission status if student_user_id is provided
    if ($student_user_id) {
      $sql .= ",
        CASE 
          WHEN pm.material_type = 'assignment' THEN
            CASE 
              WHEN asub.id IS NOT NULL THEN 1 
              ELSE 0 
            END
          WHEN a.id IS NOT NULL THEN
            CASE 
              WHEN aa.id IS NOT NULL THEN 1 
              ELSE 0 
            END
          ELSE NULL
        END as is_submitted,
        asub.submission_date,
        asub.score as assignment_score,
        asub.feedback as assignment_feedback,
        asub.graded_at as assignment_graded_at,
        grader.username as assignment_graded_by,
        CASE 
          WHEN asub.submission_date > asn.due_date THEN 1
          ELSE 0
        END as submission_is_late,
        aa.id as assessment_attempt_id,
        aa.status as assessment_status,
        aa.submitted_at as assessment_submitted_at,
        aa.score as assessment_score,
        aa.percentage as assessment_percentage,
        aa.comments as assessment_comments,
        aa.updated_at as assessment_graded_at";
    }
    
    $sql .= "
      FROM program_materials pm
      LEFT JOIN file_uploads f ON pm.file_upload_id = f.id
      LEFT JOIN users u ON f.user_id = u.id
      LEFT JOIN tutor_profiles tp ON u.id = tp.user_id AND u.role = 'tutor'
      LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
      LEFT JOIN assessments a ON pm.id = a.material_id";
    
    // Add assignment submission join if student_user_id is provided
    if ($student_user_id) {
      $sql .= "
      LEFT JOIN assignments asn ON pm.id = asn.material_id AND pm.material_type = 'assignment'
      LEFT JOIN assignment_submissions asub ON asn.id = asub.assignment_id AND asub.student_user_id = ?
      LEFT JOIN users grader ON asub.graded_by = grader.id
      LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_user_id = ? AND aa.status IN ('submitted', 'late_submission', 'graded')
        AND aa.id = (
          SELECT MAX(aa2.id) 
          FROM assessment_attempts aa2 
          WHERE aa2.assessment_id = a.id 
          AND aa2.student_user_id = ? 
          AND aa2.status IN ('submitted', 'late_submission', 'graded')
        )";
    }
    
    $sql .= "
      WHERE pm.program_id = ?";
    
    $params = [];
    $types = '';
    
    // Add student_user_id parameter if provided
    if ($student_user_id) {
      $params[] = $student_user_id; // For assignment submissions
      $params[] = $student_user_id; // For assessment attempts
      $params[] = $student_user_id; // For assessment attempts subquery
      $types .= 'iii';
    }
    
    $params[] = $program_id;
    $types .= 'i';
    
    if ($filter) {
      if ($filter_type === 'material_type') {
        $sql .= " AND pm.material_type = ?";
      } else {
        // Default to upload_type for backward compatibility
        $sql .= " AND f.upload_type = ?";
      }
      $params[] = $filter;
      $types .= 's';
    }
    
    // Order by newest first (created_at DESC), then by sort_order
    $sql .= " ORDER BY pm.created_at DESC, pm.sort_order ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $materials = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        // Safely compute uploader name
        $firstName = $row['uploader_first_name'] ?? '';
        $lastName = $row['uploader_last_name'] ?? '';
        $username = $row['uploader_username'] ?? 'Unknown';
        $row['uploader_name'] = trim($firstName . ' ' . $lastName) ?: $username;
        
        // Safely compute file size
        $fileSize = $row['file_size'] ?? 0;
        $row['file_size_formatted'] = formatFileSize($fileSize);
        
        // Safely compute dates
        $createdAt = $row['created_at'] ?? date('Y-m-d H:i:s');
        $row['upload_date_formatted'] = date('M j, Y', strtotime($createdAt));
        $row['upload_time_formatted'] = date('M j, Y, g:i A', strtotime($createdAt));
        
        $materials[] = $row;
      }
    }
    
    return $materials;
  } catch (Exception $e) {
    error_log("Error fetching program materials: " . $e->getMessage());
    return [];
  }
}

/**
 * Get sessions for a program
 * @param int $program_id The program ID
 * @param int $limit Limit number of sessions returned (optional)
 * @return array Array of sessions
 */
function getProgramSessions($program_id, $limit = null) {
  global $conn;

  try {
    $sql = "
      SELECT 
        s.id,
        s.session_date,
        s.duration,
        s.session_type,
        s.session_mode,
        s.status,
        s.notes,
        e.id as enrollment_id,
        sp.first_name as student_first_name,
        sp.last_name as student_last_name,
        p.name as program_name
      FROM sessions s
      INNER JOIN enrollments e ON s.enrollment_id = e.id
      INNER JOIN programs p ON e.program_id = p.id
      INNER JOIN users u ON e.student_user_id = u.id
      INNER JOIN student_profiles sp ON u.id = sp.user_id
      WHERE e.program_id = ?
      ORDER BY s.session_date DESC";
    
    if ($limit) {
      $sql .= " LIMIT ?";
    }
    
    $stmt = $conn->prepare($sql);
    if ($limit) {
      $stmt->bind_param('ii', $program_id, $limit);
    } else {
      $stmt->bind_param('i', $program_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $row['student_name'] = trim($row['student_first_name'] . ' ' . $row['student_last_name']);
        $row['session_date_formatted'] = date('M j, Y', strtotime($row['session_date']));
        $row['session_time_formatted'] = date('g:i A', strtotime($row['session_date'])) . ' (' . $row['duration'] . ' min)';
        $sessions[] = $row;
      }
    }
    
    return $sessions;
  } catch (Exception $e) {
    error_log("Error fetching program sessions: " . $e->getMessage());
    return [];
  }
}

/**
 * Format file size in human readable format
 * @param int $size File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($size) {
  $units = ['B', 'KB', 'MB', 'GB', 'TB'];
  $factor = floor((strlen($size) - 1) / 3);
  return sprintf("%.1f", $size / pow(1024, $factor)) . ' ' . $units[$factor];
}

/**
 * Get upcoming sessions for a tutor
 * @param int $tutor_user_id The tutor's user ID
 * @param int $limit Limit number of sessions (default 5)
 * @return array Array of upcoming sessions
 */
function getTutorUpcomingSessions($tutor_user_id, $limit = 5) {
  global $conn;

  try {
    $sql = "
      SELECT 
        s.id,
        s.session_date,
        s.start_time,
        s.end_time,
        s.status,
        s.notes,
        p.id as program_id,
        p.name as program_name,
        p.session_type as program_session_type,
        p.video_call_link,
        p.location,
        COUNT(DISTINCT e.student_user_id) as student_count,
        GROUP_CONCAT(DISTINCT CONCAT(sp.first_name, ' ', sp.last_name) SEPARATOR ', ') as student_names
      FROM sessions s
      INNER JOIN enrollments e ON s.enrollment_id = e.id
      INNER JOIN programs p ON e.program_id = p.id
      LEFT JOIN users u ON e.student_user_id = u.id
      LEFT JOIN student_profiles sp ON u.id = sp.user_id
      WHERE p.tutor_id = ? 
        AND DATE(s.session_date) >= CURDATE()
        AND s.status IN ('scheduled', 'ongoing')
      GROUP BY s.id, s.session_date, s.start_time, s.end_time, s.status, s.notes, p.id, p.name, p.session_type, p.video_call_link, p.location
      ORDER BY s.session_date ASC, s.start_time ASC
      LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $tutor_user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        // Create compatibility fields
        $row['session_date'] = $row['session_date'] . ' ' . $row['start_time'];
        $row['session_date_formatted'] = date('l, M j', strtotime($row['session_date']));
        $row['session_time_formatted'] = date('g:i A', strtotime($row['start_time']));
        
        // Calculate duration from start_time and end_time
        $start_time = strtotime($row['start_time']);
        $end_time = strtotime($row['end_time']);
        $duration_minutes = ($end_time - $start_time) / 60;
        $hours = floor($duration_minutes / 60);
        $minutes = $duration_minutes % 60;
        $row['session_duration_formatted'] = $hours . 'h ' . $minutes . 'm';
        
        $sessions[] = $row;
      }
    }
    
    return $sessions;
  } catch (Exception $e) {
    error_log("Error fetching tutor upcoming sessions: " . $e->getMessage());
    return [];
  }
}

/**
 * Get tutor's schedule for a specific month
 * @param int $tutor_user_id The tutor's user ID
 * @param int $year The year
 * @param int $month The month (1-12)
 * @return array Array of sessions for the month
 */
function getTutorMonthlySchedule($tutor_user_id, $year, $month) {
  global $conn;

  try {
    $sql = "
      SELECT 
        s.id,
        s.session_date,
        s.start_time,
        s.end_time,
        s.status,
        s.notes,
        p.id as program_id,
        p.name as program_name,
        p.session_type as program_session_type,
        p.video_call_link,
        p.location,
        COUNT(DISTINCT e.student_user_id) as student_count,
        GROUP_CONCAT(DISTINCT CONCAT(sp.first_name, ' ', sp.last_name) SEPARATOR ', ') as student_names
      FROM sessions s
      INNER JOIN enrollments e ON s.enrollment_id = e.id
      INNER JOIN programs p ON e.program_id = p.id
      LEFT JOIN users u ON e.student_user_id = u.id
      LEFT JOIN student_profiles sp ON u.id = sp.user_id
      WHERE p.tutor_id = ? 
        AND YEAR(s.session_date) = ?
        AND MONTH(s.session_date) = ?
        AND s.status IN ('scheduled', 'completed')
      GROUP BY s.id, s.session_date, s.start_time, s.end_time, s.status, s.notes, p.id, p.name, p.session_type, p.video_call_link, p.location
      ORDER BY s.session_date ASC, s.start_time ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $tutor_user_id, $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $day = date('j', strtotime($row['session_date']));
        if (!isset($sessions[$day])) {
          $sessions[$day] = [];
        }
        
        // Create session_date field for compatibility
        $row['session_date'] = $row['session_date'] . ' ' . $row['start_time'];
        $row['session_time_formatted'] = date('g:i A', strtotime($row['start_time']));
        $row['session_end_time'] = date('g:i A', strtotime($row['end_time']));
        
        // Calculate duration
        $start = new DateTime($row['session_date']);
        $end = new DateTime($row['session_date']);
        $end->setTime(date('H', strtotime($row['end_time'])), date('i', strtotime($row['end_time'])));
        $duration = $start->diff($end);
        $row['session_duration_formatted'] = $duration->h . 'h';
        if ($duration->i > 0) {
          $row['session_duration_formatted'] .= ' ' . $duration->i . 'min';
        }
        
        // Assign default color if none set
        $colors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444'];
        $row['color_code'] = $colors[$row['program_id'] % count($colors)];
        
        $sessions[$day][] = $row;
      }
    }
    
    return $sessions;
  } catch (Exception $e) {
    error_log("Error fetching tutor monthly schedule: " . $e->getMessage());
    return [];
  }
}

/**
 * Get session details for editing/viewing
 * @param int $session_id The session ID
 * @param int $tutor_user_id The tutor's user ID (for security)
 * @return array|null Session details or null if not found/no access
 */
function getSessionDetails($session_id, $tutor_user_id) {
  global $conn;

  try {
    $sql = "
      SELECT 
        s.id,
        s.session_date,
        s.duration,
        s.session_type,
        s.session_mode,
        s.status,
        s.notes,
        p.id as program_id,
        p.name as program_name,
        p.session_type as program_session_type,
        p.video_call_link,
        p.location,
        COUNT(DISTINCT e.student_user_id) as student_count,
        GROUP_CONCAT(DISTINCT CONCAT(sp.first_name, ' ', sp.last_name) ORDER BY sp.first_name SEPARATOR ', ') as student_names,
        GROUP_CONCAT(DISTINCT e.student_user_id ORDER BY sp.first_name SEPARATOR ',') as student_ids
      FROM sessions s
      INNER JOIN enrollments e ON s.enrollment_id = e.id
      INNER JOIN programs p ON e.program_id = p.id
      LEFT JOIN users u ON e.student_user_id = u.id
      LEFT JOIN student_profiles sp ON u.id = sp.user_id
      WHERE s.id = ?
        AND p.tutor_id = ?
      GROUP BY s.id, s.session_date, s.duration, s.session_type, s.session_mode, s.status, s.notes, p.id, p.name, p.session_type, p.video_call_link, p.location
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $session_id, $tutor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
      $row['session_date_formatted'] = date('l, F j, Y', strtotime($row['session_date']));
      $row['session_time_formatted'] = date('g:i A', strtotime($row['session_date']));
      $row['session_end_time'] = date('g:i A', strtotime($row['session_date'] . ' + ' . $row['duration'] . ' minutes'));
      $row['session_duration_formatted'] = ($row['duration'] / 60) . ' hours';
      
      // Convert student_ids string to array
      $row['student_ids'] = $row['student_ids'] ? explode(',', $row['student_ids']) : [];
      
      return $row;
    }
    
    return null;
    
  } catch (Exception $e) {
    error_log("Error fetching session details: " . $e->getMessage());
    return null;
  }
}

/**
 * Get or create sessions for a program based on its schedule
 * @param int $program_id Program ID
 * @return array Array of session dates
 */
function getOrCreateProgramSessions($program_id) {
  global $conn;
  
  try {
    // Get program details
    $stmt = $conn->prepare("
      SELECT start_date, end_date, start_time, end_time, days 
      FROM programs 
      WHERE id = ?
    ");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
      return [];
    }
    
    $program = $result->fetch_assoc();
    
    // Generate session dates based on program schedule
    $sessions = [];
    $start_date = new DateTime($program['start_date']);
    $end_date = new DateTime($program['end_date']);
    
    // Parse program days
    $days_str = strtolower($program['days'] ?? '');
    $day_mapping = [
      'monday' => 1, 'mon' => 1,
      'tuesday' => 2, 'tue' => 2, 'tues' => 2,
      'wednesday' => 3, 'wed' => 3,
      'thursday' => 4, 'thu' => 4, 'thurs' => 4,
      'friday' => 5, 'fri' => 5,
      'saturday' => 6, 'sat' => 6,
      'sunday' => 0, 'sun' => 0
    ];
    
    $program_days = [];
    foreach (preg_split('/[,\s]+/', $days_str) as $day) {
      $clean_day = trim($day);
      if (isset($day_mapping[$clean_day])) {
        $program_days[] = $day_mapping[$clean_day];
      }
    }
    
    // If no valid days, default to Mon/Wed/Fri
    if (empty($program_days)) {
      $program_days = [1, 3, 5];
    }
    
    // Generate sessions
    $current_date = clone $start_date;
    while ($current_date <= $end_date) {
      if (in_array((int)$current_date->format('w'), $program_days)) {
        $session_date = $current_date->format('Y-m-d');
        
        // Create session if it doesn't exist
        $stmt = $conn->prepare("
          INSERT IGNORE INTO sessions (program_id, session_date, start_time, end_time, status)
          VALUES (?, ?, ?, ?, 'scheduled')
        ");
        $stmt->bind_param('isss', $program_id, $session_date, $program['start_time'], $program['end_time']);
        $stmt->execute();
        
        $sessions[] = $session_date;
      }
      $current_date->add(new DateInterval('P1D'));
    }
    
    return $sessions;
    
  } catch (Exception $e) {
    error_log("Error creating program sessions: " . $e->getMessage());
    return [];
  }
}

/**
 * Get attendance statistics for a program
 * @param int $program_id Program ID
 * @return array Attendance statistics
 */
function getProgramAttendanceStats($program_id) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("
      SELECT 
        COUNT(DISTINCT s.id) as total_sessions,
        COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_sessions,
        COUNT(DISTINCT a.student_user_id) as students_with_attendance,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as total_late,
        COUNT(a.id) as total_attendance_records
      FROM sessions s
      LEFT JOIN attendance a ON s.id = a.session_id
      WHERE s.program_id = ?
    ");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = $result->fetch_assoc();
    
    // Calculate attendance rate
    if ($stats['total_attendance_records'] > 0) {
      $stats['attendance_rate'] = round(($stats['total_present'] / $stats['total_attendance_records']) * 100, 1);
    } else {
      $stats['attendance_rate'] = 0;
    }
    
    return $stats;
    
  } catch (Exception $e) {
    error_log("Error getting attendance stats: " . $e->getMessage());
    return [
      'total_sessions' => 0,
      'completed_sessions' => 0,
      'students_with_attendance' => 0,
      'total_present' => 0,
      'total_absent' => 0,
      'total_late' => 0,
      'total_attendance_records' => 0,
      'attendance_rate' => 0
    ];
  }
}

/**
 * Get grades for all students in a specific program
 * @param int $program_id The program ID
 * @return array Array of student grades with student details
 */
function getProgramGrades($program_id) {
  global $conn;
  
  try {
    // Get all students enrolled in the program with their grades
    $stmt = $conn->prepare("
      SELECT 
        e.student_user_id,
        u.user_id as user_id_string,
        u.username,
        u.email,
        sp.first_name,
        sp.last_name,
        g.grade,
        g.grade_type,
        g.comments,
        g.updated_at
      FROM enrollments e
      JOIN users u ON e.student_user_id = u.id
      JOIN student_profiles sp ON e.student_user_id = sp.user_id
      LEFT JOIN grades g ON e.program_id = g.program_id AND u.user_id = g.student_user_id
      WHERE e.program_id = ? AND e.status = 'active'
      ORDER BY sp.first_name, sp.last_name
    ");
    
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
      $student_id = $row['student_user_id'];
      
      // Initialize student if not exists
      if (!isset($students[$student_id])) {
        $students[$student_id] = [
          'student_user_id' => $student_id,
          'user_id_string' => $row['user_id_string'],
          'username' => $row['username'],
          'first_name' => $row['first_name'],
          'last_name' => $row['last_name'],
          'email' => $row['email'],
          'grades' => [],
          'final_grade' => null,
          'average_grade' => 0
        ];
      }
      
      // Add grade if exists
      if ($row['grade'] !== null) {
        $students[$student_id]['grades'][] = [
          'grade' => floatval($row['grade']),
          'grade_type' => $row['grade_type'],
          'comments' => $row['comments'],
          'updated_at' => $row['updated_at']
        ];
        
        // Set final grade if this is a final grade
        if ($row['grade_type'] === 'final') {
          $students[$student_id]['final_grade'] = floatval($row['grade']);
        }
      }
    }
    
    // Calculate average grades for each student
    foreach ($students as &$student) {
      if (!empty($student['grades'])) {
        $totalGrades = array_sum(array_column($student['grades'], 'grade'));
        $student['average_grade'] = round($totalGrades / count($student['grades']), 2);
      }
    }
    
    return array_values($students);
    
  } catch (Exception $e) {
    error_log("Error getting program grades: " . $e->getMessage());
    return [];
  }
}

/**
 * Update or insert a student's grade for a specific program
 * @param int $program_id The program ID
 * @param mixed $student_user_id The student user ID
 * @param float $grade The grade value
 * @param string $grade_type The type of grade (quiz, assignment, midterm, final, project, participation)
 * @param string $comments Optional comments about the grade
 * @return array Result with success status and message
 */
function updateStudentGrade($program_id, $student_user_id, $grade, $grade_type = 'final', $comments = '') {
  global $conn;
  
  // Debug logging
  error_log("updateStudentGrade called with: program_id=$program_id, student_user_id=$student_user_id, grade=$grade, grade_type=$grade_type");
  
  try {
    // Validate inputs
    if (!is_numeric($grade) || $grade < 0 || $grade > 100) {
      return ['success' => false, 'message' => 'Grade must be between 0 and 100'];
    }
    
    $valid_grade_types = ['quiz', 'assignment', 'midterm', 'final', 'project', 'participation'];
    if (!in_array($grade_type, $valid_grade_types)) {
      return ['success' => false, 'message' => 'Invalid grade type'];
    }
    
    // Check if student is enrolled in the program
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments e JOIN users u ON e.student_user_id = u.id WHERE e.program_id = ? AND u.user_id = ? AND e.status = 'active'");
    $stmt->bind_param('is', $program_id, $student_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrollment = $result->fetch_assoc();
    
    if ($enrollment['count'] == 0) {
      return ['success' => false, 'message' => 'Student is not enrolled in this program'];
    }
    
    // Check if grade already exists for this student, program, and grade type
    $stmt = $conn->prepare("SELECT id FROM grades WHERE program_id = ? AND student_user_id = ? AND grade_type = ?");
    $stmt->bind_param('iss', $program_id, $student_user_id, $grade_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_grade = $result->fetch_assoc();
    
    if ($existing_grade) {
      // Update existing grade
      $stmt = $conn->prepare("
        UPDATE grades 
        SET grade = ?, comments = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
      ");
      $stmt->bind_param('dsi', $grade, $comments, $existing_grade['id']);
    } else {
      // Insert new grade
      $stmt = $conn->prepare("
        INSERT INTO grades (program_id, student_user_id, grade, grade_type, comments) 
        VALUES (?, ?, ?, ?, ?)
      ");
      $stmt->bind_param('isdss', $program_id, $student_user_id, $grade, $grade_type, $comments);
    }
    
    if ($stmt->execute()) {
      return ['success' => true, 'message' => 'Grade updated successfully'];
    } else {
      return ['success' => false, 'message' => 'Failed to update grade'];
    }
    
  } catch (Exception $e) {
    error_log("Error updating student grade: " . $e->getMessage());
    return ['success' => false, 'message' => 'Database error occurred'];
  }
}

/**
 * Calculate grade statistics for a program
 * @param int $program_id The program ID
 * @return array Grade statistics including average, highest, lowest, etc.
 */
function calculateGradeStatistics($program_id) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("
      SELECT 
        COUNT(DISTINCT student_user_id) as total_students,
        AVG(grade) as average_grade,
        MIN(grade) as lowest_grade,
        MAX(grade) as highest_grade,
        COUNT(CASE WHEN grade >= 90 THEN 1 END) as a_grades,
        COUNT(CASE WHEN grade >= 80 AND grade < 90 THEN 1 END) as b_grades,
        COUNT(CASE WHEN grade >= 70 AND grade < 80 THEN 1 END) as c_grades,
        COUNT(CASE WHEN grade < 70 THEN 1 END) as failing_grades
      FROM grades 
      WHERE program_id = ? AND grade_type = 'final'
    ");
    
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    // Format the results
    return [
      'total_students' => intval($stats['total_students']) ?: 0,
      'average_grade' => round(floatval($stats['average_grade']) ?: 0, 2),
      'lowest_grade' => floatval($stats['lowest_grade']) ?: 0,
      'highest_grade' => floatval($stats['highest_grade']) ?: 0,
      'a_grades' => intval($stats['a_grades']) ?: 0,
      'b_grades' => intval($stats['b_grades']) ?: 0,
      'c_grades' => intval($stats['c_grades']) ?: 0,
      'failing_grades' => intval($stats['failing_grades']) ?: 0
    ];
    
  } catch (Exception $e) {
    error_log("Error calculating grade statistics: " . $e->getMessage());
    return [
      'total_students' => 0,
      'average_grade' => 0,
      'lowest_grade' => 0,
      'highest_grade' => 0,
      'a_grades' => 0,
      'b_grades' => 0,
      'c_grades' => 0,
      'failing_grades' => 0
    ];
  }
}

/**
 * Get grade history for a student in a program
 * @param int $program_id The program ID
 * @param mixed $student_user_id The student user ID
 * @return array Array of all grades for the student in the program
 */
function getStudentGradeHistory($program_id, $student_user_id) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("
      SELECT grade, grade_type, comments, created_at, updated_at
      FROM grades 
      WHERE program_id = ? AND student_user_id = ?
      ORDER BY created_at DESC
    ");
    
    $stmt->bind_param('is', $program_id, $student_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $grades = [];
    while ($row = $result->fetch_assoc()) {
      $grades[] = [
        'grade' => floatval($row['grade']),
        'grade_type' => $row['grade_type'],
        'comments' => $row['comments'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
      ];
    }
    
    return $grades;
    
  } catch (Exception $e) {
    error_log("Error getting student grade history: " . $e->getMessage());
    return [];
  }
}

/**
 * Check if tutor has access to a specific student in a specific program
 * @param int $tutor_user_id The tutor's user ID
 * @param int $student_id The student's user ID
 * @param int $program_id The program ID
 * @return bool True if tutor has access, false otherwise
 */
function tutorHasAccessToStudent($tutor_user_id, $student_id, $program_id) {
  global $conn;

  try {
    $sql = "
      SELECT COUNT(*) as count
      FROM enrollments e
      INNER JOIN programs p ON e.program_id = p.id
      WHERE p.tutor_id = ? 
        AND e.student_user_id = ? 
        AND p.id = ?
        AND e.status IN ('active', 'paused')
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $tutor_user_id, $student_id, $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
    
  } catch (Exception $e) {
    error_log("Error checking tutor access to student: " . $e->getMessage());
    return false;
  }
}

/**
 * Check if tutor has access to a student in any of their programs
 * @param int $tutor_user_id The tutor's user ID
 * @param int $student_id The student's user ID
 * @return bool True if tutor has access, false otherwise
 */
function tutorHasAccessToStudentGeneral($tutor_user_id, $student_id) {
  global $conn;

  try {
    $sql = "
      SELECT COUNT(*) as count
      FROM enrollments e
      INNER JOIN programs p ON e.program_id = p.id
      WHERE p.tutor_id = ? 
        AND e.student_user_id = ?
        AND e.status IN ('active', 'paused')
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $tutor_user_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
    
  } catch (Exception $e) {
    error_log("Error checking tutor access to student: " . $e->getMessage());
    return false;
  }
}

/**
 * Get detailed student information including performance metrics
 * @param int $student_id The student's user ID
 * @param int $program_id The program ID
 * @return array|null Student details or null if not found
 */
function getDetailedStudentInfo($student_id, $program_id) {
  global $conn;

  try {
    $sql = "
      SELECT 
        sp.first_name,
        sp.last_name,
        CONCAT(sp.first_name, ' ', sp.last_name) as full_name,
        u.email,
        sp.phone,
        sp.age_group,
        e.enrollment_date,
        e.status as enrollment_status,
        e.expected_end_date,
        p.name as program_name,
        p.duration_weeks,
        
        -- Calculate attendance rate
        COALESCE(
          (SELECT COUNT(*) FROM sessions sess WHERE sess.enrollment_id = e.id AND sess.status = 'completed') / 
          NULLIF((SELECT COUNT(*) FROM sessions sess WHERE sess.enrollment_id = e.id), 0) * 100, 
          0
        ) as attendance_rate,
        
        -- Calculate average grade
        COALESCE(
          (SELECT AVG(CAST(ag.grade as DECIMAL(5,2))) 
           FROM assignment_grades ag 
           INNER JOIN assignments a ON ag.assignment_id = a.id 
           WHERE ag.student_user_id = ? AND a.program_id = ?), 
          0
        ) as average_grade,
        
        -- Count completed assignments
        COALESCE(
          (SELECT COUNT(*) 
           FROM assignment_grades ag 
           INNER JOIN assignments a ON ag.assignment_id = a.id 
           WHERE ag.student_user_id = ? AND a.program_id = ? AND ag.grade IS NOT NULL), 
          0
        ) as completed_assignments
        
      FROM enrollments e
      INNER JOIN users u ON e.student_user_id = u.id
      INNER JOIN student_profiles sp ON u.id = sp.user_id
      INNER JOIN programs p ON e.program_id = p.id
      WHERE e.student_user_id = ? AND e.program_id = ?
      LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiiii', $student_id, $program_id, $student_id, $program_id, $student_id, $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
      // Get recent activity
      $row['recent_activity'] = getStudentRecentActivity($student_id, $program_id);
      return $row;
    }
    
    return null;
    
  } catch (Exception $e) {
    error_log("Error getting detailed student info: " . $e->getMessage());
    return null;
  }
}

/**
 * Get student's recent activity
 * @param int $student_id The student's user ID
 * @param int $program_id The program ID
 * @return array Array of recent activities
 */
function getStudentRecentActivity($student_id, $program_id) {
  global $conn;

  try {
    $activities = [];
    
    // Get recent session attendance
    $sql = "
      SELECT 
        'Session Attended' as description,
        sess.session_date as date,
        'positive' as type
      FROM sessions sess
      INNER JOIN enrollments e ON sess.enrollment_id = e.id
      WHERE e.student_user_id = ? AND e.program_id = ? AND sess.status = 'completed'
      ORDER BY sess.session_date DESC
      LIMIT 3
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $student_id, $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
      $activities[] = $row;
    }
    
    // Get recent assignment submissions
    $sql = "
      SELECT 
        CONCAT('Assignment: ', a.title, ' - Grade: ', ag.grade, '%') as description,
        ag.submitted_at as date,
        CASE WHEN CAST(ag.grade as DECIMAL) >= 70 THEN 'positive' ELSE 'neutral' END as type
      FROM assignment_grades ag
      INNER JOIN assignments a ON ag.assignment_id = a.id
      WHERE ag.student_user_id = ? AND a.program_id = ? AND ag.grade IS NOT NULL
      ORDER BY ag.submitted_at DESC
      LIMIT 2
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $student_id, $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
      $activities[] = $row;
    }
    
    // Sort by date
    usort($activities, function($a, $b) {
      return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return array_slice($activities, 0, 5); // Return top 5 most recent
    
  } catch (Exception $e) {
    error_log("Error getting student recent activity: " . $e->getMessage());
    return [];
  }
}

/**
 * Send a message to a student
 * @param int $tutor_user_id The tutor's user ID
 * @param int $student_id The student's user ID
 * @param string $message_type Type of message
 * @param string $subject Message subject
 * @param string $content Message content
 * @param bool $send_email Whether to send email notification
 * @param bool $save_to_history Whether to save to communication history
 * @return bool True if successful, false otherwise
 */
function sendStudentMessage($tutor_user_id, $student_id, $message_type, $subject, $content, $send_email = true, $save_to_history = true) {
  global $conn;

  try {
    $conn->begin_transaction();
    
    // Save to communication history if requested
    if ($save_to_history) {
      $sql = "
        INSERT INTO communication_history (
          sender_user_id, recipient_user_id, message_type, subject, content, sent_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
      ";
      
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('iisss', $tutor_user_id, $student_id, $message_type, $subject, $content);
      
      if (!$stmt->execute()) {
        throw new Exception("Failed to save message to history");
      }
    }
    
    // Send email notification if requested
    if ($send_email) {
      // Get student email
      $sql = "SELECT email FROM users WHERE id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('i', $student_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($row = $result->fetch_assoc()) {
        $student_email = $row['email'];
        
        // Get tutor name
        $tutor_name = getTutorFullName($tutor_user_id);
        
        // Prepare email
        $email_subject = "[TPLearn] " . $subject;
        $email_body = "Dear Student,\n\n" . $content . "\n\nBest regards,\n" . $tutor_name . "\nTPLearn Tutor";
        
        // In a real application, you would use a proper email service like PHPMailer
        // For now, we'll just log the email
        error_log("Email would be sent to: $student_email with subject: $email_subject");
        
        // You can integrate with your email service here
        // mail($student_email, $email_subject, $email_body);
      }
    }
    
    $conn->commit();
    return true;
    
  } catch (Exception $e) {
    $conn->rollback();
    error_log("Error sending student message: " . $e->getMessage());
    return false;
  }
}
/**
 * Create a new session
 * @param int $program_id The program ID
 * @param string $session_datetime Session date and time
 * @param int $duration Duration in minutes
 * @param string $session_type Session type (online/in-person/hybrid)
 * @param string $location Location or meeting link
 * @param string $notes Session notes
 * @param bool $repeat_session Whether to repeat the session
 * @param string $repeat_frequency Repeat frequency (weekly/biweekly/monthly)
 * @return bool True if successful, false otherwise
 */
function createSession($program_id, $session_datetime, $duration, $session_type, $location = '', $notes = '', $repeat_session = false, $repeat_frequency = 'weekly') {
  global $conn;

  try {
    $conn->begin_transaction();

    // Get all enrolled students for this program
    $sql = "SELECT id FROM enrollments WHERE program_id = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $enrollment_ids = [];
    while ($row = $result->fetch_assoc()) {
      $enrollment_ids[] = $row['id'];
    }

    if (empty($enrollment_ids)) {
      throw new Exception("No active enrollments found for this program");
    }

    // Create sessions for each enrollment
    $sessions_to_create = [];
    $current_date = new DateTime($session_datetime);
    
    // If repeat session, calculate how many sessions to create
    $session_count = 1;
    if ($repeat_session) {
      // Create sessions for the next 12 weeks/months based on frequency
      $session_count = $repeat_frequency === 'monthly' ? 6 : 12;
    }

    for ($i = 0; $i < $session_count; $i++) {
      foreach ($enrollment_ids as $enrollment_id) {
        $sessions_to_create[] = [
          'enrollment_id' => $enrollment_id,
          'session_date' => $current_date->format('Y-m-d H:i:s'),
          'duration' => $duration,
          'session_type' => $session_type,
          'session_mode' => $session_type, // For backward compatibility
          'location' => $location,
          'notes' => $notes,
          'status' => 'scheduled'
        ];
      }
      
      // Calculate next session date if repeating
      if ($repeat_session && $i < $session_count - 1) {
        switch ($repeat_frequency) {
          case 'weekly':
            $current_date->add(new DateInterval('P7D'));
            break;
          case 'biweekly':
            $current_date->add(new DateInterval('P14D'));
            break;
          case 'monthly':
            $current_date->add(new DateInterval('P1M'));
            break;
        }
      }
    }

    // Insert all sessions
    $sql = "INSERT INTO sessions (enrollment_id, session_date, duration, session_type, session_mode, location, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($sessions_to_create as $session) {
      $stmt->bind_param('isissss', 
        $session['enrollment_id'],
        $session['session_date'],
        $session['duration'],
        $session['session_type'],
        $session['session_mode'],
        $session['location'],
        $session['notes'],
        $session['status']
      );
      
      if (!$stmt->execute()) {
        throw new Exception("Failed to create session");
      }
    }

    $conn->commit();
    return true;
    
  } catch (Exception $e) {
    $conn->rollback();
    error_log("Error creating session: " . $e->getMessage());
    return false;
  }
}

/**
 * Update an existing session
 * @param int $session_id The session ID
 * @param int $program_id The program ID
 * @param string $session_datetime Session date and time
 * @param int $duration Duration in minutes
 * @param string $session_type Session type (online/in-person/hybrid)
 * @param string $location Location or meeting link
 * @param string $notes Session notes
 * @return bool True if successful, false otherwise
 */
function updateSession($session_id, $program_id, $session_datetime, $duration, $session_type, $location = '', $notes = '') {
  global $conn;

  try {
    // Update all sessions with the same date/time for this program
    $sql = "
      UPDATE sessions s
      INNER JOIN enrollments e ON s.enrollment_id = e.id
      SET s.session_date = ?, 
          s.duration = ?, 
          s.session_type = ?, 
          s.session_mode = ?, 
          s.location = ?, 
          s.notes = ?
      WHERE s.id = ?
        AND e.program_id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sissssis', $session_datetime, $duration, $session_type, $session_type, $location, $notes, $session_id, $program_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
      return true;
    }
    
    return false;
    
  } catch (Exception $e) {
    error_log("Error updating session: " . $e->getMessage());
    return false;
  }
}

/**
 * Cancel a session
 * @param int $session_id The session ID
 * @param int $tutor_user_id The tutor's user ID (for security)
 * @return bool True if successful, false otherwise
 */
function cancelSession($session_id, $tutor_user_id) {
  global $conn;

  try {
    $sql = "
      UPDATE sessions s
      INNER JOIN enrollments e ON s.enrollment_id = e.id
      INNER JOIN programs p ON e.program_id = p.id
      SET s.status = 'cancelled'
      WHERE s.id = ?
        AND p.tutor_id = ?
        AND s.status = 'scheduled'
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $session_id, $tutor_user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
      return true;
    }
    
    return false;
    
  } catch (Exception $e) {
    error_log("Error cancelling session: " . $e->getMessage());
    return false;
  }
}

/**
 * Get tutor profile data
 * @param int $user_id Tutor's user ID
 * @return array|null Tutor profile data
 */
function getTutorProfile($user_id)
{
  global $conn;

  try {
    $sql = "SELECT tp.*, u.email, u.status, u.created_at,
                   (SELECT COUNT(*) FROM programs p WHERE p.tutor_id = ?) as total_programs,
                   (SELECT COUNT(DISTINCT e.student_user_id) FROM enrollments e 
                    JOIN programs p ON e.program_id = p.id 
                    WHERE p.tutor_id = ? AND e.status = 'active') as total_students
            FROM tutor_profiles tp
            LEFT JOIN users u ON tp.user_id = u.id
            WHERE tp.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      return $result->fetch_assoc();
    }

    return null;
  } catch (Exception $e) {
    error_log("Error fetching tutor profile: " . $e->getMessage());
    return null;
  }
}

/**
 * Update tutor profile
 * @param int $user_id Tutor's user ID
 * @param array $data Profile data to update
 * @return bool Success status
 */
function updateTutorProfile($user_id, $data)
{
  global $conn;

  try {
    $conn->begin_transaction();

    // Update users table
    $user_sql = "UPDATE users SET 
                   first_name = ?, middle_name = ?, last_name = ?, 
                   email = ?, updated_at = NOW()
                 WHERE id = ?";
    
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param('ssssi', 
      $data['first_name'], 
      $data['middle_name'], 
      $data['last_name'], 
      $data['email'], 
      $user_id
    );
    
    if (!$user_stmt->execute()) {
      throw new Exception("Failed to update user data");
    }

    // Update or insert tutor_profiles
    $profile_sql = "INSERT INTO tutor_profiles 
                      (user_id, contact_number, address, bachelor_degree, 
                       specializations, bio, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                      contact_number = VALUES(contact_number),
                      address = VALUES(address),
                      bachelor_degree = VALUES(bachelor_degree),
                      specializations = VALUES(specializations),
                      bio = VALUES(bio),
                      updated_at = NOW()";

    $profile_stmt = $conn->prepare($profile_sql);
    $profile_stmt->bind_param('isssss',
      $user_id,
      $data['contact_number'],
      $data['address'],
      $data['bachelor_degree'],
      $data['specializations'],
      $data['bio']
    );

    if (!$profile_stmt->execute()) {
      throw new Exception("Failed to update tutor profile");
    }

    $conn->commit();
    return true;

  } catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating tutor profile: " . $e->getMessage());
    return false;
  }
}

/**
 * Update user password
 * @param int $user_id User ID
 * @param string $new_password New password (plain text)
 * @return bool Success status
 */
function updateUserPassword($user_id, $new_password)
{
  global $conn;
  
  try {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $hashed, $user_id);
    
    return $stmt->execute();
  } catch (Exception $e) {
    error_log("Error updating password: " . $e->getMessage());
    return false;
  }
}

/**
 * Comprehensive student profile update function that ensures consistency across all related tables
 * @param int $user_id Student's user ID  
 * @param array $profile_data Profile data to update
 * @return array Result with success status and message
 */
function updateStudentProfileSystemWide($user_id, $profile_data)
{
  global $conn;
  
  try {
    $conn->begin_transaction();
    
    // Validate required fields
    if (empty($profile_data['first_name']) || empty($profile_data['last_name'])) {
      throw new Exception('First name and last name are required');
    }
    
    // Start with users table update (for system-wide consistency)
    $userUpdateFields = [];
    $userParams = [];
    $userTypes = "";
    
    // Update core user fields that should be consistent (only fields that exist in users table)
    if (isset($profile_data['email'])) {
      $userUpdateFields[] = "email = ?";
      $userParams[] = $profile_data['email'];
      $userTypes .= "s";
    }
    
    // Always update the timestamp
    $userUpdateFields[] = "updated_at = CURRENT_TIMESTAMP";
    
    if (!empty($userUpdateFields)) {
      $userParams[] = $user_id;
      $userTypes .= "i";
      
      $userSql = "UPDATE users SET " . implode(', ', $userUpdateFields) . " WHERE id = ?";
      $userStmt = $conn->prepare($userSql);
      if (!$userStmt) {
        throw new Exception("Failed to prepare user update: " . $conn->error);
      }
      
      $userStmt->bind_param($userTypes, ...$userParams);
      if (!$userStmt->execute()) {
        throw new Exception("Failed to update user table: " . $userStmt->error);
      }
    }
    
    // Update student_profiles table with comprehensive field mapping
    $studentUpdateFields = [];
    $studentParams = [];
    $studentTypes = "";
    
    // Core identity fields
    $studentFields = [
      'first_name', 'middle_name', 'last_name', 'gender', 'suffix', 'birthday',
      'province', 'city', 'barangay', 'zip_code', 'subdivision', 
      'street', 'house_number'
    ];
    
    foreach ($studentFields as $field) {
      if (isset($profile_data[$field])) {
        $studentUpdateFields[] = "$field = ?";
        $studentParams[] = $profile_data[$field];
        $studentTypes .= "s";
      }
    }
    
    // Handle special field mappings and calculations
    if (isset($profile_data['pwd_status'])) {
      $studentUpdateFields[] = "is_pwd = ?";
      $studentParams[] = ($profile_data['pwd_status'] === 'Yes') ? 1 : 0;
      $studentTypes .= "i";
    }
    
    if (isset($profile_data['medical_history'])) {
      $studentUpdateFields[] = "medical_notes = ?";
      $studentParams[] = $profile_data['medical_history'];
      $studentTypes .= "s";
    }
    
    // Auto-generate complete address from components
    $address_components = [];
    $address_fields = ['house_number', 'street', 'subdivision', 'barangay', 'city', 'province', 'zip_code'];
    $has_address_update = false;
    
    foreach ($address_fields as $field) {
      if (isset($profile_data[$field])) {
        $has_address_update = true;
        break;
      }
    }
    
    if ($has_address_update || isset($profile_data['home_address'])) {
      // Generate complete address from components if available
      if (isset($profile_data['house_number']) || isset($profile_data['street'])) {
        $address_parts = [];
        if (!empty($profile_data['house_number'])) $address_parts[] = $profile_data['house_number'];
        if (!empty($profile_data['street'])) $address_parts[] = $profile_data['street'];
        if (!empty($profile_data['subdivision'])) $address_parts[] = $profile_data['subdivision'];
        if (!empty($profile_data['barangay'])) $address_parts[] = 'Brgy. ' . $profile_data['barangay'];
        if (!empty($profile_data['city'])) $address_parts[] = $profile_data['city'];
        if (!empty($profile_data['province'])) $address_parts[] = $profile_data['province'];
        if (!empty($profile_data['zip_code'])) $address_parts[] = $profile_data['zip_code'];
        
        $complete_address = implode(', ', array_filter($address_parts));
        $studentUpdateFields[] = "address = ?";
        $studentParams[] = $complete_address;
        $studentTypes .= "s";
      } else if (isset($profile_data['home_address'])) {
        $studentUpdateFields[] = "address = ?";
        $studentParams[] = $profile_data['home_address'];
        $studentTypes .= "s";
      }
    }
    
    // Calculate age from birthday if provided
    if (isset($profile_data['birthday'])) {
      try {
        $birthday = new DateTime($profile_data['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;
        $studentUpdateFields[] = "age = ?";
        $studentParams[] = $age;
        $studentTypes .= "i";
      } catch (Exception $e) {
        // Skip age calculation if birthday is invalid
        error_log("Invalid birthday format for age calculation: " . $e->getMessage());
      }
    }
    
    // Update student_profiles if there are fields to update
    if (!empty($studentUpdateFields)) {
      $studentParams[] = $user_id;
      $studentTypes .= "i";
      
      $studentSql = "UPDATE student_profiles SET " . implode(', ', $studentUpdateFields) . " WHERE user_id = ?";
      
      $studentStmt = $conn->prepare($studentSql);
      if (!$studentStmt) {
        throw new Exception("Failed to prepare student profile update: " . $conn->error);
      }
      
      $studentStmt->bind_param($studentTypes, ...$studentParams);
      if (!$studentStmt->execute()) {
        throw new Exception("Failed to update student profile: " . $studentStmt->error);
      }
    }
    
    // Update parent_profiles table
    $parentUpdateFields = [];
    $parentParams = [];
    $parentTypes = "";
    
    $parentFieldMapping = [
      'parent_guardian_name' => 'full_name',
      'facebook_name' => 'facebook_name', 
      'phone' => 'contact_number'
    ];
    
    foreach ($parentFieldMapping as $inputField => $dbField) {
      if (isset($profile_data[$inputField])) {
        $parentUpdateFields[] = "$dbField = ?";
        $parentParams[] = $profile_data[$inputField];
        $parentTypes .= "s";
      }
    }
    
    // Update parent profile if there are fields to update
    if (!empty($parentUpdateFields)) {
      $parentParams[] = $user_id;
      $parentTypes .= "i";
      
      // Check if parent profile exists, create if not
      $checkParentSql = "SELECT id FROM parent_profiles WHERE student_user_id = ?";
      $checkStmt = $conn->prepare($checkParentSql);
      $checkStmt->bind_param("i", $user_id);
      $checkStmt->execute();
      $parentExists = $checkStmt->get_result()->num_rows > 0;
      
      if ($parentExists) {
        $parentSql = "UPDATE parent_profiles SET " . implode(', ', $parentUpdateFields) . " WHERE student_user_id = ?";
      } else {
        // Create new parent profile with default values
        $defaultValues = [
          'full_name' => $profile_data['parent_guardian_name'] ?? '',
          'facebook_name' => $profile_data['facebook_name'] ?? '',
          'contact_number' => $profile_data['phone'] ?? ''
        ];
        
        $parentSql = "INSERT INTO parent_profiles (student_user_id, full_name, facebook_name, contact_number) VALUES (?, ?, ?, ?)";
        $parentParams = [$user_id, $defaultValues['full_name'], $defaultValues['facebook_name'], $defaultValues['contact_number']];
        $parentTypes = "isss";
      }
      
      $parentStmt = $conn->prepare($parentSql);
      if (!$parentStmt) {
        throw new Exception("Failed to prepare parent profile update: " . $conn->error);
      }
      
      $parentStmt->bind_param($parentTypes, ...$parentParams);
      if (!$parentStmt->execute()) {
        throw new Exception("Failed to update parent profile: " . $parentStmt->error);
      }
    }
    
    // Create an audit log entry for profile changes (if table exists)
    $auditSql = "INSERT INTO profile_audit_log (user_id, table_name, action, changed_fields, changed_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
    
    // Check if audit table exists first
    $tableExists = $conn->query("SHOW TABLES LIKE 'profile_audit_log'")->num_rows > 0;
    
    if ($tableExists) {
      $auditStmt = $conn->prepare($auditSql);
      
      // Log changes to each table
      if (!empty($userUpdateFields)) {
        $table_name = "users";
        $action = "update";
        $userChanges = implode(', ', array_slice($userUpdateFields, 0, -1)); // Remove timestamp field
        $auditStmt->bind_param("isss", $user_id, $table_name, $action, $userChanges);
        $auditStmt->execute();
      }
      
      if (!empty($studentUpdateFields)) {
        $table_name = "student_profiles";
        $action = "update";
        $studentChanges = implode(', ', array_slice($studentUpdateFields, 0, -1)); // Remove timestamp field
        $auditStmt->bind_param("isss", $user_id, $table_name, $action, $studentChanges);
        $auditStmt->execute();
      }
      
      if (!empty($parentUpdateFields)) {
        $table_name = "parent_profiles";
        $action = "update";
        $parentChanges = implode(', ', $parentUpdateFields);
        $auditStmt->bind_param("isss", $user_id, $table_name, $action, $parentChanges);
        $auditStmt->execute();
      }
    }
    
    $conn->commit();
    
    return [
      'success' => true,
      'message' => 'Profile updated successfully across all system tables',
      'tables_updated' => array_filter([
        !empty($userUpdateFields) ? 'users' : null,
        !empty($studentUpdateFields) ? 'student_profiles' : null,
        !empty($parentUpdateFields) ? 'parent_profiles' : null
      ])
    ];
    
  } catch (Exception $e) {
    $conn->rollback();
    error_log("Error in updateStudentProfileSystemWide: " . $e->getMessage());
    
    return [
      'success' => false,
      'message' => 'Failed to update profile: ' . $e->getMessage(),
      'tables_updated' => []
    ];
  }
}

/**
 * Validate student profile data according to registration rules
 * @param array $profile_data Profile data to validate
 * @return array Validation result with errors if any
 */
function validateStudentProfileData($profile_data)
{
  $errors = [];
  
  // Name validation - letters, spaces, apostrophes, hyphens only
  if (empty($profile_data['first_name'])) {
    $errors['first_name'] = 'First name is required.';
  } elseif (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $profile_data['first_name'])) {
    $errors['first_name'] = 'First name must be 2-50 characters and contain only letters, spaces, apostrophes, or hyphens.';
  }
  
  if (empty($profile_data['last_name'])) {
    $errors['last_name'] = 'Last name is required.';
  } elseif (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $profile_data['last_name'])) {
    $errors['last_name'] = 'Last name must be 2-50 characters and contain only letters, spaces, apostrophes, or hyphens.';
  }
  
  if (!empty($profile_data['middle_name']) && !preg_match("/^[a-zA-Z\s'-]{1,50}$/", $profile_data['middle_name'])) {
    $errors['middle_name'] = 'Middle name must be 1-50 characters and contain only letters, spaces, apostrophes, or hyphens.';
  }
  
  // Birthday validation
  if (!empty($profile_data['birthday'])) {
    try {
      $birthDate = new DateTime($profile_data['birthday']);
      $today = new DateTime();
      $minDate = (new DateTime())->modify('-100 years');
      $maxDate = (new DateTime())->modify('-3 years'); // Minimum age of 3
      
      if ($birthDate > $today) {
        $errors['birthday'] = 'Birthday cannot be in the future.';
      } elseif ($birthDate < $minDate) {
        $errors['birthday'] = 'Please enter a realistic birth date.';
      } elseif ($birthDate > $maxDate) {
        $errors['birthday'] = 'Student must be at least 3 years old.';
      }
    } catch (Exception $e) {
      $errors['birthday'] = 'Please enter a valid date.';
    }
  }
  
  // Address field validation
  if (!empty($profile_data['zip_code']) && !preg_match('/^[0-9]{4}$/', $profile_data['zip_code'])) {
    $errors['zip_code'] = 'Zip code must be exactly 4 digits.';
  }
  
  if (!empty($profile_data['street']) && (strlen($profile_data['street']) < 2 || strlen($profile_data['street']) > 100)) {
    $errors['street'] = 'Street must be between 2 and 100 characters.';
  }
  
  if (!empty($profile_data['house_number']) && (strlen($profile_data['house_number']) < 1 || strlen($profile_data['house_number']) > 50)) {
    $errors['house_number'] = 'House number/unit must be between 1 and 50 characters.';
  }
  
  if (!empty($profile_data['subdivision']) && strlen($profile_data['subdivision']) > 100) {
    $errors['subdivision'] = 'Subdivision/village name cannot exceed 100 characters.';
  }
  
  // Medical notes validation
  if (!empty($profile_data['medical_history']) && strlen($profile_data['medical_history']) > 500) {
    $errors['medical_history'] = 'Medical notes cannot exceed 500 characters.';
  }
  
  // Parent/Guardian validation
  if (!empty($profile_data['parent_guardian_name']) && !preg_match("/^[a-zA-Z\s'-]{2,100}$/", $profile_data['parent_guardian_name'])) {
    $errors['parent_guardian_name'] = 'Parent/Guardian name must be 2-100 characters and contain only letters, spaces, apostrophes, or hyphens.';
  }
  
  // Facebook name validation
  if (!empty($profile_data['facebook_name']) && (!preg_match("/^[a-zA-Z0-9\s._-]{2,50}$/", $profile_data['facebook_name']) || strlen($profile_data['facebook_name']) > 50)) {
    $errors['facebook_name'] = 'Facebook name must be 2-50 characters and contain only letters, numbers, spaces, dots, underscores, or hyphens.';
  }
  
  // Email validation
  if (!empty($profile_data['email'])) {
    if (!filter_var($profile_data['email'], FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'Please enter a valid email address.';
    } elseif (strlen($profile_data['email']) > 100) {
      $errors['email'] = 'Email address cannot exceed 100 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $profile_data['email'])) {
      $errors['email'] = 'Please enter a properly formatted email address.';
    }
  }
  
  // Philippine mobile number validation
  if (!empty($profile_data['phone']) && !preg_match('/^(09|\+639|639)\d{9}$/', $profile_data['phone'])) {
    $errors['phone'] = 'Please enter a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).';
  }
  
  return [
    'is_valid' => empty($errors),
    'errors' => $errors
  ];
}

/**
  global $conn;

  try {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $hashed_password, $user_id);
    
    return $stmt->execute();
    
  } catch (Exception $e) {
    error_log("Error updating user password: " . $e->getMessage());
    return false;
  }
}

/**
 * Update user profile picture
 * @param int $user_id User ID
 * @param string $profile_picture_url Profile picture URL
 * @return bool Success status
 */
function updateUserProfilePicture($user_id, $profile_picture_url)
{
  global $conn;

  try {
    // First check if tutor_profiles record exists
    $check_sql = "SELECT user_id FROM tutor_profiles WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;

    if ($exists) {
      // Update existing record
      $sql = "UPDATE tutor_profiles SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('si', $profile_picture_url, $user_id);
    } else {
      // Create new record
      $sql = "INSERT INTO tutor_profiles (user_id, profile_picture, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('is', $user_id, $profile_picture_url);
    }
    
    return $stmt->execute();
    
  } catch (Exception $e) {
    error_log("Error updating user profile picture: " . $e->getMessage());
    return false;
  }
}

/**
 * Get user by ID
 * @param int $user_id User ID
 * @return array|null User data
 */
function getUserById($user_id)
{
  global $conn;

  try {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      return $result->fetch_assoc();
    }

    return null;
  } catch (Exception $e) {
    error_log("Error fetching user by ID: " . $e->getMessage());
    return null;
  }
}

/**
 * Get recent activities for admin dashboard
 * @param int $limit Number of recent activities to fetch
 * @return array Array of recent activities
 */
function getAdminRecentActivities($limit = 10)
{
  global $conn;

  try {
    $activities = [];

    // Get recent activities across the platform
    $sql = "SELECT 'enrollment' as activity_type, 
                   CONCAT(u.username, ' enrolled in ', p.name) as message,
                   e.created_at as activity_time,
                   'user-plus' as icon,
                   'text-green-600' as color
            FROM enrollments e
            JOIN users u ON e.student_user_id = u.id
            JOIN programs p ON e.program_id = p.id
            WHERE e.status = 'active'
            
            UNION ALL
            
            SELECT 'payment' as activity_type,
                   CONCAT(u.username, ' made payment of ₱', FORMAT(pay.amount, 2)) as message,
                   pay.created_at as activity_time,
                   'currency-dollar' as icon,
                   'text-yellow-600' as color
            FROM payments pay
            JOIN enrollments e ON pay.enrollment_id = e.id
            JOIN users u ON e.student_user_id = u.id
            WHERE pay.status = 'validated'
            
            UNION ALL
            
            SELECT 'program' as activity_type,
                   CONCAT('New program \"', p.name, '\" created by ', u.username) as message,
                   p.created_at as activity_time,
                   'book-open' as icon,
                   'text-blue-600' as color
            FROM programs p
            JOIN users u ON p.tutor_id = u.id
            WHERE p.status = 'active'
            
            UNION ALL
            
            SELECT 'user' as activity_type,
                   CONCAT('New ', u.role, ' registered: ', u.username) as message,
                   u.created_at as activity_time,
                   'users' as icon,
                   'text-purple-600' as color
            FROM users u
            WHERE u.role IN ('tutor', 'student')
            
            ORDER BY activity_time DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      // Format the time
      $time_diff = time() - strtotime($row['activity_time']);
      
      if ($time_diff < 60) {
        $time_display = 'Just now';
      } elseif ($time_diff < 3600) {
        $time_display = floor($time_diff / 60) . ' minutes ago';
      } elseif ($time_diff < 86400) {
        $time_display = floor($time_diff / 3600) . ' hours ago';
      } else {
        $time_display = floor($time_diff / 86400) . ' days ago';
      }

      $activities[] = [
        'message' => $row['message'],
        'time' => $time_display,
        'icon' => $row['icon'],
        'color' => $row['color'],
        'type' => $row['activity_type']
      ];
    }

    return $activities;
  } catch (Exception $e) {
    error_log("Error fetching admin activities: " . $e->getMessage());
    return [];
  }
}

/**
 * Get comprehensive reports data with filtering options
 * @param string $period Filter period ('monthly', 'yearly', 'all')
 * @param int $year Specific year filter (optional)
 * @param int $month Specific month filter (optional, requires year)
 * @return array Reports data including stats, trends, and recent reports
 */
function getReportsData($period = 'all', $year = null, $month = null) {
  global $conn;
  
  try {
    $data = [
      'summary_stats' => getReportsSummaryStats($period, $year, $month),
      'enrollment_trends' => getEnrollmentTrends($period, $year, $month),
      'revenue_trends' => getRevenueTrends($period, $year, $month),
      'recent_reports' => getRecentReports()
    ];
    
    return $data;
  } catch (Exception $e) {
    error_log("Error fetching reports data: " . $e->getMessage());
    return [
      'summary_stats' => [
        'total_students' => 0,
        'active_programs' => 0,
        'total_revenue' => 0,
        'completion_rate' => 0,
        'students_growth' => 0,
        'programs_growth' => 0,
        'revenue_growth' => 0,
        'completion_growth' => 0
      ],
      'enrollment_trends' => [],
      'revenue_trends' => [],
      'recent_reports' => []
    ];
  }
}

/**
 * Get summary statistics for reports with growth calculations
 */
function getReportsSummaryStats($period = 'all', $year = null, $month = null) {
  global $conn;
  
  try {
    $whereClause = '';
    $params = [];
    $paramTypes = '';
    
    // Build date filter
    if ($period === 'monthly' && $year && $month) {
      $whereClause = 'WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?';
      $params = [$year, $month];
      $paramTypes = 'ii';
    } elseif ($period === 'yearly' && $year) {
      $whereClause = 'WHERE YEAR(created_at) = ?';
      $params = [$year];
      $paramTypes = 'i';
    }
    
    // Get current period stats
    $stats = [];
    
    // Total Students
    if ($whereClause) {
      $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND YEAR(created_at) = ?" . ($month ? " AND MONTH(created_at) = ?" : "");
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($paramTypes, ...$params);
    } else {
      $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
      $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_students'] = (int)$result->fetch_assoc()['count'];
    
    // Active Programs
    if ($whereClause) {
      $sql = "SELECT COUNT(*) as count FROM programs WHERE status = 'active' AND YEAR(created_at) = ?" . ($month ? " AND MONTH(created_at) = ?" : "");
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($paramTypes, ...$params);
    } else {
      $sql = "SELECT COUNT(*) as count FROM programs WHERE status = 'active'";
      $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['active_programs'] = (int)$result->fetch_assoc()['count'];
    
    // Total Revenue
    if ($whereClause) {
      $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'validated' AND YEAR(created_at) = ?" . ($month ? " AND MONTH(created_at) = ?" : "");
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($paramTypes, ...$params);
    } else {
      $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'validated'";
      $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_revenue'] = (float)$result->fetch_assoc()['total'];
    
    // Completion Rate (completed enrollments / total enrollments)
    if ($whereClause) {
      $sql = "SELECT 
                COUNT(*) as total_enrollments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments
              FROM enrollments 
              WHERE YEAR(created_at) = ?" . ($month ? " AND MONTH(created_at) = ?" : "");
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($paramTypes, ...$params);
    } else {
      $sql = "SELECT 
                COUNT(*) as total_enrollments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments
              FROM enrollments";
      $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $completion_data = $result->fetch_assoc();
    $total_enrollments = (int)$completion_data['total_enrollments'];
    $completed_enrollments = (int)$completion_data['completed_enrollments'];
    $stats['completion_rate'] = $total_enrollments > 0 ? round(($completed_enrollments / $total_enrollments) * 100, 1) : 0;
    
    // Calculate growth percentages compared to previous period
    $stats['students_growth'] = calculateGrowth('users', 'role = "student"', $period, $year, $month);
    $stats['programs_growth'] = calculateGrowth('programs', 'status = "active"', $period, $year, $month);
    $stats['revenue_growth'] = calculateRevenueGrowth($period, $year, $month);
    $stats['completion_growth'] = calculateCompletionGrowth($period, $year, $month);
    
    return $stats;
  } catch (Exception $e) {
    error_log("Error fetching reports summary stats: " . $e->getMessage());
    return [
      'total_students' => 0,
      'active_programs' => 0,
      'total_revenue' => 0,
      'completion_rate' => 0,
      'students_growth' => 0,
      'programs_growth' => 0,
      'revenue_growth' => 0,
      'completion_growth' => 0
    ];
  }
}

/**
 * Calculate growth percentage for a given metric
 */
function calculateGrowth($table, $condition, $period, $year, $month) {
  global $conn;
  
  try {
    $currentCount = 0;
    $previousCount = 0;
    
    if ($period === 'monthly' && $year && $month) {
      // Current month
      $sql = "SELECT COUNT(*) as count FROM $table WHERE $condition AND YEAR(created_at) = ? AND MONTH(created_at) = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('ii', $year, $month);
      $stmt->execute();
      $currentCount = (int)$stmt->get_result()->fetch_assoc()['count'];
      
      // Previous month
      $prevMonth = $month - 1;
      $prevYear = $year;
      if ($prevMonth <= 0) {
        $prevMonth = 12;
        $prevYear = $year - 1;
      }
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('ii', $prevYear, $prevMonth);
      $stmt->execute();
      $previousCount = (int)$stmt->get_result()->fetch_assoc()['count'];
      
    } elseif ($period === 'yearly' && $year) {
      // Current year
      $sql = "SELECT COUNT(*) as count FROM $table WHERE $condition AND YEAR(created_at) = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('i', $year);
      $stmt->execute();
      $currentCount = (int)$stmt->get_result()->fetch_assoc()['count'];
      
      // Previous year
      $prevYear = $year - 1;
      $stmt->bind_param('i', $prevYear);
      $stmt->execute();
      $previousCount = (int)$stmt->get_result()->fetch_assoc()['count'];
    }
    
    if ($previousCount == 0) {
      return $currentCount > 0 ? 100 : 0;
    }
    
    return round((($currentCount - $previousCount) / $previousCount) * 100, 1);
  } catch (Exception $e) {
    error_log("Error calculating growth: " . $e->getMessage());
    return 0;
  }
}

/**
 * Calculate revenue growth
 */
function calculateRevenueGrowth($period, $year, $month) {
  global $conn;
  
  try {
    $currentRevenue = 0;
    $previousRevenue = 0;
    
    if ($period === 'monthly' && $year && $month) {
      // Current month
      $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'validated' AND YEAR(created_at) = ? AND MONTH(created_at) = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('ii', $year, $month);
      $stmt->execute();
      $currentRevenue = (float)$stmt->get_result()->fetch_assoc()['total'];
      
      // Previous month
      $prevMonth = $month - 1;
      $prevYear = $year;
      if ($prevMonth <= 0) {
        $prevMonth = 12;
        $prevYear = $year - 1;
      }
      $stmt->bind_param('ii', $prevYear, $prevMonth);
      $stmt->execute();
      $previousRevenue = (float)$stmt->get_result()->fetch_assoc()['total'];
      
    } elseif ($period === 'yearly' && $year) {
      // Current year
      $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'validated' AND YEAR(created_at) = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('i', $year);
      $stmt->execute();
      $currentRevenue = (float)$stmt->get_result()->fetch_assoc()['total'];
      
      // Previous year
      $prevYear = $year - 1;
      $stmt->bind_param('i', $prevYear);
      $stmt->execute();
      $previousRevenue = (float)$stmt->get_result()->fetch_assoc()['total'];
    }
    
    if ($previousRevenue == 0) {
      return $currentRevenue > 0 ? 100 : 0;
    }
    
    return round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1);
  } catch (Exception $e) {
    error_log("Error calculating revenue growth: " . $e->getMessage());
    return 0;
  }
}

/**
 * Calculate completion rate growth
 */
function calculateCompletionGrowth($period, $year, $month) {
  global $conn;
  
  try {
    $currentRate = 0;
    $previousRate = 0;
    
    if ($period === 'monthly' && $year && $month) {
      // Current month completion rate
      $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
              FROM enrollments 
              WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('ii', $year, $month);
      $stmt->execute();
      $result = $stmt->get_result()->fetch_assoc();
      $currentRate = $result['total'] > 0 ? ($result['completed'] / $result['total']) * 100 : 0;
      
      // Previous month
      $prevMonth = $month - 1;
      $prevYear = $year;
      if ($prevMonth <= 0) {
        $prevMonth = 12;
        $prevYear = $year - 1;
      }
      $stmt->bind_param('ii', $prevYear, $prevMonth);
      $stmt->execute();
      $result = $stmt->get_result()->fetch_assoc();
      $previousRate = $result['total'] > 0 ? ($result['completed'] / $result['total']) * 100 : 0;
      
    } elseif ($period === 'yearly' && $year) {
      // Current year completion rate
      $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
              FROM enrollments 
              WHERE YEAR(created_at) = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('i', $year);
      $stmt->execute();
      $result = $stmt->get_result()->fetch_assoc();
      $currentRate = $result['total'] > 0 ? ($result['completed'] / $result['total']) * 100 : 0;
      
      // Previous year
      $prevYear = $year - 1;
      $stmt->bind_param('i', $prevYear);
      $stmt->execute();
      $result = $stmt->get_result()->fetch_assoc();
      $previousRate = $result['total'] > 0 ? ($result['completed'] / $result['total']) * 100 : 0;
    }
    
    if ($previousRate == 0) {
      return $currentRate > 0 ? 100 : 0;
    }
    
    return round((($currentRate - $previousRate) / $previousRate) * 100, 1);
  } catch (Exception $e) {
    error_log("Error calculating completion growth: " . $e->getMessage());
    return 0;
  }
}

/**
 * Get enrollment trends data for charts
 */
function getEnrollmentTrends($period = 'all', $year = null, $month = null) {
  global $conn;
  
  try {
    $trends = [];
    
    if ($period === 'monthly' && $year && $month) {
      // Get daily data for the specific month
      $sql = "SELECT 
                DAY(created_at) as day,
                COUNT(*) as enrollments
              FROM enrollments 
              WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
              GROUP BY DAY(created_at)
              ORDER BY day";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('ii', $year, $month);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $trends[] = [
          'label' => "Day " . $row['day'],
          'enrollments' => (int)$row['enrollments']
        ];
      }
      
    } elseif ($period === 'yearly' && $year) {
      // Get monthly data for the specific year
      $sql = "SELECT 
                MONTH(created_at) as month,
                COUNT(*) as enrollments
              FROM enrollments 
              WHERE YEAR(created_at) = ?
              GROUP BY MONTH(created_at)
              ORDER BY month";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('i', $year);
      $stmt->execute();
      $result = $stmt->get_result();
      
      $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      while ($row = $result->fetch_assoc()) {
        $trends[] = [
          'label' => $months[$row['month'] - 1],
          'enrollments' => (int)$row['enrollments']
        ];
      }
      
    } else {
      // Get last 12 months data
      $sql = "SELECT 
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                COUNT(*) as enrollments
              FROM enrollments 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY YEAR(created_at), MONTH(created_at)
              ORDER BY year, month";
      $result = $conn->query($sql);
      
      $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      while ($row = $result->fetch_assoc()) {
        $trends[] = [
          'label' => $months[$row['month'] - 1] . ' ' . $row['year'],
          'enrollments' => (int)$row['enrollments']
        ];
      }
    }
    
    return $trends;
  } catch (Exception $e) {
    error_log("Error fetching enrollment trends: " . $e->getMessage());
    return [];
  }
}

/**
 * Get revenue trends data for charts
 */
function getRevenueTrends($period = 'all', $year = null, $month = null) {
  global $conn;
  
  try {
    $trends = [];
    
    if ($period === 'monthly' && $year && $month) {
      // Get daily data for the specific month
      $sql = "SELECT 
                DAY(created_at) as day,
                COALESCE(SUM(amount), 0) as revenue
              FROM payments 
              WHERE status = 'validated' AND YEAR(created_at) = ? AND MONTH(created_at) = ?
              GROUP BY DAY(created_at)
              ORDER BY day";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('ii', $year, $month);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $trends[] = [
          'label' => "Day " . $row['day'],
          'revenue' => (float)$row['revenue']
        ];
      }
      
    } elseif ($period === 'yearly' && $year) {
      // Get monthly data for the specific year
      $sql = "SELECT 
                MONTH(created_at) as month,
                COALESCE(SUM(amount), 0) as revenue
              FROM payments 
              WHERE status = 'validated' AND YEAR(created_at) = ?
              GROUP BY MONTH(created_at)
              ORDER BY month";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('i', $year);
      $stmt->execute();
      $result = $stmt->get_result();
      
      $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      while ($row = $result->fetch_assoc()) {
        $trends[] = [
          'label' => $months[$row['month'] - 1],
          'revenue' => (float)$row['revenue']
        ];
      }
      
    } else {
      // Get last 12 months data
      $sql = "SELECT 
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                COALESCE(SUM(amount), 0) as revenue
              FROM payments 
              WHERE status = 'validated' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY YEAR(created_at), MONTH(created_at)
              ORDER BY year, month";
      $result = $conn->query($sql);
      
      $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      while ($row = $result->fetch_assoc()) {
        $trends[] = [
          'label' => $months[$row['month'] - 1] . ' ' . $row['year'],
          'revenue' => (float)$row['revenue']
        ];
      }
    }
    
    return $trends;
  } catch (Exception $e) {
    error_log("Error fetching revenue trends: " . $e->getMessage());
    return [];
  }
}

/**
 * Get recent reports data
 */
function getRecentReports($limit = 10) {
  try {
    // For now, return mock data. In a real implementation, this would fetch from a reports table
    $reports = [
      [
        'name' => 'Student Performance Q4',
        'type' => 'Academic',
        'date' => 'Dec 15, 2023',
        'file' => 'student_performance_q4.pdf',
        'size' => '2.4 MB'
      ],
      [
        'name' => 'Financial Summary',
        'type' => 'Financial',
        'date' => 'Dec 10, 2023',
        'file' => 'financial_summary.pdf',
        'size' => '1.8 MB'
      ],
      [
        'name' => 'Program Effectiveness',
        'type' => 'Program',
        'date' => 'Dec 5, 2023',
        'file' => 'program_effectiveness.pdf',
        'size' => '3.2 MB'
      ],
      [
        'name' => 'Enrollment Analytics',
        'type' => 'Enrollment',
        'date' => 'Nov 28, 2023',
        'file' => 'enrollment_analytics.pdf',
        'size' => '2.1 MB'
      ],
      [
        'name' => 'Tutor Performance',
        'type' => 'Academic',
        'date' => 'Nov 20, 2023',
        'file' => 'tutor_performance.pdf',
        'size' => '1.9 MB'
      ]
    ];
    
    return array_slice($reports, 0, $limit);
  } catch (Exception $e) {
    error_log("Error fetching recent reports: " . $e->getMessage());
    return [];
  }
}

/**
 * Get all users for admin management
 */
function getAllUsers() {
  global $conn;
  
  try {
    $sql = "SELECT id, user_id, username, email, role, status, created_at, last_login 
            FROM users 
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
      $users[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'role' => $row['role'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'last_login' => $row['last_login']
      ];
    }
    
    return $users;
  } catch (Exception $e) {
    error_log("Error fetching all users: " . $e->getMessage());
    return [];
  }
}

/**
 * Add a new user account
 */
function addNewUser($username, $email, $role, $password) {
  global $conn;
  
  try {
    // Check if email already exists
    $checkSql = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
      return ['success' => false, 'message' => 'Email already exists'];
    }
    
    // Generate user ID
    $user_id = generateUserID($role);
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $sql = "INSERT INTO users (user_id, username, email, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssss', $user_id, $username, $email, $hashed_password, $role);
    
    if ($stmt->execute()) {
      return ['success' => true, 'user_id' => $user_id];
    } else {
      return ['success' => false, 'message' => 'Failed to create user'];
    }
  } catch (Exception $e) {
    error_log("Error adding new user: " . $e->getMessage());
    return ['success' => false, 'message' => 'Database error'];
  }
}

/**
 * Update user account information
 */
function updateUser($id, $username, $email, $role, $status) {
  global $conn;
  
  try {
    // Check if email exists for other users
    $checkSql = "SELECT id FROM users WHERE email = ? AND id != ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('si', $email, $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
      return ['success' => false, 'message' => 'Email already exists for another user'];
    }
    
    // Update user
    $sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssi', $username, $email, $role, $status, $id);
    
    if ($stmt->execute()) {
      return ['success' => true];
    } else {
      return ['success' => false, 'message' => 'Failed to update user'];
    }
  } catch (Exception $e) {
    error_log("Error updating user: " . $e->getMessage());
    return ['success' => false, 'message' => 'Database error'];
  }
}

/**
 * Deactivate a user account
 */
function deactivateUser($id) {
  global $conn;
  
  try {
    $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
      return ['success' => true];
    } else {
      return ['success' => false, 'message' => 'Failed to deactivate user'];
    }
  } catch (Exception $e) {
    error_log("Error deactivating user: " . $e->getMessage());
    return ['success' => false, 'message' => 'Database error'];
  }
}

/**
 * Reset user password
 */
function resetUserPassword($email) {
  global $conn;
  
  try {
    // Check if user exists
    $checkSql = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
      return ['success' => false, 'message' => 'User not found'];
    }
    
    // Generate new password
    $newPassword = generateRandomPassword();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $sql = "UPDATE users SET password = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $hashedPassword, $email);
    
    if ($stmt->execute()) {
      return ['success' => true, 'new_password' => $newPassword];
    } else {
      return ['success' => false, 'message' => 'Failed to reset password'];
    }
  } catch (Exception $e) {
    error_log("Error resetting password: " . $e->getMessage());
    return ['success' => false, 'message' => 'Database error'];
  }
}

/**
 * Generate random password
 */
function generateRandomPassword($length = 8) {
  $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  $password = '';
  for ($i = 0; $i < $length; $i++) {
    $password .= $characters[rand(0, strlen($characters) - 1)];
  }
  return $password;
}

/**
 * Update admin profile information
 * @param string $user_id Admin user ID
 * @param array $data Profile data to update
 * @return array Result with success status and message
 */
function updateAdminProfile($user_id, $data) {
  global $conn;
  
  try {
    // Validate required fields
    if (empty($data['username']) || empty($data['email'])) {
      return ['success' => false, 'message' => 'Username and email are required'];
    }
    
    // Check if username or email already exists (excluding current user)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $stmt->bind_param('sss', $data['username'], $data['email'], $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
      return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Update profile
    $sql = "UPDATE users SET username = ?, email = ?, first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, address = ?, bio = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssss', 
      $data['username'], 
      $data['email'], 
      $data['first_name'], 
      $data['middle_name'], 
      $data['last_name'], 
      $data['contact_number'], 
      $data['address'], 
      $data['bio'], 
      $user_id
    );
    
    if ($stmt->execute()) {
      return ['success' => true, 'message' => 'Profile updated successfully'];
    } else {
      return ['success' => false, 'message' => 'Failed to update profile'];
    }
  } catch (Exception $e) {
    error_log("Error updating admin profile: " . $e->getMessage());
    return ['success' => false, 'message' => 'Database error occurred'];
  }
}

/**
 * Update admin password
 * @param string $user_id Admin user ID
 * @param string $current_password Current password
 * @param string $new_password New password
 * @return array Result with success status and message
 */
function updateAdminPassword($user_id, $current_password, $new_password) {
  global $conn;
  
  try {
    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
      return ['success' => false, 'message' => 'User not found'];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
      return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param('ss', $hashed_password, $user_id);
    
    if ($stmt->execute()) {
      return ['success' => true, 'message' => 'Password updated successfully'];
    } else {
      return ['success' => false, 'message' => 'Failed to update password'];
    }
  } catch (Exception $e) {
    error_log("Error updating admin password: " . $e->getMessage());
    return ['success' => false, 'message' => 'Database error occurred'];
  }
}

/**
 * Update admin profile picture
 * @param string $user_id Admin user ID
 * @param array $file Uploaded file data
 * @return array Result with success status and message
 */
function updateAdminProfilePicture($user_id, $file) {
  global $conn;
  
  try {
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
      return ['success' => false, 'message' => 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.'];
    }
    
    if ($file['size'] > $max_size) {
      return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/profile_pictures/';
    if (!file_exists($upload_dir)) {
      mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'admin_' . $user_id . '_' . time() . '.' . $extension;
    $upload_path = $upload_dir . $filename;
    $relative_path = 'uploads/profile_pictures/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
      // Get old profile picture to delete
      $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
      $stmt->bind_param('s', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
        $old_data = $result->fetch_assoc();
        $old_picture = $old_data['profile_picture'];
        
        // Update database
        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
        $stmt->bind_param('ss', $relative_path, $user_id);
        
        if ($stmt->execute()) {
          // Delete old profile picture if it exists
          if ($old_picture && file_exists(__DIR__ . '/../' . $old_picture)) {
            unlink(__DIR__ . '/../' . $old_picture);
          }
          
          return ['success' => true, 'message' => 'Profile picture updated successfully'];
        } else {
          // Delete uploaded file if database update fails
          unlink($upload_path);
          return ['success' => false, 'message' => 'Failed to update database'];
        }
      } else {
        unlink($upload_path);
        return ['success' => false, 'message' => 'User not found'];
      }
    } else {
      return ['success' => false, 'message' => 'Failed to upload file'];
    }
  } catch (Exception $e) {
    error_log("Error updating admin profile picture: " . $e->getMessage());
    return ['success' => false, 'message' => 'An error occurred while uploading the file'];
  }
}

/**
 * Get total users count for admin dashboard stats
 * @return int Total number of users
 */
function getTotalUsersCount() {
  global $conn;
  
  try {
    $sql = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
    $result = $conn->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
      return (int)$row['total'];
    }
    
    return 0;
  } catch (Exception $e) {
    error_log("Error getting total users count: " . $e->getMessage());
    return 0;
  }
}

/**
 * Get total programs count for admin dashboard stats
 * @return int Total number of programs
 */
function getTotalProgramsCount() {
  global $conn;
  
  try {
    $sql = "SELECT COUNT(*) as total FROM programs WHERE status = 'active'";
    $result = $conn->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
      return (int)$row['total'];
    }
    
    return 0;
  } catch (Exception $e) {
    error_log("Error getting total programs count: " . $e->getMessage());
    return 0;
  }
}

/**
 * Get total revenue for admin dashboard stats
 * @return float Total revenue amount
 */
function getTotalRevenue() {
  global $conn;
  
  try {
    $sql = "SELECT SUM(amount) as total FROM payments WHERE status = 'completed'";
    $result = $conn->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
      return (float)($row['total'] ?? 0);
    }
    
    return 0.00;
  } catch (Exception $e) {
    error_log("Error getting total revenue: " . $e->getMessage());
    return 0.00;
  }
}

/**
 * Get user by user_id (string) for profile display
 * @param string $user_id User ID (like TPS2025-123)
 * @return array|null User data or null if not found
 */
function getUserByUserId($user_id) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
      return $result->fetch_assoc();
    }
    
    return null;
  } catch (Exception $e) {
    error_log("Error getting user by user_id: " . $e->getMessage());
    return null;
  }
}

/**
 * =================================
 * PAYMENT METHODS FUNCTIONS
 * =================================
 */

/**
 * Get all active E-Wallet accounts
 * @return array
 */
function getAllEWalletAccounts() {
  global $conn;
  
  try {
    $stmt = $conn->prepare("SELECT * FROM ewallet_accounts WHERE is_active = 1 ORDER BY provider, id");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $accounts = [];
    while ($row = $result->fetch_assoc()) {
      $accounts[] = $row;
    }
    
    return $accounts;
  } catch (Exception $e) {
    error_log("Error getting E-Wallet accounts: " . $e->getMessage());
    return [];
  }
}

/**
 * Get all active Bank accounts
 * @return array
 */
function getAllBankAccounts() {
  global $conn;
  
  try {
    $stmt = $conn->prepare("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name, id");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $accounts = [];
    while ($row = $result->fetch_assoc()) {
      $accounts[] = $row;
    }
    
    return $accounts;
  } catch (Exception $e) {
    error_log("Error getting Bank accounts: " . $e->getMessage());
    return [];
  }
}

/**
 * Add new E-Wallet account
 * @param string $provider
 * @param string $accountNumber
 * @param string $accountName
 * @return array
 */
function addEWalletAccount($provider, $accountNumber, $accountName) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("INSERT INTO ewallet_accounts (provider, account_number, account_name) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $provider, $accountNumber, $accountName);
    
    if ($stmt->execute()) {
      return ['success' => true, 'id' => $conn->insert_id];
    } else {
      return ['success' => false, 'message' => 'Failed to add E-Wallet account'];
    }
  } catch (Exception $e) {
    error_log("Error adding E-Wallet account: " . $e->getMessage());
    return ['success' => false, 'message' => $e->getMessage()];
  }
}

/**
 * Add new Bank account
 * @param string $bankName
 * @param string $accountNumber
 * @param string $accountName
 * @return array
 */
function addBankAccount($bankName, $accountNumber, $accountName) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("INSERT INTO bank_accounts (bank_name, account_number, account_name) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $bankName, $accountNumber, $accountName);
    
    if ($stmt->execute()) {
      return ['success' => true, 'id' => $conn->insert_id];
    } else {
      return ['success' => false, 'message' => 'Failed to add Bank account'];
    }
  } catch (Exception $e) {
    error_log("Error adding Bank account: " . $e->getMessage());
    return ['success' => false, 'message' => $e->getMessage()];
  }
}

/**
 * Update E-Wallet account
 * @param int $id
 * @param string $provider
 * @param string $accountNumber
 * @param string $accountName
 * @return array
 */
function updateEWalletAccount($id, $provider, $accountNumber, $accountName) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("UPDATE ewallet_accounts SET provider = ?, account_number = ?, account_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("sssi", $provider, $accountNumber, $accountName, $id);
    
    if ($stmt->execute()) {
      return ['success' => true];
    } else {
      return ['success' => false, 'message' => 'Failed to update E-Wallet account'];
    }
  } catch (Exception $e) {
    error_log("Error updating E-Wallet account: " . $e->getMessage());
    return ['success' => false, 'message' => $e->getMessage()];
  }
}

/**
 * Update Bank account
 * @param int $id
 * @param string $bankName
 * @param string $accountNumber
 * @param string $accountName
 * @return array
 */
function updateBankAccount($id, $bankName, $accountNumber, $accountName) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("UPDATE bank_accounts SET bank_name = ?, account_number = ?, account_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("sssi", $bankName, $accountNumber, $accountName, $id);
    
    if ($stmt->execute()) {
      return ['success' => true];
    } else {
      return ['success' => false, 'message' => 'Failed to update Bank account'];
    }
  } catch (Exception $e) {
    error_log("Error updating Bank account: " . $e->getMessage());
    return ['success' => false, 'message' => $e->getMessage()];
  }
}

/**
 * Delete E-Wallet account (soft delete)
 * @param int $id
 * @return array
 */
function deleteEWalletAccount($id) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("UPDATE ewallet_accounts SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
      return ['success' => true];
    } else {
      return ['success' => false, 'message' => 'Failed to delete E-Wallet account'];
    }
  } catch (Exception $e) {
    error_log("Error deleting E-Wallet account: " . $e->getMessage());
    return ['success' => false, 'message' => $e->getMessage()];
  }
}

/**
 * Delete Bank account (soft delete)
 * @param int $id
 * @return array
 */
function deleteBankAccount($id) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("UPDATE bank_accounts SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
      return ['success' => true];
    } else {
      return ['success' => false, 'message' => 'Failed to delete Bank account'];
    }
  } catch (Exception $e) {
    error_log("Error deleting Bank account: " . $e->getMessage());
    return ['success' => false, 'message' => $e->getMessage()];
  }
}

/**
 * Get all cash settings
 * @return array
 */
function getAllCashSettings() {
  global $conn;
  
  try {
    $stmt = $conn->prepare("SELECT * FROM cash_settings WHERE is_active = 1 ORDER BY setting_key");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
      $settings[$row['setting_key']] = $row;
    }
    
    return $settings;
  } catch (Exception $e) {
    error_log("Error getting cash settings: " . $e->getMessage());
    return [];
  }
}

/**
 * Update cash setting
 * @param string $settingKey
 * @param string $settingValue
 * @return array
 */
function updateCashSetting($settingKey, $settingValue) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("UPDATE cash_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
    $stmt->bind_param("ss", $settingValue, $settingKey);
    
    if ($stmt->execute()) {
      return ['success' => true];
    } else {
      return ['success' => false, 'message' => 'Failed to update cash setting'];
    }
  } catch (Exception $e) {
    error_log("Error updating cash setting: " . $e->getMessage());
    return ['success' => false, 'message' => $e->getMessage()];
  }
}

/**
 * Get cash payment instructions formatted for display
 * @return array
 */
function getCashPaymentInstructions() {
  $settings = getAllCashSettings();
  
  if (empty($settings)) {
    // Return default instructions if no settings found
    return [
      'address' => 'Tisa, Labangon, Cebu City',
      'hours' => 'Monday-Friday, 8:00 AM - 5:00 PM',
      'contact_person' => 'Administrative Office',
      'phone_number' => '+63 XXX-XXX-XXXX',
      'additional_instructions' => 'Please bring a valid ID when making cash payments. Receipt will be provided upon payment.'
    ];
  }
  
  return [
    'address' => $settings['office_address']['setting_value'] ?? 'Tisa, Labangon, Cebu City',
    'hours' => $settings['business_hours']['setting_value'] ?? 'Monday-Friday, 8:00 AM - 5:00 PM',
    'contact_person' => $settings['contact_person']['setting_value'] ?? 'Administrative Office',
    'phone_number' => $settings['phone_number']['setting_value'] ?? '+63 XXX-XXX-XXXX',
    'additional_instructions' => $settings['additional_instructions']['setting_value'] ?? 'Please bring a valid ID when making cash payments. Receipt will be provided upon payment.'
  ];
}

/**
 * Get payment summary data for reports
 * @param string $startDate
 * @param string $endDate
 * @return array
 */
function getPaymentSummaryData($startDate, $endDate) {
  global $conn;
  
  try {
    // Get total revenue for the specified period
    $totalRevenueStmt = $conn->prepare("
      SELECT COALESCE(SUM(amount), 0) as total_revenue 
      FROM payments 
      WHERE status = 'validated' 
      AND payment_date BETWEEN ? AND ?
    ");
    $totalRevenueStmt->bind_param("ss", $startDate, $endDate);
    $totalRevenueStmt->execute();
    $totalRevenue = $totalRevenueStmt->get_result()->fetch_assoc()['total_revenue'];
    
    // Get outstanding payments for the specified period
    $outstandingStmt = $conn->prepare("
      SELECT COALESCE(SUM(amount), 0) as outstanding 
      FROM payments 
      WHERE status IN ('pending', 'rejected') 
      AND payment_date BETWEEN ? AND ?
    ");
    $outstandingStmt->bind_param("ss", $startDate, $endDate);
    $outstandingStmt->execute();
    $outstanding = $outstandingStmt->get_result()->fetch_assoc()['outstanding'];
    
    // Calculate completion rate
    $totalPayments = $totalRevenue + $outstanding;
    $completionRate = $totalPayments > 0 ? round(($totalRevenue / $totalPayments) * 100, 0) : 0;
    
    // Get total number of payment transactions for better insights
    $totalTransactionsStmt = $conn->prepare("
      SELECT COUNT(*) as total_transactions,
             COUNT(CASE WHEN status = 'validated' THEN 1 END) as validated_count,
             COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
             COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
      FROM payments 
      WHERE payment_date BETWEEN ? AND ?
    ");
    $totalTransactionsStmt->bind_param("ss", $startDate, $endDate);
    $totalTransactionsStmt->execute();
    $transactionData = $totalTransactionsStmt->get_result()->fetch_assoc();
    
    return [
      'total_revenue' => $totalRevenue,
      'outstanding_payments' => $outstanding,
      'completion_rate' => $completionRate,
      'total_transactions' => $transactionData['total_transactions'],
      'validated_count' => $transactionData['validated_count'],
      'pending_count' => $transactionData['pending_count'],
      'rejected_count' => $transactionData['rejected_count']
    ];
    
  } catch (Exception $e) {
    error_log("Error getting payment summary: " . $e->getMessage());
    return [
      'total_revenue' => 0,
      'outstanding_payments' => 0,
      'completion_rate' => 0,
      'total_transactions' => 0,
      'validated_count' => 0,
      'pending_count' => 0,
      'rejected_count' => 0
    ];
  }
}

/**
 * Get recent payments for reports
 * @param int $limit
 * @return array
 */
function getRecentPayments($limit = 10) {
  global $conn;
  
  try {
    $stmt = $conn->prepare("
      SELECT 
        p.amount,
        p.payment_date,
        CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
        pr.name as program_name,
        p.status
      FROM payments p
      JOIN enrollments e ON p.enrollment_id = e.id
      JOIN student_profiles sp ON e.student_user_id = sp.user_id
      JOIN programs pr ON e.program_id = pr.id
      ORDER BY p.payment_date DESC
      LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
      $payments[] = $row;
    }
    
    return $payments;
    
  } catch (Exception $e) {
    error_log("Error getting recent payments: " . $e->getMessage());
    return [];
  }
}

/**
 * Get enrollment statistics for reports
 * @return array
 */
function getEnrollmentStats() {
  global $conn;
  
  try {
    // Get total enrollments
    $totalEnrollmentsStmt = $conn->query("SELECT COUNT(*) as count FROM enrollments");
    $totalEnrollments = $totalEnrollmentsStmt->fetch_assoc()['count'];
    
    // Get active students (students with active enrollments)
    $activeStudentsStmt = $conn->query("
      SELECT COUNT(DISTINCT e.student_user_id) as count 
      FROM enrollments e 
      WHERE e.status = 'active'
    ");
    $activeStudents = $activeStudentsStmt->fetch_assoc()['count'];
    
    // Calculate completion rate based on completed vs total enrollments
    $completedEnrollmentsStmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'completed'");
    $completedEnrollments = $completedEnrollmentsStmt->fetch_assoc()['count'];
    
    $completionRate = $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100, 0) : 0;
    
    // Get enrollments from last month for growth calculation
    $lastMonthEnrollmentsStmt = $conn->query("
      SELECT COUNT(*) as count 
      FROM enrollments 
      WHERE created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 MONTH), INTERVAL 1 MONTH) 
      AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    $lastMonthEnrollments = $lastMonthEnrollmentsStmt->fetch_assoc()['count'];
    
    // Get this month's enrollments
    $thisMonthEnrollmentsStmt = $conn->query("
      SELECT COUNT(*) as count 
      FROM enrollments 
      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    $thisMonthEnrollments = $thisMonthEnrollmentsStmt->fetch_assoc()['count'];
    
    // Calculate growth percentages
    $enrollmentGrowth = $lastMonthEnrollments > 0 ? 
      round((($thisMonthEnrollments - $lastMonthEnrollments) / $lastMonthEnrollments) * 100, 0) : 
      ($thisMonthEnrollments > 0 ? 100 : 0);
    
    // Similar calculation for students growth
    $lastMonthActiveStmt = $conn->query("
      SELECT COUNT(DISTINCT e.student_user_id) as count 
      FROM enrollments e 
      WHERE e.status = 'active' 
      AND e.created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 MONTH), INTERVAL 1 MONTH) 
      AND e.created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    $lastMonthActive = $lastMonthActiveStmt->fetch_assoc()['count'];
    
    $studentsGrowth = $lastMonthActive > 0 ? 
      round((($activeStudents - $lastMonthActive) / $lastMonthActive) * 100, 0) : 
      ($activeStudents > 0 ? 100 : 0);
    
    // Get last month's completion rate for comparison
    $lastMonthCompletedStmt = $conn->query("
      SELECT COUNT(*) as count 
      FROM enrollments 
      WHERE status = 'completed' 
      AND created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 MONTH), INTERVAL 1 MONTH) 
      AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    $lastMonthCompleted = $lastMonthCompletedStmt->fetch_assoc()['count'];
    $lastMonthCompletionRate = $lastMonthEnrollments > 0 ? round(($lastMonthCompleted / $lastMonthEnrollments) * 100, 0) : 0;
    
    $completionGrowth = $lastMonthCompletionRate > 0 ? 
      round($completionRate - $lastMonthCompletionRate, 0) : 
      $completionRate;
    
    return [
      'total_enrollments' => $totalEnrollments,
      'active_students' => $activeStudents,
      'completion_rate' => $completionRate,
      'enrollment_growth' => $enrollmentGrowth,
      'students_growth' => $studentsGrowth,
      'completion_growth' => $completionGrowth
    ];
    
  } catch (Exception $e) {
    error_log("Error getting enrollment stats: " . $e->getMessage());
    return [
      'total_enrollments' => 0,
      'active_students' => 0,
      'completion_rate' => 0,
      'enrollment_growth' => 0,
      'students_growth' => 0,
      'completion_growth' => 0
    ];
  }
}

/**
 * Get schedule/session occupancy statistics for reports
 * @return array
 */
function getScheduleStats() {
  global $conn;
  
  try {
    // Get total scheduled sessions
    $totalSessionsStmt = $conn->query("SELECT COUNT(*) as count FROM sessions");
    $totalSessions = $totalSessionsStmt->fetch_assoc()['count'];
    
    // Get occupied sessions (sessions with enrollments or that are active)
    $occupiedSessionsStmt = $conn->query("
      SELECT COUNT(DISTINCT s.id) as count 
      FROM sessions s 
      INNER JOIN enrollments e ON s.enrollment_id = e.id 
      WHERE e.status = 'active'
    ");
    $occupiedSessions = $occupiedSessionsStmt->fetch_assoc()['count'];
    
    // Calculate available sessions
    $availableSessions = $totalSessions - $occupiedSessions;
    
    // Calculate occupancy rate
    $occupancyRate = $totalSessions > 0 ? round(($occupiedSessions / $totalSessions) * 100, 0) : 0;
    
    return [
      'total_schedules' => $totalSessions,
      'occupied' => $occupiedSessions,
      'available' => $availableSessions,
      'occupancy_rate' => $occupancyRate
    ];
    
  } catch (Exception $e) {
    error_log("Error getting schedule stats: " . $e->getMessage());
    return [
      'total_schedules' => 0,
      'occupied' => 0,
      'available' => 0,
      'occupancy_rate' => 0
    ];
  }
}

/**
 * Get student's assignments and assessments with grades for a program
 * @param int $program_id The program ID
 * @param int $student_user_id The student's user ID
 * @return array Student's assignments and assessments with grades
 */
function getStudentProgramGrades($program_id, $student_user_id) {
  global $conn;
  
  try {
    // Convert string username to integer user_id for database queries
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $student_user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    
    if (!$user_data) {
      throw new Exception("User not found: $student_user_id");
    }
    
    $student_db_id = $user_data['id'];
    $assignments = [];
    
    // Get assignments with submissions and grades  
    $stmt = $conn->prepare("
      SELECT 
        'assignment' as type,
        asn.id,
        asn.title,
        asn.due_date,
        asn.max_score as total_points,
        asub.submission_date as submitted_at,
        asub.score as grade,
        asub.status,
        asub.feedback
      FROM assignments asn
      LEFT JOIN assignment_submissions asub ON asn.id = asub.assignment_id AND asub.student_user_id = ?
      WHERE asn.program_id = ?
      
      UNION ALL
      
      SELECT 
        'assessment' as type,
        a.id,
        a.title,
        a.due_date,
        a.total_points,
        att.submitted_at,
        att.score as grade,
        att.status,
        att.comments as feedback  
      FROM assessments a
      JOIN program_materials pm ON a.material_id = pm.id
      LEFT JOIN assessment_attempts att ON a.id = att.assessment_id AND att.student_user_id = ?
      WHERE pm.program_id = ?
      
      ORDER BY due_date ASC
    ");
    
    $stmt->bind_param('iiii', $student_db_id, $program_id, $student_db_id, $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_assignments = 0;
    $total_assessments = 0;
    $assignment_points = 0;
    $assessment_points = 0;
    $assignment_earned = 0;
    $assessment_earned = 0;
    
    while ($row = $result->fetch_assoc()) {
      $status = 'upcoming';
      if ($row['submitted_at']) {
        $status = $row['grade'] !== null ? 'graded' : 'submitted';
      } elseif ($row['due_date'] && strtotime($row['due_date']) < time()) {
        $status = 'overdue';
      }
      
      $grade_display = 'Not Started';
      $grade_percentage = 0;
      
      if ($row['grade'] !== null && $row['total_points'] > 0) {
        $grade_percentage = ($row['grade'] / $row['total_points']) * 100;
        $grade_display = number_format($grade_percentage, 0) . '%';
      } elseif ($status === 'submitted') {
        $grade_display = 'Pending';
      }
      
      $assignments[] = [
        'id' => $row['id'],
        'name' => $row['title'],
        'type' => ucfirst($row['type']),
        'date' => $row['due_date'] ? date('M j, Y', strtotime($row['due_date'])) : 'TBD',
        'grade' => $grade_display,
        'grade_percentage' => $grade_percentage,
        'status' => $status,
        'total_points' => $row['total_points'],
        'earned_points' => $row['grade']
      ];
      
      // Calculate totals for averages
      if ($row['type'] === 'assignment') {
        $total_assignments++;
        $assignment_points += $row['total_points'] ?? 0;
        $assignment_earned += $row['grade'] ?? 0;
      } else {
        $total_assessments++;
        $assessment_points += $row['total_points'] ?? 0;
        $assessment_earned += $row['grade'] ?? 0;
      }
    }
    
    // Calculate averages
    $assignment_avg = $assignment_points > 0 ? ($assignment_earned / $assignment_points) * 100 : 0;
    $assessment_avg = $assessment_points > 0 ? ($assessment_earned / $assessment_points) * 100 : 0;
    $overall_grade = ($assignment_points + $assessment_points) > 0 ? 
      (($assignment_earned + $assessment_earned) / ($assignment_points + $assessment_points)) * 100 : 0;
    
    // Determine letter grade
    $letter_grade = 'F';
    if ($overall_grade >= 97) $letter_grade = 'A+';
    elseif ($overall_grade >= 93) $letter_grade = 'A';
    elseif ($overall_grade >= 90) $letter_grade = 'A-';
    elseif ($overall_grade >= 87) $letter_grade = 'B+';
    elseif ($overall_grade >= 83) $letter_grade = 'B';
    elseif ($overall_grade >= 80) $letter_grade = 'B-';
    elseif ($overall_grade >= 77) $letter_grade = 'C+';
    elseif ($overall_grade >= 73) $letter_grade = 'C';
    elseif ($overall_grade >= 70) $letter_grade = 'C-';
    elseif ($overall_grade >= 67) $letter_grade = 'D+';
    elseif ($overall_grade >= 65) $letter_grade = 'D';
    
    return [
      'assignments' => $assignments,
      'summary' => [
        'assignment_avg' => round($assignment_avg, 0),
        'assessment_avg' => round($assessment_avg, 0),
        'overall_grade' => round($overall_grade, 0),
        'letter_grade' => $letter_grade,
        'total_assignments' => $total_assignments,
        'total_assessments' => $total_assessments
      ]
    ];
    
  } catch (Exception $e) {
    error_log("Error getting student program grades: " . $e->getMessage());
    return [
      'assignments' => [],
      'summary' => [
        'assignment_avg' => 0,
        'assessment_avg' => 0,
        'overall_grade' => 0,
        'letter_grade' => 'N/A',
        'total_assignments' => 0,
        'total_assessments' => 0
      ]
    ];
  }
}

/**
 * Get program grade statistics for tutors
 * @param int $program_id The program ID
 * @return array Program grade statistics and student list
 */
function getProgramGradeStatistics($program_id) {
  global $conn;
  
  try {
    // Get program info
    $stmt = $conn->prepare("SELECT name FROM programs WHERE id = ?");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $program_result = $stmt->get_result();
    $program = $program_result->fetch_assoc();
    
    if (!$program) {
      return ['error' => 'Program not found'];
    }
    
    // Get all students with their grades
    $students = [];
    $total_grades = [];
    
    $stmt = $conn->prepare("
      SELECT DISTINCT
        e.student_user_id,
        u.username as user_id_string,
        sp.first_name,
        sp.last_name,
        u.email
      FROM enrollments e
      JOIN users u ON e.student_user_id = u.id
      JOIN student_profiles sp ON e.student_user_id = sp.user_id
      WHERE e.program_id = ? AND e.status = 'active'
      ORDER BY sp.first_name, sp.last_name
    ");
    
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
      $student_grades = getStudentProgramGrades($program_id, $row['user_id_string']);
      
      $students[] = [
        'id' => $row['student_user_id'],
        'user_id_string' => $row['user_id_string'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'email' => $row['email'],
        'assessment_avg' => $student_grades['summary']['assessment_avg'],
        'assignment_avg' => $student_grades['summary']['assignment_avg'],
        'overall_grade' => $student_grades['summary']['overall_grade'],
        'letter_grade' => $student_grades['summary']['letter_grade'],
        'total_assignments' => $student_grades['summary']['total_assignments'],
        'total_assessments' => $student_grades['summary']['total_assessments']
      ];
      
      if ($student_grades['summary']['overall_grade'] > 0) {
        $total_grades[] = $student_grades['summary']['overall_grade'];
      }
    }
    
    // Calculate class statistics
    $class_average = count($total_grades) > 0 ? round(array_sum($total_grades) / count($total_grades), 1) : 0;
    $highest_grade = count($total_grades) > 0 ? max($total_grades) : 0;
    $lowest_grade = count($total_grades) > 0 ? min($total_grades) : 0;
    
    return [
      'program_name' => $program['name'],
      'students' => $students,
      'statistics' => [
        'class_average' => $class_average,
        'highest_grade' => $highest_grade,
        'lowest_grade' => $lowest_grade,
        'total_students' => count($students)
      ]
    ];
    
  } catch (Exception $e) {
    error_log("Error getting program grade statistics: " . $e->getMessage());
    return ['error' => 'Database error occurred'];
  }
}

/**
 * Get student attendance data for a specific program
 * @param int $program_id The program ID
 * @param int $student_user_id The student's user ID
 * @return array Array containing attendance summary and session details
 */
function getStudentAttendance($program_id, $student_user_id) {
  global $conn;

  try {
    // Get all sessions for this student in this program (across all enrollments)
    // Handle case where student might have multiple enrollments
    $sessions_sql = "
      SELECT 
        s.id,
        s.session_date,
        s.start_time,
        s.end_time,
        s.status,
        s.notes,
        s.student_attended,
        s.created_at
      FROM sessions s 
      INNER JOIN enrollments e ON s.enrollment_id = e.id
      WHERE e.student_user_id = ? 
        AND e.program_id = ? 
        AND e.status IN ('active', 'paused')
      ORDER BY s.session_date DESC, s.start_time DESC
    ";
    
    $sessions_stmt = $conn->prepare($sessions_sql);
    $sessions_stmt->bind_param('ii', $student_user_id, $program_id);
    $sessions_stmt->execute();
    $sessions_result = $sessions_stmt->get_result();
    
    $sessions = [];
    $total_sessions = 0;
    $attended_sessions = 0;
    
    if ($sessions_result) {
      while ($row = $sessions_result->fetch_assoc()) {
        $total_sessions++;
        
        // Count attendance based on status and student_attended flag
        if ($row['status'] === 'completed' && $row['student_attended'] == 1) {
          $attended_sessions++;
        }
        
        $sessions[] = $row;
      }
    }
    
    // Calculate attendance rate
    $attendance_rate = $total_sessions > 0 ? round(($attended_sessions / $total_sessions) * 100, 1) : 0;
    
    return [
      'summary' => [
        'total_sessions' => $total_sessions,
        'attended_sessions' => $attended_sessions,
        'attendance_rate' => $attendance_rate
      ],
      'sessions' => $sessions
    ];
    
  } catch (Exception $e) {
    error_log("Error getting student attendance: " . $e->getMessage());
    return ['error' => 'Database error occurred'];
  }
}
?>
