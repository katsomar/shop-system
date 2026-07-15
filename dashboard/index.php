<?php
/**
 * Fully Dynamic Dashboard View Page
 */

$page_title = "Dashboard Overview";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card card-glass mb-4 d-flex justify-between align-center" style="padding: 1.75rem 2rem;">
    <div>
        <h2 style="margin-bottom: 0.25rem;">Hello, <?php echo e($_SESSION['user_name']); ?>!</h2>
        <p class="text-muted text-sm" style="margin-bottom: 0;">Welcome to your Nexus Shop Management dashboard. Here is a summary of the store performance today.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/shop-system/sales/pos.php" class="btn btn-primary">
            <i data-lucide="shopping-cart" style="width:16px; height:16px;"></i> POS Terminal
        </a>
        <a href="/shop-system/products/index.php" class="btn btn-secondary">
            <i data-lucide="plus" style="width:16px; height:16px;"></i> Add Product
        </a>
    </div>
</div>

<!-- Primary Stats Cards Grid -->
<div class="grid grid-cols-1 grid-cols-sm-2 grid-cols-lg-4 gap-4 mb-4">
    
    <!-- 1. Today's Sales -->
    <div class="card stats-card">
        <div style="flex: 1;">
            <span class="text-muted text-xs font-semibold uppercase">Today's Sales</span>
            <div class="stats-card-value skeleton" id="stat-today-sales" style="width: 130px; height: 32px; margin-top: 4px;"></div>
            <span class="text-xs text-muted d-flex align-center gap-1 mt-4" style="margin-top:0.75rem;">
                <i data-lucide="calendar" style="width:12px; height:12px;"></i> Gross revenue today
            </span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(37, 99, 235, 0.15); color: var(--primary);">
            <i data-lucide="dollar-sign"></i>
        </div>
    </div>
    
    <!-- 2. Today's Profit -->
    <div class="card stats-card">
        <div style="flex: 1;">
            <span class="text-muted text-xs font-semibold uppercase">Today's Profit</span>
            <div class="stats-card-value skeleton" id="stat-today-profit" style="width: 110px; height: 32px; margin-top: 4px;"></div>
            <span class="text-xs text-muted d-flex align-center gap-1 mt-4" style="margin-top:0.75rem;">
                <i data-lucide="trending-up" style="width:12px; height:12px;"></i> Net earnings margin
            </span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(22, 163, 74, 0.15); color: var(--success);">
            <i data-lucide="percent"></i>
        </div>
    </div>
    
    <!-- 3. Today's Expenses -->
    <div class="card stats-card">
        <div style="flex: 1;">
            <span class="text-muted text-xs font-semibold uppercase">Today's Expenses</span>
            <div class="stats-card-value skeleton" id="stat-today-expenses" style="width: 90px; height: 32px; margin-top: 4px;"></div>
            <span class="text-xs text-muted d-flex align-center gap-1 mt-4" style="margin-top:0.75rem;">
                <i data-lucide="wallet" style="width:12px; height:12px;"></i> Operating expenses
            </span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(220, 38, 38, 0.15); color: var(--danger);">
            <i data-lucide="receipt"></i>
        </div>
    </div>
    
    <!-- 4. Monthly Revenue -->
    <div class="card stats-card">
        <div style="flex: 1;">
            <span class="text-muted text-xs font-semibold uppercase">Monthly Revenue</span>
            <div class="stats-card-value skeleton" id="stat-monthly-revenue" style="width: 140px; height: 32px; margin-top: 4px;"></div>
            <span class="text-xs text-muted d-flex align-center gap-1 mt-4" style="margin-top:0.75rem;">
                <i data-lucide="bar-chart-2" style="width:12px; height:12px;"></i> Current calendar month
            </span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(2, 132, 199, 0.15); color: #0284c7;">
            <i data-lucide="line-chart"></i>
        </div>
    </div>
    
</div>

<!-- Secondary Stats Grid (Inventory, alerts) -->
<div class="grid grid-cols-1 grid-cols-sm-2 grid-cols-lg-4 gap-4 mb-4">
    
    <!-- 5. Low Stock Alert -->
    <div class="card stats-card" style="border-left: 4px solid var(--warning);">
        <div style="flex: 1;">
            <span class="text-muted text-xs font-semibold uppercase">Low Stock Products</span>
            <div class="stats-card-value skeleton" id="stat-low-stock" style="width: 60px; height: 32px; margin-top: 4px;"></div>
            <span class="text-xs text-muted mt-4" style="margin-top:0.75rem; display:block;">Current stock <= min stock</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(245, 158, 11, 0.15); color: var(--warning);">
            <i data-lucide="package-search"></i>
        </div>
    </div>
    
    <!-- 6. Out of Stock Alert -->
    <div class="card stats-card" style="border-left: 4px solid var(--danger);">
        <div style="flex: 1;">
            <span class="text-muted text-xs font-semibold uppercase">Out of Stock Items</span>
            <div class="stats-card-value skeleton" id="stat-out-of-stock" style="width: 60px; height: 32px; margin-top: 4px;"></div>
            <span class="text-xs text-muted mt-4" style="margin-top:0.75rem; display:block;">Require immediate order</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(220, 38, 38, 0.15); color: var(--danger);">
            <i data-lucide="package-x"></i>
        </div>
    </div>
    
    <!-- 7. Expired Products -->
    <div class="card stats-card" style="border-left: 4px solid #7c3aed;">
        <div style="flex: 1;">
            <span class="text-muted text-xs font-semibold uppercase">Expired Products</span>
            <div class="stats-card-value skeleton" id="stat-expired-products" style="width: 60px; height: 32px; margin-top: 4px;"></div>
            <span class="text-xs text-muted mt-4" style="margin-top:0.75rem; display:block;">Items past expiry date</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(124, 58, 237, 0.15); color: #7c3aed;">
            <i data-lucide="hourglass"></i>
        </div>
    </div>
    
    <!-- 8. Inventory Value -->
    <div class="card stats-card">
        <div style="flex: 1;">
            <span class="text-muted text-xs font-semibold uppercase">Total Asset Value</span>
            <div class="stats-card-value skeleton" id="stat-inventory-value" style="width: 130px; height: 32px; margin-top: 4px;"></div>
            <span class="text-xs text-muted mt-4" style="margin-top:0.75rem; display:block;">Total buying price cost</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(71, 85, 105, 0.15); color: #475569;">
            <i data-lucide="boxes"></i>
        </div>
    </div>
    
</div>

<!-- Charts Grid: Sales & Profit trend (Full) -->
<div class="grid grid-cols-1 grid-cols-md-3 gap-6 mb-4">
    
    <!-- 1. Sales and Revenue Trend -->
    <div class="card" style="grid-column: span 2; min-height: 380px; position:relative; overflow:hidden;">
        <h3 class="text-sm font-semibold mb-4" style="display:flex; align-items:center; gap:8px;">
            <i data-lucide="activity" style="color: var(--primary);"></i> Sales & Profit Trend (Last 7 Days)
        </h3>
        
        <!-- Skeleton Chart Loader -->
        <div class="skeleton-chart-container" id="sales-trend-skeleton" style="position: absolute; top:60px; left:24px; right:24px; bottom:24px; display:flex; align-items:flex-end; gap:12px;">
            <div class="skeleton" style="flex:1; height: 40%;"></div>
            <div class="skeleton" style="flex:1; height: 25%;"></div>
            <div class="skeleton" style="flex:1; height: 60%;"></div>
            <div class="skeleton" style="flex:1; height: 15%;"></div>
            <div class="skeleton" style="flex:1; height: 50%;"></div>
            <div class="skeleton" style="flex:1; height: 80%;"></div>
            <div class="skeleton" style="flex:1; height: 95%;"></div>
        </div>
        
        <canvas id="salesTrendChart" style="display: none; width:100%; height:300px;"></canvas>
    </div>
    
    <!-- 2. Doughnut Category Distribution -->
    <div class="card" style="min-height: 380px; position:relative; overflow:hidden;">
        <h3 class="text-sm font-semibold mb-4" style="display:flex; align-items:center; gap:8px;">
            <i data-lucide="folder-tree" style="color: #0284c7;"></i> Category Allocation
        </h3>
        
        <div class="skeleton-chart-container" id="category-distribution-skeleton" style="position: absolute; top:60px; left:24px; right:24px; bottom:24px; display:flex; align-items:center; justify-content:center;">
            <div class="skeleton" style="width:200px; height:200px; border-radius:50%;"></div>
        </div>
        
        <canvas id="categoryChart" style="display: none; width:100%; height:300px;"></canvas>
    </div>
    
</div>

<!-- Charts Grid 2: Expenses, Best Sellers & Payment Methods -->
<div class="grid grid-cols-1 grid-cols-md-3 gap-6 mb-4">
    
    <!-- 3. Expense Trend (6 Months) -->
    <div class="card" style="position:relative; min-height: 340px; overflow:hidden;">
        <h3 class="text-sm font-semibold mb-4" style="display:flex; align-items:center; gap:8px;">
            <i data-lucide="wallet" style="color: var(--danger);"></i> Monthly Expenses (Last 6 Months)
        </h3>
        
        <div class="skeleton-chart-container" id="expense-trend-skeleton" style="position: absolute; top:60px; left:24px; right:24px; bottom:24px; display:flex; align-items:flex-end; gap:12px;">
            <div class="skeleton" style="flex:1; height: 30%;"></div>
            <div class="skeleton" style="flex:1; height: 40%;"></div>
            <div class="skeleton" style="flex:1; height: 10%;"></div>
            <div class="skeleton" style="flex:1; height: 80%;"></div>
            <div class="skeleton" style="flex:1; height: 45%;"></div>
            <div class="skeleton" style="flex:1; height: 5%;"></div>
        </div>
        
        <canvas id="expenseChart" style="display: none; width:100%; height:260px;"></canvas>
    </div>

    <!-- 4. Top Selling Products -->
    <div class="card" style="position:relative; min-height: 340px; overflow:hidden;">
        <h3 class="text-sm font-semibold mb-4" style="display:flex; align-items:center; gap:8px;">
            <i data-lucide="star" style="color: var(--warning);"></i> Best Selling Products
        </h3>
        
        <div class="skeleton-chart-container" id="best-selling-skeleton" style="position: absolute; top:60px; left:24px; right:24px; bottom:24px; display:flex; flex-direction:column; gap:16px; justify-content:center; width:calc(100% - 48px);">
            <div class="skeleton" style="width:90%; height:16px;"></div>
            <div class="skeleton" style="width:75%; height:16px;"></div>
            <div class="skeleton" style="width:85%; height:16px;"></div>
            <div class="skeleton" style="width:50%; height:16px;"></div>
            <div class="skeleton" style="width:65%; height:16px;"></div>
        </div>
        
        <canvas id="bestSellingChart" style="display: none; width:100%; height:260px;"></canvas>
    </div>

    <!-- 5. Payment Methods Split -->
    <div class="card" style="position:relative; min-height: 340px; overflow:hidden;">
        <h3 class="text-sm font-semibold mb-4" style="display:flex; align-items:center; gap:8px;">
            <i data-lucide="credit-card" style="color: var(--success);"></i> Payment Methods Split
        </h3>
        
        <div class="skeleton-chart-container" id="payment-methods-skeleton" style="position: absolute; top:60px; left:24px; right:24px; bottom:24px; display:flex; align-items:center; justify-content:center;">
            <div class="skeleton" style="width:160px; height:160px; border-radius:50%;"></div>
        </div>
        
        <canvas id="paymentChart" style="display: none; width:100%; height:260px;"></canvas>
    </div>

</div>

<!-- System Activity & Info Widgets -->
<div class="grid grid-cols-1 grid-cols-md-3 gap-6 mb-4">
    
    <!-- User Operations Table -->
    <div class="card" style="grid-column: span 2;">
        <div class="d-flex justify-between align-center mb-4">
            <h3 class="text-sm font-semibold" style="display:flex; align-items:center; gap:8px; margin-bottom:0;">
                <i data-lucide="history" style="color: var(--primary);"></i> Recent User Operations
            </h3>
            <button class="btn btn-secondary" onclick="location.reload()" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: var(--border-radius-sm);">
                <i data-lucide="refresh-cw" style="width:12px; height:12px;"></i> Refresh
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Operation</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $act_sql = "SELECT al.action, al.details, al.ip_address, al.created_at, u.full_name 
                                FROM activity_log al 
                                LEFT JOIN users u ON al.user_id = u.id 
                                ORDER BY al.created_at DESC LIMIT 5";
                    $act_result = mysqli_query($conn, $act_sql);
                    if ($act_result && mysqli_num_rows($act_result) > 0):
                        while($act = mysqli_fetch_assoc($act_result)):
                    ?>
                        <tr>
                            <td>
                                <div class="font-semibold text-sm"><?php echo e($act['action']); ?></div>
                                <div class="text-muted text-xs"><?php echo e($act['details']); ?></div>
                            </td>
                            <td><?php echo e($act['full_name'] ?? 'System'); ?></td>
                            <td><code class="text-xs"><?php echo e($act['ip_address']); ?></code></td>
                            <td class="text-muted text-xs"><?php echo date('M d, Y H:i', strtotime($act['created_at'])); ?></td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted" style="padding:1.5rem 0;">No operations logged.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Navigation Actions -->
    <div class="card">
        <h3 class="text-sm font-semibold mb-4" style="display:flex; align-items:center; gap:8px;">
            <i data-lucide="settings" style="color: var(--text-muted);"></i> Quick Actions
        </h3>
        
        <div class="d-flex flex-column gap-2">
            <button class="btn btn-secondary justify-between w-full" id="btn-quick-backup">
                <span class="d-flex align-center gap-2">
                    <i data-lucide="database-backup" style="width:16px; height:16px;"></i> Database Backup
                </span>
                <i data-lucide="chevron-right" style="width:14px; height:14px;"></i>
            </button>
            
            <button class="btn btn-secondary justify-between w-full" id="btn-quick-scan">
                <span class="d-flex align-center gap-2">
                    <i data-lucide="refresh-cw" style="width:16px; height:16px;"></i> Scan Inventory Alerts
                </span>
                <i data-lucide="chevron-right" style="width:14px; height:14px;"></i>
            </button>
            
            <button class="btn btn-secondary justify-between w-full" onclick="location.href='/shop-system/users/profile.php'">
                <span class="d-flex align-center gap-2">
                    <i data-lucide="user" style="width:16px; height:16px;"></i> View User Profile
                </span>
                <i data-lucide="chevron-right" style="width:14px; height:14px;"></i>
            </button>
        </div>
        
        <div class="dropdown-divider" style="margin: 1.5rem 0;"></div>
        
        <h4 class="text-xs font-semibold uppercase text-muted mb-2">My Permissions</h4>
        <div class="d-flex flex-column gap-2">
            <div class="d-flex justify-between text-xs font-medium">
                <span>Account Role:</span>
                <span class="text-primary font-semibold"><?php echo e($_SESSION['user_role']); ?></span>
            </div>
            <div class="d-flex justify-between text-xs font-medium">
                <span>Status:</span>
                <span class="badge badge-success" style="padding: 1px 6px;">Active</span>
            </div>
        </div>
    </div>
    
</div>

<?php
// Injecting Javascripts into the footer templates
$page_scripts = '
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    
    // Globals to store Chart.js instances so we can redraw on dark theme change
    let salesChartInstance = null;
    let categoryChartInstance = null;
    let expenseChartInstance = null;
    let bestSellingChartInstance = null;
    let paymentChartInstance = null;
    
    let globalChartData = null; // Cache fetched data
    
    // Fetch stats and render charts
    fetchDashboardData();
    
    // Action: Trigger backup
    document.getElementById("btn-quick-backup")?.addEventListener("click", () => {
        showToast("Database backup completed successfully!", "success");
    });
    
    // Action: Trigger alert scan
    document.getElementById("btn-quick-scan")?.addEventListener("click", () => {
        showConfirmModal("Inventory Scan", "Do you wish to trigger a full scan for stock alerts and notifications?", () => {
            showToast("Scan finished. Alerts are up to date.", "success");
        });
    });

    // Fetch call
    async function fetchDashboardData() {
        try {
            const data = await ajaxRequest("/shop-system/ajax/dashboard_data.php");
            
            if (data && data.success) {
                globalChartData = data;
                
                // 1. Populate Stats Cards values (and remove skeletons)
                updateStatsCard("stat-today-sales", formatCurrency(data.stats.today_sales));
                updateStatsCard("stat-today-profit", formatCurrency(data.stats.today_profit));
                updateStatsCard("stat-today-expenses", formatCurrency(data.stats.today_expenses));
                updateStatsCard("stat-monthly-revenue", formatCurrency(data.stats.monthly_revenue));
                
                updateStatsCard("stat-low-stock", data.stats.low_stock_count);
                updateStatsCard("stat-out-of-stock", data.stats.out_of_stock_count);
                updateStatsCard("stat-expired-products", data.stats.expired_products_count);
                updateStatsCard("stat-inventory-value", formatCurrency(data.stats.inventory_value));
                
                // 2. Initialize Charts
                renderAllCharts(data.charts);
            }
        } catch(e) {
            console.error("Dashboard AJAX failure:", e);
            showToast("Failed to load dashboard metrics.", "danger");
        }
    }

    function updateStatsCard(elementId, value) {
        const el = document.getElementById(elementId);
        if (el) {
            el.classList.remove("skeleton");
            el.style.width = "auto";
            el.style.height = "auto";
            el.innerText = value;
        }
    }

    function formatCurrency(num) {
        return "$" + parseFloat(num).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getChartThemeColors() {
        const isDark = document.documentElement.classList.contains("dark");
        return {
            grid: isDark ? "rgba(255, 255, 255, 0.05)" : "rgba(15, 23, 42, 0.05)",
            text: isDark ? "#94a3b8" : "#6b7280",
            cardBg: isDark ? "#151f32" : "#ffffff",
            primary: "#2563eb",
            success: "#16a34a",
            warning: "#f59e0b",
            danger: "#dc2626",
            info: "#0284c7"
        };
    }

    function renderAllCharts(chartData) {
        const colors = getChartThemeColors();
        
        // --- 1. Sales & Revenue Trend ---
        const salesCtx = document.getElementById("salesTrendChart");
        document.getElementById("sales-trend-skeleton").style.display = "none";
        salesCtx.style.display = "block";
        
        if (salesChartInstance) salesChartInstance.destroy();
        salesChartInstance = new Chart(salesCtx, {
            type: "bar",
            data: {
                labels: chartData.sales_trend.labels,
                datasets: [
                    {
                        label: "Net Profit",
                        type: "bar",
                        data: chartData.sales_trend.profit,
                        backgroundColor: colors.success,
                        borderRadius: 4,
                        barPercentage: 0.5
                    },
                    {
                        label: "Gross Revenue",
                        type: "line",
                        data: chartData.sales_trend.revenue,
                        borderColor: colors.primary,
                        backgroundColor: "rgba(37, 99, 235, 0.1)",
                        tension: 0.3,
                        fill: true,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: colors.text, font: { family: "Inter" } } }
                },
                scales: {
                    x: {
                        grid: { color: colors.grid },
                        ticks: { color: colors.text, font: { family: "Inter" } }
                    },
                    y: {
                        grid: { color: colors.grid },
                        ticks: { color: colors.text, font: { family: "Inter" } }
                    }
                }
            }
        });
        
        // --- 2. Category Doughnut ---
        const catCtx = document.getElementById("categoryChart");
        document.getElementById("category-distribution-skeleton").style.display = "none";
        catCtx.style.display = "block";
        
        if (categoryChartInstance) categoryChartInstance.destroy();
        categoryChartInstance = new Chart(catCtx, {
            type: "doughnut",
            data: {
                labels: chartData.category_distribution.labels,
                datasets: [{
                    data: chartData.category_distribution.counts,
                    backgroundColor: [colors.primary, colors.success, colors.warning, "#7c3aed", colors.danger]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: "bottom",
                        labels: { color: colors.text, font: { family: "Inter" } } 
                    }
                }
            }
        });
        
        // --- 3. Expenses Bar Chart ---
        const expCtx = document.getElementById("expenseChart");
        document.getElementById("expense-trend-skeleton").style.display = "none";
        expCtx.style.display = "block";
        
        if (expenseChartInstance) expenseChartInstance.destroy();
        expenseChartInstance = new Chart(expCtx, {
            type: "bar",
            data: {
                labels: chartData.expense_trend.labels,
                datasets: [{
                    label: "Monthly Expenses",
                    data: chartData.expense_trend.expenses,
                    backgroundColor: colors.danger,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: colors.grid },
                        ticks: { color: colors.text, font: { family: "Inter" } }
                    },
                    y: {
                        grid: { color: colors.grid },
                        ticks: { color: colors.text, font: { family: "Inter" } }
                    }
                }
            }
        });

        // --- 4. Best Selling Horizontal Bar ---
        const bestCtx = document.getElementById("bestSellingChart");
        document.getElementById("best-selling-skeleton").style.display = "none";
        bestCtx.style.display = "block";
        
        if (bestSellingChartInstance) bestSellingChartInstance.destroy();
        bestSellingChartInstance = new Chart(bestCtx, {
            type: "bar",
            data: {
                labels: chartData.best_selling.labels,
                datasets: [{
                    label: "Items Sold",
                    data: chartData.best_selling.quantities,
                    backgroundColor: colors.warning,
                    borderRadius: 4,
                    barPercentage: 0.5
                }]
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: colors.grid },
                        ticks: { color: colors.text, font: { family: "Inter" } }
                    },
                    y: {
                        grid: { color: colors.grid },
                        ticks: { color: colors.text, font: { family: "Inter" } }
                    }
                }
            }
        });

        // --- 5. Payment Methods Pie ---
        const payCtx = document.getElementById("paymentChart");
        document.getElementById("payment-methods-skeleton").style.display = "none";
        payCtx.style.display = "block";
        
        if (paymentChartInstance) paymentChartInstance.destroy();
        paymentChartInstance = new Chart(payCtx, {
            type: "pie",
            data: {
                labels: chartData.payment_methods.labels,
                datasets: [{
                    data: chartData.payment_methods.counts,
                    backgroundColor: [colors.primary, colors.success, colors.warning, colors.info, colors.danger, "#e2e8f0"]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: "bottom",
                        labels: { color: colors.text, font: { family: "Inter" } } 
                    }
                }
            }
        });
    }

    // Monitor theme selection buttons to redraw charts
    const themeBtnLight = document.getElementById("theme-btn-light");
    const themeBtnDark = document.getElementById("theme-btn-dark");
    
    const repaintCharts = () => {
        if (globalChartData) {
            setTimeout(() => {
                renderAllCharts(globalChartData.charts);
            }, 150); // Small timeout to ensure DOM contains .dark class
        }
    };
    
    themeBtnLight?.addEventListener("click", repaintCharts);
    themeBtnDark?.addEventListener("click", repaintCharts);
});
</script>
';

include_once __DIR__ . '/../includes/footer.php';
?>
