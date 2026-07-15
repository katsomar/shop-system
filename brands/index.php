<?php
/**
 * Brands Workspace View Page (Split-pane layout)
 */

$page_title = "Brand Management";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper']);
?>

<!-- Description Header -->
<div class="mb-4">
    <p class="text-muted text-sm">Organize and structure products by brand or manufacturer. Adding new brands updates the product form options.</p>
</div>

<!-- Split-Pane Workspace Grid -->
<div class="grid grid-cols-1 grid-cols-md-3 gap-6">
    
    <!-- Left Pane: Add/Edit Form Panel -->
    <div style="grid-column: span 1;">
        <div class="card" id="form-container">
            <h3 class="text-sm font-semibold mb-4" id="form-title">Add Brand</h3>
            
            <form id="brand-form" autocomplete="off">
                <!-- Hidden inputs for ID -->
                <input type="hidden" name="id" id="brand-id" value="">
                
                <div class="form-group">
                    <label class="form-label" for="name">Brand Name *</label>
                    <input type="text" name="name" id="brand-name" class="form-control" placeholder="e.g. Nike, Samsung" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" for="description">Description</label>
                    <textarea name="description" id="brand-description" class="form-control" rows="4" placeholder="Brief details about the brand..."></textarea>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex gap-2">
                    <?php if ($can_edit): ?>
                        <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="form-submit-btn" style="flex:1;">
                            <span id="submit-btn-text">Create Brand</span>
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
    
    <!-- Right Pane: Brands Datatable List -->
    <div style="grid-column: span 2;">
        <div class="card" style="padding: 0; overflow:hidden;">
            
            <!-- Table Header search filter -->
            <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color);">
                <div class="search-container" style="max-width: 320px;">
                    <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
                    <input type="text" id="brand-search" class="form-control search-input" placeholder="Search brands...">
                </div>
            </div>
            
            <!-- Brands List Table -->
            <div class="table-responsive">
                <table class="table table-hover" id="brand-table">
                    <thead>
                        <tr>
                            <th>Brand Details</th>
                            <th style="width: 150px; text-align: center;">Linked Products</th>
                            <th style="width: 130px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="brand-rows">
                        <!-- Dynamic rows loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Empty state -->
            <div id="brand-empty-state" style="display: none; padding: 3rem; text-align: center;">
                <i data-lucide="tag" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom:0.25rem;">No brands match</h4>
                <p class="text-muted text-sm">Add a new brand on the left side panel to list it here.</p>
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
    
    // Fetch brands initially
    fetchBrands();
    
    // Bind search debounce
    const brandSearch = document.getElementById("brand-search");
    let searchTimeout = null;
    brandSearch.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchBrands();
        }, 300);
    });
    
    // Fetch Brand list
    async function fetchBrands() {
        const brandRows = document.getElementById("brand-rows");
        const emptyState = document.getElementById("brand-empty-state");
        
        // Show 4 skeletons loading
        brandRows.innerHTML = "";
        for (let i = 0; i < 4; i++) {
            brandRows.innerHTML += `
                <tr>
                    <td>
                        <div class="skeleton" style="width:140px; height:16px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:200px; height:12px;"></div>
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
            search: brandSearch.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/brands.php?" + params.toString());
            
            brandRows.innerHTML = "";
            if (data && data.success && data.brands.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("brand-table").style.display = "table";
                
                data.brands.forEach(b => {
                    const actionButtons = \`
                        <div class="d-flex justify-end gap-1">
                            \${ ${can_edit ? "true" : "false"} ? \`
                                <button class="btn btn-secondary btn-edit-brand" data-id="\${b.id}" data-name="\${e(b.name)}" data-desc="\${e(b.description)}" style="padding:0.4rem; font-size:0.75rem;" title="Edit Brand">
                                    <i data-lucide="edit-3" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                </button>
                                <button class="btn btn-secondary btn-delete-brand" data-id="\${b.id}" data-name="\${e(b.name)}" style="padding:0.4rem; font-size:0.75rem;" title="Delete Brand">
                                    <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i>
                                </button>
                            \` : \`<span class="text-xs text-muted">No Permissions</span>\` }
                        </div>
                    \`;
                    
                    brandRows.innerHTML += \`
                        <tr>
                            <td>
                                <div class="font-semibold text-sm">\${e(b.name)}</div>
                                <div class="text-muted text-xs">\${e(b.description || "No description")}</div>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-info">\${b.product_count} product(s)</span>
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
                document.getElementById("brand-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to retrieve brands list.", "danger");
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
            fetchBrands();
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
                    fetchBrands();
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
            fetchBrands();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Row Actions: Edit, Delete
    function bindRowActions() {
        // Edit Row click
        document.querySelectorAll(".btn-edit-brand").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                const desc = btn.getAttribute("data-desc");
                
                // Switch form layout state to Edit Mode
                editMode = true;
                document.getElementById("brand-id").value = id;
                document.getElementById("brand-name").value = name;
                document.getElementById("brand-description").value = desc;
                
                document.getElementById("form-title").innerText = "Edit Brand";
                document.getElementById("submit-btn-text").innerText = "Save Changes";
                document.getElementById("form-cancel-btn").style.display = "inline-block";
                
                document.getElementById("brand-name").focus();
            });
        });
        
        // Delete Row click
        document.querySelectorAll(".btn-delete-brand").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                
                showConfirmModal(
                    "Delete Brand",
                    \`Are you sure you want to delete the brand "\${name}"? This operation will fail if any products are currently assigned to it.\`,
                    async () => {
                        const fd = new FormData();
                        fd.append("id", id);
                        
                        const res = await ajaxRequest("/shop-system/ajax/brands.php?action=delete", {
                            method: "POST",
                            body: fd
                        });
                        
                        if (res && res.success) {
                            showToast(res.message, "success");
                            // If we deleted the brand currently being edited, reset form
                            if (document.getElementById("brand-id").value == id) {
                                resetFormState();
                            }
                            fetchBrands();
                        } else {
                            showToast(res.message || "Failed to delete brand.", "danger");
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
        document.getElementById("brand-form").reset();
        document.getElementById("brand-id").value = "";
        
        document.getElementById("form-title").innerText = "Add Brand";
        document.getElementById("submit-btn-text").innerText = "Create Brand";
        document.getElementById("form-cancel-btn").style.display = "none";
    }
    
    // Form submission (Add/Edit action handler)
    const brandForm = document.getElementById("brand-form");
    const submitBtn = document.getElementById("form-submit-btn");
    const spinner = document.getElementById("form-spinner");
    
    brandForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        submitBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(brandForm);
        const actionQuery = editMode ? "edit" : "add";
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/brands.php?action=" + actionQuery, {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                resetFormState();
                fetchBrands();
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
