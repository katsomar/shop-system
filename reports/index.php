<?php
/**
 * Reports and Business Analytics Dashboard View Page
 */

$page_title = "Business Intelligence & Reports";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

if (!has_role(['Administrator', 'Manager', 'Accountant'])) {
    set_flash_message('danger', 'Unauthorized access to business intelligence reports.');
    header("Location: /shop-system/dashboard/index.php");
    exit();
}

$currency = get_setting($conn, 'currency_symbol', '$');
?>

<!-- Print Stylesheet Overrides -->
<style>
    /* Tab buttons style */
    .report-tab-btn {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        padding: 8px 16px;
        font-weight: 500;
        font-size: 0.85rem;
        border-radius: var(--border-radius-sm);
        cursor: pointer;
        transition: all 0.15s;
    }
    .report-tab-btn.active {
        background-color: var(--primary);
        border-color: var(--primary);
        color: #ffffff;
    }
    .report-tab-btn:hover:not(.active) {
        background-color: var(--bg-app);
    }
    
    /* Print Media formatting definitions */
    @media print {
        header, .sidebar, footer, .filter-toolbar, .tabs-row, .print-btn-container {
            display: none !important;
        }
        main, .content-body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            background-color: #ffffff !important;
        }
        .report-section {
            display: block !important;
            page-break-after: always;
        }
        .chart-container-wrapper {
            display: none !important; /* Hide charts in print receipt logs for cleanliness */
        }
        .table {
            border: 1px solid #cbd5e1 !important;
        }
        .text-danger {
            color: #dc2626 !important;
        }
        .text-success {
            color: #16a34a !important;
        }
    }
</style>

<!-- Filter Toolbar (Hidden during print) -->
<div class="card filter-toolbar mb-4" style="padding: 1.25rem;">
    <div class="d-flex justify-between align-center flex-wrap gap-4">
        
        <!-- Preset and Custom inputs -->
        <div class="d-flex align-center flex-wrap gap-2" style="flex: 1; min-width: 280px;">
            <div class="form-group mb-0" style="margin-bottom:0;">
                <select id="date-preset" class="form-control" style="font-size: 0.85rem; padding: 6px 12px; height: auto; min-width:140px;">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="last7">Last 7 Days</option>
                    <option value="last30" selected>Last 30 Days</option>
                    <option value="this_month">This Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            
            <div class="d-flex align-center gap-2" id="custom-date-inputs" style="display: none !important;">
                <input type="date" id="start-date" class="form-control" style="padding: 5px 10px; font-size: 0.8rem; height: auto;" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                <span class="text-xs text-muted">to</span>
                <input type="date" id="end-date" class="form-control" style="padding: 5px 10px; font-size: 0.8rem; height: auto;" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <button type="button" class="btn btn-secondary d-flex align-center gap-2" id="btn-apply-filter" style="padding: 6px 12px; font-size: 0.8rem;">
                <i data-lucide="refresh-cw" style="width:14px; height:14px;"></i> Update Report
            </button>
        </div>
        
        <!-- Print Button -->
        <div class="print-btn-container">
            <button type="button" class="btn btn-success d-flex align-center gap-2" onclick="window.print()">
                <i data-lucide="printer" style="width:16px; height:16px;"></i> Print Report Sheet
            </button>
        </div>
    </div>
</div>

<!-- Tabs Row (Hidden during print) -->
<div class="d-flex gap-2 tabs-row mb-6" style="overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding-bottom: 4px;">
    <button class="report-tab-btn active" data-target="section-sales">
        <i data-lucide="trending-up" style="width:14px; height:14px; vertical-align:middle; margin-right:4px;"></i> Sales Report
    </button>
    <button class="report-tab-btn" data-target="section-purchases">
        <i data-lucide="shopping-cart" style="width:14px; height:14px; vertical-align:middle; margin-right:4px;"></i> Purchases Report
    </button>
    <button class="report-tab-btn" data-target="section-expenses">
        <i data-lucide="wallet" style="width:14px; height:14px; vertical-align:middle; margin-right:4px;"></i> Expenses Report
    </button>
    <button class="report-tab-btn" data-target="section-pl">
        <i data-lucide="file-text" style="width:14px; height:14px; vertical-align:middle; margin-right:4px;"></i> Profit & Loss (P&L)
    </button>
</div>

<!-- ========================================== -->
<!-- 1. SALES REPORT CONTAINER -->
<!-- ========================================== -->
<div class="report-section" id="section-sales">
    <h2 class="text-lg font-bold text-main mb-4">Sales & Profitability Analysis</h2>
    
    <!-- Sales Metrics -->
    <div class="grid grid-cols-1 grid-cols-sm-4 gap-4 mb-6">
        <div class="card stats-card" style="padding: 1rem;">
            <div>
                <span class="text-muted text-xs font-semibold uppercase">Gross Sales</span>
                <div class="stats-card-value text-md" id="sales-gross">$0.00</div>
            </div>
        </div>
        <div class="card stats-card" style="padding: 1rem;">
            <div>
                <span class="text-muted text-xs font-semibold uppercase">Tax Collected</span>
                <div class="stats-card-value text-md" id="sales-tax">$0.00</div>
            </div>
        </div>
        <div class="card stats-card" style="padding: 1rem;">
            <div>
                <span class="text-muted text-xs font-semibold uppercase">Net profit Margin</span>
                <div class="stats-card-value text-md text-main" id="sales-profit">$0.00</div>
            </div>
        </div>
        <div class="card stats-card" style="padding: 1rem;">
            <div>
                <span class="text-muted text-xs font-semibold uppercase">Gross margin %</span>
                <div class="stats-card-value text-md" id="sales-margin">0.00%</div>
            </div>
        </div>
    </div>
    
    <!-- Chart Row -->
    <div class="card chart-container-wrapper mb-6" style="padding: 1.25rem;">
        <h3 class="text-sm font-semibold mb-4">Daily Sales Trend</h3>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="salesLineChart"></canvas>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 2. PURCHASES REPORT CONTAINER -->
<!-- ========================================== -->
<div class="report-section" id="section-purchases" style="display: none;">
    <h2 class="text-lg font-bold text-main mb-4">Purchase Orders & Payables Liabilities</h2>
    
    <!-- Purchase Metrics -->
    <div class="grid grid-cols-1 grid-cols-sm-3 gap-4 mb-6">
        <div class="card stats-card" style="padding: 1rem;">
            <div>
                <span class="text-muted text-xs font-semibold uppercase">Total Purchases</span>
                <div class="stats-card-value text-md" id="purchases-total">$0.00</div>
            </div>
        </div>
        <div class="card stats-card" style="padding: 1rem;">
            <div>
                <span class="text-muted text-xs font-semibold uppercase">Total Paid</span>
                <div class="stats-card-value text-md text-success" id="purchases-paid">$0.00</div>
            </div>
        </div>
        <div class="card stats-card" style="padding: 1rem;">
            <div>
                <span class="text-muted text-xs font-semibold uppercase">Liabilities Balance</span>
                <div class="stats-card-value text-md text-danger" id="purchases-due">$0.00</div>
            </div>
        </div>
    </div>
    
    <!-- Table list -->
    <div class="card" style="padding: 0; overflow:hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order Date</th>
                        <th>Purchase Invoice #</th>
                        <th>Supplier Partner</th>
                        <th>Grand Total</th>
                        <th>Paid amount</th>
                        <th>Liability Due</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody id="purchases-tbody">
                    <!-- Dynamic PO rows loaded here -->
                </tbody>
            </table>
        </div>
        <div id="purchases-empty-state" style="display: none; padding: 2rem; text-align: center;" class="text-muted text-xs">
            No received purchases found within select range.
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 3. EXPENSES REPORT CONTAINER -->
<!-- ========================================== -->
<div class="report-section" id="section-expenses" style="display: none;">
    <h2 class="text-lg font-bold text-main mb-4">Operational Costs & Category splits</h2>
    
    <div class="grid grid-cols-1 grid-cols-md-12 gap-6 mb-6">
        <!-- Chart Pane -->
        <div class="card chart-container-wrapper" style="grid-column: span 5; padding: 1.25rem; display: flex; flex-direction: column; align-items: center; justify-center;">
            <h3 class="text-sm font-semibold mb-4 w-full">Expense Splits</h3>
            <div style="position: relative; height: 260px; width: 100%; display:flex; justify-content:center;">
                <canvas id="expensesDoughnutChart"></canvas>
            </div>
        </div>
        <!-- Table splits -->
        <div style="grid-column: span 7;" class="d-flex flex-column gap-4">
            <div class="card stats-card mb-0" style="padding: 1rem;">
                <div>
                    <span class="text-muted text-xs font-semibold uppercase">Total Expenses Outflow</span>
                    <div class="stats-card-value text-md text-danger" id="expenses-total-out">$0.00</div>
                </div>
            </div>
            
            <div class="card" style="padding: 0; overflow:hidden; flex:1;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th style="text-align: right;">Total Outflow</th>
                            </tr>
                        </thead>
                        <tbody id="expenses-tbody">
                            <!-- Dynamic category rows loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 4. PROFIT & LOSS REPORT CONTAINER -->
<!-- ========================================== -->
<div class="report-section" id="section-pl" style="display: none;">
    
    <!-- Printable Brand Headers -->
    <div class="header-print-block" style="text-align: center; margin-bottom: 20px;">
        <h1 class="font-bold text-lg text-main" style="margin: 0;"><?php echo e(get_setting($conn, 'shop_name', 'Nexus Shop')); ?></h1>
        <p class="text-muted text-xs" style="margin: 4px 0 0 0;">Profit & Loss Statement Ledger</p>
        <p class="text-xs font-medium" style="margin: 4px 0 0 0; color:var(--primary);">Statement Period: <span id="pl-period">-- to --</span></p>
    </div>
    
    <div class="card" style="padding: 1.5rem; max-width: 680px; margin: 0 auto; border: 1px solid var(--border-color);">
        <table class="table" style="border:none;">
            <tbody>
                <!-- 1. Revenue -->
                <tr style="background-color: var(--bg-app); font-weight: 700; color: var(--main-color);">
                    <td colspan="2" style="font-size: 0.95rem; padding: 10px 15px;">1. Operating Revenue</td>
                </tr>
                <tr>
                    <td style="padding-left: 30px;">Gross Sales Revenue</td>
                    <td style="text-align: right; font-weight: 600;" id="pl-gross-sales">$0.00</td>
                </tr>
                <tr style="border-bottom: 1px dashed var(--border-color);">
                    <td style="padding-left: 30px;" class="text-muted">Less: Customer Returns</td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger);" id="pl-returns">-$0.00</td>
                </tr>
                <tr style="font-weight: 700;">
                    <td style="padding-left: 15px;">Net Operating Revenue</td>
                    <td style="text-align: right; text-decoration: underline;" id="pl-net-sales">$0.00</td>
                </tr>
                
                <!-- Spacer -->
                <tr><td colspan="2" style="height: 10px; border:none; padding:0;"></td></tr>
                
                <!-- 2. Cost of Goods Sold -->
                <tr style="background-color: var(--bg-app); font-weight: 700; color: var(--main-color);">
                    <td colspan="2" style="font-size: 0.95rem; padding: 10px 15px;">2. Cost of Goods Sold (COGS)</td>
                </tr>
                <tr style="border-bottom: 1px dashed var(--border-color);">
                    <td style="padding-left: 30px;">Inventory buying cost of items sold</td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger);" id="pl-cogs">-$0.00</td>
                </tr>
                <tr style="font-weight: 700;">
                    <td style="padding-left: 15px;">Gross Business Profit</td>
                    <td style="text-align: right; text-decoration: underline;" id="pl-gross-profit">$0.00</td>
                </tr>
                
                <!-- Spacer -->
                <tr><td colspan="2" style="height: 10px; border:none; padding:0;"></td></tr>
                
                <!-- 3. Expenses -->
                <tr style="background-color: var(--bg-app); font-weight: 700; color: var(--main-color);">
                    <td colspan="2" style="font-size: 0.95rem; padding: 10px 15px;">3. Operating Expenses</td>
                </tr>
                <tr style="border-bottom: 1px dashed var(--border-color);">
                    <td style="padding-left: 30px;">Operational Cash Outflows</td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger);" id="pl-expenses">-$0.00</td>
                </tr>
                
                <!-- Spacer -->
                <tr><td colspan="2" style="height: 15px; border:none; padding:0;"></td></tr>
                
                <!-- 4. Summary Net Profit -->
                <tr style="font-size: 1.1rem; font-bold; border-top: 2px solid var(--border-color); background-color: var(--bg-app);">
                    <td style="padding: 12px 15px; font-weight: 800; color:var(--text-color);">NET BUSINESS PROFIT</td>
                    <td style="text-align: right; padding: 12px 15px; font-weight: 800;" id="pl-net-profit">$0.00</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    // Tab switching controls
    document.querySelectorAll(".report-tab-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            document.querySelectorAll(".report-tab-btn").forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            
            const target = btn.getAttribute("data-target");
            document.querySelectorAll(".report-section").forEach(s => s.style.display = "none");
            document.getElementById(target).style.display = "block";
            
            // Reload specific report action
            const action = target.replace("section-", "");
            loadReport(action);
        });
    });
    
    // Preset filter changes
    const presetSelect = document.getElementById("date-preset");
    const customDateInputs = document.getElementById("custom-date-inputs");
    const startDateInput = document.getElementById("start-date");
    const endDateInput = document.getElementById("end-date");
    
    presetSelect.addEventListener("change", () => {
        const val = presetSelect.value;
        if (val === "custom") {
            customDateInputs.style.setProperty("display", "flex", "important");
        } else {
            customDateInputs.style.setProperty("display", "none", "important");
            setPresetDates(val);
        }
    });
    
    // Set preset inputs
    function setPresetDates(preset) {
        const today = new Date().toISOString().split("T")[0];
        let start = today;
        let end = today;
        
        if (preset === "yesterday") {
            const yest = new Date();
            yest.setDate(yest.getDate() - 1);
            start = yest.toISOString().split("T")[0];
            end = start;
        } else if (preset === "last7") {
            const s = new Date();
            s.setDate(s.getDate() - 7);
            start = s.toISOString().split("T")[0];
        } else if (preset === "last30") {
            const s = new Date();
            s.setDate(s.getDate() - 30);
            start = s.toISOString().split("T")[0];
        } else if (preset === "this_month") {
            const now = new Date();
            start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split("T")[0];
        }
        
        startDateInput.value = start;
        endDateInput.value = end;
    }
    
    // Initialize datepreset
    setPresetDates("last30");
    
    // Bind apply filters button
    document.getElementById("btn-apply-filter").addEventListener("click", () => {
        const activeTab = document.querySelector(".report-tab-btn.active");
        const action = activeTab.getAttribute("data-target").replace("section-", "");
        loadReport(action);
    });
    
    // Setup Chart.js line and doughnut chart variables
    let salesChart = null;
    let expensesChart = null;
    
    // Load reports dynamically based on tabs and dates
    async function loadReport(reportAction) {
        const start = startDateInput.value;
        const end = endDateInput.value;
        
        const params = new URLSearchParams({
            action: reportAction,
            start_date: start,
            end_date: end
        });
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/reports.php?" + params.toString());
            if (!res || !res.success) {
                showToast("Failed to fetch report data.", "danger");
                return;
            }
            
            if (reportAction === "sales") {
                // Populate Sales Summary
                document.getElementById("sales-gross").innerText = "$" + res.summary.total_sales.toFixed(2);
                document.getElementById("sales-tax").innerText = "$" + res.summary.total_tax.toFixed(2);
                document.getElementById("sales-profit").innerText = "$" + res.summary.total_profit.toFixed(2);
                document.getElementById("sales-margin").innerText = res.summary.margin.toFixed(2) + "%";
                
                // Redraw Line Chart
                renderSalesChart(res.chart.labels, res.chart.datasets);
            } 
            else if (reportAction === "purchases") {
                // Populate Purchases Summary
                document.getElementById("purchases-total").innerText = "$" + res.summary.total_purchases.toFixed(2);
                document.getElementById("purchases-paid").innerText = "$" + res.summary.total_paid.toFixed(2);
                document.getElementById("purchases-due").innerText = "$" + res.summary.outstanding_due.toFixed(2);
                
                // Populate PO list table rows
                const tbody = document.getElementById("purchases-tbody");
                const empty = document.getElementById("purchases-empty-state");
                
                tbody.innerHTML = "";
                if (res.orders && res.orders.length > 0) {
                    empty.style.display = "none";
                    res.orders.forEach(po => {
                        const total = parseFloat(po.total_amount);
                        const paid = parseFloat(po.paid_amount);
                        const due = total - paid;
                        
                        // Status styling
                        let payBadge = "badge-secondary";
                        if (po.payment_status === "Paid") payBadge = "badge-success";
                        if (po.payment_status === "Partial") payBadge = "badge-warning";
                        if (po.payment_status === "Unpaid") payBadge = "badge-danger";
                        
                        tbody.innerHTML += `
                            <tr>
                                <td class="text-sm">\${po.order_date}</td>
                                <td class="font-semibold text-xs">\${e(po.purchase_no)}</td>
                                <td class="text-sm">\${e(po.supplier_name)}</td>
                                <td class="text-sm font-semibold">$\${total.toFixed(2)}</td>
                                <td class="text-sm text-success">$\${paid.toFixed(2)}</td>
                                <td class="text-sm text-danger">$\${due.toFixed(2)}</td>
                                <td><span class="badge \${payBadge}">\${po.payment_status}</span></td>
                            </tr>
                        `;
                    });
                } else {
                    empty.style.display = "block";
                }
            } 
            else if (reportAction === "expenses") {
                // Populate Expenses summary
                document.getElementById("expenses-total-out").innerText = "$" + res.summary.total_expenses.toFixed(2);
                
                // Populate Category list table rows
                const tbody = document.getElementById("expenses-tbody");
                tbody.innerHTML = "";
                if (res.categories && res.categories.length > 0) {
                    res.categories.forEach(row => {
                        tbody.innerHTML += `
                            <tr>
                                <td class="text-sm font-semibold">\${e(row.category_name)}</td>
                                <td class="text-sm font-semibold text-danger" style="text-align:right;">$\${parseFloat(row.category_total).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="2" class="text-center text-muted text-xs p-4">No operational expenses found</td></tr>`;
                }
                
                // Redraw Doughnut Chart
                renderExpensesChart(res.chart.labels, res.chart.datasets);
            } 
            else if (reportAction === "pl") {
                // Populate P&L Statement
                const stmt = res.statement;
                document.getElementById("pl-period").innerText = stmt.start_date + " to " + stmt.end_date;
                document.getElementById("pl-gross-sales").innerText = "$" + parseFloat(stmt.gross_sales).toFixed(2);
                document.getElementById("pl-returns").innerText = "-$" + parseFloat(stmt.customer_returns).toFixed(2);
                document.getElementById("pl-net-sales").innerText = "$" + parseFloat(stmt.net_sales).toFixed(2);
                document.getElementById("pl-cogs").innerText = "-$" + parseFloat(stmt.cogs).toFixed(2);
                document.getElementById("pl-gross-profit").innerText = "$" + parseFloat(stmt.gross_profit).toFixed(2);
                document.getElementById("pl-expenses").innerText = "-$" + parseFloat(stmt.operating_expenses).toFixed(2);
                
                const netProfit = parseFloat(stmt.net_profit);
                const plNet = document.getElementById("pl-net-profit");
                plNet.innerText = (netProfit >= 0 ? "$" : "-$") + Math.abs(netProfit).toFixed(2);
                plNet.className = netProfit >= 0 ? "text-success" : "text-danger";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve intelligence report.", "danger");
        }
    }
    
    // Load Sales report initially
    loadReport("sales");
    
    // Line Chart renderer
    function renderSalesChart(labels, dataset) {
        const ctx = document.getElementById("salesLineChart")?.getContext("2d");
        if (!ctx) return;
        
        if (salesChart) {
            salesChart.destroy();
        }
        
        // Premium curves and dark-theme configurations
        const isDark = document.body.classList.contains("dark-mode");
        const gridColor = isDark ? "rgba(255,255,255,0.06)" : "rgba(0,0,0,0.04)";
        const tickColor = isDark ? "#94a3b8" : "#64748b";
        
        salesChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: labels,
                datasets: [{
                    label: "Daily Gross Sales Revenue ($)",
                    data: dataset,
                    borderColor: "#22c55e",
                    backgroundColor: "rgba(34, 197, 94, 0.08)",
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: "#22c55e"
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
                        grid: { color: gridColor },
                        ticks: { color: tickColor, font: { family: "Inter", size: 10 } }
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: tickColor, font: { family: "Inter", size: 10 } }
                    }
                }
            }
        });
    }
    
    // Doughnut Chart renderer
    function renderExpensesChart(labels, dataset) {
        const ctx = document.getElementById("expensesDoughnutChart")?.getContext("2d");
        if (!ctx) return;
        
        if (expensesChart) {
            expensesChart.destroy();
        }
        
        const isDark = document.body.classList.contains("dark-mode");
        const legendColor = isDark ? "#cbd5e1" : "#334155";
        
        expensesChart = new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: labels,
                datasets: [{
                    data: dataset,
                    backgroundColor: [
                        "#3b82f6", "#ef4444", "#f59e0b", "#10b981", 
                        "#6366f1", "#ec4899", "#8b5cf6", "#14b8a6"
                    ],
                    borderWidth: isDark ? 2 : 1,
                    borderColor: isDark ? "#1e293b" : "#ffffff"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "right",
                        labels: {
                            color: legendColor,
                            font: { family: "Inter", size: 11 }
                        }
                    }
                }
            }
        });
    }

    // Helper for HTML escaping
    function e(string) {
        return (string || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/\x27/g, "&#039;");
    }
});
</script>
';

include_once __DIR__ . '/../includes/footer.php';
?>
