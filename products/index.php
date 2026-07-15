<?php
/**
 * Product Listing Directory View Page
 */

$page_title = "Product Directory";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

// Fetch Categories and Brands for dropdown filters
$cat_q = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories = [];
while ($row = mysqli_fetch_assoc($cat_q)) {
    $categories[] = $row;
}

$brand_q = mysqli_query($conn, "SELECT id, name FROM brands ORDER BY name ASC");
$brands = [];
while ($row = mysqli_fetch_assoc($brand_q)) {
    $brands[] = $row;
}

// User Permission Check for additions/edits
$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper']);
?>

<!-- Action Bar Header -->
<div class="d-flex justify-between align-center mb-4" style="flex-wrap: wrap; gap: 1rem;">
    <div>
        <p class="text-muted text-sm" style="margin-bottom:0;">Search, edit, filter, and import/export shop products catalog.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-secondary" id="btn-import-modal">
            <i data-lucide="upload" style="width:16px; height:16px;"></i> Import CSV
        </button>
        <a href="/shop-system/ajax/products.php?action=export" class="btn btn-secondary" target="_blank">
            <i data-lucide="download" style="width:16px; height:16px;"></i> Export CSV
        </a>
        <?php if ($can_edit): ?>
            <a href="/shop-system/products/add.php" class="btn btn-primary">
                <i data-lucide="plus" style="width:16px; height:16px;"></i> Add Product
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Panel -->
<div class="card mb-4" style="padding: 1.25rem;">
    <form id="filters-form" class="grid grid-cols-1 grid-cols-sm-2 grid-cols-lg-4 gap-3" autocomplete="off">
        <!-- Search field -->
        <div class="search-container">
            <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
            <input type="text" name="search" id="filter-search" class="form-control search-input" placeholder="Search by SKU, name, barcode...">
        </div>
        
        <!-- Category Filter -->
        <div>
            <select name="category_id" id="filter-category" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Brand Filter -->
        <div>
            <select name="brand_id" id="filter-brand" class="form-control">
                <option value="">All Brands</option>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo $brand['id']; ?>"><?php echo e($brand['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- stock / status filter dropdowns -->
        <div class="d-flex gap-2">
            <select name="stock_status" id="filter-stock" class="form-control" style="flex: 1;">
                <option value="">All Stock Levels</option>
                <option value="low">Low Stock Alerts</option>
                <option value="out">Out of Stock</option>
                <option value="expired">Expired Products</option>
            </select>
            <select name="status" id="filter-status" class="form-control" style="width: 100px;">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>
    </form>
</div>

<!-- Products Directory Table Container -->
<div class="card" style="padding: 0; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-hover" id="products-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Image</th>
                    <th>Product Details</th>
                    <th>SKU & Barcode</th>
                    <th>Category & Brand</th>
                    <th>Pricing</th>
                    <th>Stock status</th>
                    <th>Expiry & Tax</th>
                    <th style="width: 130px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="products-rows">
                <!-- Skeleton rows injected by Javascript -->
            </tbody>
        </table>
    </div>
    
    <!-- Table Empty State -->
    <div id="empty-state" style="display: none; padding: 3rem; text-align: center;">
        <i data-lucide="package-open" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom:0.25rem;">No products found</h4>
        <p class="text-muted text-sm">Try broadening your filters or add a new product to list it here.</p>
    </div>
    
    <!-- Pagination & Stats footer -->
    <div class="d-flex justify-between align-center" style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); flex-wrap:wrap; gap: 0.5rem;">
        <span class="text-muted text-xs" id="pagination-summary">Showing 0 to 0 of 0 entries</span>
        <div class="pagination" id="pagination-controls">
            <!-- Dynamic page buttons -->
        </div>
    </div>
</div>

<!-- BULK IMPORT MODAL -->
<div class="modal-overlay" id="import-modal">
    <div class="modal-window" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Bulk Import Products</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="import-modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="import-form">
            <div class="modal-body">
                <p class="text-muted text-xs mb-4">Upload a CSV file containing columns: <code>SKU</code>, <code>Name</code>, <code>Barcode</code>, <code>Description</code>, <code>Category</code>, <code>Brand</code>, <code>Supplier</code>, <code>Buying Price</code>, <code>Selling Price</code>, <code>Wholesale Price</code>, <code>Stock</code>, <code>Min Stock</code>, <code>Unit</code>, <code>Expiry Date</code>, <code>Tax Rate</code>, <code>Status</code>.</p>
                
                <div class="form-group">
                    <label class="form-label" for="csv_file">Choose CSV File</label>
                    <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                </div>
                
                <div id="import-error" style="display:none; padding:0.5rem 0.75rem; background:rgba(220,38,38,0.1); border:1px solid rgba(220,38,38,0.2); color:var(--danger); border-radius:4px; font-size:0.75rem; margin-top:1rem;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="import-cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="import-submit-btn">
                    <span>Upload CSV</span>
                    <div class="spinner" id="import-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PRODUCT VIEW DETAIL MODAL -->
<div class="modal-overlay" id="view-modal">
    <div class="modal-window" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="view-title">Product Details</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="view-modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <div class="modal-body" id="view-body" style="padding: 1.5rem;">
            <!-- Content loaded dynamically -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="view-close-btn">Close</button>
        </div>
    </div>
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    let currentPage = 1;
    let pageLimit = 10;
    
    // Initial fetch
    fetchProducts();
    
    // Bind filters form changes
    const filterSearch = document.getElementById("filter-search");
    const filterCategory = document.getElementById("filter-category");
    const filterBrand = document.getElementById("filter-brand");
    const filterStock = document.getElementById("filter-stock");
    const filterStatus = document.getElementById("filter-status");
    
    // Keyup search debounce
    let searchTimeout = null;
    filterSearch.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            fetchProducts();
        }, 300);
    });
    
    filterCategory.addEventListener("change", () => { currentPage = 1; fetchProducts(); });
    filterBrand.addEventListener("change", () => { currentPage = 1; fetchProducts(); });
    filterStock.addEventListener("change", () => { currentPage = 1; fetchProducts(); });
    filterStatus.addEventListener("change", () => { currentPage = 1; fetchProducts(); });
    
    // Fetch Products Function
    async function fetchProducts() {
        const productsRows = document.getElementById("products-rows");
        const emptyState = document.getElementById("empty-state");
        
        // Show Skeleton Loaders (5 rows)
        productsRows.innerHTML = "";
        for (let i = 0; i < 5; i++) {
            productsRows.innerHTML += `
                <tr>
                    <td><div class="skeleton" style="width:36px; height:36px; border-radius:50%;"></div></td>
                    <td>
                        <div class="skeleton" style="width:180px; height:16px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:100px; height:12px;"></div>
                    </td>
                    <td>
                        <div class="skeleton" style="width:90px; height:14px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:70px; height:12px;"></div>
                    </td>
                    <td>
                        <div class="skeleton" style="width:110px; height:14px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:80px; height:12px;"></div>
                    </td>
                    <td>
                        <div class="skeleton" style="width:60px; height:14px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:50px; height:12px;"></div>
                    </td>
                    <td><div class="skeleton" style="width:80px; height:20px; border-radius:50px;"></div></td>
                    <td>
                        <div class="skeleton" style="width:80px; height:12px; margin-bottom:6px;"></div>
                        <div class="skeleton" style="width:40px; height:12px;"></div>
                    </td>
                    <td style="text-align:right;"><div class="skeleton" style="width:80px; height:28px; margin-left:auto; border-radius:6px;"></div></td>
                </tr>
            `;
        }
        
        // Build query parameters
        const params = new URLSearchParams({
            action: "list",
            page: currentPage,
            limit: pageLimit,
            search: filterSearch.value,
            category_id: filterCategory.value,
            brand_id: filterBrand.value,
            stock_status: filterStock.value,
            status: filterStatus.value
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/products.php?" + params.toString());
            
            productsRows.innerHTML = "";
            if (data && data.success && data.products.length > 0) {
                emptyState.style.display = "none";
                document.getElementById("products-table").style.display = "table";
                
                data.products.forEach(p => {
                    // Image placeholder or real path
                    const imgTag = p.image_path 
                        ? `<img src="${p.image_path}" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid var(--border-color);">`
                        : `<div class="sidebar-user-avatar" style="width:36px; height:36px; font-size:0.75rem; border-radius:50%; font-weight:600;">${p.name.substring(0,2).toUpperCase()}</div>`;
                        
                    // Stock badge
                    let stockBadge = "";
                    const current_stock = parseInt(p.current_stock);
                    const min_stock = parseInt(p.minimum_stock);
                    
                    if (current_stock === 0) {
                        stockBadge = `<span class="badge badge-danger">Out of stock</span>`;
                    } else if (current_stock <= min_stock) {
                        stockBadge = `<span class="badge badge-warning" style="display:flex; flex-direction:column; align-items:flex-start; gap:2px; border-radius:8px; padding:4px 8px;">
                                        <span>Low Stock (${current_stock})</span>
                                        <span style="font-size:0.65rem; opacity:0.8;">Min: ${min_stock}</span>
                                      </span>`;
                    } else {
                        stockBadge = `<span class="badge badge-success">${current_stock} ${p.unit}</span>`;
                    }
                    
                    // Expiry check
                    let expiryText = p.expiry_date ? p.expiry_date : "<span class=\"text-muted\">-</span>";
                    if (p.expiry_date) {
                        const expDate = new Date(p.expiry_date);
                        const today = new Date();
                        today.setHours(0,0,0,0);
                        if (expDate < today) {
                            expiryText = `<span class="font-semibold text-xs" style="color:var(--danger); display:flex; align-items:center; gap:2px;"><i data-lucide="alert-triangle" style="width:12px; height:12px;"></i> Expired (${p.expiry_date})</span>`;
                        }
                    }
                    
                    // Status Badge
                    const statusBadge = p.status === "Active" 
                        ? `<span class="badge badge-success" style="padding:2px 8px; font-size:0.7rem;">Active</span>`
                        : `<span class="badge badge-danger" style="padding:2px 8px; font-size:0.7rem;">Inactive</span>`;
                        
                    // Check if edit actions permitted
                    const actionButtons = \`
                        <div class="d-flex justify-end gap-1">
                            <button class="btn btn-secondary btn-view-prod" data-id="\${p.id}" style="padding:0.4rem; font-size:0.75rem;" title="View Details">
                                <i data-lucide="eye" style="width:14px; height:14px; color:var(--text-muted);"></i>
                            </button>
                            \${ ${can_edit ? "true" : "false"} ? \`
                                <a href="/shop-system/products/edit.php?id=\${p.id}" class="btn btn-secondary" style="padding:0.4rem; font-size:0.75rem;" title="Edit Product">
                                    <i data-lucide="edit-3" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                </a>
                                <button class="btn btn-secondary btn-delete-prod" data-id="\${p.id}" data-name="\${p.name}" style="padding:0.4rem; font-size:0.75rem;" title="Delete Product">
                                    <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i>
                                </button>
                            \` : "" }
                            <button class="btn btn-secondary btn-print-menu dropdown" style="padding:0.4rem; font-size:0.75rem; position:relative;" title="Print Options">
                                <i data-lucide="printer" style="width:14px; height:14px; color:var(--text-muted);"></i>
                                <div class="dropdown-menu" style="right:0; width:130px; font-size:0.8rem; padding:4px 0;">
                                    <a href="/shop-system/products/barcode.php?id=\${p.id}" target="_blank" class="dropdown-item"><i data-lucide="barcode" style="width:12px; height:12px;"></i> Barcode</a>
                                    <a href="/shop-system/products/label.php?id=\${p.id}" target="_blank" class="dropdown-item"><i data-lucide="tag" style="width:12px; height:12px;"></i> Price Label</a>
                                </div>
                            </button>
                        </div>
                    \`;
                    
                    productsRows.innerHTML += \`
                        <tr>
                            <td>\${imgTag}</td>
                            <td>
                                <div class="font-semibold text-sm">\${e(p.name)}</div>
                                <div class="text-muted text-xs">\${e(p.description || "No description")}</div>
                            </td>
                            <td>
                                <div class="font-medium text-xs">SKU: \${e(p.sku)}</div>
                                <div class="text-muted text-xs">\${p.barcode ? "BC: " + e(p.barcode) : "BC: -"}</div>
                            </td>
                            <td>
                                <div class="text-sm">\${e(p.category_name || "Uncategorized")}</div>
                                <div class="text-muted text-xs">\${e(p.brand_name || "Generic")}</div>
                            </td>
                            <td>
                                <div class="font-semibold text-sm">Sell: $\${parseFloat(p.selling_price).toFixed(2)}</div>
                                <div class="text-muted text-xs">Cost: $\${parseFloat(p.buying_price).toFixed(2)}</div>
                            </td>
                            <td>\${stockBadge}</td>
                            <td>
                                <div class="text-xs">\${expiryText}</div>
                                <div class="text-muted text-xs">Tax: \${parseFloat(p.tax_rate)}%</div>
                            </td>
                            <td>
                                \${actionButtons}
                            </td>
                        </tr>
                    \`;
                });
                
                // Initialize Lucide Icons
                if (typeof lucide !== "undefined") lucide.createIcons();
                
                // Render Page Controls
                renderPagination(data.total_records, data.page, data.limit, data.total_pages);
                bindRowActions(data.products);
                
            } else {
                document.getElementById("products-table").style.display = "none";
                emptyState.style.display = "block";
                document.getElementById("pagination-summary").innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById("pagination-controls").innerHTML = "";
            }
        } catch(err) {
            console.error(err);
            showToast("Failed to fetch product list.", "danger");
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
            fetchProducts();
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
                    fetchProducts();
                });
                controls.appendChild(btn);
            } else if (i === page - 2 || i === page + 2) {
                const dots = document.createElement("span");
                dots.innerText = "...";
                dots.style.padding = "0 8px";
                controls.appendChild(dots);
            }
        }
        
        // Next button
        const nextBtn = document.createElement("button");
        nextBtn.className = "page-btn";
        nextBtn.disabled = page === totalPages;
        nextBtn.innerHTML = "<i data-lucide=\'chevron-right\' style=\'width:16px; height:16px;\'></i>";
        nextBtn.addEventListener("click", () => {
            currentPage = page + 1;
            fetchProducts();
        });
        controls.appendChild(nextBtn);
        
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    
    // Bind click handlers for viewing, deleting, and barcode dropdown menus
    function bindRowActions(productsArray) {
        // Dropdown actions in rows
        const dropdownToggles = document.querySelectorAll(".btn-print-menu");
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener("click", (e) => {
                e.stopPropagation();
                toggle.classList.toggle("active");
            });
        });
        
        // Close menus when clicking elsewhere
        document.addEventListener("click", () => {
            dropdownToggles.forEach(t => t.classList.remove("active"));
        });
        
        // Delete button
        document.querySelectorAll(".btn-delete-prod").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                
                showConfirmModal(
                    "Delete Product",
                    \`Are you sure you want to delete "\${name}"? This action cannot be undone and will fail if the product has transaction history.\`,
                    async () => {
                        const fd = new FormData();
                        fd.append("id", id);
                        
                        const res = await ajaxRequest("/shop-system/ajax/products.php?action=delete", {
                            method: "POST",
                            body: fd
                        });
                        
                        if (res && res.success) {
                            showToast(res.message, "success");
                            fetchProducts();
                        } else {
                            showToast(res.message || "Failed to delete product.", "danger");
                        }
                    }
                );
            });
        });
        
        // View detail modal
        document.querySelectorAll(".btn-view-prod").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = parseInt(btn.getAttribute("data-id"));
                const p = productsArray.find(item => item.id === id);
                if (p) {
                    showProductDetailModal(p);
                }
            });
        });
    }
    
    // Renders the View modal detailed table
    function showProductDetailModal(p) {
        const viewBody = document.getElementById("view-body");
        
        const imgTag = p.image_path 
            ? `<img src="${p.image_path}" style="width:120px; height:120px; border-radius:var(--border-radius-md); object-fit:cover; margin-bottom:1rem; border:1px solid var(--border-color);">`
            : `<div class="sidebar-user-avatar" style="width:120px; height:120px; font-size:2.5rem; border-radius:var(--border-radius-md); font-weight:700; margin-bottom:1rem;">${p.name.substring(0,2).toUpperCase()}</div>`;
            
        viewBody.innerHTML = `
            <div class="d-flex align-center gap-4" style="flex-wrap:wrap; margin-bottom:1.5rem; border-bottom:1px solid var(--border-color); padding-bottom:1rem;">
                \${imgTag}
                <div>
                    <h2>\${e(p.name)}</h2>
                    <p class="text-muted text-sm">\${e(p.description || "No description provided.")}</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 grid-cols-sm-2 gap-4">
                <div>
                    <label class="text-muted text-xs font-semibold uppercase">Product SKU</label>
                    <div class="font-semibold text-sm" style="margin-bottom:1rem;">\${e(p.sku)}</div>
                    
                    <label class="text-muted text-xs font-semibold uppercase">Barcode Identifier</label>
                    <div class="font-medium text-sm" style="margin-bottom:1rem;">\${e(p.barcode || "N/A")}</div>
                    
                    <label class="text-muted text-xs font-semibold uppercase">Category / Brand</label>
                    <div class="font-medium text-sm" style="margin-bottom:1rem;">\${e(p.category_name || "Uncategorized")} / \${e(p.brand_name || "Generic")}</div>
                    
                    <label class="text-muted text-xs font-semibold uppercase">Supplier</label>
                    <div class="font-medium text-sm" style="margin-bottom:1rem;">\${e(p.supplier_name || "N/A")}</div>
                </div>
                
                <div>
                    <label class="text-muted text-xs font-semibold uppercase">Buying Price Cost</label>
                    <div class="font-medium text-sm" style="margin-bottom:1rem;">$\${parseFloat(p.buying_price).toFixed(2)}</div>
                    
                    <label class="text-muted text-xs font-semibold uppercase">Selling Price (Retail / Wholesale)</label>
                    <div class="font-semibold text-sm" style="margin-bottom:1rem; color:var(--primary);">$\${parseFloat(p.selling_price).toFixed(2)} / $\${parseFloat(p.wholesale_price).toFixed(2)}</div>
                    
                    <label class="text-muted text-xs font-semibold uppercase">Current / Min Stock</label>
                    <div class="font-semibold text-sm" style="margin-bottom:1rem;">\${p.current_stock} \${p.unit} (Alert limit: \${p.minimum_stock})</div>
                    
                    <label class="text-muted text-xs font-semibold uppercase">Expiry Date / Tax</label>
                    <div class="font-medium text-sm" style="margin-bottom:1rem;">\${p.expiry_date || "N/A"} (Rate: \${parseFloat(p.tax_rate)}%)</div>
                </div>
            </div>
        `;
        
        document.getElementById("view-modal").classList.add("active");
    }
    
    // Close view modal
    document.getElementById("view-modal-close").addEventListener("click", () => {
        document.getElementById("view-modal").classList.remove("active");
    });
    document.getElementById("view-close-btn").addEventListener("click", () => {
        document.getElementById("view-modal").classList.remove("active");
    });

    // --- BULK IMPORT MODAL CONTROLS ---
    const importModal = document.getElementById("import-modal");
    const importForm = document.getElementById("import-form");
    const importError = document.getElementById("import-error");
    const importSubmitBtn = document.getElementById("import-submit-btn");
    const importSpinner = document.getElementById("import-spinner");

    document.getElementById("btn-import-modal").addEventListener("click", () => {
        importError.style.display = "none";
        importForm.reset();
        importModal.classList.add("active");
    });

    const closeImportModal = () => {
        importModal.classList.remove("active");
    };
    document.getElementById("import-modal-close").addEventListener("click", closeImportModal);
    document.getElementById("import-cancel-btn").addEventListener("click", closeImportModal);

    // Form Submission
    importForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        importError.style.display = "none";
        importSubmitBtn.disabled = true;
        importSpinner.style.display = "inline-block";

        const fd = new FormData(importForm);

        try {
            const res = await ajaxRequest("/shop-system/ajax/products.php?action=import", {
                method: "POST",
                body: fd
            });

            if (res && res.success) {
                showToast(res.message, "success");
                closeImportModal();
                fetchProducts();
            } else {
                importError.innerText = res.message || "Failed to process import file.";
                importError.style.display = "block";
            }
        } catch(err) {
            importError.innerText = "Network transmission error. Please retry.";
            importError.style.display = "block";
        } finally {
            importSubmitBtn.disabled = false;
            importSpinner.style.display = "none";
        }
    });

    // Helper for HTML entity escape
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
