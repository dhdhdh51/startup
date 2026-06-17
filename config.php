<?php
/**
 * Bharat SEO CRM - Configuration File
 * 
 * Database settings, site configuration, and session setup.
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'bharat_seo_crm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Site Settings
define('SITE_NAME', 'Bharat SEO');
define('SITE_TAGLINE', 'Website + Google Map SEO + WhatsApp Leads for Local Businesses');
define('BASE_URL', '/');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);
session_start();

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Pagination
define('ITEMS_PER_PAGE', 25);
