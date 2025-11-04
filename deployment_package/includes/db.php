<?php
// =============================
// Database Connection (db.php)
// =============================

// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Show PHP errors during development (disable later in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database credentials (adjust if needed)
$host = "localhost";
$user = "root";       // default user in XAMPP
$pass = "";           // default password is empty
$dbname = "tplearn";  // make sure this database exists in phpMyAdmin

// Create MySQLi connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check MySQLi connection
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}

// Set charset to handle special characters properly
$conn->set_charset("utf8mb4");

// Create PDO connection for APIs that need it
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("PDO Database connection failed: " . $e->getMessage());
}

// Database helper functions
class Database
{
  private $conn;

  public function __construct($connection)
  {
    $this->conn = $connection;
  }

  // Execute a prepared statement and return results
  public function executeQuery($sql, $params = [], $types = '')
  {
    $stmt = $this->conn->prepare($sql);
    if (!$stmt) {
      throw new Exception("Prepare failed: " . $this->conn->error);
    }

    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    if ($stmt->error) {
      throw new Exception("Execute failed: " . $stmt->error);
    }

    return $stmt;
  }

  // Get single row
  public function getRow($sql, $params = [], $types = '')
  {
    $stmt = $this->executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
  }

  // Get multiple rows
  public function getRows($sql, $params = [], $types = '')
  {
    $stmt = $this->executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }
    $stmt->close();
    return $rows;
  }

  // Insert and return last insert ID
  public function insert($sql, $params = [], $types = '')
  {
    $stmt = $this->executeQuery($sql, $params, $types);
    $lastId = $this->conn->insert_id;
    $stmt->close();
    return $lastId;
  }

  // Update/Delete and return affected rows
  public function execute($sql, $params = [], $types = '')
  {
    $stmt = $this->executeQuery($sql, $params, $types);
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    return $affectedRows;
  }

  // Get connection for direct access if needed
  public function getConnection()
  {
    return $this->conn;
  }

  // Start transaction
  public function beginTransaction()
  {
    $this->conn->autocommit(false);
  }

  // Commit transaction
  public function commit()
  {
    $this->conn->commit();
    $this->conn->autocommit(true);
  }

  // Rollback transaction
  public function rollback()
  {
    $this->conn->rollback();
    $this->conn->autocommit(true);
  }
}

// Create database instance
$db = new Database($conn);

// User management functions
class UserManager
{
  private $db;

  public function __construct($database)
  {
    $this->db = $database;
  }

  // Get user by ID
  public function getUserById($id)
  {
    return $this->db->getRow(
      "SELECT * FROM users WHERE id = ?",
      [$id],
      "i"
    );
  }

  // Get user by username or email
  public function getUserByLogin($usernameOrEmail)
  {
    return $this->db->getRow(
      "SELECT * FROM users WHERE username = ? OR email = ?",
      [$usernameOrEmail, $usernameOrEmail],
      "ss"
    );
  }

  // Create new user
  public function createUser($username, $email, $password, $role = 'student')
  {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    return $this->db->insert(
      "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)",
      [$username, $email, $hashedPassword, $role],
      "ssss"
    );
  }

  // Update user status
  public function updateUserStatus($id, $status)
  {
    return $this->db->execute(
      "UPDATE users SET status = ? WHERE id = ?",
      [$status, $id],
      "si"
    );
  }

  // Get all users by role
  public function getUsersByRole($role)
  {
    return $this->db->getRows(
      "SELECT u.*, 
                    CASE 
                        WHEN u.role = 'student' THEN CONCAT(sp.first_name, ' ', sp.last_name)
                        WHEN u.role = 'tutor' THEN CONCAT(tp.first_name, ' ', tp.last_name)
                        ELSE u.username
                    END as full_name
             FROM users u 
             LEFT JOIN student_profiles sp ON u.id = sp.user_id 
             LEFT JOIN tutor_profiles tp ON u.id = tp.user_id 
             WHERE u.role = ?
             ORDER BY u.created_at DESC",
      [$role],
      "s"
    );
  }
}

// Program management functions
class ProgramManager
{
  private $db;

  public function __construct($database)
  {
    $this->db = $database;
  }

  // Get all programs
  public function getAllPrograms()
  {
    return $this->db->getRows(
      "SELECT p.*, 
                    COUNT(e.id) as enrollment_count,
                    SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_enrollments
             FROM programs p 
             LEFT JOIN enrollments e ON p.id = e.program_id 
             GROUP BY p.id 
             ORDER BY p.name"
    );
  }

  // Get program by ID
  public function getProgramById($id)
  {
    return $this->db->getRow(
      "SELECT * FROM programs WHERE id = ?",
      [$id],
      "i"
    );
  }

  // Create new program
  public function createProgram($name, $description, $duration_weeks, $fee)
  {
    return $this->db->insert(
      "INSERT INTO programs (name, description, duration_weeks, fee) VALUES (?, ?, ?, ?)",
      [$name, $description, $duration_weeks, $fee],
      "ssid"
    );
  }

  // Update program
  public function updateProgram($id, $name, $description, $duration_weeks, $fee, $status)
  {
    return $this->db->execute(
      "UPDATE programs SET name = ?, description = ?, duration_weeks = ?, fee = ?, status = ? WHERE id = ?",
      [$name, $description, $duration_weeks, $fee, $status, $id],
      "ssidsi"
    );
  }
}

// Enrollment management functions
class EnrollmentManager
{
  private $db;

  public function __construct($database)
  {
    $this->db = $database;
  }

  // Create enrollment with capacity checking
  public function createEnrollment($student_user_id, $program_id, $tutor_user_id = null)
  {
    global $conn; // Use global connection for transaction management
    
    // Start transaction to ensure data integrity
    $conn->autocommit(false);
    
    try {
      // Check program capacity with lock to prevent race conditions
      $capacity_check = $this->db->getRow(
        "SELECT p.max_students, COUNT(e.id) as enrolled_count, p.name, p.fee
         FROM programs p 
         LEFT JOIN enrollments e ON p.id = e.program_id AND e.status IN ('pending', 'active')
         WHERE p.id = ? AND p.status = 'active'
         GROUP BY p.id, p.max_students, p.name, p.fee
         FOR UPDATE",
        [$program_id],
        "i"
      );

      if (!$capacity_check) {
        throw new Exception('Program not found or inactive');
      }

      if ($capacity_check['enrolled_count'] >= $capacity_check['max_students']) {
        throw new Exception('Program is at full capacity (' . $capacity_check['enrolled_count'] . '/' . $capacity_check['max_students'] . ')');
      }

      // Check for duplicate enrollment
      $existing = $this->db->getRow(
        "SELECT id FROM enrollments WHERE student_user_id = ? AND program_id = ? AND status IN ('pending', 'active')",
        [$student_user_id, $program_id],
        "ii"
      );

      if ($existing) {
        throw new Exception('Student is already enrolled in this program');
      }

      // Create the enrollment
      $enrollment_id = $this->db->insert(
        "INSERT INTO enrollments (student_user_id, program_id, tutor_user_id, enrollment_date, total_fee, status) VALUES (?, ?, ?, CURDATE(), ?, 'pending')",
        [$student_user_id, $program_id, $tutor_user_id, $capacity_check['fee']],
        "iiid"
      );

      // Commit the transaction
      $conn->commit();
      $conn->autocommit(true);
      
      return $enrollment_id;
      
    } catch (Exception $e) {
      // Rollback on error
      $conn->rollback();
      $conn->autocommit(true);
      throw $e;
    }
  }

  // Get enrollments for student
  public function getStudentEnrollments($student_user_id)
  {
    return $this->db->getRows(
      "SELECT e.*, p.name as program_name, p.description as program_description,
                    CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                    SUM(pay.amount) as total_paid,
                    (e.total_fee - IFNULL(SUM(pay.amount), 0)) as balance
             FROM enrollments e
             JOIN programs p ON e.program_id = p.id
             LEFT JOIN tutor_profiles tp ON e.tutor_user_id = tp.user_id
             LEFT JOIN payments pay ON e.id = pay.enrollment_id AND pay.status = 'validated'
             WHERE e.student_user_id = ?
             GROUP BY e.id
             ORDER BY e.created_at DESC",
      [$student_user_id],
      "i"
    );
  }

  // Get all enrollments with details
  public function getAllEnrollments()
  {
    return $this->db->getRows(
      "SELECT e.*, p.name as program_name,
                    CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                    CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                    u.username as student_id,
                    SUM(pay.amount) as total_paid,
                    (e.total_fee - IFNULL(SUM(pay.amount), 0)) as balance
             FROM enrollments e
             JOIN programs p ON e.program_id = p.id
             JOIN users u ON e.student_user_id = u.id
             JOIN student_profiles sp ON e.student_user_id = sp.user_id
             LEFT JOIN tutor_profiles tp ON e.tutor_user_id = tp.user_id
             LEFT JOIN payments pay ON e.id = pay.enrollment_id AND pay.status = 'validated'
             GROUP BY e.id
             ORDER BY e.created_at DESC"
    );
  }
}

// Payment management functions
class PaymentManager
{
  private $db;

  public function __construct($database)
  {
    $this->db = $database;
  }

  // Record payment
  public function recordPayment($enrollment_id, $amount, $payment_method, $reference_number = null, $notes = null)
  {
    return $this->db->insert(
      "INSERT INTO payments (enrollment_id, amount, payment_date, payment_method, reference_number, notes) VALUES (?, ?, CURDATE(), ?, ?, ?)",
      [$enrollment_id, $amount, $payment_method, $reference_number, $notes],
      "idsss"
    );
  }

  // Validate payment and update enrollment status
  public function validatePayment($payment_id, $validator_id, $status = 'validated')
  {
    // Start transaction
    $this->conn->begin_transaction();
    
    try {
      // Update payment status
      $payment_result = $this->db->execute(
        "UPDATE payments SET status = ?, validated_by = ?, validated_at = NOW() WHERE id = ?",
        [$status, $validator_id, $payment_id],
        "sii"
      );
      
      // If payment is being validated (approved), check if enrollment should be activated
      if ($status === 'validated' && $payment_result > 0) {
        // Get the enrollment and payment details
        $payment_data = $this->db->getRow(
          "SELECT e.id as enrollment_id, e.status as enrollment_status, 
                  p.installment_number, p.enrollment_id
           FROM payments p
           JOIN enrollments e ON e.id = p.enrollment_id 
           WHERE p.id = ?",
          [$payment_id],
          "i"
        );
        
        if ($payment_data && $payment_data['enrollment_status'] === 'pending') {
          // Check if this is the first installment or a single payment
          $installment_number = $payment_data['installment_number'] ?? 1;
          
          // For first installment (or single payment), activate the enrollment
          if ($installment_number <= 1) {
            $this->db->execute(
              "UPDATE enrollments SET status = 'active' WHERE id = ?",
              [$payment_data['enrollment_id']],
              "i"
            );
          }
          // For subsequent installments, check if there's at least one validated payment
          else {
            $validated_payments = $this->db->getRow(
              "SELECT COUNT(*) as count FROM payments 
               WHERE enrollment_id = ? AND status = 'validated'",
              [$payment_data['enrollment_id']],
              "i"
            );
            
            // If this makes at least one validated payment, activate enrollment
            if ($validated_payments && $validated_payments['count'] >= 1) {
              $this->db->execute(
                "UPDATE enrollments SET status = 'active' WHERE id = ?",
                [$payment_data['enrollment_id']],
                "i"
              );
            }
          }
        }
      }
      
      $this->conn->commit();
      return $payment_result;
    } catch (Exception $e) {
      $this->conn->rollback();
      throw $e;
    }
  }

  // Get all payments
  public function getAllPayments()
  {
    return $this->db->getRows(
      "SELECT p.*, e.id as enrollment_id, pr.name as program_name,
                    CONCAT(sp.first_name, ' ', sp.last_name) as student_name,
                    u.username as student_id,
                    CONCAT(vp.first_name, ' ', vp.last_name) as validator_name
             FROM payments p
             JOIN enrollments e ON p.enrollment_id = e.id
             JOIN programs pr ON e.program_id = pr.id
             JOIN users u ON e.student_user_id = u.id
             JOIN student_profiles sp ON e.student_user_id = sp.user_id
             LEFT JOIN users v ON p.validated_by = v.id
             LEFT JOIN student_profiles vp ON v.id = vp.user_id
             ORDER BY p.created_at DESC"
    );
  }
}

// Create instances
$userManager = new UserManager($db);
$programManager = new ProgramManager($db);
$enrollmentManager = new EnrollmentManager($db);
$paymentManager = new PaymentManager($db);
