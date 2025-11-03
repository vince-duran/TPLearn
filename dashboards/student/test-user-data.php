<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireRole('student');

echo "<h2>Testing User Data for Enrollment Process</h2>";

$student_user_id = $_SESSION['user_id'];
echo "<p><strong>User ID from session:</strong> " . htmlspecialchars($student_user_id) . "</p>";

try {
    $student_sql = "SELECT * FROM users WHERE id = ?";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("i", $student_user_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    $current_student = $student_result->fetch_assoc();

    if ($current_student) {
        echo "<h3>✅ User Data Retrieved Successfully:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        foreach ($current_student as $key => $value) {
            echo "<tr><td><strong>" . htmlspecialchars($key) . "</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
        
        // Test the name parsing logic
        if (isset($current_student['name'])) {
            $student_name = $current_student['name'];
            $name_parts = explode(' ', trim($student_name));
            $first_name = $name_parts[0] ?? 'Student';
            $last_name = isset($name_parts[1]) ? end($name_parts) : '';
            $student_initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
            
            echo "<h3>✅ Parsed Name Data:</h3>";
            echo "<ul>";
            echo "<li><strong>Full Name:</strong> " . htmlspecialchars($student_name) . "</li>";
            echo "<li><strong>First Name:</strong> " . htmlspecialchars($first_name) . "</li>";
            echo "<li><strong>Last Name:</strong> " . htmlspecialchars($last_name) . "</li>";
            echo "<li><strong>Initials:</strong> " . htmlspecialchars($student_initials) . "</li>";
            echo "</ul>";
        } else {
            echo "<p>❌ No 'name' field found in user data</p>";
        }
        
    } else {
        echo "<p>❌ No user data found for ID: " . htmlspecialchars($student_user_id) . "</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='enrollment-process.php?program_id=1'>← Back to Enrollment Process</a></p>";
?>