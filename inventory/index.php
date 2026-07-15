<?php
/**
 * Inventory Stock Levels view page
 */

$page_title = "Inventory Control";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper', 'Accountant']);

// Aggregate overview calculations
$tot_q = mysqli_query($conn, "SELECT COUNT(*), SUM(current_stock * buying_price) FROM products WHERE status = 'Active'");
$totals = mysqli_fetch_row($tot_q);
$total_skus = (int)($totals[0] ?? 0);
$asset_value = (float)($totals[1] ?? 0.00);

$low_q = mysqli_query($conn, "SELECT COUNT(*) FROM products WHERE current_stock <= minimum_stock AND current_stock > 0 AND status = 'Active'");
$low_stock_count = mysqli_fetch_row($low_q)[0] ?? 0;

$out_q = mysqli_query($conn, "SELECT COUNT(*) FROM products WHERE current_stock <= 0 AND status = 'Active'");
$out_stock_count = mysqli_fetch_row($out_q)[0] ?? 0;

$currency = get_setting($conn, 'currency_symbol', '$');
?>

<!-- Statistics Overview Cards -->
<div class="grid grid-cols-1 grid-cols-sm-4 gap-4 mb-6">
    <!-- Active SKUs -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Active Items</span>
            <div class="stats-card-value" id="stats-skus"><?php echo $total_skus; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Distinct catalog products</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(37, 99, 235, 0.15); color: var(--primary);">
            <i data-lucide="package"></i>
        </div>
    </div>
    
    <!-- Asset Value -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Total Asset Value</span>
            <div class="stats-card-value" id="stats-asset-val"><?php echo $currency; ?><?php echo number_format($asset_value, 2); ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Net cost inventory value</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(99, 102, 241, 0.15); color: #6366f1;">
            <i data-lucide="banknote"></i>
        </div>
    </div>
    
    <!-- Low Stock count -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Low Stock Alerts</span>
            <div class="stats-card-value" id="stats-low" style="color: var(--warning);"><?php echo $low_stock_count; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Items below threshold limits</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(245, 158, 11, 0.15); color: var(--warning);">
            <i data-lucide="alert-triangle"></i>
        </div>
    </div>
    
    <!-- Out of Stock count -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Out of Stock</span>
            <div class="stats-card-value" id="stats-out" style="color: var(--danger);"><?php echo $out_stock_count; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Depleted stock catalog rows</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(220, 38, 38, 0.15); color: var(--danger);">
            <i data-lucide="shield-alert"></i>
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
                <input type="text" id="inv-search" class="form-control search-input" placeholder="Search by name or SKU...">
            </div>
            
            <select id="inv-status" class="form-control" style="max-width: 155px; font-size: 0.85rem; padding: 6px 12px; height: auto;">
                <option value="">All Inventory Levels</option>
                <option value="low">Low Stock Alerts</option>
                <option value="out">Out of Stock</option>
            </select>
        </div>
        
        <!-- Movements log link -->
        <div>
            <a href="/shop-system/inventory/movements.php" class="btn btn-secondary d-flex align-center gap-2">
                <i data-lucide="history" style="width:16px; height:16px;"></i> View Movements Audit Trail
            </a>
        </div>
    </div>
</div>

<!-- Datatable Card -->
<div class="card" style="padding: 0; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-hover" id="inv-table">
            <thead>
                <tr>
                    <th>Product Catalog Name</th>
                    <th>Current Stock</th>
                    <th>Min Alert Threshold</th>
                    <th>Unit Cost</th>
                    <th>Retail Price</th>
                    <th>Asset Valuation</th>
                    <th>Status Badge</th>
                    <th style="width: 100px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="inv-rows">
                <!-- Dynamic AJAX rows loaded here -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="inv-empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="package-search" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No products match query</h4>
        <p class="text-muted text-sm">Add active products to view and adjust their inventory levels.</p>
    </div>
    
    <!-- Pagination controls -->
    <div class="d-flex justify-between align-center" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap:wrap; gap: 0.5rem;">
        <span class="text-muted text-xs" id="pagination-summary">Showing 0 to 0 of 0 entries</span>
        <div class="pagination" id="pagination-controls">
            <!-- Dynamic pages -->
        </div>
    </div>
</div>

<!-- MANUAL STOCK ADJUSTMENT MODAL -->
<div class="modal-overlay" id="adjust-modal">
    <div class="modal-window" style="max-width: 420px;">
        <div class="modal-header">
            <h3>Manual Stock Adjustment</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="adjust-form">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="product_id" id="adj-product-id" value="">
            
            <div class="modal-body">
                <div style="background-color: var(--bg-app); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.25rem;">
                    <div class="d-flex justify-between text-sm mb-1">
                        <span class="text-muted">Product Name:</span>
                        <span class="font-semibold" id="adj-product-name">Product Name</span>
                    </div>
                    <div class="d-flex justify-between text-sm">
                        <span class="text-muted">Available Stock:</span>
                        <span class="font-semibold" id="adj-product-stock">0 pcs</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="adjustment_type">Adjustment Mode *</label>
                    <select name="adjustment_type" id="adj-type" class="form-control" required>
                        <option value="Add">Add Stock (+)</option>
                        <option value="Deduct">Deduct Stock (-)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="quantity">Quantity *</label>
                    <input type="number" name="quantity" id="adj-quantity" class="form-control" min="1" step="1" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="reason">Audit Adjustment Reason</label>
                    <select name="reason" id="adj-reason" class="form-control">
                        <option value="Correction">Correction (Inventory Audit count)</option>
                        <option value="Damage">Damage (Broken / Unshelved)</option>
                        <option value="Loss">Loss (Stolen / Misplaced)</option>
                        <option value="Found">Found (Extra stock found)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Adjustment Notes</label>
                    <textarea name="notes" id="adj-notes" class="form-control" rows="3" placeholder="Provide extra description about adjustment details..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="modal-submit-btn">
                    <span>Submit Adjustment</span>
                    <div class="spinner" id="modal-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    let currentPage = 1;
    let pageLimit = 10;
    
    // Fetch initial list
    fetchInventory();
    
    // Bind search and filter
    const searchInput = document.getElementById("inv-search");
    const statusSelect = document.getElementById("inv-status");
    
    let searchTimeout = null;
    searchInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchInventory();
        }, 300);
    });
    
    statusSelect.addEventListener("change", () => {
        currentPage = 1;
        fetchInventory();
    });
    
    // AJAX Fetch Inventory
    async function fetchInventory() {
        const invRows = document.getElementById("inv-rows");
        const emptyState = document.getElementById("inv-empty-state");
        
        invRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            invRows.innerHTML += `
                <tr>
                    <td>
                        <div class="skeleton" style="width:180px; height:16px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:100px; height:12px;"></div>
                    </td>
                    <td><div class="skeleton" style="width:60px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:50px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:70px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:70px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:80px; height:14px;"></div></td>
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
            stock_status: statusSelect.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/inventory.php?" + params.toString());
            
            invRows.innerHTML = "";
            if (data && data.success && data.inventory.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("inv-table").style.display = "table";
                
                data.inventory.forEach(p => {
                    const stock = parseInt(p.current_stock);
                    const limit = parseInt(p.minimum_stock);
                    const cost = parseFloat(p.buying_price);
                    const price = parseFloat(p.selling_price);
                    const assetVal = stock * cost;
                    
                    // Alert badges
                    let alertBadge = `<span class="badge badge-success">Healthy</span>`;
                    if (stock <= limit && stock > 0) alertBadge = `<span class="badge badge-warning">Low Stock</span>`;
                    if (stock <= 0) alertBadge = `<span class="badge badge-danger">Out of Stock</span>`;
                    
                    const actionButton = \`
                        <div class="d-flex justify-end">
                            \${ ${can_edit ? "true" : "false"} ? \`
                                <button class="btn btn-secondary btn-adjust-stock" data-id="\${p.id}" data-name="\${e(p.name)}" data-stock="\${stock}" data-unit="\${e(p.unit)}" style="padding:0.4rem; font-size:0.75rem;" title="Adjust Stock">
                                    <i data-lucide="settings-2" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                </button>
                            \` : \`<span class="text-xs text-muted">No Permissions</span>\` }
                        </div>
                    \`;
                    
                    invRows.innerHTML += \`
                        <tr>
                            <td>
                                <div class="font-semibold text-sm">\${e(p.name)}</div>
                                <div class="text-muted text-xs">SKU: \${e(p.sku)}</div>
                            </td>
                            <td class="text-sm font-semibold">\${stock} \${e(p.unit)}</td>
                            <td class="text-sm">\${limit} \${e(p.unit)}</td>
                            <td class="text-sm">$\${cost.toFixed(2)}</td>
                            <td class="text-sm">$\${price.toFixed(2)}</td>
                            <td class="text-sm font-semibold text-main">$\${assetVal.toFixed(2)}</td>
                            <td>\${alertBadge}</td>
                            <td>\${actionButton}</td>
                        </tr>
                    \`;
                });
                
                if (typeof lucide !== "undefined") lucide.createIcons();
                
                renderPagination(data.total_records, data.page, data.limit, data.total_pages);
                bindRowActions();
            } else {
                document.getElementById("inv-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve inventory list.", "danger");
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
            fetchInventory();
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
                    fetchInventory();
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
            fetchInventory();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Bind Actions
    const modal = document.getElementById("adjust-modal");
    const adjustForm = document.getElementById("adjust-form");
    const submitBtn = document.getElementById("modal-submit-btn");
    const spinner = document.getElementById("modal-spinner");
    
    const closeModal = () => {
        modal.classList.remove("active");
    };
    
    document.getElementById("modal-close").addEventListener("click", closeModal);
    document.getElementById("modal-cancel-btn").addEventListener("click", closeModal);
    
    function bindRowActions() {
        document.querySelectorAll(".btn-adjust-stock").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                const stock = btn.getAttribute("data-stock");
                const unit = btn.getAttribute("data-unit");
                
                document.getElementById("adj-product-id").value = id;
                document.getElementById("adj-product-name").innerText = name;
                document.getElementById("adj-product-stock").innerText = stock + " " + unit;
                
                adjustForm.reset();
                document.getElementById("adj-product-id").value = id;
                
                modal.classList.add("active");
            });
        });
    }
    
    // Handle form submit
    adjustForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        submitBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(adjustForm);
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/inventory.php?action=adjust", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                closeModal();
                fetchInventory();
                
                // Reload page totals dynamically
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(res.message || "Failed to adjust stock.", "danger");
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
        } finally {
            submitBtn.disabled = false;
            spinner.style.display = "none";
        }
    });

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
