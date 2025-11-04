<?php
// =============================
// Database Connection Test for Production
// =============================

// This file helps verify database connection after deployment
// DELETE THIS FILE after successful deployment for security

echo "<h1>TPLearn Database Connection Test</h1>";
echo "<p>Testing database connection on production server...</p>";

// Load database configuration
require_once 'includes/db.php';

try {
    // Test basic connection
    if ($conn) {
        echo "<div style='color: green; padding: 10px; background: #e8f5e8; border: 1px solid #4CAF50; margin: 10px 0;'>";
        echo "✅ <strong>Database Connection: SUCCESS</strong><br>";
        echo "Connected to database: " . $dbname . "<br>";
        echo "Server info: " . $conn->server_info . "<br>";
        echo "Client info: " . $conn->client_info . "<br>";
        echo "</div>";
        
        // Test if tables exist
        $tables_to_check = ['users', 'programs', 'enrollments', 'payments'];
        echo "<h3>Database Tables Check:</h3>";
        
        foreach ($tables_to_check as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                echo "<div style='color: green;'>✅ Table '$table' exists</div>";
            } else {
                echo "<div style='color: red;'>❌ Table '$table' missing</div>";
            }
        }
        
        // Test sample query
        echo "<h3>Sample Data Test:</h3>";
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<div style='color: blue;'>📊 Total users in database: " . $row['count'] . "</div>";
        }
        
        echo "<div style='color: orange; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; margin: 20px 0;'>";
        echo "⚠️ <strong>Security Notice:</strong> Delete this file (check_db_connection.php) after testing!";
        echo "</div>";
        
    } else {
        throw new Exception("Connection object is null");
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; background: #f8d7da; border: 1px solid #dc3545; margin: 10px 0;'>";
    echo "❌ <strong>Database Connection: FAILED</strong><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "</div>";
    
    echo "<h3>Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li>Check database credentials in includes/db.php</li>";
    echo "<li>Verify database exists in hosting control panel</li>";
    echo "<li>Check database user permissions</li>";
    echo "<li>Ensure MySQL service is running</li>";
    echo "<li>Contact hosting support if issues persist</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<h3>Server Information:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Server Name: " . $_SERVER['SERVER_NAME'] . "<br>";

echo "<hr>";
echo "<p><a href='index.php'>← Back to TPLearn Home</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background: #f5f5f5;
}
h1 {
    color: #333;
    border-bottom: 2px solid #4CAF50;
    padding-bottom: 10px;
}
</style>