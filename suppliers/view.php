<?php
/**
 * Supplier Profile Details Workspace Page
 */

$page_title = "Supplier Profile Details";

require_once __DIR__ . '/../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash_message('danger', 'Invalid supplier identifier.');
    header("Location: /shop-system/suppliers/index.php");
    exit();
}

// Fetch Supplier Profile Info
$supp_stmt = mysqli_prepare($conn, "SELECT * FROM suppliers WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($supp_stmt, 'i', $id);
mysqli_stmt_execute($supp_stmt);
$result = mysqli_stmt_get_result($supp_stmt);
$s = mysqli_fetch_assoc($result);
mysqli_stmt_close($supp_stmt);

if (!$s) {
    set_flash_message('danger', 'Supplier profile not found.');
    header("Location: /shop-system/suppliers/index.php");
    exit();
}

require_once __DIR__ . '/../includes/header.php';

// Fetch Purchase Orders for this supplier
$po_q = mysqli_query($conn, "
    SELECT * FROM purchase_orders 
    WHERE supplier_id = $id 
    ORDER BY order_date DESC, id DESC
");
$orders = [];
if ($po_q) {
    while ($row = mysqli_fetch_assoc($po_q)) {
        $orders[] = $row;
    }
}

// Fetch Products linked to this supplier
$prod_q = mysqli_query($conn, "
    SELECT p.id, p.sku, p.name, p.selling_price, p.current_stock, p.unit, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.supplier_id = $id 
    ORDER BY p.name ASC
");
$products = [];
if ($prod_q) {
    while ($row = mysqli_fetch_assoc($prod_q)) {
        $products[] = $row;
    }
}

$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper', 'Accountant']);
$currency = get_setting($conn, 'currency_symbol', '$');
?>

<div class="mb-4 d-flex justify-between align-center" style="flex-wrap: wrap; gap: 1rem;">
    <a href="/shop-system/suppliers/index.php" class="btn btn-secondary">
        <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Back to Directory
    </a>
    <?php if ($can_edit && (float)$s['credit_balance'] > 0): ?>
        <button class="btn btn-success" id="btn-pay-balance">
            <i data-lucide="hand-coins" style="width:16px; height:16px;"></i> Pay Outstanding Balance
        </button>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 grid-cols-md-3 gap-6">
    
    <!-- Left Column: Profile Card & Balance summary -->
    <div style="grid-column: span 1;" class="d-flex flex-column gap-6">
        
        <!-- Contact Card -->
        <div class="card">
            <div class="d-flex align-center gap-3 mb-4">
                <div class="sidebar-user-avatar" style="width: 50px; height: 50px; font-size: 1.5rem; font-weight:700;">
                    <?php echo strtoupper(substr($s['name'], 0, 1)); ?>
                </div>
                <div>
                    <h3 style="margin-bottom:0.15rem;"><?php echo e($s['name']); ?></h3>
                    <span class="text-xs text-muted" style="font-weight:600; text-transform:uppercase;"><?php echo e($s['company_name'] ?? 'Individual Supplier'); ?></span>
                </div>
            </div>
            
            <div class="dropdown-divider" style="margin: 1rem 0;"></div>
            
            <div class="d-flex flex-column gap-3" style="font-size: 0.85rem;">
                <div>
                    <span class="text-muted text-xs font-semibold uppercase display-block" style="color:var(--text-muted);">Phone Number</span>
                    <div class="font-medium"><?php echo e($s['phone']); ?></div>
                </div>
                <div>
                    <span class="text-muted text-xs font-semibold uppercase display-block" style="color:var(--text-muted);">Email Address</span>
                    <div class="font-medium"><?php echo e($s['email'] ?: 'No email configured'); ?></div>
                </div>
                <div>
                    <span class="text-muted text-xs font-semibold uppercase display-block" style="color:var(--text-muted);">Street Address</span>
                    <div class="font-medium"><?php echo e($s['address'] ?: 'No address configured'); ?></div>
                </div>
                <div>
                    <span class="text-muted text-xs font-semibold uppercase display-block" style="color:var(--text-muted);">Partner Since</span>
                    <div class="font-medium"><?php echo date('M d, Y', strtotime($s['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Balance Summary Card -->
        <div class="card" style="border-left: 4px solid <?php echo (float)$s['credit_balance'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
            <span class="text-muted text-xs font-semibold uppercase">Supplier Balance</span>
            <div class="stats-card-value" style="color: <?php echo (float)$s['credit_balance'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>; margin-top: 0.5rem; font-size: 2.25rem;">
                <?php echo $currency; ?><?php echo number_format($s['credit_balance'], 2); ?>
            </div>
            <p class="text-xs text-muted mt-4" style="margin-top: 1rem;">
                <?php echo (float)$s['credit_balance'] > 0 ? 'Outstanding due payables. Click Pay Balance to settle.' : 'Account settled. No outstanding due payables.'; ?>
            </p>
        </div>
        
    </div>
    
    <!-- Right Column: Purchase History & Linked Products -->
    <div style="grid-column: span 2;" class="d-flex flex-column gap-6">
        
        <!-- Purchase History Ledger -->
        <div class="card" style="padding: 0; overflow:hidden;">
            <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color);">
                <h3 class="text-sm font-semibold mb-4" style="margin-bottom:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="receipt" style="color: var(--primary);"></i> Purchase History Ledger
                </h3>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Order Date</th>
                            <th>Total Cost</th>
                            <th>Paid Amount</th>
                            <th>Balance Due</th>
                            <th style="width: 100px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding: 2.5rem 0;">No purchase invoices recorded for this supplier.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <?php 
                                // Payment status badge
                                $pay_status = $o['payment_status'];
                                $pay_badge = "";
                                if ($pay_status === 'Paid') $pay_badge = 'badge-success';
                                if ($pay_status === 'Partial') $pay_badge = 'badge-warning';
                                if ($pay_status === 'Unpaid') $pay_badge = 'badge-danger';
                                
                                $due = (float)$o['total_amount'] - (float)$o['paid_amount'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold text-sm"><?php echo e($o['invoice_number']); ?></div>
                                        <span class="badge <?php echo $pay_badge; ?>" style="font-size:0.65rem; padding: 1px 6px;"><?php echo $pay_status; ?></span>
                                    </td>
                                    <td class="text-sm"><?php echo date('M d, Y', strtotime($o['order_date'])); ?></td>
                                    <td class="text-sm font-medium"><?php echo $currency; ?><?php echo number_format($o['total_amount'], 2); ?></td>
                                    <td class="text-sm"><?php echo $currency; ?><?php echo number_format($o['paid_amount'], 2); ?></td>
                                    <td class="text-sm font-semibold <?php echo $due > 0 ? 'text-danger' : 'text-muted'; ?>">
                                        <?php echo $currency; ?><?php echo number_format($due, 2); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge <?php echo $o['status'] === 'Received' ? 'badge-success' : 'badge-warning'; ?>" style="font-size:0.7rem;">
                                            <?php echo $o['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Linked Products distributed -->
        <div class="card" style="padding: 0; overflow:hidden;">
            <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color);">
                <h3 class="text-sm font-semibold mb-4" style="margin-bottom:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="package" style="color: #0284c7;"></i> Distributed Products Catalog
                </h3>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Retail Price</th>
                            <th style="width: 100px; text-align: center;">Current Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding: 2.5rem 0;">No products mapped to this supplier.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><a href="/shop-system/products/index.php" class="font-semibold text-sm" style="color:var(--text-main);"><?php echo e($p['name']); ?></a></td>
                                    <td><code class="text-xs"><?php echo e($p['sku']); ?></code></td>
                                    <td class="text-sm"><?php echo e($p['category_name'] ?: 'Uncategorized'); ?></td>
                                    <td class="text-sm font-medium"><?php echo $currency; ?><?php echo number_format($p['selling_price'], 2); ?></td>
                                    <td style="text-align: center;">
                                        <span class="badge <?php echo (int)$p['current_stock'] > 0 ? 'badge-success' : 'badge-danger'; ?>" style="font-size:0.7rem;">
                                            <?php echo $p['current_stock']; ?> <?php echo e($p['unit']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    
</div>

<!-- PAY BALANCE MODAL WINDOW -->
<?php if ($can_edit && (float)$s['credit_balance'] > 0): ?>
<div class="modal-overlay" id="pay-modal">
    <div class="modal-window" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Record Payment</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="pay-modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="pay-form">
            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
            
            <div class="modal-body">
                <div style="background-color: var(--bg-app); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.25rem;">
                    <div class="d-flex justify-between text-sm mb-1">
                        <span class="text-muted">Supplier Name:</span>
                        <span class="font-semibold"><?php echo e($s['name']); ?></span>
                    </div>
                    <div class="d-flex justify-between text-sm">
                        <span class="text-muted">Total Due Balance:</span>
                        <span class="font-semibold" style="color:var(--danger);"><?php echo $currency; ?><?php echo number_format($s['credit_balance'], 2); ?></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="amount">Payment Amount (<?php echo $currency; ?>) *</label>
                    <input type="number" name="amount" id="pay-amount" class="form-control" value="<?php echo number_format($s['credit_balance'], 2, '.', ''); ?>" step="0.01" min="0.01" max="<?php echo number_format($s['credit_balance'], 2, '.', ''); ?>" required>
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
                    <input type="text" name="notes" id="pay-notes" class="form-control" placeholder="e.g. Paid cash, wire reference ID...">
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
<?php endif; ?>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    
    // --- PAY BALANCE MODAL INTERACTION ---
    const payModal = document.getElementById("pay-modal");
    const payForm = document.getElementById("pay-form");
    const paySubmitBtn = document.getElementById("pay-submit-btn");
    const paySpinner = document.getElementById("pay-spinner");
    
    document.getElementById("btn-pay-balance")?.addEventListener("click", () => {
        payModal.classList.add("active");
    });
    
    const closePayModal = () => {
        payModal.classList.remove("active");
    };
    
    document.getElementById("pay-modal-close")?.addEventListener("click", closePayModal);
    document.getElementById("pay-cancel-btn")?.addEventListener("click", closePayModal);
    
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
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(res.message || "Failed to log payment.", "danger");
                paySubmitBtn.disabled = false;
                paySpinner.style.display = "none";
            }
        } catch(err) {
            showToast("Network error. Please try again.", "danger");
            paySubmitBtn.disabled = false;
            paySpinner.style.display = "none";
        }
    });
});
</script>
';

include_once __DIR__ . '/../includes/footer.php';
?>
