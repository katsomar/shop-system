<?php
/**
 * Core Application Functions
 */

require_once __DIR__ . '/../config/config.php';

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

/**
 * Escape output for HTML safety (XSS prevention)
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize and clean inputs
 */
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data ?? '');
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log activity in the database
 */
function log_activity($conn, $action, $details = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $sql = "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'issss', $user_id, $action, $details, $ip_address, $user_agent);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get setting value from database
 */
function get_setting($conn, $key, $default = '') {
    $sql = "SELECT value FROM settings WHERE `key` = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $key);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $value);
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            return $value;
        }
        mysqli_stmt_close($stmt);
    }
    return $default;
}

/**
 * Update/Insert setting in database
 */
function update_setting($conn, $key, $value) {
    $sql = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sss', $key, $value, $value);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Get logged-in user data
 */
function current_user($conn) {
    if (!is_logged_in()) {
        return null;
    }
    
    $sql = "SELECT id, username, email, role, full_name, phone, status FROM users WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $user;
    }
    return null;
}

/**
 * Check if the user has a specific role or set of roles
 * @param array|string $allowed_roles
 */
function has_role($allowed_roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'] ?? '';
    
    if (is_array($allowed_roles)) {
        return in_array($user_role, $allowed_roles);
    }
    
    return $user_role === $allowed_roles;
}

/**
 * Enforce role access control, redirecting unauthorized users
 */
function check_role($allowed_roles, $redirect_url = '/shop-system/dashboard/index.php') {
    if (!is_logged_in()) {
        header("Location: /shop-system/authentication/login.php");
        exit();
    }
    
    if (!has_role($allowed_roles)) {
        set_flash_message('danger', 'Unauthorized Access: You do not have permission to view that page.');
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Add an application alert/notification
 */
function add_notification($conn, $title, $message, $type = 'System') {
    $sql = "INSERT INTO notifications (title, message, type, status) VALUES (?, ?, ?, 'Unread')";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sss', $title, $message, $type);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get the count of unread notifications
 */
function get_unread_notifications_count($conn) {
    $sql = "SELECT COUNT(*) FROM notifications WHERE status = 'Unread'";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_row($result);
        return (int)$row[0];
    }
    return 0;
}

/**
 * Set flash message in session for visual alerts (toast/alerts)
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash messages from session
 */
function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['csrf_token']); // Refresh CSRF check per request where messages might load
    $_SESSION['flash_messages'] = [];
    return $messages;
}

/**
 * Generate a random token
 */
function generate_token() {
    return bin2hex(random_bytes(32));
}
