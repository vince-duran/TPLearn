<?php
/**
 * Railway Domain Configuration Checker
 * Run this file to verify your Railway deployment configuration
 */

// Check if this is running on Railway
$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['RAILWAY_PUBLIC_DOMAIN']);

echo "<!DOCTYPE html>\n";
echo "<html><head><title>TPLearn Railway Configuration Check</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 40px; }
.info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
.success { background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0; }
.warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
.error { background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background-color: #f2f2f2; }
</style></head><body>";

echo "<h1>🚀 TPLearn Railway Configuration Check</h1>";

// Environment Detection
echo "<div class='" . ($isRailway ? "success" : "info") . "'>";
echo "<h2>Environment Detection</h2>";
echo "<p><strong>Running on:</strong> " . ($isRailway ? "Railway ✅" : "Local Development 🖥️") . "</p>";
echo "</div>";

// Domain Configuration
echo "<div class='info'>";
echo "<h2>Domain Configuration</h2>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";

if ($isRailway) {
    $domain = $_ENV['RAILWAY_PUBLIC_DOMAIN'] ?? $_ENV['APP_URL'] ?? 'Not Set';
    echo "<tr><td>Railway Domain</td><td>" . htmlspecialchars($domain) . "</td></tr>";
    echo "<tr><td>Base URL</td><td>https://" . htmlspecialchars($domain) . "</td></tr>";
} else {
    echo "<tr><td>Local Domain</td><td>localhost/TPLearn</td></tr>";
    echo "<tr><td>Base URL</td><td>http://localhost/TPLearn</td></tr>";
}

echo "</table>";
echo "</div>";

// Environment Variables Check
echo "<div class='info'>";
echo "<h2>Environment Variables</h2>";
echo "<table>";
echo "<tr><th>Variable</th><th>Status</th><th>Value</th></tr>";

$envVars = [
    'RAILWAY_ENVIRONMENT' => $_ENV['RAILWAY_ENVIRONMENT'] ?? null,
    'RAILWAY_PUBLIC_DOMAIN' => $_ENV['RAILWAY_PUBLIC_DOMAIN'] ?? null,
    'APP_URL' => $_ENV['APP_URL'] ?? null,
    'DATABASE_URL' => $_ENV['DATABASE_URL'] ?? null,
    'MAIL_HOST' => $_ENV['MAIL_HOST'] ?? null,
    'MAIL_USERNAME' => $_ENV['MAIL_USERNAME'] ?? null,
];

foreach ($envVars as $key => $value) {
    $status = $value ? "✅ Set" : "❌ Not Set";
    $displayValue = $value ? (strlen($value) > 50 ? substr($value, 0, 50) . "..." : $value) : "Not Set";
    echo "<tr><td>$key</td><td>$status</td><td>" . htmlspecialchars($displayValue) . "</td></tr>";
}

echo "</table>";
echo "</div>";

// Database Connection Test
echo "<div class='info'>";
echo "<h2>Database Connection Test</h2>";

try {
    // Include Railway database configuration
    include_once 'config/railway-db.php';
    
    $connection = getRailwayConnection();
    if ($connection) {
        echo "<div class='success'>✅ Database connection successful!</div>";
        echo "<p><strong>Database Type:</strong> " . (defined('DB_TYPE') ? DB_TYPE : 'Unknown') . "</p>";
        echo "<p><strong>Database Host:</strong> " . (defined('DB_HOST') ? DB_HOST : 'Unknown') . "</p>";
        echo "<p><strong>Database Name:</strong> " . (defined('DB_NAME') ? DB_NAME : 'Unknown') . "</p>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

// File Structure Check
echo "<div class='info'>";
echo "<h2>Configuration Files</h2>";
echo "<table>";
echo "<tr><th>File</th><th>Status</th></tr>";

$configFiles = [
    'railway.toml' => 'Railway configuration',
    'health-check.php' => 'Health check endpoint',
    'config/railway-db.php' => 'Railway database config',
    'config/domain-config.php' => 'Domain configuration',
    'config/email.php' => 'Email configuration',
];

foreach ($configFiles as $file => $description) {
    $exists = file_exists($file);
    $status = $exists ? "✅ Exists" : "❌ Missing";
    echo "<tr><td>$file</td><td>$status</td></tr>";
}

echo "</table>";
echo "</div>";

// Next Steps
echo "<div class='warning'>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>If running locally: Everything looks good for development</li>";
echo "<li>If on Railway without custom domain: Add domain in Railway dashboard</li>";
echo "<li>If on Railway with custom domain: Configure DNS records</li>";
echo "<li>Test all application features after domain setup</li>";
echo "</ol>";
echo "</div>";

// Help Links
echo "<div class='info'>";
echo "<h2>Helpful Links</h2>";
echo "<ul>";
echo "<li><a href='RAILWAY_DOMAIN_GUIDE.md' target='_blank'>📖 Railway Domain Setup Guide</a></li>";
echo "<li><a href='DEPLOYMENT_GUIDE.md' target='_blank'>🚀 General Deployment Guide</a></li>";
echo "<li><a href='health-check.php' target='_blank'>❤️ Health Check Endpoint</a></li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>