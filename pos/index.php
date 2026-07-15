<?php
/**
 * POS sales terminal workspace page
 */

$page_title = "POS Sales Terminal";

require_once __DIR__ . '/../includes/auth_check.php';

if (!has_role(['Administrator', 'Manager', 'Cashier'])) {
    set_flash_message('danger', 'Unauthorized access to POS Sales terminal.');
    header("Location: /shop-system/dashboard/index.php");
    exit();
}

require_once __DIR__ . '/../includes/header.php';

// Fetch categories for pills filter
$cat_q = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories = [];
if ($cat_q) {
    while ($row = mysqli_fetch_assoc($cat_q)) {
        $categories[] = $row;
    }
}

// Fetch customers list for dropdown
$cust_q = mysqli_query($conn, "SELECT id, name FROM customers ORDER BY name ASC");
$customers = [];
if ($cust_q) {
    while ($row = mysqli_fetch_assoc($cust_q)) {
        $customers[] = $row;
    }
}

// Fetch global tax rate from settings
$tax_rate = (float)get_setting($conn, 'tax_rate', '8.25');
$currency = get_setting($conn, 'currency_symbol', '$');
?>

<!-- Description Header -->
<div class="mb-4 d-flex justify-between align-center" style="flex-wrap:wrap; gap:0.5rem;">
    <p class="text-muted text-sm" style="margin:0;">Interactive sales workspace. Search products, add items to cart, and checkout invoices.</p>
    <div class="text-xs text-muted">Global Tax Rate: <span class="font-semibold text-main"><?php echo $tax_rate; ?>%</span></div>
</div>

<!-- POS Main Grid Workspace -->
<div class="grid grid-cols-1 grid-cols-lg-12 gap-6" style="align-items: start;">
    
    <!-- Left Column (Grid Col Span 7): Products Catalog Grid -->
    <div style="grid-column: span 7;" class="d-flex flex-column gap-4">
        
        <!-- Filter and Search Card -->
        <div class="card" style="padding: 1rem;">
            <div class="d-flex gap-2 mb-3" style="flex-wrap: wrap;">
                <div class="search-container" style="flex: 1; min-width: 200px;">
                    <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
                    <input type="text" id="pos-search" class="form-control search-input" placeholder="Search by name, SKU, or barcode...">
                </div>
            </div>
            
            <!-- Category Pills/Tabs Filter -->
            <div class="d-flex gap-2" style="overflow-x: auto; padding-bottom: 4px; white-space: nowrap; -webkit-overflow-scrolling: touch;">
                <button class="btn btn-secondary cat-pill active" data-id="0" style="padding: 6px 12px; font-size: 0.8rem; border-radius:50px;">All Categories</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="btn btn-secondary cat-pill" data-id="<?php echo $cat['id']; ?>" style="padding: 6px 12px; font-size: 0.8rem; border-radius:50px;">
                        <?php echo e($cat['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Products Grid Container -->
        <div class="grid grid-cols-1 grid-cols-sm-3 gap-4" id="catalog-grid" style="max-height: 580px; overflow-y: auto; padding-right: 4px;">
            <!-- Dynamic product cards populated via AJAX -->
        </div>
        
    </div>
    
    <!-- Right Column (Grid Col Span 5): Shopping Cart and Checkout Summary -->
    <div style="grid-column: span 5;">
        <div class="card d-flex flex-column" style="padding: 1.25rem; min-height: 600px;">
            
            <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 0.75rem;">
                <h3 class="text-sm font-semibold d-flex align-center gap-2" style="margin:0;">
                    <i data-lucide="shopping-cart" style="color:var(--primary); width:18px; height:18px;"></i> Customer Cart
                </h3>
            </div>
            
            <!-- Interactive Cart Table (Flexible Height) -->
            <div style="flex: 1; overflow-y: auto; max-height: 260px; margin-bottom: 1rem; border-bottom: 1px dashed var(--border-color);">
                <table class="table" style="margin-bottom:0;" id="cart-table">
                    <tbody id="cart-tbody">
                        <tr id="cart-empty-placeholder">
                            <td class="text-center text-muted" style="padding: 3rem 0;">
                                <i data-lucide="shopping-bag" style="width:32px; height:32px; margin-bottom:0.5rem; color:var(--text-muted);"></i>
                                <div class="text-xs">Your shopping cart is empty. Add products from the catalog.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Checkout Configurations -->
            <form id="checkout-form">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="tax_rate" value="<?php echo $tax_rate; ?>">
                
                <div class="form-group mb-2">
                    <label class="form-label text-xs" for="customer_id" style="margin-bottom: 4px;">Select Customer</label>
                    <select name="customer_id" id="customer_id" class="form-control text-xs" style="padding: 6px 12px; height:auto;" required>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo strtolower($c['name']) === 'walk-in customer' ? 'selected' : ''; ?>>
                                <?php echo e($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div class="form-group">
                        <label class="form-label text-xs" for="discount" style="margin-bottom: 4px;">Flat Discount ($)</label>
                        <input type="number" name="discount" id="discount" class="form-control text-xs" value="0.00" min="0" step="0.01" style="padding: 6px 12px; height:auto;">
                    </div>
                    <div class="form-group">
                        <label class="form-label text-xs" for="payment_method" style="margin-bottom: 4px;">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control text-xs" style="padding: 6px 12px; height:auto;">
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Credit">Credit Account</option>
                        </select>
                    </div>
                </div>
                
                <!-- Financial breakdowns calculations box -->
                <div style="background-color: var(--bg-app); padding: 10px 15px; border-radius: var(--border-radius-sm); margin-bottom: 1rem; font-size: 0.85rem;" class="d-flex flex-column gap-1.5">
                    <div class="d-flex justify-between font-medium">
                        <span class="text-muted">Subtotal:</span>
                        <span id="label-subtotal">$0.00</span>
                    </div>
                    <div class="d-flex justify-between font-medium">
                        <span class="text-muted">Discount:</span>
                        <span id="label-discount">-$0.00</span>
                    </div>
                    <div class="d-flex justify-between font-medium">
                        <span class="text-muted">Tax (<?php echo $tax_rate; ?>%):</span>
                        <span id="label-tax">$0.00</span>
                    </div>
                    <div class="d-flex justify-between font-bold text-md text-main" style="border-top: 1px dashed var(--border-color); padding-top: 6px; margin-top: 4px; font-size: 1rem;">
                        <span>Grand Total:</span>
                        <span id="label-grand-total">$0.00</span>
                    </div>
                </div>
                
                <div class="form-group mb-4">
                    <label class="form-label text-xs" for="paid_amount" style="margin-bottom: 4px;">Amount Received</label>
                    <input type="number" name="paid_amount" id="paid_amount" class="form-control" value="0.00" min="0" step="0.01">
                </div>
                
                <!-- Settle Button -->
                <button type="submit" class="btn btn-success w-full d-flex align-center justify-center gap-2" id="checkout-btn" style="padding: 10px;">
                    <i data-lucide="check-circle" style="width:18px; height:18px;"></i>
                    <span>Settle & Print Receipt</span>
                    <div class="spinner" id="checkout-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                </button>
            </form>
            
        </div>
    </div>
    
</div>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    let activeCategoryId = 0;
    let productsList = [];
    let cart = [];
    
    // Fetch products catalog
    fetchProducts();
    
    // Bind search keyup
    const searchInput = document.getElementById("pos-search");
    let searchTimeout = null;
    searchInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(fetchProducts, 250);
    });
    
    // Bind category pills
    document.querySelectorAll(".cat-pill").forEach(pill => {
        pill.addEventListener("click", () => {
            document.querySelectorAll(".cat-pill").forEach(p => p.classList.remove("active"));
            pill.classList.add("active");
            activeCategoryId = parseInt(pill.getAttribute("data-id"));
            fetchProducts();
        });
    });
    
    // AJAX Fetch Products
    async function fetchProducts() {
        const grid = document.getElementById("catalog-grid");
        
        grid.innerHTML = "";
        for (let i = 0; i < 6; i++) {
            grid.innerHTML += `
                <div class="card" style="padding:0.75rem;">
                    <div class="skeleton" style="width:100%; aspect-ratio:1.3; border-radius:6px; margin-bottom:8px;"></div>
                    <div class="skeleton" style="width:80%; height:14px; margin-bottom:4px;"></div>
                    <div class="skeleton" style="width:40%; height:12px; margin-bottom:8px;"></div>
                    <div class="skeleton" style="width:100%; height:28px; border-radius:6px;"></div>
                </div>
            `;
        }
        
        const params = new URLSearchParams({
            action: "products",
            search: searchInput.value,
            category_id: activeCategoryId
        });
        
        try {
            const data = await ajaxRequest("/shop-system/ajax/pos.php?" + params.toString());
            productsList = data;
            
            grid.innerHTML = "";
            if (data && data.length > 0) {
                data.forEach(p => {
                    const price = parseFloat(p.selling_price);
                    const stock = parseInt(p.current_stock);
                    const isOutOfStock = stock <= 0;
                    
                    // Stock badge
                    let stockBadge = `<span class="badge badge-success" style="font-size:0.65rem;">Stock: \${stock}</span>`;
                    if (stock <= 10) stockBadge = `<span class="badge badge-warning" style="font-size:0.65rem;">Low Stock: \${stock}</span>`;
                    if (isOutOfStock) stockBadge = `<span class="badge badge-danger" style="font-size:0.65rem;">Out of Stock</span>`;
                    
                    const imgTag = p.image_path 
                        ? `<img src="/shop-system/\${e(p.image_path)}" style="width:100%; height:100px; object-fit:cover; border-radius:6px; margin-bottom:8px;">`
                        : `<div style="width:100%; height:100px; background-color:var(--bg-app); display:flex; align-items:center; justify-center; border-radius:6px; margin-bottom:8px;"><i data-lucide="package" style="width:24px; height:24px; color:var(--text-muted);"></i></div>`;
                    
                    grid.innerHTML += \`
                        <div class="card d-flex flex-column justify-between" style="padding:0.75rem; border-color: \${isOutOfStock ? "rgba(220,38,38,0.1)" : "var(--border-color)"};">
                            <div>
                                \${imgTag}
                                <div class="font-semibold text-xs mb-1" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="\${e(p.name)}">\${e(p.name)}</div>
                                <div class="d-flex justify-between align-center mb-3">
                                    <span class="font-bold text-xs" style="color:var(--primary);">$\${price.toFixed(2)}</span>
                                    \${stockBadge}
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary w-full btn-add-cart" data-id="\${p.id}" \${isOutOfStock ? "disabled" : ""} style="padding: 5px; font-size:0.75rem;">
                                \${isOutOfStock ? "Unavailable" : "Add to Cart"}
                            </button>
                        </div>
                    \`;
                });
                
                if (typeof lucide !== "undefined") lucide.createIcons();
                
                // Bind additions
                document.querySelectorAll(".btn-add-cart").forEach(btn => {
                    btn.addEventListener("click", () => {
                        const id = parseInt(btn.getAttribute("data-id"));
                        addToCart(id);
                    });
                });
            } else {
                grid.innerHTML = `<div class="text-center text-muted p-4 text-xs" style="grid-column: span 3;">No matching active products found</div>`;
            }
        } catch(err) {
            console.error(err);
        }
    }
    
    // Add to Cart
    function addToCart(productId) {
        const product = productsList.find(p => p.id === productId);
        if (!product) return;
        
        const existingItem = cart.find(item => item.product_id === productId);
        if (existingItem) {
            // Check stock limit
            if (existingItem.qty >= parseInt(product.current_stock)) {
                showToast("Cannot exceed available stock limit (\${product.current_stock} \${product.unit}).", "warning");
                return;
            }
            existingItem.qty += 1;
        } else {
            cart.push({
                product_id: productId,
                name: product.name,
                selling_price: parseFloat(product.selling_price),
                qty: 1,
                max_stock: parseInt(product.current_stock)
            });
        }
        
        renderCart();
        calculateCartTotals();
    }
    
    // Render Shopping Cart list
    function renderCart() {
        const tbody = document.getElementById("cart-tbody");
        const placeholder = document.getElementById("cart-empty-placeholder");
        
        // Remove old item rows
        document.querySelectorAll(".cart-row-item").forEach(r => r.remove());
        
        if (cart.length === 0) {
            placeholder.style.display = "table-row";
            return;
        }
        
        placeholder.style.display = "none";
        
        cart.forEach((item, index) => {
            const row = document.createElement("tr");
            row.className = "cart-row-item";
            row.innerHTML = `
                <td style="padding:8px 0;">
                    <input type="hidden" name="product_ids[]" value="\${item.product_id}">
                    <input type="hidden" name="selling_prices[]" value="\${item.selling_price}">
                    <input type="hidden" name="quantities[]" class="input-qty-hidden" value="\${item.qty}">
                    
                    <div class="font-semibold text-xs" style="max-width: 140px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">\${e(item.name)}</div>
                    <span class="text-muted text-xs">$\${item.selling_price.toFixed(2)}</span>
                </td>
                <td style="padding:8px 0; width:90px;">
                    <div class="d-flex align-center gap-1">
                        <button type="button" class="btn btn-secondary btn-minus" style="padding:2px 6px; font-size:0.7rem; height:auto;">-</button>
                        <span class="font-semibold text-xs label-qty" style="min-width:18px; text-align:center;">\${item.qty}</span>
                        <button type="button" class="btn btn-secondary btn-plus" style="padding:2px 6px; font-size:0.7rem; height:auto;">+</button>
                    </div>
                </td>
                <td style="padding:8px 0; text-align:right; font-size:0.8rem;" class="font-semibold">
                    $\${(item.selling_price * item.qty).toFixed(2)}
                </td>
                <td style="padding:8px 0; text-align:center; width:30px;">
                    <button type="button" class="btn-remove-item" style="background:none; border:none; padding:2px; cursor:pointer;" title="Delete row">
                        <i data-lucide="trash-2" style="width:13px; height:13px; color:var(--danger);"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
            if (typeof lucide !== "undefined") lucide.createIcons();
            
            // Bind item modifiers
            row.querySelector(".btn-plus").addEventListener("click", () => {
                if (item.qty >= item.max_stock) {
                    showToast("Cannot exceed available stock limit.", "warning");
                    return;
                }
                item.qty += 1;
                renderCart();
                calculateCartTotals();
            });
            row.querySelector(".btn-minus").addEventListener("click", () => {
                if (item.qty > 1) {
                    item.qty -= 1;
                    renderCart();
                    calculateCartTotals();
                }
            });
            row.querySelector(".btn-remove-item").addEventListener("click", () => {
                cart.splice(index, 1);
                renderCart();
                calculateCartTotals();
            });
        });
    }
    
    // Calculate Cart Totals
    const discountInput = document.getElementById("discount");
    const paidInput = document.getElementById("paid_amount");
    
    function calculateCartTotals() {
        let subtotal = 0.00;
        cart.forEach(item => {
            subtotal += item.selling_price * item.qty;
        });
        
        const discVal = parseFloat(discountInput.value) || 0.00;
        const discountTotal = Math.min(subtotal, discVal);
        const subAfterDisc = Math.max(0.00, subtotal - discountTotal);
        
        const taxRate = parseFloat(document.querySelector(\'[name="tax_rate"]\').value) || 0.00;
        const taxAmount = subAfterDisc * (taxRate / 100);
        const grandTotal = subAfterDisc + taxAmount;
        
        // Populate labels
        document.getElementById("label-subtotal").innerText = "$" + subtotal.toFixed(2);
        document.getElementById("label-discount").innerText = "-$" + discountTotal.toFixed(2);
        document.getElementById("label-tax").innerText = "$" + taxAmount.toFixed(2);
        document.getElementById("label-grand-total").innerText = "$" + grandTotal.toFixed(2);
        
        // Auto pre-populate amount paid to match grand total
        if (paidInput.value == "0.00" || paidInput.getAttribute("data-auto") === "true") {
            paidInput.value = grandTotal.toFixed(2);
            paidInput.setAttribute("data-auto", "true");
        }
        
        // Paid & Balance
        const paidVal = parseFloat(paidInput.value) || 0.00;
        const balance = Math.max(0.00, grandTotal - paidVal);
        document.getElementById("label-balance").innerText = "$" + balance.toFixed(2);
    }
    
    discountInput.addEventListener("input", calculateCartTotals);
    paidInput.addEventListener("input", () => {
        paidInput.setAttribute("data-auto", "false");
        calculateCartTotals();
    });
    
    // Checkout form submit
    const checkoutForm = document.getElementById("checkout-form");
    const checkoutBtn = document.getElementById("checkout-btn");
    const spinner = document.getElementById("checkout-spinner");
    
    checkoutForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        if (cart.length === 0) {
            showToast("Your shopping cart is empty.", "danger");
            return;
        }
        
        checkoutBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(checkoutForm);
        // Append dynamic arrays from cart structure
        cart.forEach(item => {
            fd.append("product_ids[]", item.product_id);
            fd.append("quantities[]", item.qty);
            fd.append("selling_prices[]", item.selling_price);
        });
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/pos.php?action=checkout", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                
                // Open Printable Receipt Popup
                const receiptWin = window.open("/shop-system/pos/receipt.php?id=" + res.sale_id, "PrintReceipt", "width=400,height=600");
                if (!receiptWin || receiptWin.closed || typeof receiptWin.closed == "undefined") {
                    showToast("Pop-up blocker detected. Please allow popups to print the receipt.", "warning");
                }
                
                // Clear cart & reset forms
                cart = [];
                renderCart();
                checkoutForm.reset();
                discountInput.value = "0.00";
                paidInput.value = "0.00";
                paidInput.setAttribute("data-auto", "true");
                calculateCartTotals();
                
                // Refresh catalog stocks
                fetchProducts();
            } else {
                showToast(res.message || "Failed to process sale.", "danger");
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
        } finally {
            checkoutBtn.disabled = false;
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
