<?php
// Simple test page to check what's happening
session_start();
require_once '../../includes/db.php';

echo "<h1>Tutor Students Debug Page</h1>";

echo "<h2>Session Information:</h2>";
echo "<pre>";
echo "Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Session Username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";
echo "Session Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "Session data: " . print_r($_SESSION, true);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    $tutor_user_id = $_SESSION['user_id'];
    
    echo "<h2>Database Query Test:</h2>";
    
    $sql = "
      SELECT 
        e.student_user_id as id,
        COALESCE(sp.first_name, SUBSTRING_INDEX(u.username, '-', 1)) as first_name,
        COALESCE(sp.last_name, SUBSTRING_INDEX(u.username, '-', -1)) as last_name,
        u.email,
        COALESCE(sp.address, 'N/A') as address,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') as program_names,
        COUNT(DISTINCT p.id) as program_count
      FROM programs p
      INNER JOIN enrollments e ON p.id = e.program_id
      INNER JOIN users u ON e.student_user_id = u.id
      LEFT JOIN student_profiles sp ON u.id = sp.user_id
      WHERE p.tutor_id = ? 
      AND e.status IN ('active', 'pending', 'completed')
      GROUP BY e.student_user_id, u.email, u.username, sp.first_name, sp.last_name, sp.address
      ORDER BY COALESCE(sp.first_name, u.username), COALESCE(sp.last_name, '')
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tutor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<p>Tutor ID: $tutor_user_id</p>";
    echo "<p>Query rows returned: " . $result->num_rows . "</p>";
    
    if ($result->num_rows > 0) {
        echo "<h3>Students found:</h3>";
        echo "<pre>";
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    } else {
        echo "<p>No students found for this tutor.</p>";
        
        // Additional debugging - check if tutor has any programs
        echo "<h3>Programs for this tutor:</h3>";
        $prog_sql = "SELECT id, name FROM programs WHERE tutor_id = ?";
        $prog_stmt = $conn->prepare($prog_sql);
        $prog_stmt->bind_param('i', $tutor_user_id);
        $prog_stmt->execute();
        $prog_result = $prog_stmt->get_result();
        
        if ($prog_result->num_rows > 0) {
            echo "<ul>";
            while ($prog_row = $prog_result->fetch_assoc()) {
                echo "<li>Program ID: {$prog_row['id']}, Name: {$prog_row['name']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No programs assigned to this tutor.</p>";
        }
    }
} else {
    echo "<p style='color: red;'>No user session found. Please log in.</p>";
}
?>