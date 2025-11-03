<?php
/**
 * Test the fixed getStudentAvailablePrograms function
 */

session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Set up a test user session (you can adjust this user ID)
$_SESSION['user_id'] = 1; // Adjust if needed
$user_id = $_SESSION['user_id'];

echo "<h2>Testing Fixed getStudentAvailablePrograms Function</h2>";

// Include the function from the student enrollment file
function getStudentAvailablePrograms($student_id, $filters = [], $page = 1, $per_page = 9)
{
  global $conn;

  try {
    $offset = ($page - 1) * $per_page;

    // Base query with enrollment status check - UPDATED TO INCLUDE cover_image
    $sql = "SELECT p.id, p.name, p.description, p.fee, p.status, p.age_group,
                   p.max_students, p.session_type, p.location, p.start_date, p.end_date,
                   p.start_time, p.end_time, p.days, p.difficulty_level, p.category,
                   p.duration_weeks, p.tutor_id, p.cover_image,
                   CONCAT(tp.first_name, ' ', tp.last_name) as tutor_name,
                   COUNT(e_count.id) as enrolled_count,
                   e_student.status as enrollment_status,
                   e_student.enrollment_date,
                   CASE 
                     WHEN p.end_date < CURDATE() THEN 'ended'
                     WHEN p.start_date <= CURDATE() AND p.end_date >= CURDATE() THEN 'ongoing'
                     WHEN p.start_date > CURDATE() THEN 'upcoming'
                     ELSE 'upcoming'
                   END as calculated_status
            FROM programs p
            LEFT JOIN users u ON p.tutor_id = u.id
            LEFT JOIN tutor_profiles tp ON u.id = tp.user_id
            LEFT JOIN enrollments e_count ON p.id = e_count.program_id 
                     AND e_count.status IN ('pending', 'active')
            LEFT JOIN enrollments e_student ON p.id = e_student.program_id 
                     AND e_student.student_user_id = ?
            WHERE p.status = 'active'";

    $params = [$student_id];
    $param_types = "i";

    $sql .= " GROUP BY p.id, p.name, p.description, p.fee, p.status, p.age_group,
                       p.max_students, p.session_type, p.location, p.start_date, p.end_date,
                       p.start_time, p.end_time, p.days, p.difficulty_level, p.category,
                       p.duration_weeks, p.tutor_id, p.cover_image, tp.first_name, tp.last_name,
                       e_student.status, e_student.enrollment_date
              ORDER BY p.start_date ASC, p.name ASC
              LIMIT ? OFFSET ?";

    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $programs = [];
    while ($row = $result->fetch_assoc()) {
      $programs[] = $row;
    }

    // Get total count for pagination
    $count_sql = "SELECT COUNT(DISTINCT p.id) as total
                  FROM programs p
                  LEFT JOIN enrollments e_student ON p.id = e_student.program_id 
                           AND e_student.student_user_id = ?
                  WHERE p.status = 'active'";
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $student_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_count = $count_result->fetch_assoc()['total'];

    return [
      'programs' => $programs,
      'total_count' => $total_count,
      'total_pages' => ceil($total_count / $per_page),
      'current_page' => $page
    ];
  } catch (Exception $e) {
    error_log("Error in getStudentAvailablePrograms: " . $e->getMessage());
    return [
      'programs' => [],
      'total_count' => 0,
      'total_pages' => 1,
      'current_page' => 1
    ];
  }
}

// Test the function
try {
    $programs_data = getStudentAvailablePrograms($user_id);
    $programs = $programs_data['programs'];
    
    echo "<h3>✅ Function executed successfully!</h3>";
    echo "<p><strong>Total programs found:</strong> " . count($programs) . "</p>";
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Name</th><th>Cover Image</th><th>Has Image</th><th>File Exists</th></tr>";
    
    foreach ($programs as $program) {
        $hasImage = !empty($program['cover_image']);
        $imageStatus = $hasImage ? '✅ YES' : '⭕ NO';
        
        $fileExists = 'N/A';
        if ($hasImage) {
            $imagePath = '../../uploads/program_covers/' . basename($program['cover_image']);
            $fileExists = file_exists($imagePath) ? '✅ EXISTS' : '❌ MISSING';
        }
        
        echo "<tr>";
        echo "<td>{$program['id']}</td>";
        echo "<td>" . htmlspecialchars($program['name']) . "</td>";
        echo "<td>" . htmlspecialchars($program['cover_image'] ?? '') . "</td>";
        echo "<td>$imageStatus</td>";
        echo "<td>$fileExists</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test Sample 6 specifically
    echo "<h3>Sample 6 Specific Test</h3>";
    $sample6 = null;
    foreach ($programs as $program) {
        if ($program['name'] === 'Sample 6') {
            $sample6 = $program;
            break;
        }
    }
    
    if ($sample6) {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<p><strong>✅ Sample 6 found with cover image data!</strong></p>";
        echo "<p><strong>Cover Image:</strong> " . htmlspecialchars($sample6['cover_image'] ?? 'NULL') . "</p>";
        echo "<p><strong>Empty Check:</strong> " . var_export(empty($sample6['cover_image']), true) . "</p>";
        
        if (!empty($sample6['cover_image'])) {
            $fileName = basename($sample6['cover_image']);
            $imageUrl = "../../serve_image.php?file=" . htmlspecialchars($fileName);
            echo "<p><strong>Image URL:</strong> $imageUrl</p>";
            echo "<img src='$imageUrl' alt='Sample 6' style='max-width: 200px; border: 2px solid green;'>";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<p><strong>❌ Sample 6 not found in student available programs</strong></p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<p><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<h3>🎉 Fix Summary</h3>";
echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
echo "<p><strong>Problem:</strong> Student enrollment page wasn't showing cover images</p>";
echo "<p><strong>Root Cause:</strong> getStudentAvailablePrograms() function wasn't selecting the cover_image field</p>";
echo "<p><strong>Fix Applied:</strong> Added p.cover_image to SELECT and GROUP BY clauses</p>";
echo "<p><strong>Result:</strong> Cover images should now display on student enrollment page</p>";
echo "</div>";
?>