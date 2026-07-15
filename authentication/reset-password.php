<?php
/**
 * Reset Password Interface View Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    header("Location: /shop-system/dashboard/index.php");
    exit();
}

$token = clean_input($_GET['token'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    
    <!-- Design System CSS -->
    <link rel="stylesheet" href="/shop-system/assets/css/style.css">
    
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
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
        
        <div class="login-logo">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linecap="round">
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

        <h2 class="login-title">Choose New Password</h2>
        <p class="login-subtitle">Enter your new strong password below</p>

        <!-- Status Container -->
        <div id="reset-alert" style="display: none; border-radius: var(--border-radius-sm); padding: 0.75rem 1rem; font-size: 0.8rem; margin-bottom: 1.5rem; align-items: center; gap: 8px;">
            <i id="reset-alert-icon" data-lucide="alert-circle" style="width: 16px; height: 16px; min-width: 16px;"></i>
            <span id="reset-alert-message"></span>
        </div>

        <!-- Reset Form -->
        <form id="reset-form" autocomplete="off">
            <input type="hidden" name="token" value="<?php echo e($token); ?>">
            
            <div class="form-group">
                <label class="form-label login-label" for="password">New Password</label>
                <input class="form-control login-input" type="password" id="password" name="password" placeholder="Min. 8 characters" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label login-label" for="confirm_password">Confirm New Password</label>
                <input class="form-control login-input" type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required>
            </div>

            <button type="submit" class="btn btn-primary w-full d-flex align-center justify-center" id="reset-submit-btn" style="background-color: #2563eb; color: white; margin-bottom: 1.5rem;">
                <span>Reset Password</span>
                <div class="spinner" id="reset-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 16px; height: 16px; border-width: 2px;"></div>
            </button>
            
            <div class="text-center">
                <a class="login-footer-link" href="/shop-system/authentication/login.php">Back to Login</a>
            </div>
        </form>

    </div>

    <!-- Scripting for Reset Password -->
    <script src="/shop-system/assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const resetForm = document.getElementById('reset-form');
            const submitBtn = document.getElementById('reset-submit-btn');
            const spinner = document.getElementById('reset-spinner');
            const alertBox = document.getElementById('reset-alert');
            const alertMessage = document.getElementById('reset-alert-message');
            const alertIcon = document.getElementById('reset-alert-icon');

            if (resetForm) {
                resetForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    alertBox.style.display = 'none';
                    
                    // Match validation
                    const password = document.getElementById('password').value;
                    const confirm = document.getElementById('confirm_password').value;
                    
                    if (password.length < 8) {
                        alertBox.style.display = 'flex';
                        alertBox.style.backgroundColor = 'rgba(239, 68, 68, 0.15)';
                        alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                        alertBox.style.color = '#fca5a5';
                        alertMessage.innerText = 'Password must be at least 8 characters long.';
                        alertIcon.setAttribute('data-lucide', 'alert-triangle');
                        lucide.createIcons();
                        return;
                    }

                    if (password !== confirm) {
                        alertBox.style.display = 'flex';
                        alertBox.style.backgroundColor = 'rgba(239, 68, 68, 0.15)';
                        alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                        alertBox.style.color = '#fca5a5';
                        alertMessage.innerText = 'Passwords do not match.';
                        alertIcon.setAttribute('data-lucide', 'alert-triangle');
                        lucide.createIcons();
                        return;
                    }

                    submitBtn.disabled = true;
                    spinner.style.display = 'block';

                    const formData = new FormData(resetForm);
                    
                    try {
                        const res = await ajaxRequest('/shop-system/ajax/auth_handler.php?action=reset', {
                            method: 'POST',
                            body: formData
                        });

                        alertBox.style.display = 'flex';
                        if (res && res.success) {
                            alertBox.style.backgroundColor = 'rgba(22, 163, 74, 0.15)';
                            alertBox.style.border = '1px solid rgba(22, 163, 74, 0.3)';
                            alertBox.style.color = '#86efac';
                            alertMessage.innerText = res.message;
                            alertIcon.setAttribute('data-lucide', 'check-circle');
                            
                            // Redirect to login page after 2 seconds
                            setTimeout(() => {
                                window.location.href = '/shop-system/authentication/login.php';
                            }, 2000);
                        } else {
                            alertBox.style.backgroundColor = 'rgba(239, 68, 68, 0.15)';
                            alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                            alertBox.style.color = '#fca5a5';
                            alertMessage.innerText = res.message || 'Error occurred.';
                            alertIcon.setAttribute('data-lucide', 'alert-triangle');
                            submitBtn.disabled = false;
                            spinner.style.display = 'none';
                        }
                        
                        lucide.createIcons();
                    } catch (err) {
                        console.error(err);
                        alertBox.style.display = 'flex';
                        alertBox.style.backgroundColor = 'rgba(239, 68, 68, 0.15)';
                        alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                        alertBox.style.color = '#fca5a5';
                        alertMessage.innerText = 'Network error occurred. Please try again.';
                        alertIcon.setAttribute('data-lucide', 'alert-triangle');
                        lucide.createIcons();
                        submitBtn.disabled = false;
                        spinner.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>
