<?php
/**
 * Authentication Check Helper
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// 1. Try to restore session via Remember Me cookie if session is not active
if (!is_logged_in() && isset($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me']);
    
    if (count($parts) === 2) {
        list($selector, $validator) = $parts;
        
        $sql = "SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at, u.username, u.role, u.full_name, u.status 
                FROM remember_tokens rt 
                JOIN users u ON rt.user_id = u.id 
                WHERE rt.selector = ? LIMIT 1";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $selector);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $token_data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if ($token_data) {
                $now = date('Y-m-d H:i:s');
                if ($token_data['expires_at'] > $now && $token_data['status'] === 'Active') {
                    // Verify validator
                    if (hash_equals($token_data['token_hash'], hash('sha256', $validator))) {
                        // Success! Re-establish session
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $token_data['user_id'];
                        $_SESSION['username'] = $token_data['username'];
                        $_SESSION['user_role'] = $token_data['role'];
                        $_SESSION['user_name'] = $token_data['full_name'];
                        $_SESSION['LAST_ACTIVITY'] = time();
                        
                        log_activity($conn, 'Remember Me Login', 'Restored session via remember-me token.');
                    }
                } else {
                    // Expired or user suspended, clean up token
                    $del_sql = "DELETE FROM remember_tokens WHERE selector = ?";
                    $del_stmt = mysqli_prepare($conn, $del_sql);
                    if ($del_stmt) {
                        mysqli_stmt_bind_param($del_stmt, 's', $selector);
                        mysqli_stmt_execute($del_stmt);
                        mysqli_stmt_close($del_stmt);
                    }
                    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
                }
            }
        }
    }
}

// 2. Redirect to Login if still not logged in
if (!is_logged_in()) {
    $redirect = $_SERVER['REQUEST_URI'];
    header("Location: /shop-system/authentication/login.php?redirect=" . urlencode($redirect));
    exit();
}

// 3. Double-check if logged-in user is still active in the database
$user_check_sql = "SELECT status FROM users WHERE id = ? LIMIT 1";
$uc_stmt = mysqli_prepare($conn, $user_check_sql);
if ($uc_stmt) {
    mysqli_stmt_bind_param($uc_stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($uc_stmt);
    mysqli_stmt_bind_result($uc_stmt, $status);
    if (mysqli_stmt_fetch($uc_stmt)) {
        if ($status !== 'Active') {
            mysqli_stmt_close($uc_stmt);
            // Log user out
            session_unset();
            session_destroy();
            setcookie('remember_me', '', time() - 3600, '/', '', false, true);
            
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            set_flash_message('danger', 'Your account has been deactivated or suspended.');
            header("Location: /shop-system/authentication/login.php");
            exit();
        }
    } else {
        mysqli_stmt_close($uc_stmt);
        // User not found in DB
        session_unset();
        session_destroy();
        header("Location: /shop-system/authentication/login.php");
        exit();
    }
    mysqli_stmt_close($uc_stmt);
}
