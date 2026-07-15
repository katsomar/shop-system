<?php
/**
 * AJAX Authentication Backend Handler
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = clean_input($_GET['action'] ?? '');

// 1. Process Actions
switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }

        // CSRF Verification
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed. Please refresh.']);
            exit();
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);
        $redirect = trim($_POST['redirect'] ?? '/shop-system/dashboard/index.php');

        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
            exit();
        }

        // Query user info and lockout status
        $sql = "SELECT id, username, email, password, role, full_name, status, failed_attempts, lock_until FROM users WHERE username = ? OR email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'System query execution error.']);
            exit();
        }

        mysqli_stmt_bind_param($stmt, 'ss', $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Invalid username/email or password.']);
            exit();
        }

        $now = date('Y-m-d H:i:s');

        // Check account lock
        if ($user['lock_until'] && $user['lock_until'] > $now) {
            $lock_time_left = strtotime($user['lock_until']) - time();
            $minutes_left = ceil($lock_time_left / 60);
            echo json_encode([
                'success' => false, 
                'message' => "Your account is temporarily locked due to too many failed attempts. Try again in {$minutes_left} minute(s)."
            ]);
            exit();
        }

        // Check account active status
        if ($user['status'] !== 'Active') {
            echo json_encode(['success' => false, 'message' => 'Your account is deactivated or suspended.']);
            exit();
        }

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Success! Reset lock parameters
            $reset_sql = "UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = ?";
            $reset_stmt = mysqli_prepare($conn, $reset_sql);
            if ($reset_stmt) {
                mysqli_stmt_bind_param($reset_stmt, 'i', $user['id']);
                mysqli_stmt_execute($reset_stmt);
                mysqli_stmt_close($reset_stmt);
            }

            // Regenerate session for session hijacking defense
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['LAST_ACTIVITY'] = time();

            // Handle "Remember Me"
            if ($remember) {
                $selector = bin2hex(random_bytes(12));
                $validator = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $validator);
                $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 Days

                // Save to remember_tokens table
                $rem_sql = "INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)";
                $rem_stmt = mysqli_prepare($conn, $rem_sql);
                if ($rem_stmt) {
                    mysqli_stmt_bind_param($rem_stmt, 'isss', $user['id'], $selector, $token_hash, $expires_at);
                    mysqli_stmt_execute($rem_stmt);
                    mysqli_stmt_close($rem_stmt);

                    // Set cookie: selector:validator, HTTPOnly, Secure if possible, expires in 30 days
                    $cookie_value = $selector . ':' . $validator;
                    setcookie('remember_me', $cookie_value, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                }
            }

            // Log activity
            log_activity($conn, 'Login', "User successfully authenticated from IP: " . $_SERVER['REMOTE_ADDR']);

            echo json_encode([
                'success' => true,
                'message' => "Welcome back, {$user['full_name']}!",
                'redirect' => $redirect
            ]);
            exit();
        } else {
            // Failed password. Increment failed attempts
            $attempts = $user['failed_attempts'] + 1;
            $lock_until = null;

            if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                $lock_until = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
                log_activity($conn, 'Account Lockout', "User {$user['username']} locked due to excessive failed logins.");
            }

            $update_sql = "UPDATE users SET failed_attempts = ?, lock_until = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, 'isi', $attempts, $lock_until, $user['id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }

            if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Too many failed attempts. Your account has been locked for 15 minutes.'
                ]);
            } else {
                $remaining = LOGIN_MAX_ATTEMPTS - $attempts;
                echo json_encode([
                    'success' => false,
                    'message' => "Invalid password. {$remaining} attempt(s) remaining before lockout."
                ]);
            }
            exit();
        }
        break;

    case 'forgot':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }

        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed. Please refresh.']);
            exit();
        }

        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
            exit();
        }

        // Verify if user exists
        $sql = "SELECT id, username FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            // Security Best Practice: Don't explicitly reveal if email is not found, but since this is local simulation, we can be slightly more helpful
            echo json_encode(['success' => false, 'message' => 'No account associated with that email address.']);
            exit();
        }

        // Generate Token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + (60 * 15)); // 15 mins validity

        // Save token to DB
        $ins_sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
        $ins_stmt = mysqli_prepare($conn, $ins_sql);
        if ($ins_stmt) {
            mysqli_stmt_bind_param($ins_stmt, 'sss', $email, $token, $expires_at);
            mysqli_stmt_execute($ins_stmt);
            mysqli_stmt_close($ins_stmt);
        }

        // Simulated Response for local ease of deployment
        $reset_link = "/shop-system/authentication/reset-password.php?token=" . urlencode($token);
        
        echo json_encode([
            'success' => true,
            'message' => "Reset link generated successfully!<br><br><a href='{$reset_link}' style='display:inline-block; padding: 6px 12px; background-color:#16a34a; color:white; border-radius:4px; font-weight:600; text-decoration:none; margin-top:5px;'>Reset Password Now</a>"
        ]);
        exit();
        break;

    case 'reset':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }

        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed. Please refresh.']);
            exit();
        }

        $token = trim($_POST['token'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($token) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameter.']);
            exit();
        }

        $now = date('Y-m-d H:i:s');

        // Check if token exists and is valid
        $sql = "SELECT email FROM password_resets WHERE token = ? AND expires_at > ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $token, $now);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reset_entry = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$reset_entry) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired password reset token.']);
            exit();
        }

        $email = $reset_entry['email'];
        $new_hash = password_hash($password, PASSWORD_DEFAULT);

        // Update password in users table
        $up_sql = "UPDATE users SET password = ?, failed_attempts = 0, lock_until = NULL WHERE email = ?";
        $up_stmt = mysqli_prepare($conn, $up_sql);
        if ($up_stmt) {
            mysqli_stmt_bind_param($up_stmt, 'ss', $new_hash, $email);
            $success = mysqli_stmt_execute($up_stmt);
            mysqli_stmt_close($up_stmt);
        }

        if (isset($success) && $success) {
            // Delete token
            $del_sql = "DELETE FROM password_resets WHERE email = ?";
            $del_stmt = mysqli_prepare($conn, $del_sql);
            if ($del_stmt) {
                mysqli_stmt_bind_param($del_stmt, 's', $email);
                mysqli_stmt_execute($del_stmt);
                mysqli_stmt_close($del_stmt);
            }

            // Log activity
            log_activity($conn, 'Password Reset', "User with email {$email} successfully reset their password.");

            echo json_encode([
                'success' => true,
                'message' => 'Your password has been reset successfully! Redirecting...'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while resetting password.']);
        }
        exit();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
        exit();
}
