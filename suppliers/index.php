<?php
/**
 * Suppliers Directory Listing View Page
 */

$page_title = "Suppliers Directory";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper', 'Accountant']);

// Calculate totals from database
$tot_q = mysqli_query($conn, "SELECT COUNT(*), SUM(credit_balance) FROM suppliers");
$totals = mysqli_fetch_row($tot_q);
$total_suppliers = (int)($totals[0] ?? 0);
$total_due = (float)($totals[1] ?? 0.00);
?>

<!-- Statistics Overview Cards -->
<div class="grid grid-cols-1 grid-cols-sm-2 gap-4 mb-6">
    <!-- Total Suppliers count -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Total Suppliers</span>
            <div class="stats-card-value" id="stats-total-count"><?php echo $total_suppliers; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Active supply partnerships</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(37, 99, 235, 0.15); color: var(--primary);">
            <i data-lucide="truck"></i>
        </div>
    </div>
    
    <!-- Total Outstanding balance -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Total Outstanding Balance</span>
            <div class="stats-card-value" id="stats-total-due" style="color: var(--danger);">$<?php echo number_format($total_due, 2); ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Due payables to suppliers</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(220, 38, 38, 0.15); color: var(--danger);">
            <i data-lucide="wallet"></i>
        </div>
    </div>
</div>

<!-- Header Action Row -->
<div class="d-flex justify-between align-center mb-4" style="flex-wrap: wrap; gap: 1rem;">
    <div class="search-container" style="max-width: 320px; flex: 1;">
        <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
        <input type="text" id="supp-search" class="form-control search-input" placeholder="Search by name, company, email...">
    </div>
    <div>
        <?php if ($can_edit): ?>
            <button class="btn btn-primary" id="btn-add-supplier">
                <i data-lucide="plus" style="width:16px; height:16px;"></i> Add Supplier
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Datatable Card -->
<div class="card" style="padding: 0; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-hover" id="supp-table">
            <thead>
                <tr>
                    <th>Supplier / Company</th>
                    <th>Contact Info</th>
                    <th>Street Address</th>
                    <th>Credit Balance</th>
                    <th style="width: 130px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="supp-rows">
                <!-- Dynamic AJAX rows loaded here -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="supp-empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="users-2" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No suppliers found</h4>
        <p class="text-muted text-sm">Register a new supplier to link them to purchase orders.</p>
    </div>
    
    <!-- Pagination controls -->
    <div class="d-flex justify-between align-center" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap:wrap; gap: 0.5rem;">
        <span class="text-muted text-xs" id="pagination-summary">Showing 0 to 0 of 0 entries</span>
        <div class="pagination" id="pagination-controls">
            <!-- Dynamic pages -->
        </div>
    </div>
</div>

<!-- SUPPLIER ADD/EDIT MODAL -->
<div class="modal-overlay" id="supplier-modal">
    <div class="modal-window" style="max-width: 480px;">
        <div class="modal-header">
            <h3 id="modal-title">Register Supplier</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="supplier-form" autocomplete="off">
            <input type="hidden" name="id" id="supp-id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="name">Contact Name *</label>
                    <input type="text" name="name" id="supp-name" class="form-control" required placeholder="e.g. John Miller">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="company_name">Company Name</label>
                    <input type="text" name="company_name" id="supp-company" class="form-control" placeholder="e.g. Miller Distributors Inc">
                </div>
                
                <div class="grid grid-cols-1 grid-cols-sm-2 gap-2">
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number *</label>
                        <input type="text" name="phone" id="supp-phone" class="form-control" required placeholder="e.g. +1 555-123-4567">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" name="email" id="supp-email" class="form-control" placeholder="e.g. sales@company.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="address">Street Address</label>
                    <textarea name="address" id="supp-address" class="form-control" rows="3" placeholder="e.g. 100 Silicon Valley, California"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="modal-submit-btn">
                    <span id="submit-btn-text">Save Details</span>
                    <div class="spinner" id="modal-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- QUICK PAY MODAL -->
<div class="modal-overlay" id="pay-modal">
    <div class="modal-window" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Record Payment</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="pay-modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="pay-form">
            <input type="hidden" name="id" id="pay-supp-id" value="">
            
            <div class="modal-body">
                <div style="background-color: var(--bg-app); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.25rem;">
                    <div class="d-flex justify-between text-sm mb-1">
                        <span class="text-muted">Supplier:</span>
                        <span class="font-semibold" id="pay-supp-name">Supplier Name</span>
                    </div>
                    <div class="d-flex justify-between text-sm">
                        <span class="text-muted">Outstanding:</span>
                        <span class="font-semibold" id="pay-supp-due" style="color:var(--danger);">$0.00</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="amount">Payment Amount *</label>
                    <input type="number" name="amount" id="pay-amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="payment_method">Payment Method</label>
                    <select name="payment_method" id="pay-method" class="form-control">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="Bank">Bank Wire</option>
                        <option value="Mobile Money">Mobile Money</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Notes / Reference</label>
                    <input type="text" name="notes" id="pay-notes" class="form-control" placeholder="e.g. Paid cash, wire reference number...">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="pay-cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-success d-flex align-center gap-2" id="pay-submit-btn">
                    <span>Submit Payment</span>
                    <div class="spinner" id="pay-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
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
    let editMode = false;
    
    // Fetch initial list
    fetchSuppliers();
    
    // Bind search keyup
    const searchInput = document.getElementById("supp-search");
    let searchTimeout = null;
    searchInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchSuppliers();
        }, 300);
    });
    
    // AJAX Fetch Suppliers
    async function fetchSuppliers() {
        const suppRows = document.getElementById("supp-rows");
        const emptyState = document.getElementById("supp-empty-state");
        
        suppRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            suppRows.innerHTML += `
                <tr>
                    <td>
                        <div class="skeleton" style="width:150px; height:16px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:90px; height:12px;"></div>
                    </td>
                    <td>
                        <div class="skeleton" style="width:110px; height:14px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:80px; height:12px;"></div>
                    </td>
                    <td><div class="skeleton" style="width:140px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:60px; height:16px;"></div></td>
                    <td style="text-align:right;"><div class="skeleton" style="width:120px; height:28px; margin-left:auto; border-radius:6px;"></div></td>
                </tr>
            `;
        }
        
        const params = new URLSearchParams({
            action: "list",
            page: currentPage,
            limit: pageLimit,
            search: searchInput.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/suppliers.php?" + params.toString());
            
            suppRows.innerHTML = "";
            if (data && data.success && data.suppliers.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("supp-table").style.display = "table";
                
                data.suppliers.forEach(s => {
                    // Credit balance class
                    const due = parseFloat(s.credit_balance);
                    const dueText = due > 0 
                        ? `<span class="font-semibold" style="color:var(--danger);">$` + due.toFixed(2) + `</span>`
                        : `<span class="text-muted">$0.00</span>`;
                        
                    // Quick pay button if outstanding balance exists
                    const payBtn = due > 0 && ${can_edit ? "true" : "false"}
                        ? `<button class="btn btn-secondary btn-pay-supp" data-id="\${s.id}" data-name="\${e(s.name)}" data-due="\${due}" style="padding:0.4rem; font-size:0.75rem; color:var(--success); border-color:rgba(22,163,74,0.3); background-color:rgba(22,163,74,0.05);" title="Pay Balance">
                               <i data-lucide="hand-coins" style="width:14px; height:14px;"></i>
                           </button>`
                        : "";
                        
                    const actionButtons = \`
                        <div class="d-flex justify-end gap-1">
                            <a href="/shop-system/suppliers/view.php?id=\${s.id}" class="btn btn-secondary" style="padding:0.4rem; font-size:0.75rem;" title="View Purchase Profile">
                                <i data-lucide="eye" style="width:14px; height:14px; color:var(--text-muted);"></i>
                            </a>
                            \${payBtn}
                            \${ ${can_edit ? "true" : "false"} ? \`
                                <button class="btn btn-secondary btn-edit-supp" data-id="\${s.id}" data-name="\${e(s.name)}" data-company="\${e(s.company_name)}" data-phone="\${e(s.phone)}" data-email="\${e(s.email)}" data-address="\${e(s.address)}" style="padding:0.4rem; font-size:0.75rem;" title="Edit Supplier">
                                    <i data-lucide="edit-3" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                </button>
                                <button class="btn btn-secondary btn-delete-supp" data-id="\${s.id}" data-name="\${e(s.name)}" style="padding:0.4rem; font-size:0.75rem;" title="Delete Supplier">
                                    <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i>
                                </button>
                            \` : "" }
                        </div>
                    \`;
                    
                    suppRows.innerHTML += \`
                        <tr>
                            <td>
                                <div class="font-semibold text-sm">\${e(s.name)}</div>
                                <div class="text-muted text-xs">\${e(s.company_name || "No company name")}</div>
                            </td>
                            <td>
                                <div class="text-sm">\${e(s.phone)}</div>
                                <div class="text-muted text-xs">\${e(s.email || "No email")}</div>
                            </td>
                            <td class="text-sm">\${e(s.address || "N/A")}</td>
                            <td class="text-sm">\${dueText}</td>
                            <td>
                                \${actionButtons}
                            </td>
                        </tr>
                    \`;
                });
                
                if (typeof lucide !== "undefined") lucide.createIcons();
                
                renderPagination(data.total_records, data.page, data.limit, data.total_pages);
                bindRowActions();
            } else {
                document.getElementById("supp-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve suppliers directory.", "danger");
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
            fetchSuppliers();
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
                    fetchSuppliers();
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
            fetchSuppliers();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Bind Row Actions
    function bindRowActions() {
        // Edit Trigger
        document.querySelectorAll(".btn-edit-supp").forEach(btn => {
            btn.addEventListener("click", () => {
                editMode = true;
                const id = btn.getAttribute("data-id");
                
                document.getElementById("supp-id").value = id;
                document.getElementById("supp-name").value = btn.getAttribute("data-name");
                document.getElementById("supp-company").value = btn.getAttribute("data-company");
                document.getElementById("supp-phone").value = btn.getAttribute("data-phone");
                document.getElementById("supp-email").value = btn.getAttribute("data-email");
                document.getElementById("supp-address").value = btn.getAttribute("data-address");
                
                document.getElementById("modal-title").innerText = "Edit Supplier Details";
                document.getElementById("submit-btn-text").innerText = "Save Changes";
                
                document.getElementById("supplier-modal").classList.add("active");
            });
        });
        
        // Delete Trigger
        document.querySelectorAll(".btn-delete-supp").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                
                showConfirmModal(
                    "Delete Supplier",
                    \`Are you sure you want to delete supplier "\${name}"? This operation cannot be undone and will fail if the supplier has transaction logs.\`,
                    async () => {
                        const fd = new FormData();
                        fd.append("id", id);
                        
                        const res = await ajaxRequest("/shop-system/ajax/suppliers.php?action=delete", {
                            method: "POST",
                            body: fd
                        });
                        
                        if (res && res.success) {
                            showToast(res.message, "success");
                            fetchSuppliers();
                        } else {
                            showToast(res.message || "Failed to delete supplier.", "danger");
                        }
                    }
                );
            });
        });
        
        // Pay Balance Quick Trigger
        document.querySelectorAll(".btn-pay-supp").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                const due = parseFloat(btn.getAttribute("data-due"));
                
                document.getElementById("pay-supp-id").value = id;
                document.getElementById("pay-supp-name").innerText = name;
                document.getElementById("pay-supp-due").innerText = "$" + due.toFixed(2);
                document.getElementById("pay-amount").value = due.toFixed(2);
                document.getElementById("pay-amount").max = due.toFixed(2);
                
                document.getElementById("pay-form").reset();
                document.getElementById("pay-supp-id").value = id;
                document.getElementById("pay-amount").value = due.toFixed(2);
                
                document.getElementById("pay-modal").classList.add("active");
            });
        });
    }
    
    // --- SUPPLIER ADD/EDIT MODAL ---
    const suppModal = document.getElementById("supplier-modal");
    const suppForm = document.getElementById("supplier-form");
    const suppSubmitBtn = document.getElementById("modal-submit-btn");
    const suppSpinner = document.getElementById("modal-spinner");
    
    document.getElementById("btn-add-supplier")?.addEventListener("click", () => {
        editMode = false;
        suppForm.reset();
        document.getElementById("supp-id").value = "";
        document.getElementById("modal-title").innerText = "Register Supplier";
        document.getElementById("submit-btn-text").innerText = "Save Details";
        
        suppModal.classList.add("active");
    });
    
    const closeSuppModal = () => {
        suppModal.classList.remove("active");
    };
    document.getElementById("modal-close").addEventListener("click", closeSuppModal);
    document.getElementById("modal-cancel-btn").addEventListener("click", closeSuppModal);
    
    suppForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        suppSubmitBtn.disabled = true;
        suppSpinner.style.display = "inline-block";
        
        const fd = new FormData(suppForm);
        const actionQuery = editMode ? "edit" : "add";
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/suppliers.php?action=" + actionQuery, {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                closeSuppModal();
                fetchSuppliers();
            } else {
                showToast(res.message || "Operation failed.", "danger");
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
        } finally {
            suppSubmitBtn.disabled = false;
            suppSpinner.style.display = "none";
        }
    });
    
    // --- PAY BALANCE MODAL ---
    const payModal = document.getElementById("pay-modal");
    const payForm = document.getElementById("pay-form");
    const paySubmitBtn = document.getElementById("pay-submit-btn");
    const paySpinner = document.getElementById("pay-spinner");
    
    const closePayModal = () => {
        payModal.classList.remove("active");
    };
    document.getElementById("pay-modal-close").addEventListener("click", closePayModal);
    document.getElementById("pay-cancel-btn").addEventListener("click", closePayModal);
    
    payForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        paySubmitBtn.disabled = true;
        paySpinner.style.display = "inline-block";
        
        const fd = new FormData(payForm);
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/suppliers.php?action=pay_balance", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                closePayModal();
                fetchSuppliers();
            } else {
                showToast(res.message || "Failed to record payment.", "danger");
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
        } finally {
            paySubmitBtn.disabled = false;
            paySpinner.style.display = "none";
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
