<?php
/**
 * Bharat SEO Lead Finder & Auto Audit CRM Tool
 * Configuration File
 * 
 * INSTALLATION:
 * 1. Create MySQL database 'bharat_seo_crm'
 * 2. Import database.sql
 * 3. Update database credentials below
 * 4. Default login: admin / admin123
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'bharat_seo_crm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Bharat SEO');
define('APP_TAGLINE', 'Website + Google Map SEO + WhatsApp Leads for Local Businesses');
define('APP_VERSION', '1.0.0');
define('APP_URL', ''); // Set your domain e.g., https://crm.bharatseo.com

// Path Configuration
define('BASE_PATH', __DIR__);
define('ADMIN_PATH', BASE_PATH . '/admin');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('CRON_PATH', BASE_PATH . '/cron');

// Session Configuration
define('SESSION_NAME', 'bharat_seo_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// CSRF Token
define('CSRF_TOKEN_NAME', 'csrf_token');

// cURL defaults
define('CURL_TIMEOUT_DEFAULT', 30);
define('CURL_CONNECT_TIMEOUT', 10);

// Audit defaults
define('AUDIT_BATCH_SIZE', 10);
define('AUDIT_DELAY_SECONDS', 3);

// Database Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
        }
    }
    return $pdo;
}

// Start session
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// Get setting from database
function getSetting($key, $default = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Update setting
function updateSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

// Log activity
function logActivity($action, $details = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (action, details) VALUES (?, ?)");
        $stmt->execute([$action, $details]);
    } catch (Exception $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
}
