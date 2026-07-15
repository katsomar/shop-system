<?php
/**
 * Purchase Orders List View Page
 */

$page_title = "Purchase Orders";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper', 'Accountant']);

// Calculate totals from database
$tot_q = mysqli_query($conn, "
    SELECT 
        COUNT(*), 
        SUM(CASE WHEN status = 'Received' THEN 1 ELSE 0 END), 
        SUM(total_amount - paid_amount) 
    FROM purchase_orders
");
$totals = mysqli_fetch_row($tot_q);
$total_pos = (int)($totals[0] ?? 0);
$received_pos = (int)($totals[1] ?? 0);
$outstanding_payables = (float)($totals[2] ?? 0.00);

$currency = get_setting($conn, 'currency_symbol', '$');
?>

<!-- Statistics Overview Cards -->
<div class="grid grid-cols-1 grid-cols-sm-3 gap-4 mb-6">
    <!-- Total Purchase Orders count -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Total Purchase Orders</span>
            <div class="stats-card-value" id="stats-total"><?php echo $total_pos; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">All recorded stock orders</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(37, 99, 235, 0.15); color: var(--primary);">
            <i data-lucide="receipt"></i>
        </div>
    </div>
    
    <!-- Received Orders count -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Received Orders</span>
            <div class="stats-card-value" id="stats-received" style="color: var(--success);"><?php echo $received_pos; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Stock successfully receipted</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(22, 163, 74, 0.15); color: var(--success);">
            <i data-lucide="package-check"></i>
        </div>
    </div>
    
    <!-- Outstanding Payables -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Outstanding Payables</span>
            <div class="stats-card-value" id="stats-payables" style="color: var(--danger);"><?php echo $currency; ?><?php echo number_format($outstanding_payables, 2); ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Outstanding debt to suppliers</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(220, 38, 38, 0.15); color: var(--danger);">
            <i data-lucide="wallet"></i>
        </div>
    </div>
</div>

<!-- Header Action Row -->
<div class="card mb-4" style="padding: 1.25rem;">
    <div class="d-flex justify-between align-center flex-wrap gap-4">
        <!-- Filters panel -->
        <div class="d-flex align-center flex-wrap gap-2" style="flex: 1; min-width: 280px;">
            <div class="search-container" style="max-width: 260px; flex: 1;">
                <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
                <input type="text" id="po-search" class="form-control search-input" placeholder="Invoice # or supplier...">
            </div>
            
            <select id="po-status" class="form-control" style="max-width: 130px; font-size: 0.85rem; padding: 6px 12px; height: auto;">
                <option value="">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Ordered">Ordered</option>
                <option value="Received">Received</option>
            </select>
            
            <select id="po-payment" class="form-control" style="max-width: 140px; font-size: 0.85rem; padding: 6px 12px; height: auto;">
                <option value="">All Payments</option>
                <option value="Paid">Paid</option>
                <option value="Partial">Partial</option>
                <option value="Unpaid">Unpaid</option>
            </select>
        </div>
        
        <div>
            <?php if ($can_edit): ?>
                <a href="/shop-system/purchases/add.php" class="btn btn-primary d-flex align-center gap-2">
                    <i data-lucide="plus" style="width:16px; height:16px;"></i> Create Purchase Order
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Datatable Card -->
<div class="card" style="padding: 0; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-hover" id="po-table">
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Supplier Partner</th>
                    <th>Order Date</th>
                    <th>Total Cost</th>
                    <th>Paid Amount</th>
                    <th>Balance Due</th>
                    <th>Order Status</th>
                    <th>Payment Status</th>
                    <th style="width: 80px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="po-rows">
                <!-- Dynamic AJAX rows preloaded here -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="po-empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="package-search" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No purchase orders found</h4>
        <p class="text-muted text-sm">Create a new purchase order to reorder product inventories.</p>
    </div>
    
    <!-- Pagination controls -->
    <div class="d-flex justify-between align-center" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap:wrap; gap: 0.5rem;">
        <span class="text-muted text-xs" id="pagination-summary">Showing 0 to 0 of 0 entries</span>
        <div class="pagination" id="pagination-controls">
            <!-- Dynamic pages -->
        </div>
    </div>
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    let currentPage = 1;
    let pageLimit = 10;
    
    // Fetch initial list
    fetchPurchases();
    
    // Bind search and filters
    const searchInput = document.getElementById("po-search");
    const statusSelect = document.getElementById("po-status");
    const paymentSelect = document.getElementById("po-payment");
    
    let searchTimeout = null;
    searchInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchPurchases();
        }, 300);
    });
    
    statusSelect.addEventListener("change", () => { currentPage = 1; fetchPurchases(); });
    paymentSelect.addEventListener("change", () => { currentPage = 1; fetchPurchases(); });
    
    // Fetch Purchase Orders
    async function fetchPurchases() {
        const poRows = document.getElementById("po-rows");
        const emptyState = document.getElementById("po-empty-state");
        
        poRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            poRows.innerHTML += `
                <tr>
                    <td><div class="skeleton" style="width:110px; height:16px;"></div></td>
                    <td>
                        <div class="skeleton" style="width:130px; height:14px; margin-bottom:4px;"></div>
                        <div class="skeleton" style="width:80px; height:12px;"></div>
                    </td>
                    <td><div class="skeleton" style="width:80px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:70px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:70px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:70px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:80px; height:20px; border-radius:50px;"></div></td>
                    <td><div class="skeleton" style="width:80px; height:20px; border-radius:50px;"></div></td>
                    <td style="text-align:right;"><div class="skeleton" style="width:40px; height:28px; margin-left:auto; border-radius:6px;"></div></td>
                </tr>
            `;
        }
        
        const params = new URLSearchParams({
            action: "list",
            page: currentPage,
            limit: pageLimit,
            search: searchInput.value,
            status: statusSelect.value,
            payment_status: paymentSelect.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/purchases.php?" + params.toString());
            
            poRows.innerHTML = "";
            if (data && data.success && data.purchases.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("po-table").style.display = "table";
                
                data.purchases.forEach(p => {
                    // Total cost formats
                    const total = parseFloat(p.total_amount);
                    const paid = parseFloat(p.paid_amount);
                    const due = total - paid;
                    
                    // Order status badge
                    let statusBadge = "badge-info";
                    if (p.status === "Received") statusBadge = "badge-success";
                    if (p.status === "Pending") statusBadge = "badge-warning";
                    
                    // Payment status badge
                    let payBadge = "badge-secondary";
                    if (p.payment_status === "Paid") payBadge = "badge-success";
                    if (p.payment_status === "Partial") payBadge = "badge-warning";
                    if (p.payment_status === "Unpaid") payBadge = "badge-danger";
                    
                    poRows.innerHTML += \`
                        <tr>
                            <td><span class="font-semibold text-sm">\${e(p.invoice_number)}</span></td>
                            <td>
                                <div class="font-medium text-sm">\${e(p.supplier_name)}</div>
                                <div class="text-muted text-xs">\${e(p.company_name || "")}</div>
                            </td>
                            <td class="text-sm">\${formatDate(p.order_date)}</td>
                            <td class="text-sm font-semibold">$\${total.toFixed(2)}</td>
                            <td class="text-sm">$\${paid.toFixed(2)}</td>
                            <td class="text-sm font-semibold \${due > 0 ? "text-danger" : "text-muted"}">$\${due.toFixed(2)}</td>
                            <td><span class="badge \${statusBadge}">\${p.status}</span></td>
                            <td><span class="badge \${payBadge}">\${p.payment_status}</span></td>
                            <td style="text-align:right;">
                                <a href="/shop-system/purchases/view.php?id=\${p.id}" class="btn btn-secondary" style="padding:0.4rem; font-size:0.75rem;" title="View Invoice">
                                    <i data-lucide="eye" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                </a>
                            </td>
                        </tr>
                    \`;
                });
                
                if (typeof lucide !== "undefined") lucide.createIcons();
                
                renderPagination(data.total_records, data.page, data.limit, data.total_pages);
            } else {
                document.getElementById("po-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve purchase orders.", "danger");
        }
    }
    
    // Pagination generator
    function renderPagination(total, page, limit, totalPages) {
        const start = (page - 1) * limit + 1;
        const end = Math.min(page * limit, total);
        document.getElementById("pagination-summary").innerText = \`Showing \${start} to \${end} of \${total} entries\`;
        
        const controls = document.getElementById("pagination-controls");
        controls.innerHTML = "";
        
        // Prev button
        const prevBtn = document.createElement("button");
        prevBtn.className = "page-btn";
        prevBtn.disabled = page === 1;
        prevBtn.innerHTML = "<i data-lucide=\'chevron-left\' style=\'width:16px; height:16px;\'></i>";
        prevBtn.addEventListener("click", () => {
            currentPage = page - 1;
            fetchPurchases();
        });
        controls.appendChild(prevBtn);
        
        // Page buttons
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
                const btn = document.createElement("button");
                btn.className = \`page-btn \${i === page ? "active" : ""}\`;
                btn.innerText = i;
                btn.addEventListener("click", () => {
                    currentPage = i;
                    fetchPurchases();
                });
                controls.appendChild(btn);
            }
        }
        
        // Next button
        const nextBtn = document.createElement("button");
        nextBtn.className = "page-btn";
        nextBtn.disabled = page === totalPages;
        nextBtn.innerHTML = "<i data-lucide=\'chevron-right\' style=\'width:16px; height:16px;\'></i>";
        nextBtn.addEventListener("click", () => {
            currentPage = page + 1;
            fetchPurchases();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Helper date formatting
    function formatDate(dateStr) {
        if (!dateStr) return "";
        const parts = dateStr.split("-");
        if (parts.length !== 3) return dateStr;
        const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const year = parts[0];
        const monthIndex = parseInt(parts[1]) - 1;
        const day = parts[2];
        return months[monthIndex] + " " + day + ", " + year;
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
