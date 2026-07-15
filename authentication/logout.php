<?php
/**
 * Logout Page Handler
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Log user logout action before destroying session
if (is_logged_in()) {
    log_activity($conn, 'Logout', 'User manually logged out.');
    
    // Clean remember me tokens if selector exists
    if (isset($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) === 2) {
            list($selector, $validator) = $parts;
            
            $sql = "DELETE FROM remember_tokens WHERE selector = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $selector);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Clear the cookie
setcookie('remember_me', '', time() - 3600, '/', '', false, true);

// Unset all of the session variables
$_SESSION = [];

// Destroy the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Start fresh session to set flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash_message('success', 'You have been logged out successfully.');

// Redirect to login page
header("Location: /shop-system/authentication/login.php");
exit();
