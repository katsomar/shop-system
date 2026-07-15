<?php
/**
 * Shared Header Layout Template
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in before rendering header
if (!is_logged_in()) {
    header("Location: /shop-system/authentication/login.php");
    exit();
}

$current_user_role = $_SESSION['user_role'] ?? 'Cashier';
$current_user_name = $_SESSION['user_name'] ?? 'User';
$unread_count = get_unread_notifications_count($conn);

// Get recent notifications for dropdown
$notif_sql = "SELECT id, title, message, type, created_at FROM notifications WHERE status = 'Unread' ORDER BY created_at DESC LIMIT 5";
$notif_result = mysqli_query($conn, $notif_sql);
$recent_notifications = [];
if ($notif_result) {
    while ($row = mysqli_fetch_assoc($notif_result)) {
        $recent_notifications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title><?php echo isset($page_title) ? $page_title . " - " . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Design System CSS -->
    <link rel="stylesheet" href="/shop-system/assets/css/style.css">
    
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="app-container">
        
        <!-- Sidebar Navigation Included -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>
        
        <!-- Sidebar Overlay for mobile screen widths -->
        <div class="sidebar-overlay"></div>
        
        <!-- Main Layout wrapper -->
        <div style="flex: 1; display: flex; flex-direction: column; min-width: 0;">
            
            <!-- Sticky Top Navbar -->
            <header class="top-navbar">
                <div class="d-flex align-center gap-3">
                    <!-- Hamburger / Sidebar toggles -->
                    <button class="btn btn-secondary cursor-pointer" id="sidebar-toggle" style="padding: 0.5rem; display: none; border: none; background: none;">
                        <i data-lucide="menu"></i>
                    </button>
                    <button class="btn btn-secondary cursor-pointer" id="mobile-sidebar-toggle" style="padding: 0.5rem; border: none; background: none;">
                        <i data-lucide="menu"></i>
                    </button>
                    
                    <!-- Page Breadcrumb Title -->
                    <h2 class="text-lg font-semibold mb-4" style="margin-bottom:0; font-size:1.15rem;">
                        <?php echo $page_title ?? 'Dashboard'; ?>
                    </h2>
                </div>
                
                <!-- Navbar Actions -->
                <div class="d-flex align-center gap-4">
                    
                    <!-- Theme Selector Button -->
                    <div class="theme-switch">
                        <div class="theme-switch-btn" id="theme-btn-light" title="Light Theme">
                            <i data-lucide="sun" style="width:16px; height:16px;"></i>
                        </div>
                        <div class="theme-switch-btn" id="theme-btn-dark" title="Dark Theme">
                            <i data-lucide="moon" style="width:16px; height:16px;"></i>
                        </div>
                    </div>
                    
                    <!-- Notifications Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle cursor-pointer" style="border:none; padding:0.5rem; position:relative; background:none;">
                            <i data-lucide="bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span style="position:absolute; top:-2px; right:-2px; background-color:var(--danger); color:white; font-size:0.65rem; padding: 2px 6px; border-radius:50px; font-weight:700;">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu" style="min-width: 320px; right: -50px; padding: 0.5rem 0;">
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                                <span class="font-semibold text-sm">Notifications</span>
                                <span class="text-muted text-xs"><?php echo $unread_count; ?> Unread</span>
                            </div>
                            <div style="max-height: 250px; overflow-y: auto;">
                                <?php if (empty($recent_notifications)): ?>
                                    <div class="text-center text-muted text-sm" style="padding: 1.5rem;">
                                        No unread notifications
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_notifications as $notif): ?>
                                        <div class="dropdown-item flex-column align-start" style="border-bottom: 1px solid var(--border-color); padding: 0.75rem 1rem; gap: 0.25rem;">
                                            <div class="d-flex w-full justify-between align-center">
                                                <span class="font-semibold text-xs text-primary" style="display:flex; align-items:center; gap:4px;">
                                                    <i data-lucide="alert-circle" style="width:12px; height:12px;"></i>
                                                    <?php echo e($notif['type']); ?>
                                                </span>
                                                <span class="text-muted" style="font-size: 0.65rem;"><?php echo date('H:i', strtotime($notif['created_at'])); ?></span>
                                            </div>
                                            <p class="font-medium text-xs" style="margin: 0; color:var(--text-main);"><?php echo e($notif['title']); ?></p>
                                            <p class="text-muted text-xs" style="margin: 0; font-size:0.7rem; white-space: normal;"><?php echo e($notif['message']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-divider" style="margin:0;"></div>
                            <a href="/shop-system/reports/notifications.php" class="dropdown-item text-center font-semibold text-sm text-primary justify-center" style="padding: 0.75rem 1rem;">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                    
                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <div class="dropdown-toggle cursor-pointer d-flex align-center gap-2">
                            <div class="sidebar-user-avatar" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                            </div>
                            <span class="font-semibold text-sm" style="display:none; @media (min-width:768px){display:inline;}">
                                <?php echo e($current_user_name); ?>
                            </span>
                            <i data-lucide="chevron-down" style="width:16px; height:16px; color:var(--text-muted);"></i>
                        </div>
                        <div class="dropdown-menu">
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color);">
                                <span class="font-semibold text-sm" style="display:block;"><?php echo e($current_user_name); ?></span>
                                <span class="text-muted text-xs" style="display:block;"><?php echo e($current_user_role); ?></span>
                            </div>
                            <a href="/shop-system/users/profile.php" class="dropdown-item">
                                <i data-lucide="user" style="width:16px; height:16px;"></i> My Profile
                            </a>
                            <a href="/shop-system/settings/index.php" class="dropdown-item">
                                <i data-lucide="settings" style="width:16px; height:16px;"></i> Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="/shop-system/authentication/logout.php" class="dropdown-item" style="color: var(--danger);">
                                <i data-lucide="log-out" style="width:16px; height:16px; color:var(--danger);"></i> Log Out
                            </a>
                        </div>
                    </div>
                    
                </div>
            </header>
            
            <!-- Main Content Container starts here -->
            <main class="main-content">
                <div class="fade-in">
                    
                    <!-- Flash messages handler (Toasts) -->
                    <?php 
                    $flash_messages = get_flash_messages();
                    if (!empty($flash_messages)): 
                        foreach ($flash_messages as $flash): 
                    ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                showToast("<?php echo addslashes($flash['message']); ?>", "<?php echo $flash['type']; ?>");
                            });
                        </script>
                    <?php 
                        endforeach; 
                    endif; 
                    ?>
                    
                    <!-- Screen Loader -->
                    <div id="screen-loader" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.15); backdrop-filter: blur(1px); z-index: 2000; align-items: center; justify-content: center;">
                        <div class="card d-flex align-center justify-center" style="padding: 1.5rem; border-radius: var(--border-radius-md);">
                            <div class="spinner"></div>
                            <span class="ml-2 font-medium text-sm">Processing...</span>
                        </div>
                    </div>
