<?php
/**
 * Expenses Directory Listing View Page
 */

$page_title = "Operational Expenses";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$can_edit = has_role(['Administrator', 'Manager', 'Accountant']);

// Calculate totals from database
$monthly_q = mysqli_query($conn, "SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE())");
$monthly_total = (float)(mysqli_fetch_row($monthly_q)[0] ?? 0.00);

$yearly_q = mysqli_query($conn, "SELECT SUM(amount) FROM expenses WHERE YEAR(expense_date) = YEAR(CURRENT_DATE())");
$yearly_total = (float)(mysqli_fetch_row($yearly_q)[0] ?? 0.00);

// Fetch categories for select dropdown
$cat_q = mysqli_query($conn, "SELECT id, name FROM expense_categories ORDER BY name ASC");
$categories = [];
if ($cat_q) {
    while ($row = mysqli_fetch_assoc($cat_q)) {
        $categories[] = $row;
    }
}

$currency = get_setting($conn, 'currency_symbol', '$');
?>

<!-- Statistics Overview Cards -->
<div class="grid grid-cols-1 grid-cols-sm-2 gap-4 mb-6">
    <!-- Monthly Expenses -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Expenses (Current Month)</span>
            <div class="stats-card-value" id="stats-monthly" style="color:var(--danger);"><?php echo $currency; ?><?php echo number_format($monthly_total, 2); ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Operational cost outflow this month</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(220, 38, 38, 0.15); color: var(--danger);">
            <i data-lucide="wallet"></i>
        </div>
    </div>
    
    <!-- Yearly Expenses -->
    <div class="card stats-card">
        <div>
            <span class="text-muted text-xs font-semibold uppercase">Expenses (Current Year)</span>
            <div class="stats-card-value" id="stats-yearly"><?php echo $currency; ?><?php echo number_format($yearly_total, 2); ?></div>
            <span class="text-xs text-muted mt-4" style="display:block;">Cumulative annual outlays</span>
        </div>
        <div class="stats-card-icon" style="background-color: rgba(37, 99, 235, 0.15); color: var(--primary);">
            <i data-lucide="calculator"></i>
        </div>
    </div>
</div>

<!-- Header Action Row -->
<div class="card mb-4" style="padding: 1.25rem;">
    <div class="d-flex justify-between align-center flex-wrap gap-4">
        <!-- Filters panel -->
        <div class="d-flex align-center flex-wrap gap-2" style="flex: 1; min-width: 280px;">
            <div class="search-container" style="max-width: 220px; flex: 1;">
                <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
                <input type="text" id="ex-search" class="form-control search-input" placeholder="Search ref or notes...">
            </div>
            
            <select id="ex-category" class="form-control" style="max-width: 140px; font-size: 0.85rem; padding: 6px 12px; height: auto;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <select id="ex-payment" class="form-control" style="max-width: 135px; font-size: 0.85rem; padding: 6px 12px; height: auto;">
                <option value="">All Payments</option>
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="Bank">Bank Wire</option>
                <option value="Mobile Money">Mobile Money</option>
            </select>
        </div>
        
        <div class="d-flex gap-2">
            <a href="/shop-system/expenses/categories.php" class="btn btn-secondary d-flex align-center gap-2">
                <i data-lucide="folder-tree" style="width:16px; height:16px;"></i> Expense Categories
            </a>
            <?php if ($can_edit): ?>
                <button class="btn btn-primary d-flex align-center gap-2" id="btn-add-expense">
                    <i data-lucide="plus" style="width:16px; height:16px;"></i> Log Expense
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Datatable Card -->
<div class="card" style="padding: 0; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-hover" id="ex-table">
            <thead>
                <tr>
                    <th>Date Logged</th>
                    <th>Expense Category</th>
                    <th>Reference Code</th>
                    <th>Cost Amount</th>
                    <th>Payment Method</th>
                    <th>Handler User</th>
                    <th style="width: 100px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="ex-rows">
                <!-- Dynamic AJAX rows populated here -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="ex-empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="wallet-cards" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No expenses logged</h4>
        <p class="text-muted text-sm">Log a new operational expense to track business outlays.</p>
    </div>
    
    <!-- Pagination controls -->
    <div class="d-flex justify-between align-center" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap:wrap; gap: 0.5rem;">
        <span class="text-muted text-xs" id="pagination-summary">Showing 0 to 0 of 0 entries</span>
        <div class="pagination" id="pagination-controls">
            <!-- Dynamic pages -->
        </div>
    </div>
</div>

<!-- EXPENSE ADD/EDIT MODAL -->
<div class="modal-overlay" id="expense-modal">
    <div class="modal-window" style="max-width: 450px;">
        <div class="modal-header">
            <h3 id="modal-title">Log Expense</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="expense-form" autocomplete="off">
            <input type="hidden" name="id" id="ex-id" value="">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="category_id">Expense Category *</label>
                    <select name="category_id" id="ex-category-select" class="form-control" required>
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-2">
                    <div class="form-group">
                        <label class="form-label" for="amount">Amount *</label>
                        <input type="number" name="amount" id="ex-amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="expense_date">Date *</label>
                        <input type="date" name="expense_date" id="ex-date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-2">
                    <div class="form-group">
                        <label class="form-label" for="reference_no">Reference / Invoice #</label>
                        <input type="text" name="reference_no" id="ex-ref" class="form-control" placeholder="e.g. INV-10292">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="payment_method">Payment Method</label>
                        <select name="payment_method" id="ex-method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Bank">Bank Wire</option>
                            <option value="Mobile Money">Mobile Money</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Notes / Details</label>
                    <textarea name="notes" id="ex-notes" class="form-control" rows="3" placeholder="Brief details about this expense..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="modal-submit-btn">
                    <span id="submit-btn-text">Log Expense</span>
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
    let editMode = false;
    
    // Fetch initial list
    fetchExpenses();
    
    // Bind search and filter events
    const searchInput = document.getElementById("ex-search");
    const categorySelect = document.getElementById("ex-category");
    const paymentSelect = document.getElementById("ex-payment");
    
    let searchTimeout = null;
    searchInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchExpenses();
        }, 300);
    });
    
    categorySelect.addEventListener("change", () => { currentPage = 1; fetchExpenses(); });
    paymentSelect.addEventListener("change", () => { currentPage = 1; fetchExpenses(); });
    
    // AJAX Fetch Expenses
    async function fetchExpenses() {
        const exRows = document.getElementById("ex-rows");
        const emptyState = document.getElementById("ex-empty-state");
        
        exRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            exRows.innerHTML += `
                <tr>
                    <td><div class="skeleton" style="width:100px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:80px; height:20px; border-radius:50px;"></div></td>
                    <td><div class="skeleton" style="width:110px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:70px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:90px; height:14px;"></div></td>
                    <td><div class="skeleton" style="width:80px; height:14px;"></div></td>
                    <td style="text-align:right;"><div class="skeleton" style="width:80px; height:28px; margin-left:auto; border-radius:6px;"></div></td>
                </tr>
            `;
        }
        
        const params = new URLSearchParams({
            action: "list",
            page: currentPage,
            limit: pageLimit,
            search: searchInput.value,
            category_id: categorySelect.value,
            payment_method: paymentSelect.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/expenses.php?" + params.toString());
            
            exRows.innerHTML = "";
            if (data && data.success && data.expenses.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("ex-table").style.display = "table";
                
                data.expenses.forEach(ex => {
                    const amount = parseFloat(ex.amount);
                    
                    const actionButtons = \`
                        <div class="d-flex justify-end gap-1">
                            \${ ${can_edit ? "true" : "false"} ? \`
                                <button class="btn btn-secondary btn-edit-ex" data-id="\${ex.id}" data-category="\${ex.category_id}" data-amount="\${amount}" data-date="\${ex.expense_date}" data-ref="\${e(ex.reference_no)}" data-method="\${e(ex.payment_method)}" data-notes="\${e(ex.notes)}" style="padding:0.4rem; font-size:0.75rem;" title="Edit Expense">
                                    <i data-lucide="edit-3" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                </button>
                                <button class="btn btn-secondary btn-delete-ex" data-id="\${ex.id}" data-ref="\${e(ex.reference_no)}" style="padding:0.4rem; font-size:0.75rem;" title="Delete Expense">
                                    <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i>
                                </button>
                            \` : \`<span class="text-xs text-muted">No Permissions</span>\` }
                        </div>
                    \`;
                    
                    exRows.innerHTML += \`
                        <tr>
                            <td class="text-sm">\${formatDate(ex.expense_date)}</td>
                            <td><span class="badge badge-primary">\${e(ex.category_name)}</span></td>
                            <td><span class="font-semibold text-xs">\${e(ex.reference_no || "N/A")}</span></td>
                            <td class="text-sm font-semibold text-danger">$\${amount.toFixed(2)}</td>
                            <td class="text-sm">\${e(ex.payment_method)}</td>
                            <td><div class="text-xs">@\${e(ex.handler_name)}</div></td>
                            <td>\${actionButtons}</td>
                        </tr>
                    \`;
                });
                
                if (typeof lucide !== "undefined") lucide.createIcons();
                
                renderPagination(data.total_records, data.page, data.limit, data.total_pages);
                bindRowActions();
            } else {
                document.getElementById("ex-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve operational expenses.", "danger");
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
            fetchExpenses();
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
                    fetchExpenses();
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
            fetchExpenses();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Bind Actions
    const modal = document.getElementById("expense-modal");
    const exForm = document.getElementById("expense-form");
    const submitBtn = document.getElementById("modal-submit-btn");
    const spinner = document.getElementById("modal-spinner");
    
    const closeExModal = () => {
        modal.classList.remove("active");
    };
    document.getElementById("modal-close").addEventListener("click", closeExModal);
    document.getElementById("modal-cancel-btn").addEventListener("click", closeExModal);
    
    document.getElementById("btn-add-expense")?.addEventListener("click", () => {
        editMode = false;
        exForm.reset();
        document.getElementById("ex-id").value = "";
        document.getElementById("ex-date").value = new Date().toISOString().split("T")[0];
        document.getElementById("modal-title").innerText = "Log Expense";
        document.getElementById("submit-btn-text").innerText = "Log Expense";
        
        modal.classList.add("active");
    });
    
    function bindRowActions() {
        // Edit Row click
        document.querySelectorAll(".btn-edit-ex").forEach(btn => {
            btn.addEventListener("click", () => {
                editMode = true;
                
                document.getElementById("ex-id").value = btn.getAttribute("data-id");
                document.getElementById("ex-category-select").value = btn.getAttribute("data-category");
                document.getElementById("ex-amount").value = btn.getAttribute("data-amount");
                document.getElementById("ex-date").value = btn.getAttribute("data-date");
                document.getElementById("ex-ref").value = btn.getAttribute("data-ref");
                document.getElementById("ex-method").value = btn.getAttribute("data-method");
                document.getElementById("ex-notes").value = btn.getAttribute("data-notes");
                
                document.getElementById("modal-title").innerText = "Edit Expense Log";
                document.getElementById("submit-btn-text").innerText = "Save Changes";
                
                modal.classList.add("active");
            });
        });
        
        // Delete Row click
        document.querySelectorAll(".btn-delete-ex").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const ref = btn.getAttribute("data-ref") || ("ID " + id);
                
                showConfirmModal(
                    "Delete Expense Log",
                    \`Are you sure you want to delete the expense log "\${ref}"? This operation cannot be undone.\`,
                    async () => {
                        const fd = new FormData();
                        fd.append("id", id);
                        
                        const res = await ajaxRequest("/shop-system/ajax/expenses.php?action=delete", {
                            method: "POST",
                            body: fd
                        });
                        
                        if (res && res.success) {
                            showToast(res.message, "success");
                            fetchExpenses();
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast(res.message || "Failed to delete expense log.", "danger");
                        }
                    }
                );
            });
        });
    }
    
    // Form submission
    exForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        submitBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(exForm);
        const actionQuery = editMode ? "edit" : "add";
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/expenses.php?action=" + actionQuery, {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                closeExModal();
                fetchExpenses();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(res.message || "Operation failed.", "danger");
                submitBtn.disabled = false;
                spinner.style.display = "none";
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
            submitBtn.disabled = false;
            spinner.style.display = "none";
        }
    });
    
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
