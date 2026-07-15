<?php
/**
 * Inventory movements audit ledger log view page
 */

$page_title = "Inventory movements Audit Trail";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Description Header -->
<div class="mb-4">
    <p class="text-muted text-sm">Review the complete audit log of all stock fluctuations. All automated transaction checkouts, purchase order receipts, and manual adjustments are captured below.</p>
</div>

<!-- Header Action Row -->
<div class="card mb-4" style="padding: 1.25rem;">
    <div class="d-flex justify-between align-center flex-wrap gap-4">
        
        <!-- Filters panel -->
        <div class="d-flex align-center flex-wrap gap-2" style="flex: 1; min-width: 280px;">
            <div class="search-container" style="max-width: 260px; flex: 1;">
                <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
                <input type="text" id="mov-search" class="form-control search-input" placeholder="Search product, SKU, user...">
            </div>
            
            <select id="mov-type" class="form-control" style="max-width: 160px; font-size: 0.85rem; padding: 6px 12px; height: auto;">
                <option value="">All Movement Types</option>
                <option value="Purchase">Purchase Orders</option>
                <option value="Sale">POS Sales</option>
                <option value="Adjustment">Adjustments</option>
                <option value="Damage">Damage Logs</option>
                <option value="Loss">Loss Logs</option>
                <option value="Return">Returns</option>
            </select>
        </div>
        
        <!-- Return back button -->
        <div>
            <a href="/shop-system/inventory/index.php" class="btn btn-secondary d-flex align-center gap-2">
                <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Back to Inventory Control
            </a>
        </div>
    </div>
</div>

<!-- Datatable Card -->
<div class="card" style="padding: 0; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-hover" id="mov-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Product details</th>
                    <th>Movement Type</th>
                    <th>Qty Adjusted</th>
                    <th>Audit Details / Reference</th>
                    <th style="width: 140px;">Handled By</th>
                </tr>
            </thead>
            <tbody id="mov-rows">
                <!-- Dynamic AJAX rows populated here -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="mov-empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="history" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No movements logged</h4>
        <p class="text-muted text-sm">Inventory stock changes will create audit logs dynamically here.</p>
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
    let pageLimit = 15;
    
    // Fetch initial list
    fetchMovements();
    
    // Bind search and filter
    const searchInput = document.getElementById("mov-search");
    const typeSelect = document.getElementById("mov-type");
    
    let searchTimeout = null;
    searchInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchMovements();
        }, 300);
    });
    
    typeSelect.addEventListener("change", () => {
        currentPage = 1;
        fetchMovements();
    });
    
    // AJAX Fetch movements
    async function fetchMovements() {
        const movRows = document.getElementById("mov-rows");
        const emptyState = document.getElementById("mov-empty-state");
        
        movRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            movRows.innerHTML += `
                <tr>
                    <td><div class="skeleton" style="width:120px; height:14px;"></div></td>
                    <td>
                        <div class="skeleton" style="width:150px; height:16px; margin-bottom:4px;"></div>
                        <div class="skeleton" style="width:90px; height:12px;"></div>
                    </td>
                    <td><div class="skeleton" style="width:80px; height:20px; border-radius:50px;"></div></td>
                    <td><div class="skeleton" style="width:50px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:200px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:95px; height:14px;"></div></td>
                </tr>
            `;
        }
        
        const params = new URLSearchParams({
            action: "movements",
            page: currentPage,
            limit: pageLimit,
            search: searchInput.value,
            movement_type: typeSelect.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/inventory.php?" + params.toString());
            
            movRows.innerHTML = "";
            if (data && data.success && data.movements.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("mov-table").style.display = "table";
                
                data.movements.forEach(m => {
                    const qty = parseInt(m.quantity);
                    const qtyFormatted = qty > 0 
                        ? `<span class="font-semibold" style="color:var(--success);">+\${qty} \${e(m.product_unit)}</span>`
                        : `<span class="font-semibold" style="color:var(--danger);">\${qty} \${e(m.product_unit)}</span>`;
                        
                    // Movement type badges
                    let typeBadge = "badge-secondary";
                    if (m.movement_type === "Purchase") typeBadge = "badge-success";
                    if (m.movement_type === "Sale") typeBadge = "badge-info";
                    if (m.movement_type === "Damage") typeBadge = "badge-danger";
                    if (m.movement_type === "Loss") typeBadge = "badge-danger";
                    if (m.movement_type === "Return") typeBadge = "badge-warning";
                    if (m.movement_type === "Adjustment") typeBadge = "badge-primary";
                    
                    movRows.innerHTML += \`
                        <tr>
                            <td class="text-sm text-muted">\${formatDateTime(m.created_at)}</td>
                            <td>
                                <div class="font-semibold text-sm">\${e(m.product_name)}</div>
                                <div class="text-muted text-xs">SKU: \${e(m.product_sku)}</div>
                            </td>
                            <td><span class="badge \${typeBadge}">\${m.movement_type}</span></td>
                            <td class="text-sm">\${qtyFormatted}</td>
                            <td class="text-sm">\${e(m.description || "N/A")}</td>
                            <td><div class="font-medium text-xs">@\${e(m.handler_name)}</div></td>
                        </tr>
                    \`;
                });
                
                renderPagination(data.total_records, data.page, data.limit, data.total_pages);
            } else {
                document.getElementById("mov-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve inventory movements log.", "danger");
        }
    }
    
    // Pagination generator
    function renderPagination(total, page, limit, totalPages) {
        const start = (page - 1) * limit + 1;
        const end = Math.min(page * limit, total);
        document.getElementById("pagination-summary").innerText = \`Showing \${start} to \${end} of \${total} entries\`;
        
        const controls = document.getElementById("pagination-controls");
        controls.innerHTML = "";
        
        // Prev
        const prevBtn = document.createElement("button");
        prevBtn.className = "page-btn";
        prevBtn.disabled = page === 1;
        prevBtn.innerHTML = "<i data-lucide=\'chevron-left\' style=\'width:16px; height:16px;\'></i>";
        prevBtn.addEventListener("click", () => {
            currentPage = page - 1;
            fetchMovements();
        });
        controls.appendChild(prevBtn);
        
        // Pages
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
                const btn = document.createElement("button");
                btn.className = \`page-btn \${i === page ? "active" : ""}\`;
                btn.innerText = i;
                btn.addEventListener("click", () => {
                    currentPage = i;
                    fetchMovements();
                });
                controls.appendChild(btn);
            }
        }
        
        // Next
        const nextBtn = document.createElement("button");
        nextBtn.className = "page-btn";
        nextBtn.disabled = page === totalPages;
        nextBtn.innerHTML = "<i data-lucide=\'chevron-right\' style=\'width:16px; height:16px;\'></i>";
        nextBtn.addEventListener("click", () => {
            currentPage = page + 1;
            fetchMovements();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Format Date Time
    function formatDateTime(dateTimeStr) {
        if (!dateTimeStr) return "";
        const parts = dateTimeStr.split(" ");
        if (parts.length !== 2) return dateTimeStr;
        
        const dateParts = parts[0].split("-");
        const timeParts = parts[1].split(":");
        if (dateParts.length !== 3 || timeParts.length !== 3) return dateTimeStr;
        
        const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const year = dateParts[0];
        const monthIndex = parseInt(dateParts[1]) - 1;
        const day = dateParts[2];
        
        const hour = timeParts[0];
        const minute = timeParts[1];
        
        return months[monthIndex] + " " + day + ", " + year + " " + hour + ":" + minute;
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
