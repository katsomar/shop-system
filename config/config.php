<?php
/**
 * Application Configuration File
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// Set Timezone
date_default_timezone_set('Europe/Athens'); // Matching the local time zone offset or general default

// Database Config
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shop_system');

// Site Config
define('SITE_NAME', 'Nexus Shop');
define('BASE_URL', '/shop-system/'); // Adjust based on your XAMPP folder structure

// Security Config
define('SESSION_LIFETIME', 3600); // 1 hour session duration
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15 * 60); // 15 minutes lockout

// Session Settings - Security Hardening
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Use secure cookies if HTTPS is enabled
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    ini_set('session.cookie_secure', $isSecure ? 1 : 0);
    
    // SameSite session cookie attribute
    ini_set('session.cookie_samesite', 'Lax');
    
    session_start();
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_LIFETIME)) {
    session_unset();
    session_destroy();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['timeout_message'] = 'Your session has expired due to inactivity.';
}
$_SESSION['LAST_ACTIVITY'] = time();
