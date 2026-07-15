<?php
/**
 * Customer Profile Details Workspace Page
 */

$page_title = "Customer Profile Details";

require_once __DIR__ . '/../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash_message('danger', 'Invalid customer identifier.');
    header("Location: /shop-system/customers/index.php");
    exit();
}

// Fetch Customer Profile Info
$cust_stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($cust_stmt, 'i', $id);
mysqli_stmt_execute($cust_stmt);
$result = mysqli_stmt_get_result($cust_stmt);
$c = mysqli_fetch_assoc($result);
mysqli_stmt_close($cust_stmt);

if (!$c) {
    set_flash_message('danger', 'Customer profile not found.');
    header("Location: /shop-system/customers/index.php");
    exit();
}

require_once __DIR__ . '/../includes/header.php';

// Fetch Sales Invoices for this customer
$sales_q = mysqli_query($conn, "
    SELECT * FROM sales 
    WHERE customer_id = $id 
    ORDER BY sale_date DESC, id DESC
");
$invoices = [];
if ($sales_q) {
    while ($row = mysqli_fetch_assoc($sales_q)) {
        $invoices[] = $row;
    }
}

$can_edit = has_role(['Administrator', 'Manager', 'Cashier', 'Accountant']);
$currency = get_setting($conn, 'currency_symbol', '$');
?>

<div class="mb-4 d-flex justify-between align-center" style="flex-wrap: wrap; gap: 1rem;">
    <a href="/shop-system/customers/index.php" class="btn btn-secondary">
        <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Back to Directory
    </a>
    <?php if ($can_edit && (float)$c['credit_balance'] > 0): ?>
        <button class="btn btn-success" id="btn-pay-balance">
            <i data-lucide="hand-coins" style="width:16px; height:16px;"></i> Record Credit Payment
        </button>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 grid-cols-md-3 gap-6">
    
    <!-- Left Column: Profile Card, Notes & Balance -->
    <div style="grid-column: span 1;" class="d-flex flex-column gap-6">
        
        <!-- Contact Card -->
        <div class="card">
            <div class="d-flex align-center gap-3 mb-4">
                <div class="sidebar-user-avatar" style="width: 50px; height: 50px; font-size: 1.5rem; font-weight:700; background-color: var(--primary); color:white;">
                    <?php echo strtoupper(substr($c['name'], 0, 1)); ?>
                </div>
                <div>
                    <h3 style="margin-bottom:0.15rem;"><?php echo e($c['name']); ?></h3>
                    <span class="text-xs text-muted" style="font-weight:600; text-transform:uppercase;">Registered Customer</span>
                </div>
            </div>
            
            <div class="dropdown-divider" style="margin: 1rem 0;"></div>
            
            <div class="d-flex flex-column gap-3" style="font-size: 0.85rem;">
                <div>
                    <span class="text-muted text-xs font-semibold uppercase display-block" style="color:var(--text-muted);">Phone Number</span>
                    <div class="font-medium"><?php echo e($c['phone'] ?: 'No phone configured'); ?></div>
                </div>
                <div>
                    <span class="text-muted text-xs font-semibold uppercase display-block" style="color:var(--text-muted);">Email Address</span>
                    <div class="font-medium"><?php echo e($c['email'] ?: 'No email configured'); ?></div>
                </div>
                <div>
                    <span class="text-muted text-xs font-semibold uppercase display-block" style="color:var(--text-muted);">Billing Address</span>
                    <div class="font-medium"><?php echo e($c['address'] ?: 'No billing address configured'); ?></div>
                </div>
                <div>
                    <span class="text-muted text-xs font-semibold uppercase display-block" style="color:var(--text-muted);">Client Since</span>
                    <div class="font-medium"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Balance Summary Card -->
        <div class="card" style="border-left: 4px solid <?php echo (float)$c['credit_balance'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
            <span class="text-muted text-xs font-semibold uppercase">Credit Account Balance</span>
            <div class="stats-card-value" style="color: <?php echo (float)$c['credit_balance'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>; margin-top: 0.5rem; font-size: 2.25rem;">
                <?php echo $currency; ?><?php echo number_format($c['credit_balance'], 2); ?>
            </div>
            <p class="text-xs text-muted mt-4" style="margin-top: 1rem;">
                <?php echo (float)$c['credit_balance'] > 0 ? 'Outstanding credit sales liability.' : 'Account settled. No outstanding credit balance.'; ?>
            </p>
        </div>
        
        <!-- Customer Notes Panel (Saves dynamically via AJAX) -->
        <div class="card">
            <h3 class="text-xs font-semibold uppercase mb-3" style="color:var(--text-muted); display:flex; align-items:center; gap:6px;">
                <i data-lucide="sticky-note" style="width:14px; height:14px;"></i> Customer Notes
            </h3>
            
            <form id="notes-form">
                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                <div class="form-group mb-3" style="margin-bottom:1rem;">
                    <textarea name="notes" id="cust-notes-text" class="form-control text-xs" rows="5" placeholder="e.g. Prefers home delivery, shipping instructions, custom discount requests..."><?php echo e($c['notes']); ?></textarea>
                </div>
                <?php if ($can_edit): ?>
                    <button type="submit" class="btn btn-secondary w-full d-flex align-center justify-center gap-2" id="notes-submit-btn" style="font-size:0.75rem; padding: 0.45rem;">
                        <span>Save Customer Notes</span>
                        <div class="spinner" id="notes-spinner" style="display: none; border-color: rgba(0,0,0,0.1); border-top-color: var(--text-main); width: 12px; height: 12px; border-width: 1.5px;"></div>
                    </button>
                <?php endif; ?>
            </form>
        </div>
        
    </div>
    
    <!-- Right Column: Sales Invoices Ledger -->
    <div style="grid-column: span 2;" class="d-flex flex-column gap-6">
        
        <!-- Sales Invoice Ledger -->
        <div class="card" style="padding: 0; overflow:hidden;">
            <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color);">
                <h3 class="text-sm font-semibold mb-4" style="margin-bottom:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="receipt" style="color: var(--primary);"></i> Sales Invoice History Ledger
                </h3>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Sale Date</th>
                            <th>Subtotal</th>
                            <th>Paid Amount</th>
                            <th>Balance Due</th>
                            <th style="width: 100px; text-align: center;">Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding: 2.5rem 0;">No sales invoices recorded for this customer.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $i): ?>
                                <?php 
                                // Payment status badge
                                $pay_status = $i['payment_status'];
                                $pay_badge = "";
                                if ($pay_status === 'Paid') $pay_badge = 'badge-success';
                                if ($pay_status === 'Partial') $pay_badge = 'badge-warning';
                                if ($pay_status === 'Unpaid') $pay_badge = 'badge-danger';
                                
                                $due = (float)$i['total_amount'] - (float)$i['paid_amount'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold text-sm"><?php echo e($i['invoice_number']); ?></div>
                                        <span class="text-xs text-muted">via <?php echo e($i['payment_method']); ?></span>
                                    </td>
                                    <td class="text-sm"><?php echo date('M d, Y h:i A', strtotime($i['sale_date'])); ?></td>
                                    <td class="text-sm font-medium"><?php echo $currency; ?><?php echo number_format($i['total_amount'], 2); ?></td>
                                    <td class="text-sm"><?php echo $currency; ?><?php echo number_format($i['paid_amount'], 2); ?></td>
                                    <td class="text-sm font-semibold <?php echo $due > 0 ? 'text-danger' : 'text-muted'; ?>">
                                        <?php echo $currency; ?><?php echo number_format($due, 2); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge <?php echo $pay_badge; ?>" style="font-size:0.7rem;">
                                            <?php echo $pay_status; ?>
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

<!-- PAY CREDIT BALANCE MODAL -->
<?php if ($can_edit && (float)$c['credit_balance'] > 0): ?>
<div class="modal-overlay" id="pay-modal">
    <div class="modal-window" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Record Credit Payment</h3>
            <button class="btn btn-secondary p-0 cursor-pointer" id="pay-modal-close" style="border:none; background:none; font-size:1.25rem;">&times;</button>
        </div>
        <form id="pay-form">
            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
            
            <div class="modal-body">
                <div style="background-color: var(--bg-app); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.25rem;">
                    <div class="d-flex justify-between text-sm mb-1">
                        <span class="text-muted">Customer Name:</span>
                        <span class="font-semibold"><?php echo e($c['name']); ?></span>
                    </div>
                    <div class="d-flex justify-between text-sm">
                        <span class="text-muted">Outstanding Balance:</span>
                        <span class="font-semibold" style="color:var(--danger);"><?php echo $currency; ?><?php echo number_format($c['credit_balance'], 2); ?></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="amount">Payment Amount (<?php echo $currency; ?>) *</label>
                    <input type="number" name="amount" id="pay-amount" class="form-control" value="<?php echo number_format($c['credit_balance'], 2, '.', ''); ?>" step="0.01" min="0.01" max="<?php echo number_format($c['credit_balance'], 2, '.', ''); ?>" required>
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
                    <input type="text" name="notes" id="pay-notes" class="form-control" placeholder="e.g. Settle cash payment, wire reference number...">
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
            const res = await ajaxRequest("/shop-system/ajax/customers.php?action=pay_balance", {
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
                showToast(res.message || "Failed to record payment.", "danger");
                paySubmitBtn.disabled = false;
                paySpinner.style.display = "none";
            }
        } catch(err) {
            showToast("Network error. Please try again.", "danger");
            paySubmitBtn.disabled = false;
            paySpinner.style.display = "none";
        }
    });
    
    // --- SAVE NOTES INTERACTION ---
    const notesForm = document.getElementById("notes-form");
    const notesSubmitBtn = document.getElementById("notes-submit-btn");
    const notesSpinner = document.getElementById("notes-spinner");
    
    notesForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        notesSubmitBtn.disabled = true;
        notesSpinner.style.display = "inline-block";
        
        const fd = new FormData(notesForm);
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/customers.php?action=save_notes", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
            } else {
                showToast(res.message || "Failed to save notes.", "danger");
            }
        } catch(err) {
            showToast("Network error while saving notes.", "danger");
        } finally {
            notesSubmitBtn.disabled = false;
            notesSpinner.style.display = "none";
        }
    });
});
</script>
';

include_once __DIR__ . '/../includes/footer.php';
?>
