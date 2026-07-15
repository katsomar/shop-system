<?php
/**
 * Expense Categories Workspace view page (Split-pane layout)
 */

$page_title = "Expense Categories";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$can_edit = has_role(['Administrator', 'Manager', 'Accountant']);
?>

<!-- Description Header -->
<div class="mb-4 d-flex justify-between align-center" style="flex-wrap: wrap; gap:1rem;">
    <p class="text-muted text-sm" style="margin:0;">Manage and structure business expenses by classification categories (e.g. rent, tax, salaries).</p>
    <a href="/shop-system/expenses/index.php" class="btn btn-secondary btn-sm d-flex align-center gap-2">
        <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Back to Expenses Log
    </a>
</div>

<!-- Split-Pane Workspace Grid -->
<div class="grid grid-cols-1 grid-cols-md-3 gap-6">
    
    <!-- Left Pane: Add/Edit Form Panel -->
    <div style="grid-column: span 1;">
        <div class="card" id="form-container">
            <h3 class="text-sm font-semibold mb-4" id="form-title">Add Expense Category</h3>
            
            <form id="category-form" autocomplete="off">
                <!-- Hidden inputs for ID -->
                <input type="hidden" name="id" id="cat-id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="name">Category Name *</label>
                    <input type="text" name="name" id="cat-name" class="form-control" placeholder="e.g. Utilities, Salaries" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" for="description">Description</label>
                    <textarea name="description" id="cat-description" class="form-control" rows="4" placeholder="Brief details about what operational costs fall under this category..."></textarea>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex gap-2">
                    <?php if ($can_edit): ?>
                        <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="form-submit-btn" style="flex:1;">
                            <span id="submit-btn-text">Create Category</span>
                            <div class="spinner" id="form-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                        </button>
                        <button type="button" class="btn btn-secondary" id="form-cancel-btn" style="display: none;">Cancel</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary w-full" disabled>Edit Permission Required</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Right Pane: Categories Datatable List -->
    <div style="grid-column: span 2;">
        <div class="card" style="padding: 0; overflow:hidden;">
            
            <!-- Table Header search filter -->
            <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color);">
                <div class="search-container" style="max-width: 320px;">
                    <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
                    <input type="text" id="cat-search" class="form-control search-input" placeholder="Search expense categories...">
                </div>
            </div>
            
            <!-- Category List Table -->
            <div class="table-responsive">
                <table class="table table-hover" id="cat-table">
                    <thead>
                        <tr>
                            <th>Category Details</th>
                            <th style="width: 150px; text-align: center;">Logged Expenses</th>
                            <th style="width: 130px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cat-rows">
                        <!-- Dynamic rows loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Empty state -->
            <div id="cat-empty-state" style="display: none; padding: 3rem; text-align: center;">
                <i data-lucide="folder-tree" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom:0.25rem;">No categories match</h4>
                <p class="text-muted text-sm">Add a new category on the left side panel to list it here.</p>
            </div>
            
        </div>
    </div>
    
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    let editMode = false;
    
    // Fetch categories initially
    fetchCategories();
    
    // Bind search debounce
    const catSearch = document.getElementById("cat-search");
    let searchTimeout = null;
    catSearch.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchCategories();
        }, 300);
    });
    
    // Fetch Category list
    async function fetchCategories() {
        const catRows = document.getElementById("cat-rows");
        const emptyState = document.getElementById("cat-empty-state");
        
        // Show skeletons loading
        catRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            catRows.innerHTML += `
                <tr>
                    <td>
                        <div class="skeleton" style="width:160px; height:16px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:240px; height:12px;"></div>
                    </td>
                    <td><div class="skeleton" style="width:50px; height:20px; border-radius:50px; margin:0 auto;"></div></td>
                    <td style="text-align:right;"><div class="skeleton" style="width:80px; height:28px; margin-left:auto; border-radius:6px;"></div></td>
                </tr>
            `;
        }
        
        const params = new URLSearchParams({
            action: "category_list",
            search: catSearch.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/expenses.php?" + params.toString());
            
            catRows.innerHTML = "";
            if (data && data.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("cat-table").style.display = "table";
                
                data.forEach(c => {
                    const actionButtons = \`
                        <div class="d-flex justify-end gap-1">
                            \${ ${can_edit ? "true" : "false"} ? \`
                                <button class="btn btn-secondary btn-edit-cat" data-id="\${c.id}" data-name="\${e(c.name)}" data-desc="\${e(c.description)}" style="padding:0.4rem; font-size:0.75rem;" title="Edit Category">
                                    <i data-lucide="edit-3" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                </button>
                                <button class="btn btn-secondary btn-delete-cat" data-id="\${c.id}" data-name="\${e(c.name)}" style="padding:0.4rem; font-size:0.75rem;" title="Delete Category">
                                    <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i>
                                </button>
                            \` : \`<span class="text-xs text-muted">No Permissions</span>\` }
                        </div>
                    \`;
                    
                    catRows.innerHTML += \`
                        <tr>
                            <td>
                                <div class="font-semibold text-sm">\${e(c.name)}</div>
                                <div class="text-muted text-xs">\${e(c.description || "No description")}</div>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-secondary">\${c.expense_count} log(s)</span>
                            </td>
                            <td>
                                \${actionButtons}
                            </td>
                        </tr>
                    \`;
                });
                
                if (typeof lucide !== "undefined") lucide.createIcons();
                bindRowActions();
            } else {
                document.getElementById("cat-table").style.display = "none";
                emptyState.style.display = "block";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve categories list.", "danger");
        }
    }
    
    // Row Actions: Edit, Delete
    function bindRowActions() {
        // Edit Row click
        document.querySelectorAll(".btn-edit-cat").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                const desc = btn.getAttribute("data-desc");
                
                // Switch form layout state to Edit Mode
                editMode = true;
                document.getElementById("cat-id").value = id;
                document.getElementById("cat-name").value = name;
                document.getElementById("cat-description").value = desc;
                
                document.getElementById("form-title").innerText = "Edit Expense Category";
                document.getElementById("submit-btn-text").innerText = "Save Changes";
                document.getElementById("form-cancel-btn").style.display = "inline-block";
                
                document.getElementById("cat-name").focus();
            });
        });
        
        // Delete Row click
        document.querySelectorAll(".btn-delete-cat").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                
                showConfirmModal(
                    "Delete Category",
                    \`Are you sure you want to delete the category "\${name}"? This operation will fail if any expense transactions are currently assigned to it.\`,
                    async () => {
                        const fd = new FormData();
                        fd.append("id", id);
                        
                        const res = await ajaxRequest("/shop-system/ajax/expenses.php?action=category_delete", {
                            method: "POST",
                            body: fd
                        });
                        
                        if (res && res.success) {
                            showToast(res.message, "success");
                            // If we deleted the category currently being edited, reset form
                            if (document.getElementById("cat-id").value == id) {
                                resetFormState();
                            }
                            fetchCategories();
                        } else {
                            showToast(res.message || "Failed to delete category.", "danger");
                        }
                    }
                );
            });
        });
    }
    
    // Form Cancel click (Reset to Add mode)
    const cancelBtn = document.getElementById("form-cancel-btn");
    cancelBtn?.addEventListener("click", () => {
        resetFormState();
    });
    
    function resetFormState() {
        editMode = false;
        document.getElementById("category-form").reset();
        document.getElementById("cat-id").value = "";
        
        document.getElementById("form-title").innerText = "Add Expense Category";
        document.getElementById("submit-btn-text").innerText = "Create Category";
        document.getElementById("form-cancel-btn").style.display = "none";
    }
    
    // Form submission
    const categoryForm = document.getElementById("category-form");
    const submitBtn = document.getElementById("form-submit-btn");
    const spinner = document.getElementById("form-spinner");
    
    categoryForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        submitBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(categoryForm);
        const actionQuery = editMode ? "category_edit" : "category_add";
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/expenses.php?action=" + actionQuery, {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                resetFormState();
                fetchCategories();
            } else {
                showToast(res.message || "Operation failed.", "danger");
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
