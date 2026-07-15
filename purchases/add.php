<?php
/**
 * Create Purchase Order Workspace Form Page
 */

$page_title = "Create Purchase Order";

require_once __DIR__ . '/../includes/auth_check.php';

if (!has_role(['Administrator', 'Manager', 'Store Keeper', 'Accountant'])) {
    set_flash_message('danger', 'Unauthorized access.');
    header("Location: /shop-system/purchases/index.php");
    exit();
}

require_once __DIR__ . '/../includes/header.php';

// Fetch all active suppliers for selector
$supp_q = mysqli_query($conn, "SELECT id, name, company_name FROM suppliers ORDER BY name ASC");
$suppliers = [];
if ($supp_q) {
    while ($row = mysqli_fetch_assoc($supp_q)) {
        $suppliers[] = $row;
    }
}
?>

<div class="mb-4">
    <a href="/shop-system/purchases/index.php" class="btn btn-secondary">
        <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Cancel and Return
    </a>
</div>

<form id="po-form" autocomplete="off">
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    
    <div class="grid grid-cols-1 grid-cols-md-3 gap-6">
        
        <!-- Left Panel: Header Inputs Card (Col Span 2) -->
        <div style="grid-column: span 2;" class="d-flex flex-column gap-6">
            
            <!-- Details Card -->
            <div class="card">
                <h3 class="text-sm font-semibold mb-4" style="margin-top:0;">1. General Order Details</h3>
                
                <div class="grid grid-cols-1 grid-cols-sm-2 gap-4">
                    <!-- Supplier select -->
                    <div class="form-group">
                        <label class="form-label" for="supplier_id">Supplier Partner *</label>
                        <select name="supplier_id" id="supplier_id" class="form-control" required>
                            <option value="">-- Choose Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo e($s['name']); ?> <?php echo !empty($s['company_name']) ? '(' . e($s['company_name']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Date -->
                    <div class="form-group">
                        <label class="form-label" for="order_date">Order Date *</label>
                        <input type="date" name="order_date" id="order_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <!-- Invoice # -->
                    <div class="form-group">
                        <label class="form-label" for="invoice_number">Invoice / Ref Number</label>
                        <input type="text" name="invoice_number" id="invoice_number" class="form-control" placeholder="Leave empty to auto-generate">
                    </div>
                    
                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label" for="status">Order Status *</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="Pending">Pending (Draft / Unordered)</option>
                            <option value="Ordered">Ordered (Awaiting Delivery)</option>
                            <option value="Received">Received (Increments Stock levels)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Items Grid Card -->
            <div class="card" style="padding: 1.5rem 0;">
                <div style="padding: 0 1.5rem 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); position:relative;">
                    <h3 class="text-sm font-semibold mb-3" style="margin-top:0;">2. Order Items Grid</h3>
                    
                    <!-- Autocomplete Search Product input -->
                    <div class="search-container">
                        <i data-lucide="search" class="search-icon" style="width:16px; height:16px;"></i>
                        <input type="text" id="prod-search-input" class="form-control search-input" placeholder="Type product name, SKU or barcode to add items...">
                    </div>
                    
                    <!-- Search Results Dropdown -->
                    <div id="search-dropdown" class="card" style="display:none; position:absolute; top: 100%; left: 1.5rem; right: 1.5rem; z-index: 50; padding:0.5rem; margin-top:4px; max-height:280px; overflow-y:auto; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                        <!-- Search results preloaded here -->
                    </div>
                </div>
                
                <!-- Dynamic Items Table -->
                <div class="table-responsive" style="margin-top: 1rem;">
                    <table class="table" id="items-table" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th>Product Details</th>
                                <th style="width: 110px;">Quantity</th>
                                <th style="width: 130px;">Unit Cost ($)</th>
                                <th style="width: 120px; text-align: right;">Subtotal</th>
                                <th style="width: 50px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody">
                            <tr id="empty-row-placeholder">
                                <td colspan="5" class="text-center text-muted" style="padding: 3rem 0;">
                                    <i data-lucide="shopping-bag" style="width:36px; height:36px; margin-bottom:0.75rem; color:var(--text-muted);"></i>
                                    <div class="text-sm">Items list is empty. Add products using the search box above.</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
        
        <!-- Right Panel: Financial Totals Card (Col Span 1) -->
        <div style="grid-column: span 1;" class="d-flex flex-column gap-6">
            
            <!-- Summary Calculations -->
            <div class="card">
                <h3 class="text-sm font-semibold mb-4" style="margin-top:0;">3. Payment Summary</h3>
                
                <div class="d-flex flex-column gap-3 mb-4" style="font-size: 0.9rem;">
                    <div class="d-flex justify-between font-medium">
                        <span class="text-muted">Total Cost:</span>
                        <span id="label-total">$0.00</span>
                    </div>
                    <div class="d-flex justify-between font-semibold" style="font-size:1.1rem; border-top:1px dashed var(--border-color); padding-top:0.75rem;">
                        <span>Grand Total:</span>
                        <span id="label-grand-total" style="color:var(--primary);">$0.00</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="paid_amount">Amount Paid *</label>
                    <input type="number" name="paid_amount" id="paid_amount" class="form-control" value="0.00" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="Bank">Bank Wire</option>
                        <option value="Mobile Money">Mobile Money</option>
                    </select>
                </div>
                
                <div class="d-flex justify-between align-center mb-4 p-3" style="background-color: var(--bg-app); border-radius: var(--border-radius-sm);">
                    <span class="text-sm font-semibold">Remaining Due:</span>
                    <span id="label-balance" class="font-bold text-md text-danger">$0.00</span>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" for="notes">Internal Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Order notes, shipping terms..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary w-full d-flex align-center justify-center gap-2" id="submit-btn">
                    <span>Submit Purchase Order</span>
                    <div class="spinner" id="form-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
                </button>
            </div>
            
        </div>
        
    </div>
</form>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    
    const prodInput = document.getElementById("prod-search-input");
    const dropdown = document.getElementById("search-dropdown");
    const itemsTbody = document.getElementById("items-tbody");
    const emptyRow = document.getElementById("empty-row-placeholder");
    const totalLabel = document.getElementById("label-total");
    const grandLabel = document.getElementById("label-grand-total");
    const balanceLabel = document.getElementById("label-balance");
    const paidInput = document.getElementById("paid_amount");
    
    // Product autocomplete search
    let searchTimeout = null;
    prodInput.addEventListener("keyup", () => {
        clearTimeout(searchTimeout);
        const query = prodInput.value.trim();
        if (query.length < 2) {
            dropdown.style.display = "none";
            return;
        }
        
        searchTimeout = setTimeout(async () => {
            try {
                const res = await ajaxRequest("/shop-system/ajax/purchases.php?action=search_products&query=" + encodeURIComponent(query));
                
                dropdown.innerHTML = "";
                if (res && res.length > 0) {
                    dropdown.style.display = "block";
                    res.forEach(p => {
                        dropdown.innerHTML += `
                            <div class="search-result-item d-flex justify-between align-center p-2 cursor-pointer border-bottom hover-bg" data-id="\${p.id}" data-name="\${e(p.name)}" data-sku="\${e(p.sku)}" data-price="\${p.buying_price}" style="font-size:0.85rem; border-bottom: 1px solid var(--border-color);">
                                <div>
                                    <div class="font-semibold">\${e(p.name)}</div>
                                    <div class="text-xs text-muted">SKU: \${e(p.sku)} | \${e(p.unit)}</div>
                                </div>
                                <div class="font-medium">$\${parseFloat(p.buying_price).toFixed(2)}</div>
                            </div>
                        `;
                    });
                    
                    // Bind click on search results
                    document.querySelectorAll(".search-result-item").forEach(item => {
                        item.addEventListener("click", () => {
                            const id = parseInt(item.getAttribute("data-id"));
                            const name = item.getAttribute("data-name");
                            const sku = item.getAttribute("data-sku");
                            const price = parseFloat(item.getAttribute("data-price"));
                            
                            addProductRow(id, name, sku, price);
                            
                            // Reset search
                            prodInput.value = "";
                            dropdown.style.display = "none";
                        });
                    });
                } else {
                    dropdown.innerHTML = `<div class="text-center text-muted p-3 text-xs">No active products found</div>`;
                    dropdown.style.display = "block";
                }
            } catch(e) {
                console.error(e);
            }
        }, 250);
    });
    
    // Close dropdown on click outside
    document.addEventListener("click", (e) => {
        if (e.target !== prodInput && e.target !== dropdown) {
            dropdown.style.display = "none";
        }
    });
    
    // Add Row function
    function addProductRow(id, name, sku, price) {
        // If row already exists in table, increment quantity instead!
        const existingRow = document.querySelector(`.po-item-row[data-id="\${id}"]`);
        if (existingRow) {
            const qtyInput = existingRow.querySelector(".item-qty");
            qtyInput.value = parseInt(qtyInput.value) + 1;
            updateRowSubtotal(existingRow);
            calculateOrderTotals();
            return;
        }
        
        // Hide empty placeholder
        if (emptyRow) emptyRow.style.display = "none";
        
        const row = document.createElement("tr");
        row.className = "po-item-row";
        row.setAttribute("data-id", id);
        
        row.innerHTML = `
            <td>
                <input type="hidden" name="product_ids[]" value="\${id}">
                <div class="font-semibold text-sm">\${e(name)}</div>
                <div class="text-muted text-xs">SKU: \${e(sku)}</div>
            </td>
            <td>
                <input type="number" name="quantities[]" class="form-control item-qty" value="1" min="1" step="1" style="padding: 4px 8px; height:auto;" required>
            </td>
            <td>
                <input type="number" name="buying_prices[]" class="form-control item-cost" value="\${price.toFixed(2)}" min="0" step="0.01" style="padding: 4px 8px; height:auto;" required>
            </td>
            <td class="text-sm font-medium text-right item-subtotal" style="text-align:right;">
                $\${price.toFixed(2)}
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn btn-secondary btn-remove-row p-1" style="border:none; background:none;" title="Remove row">
                    <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--danger);"></i>
                </button>
            </td>
        `;
        
        itemsTbody.appendChild(row);
        if (typeof lucide !== "undefined") lucide.createIcons();
        
        // Bind updates
        row.querySelector(".item-qty").addEventListener("input", () => {
            updateRowSubtotal(row);
            calculateOrderTotals();
        });
        row.querySelector(".item-cost").addEventListener("input", () => {
            updateRowSubtotal(row);
            calculateOrderTotals();
        });
        row.querySelector(".btn-remove-row").addEventListener("click", () => {
            row.remove();
            checkTableEmptyState();
            calculateOrderTotals();
        });
        
        calculateOrderTotals();
    }
    
    function updateRowSubtotal(row) {
        const qty = parseInt(row.querySelector(".item-qty").value) || 0;
        const price = parseFloat(row.querySelector(".item-cost").value) || 0.00;
        const subtotal = qty * price;
        row.querySelector(".item-subtotal").innerText = "$" + subtotal.toFixed(2);
    }
    
    function checkTableEmptyState() {
        const rows = document.querySelectorAll(".po-item-row");
        if (rows.length === 0) {
            if (emptyRow) emptyRow.style.display = "table-row";
        }
    }
    
    // Calculate Order Totals
    function calculateOrderTotals() {
        const rows = document.querySelectorAll(".po-item-row");
        let grandTotal = 0.00;
        
        rows.forEach(row => {
            const qty = parseInt(row.querySelector(".item-qty").value) || 0;
            const price = parseFloat(row.querySelector(".item-cost").value) || 0.00;
            grandTotal += qty * price;
        });
        
        // Labels
        totalLabel.innerText = "$" + grandTotal.toFixed(2);
        grandLabel.innerText = "$" + grandTotal.toFixed(2);
        
        // Paid & Balance
        const paid = parseFloat(paidInput.value) || 0.00;
        const balance = Math.max(0.00, grandTotal - paid);
        balanceLabel.innerText = "$" + balance.toFixed(2);
        
        // Settle paid maximums
        paidInput.max = grandTotal.toFixed(2);
    }
    
    paidInput.addEventListener("input", calculateOrderTotals);
    
    // Form submission
    const poForm = document.getElementById("po-form");
    const submitBtn = document.getElementById("submit-btn");
    const spinner = document.getElementById("form-spinner");
    
    poForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        const rows = document.querySelectorAll(".po-item-row");
        if (rows.length === 0) {
            showToast("Please add at least one product to the purchase order items grid.", "danger");
            return;
        }
        
        submitBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(poForm);
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/purchases.php?action=add", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                setTimeout(() => {
                    location.href = "/shop-system/purchases/index.php";
                }, 1000);
            } else {
                showToast(res.message || "Failed to submit purchase order.", "danger");
                submitBtn.disabled = false;
                spinner.style.display = "none";
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
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
