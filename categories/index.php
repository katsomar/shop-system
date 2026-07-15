<?php
/**
 * Categories Workspace View Page (Split-pane layout)
 */

$page_title = "Category Management";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper']);
?>

<!-- Description Header -->
<div class="mb-4">
    <p class="text-muted text-sm">Organize and structure products by department or group. Adding new categories immediately updates the product form options.</p>
</div>

<!-- Split-Pane Workspace Grid -->
<div class="grid grid-cols-1 grid-cols-md-3 gap-6">
    
    <!-- Left Pane: Add/Edit Form Panel -->
    <div style="grid-column: span 1;">
        <div class="card" id="form-container">
            <h3 class="text-sm font-semibold mb-4" id="form-title">Add Category</h3>
            
            <form id="category-form" autocomplete="off">
                <!-- Hidden inputs for ID -->
                <input type="hidden" name="id" id="cat-id" value="">
                
                <div class="form-group">
                    <label class="form-label" for="name">Category Name *</label>
                    <input type="text" name="name" id="cat-name" class="form-control" placeholder="e.g. Office Supplies" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" for="description">Description</label>
                    <textarea name="description" id="cat-description" class="form-control" rows="4" placeholder="Brief details about items in this category..."></textarea>
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
                    <input type="text" id="cat-search" class="form-control search-input" placeholder="Search categories...">
                </div>
            </div>
            
            <!-- Category List Table -->
            <div class="table-responsive">
                <table class="table table-hover" id="cat-table">
                    <thead>
                        <tr>
                            <th>Category Details</th>
                            <th style="width: 150px; text-align: center;">Linked Products</th>
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
            
            <!-- Pagination Controls footer -->
            <div class="d-flex justify-between align-center" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap:wrap; gap: 0.5rem;">
                <span class="text-muted text-xs" id="pagination-summary">Showing 0 to 0 of 0 entries</span>
                <div class="pagination" id="pagination-controls">
                    <!-- Dynamic Page Buttons -->
                </div>
            </div>
            
        </div>
    </div>
    
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    let currentPage = 1;
    let pageLimit = 10;
    let editMode = false;
    
    // Fetch categories initially
    fetchCategories();
    
    // Bind search debounce
    const catSearch = document.getElementById("cat-search");
    let searchTimeout = null;
    catSearch.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchCategories();
        }, 300);
    });
    
    // Fetch Category list
    async function fetchCategories() {
        const catRows = document.getElementById("cat-rows");
        const emptyState = document.getElementById("cat-empty-state");
        
        // Show 4 skeletons loading
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
            action: "list",
            page: currentPage,
            limit: pageLimit,
            search: catSearch.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/categories.php?" + params.toString());
            
            catRows.innerHTML = "";
            if (data && data.success && data.categories.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("cat-table").style.display = "table";
                
                data.categories.forEach(c => {
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
                                <span class="badge badge-info">\${c.product_count} product(s)</span>
                            </td>
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
                document.getElementById("cat-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve categories list.", "danger");
        }
    }
    
    // Pagination controls generator
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
            fetchCategories();
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
                    fetchCategories();
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
            fetchCategories();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
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
                
                document.getElementById("form-title").innerText = "Edit Category";
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
                    \`Are you sure you want to delete the category "\${name}"? This operation will fail if any products are currently assigned to it.\`,
                    async () => {
                        const fd = new FormData();
                        fd.append("id", id);
                        
                        const res = await ajaxRequest("/shop-system/ajax/categories.php?action=delete", {
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
        
        document.getElementById("form-title").innerText = "Add Category";
        document.getElementById("submit-btn-text").innerText = "Create Category";
        document.getElementById("form-cancel-btn").style.display = "none";
    }
    
    // Form submission (Add/Edit action handler)
    const categoryForm = document.getElementById("category-form");
    const submitBtn = document.getElementById("form-submit-btn");
    const spinner = document.getElementById("form-spinner");
    
    categoryForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        submitBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(categoryForm);
        const actionQuery = editMode ? "edit" : "add";
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/categories.php?action=" + actionQuery, {
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
