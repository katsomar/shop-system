<?php
/**
 * Customers Directory Listing View Page
 */

$page_title = "Customers Directory";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$can_edit = has_role(['Administrator', 'Manager', 'Cashier', 'Accountant']);

// Calculate totals from database
$tot_q = mysqli_query($conn, "SELECT COUNT(*), SUM(credit_balance) FROM customers");
$totals = mysqli_fetch_row($tot_q);
$total_customers = (int)($totals[0] ?? 0);
$total_due = (float)($totals[1] ?? 0.00);
?>

<!-- Statistics Overview Cards -->
<div class="grid grid-cols-1 grid-cols-sm-2 gap-4 mb-6">
    <!-- Total Customers count -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Total Customers</span>
            <div class="stats-card-value" id="stats-total-count"><?php echo $total_customers; ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Registered buyer profiles</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(37, 99, 235, 0.15); color: var(--primary);">
            <i data-lucide="users-2"></i>
        </div>
    </div>
    
    <!-- Total Outstanding credit balance -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Outstanding Customer Credit</span>
            <div class="stats-card-value" id="stats-total-due" style="color: var(--danger);">$<?php echo number_format($total_due, 2); ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Client debt receivables</span>
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
        <input type="text" id="cust-search" class="form-control search-input" placeholder="Search by name, phone, email...">
    </div>
    <div>
        <?php if ($can_edit): ?>
            <button class="btn btn-primary" id="btn-add-customer">
                <i data-lucide="plus" style="width:16px; height:16px;"></i> Add Customer
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Datatable Card -->
<div class="card" style="padding: 0; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-hover" id="cust-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Contact Phone</th>
                    <th>Email Address</th>
                    <th>Billing Address</th>
                    <th>Credit Balance</th>
                    <th style="width: 130px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="cust-rows">
                <!-- Dynamic AJAX rows loaded here -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="cust-empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="users" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No customers found</h4>
        <p class="text-muted text-sm">Register a new customer profile to manage credit accounts.</p>
    </div>
    
    <!-- Pagination controls -->
    <div class="d-flex justify-between align-center" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap:wrap; gap: 0.5rem;">
        <span class="text-muted text-xs" id="pagination-summary">Showing 0 to 0 of 0 entries</span>
        <div class="pagination" id="pagination-controls">
            <!-- Dynamic pages -->
        </div>
    </div>
</div>

<!-- CUSTOMER ADD/EDIT MODAL -->
<div class="modal-overlay" id="customer-modal">
    <div class="modal-window" style="max-width: 450px;">
        <div class="modal-header">
            <h3 id="modal-title">Register Customer</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="customer-form" autocomplete="off">
            <input type="hidden" name="id" id="cust-id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="name">Customer Full Name *</label>
                    <input type="text" name="name" id="cust-name" class="form-control" required placeholder="e.g. John Doe">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="text" name="phone" id="cust-phone" class="form-control" placeholder="e.g. +1 555-444-1111">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" name="email" id="cust-email" class="form-control" placeholder="e.g. client@domain.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="address">Billing Address</label>
                    <textarea name="address" id="cust-address" class="form-control" rows="3" placeholder="e.g. 50 Main Road, New York"></textarea>
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
            <h3>Record Credit Payment</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="pay-modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="pay-form">
            <input type="hidden" name="id" id="pay-cust-id" value="">
            
            <div class="modal-body">
                <div style="background-color: var(--bg-app); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.25rem;">
                    <div class="d-flex justify-between text-sm mb-1">
                        <span class="text-muted">Customer:</span>
                        <span class="font-semibold" id="pay-cust-name">Customer Name</span>
                    </div>
                    <div class="d-flex justify-between text-sm">
                        <span class="text-muted">Total Balance:</span>
                        <span class="font-semibold" id="pay-cust-due" style="color:var(--danger);">$0.00</span>
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
                    <input type="text" name="notes" id="pay-notes" class="form-control" placeholder="e.g. Settled cash, check reference number...">
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
    fetchCustomers();
    
    // Bind search keyup
    const searchInput = document.getElementById("cust-search");
    let searchTimeout = null;
    searchInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchCustomers();
        }, 300);
    });
    
    // AJAX Fetch Customers
    async function fetchCustomers() {
        const custRows = document.getElementById("cust-rows");
        const emptyState = document.getElementById("cust-empty-state");
        
        custRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            custRows.innerHTML += `
                <tr>
                    <td><div class="skeleton" style="width:140px; height:16px;"></div></td>
                    <td><div class="skeleton" style="width:100px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:120px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:150px; height:14px;"></div></td>
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
            const data = await ajaxRequest("/shop-system/ajax/customers.php?" + params.toString());
            
            custRows.innerHTML = "";
            if (data && data.success && data.customers.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("cust-table").style.display = "table";
                
                data.customers.forEach(c => {
                    const due = parseFloat(c.credit_balance);
                    const dueText = due > 0 
                        ? `<span class="font-semibold" style="color:var(--danger);">$` + due.toFixed(2) + `</span>`
                        : `<span class="text-muted">$0.00</span>`;
                        
                    // Quick pay button if outstanding balance exists
                    const payBtn = due > 0 && ${can_edit ? "true" : "false"}
                        ? `<button class="btn btn-secondary btn-pay-cust" data-id="\${c.id}" data-name="\${e(c.name)}" data-due="\${due}" style="padding:0.4rem; font-size:0.75rem; color:var(--success); border-color:rgba(22,163,74,0.3); background-color:rgba(22,163,74,0.05);" title="Pay Balance">
                               <i data-lucide="hand-coins" style="width:14px; height:14px;"></i>
                           </button>`
                        : "";
                        
                    const actionButtons = \`
                        <div class="d-flex justify-end gap-1">
                            <a href="/shop-system/customers/view.php?id=\${c.id}" class="btn btn-secondary" style="padding:0.4rem; font-size:0.75rem;" title="View Profile details">
                                <i data-lucide="eye" style="width:14px; height:14px; color:var(--text-muted);"></i>
                            </a>
                            \${payBtn}
                            \${ ${can_edit ? "true" : "false"} ? \`
                                <button class="btn btn-secondary btn-edit-cust" data-id="\${c.id}" data-name="\${e(c.name)}" data-phone="\${e(c.phone)}" data-email="\${e(c.email)}" data-address="\${e(c.address)}" style="padding:0.4rem; font-size:0.75rem;" title="Edit Customer">
                                    <i data-lucide="edit-3" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                </button>
                                <button class="btn btn-secondary btn-delete-cust" data-id="\${c.id}" data-name="\${e(c.name)}" style="padding:0.4rem; font-size:0.75rem;" title="Delete Customer">
                                    <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i>
                                </button>
                            \` : "" }
                        </div>
                    \`;
                    
                    custRows.innerHTML += \`
                        <tr>
                            <td><div class="font-semibold text-sm">\${e(c.name)}</div></td>
                            <td class="text-sm">\${e(c.phone || "N/A")}</td>
                            <td class="text-sm">\${e(c.email || "N/A")}</td>
                            <td class="text-sm">\${e(c.address || "N/A")}</td>
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
                document.getElementById("cust-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve customers directory.", "danger");
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
            fetchCustomers();
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
                    fetchCustomers();
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
            fetchCustomers();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Bind Row Actions
    function bindRowActions() {
        // Edit Trigger
        document.querySelectorAll(".btn-edit-cust").forEach(btn => {
            btn.addEventListener("click", () => {
                editMode = true;
                const id = btn.getAttribute("data-id");
                
                document.getElementById("cust-id").value = id;
                document.getElementById("cust-name").value = btn.getAttribute("data-name");
                document.getElementById("cust-phone").value = btn.getAttribute("data-phone");
                document.getElementById("cust-email").value = btn.getAttribute("data-email");
                document.getElementById("cust-address").value = btn.getAttribute("data-address");
                
                document.getElementById("modal-title").innerText = "Edit Customer Details";
                document.getElementById("submit-btn-text").innerText = "Save Changes";
                
                document.getElementById("customer-modal").classList.add("active");
            });
        });
        
        // Delete Trigger
        document.querySelectorAll(".btn-delete-cust").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                
                showConfirmModal(
                    "Delete Customer",
                    \`Are you sure you want to delete customer "\${name}"? This operation cannot be undone and will fail if they have sales invoice logs.\`,
                    async () => {
                        const fd = new FormData();
                        fd.append("id", id);
                        
                        const res = await ajaxRequest("/shop-system/ajax/customers.php?action=delete", {
                            method: "POST",
                            body: fd
                        });
                        
                        if (res && res.success) {
                            showToast(res.message, "success");
                            fetchCustomers();
                        } else {
                            showToast(res.message || "Failed to delete customer.", "danger");
                        }
                    }
                );
            });
        });
        
        // Pay Balance Quick Trigger
        document.querySelectorAll(".btn-pay-cust").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                const due = parseFloat(btn.getAttribute("data-due"));
                
                document.getElementById("pay-cust-id").value = id;
                document.getElementById("pay-cust-name").innerText = name;
                document.getElementById("pay-cust-due").innerText = "$" + due.toFixed(2);
                document.getElementById("pay-amount").value = due.toFixed(2);
                document.getElementById("pay-amount").max = due.toFixed(2);
                
                document.getElementById("pay-form").reset();
                document.getElementById("pay-cust-id").value = id;
                document.getElementById("pay-amount").value = due.toFixed(2);
                
                document.getElementById("pay-modal").classList.add("active");
            });
        });
    }
    
    // --- CUSTOMER ADD/EDIT MODAL ---
    const custModal = document.getElementById("customer-modal");
    const custForm = document.getElementById("customer-form");
    const custSubmitBtn = document.getElementById("modal-submit-btn");
    const custSpinner = document.getElementById("modal-spinner");
    
    document.getElementById("btn-add-customer")?.addEventListener("click", () => {
        editMode = false;
        custForm.reset();
        document.getElementById("cust-id").value = "";
        document.getElementById("modal-title").innerText = "Register Customer";
        document.getElementById("submit-btn-text").innerText = "Save Details";
        
        custModal.classList.add("active");
    });
    
    const closeCustModal = () => {
        custModal.classList.remove("active");
    };
    document.getElementById("modal-close").addEventListener("click", closeCustModal);
    document.getElementById("modal-cancel-btn").addEventListener("click", closeCustModal);
    
    custForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        custSubmitBtn.disabled = true;
        custSpinner.style.display = "inline-block";
        
        const fd = new FormData(custForm);
        const actionQuery = editMode ? "edit" : "add";
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/customers.php?action=" + actionQuery, {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                closeCustModal();
                fetchCustomers();
            } else {
                showToast(res.message || "Operation failed.", "danger");
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
        } finally {
            custSubmitBtn.disabled = false;
            custSpinner.style.display = "none";
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
            const res = await ajaxRequest("/shop-system/ajax/customers.php?action=pay_balance", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                closePayModal();
                fetchCustomers();
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
