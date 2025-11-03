<?php
// Debug version of auth.php that shows what's happening
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
  session_start();
}

function isLoggedIn()
{
  return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

function requireRole($role)
{
  if (!isLoggedIn() || $_SESSION['role'] !== $role) {
    // Instead of silent redirect, show debug info
    echo "<!DOCTYPE html><html><head><title>Authentication Required</title>";
    echo "<style>body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }";
    echo ".container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; }";
    echo ".error { color: #d32f2f; background: #ffebee; padding: 15px; border-radius: 5px; margin: 20px 0; }";
    echo ".info { color: #1976d2; background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }";
    echo ".btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; text-decoration: none; border-radius: 5px; font-weight: bold; }";
    echo ".btn-primary { background: #2196F3; color: white; }";
    echo ".btn-success { background: #4CAF50; color: white; }";
    echo "</style></head><body><div class='container'>";
    
    echo "<h1>🔒 Authentication Required</h1>";
    echo "<div class='error'><strong>Access Denied:</strong> You need to be logged in as a <strong>'$role'</strong> to access this page.</div>";
    
    if (!isLoggedIn()) {
      echo "<div class='info'><strong>Current Status:</strong> Not logged in</div>";
    } else {
      echo "<div class='info'><strong>Current Role:</strong> " . ($_SESSION['role'] ?? 'none') . " (need: $role)</div>";
    }
    
    echo "<h2>Quick Actions:</h2>";
    echo "<a href='../../auto_login_simple.php' class='btn btn-success'>🚀 Auto-Login Helper</a>";
    echo "<a href='../../login.php' class='btn btn-primary'>🔑 Regular Login</a>";
    
    echo "<h3>Debug Info:</h3>";
    echo "<pre>Session Data: " . print_r($_SESSION, true) . "</pre>";
    echo "<p><strong>Current URL:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "</p>";
    
    echo "</div></body></html>";
    exit();
  }
}

// Include other auth functions for compatibility
function isAuthenticated() { return isLoggedIn(); }
function hasRole($role) { return isLoggedIn() && $_SESSION['role'] === $role; }
function getCurrentUserId() { return $_SESSION['user_id'] ?? null; }
function getCurrentUserRole() { return $_SESSION['role'] ?? null; }
?>