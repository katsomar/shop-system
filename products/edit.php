<?php
/**
 * Edit Product Page
 */

$page_title = "Edit Product";

require_once __DIR__ . '/../includes/auth_check.php';

// Enforce role permission
check_role(['Administrator', 'Manager', 'Store Keeper'], '/shop-system/products/index.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash_message('danger', 'Invalid product parameter.');
    header("Location: /shop-system/products/index.php");
    exit();
}

// Fetch Product details
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$p = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$p) {
    set_flash_message('danger', 'Product not found.');
    header("Location: /shop-system/products/index.php");
    exit();
}

require_once __DIR__ . '/../includes/header.php';

// Fetch Dropdowns
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

$supp_q = mysqli_query($conn, "SELECT id, name FROM suppliers ORDER BY name ASC");
$suppliers = [];
while ($row = mysqli_fetch_assoc($supp_q)) {
    $suppliers[] = $row;
}
?>

<div class="mb-4">
    <a href="/shop-system/products/index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
        <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Back to Directory
    </a>
</div>

<form id="edit-product-form" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
    
    <div class="grid grid-cols-1 grid-cols-md-3 gap-6">
        
        <!-- Left Side: Basic Details & Media -->
        <div style="grid-column: span 2;" class="d-flex flex-column gap-6">
            
            <!-- Details Card -->
            <div class="card">
                <h3 class="text-sm font-semibold mb-4">Basic Details</h3>
                
                <div class="form-group">
                    <label class="form-label" for="name">Product Name *</label>
                    <input type="text" name="name" id="name" class="form-control" value="<?php echo e($p['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="4"><?php echo e($p['description']); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 grid-cols-sm-3 gap-3">
                    <div class="form-group">
                        <label class="form-label" for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="form-control">
                            <option value="">Choose Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $p['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="brand_id">Brand</label>
                        <select name="brand_id" id="brand_id" class="form-control">
                            <option value="">Choose Brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>" <?php echo $p['brand_id'] == $brand['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($brand['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="supplier_id">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="form-control">
                            <option value="">Choose Supplier</option>
                            <?php foreach ($suppliers as $supp): ?>
                                <option value="<?php echo $supp['id']; ?>" <?php echo $p['supplier_id'] == $supp['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($supp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Expiry & Unit Properties Card -->
            <div class="card">
                <h3 class="text-sm font-semibold mb-4">Properties & Rules</h3>
                
                <div class="grid grid-cols-1 grid-cols-sm-3 gap-3">
                    <div class="form-group">
                        <label class="form-label" for="unit">Measurement Unit</label>
                        <input type="text" name="unit" id="unit" class="form-control" value="<?php echo e($p['unit']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="expiry_date">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="<?php echo $p['expiry_date']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="tax_rate">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" id="tax_rate" class="form-control" value="<?php echo $p['tax_rate']; ?>" step="0.01" min="0">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 grid-cols-sm-2 gap-3">
                    <div class="form-group">
                        <label class="form-label" for="status">Catalog Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Active" <?php echo $p['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $p['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Media Upload Card -->
            <div class="card">
                <h3 class="text-sm font-semibold mb-4">Product Image</h3>
                
                <div class="d-flex align-center gap-4 style-upload-preview" style="flex-wrap: wrap;">
                    <div id="image-preview-container" style="width: 120px; height: 120px; border-radius: var(--border-radius-md); border: 2px dashed var(--border-color); display:flex; align-items:center; justify-content:center; overflow:hidden; background-color: var(--bg-app);">
                        <?php if ($p['image_path']): ?>
                            <img src="<?php echo $p['image_path']; ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <i data-lucide="image" style="width: 32px; height: 32px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                        <p class="text-xs text-muted mb-2">Recommended: Square format (1:1), maximum 2MB size. Formats: JPG, PNG, WEBP.</p>
                        <input type="file" name="product_image" id="product_image" class="form-control" accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('product_image').click()">
                            <i data-lucide="upload-cloud" style="width:16px; height:16px;"></i> Replace Image
                        </button>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Right Side: Identifiers, Pricing, Stock -->
        <div class="d-flex flex-column gap-6">
            
            <!-- Code Identifiers -->
            <div class="card">
                <h3 class="text-sm font-semibold mb-4">Identifiers</h3>
                
                <div class="form-group">
                    <div class="d-flex justify-between align-center mb-1">
                        <label class="form-label" for="sku" style="margin-bottom:0;">SKU *</label>
                        <button type="button" class="btn btn-secondary p-0" id="btn-gen-sku" style="border:none; background:none; font-size:0.75rem; color:var(--primary);">Generate SKU</button>
                    </div>
                    <input type="text" name="sku" id="sku" class="form-control" style="text-transform: uppercase;" value="<?php echo e($p['sku']); ?>" required>
                </div>
                
                <div class="form-group">
                    <div class="d-flex justify-between align-center mb-1">
                        <label class="form-label" for="barcode" style="margin-bottom:0;">Barcode (EAN-13)</label>
                        <button type="button" class="btn btn-secondary p-0" id="btn-gen-barcode" style="border:none; background:none; font-size:0.75rem; color:var(--primary);">Generate Barcode</button>
                    </div>
                    <input type="text" name="barcode" id="barcode" class="form-control" value="<?php echo e($p['barcode']); ?>" pattern="[0-9]{8,13}">
                </div>
            </div>
            
            <!-- Pricing Config -->
            <div class="card">
                <h3 class="text-sm font-semibold mb-4">Pricing Strategy</h3>
                
                <div class="form-group">
                    <label class="form-label" for="buying_price">Buying Cost Price *</label>
                    <input type="number" name="buying_price" id="buying_price" class="form-control" value="<?php echo $p['buying_price']; ?>" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="selling_price">Retail Selling Price *</label>
                    <input type="number" name="selling_price" id="selling_price" class="form-control" value="<?php echo $p['selling_price']; ?>" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="wholesale_price">Wholesale Selling Price *</label>
                    <input type="number" name="wholesale_price" id="wholesale_price" class="form-control" value="<?php echo $p['wholesale_price']; ?>" step="0.01" min="0" required>
                </div>
                
                <!-- live stats margin calculation -->
                <div style="background-color: var(--bg-app); padding: 0.75rem; border-radius: var(--border-radius-sm); font-size: 0.8rem;">
                    <div class="d-flex justify-between mb-1">
                        <span class="text-muted">Estimated Margin:</span>
                        <span class="font-semibold" id="calc-margin">0%</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span class="text-muted">Markup Profit:</span>
                        <span class="font-semibold" id="calc-profit" style="color:var(--success);">$0.00</span>
                    </div>
                </div>
            </div>
            
            <!-- Stock Control -->
            <div class="card">
                <h3 class="text-sm font-semibold mb-4">Inventory Stock</h3>
                
                <div class="form-group">
                    <label class="form-label" for="current_stock">Manual Stock Count Override</label>
                    <input type="number" name="current_stock" id="current_stock" class="form-control" value="<?php echo $p['current_stock']; ?>" min="0" required>
                    <small class="text-muted text-xs">Note: Changing this directly creates an inventory movement log.</small>
                </div>
                
                <div class="grid grid-cols-1 grid-cols-sm-2 gap-2">
                    <div class="form-group">
                        <label class="form-label" for="minimum_stock">Min Alert Limit</label>
                        <input type="number" name="minimum_stock" id="minimum_stock" class="form-control" value="<?php echo $p['minimum_stock']; ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="maximum_stock">Max Stock Limit</label>
                        <input type="number" name="maximum_stock" id="maximum_stock" class="form-control" value="<?php echo $p['maximum_stock']; ?>" min="0" required>
                    </div>
                </div>
            </div>
            
            <!-- Buttons panel -->
            <div class="card d-flex gap-2 justify-between">
                <button type="button" class="btn btn-secondary" onclick="location.href='/shop-system/products/index.php'">Cancel</button>
                <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="btn-save-product">
                    <span>Save Changes</span>
                    <div class="spinner" id="save-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                </button>
            </div>
            
        </div>
        
    </div>
</form>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    // 1. Live Image Upload Preview
    const imageInput = document.getElementById("product_image");
    const previewContainer = document.getElementById("image-preview-container");
    
    imageInput.addEventListener("change", (e) => {
        const file = e.target.files[0];
        if (file) {
            const previewUrl = URL.createObjectURL(file);
            previewContainer.innerHTML = `<img src="\${previewUrl}" style="width:100%; height:100%; object-fit:cover;">`;
        }
    });
    
    // 2. live Margin Calculations
    const buyPriceInput = document.getElementById("buying_price");
    const sellPriceInput = document.getElementById("selling_price");
    const marginSpan = document.getElementById("calc-margin");
    const profitSpan = document.getElementById("calc-profit");
    
    const updateMargins = () => {
        const buy = parseFloat(buyPriceInput.value) || 0;
        const sell = parseFloat(sellPriceInput.value) || 0;
        
        const profit = sell - buy;
        let margin = 0;
        if (sell > 0) {
            margin = (profit / sell) * 100;
        }
        
        profitSpan.innerText = "$" + profit.toFixed(2);
        marginSpan.innerText = margin.toFixed(1) + "%";
        
        if (profit < 0) {
            profitSpan.style.color = "var(--danger)";
        } else {
            profitSpan.style.color = "var(--success)";
        }
    };
    
    // Run calculations initially on preload
    updateMargins();
    
    buyPriceInput.addEventListener("input", updateMargins);
    sellPriceInput.addEventListener("input", updateMargins);
    
    // 3. Generators
    document.getElementById("btn-gen-sku").addEventListener("click", () => {
        const name = document.getElementById("name").value.trim();
        const rand = Math.floor(1000 + Math.random() * 9000);
        let prefix = "PROD";
        
        if (name.length > 2) {
            prefix = name.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, "PRD");
        }
        
        document.getElementById("sku").value = \`\${prefix}-\${rand}\`;
    });
    
    document.getElementById("btn-gen-barcode").addEventListener("click", () => {
        let barcode = "978";
        for (let i = 0; i < 9; i++) {
            barcode += Math.floor(Math.random() * 10);
        }
        
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(barcode.charAt(i)) * (i % 2 === 0 ? 1 : 3);
        }
        const checkDigit = (10 - (sum % 10)) % 10;
        
        document.getElementById("barcode").value = barcode + checkDigit;
    });
    
    // 4. AJAX Save Submission
    const productForm = document.getElementById("edit-product-form");
    const saveBtn = document.getElementById("btn-save-product");
    const spinner = document.getElementById("save-spinner");
    
    productForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        const buy = parseFloat(buyPriceInput.value) || 0;
        const sell = parseFloat(sellPriceInput.value) || 0;
        if (sell < buy) {
            showToast("Selling price cannot be lower than buying cost price.", "warning");
            return;
        }
        
        saveBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(productForm);
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/products.php?action=edit", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                setTimeout(() => {
                    window.location.href = "/shop-system/products/index.php";
                }, 1000);
            } else {
                showToast(res.message || "Failed to update product.", "danger");
                saveBtn.disabled = false;
                spinner.style.display = "none";
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
            saveBtn.disabled = false;
            spinner.style.display = "none";
        }
    });
});
</script>
';

include_once __DIR__ . '/../includes/footer.php';
?>
