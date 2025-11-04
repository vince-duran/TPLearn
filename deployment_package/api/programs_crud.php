<?php

/**
 * Programs CRUD API
 * Handles create, read, update, delete operations for programs
 */

// Prevent any HTML output from PHP errors/warnings
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/data-helpers.php';
require_once '../includes/program-validation.php';
require_once '../includes/program-helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

/**
 * Parse multipart/form-data for PUT requests
 * PHP doesn't populate $_POST for PUT requests with multipart data
 */
function parseMultipartData($input, $boundary)
{
  $data = [];
  $boundary = '--' . $boundary;
  $parts = explode($boundary, $input);

  foreach ($parts as $part) {
    if (empty(trim($part))) continue;

    // Split headers and content
    $sections = explode("\r\n\r\n", $part, 2);
    if (count($sections) != 2) continue;

    $headers = $sections[0];
    $content = rtrim($sections[1], "\r\n");

    // Extract field name from Content-Disposition header
    if (preg_match('/name="([^"]*)"/', $headers, $matches)) {
      $fieldName = $matches[1];
      $data[$fieldName] = $content;
    }
  }

  return $data;
}

// Check if user is logged in
if (!isLoggedIn()) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle method override for edit operations sent via POST
if ($method === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
  $method = 'PUT';
  // For edit operations, get the ID from POST data instead of URL
  if (isset($_POST['id'])) {
    $_GET['id'] = $_POST['id'];
  }
}

$action = $_GET['action'] ?? '';

try {
  switch ($method) {
    case 'GET':
      if ($action === 'get' && isset($_GET['id'])) {
        // Get single program by ID with calculated status
        $programId = (int)$_GET['id'];
        $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

        $programs = getProgramsWithCalculatedStatus(['id' => $programId]);

        if (!empty($programs)) {
          $program = $programs[0];

          // Add calculated program status
          $program['status'] = calculateProgramStatus($program);
          
          // Add formatted schedule and duration
          $program['formatted_schedule'] = formatProgramSchedule(
            $program['days'],
            $program['start_time'],
            $program['end_time']
          );
          $program['formatted_duration'] = formatProgramDuration(
            $program['start_date'],
            $program['end_date']
          );

          // Add program statistics (with error handling)
          try {
            $program['stats'] = getProgramStats($programId);
          } catch (Exception $e) {
            error_log("Error getting program stats: " . $e->getMessage());
            $program['stats'] = [
              'total_students' => 0,
              'completed_students' => 0,
              'active_students' => 0,
              'completion_rate' => 0
            ];
          }

          // If student_id is provided, add enrollment status and progress
          if ($studentId) {
            $enrollmentQuery = "SELECT status FROM enrollments WHERE student_user_id = ? AND program_id = ?";
            $stmt = $conn->prepare($enrollmentQuery);
            $stmt->bind_param('ii', $studentId, $programId);
            $stmt->execute();
            $result = $stmt->get_result();
            $enrollment = $result->fetch_assoc();

            $program['enrollment_status'] = $enrollment ? $enrollment['status'] : null;
            
            // Add student's progress if enrolled (with error handling)
            if ($enrollment) {
              try {
                $program['student_progress'] = getStudentProgramProgress($programId, $studentId);
              } catch (Exception $e) {
                error_log("Error getting student program progress: " . $e->getMessage());
                $program['student_progress'] = [
                  'completion_percentage' => 0,
                  'completed_materials' => 0,
                  'total_materials' => 0,
                  'last_activity' => null
                ];
              }
            }
          }

          echo json_encode(['success' => true, 'program' => $program]);
        } else {
          echo json_encode(['success' => false, 'message' => 'Program not found']);
        }
      } elseif ($action === 'search') {
        // Get programs with filters using calculated status
        $filters = [
          'status' => $_GET['status'] ?? '',
          'search' => $_GET['search'] ?? '',
          'tutor_id' => $_GET['tutor_id'] ?? ''
        ];

        $programs = getProgramsWithCalculatedStatus($filters);
        
        // Enhance each program with additional data
        foreach ($programs as &$program) {
          // Add calculated status
          $program['status'] = calculateProgramStatus($program);
          
          // Add formatted schedule and duration
          $program['formatted_schedule'] = formatProgramSchedule(
            $program['days'],
            $program['start_time'],
            $program['end_time']
          );
          $program['formatted_duration'] = formatProgramDuration(
            $program['start_date'],
            $program['end_date']
          );
          
          // Add summarized stats
          $stats = getProgramStats($program['id']);
          $program['summary'] = [
            'enrolled' => $stats['total_enrolled'],
            'active' => $stats['active_students'],
            'attendance_rate' => $stats['attendance_rate'],
            'avg_grade' => $stats['avg_grade']
          ];
        }
        unset($program); // Remove reference

        echo json_encode([
          'success' => true, 
          'programs' => $programs,
          'total' => count($programs)
        ]);
      } else {
        // Get all programs with calculated status
        $programs = getProgramsWithCalculatedStatus();
        echo json_encode(['success' => true, 'programs' => $programs]);
      }
      break;

    case 'POST':
      // Create new program
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      // Handle both JSON and form data
      $input = file_get_contents('php://input');
      $data = [];

      if (!empty($input)) {
        $data = json_decode($input, true) ?: [];
      }

      // Merge with POST data (form submission)
      $data = array_merge($data, $_POST);

      // Debug logging
      error_log("POST Request received data: " . json_encode($data, JSON_UNESCAPED_SLASHES));
      error_log("POST Request \$_POST: " . print_r($_POST, true));

      // Validate input
      $validation = validateProgramData($data);
      if (!$validation['isValid']) {
        $errorMessage = implode(". ", $validation['errors']);
        error_log("Validation failed: " . $errorMessage);
        throw new Exception($errorMessage);
      }

      // Format data for database
      $programData = formatProgramData($data);
      error_log("Formatted program data: " . json_encode($programData, JSON_UNESCAPED_SLASHES));

      $result = createProgram($programData);

      if ($result) {
        // Get the newly created program with calculated status
        $program = getProgramsWithCalculatedStatus(['id' => $result])[0];
        
        echo json_encode([
          'success' => true,
          'message' => 'Program created successfully',
          'program' => $program
        ]);
      } else {
        throw new Exception('Failed to create program');
      }
      break;

    case 'PUT':
      // Update program
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      // For PUT requests with FormData, we need to handle it specially
      $data = [];

      // Check if this is a multipart/form-data request
      $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

      if (strpos($contentType, 'multipart/form-data') !== false) {
        // For multipart/form-data in PUT, PHP doesn't populate $_POST
        // We need to manually parse the input
        $input = file_get_contents('php://input');

        // Parse multipart data manually
        if (!empty($input)) {
          $boundary = '';
          if (preg_match('/boundary=(.*)$/', $contentType, $matches)) {
            $boundary = $matches[1];
          }

          if ($boundary) {
            $data = parseMultipartData($input, $boundary);
          }
        }
      } else {
        // For regular PUT data
        $input = file_get_contents('php://input');
        if (!empty($input)) {
          parse_str($input, $data);
        }
      }

      // Fallback to $_POST if available
      if (empty($data)) {
        $data = $_POST;
      }

      // Also check GET parameters for ID
      if (isset($_GET['id'])) {
        $data['id'] = $_GET['id'];
      }

      // Debug logging - log raw input and content type
      error_log("PUT Request Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));
      error_log("PUT Request raw input length: " . strlen(file_get_contents('php://input')));
      error_log("PUT Request GET params: " . json_encode($_GET));
      error_log("PUT Request POST params: " . json_encode($_POST));
      error_log("PUT Request parsed data: " . json_encode($data));

      $id = $data['id'] ?? 0;

      if (!$id) {
        throw new Exception('Program ID is required for update');
      }

      // Check if program exists
      $existing = getProgramsWithCalculatedStatus(['id' => $id]);
      if (empty($existing)) {
        throw new Exception('Program not found');
      }

      // Load validation functions
      require_once '../includes/program-validation.php';

      // When updating, merge with existing data for partial updates
      $updateData = array_merge((array)$existing[0], $data);
      
      // Validate the merged data
      $validation = validateProgramData($updateData);
      if (!$validation['isValid']) {
        throw new Exception(implode(". ", $validation['errors']));
      }

      // Format data for database
      $programData = formatProgramData($updateData);

      // Debug logging
      error_log("PUT Request received data: " . json_encode($data));
      error_log("PUT Request formatted program data: " . json_encode($programData));

      $result = updateProgram($id, $programData);

      if ($result) {
        // Get the updated program with calculated status
        $program = getProgramsWithCalculatedStatus(['id' => $id])[0];
        
        echo json_encode([
          'success' => true,
          'message' => 'Program updated successfully',
          'program' => $program
        ]);
      } else {
        throw new Exception('Failed to update program');
      }
      break;

    case 'DELETE':
      // Delete program
      if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Admin access required');
      }

      // For DELETE requests with FormData, parse input stream
      $id = $_GET['id'] ?? $_POST['id'] ?? 0;

      if (!$id) {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
          parse_str($input, $deleteData);
          $id = $deleteData['id'] ?? 0;
        }
      }

      if (!$id) {
        throw new Exception('Program ID is required for delete');
      }

      try {
        $result = deleteProgram($id);
        
        if ($result) {
          echo json_encode([
            'success' => true,
            'message' => 'Program deleted successfully'
          ]);
        } else {
          throw new Exception('Failed to delete program');
        }
      } catch (Exception $deleteError) {
        // Pass through the specific error message from deleteProgram
        throw new Exception($deleteError->getMessage());
      }
      break;

    default:
      throw new Exception('Method not allowed');
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
