<?php
/**
 * Shared Sidebar Layout Template
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$role = $_SESSION['user_role'] ?? 'Cashier';
$username = $_SESSION['username'] ?? 'User';
$fullname = $_SESSION['user_name'] ?? 'User';

// Helper function to check if the current page filename matches a URL path (to mark it active)
function is_page_active($path) {
    $current_uri = $_SERVER['REQUEST_URI'];
    return (strpos($current_uri, $path) !== false) ? 'active' : '';
}
?>
<aside class="sidebar">
    
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <!-- SVG Logo -->
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cpu" style="color: var(--primary);">
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
        <span class="brand-name">Nexus Admin</span>
    </div>
    
    <!-- Sidebar Navigation Links Wrapper -->
    <div class="sidebar-menu-wrapper">
        
        <!-- CORE SECTION -->
        <?php if (in_array($role, ['Administrator', 'Manager', 'Accountant'])): ?>
        <ul class="sidebar-menu">
            <li class="sidebar-section-title">Core</li>
            <li class="sidebar-item <?php echo is_page_active('/dashboard/index.php'); ?>">
                <a href="/shop-system/dashboard/index.php" class="sidebar-link">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- SALES SECTION -->
        <?php if (in_array($role, ['Administrator', 'Manager', 'Cashier', 'Accountant'])): ?>
        <ul class="sidebar-menu">
            <li class="sidebar-section-title">Sales & Customers</li>
            
            <?php if (in_array($role, ['Administrator', 'Manager', 'Cashier'])): ?>
            <li class="sidebar-item <?php echo is_page_active('/sales/pos.php'); ?>">
                <a href="/shop-system/sales/pos.php" class="sidebar-link">
                    <i data-lucide="shopping-cart"></i>
                    <span>POS Terminal</span>
                </a>
            </li>
            <?php endif; ?>
            
            <li class="sidebar-item <?php echo is_page_active('/sales/returns.php'); ?>">
                <a href="/shop-system/sales/returns.php" class="sidebar-link">
                    <i data-lucide="undo-2"></i>
                    <span>Return Sales</span>
                </a>
            </li>
            
            <li class="sidebar-item <?php echo is_page_active('/customers/index.php'); ?>">
                <a href="/shop-system/customers/index.php" class="sidebar-link">
                    <i data-lucide="users"></i>
                    <span>Customers</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- PRODUCTS & STOCK SECTION -->
        <?php if (in_array($role, ['Administrator', 'Manager', 'Store Keeper'])): ?>
        <ul class="sidebar-menu">
            <li class="sidebar-section-title">Stock Management</li>
            <li class="sidebar-item <?php echo is_page_active('/products/index.php'); ?>">
                <a href="/shop-system/products/index.php" class="sidebar-link">
                    <i data-lucide="package"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo is_page_active('/categories/index.php'); ?>">
                <a href="/shop-system/categories/index.php" class="sidebar-link">
                    <i data-lucide="folder-tree"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo is_page_active('/brands/index.php'); ?>">
                <a href="/shop-system/brands/index.php" class="sidebar-link">
                    <i data-lucide="tag"></i>
                    <span>Brands</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo is_page_active('/inventory/index.php'); ?>">
                <a href="/shop-system/inventory/index.php" class="sidebar-link">
                    <i data-lucide="boxes"></i>
                    <span>Stock Control</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- SUPPLY SECTION -->
        <?php if (in_array($role, ['Administrator', 'Manager', 'Store Keeper', 'Accountant'])): ?>
        <ul class="sidebar-menu">
            <li class="sidebar-section-title">Procurement</li>
            <li class="sidebar-item <?php echo is_page_active('/suppliers/index.php'); ?>">
                <a href="/shop-system/suppliers/index.php" class="sidebar-link">
                    <i data-lucide="truck"></i>
                    <span>Suppliers</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo is_page_active('/purchases/index.php'); ?>">
                <a href="/shop-system/purchases/index.php" class="sidebar-link">
                    <i data-lucide="receipt"></i>
                    <span>Purchase Orders</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- FINANCIAL SECTION -->
        <?php if (in_array($role, ['Administrator', 'Manager', 'Accountant'])): ?>
        <ul class="sidebar-menu">
            <li class="sidebar-section-title">Finance</li>
            <li class="sidebar-item <?php echo is_page_active('/expenses/index.php'); ?>">
                <a href="/shop-system/expenses/index.php" class="sidebar-link">
                    <i data-lucide="wallet"></i>
                    <span>Expenses</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- ADMINISTRATION SECTION -->
        <ul class="sidebar-menu">
            <li class="sidebar-section-title">System</li>
            
            <?php if (in_array($role, ['Administrator', 'Manager', 'Accountant'])): ?>
            <li class="sidebar-item <?php echo is_page_active('/reports/index.php'); ?>">
                <a href="/shop-system/reports/index.php" class="sidebar-link">
                    <i data-lucide="bar-chart-3"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($role === 'Administrator'): ?>
            <li class="sidebar-item <?php echo is_page_active('/users/index.php'); ?>">
                <a href="/shop-system/users/index.php" class="sidebar-link">
                    <i data-lucide="shield-check"></i>
                    <span>Users Control</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo is_page_active('/settings/index.php'); ?>">
                <a href="/shop-system/settings/index.php" class="sidebar-link">
                    <i data-lucide="cog"></i>
                    <span>App Settings</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (in_array($role, ['Administrator', 'Manager'])): ?>
            <li class="sidebar-item <?php echo is_page_active('/backups/index.php'); ?>">
                <a href="/shop-system/backups/index.php" class="sidebar-link">
                    <i data-lucide="database-backup"></i>
                    <span>Backups</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
    </div>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user-avatar">
            <?php echo strtoupper(substr($fullname, 0, 1)); ?>
        </div>
        <div class="sidebar-user-info">
            <h4 style="color:#ffffff; font-size:0.85rem; margin-bottom:0; font-weight:600;"><?php echo e($fullname); ?></h4>
            <span style="color:#9ca3af; font-size:0.75rem; font-weight:500;"><?php echo e($role); ?></span>
        </div>
    </div>
</aside>
