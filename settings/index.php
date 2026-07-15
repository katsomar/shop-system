<?php
/**
 * App Settings Configuration Panel Workspace View Page
 */

$page_title = "System Configuration";

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

if (!has_role(['Administrator', 'Manager'])) {
    set_flash_message('danger', 'Unauthorized access to application configuration settings.');
    header("Location: /shop-system/dashboard/index.php");
    exit();
}

// Fetch current configurations
$shop_name = get_setting($conn, 'shop_name', 'Nexus Shop');
$shop_phone = get_setting($conn, 'shop_phone', '+1 555-019-9900');
$shop_email = get_setting($conn, 'shop_email', 'billing@nexusshop.com');
$shop_address = get_setting($conn, 'shop_address', '100 Core Avenue, Silicon Valley');
$currency_symbol = get_setting($conn, 'currency_symbol', '$');
$tax_rate = (float)get_setting($conn, 'tax_rate', '8.25');
$low_stock_threshold = (int)get_setting($conn, 'low_stock_threshold', '10');
?>

<!-- Description Header -->
<div class="mb-4">
    <p class="text-muted text-sm">Configure global company identity metadata, invoice currency values, tax percentages, and stock management limits.</p>
</div>

<!-- Settings Form Panel -->
<form id="settings-form" class="max-w-2xl" autocomplete="off" style="max-width: 720px; margin: 0 auto;">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    
    <!-- 1. General Profile details Card -->
    <div class="card mb-6" style="padding: 1.5rem;">
        <h3 class="text-sm font-semibold mb-4 d-flex align-center gap-2" style="border-bottom: 1px dashed var(--border-color); padding-bottom: 8px;">
            <i data-lucide="building" style="color:var(--primary); width:18px; height:18px;"></i> General Shop details
        </h3>
        
        <div class="form-group">
            <label class="form-label" for="shop_name">Shop Name *</label>
            <input type="text" name="shop_name" id="settings-name" class="form-control" value="<?php echo e($shop_name); ?>" required>
        </div>
        
        <div class="grid grid-cols-1 grid-cols-sm-2 gap-4">
            <div class="form-group">
                <label class="form-label" for="shop_phone">Contact Phone</label>
                <input type="text" name="shop_phone" id="settings-phone" class="form-control" value="<?php echo e($shop_phone); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="shop_email">Contact Email</label>
                <input type="email" name="shop_email" id="settings-email" class="form-control" value="<?php echo e($shop_email); ?>">
            </div>
        </div>
        
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="shop_address">Shop Address</label>
            <textarea name="shop_address" id="settings-address" class="form-control" rows="3"><?php echo e($shop_address); ?></textarea>
        </div>
    </div>
    
    <!-- 2. Financial and billing settings Card -->
    <div class="card mb-6" style="padding: 1.5rem;">
        <h3 class="text-sm font-semibold mb-4 d-flex align-center gap-2" style="border-bottom: 1px dashed var(--border-color); padding-bottom: 8px;">
            <i data-lucide="percent" style="color:var(--primary); width:18px; height:18px;"></i> Financial & Taxes Billing
        </h3>
        
        <div class="grid grid-cols-1 grid-cols-sm-2 gap-4" style="margin-bottom:0;">
            <div class="form-group">
                <label class="form-label" for="currency_symbol">Currency Symbol *</label>
                <input type="text" name="currency_symbol" id="settings-currency" class="form-control" value="<?php echo e($currency_symbol); ?>" required>
                <span class="text-xs text-muted" style="margin-top:2px; display:block;">Symbol or acronym used (e.g. $, €, USD, £).</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="tax_rate">Tax Rate (%) *</label>
                <input type="number" name="tax_rate" id="settings-tax" class="form-control" value="<?php echo $tax_rate; ?>" step="0.01" min="0" max="100" required>
                <span class="text-xs text-muted" style="margin-top:2px; display:block;">Applicable POS tax rate percentage.</span>
            </div>
        </div>
    </div>
    
    <!-- 3. Operations Inventory stock limits Card -->
    <div class="card mb-6" style="padding: 1.5rem;">
        <h3 class="text-sm font-semibold mb-4 d-flex align-center gap-2" style="border-bottom: 1px dashed var(--border-color); padding-bottom: 8px;">
            <i data-lucide="package-open" style="color:var(--primary); width:18px; height:18px;"></i> Stock alerts limit
        </h3>
        
        <div class="form-group" style="margin-bottom:0; max-width: 320px;">
            <label class="form-label" for="low_stock_threshold">Default Low Stock threshold *</label>
            <input type="number" name="low_stock_threshold" id="settings-threshold" class="form-control" value="<?php echo $low_stock_threshold; ?>" min="1" step="1" required>
            <span class="text-xs text-muted" style="margin-top:2px; display:block;">Trigger low stock notifications when quantities fall below this.</span>
        </div>
    </div>
    
    <!-- Action controls button -->
    <div class="d-flex justify-end gap-2">
        <button type="submit" class="btn btn-primary d-flex align-center gap-2" id="settings-submit-btn" style="padding: 10px 20px;">
            <i data-lucide="save" style="width:16px; height:16px;"></i>
            <span>Save Configuration</span>
            <div class="spinner" id="settings-spinner" style="display: none; border-color: rgba(255,255,255,0.2); border-top-color: white; width: 14px; height: 14px; border-width: 2px;"></div>
        </button>
    </div>
</form>

<?php
$page_scripts = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    const settingsForm = document.getElementById("settings-form");
    const submitBtn = document.getElementById("settings-submit-btn");
    const spinner = document.getElementById("settings-spinner");
    
    settingsForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        submitBtn.disabled = true;
        spinner.style.display = "inline-block";
        
        const fd = new FormData(settingsForm);
        
        try {
            const res = await ajaxRequest("/shop-system/ajax/settings.php", {
                method: "POST",
                body: fd
            });
            
            if (res && res.success) {
                showToast(res.message, "success");
                
                // Reload layout elements to instantly repaint modifications
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(res.message || "Failed to update configurations.", "danger");
                submitBtn.disabled = false;
                spinner.style.display = "none";
            }
        } catch(err) {
            showToast("Network transmission error.", "danger");
            submitBtn.disabled = false;
            spinner.style.display = "none";
        }
    });
});
</script>
';

include_once __DIR__ . '/../includes/footer.php';
?>
