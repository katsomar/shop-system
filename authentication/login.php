<?php
/**
 * Login Interface View Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: /shop-system/dashboard/index.php");
    exit();
}

$redirect_to = clean_input($_GET['redirect'] ?? '/shop-system/dashboard/index.php');
$timeout_msg = $_SESSION['timeout_message'] ?? null;
unset($_SESSION['timeout_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
    <!-- Design System CSS -->
    <link rel="stylesheet" href="/shop-system/assets/css/style.css">
    
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* Specific Styles for Login Screen */
        .login-page {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 10% 20%, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 1) 90%);
            padding: 1.5rem;
        }
        
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(30, 41, 59, 0.45);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--border-radius-lg);
            padding: 2.5rem 2.25rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
            animation: modalScaleUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            color: #f8fafc;
        }

        html.dark .login-card {
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .login-logo svg {
            color: #3b82f6;
            filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.4));
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
            color: #ffffff;
        }

        .login-subtitle {
            font-size: 0.85rem;
            color: #94a3b8;
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-input {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .login-input:focus {
            background-color: rgba(15, 23, 42, 0.8);
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }

        .login-label {
            color: #cbd5e1;
        }

        .login-footer-link {
            color: #94a3b8;
            font-size: 0.85rem;
            transition: color 0.15s;
        }

        .login-footer-link:hover {
            color: #3b82f6;
        }
    </style>
</head>
<body class="login-page">

    <div class="login-card">
        
        <!-- Brand Logo -->
        <div class="login-logo">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect width="16" height="16" x="4" y="4" rx="2"/>
                <rect width="6" height="6" x="9" y="9" rx="1"/>
                <path d="M9 1v3"/>
                <path d="M15 1v3"/>
                <path d="M9 20v3"/>
                <path d="M15 20v3"/>
                <path d="M20 9h3"/>
                <path d="M20 15h3"/>
                <path d="M1 9h3"/>
                <path d="M1 15h3"/>
            </svg>
            <h1 class="brand-name" style="color: #ffffff; font-size: 1.5rem; margin-bottom: 0;">NEXUS RETAIL</h1>
        </div>

        <h2 class="login-title">Welcome back</h2>
        <p class="login-subtitle">Enter your details to access your account</p>

        <!-- Timeout alert message -->
        <?php if ($timeout_msg): ?>
            <div style="background-color: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--border-radius-sm); padding: 0.75rem 1rem; color: #fca5a5; font-size: 0.8rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="alert-triangle" style="width: 16px; height: 16px; min-width: 16px;"></i>
                <span><?php echo e($timeout_msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Error Container for AJAX Login failure -->
        <div id="login-error-alert" style="display: none; background-color: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--border-radius-sm); padding: 0.75rem 1rem; color: #fca5a5; font-size: 0.8rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="alert-triangle" style="width: 16px; height: 16px; min-width: 16px;"></i>
            <span id="login-error-message"></span>
        </div>

        <!-- Login Form -->
        <form id="login-form" autocomplete="off">
            <input type="hidden" name="redirect" value="<?php echo e($redirect_to); ?>">
            
            <div class="form-group">
                <label class="form-label login-label" for="username">Username or Email</label>
                <input class="form-control login-input" type="text" id="username" name="username" placeholder="e.g. admin" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <div class="d-flex justify-between align-center" style="margin-bottom: 0.375rem;">
                    <label class="form-label login-label" for="password" style="margin-bottom: 0;">Password</label>
                    <a class="login-footer-link" href="/shop-system/authentication/forgot-password.php">Forgot password?</a>
                </div>
                <div style="position: relative;">
                    <input class="form-control login-input" type="password" id="password" name="password" placeholder="••••••••" required style="padding-right: 2.5rem;">
                    <button type="button" id="toggle-password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;">
                        <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
                    </button>
                </div>
            </div>

            <div class="form-group d-flex align-center" style="margin-bottom: 2rem;">
                <input type="checkbox" id="remember" name="remember" style="width: 16px; height: 16px; margin-right: 8px; cursor: pointer; accent-color: #3b82f6;">
                <label for="remember" class="login-label" style="font-size: 0.85rem; user-select: none; cursor: pointer;">Remember me for 30 days</label>
            </div>

            <button type="submit" class="btn btn-primary w-full d-flex align-center justify-center" id="login-submit-btn" style="background-color: #2563eb; color: white;">
                <span>Sign In</span>
                <div class="spinner" id="login-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 16px; height: 16px; border-width: 2px;"></div>
            </button>
        </form>

    </div>

    <!-- Scripting for Login Page -->
    <script src="/shop-system/assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Password Visibility Toggler
            const togglePasswordBtn = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('password');
            
            if (togglePasswordBtn && passwordInput) {
                togglePasswordBtn.addEventListener('click', () => {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    const eyeIcon = togglePasswordBtn.querySelector('i');
                    if (type === 'text') {
                        togglePasswordBtn.innerHTML = '<i data-lucide="eye-off" style="width: 18px; height: 18px;"></i>';
                    } else {
                        togglePasswordBtn.innerHTML = '<i data-lucide="eye" style="width: 18px; height: 18px;"></i>';
                    }
                    lucide.createIcons();
                });
            }

            // AJAX login submission
            const loginForm = document.getElementById('login-form');
            const submitBtn = document.getElementById('login-submit-btn');
            const spinner = document.getElementById('login-spinner');
            const errorAlert = document.getElementById('login-error-alert');
            const errorMessage = document.getElementById('login-error-message');

            if (loginForm) {
                loginForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    // Hide any previous errors
                    errorAlert.style.display = 'none';
                    
                    // Show spinner and disable button
                    submitBtn.disabled = true;
                    spinner.style.display = 'block';

                    const formData = new FormData(loginForm);
                    
                    try {
                        const res = await ajaxRequest('/shop-system/ajax/auth_handler.php?action=login', {
                            method: 'POST',
                            body: formData
                        });

                        if (res && res.success) {
                            showToast(res.message, 'success');
                            setTimeout(() => {
                                window.location.href = res.redirect || '/shop-system/dashboard/index.php';
                            }, 800);
                        } else {
                            errorMessage.innerText = res.message || 'Login failed. Please try again.';
                            errorAlert.style.display = 'flex';
                            submitBtn.disabled = false;
                            spinner.style.display = 'none';
                        }
                    } catch (err) {
                        console.error(err);
                        errorMessage.innerText = 'Network error occurred. Please try again.';
                        errorAlert.style.display = 'flex';
                        submitBtn.disabled = false;
                        spinner.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>
