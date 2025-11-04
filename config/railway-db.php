<?php
/**
 * Railway Database Configuration
 * Automatically detects Railway database URL and configures connection
 */

// Check if running on Railway
if (isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['DATABASE_URL'])) {
    // Railway PostgreSQL configuration
    if (isset($_ENV['DATABASE_URL'])) {
        $databaseUrl = $_ENV['DATABASE_URL'];
        $parsedUrl = parse_url($databaseUrl);
        
        define('DB_HOST', $parsedUrl['host']);
        define('DB_USER', $parsedUrl['user']);
        define('DB_PASS', $parsedUrl['pass']);
        define('DB_NAME', ltrim($parsedUrl['path'], '/'));
        define('DB_PORT', $parsedUrl['port']);
        define('DB_TYPE', 'pgsql'); // PostgreSQL for Railway
    } else {
        // Fallback to MySQL on Railway (if using MySQL addon)
        define('DB_HOST', $_ENV['MYSQLHOST'] ?? 'localhost');
        define('DB_USER', $_ENV['MYSQLUSER'] ?? 'root');
        define('DB_PASS', $_ENV['MYSQLPASSWORD'] ?? '');
        define('DB_NAME', $_ENV['MYSQLDATABASE'] ?? 'tplearn');
        define('DB_PORT', $_ENV['MYSQLPORT'] ?? 3306);
        define('DB_TYPE', 'mysql');
    }
    
    // Railway environment settings
    define('ENVIRONMENT', 'production');
    define('DEBUG_MODE', false);
    define('BASE_URL', $_ENV['APP_URL'] ?? 'https://your-app-url.railway.app');
    
} else {
    // Local development configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'tplearn');
    define('DB_PORT', 3306);
    define('DB_TYPE', 'mysql');
    define('ENVIRONMENT', 'development');
    define('DEBUG_MODE', true);
    define('BASE_URL', 'http://localhost/TPLearn');
}

// Common settings
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection based on environment
 */
function getRailwayConnection() {
    try {
        if (DB_TYPE === 'pgsql') {
            // PostgreSQL connection for Railway
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            // MySQL connection (local or Railway MySQL)
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ]);
        }
        
        return $pdo;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Database connection failed: " . $e->getMessage());
        } else {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
}

// Export environment info for debugging
function getDatabaseInfo() {
    return [
        'environment' => ENVIRONMENT,
        'db_type' => DB_TYPE,
        'db_host' => DB_HOST,
        'db_name' => DB_NAME,
        'base_url' => BASE_URL,
        'railway_env' => $_ENV['RAILWAY_ENVIRONMENT'] ?? 'not-set'
    ];
}
?>