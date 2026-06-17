<?php
/**
 * Bharat SEO CRM - Admin Logout
 * 
 * Destroy session and redirect to login page.
 */

require_once __DIR__ . '/../functions.php';

// Log the activity before destroying the session
if (isset($_SESSION['admin_username'])) {
    logActivity('logout', 'User logged out: ' . $_SESSION['admin_username']);
}

// Destroy session
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

redirect('login.php');
