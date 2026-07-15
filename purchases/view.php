<?php
/**
 * Detailed Purchase Invoice View Page
 */

$page_title = "Purchase Invoice Details";

require_once __DIR__ . '/../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash_message('danger', 'Invalid purchase order identifier.');
    header("Location: /shop-system/purchases/index.php");
    exit();
}

// Fetch Purchase Order Header
$po_sql = "SELECT po.*, s.name as supplier_name, s.company_name, s.email as supplier_email, s.phone as supplier_phone, s.address as supplier_address, u.full_name as creator_name 
           FROM purchase_orders po 
           LEFT JOIN suppliers s ON po.supplier_id = s.id 
           LEFT JOIN users u ON po.created_by = u.id 
           WHERE po.id = ? LIMIT 1";
           
$po_stmt = mysqli_prepare($conn, $po_sql);
mysqli_stmt_bind_param($po_stmt, 'i', $id);
mysqli_stmt_execute($po_stmt);
$result = mysqli_stmt_get_result($po_stmt);
$order = mysqli_fetch_assoc($result);
mysqli_stmt_close($po_stmt);

if (!$order) {
    set_flash_message('danger', 'Purchase order invoice not found.');
    header("Location: /shop-system/purchases/index.php");
    exit();
}

// Fetch Purchase Order Items
$items_sql = "SELECT poi.*, p.name as product_name, p.sku as product_sku, p.unit as product_unit 
              FROM purchase_order_items poi 
              LEFT JOIN products p ON poi.product_id = p.id 
              WHERE poi.purchase_order_id = ? 
              ORDER BY poi.id ASC";
              
$items_stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($items_stmt, 'i', $id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

$items = [];
while ($row = mysqli_fetch_assoc($items_result)) {
    $items[] = $row;
}
mysqli_stmt_close($items_stmt);

require_once __DIR__ . '/../includes/header.php';

// Get Currency & Shop Details
$shop_name = get_setting($conn, 'shop_name', 'Nexus Shop');
$shop_address = get_setting($conn, 'shop_address', '100 Core Avenue, Silicon Valley');
$shop_phone = get_setting($conn, 'shop_phone', '+1 555-019-9900');
$shop_email = get_setting($conn, 'shop_email', 'billing@nexusshop.com');
$currency = get_setting($conn, 'currency_symbol', '$');

$due_balance = (float)$order['total_amount'] - (float)$order['paid_amount'];

// Payment status badge class
$pay_badge = "badge-secondary";
if ($order['payment_status'] === 'Paid') $pay_badge = 'badge-success';
if ($order['payment_status'] === 'Partial') $pay_badge = 'badge-warning';
if ($order['payment_status'] === 'Unpaid') $pay_badge = 'badge-danger';

// Order status badge class
$status_badge = "badge-info";
if ($order['status'] === 'Received') $status_badge = 'badge-success';
if ($order['status'] === 'Pending') $status_badge = 'badge-warning';
?>

<!-- Action Toolbar (Hidden on Print) -->
<div class="mb-4 d-flex justify-between align-center toolbar-area">
    <a href="/shop-system/purchases/index.php" class="btn btn-secondary">
        <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Back to list
    </a>
    <div class="d-flex gap-2">
        <button class="btn btn-primary d-flex align-center gap-2" onclick="window.print()">
            <i data-lucide="printer" style="width:16px; height:16px;"></i> Print Invoice
        </button>
    </div>
</div>

<!-- Invoice Card Container -->
<div class="card printable-invoice" style="padding: 2.5rem; max-width: 900px; margin: 0 auto;">
    
    <!-- Invoice Branding Header -->
    <div class="d-flex justify-between align-start" style="flex-wrap: wrap; gap: 1.5rem; margin-bottom: 2rem;">
        <div>
            <h2 class="font-bold" style="margin:0 0 0.25rem 0; color:var(--primary); font-size:1.8rem; letter-spacing:-0.03em;"><?php echo e($shop_name); ?></h2>
            <div class="text-xs text-muted" style="line-height:1.4;">
                <?php echo e($shop_address); ?><br>
                Phone: <?php echo e($shop_phone); ?> | Email: <?php echo e($shop_email); ?>
            </div>
        </div>
        
        <div style="text-align: right;">
            <h1 class="font-bold text-lg uppercase" style="margin:0 0 0.5rem 0; font-size:1.4rem; letter-spacing:0.02em;">Purchase Order</h1>
            <div class="text-sm font-semibold" style="margin-bottom:0.25rem;"><?php echo e($order['invoice_number']); ?></div>
            <div class="text-xs text-muted">Order Date: <?php echo date('M d, Y', strtotime($order['order_date'])); ?></div>
        </div>
    </div>
    
    <div class="dropdown-divider" style="margin: 1.5rem 0;"></div>
    
    <!-- Invoice Billing Details Grid -->
    <div class="grid grid-cols-1 grid-cols-sm-2 gap-6 mb-6">
        <div>
            <span class="text-muted text-xs font-semibold uppercase display-block mb-2" style="color:var(--text-muted);">Supplier Details</span>
            <div class="font-bold text-sm" style="margin-bottom: 0.25rem;"><?php echo e($order['supplier_name']); ?></div>
            <?php if (!empty($order['company_name'])): ?>
                <div class="text-xs font-medium text-muted mb-2"><?php echo e($order['company_name']); ?></div>
            <?php endif; ?>
            <div class="text-xs text-muted" style="line-height: 1.5;">
                <?php if (!empty($order['supplier_address'])): ?>
                    Address: <?php echo e($order['supplier_address']); ?><br>
                <?php endif; ?>
                Phone: <?php echo e($order['supplier_phone']); ?><br>
                Email: <?php echo e($order['supplier_email'] ?: 'N/A'); ?>
            </div>
        </div>
        
        <div style="text-align: right; font-size: 0.85rem;" class="d-flex flex-column gap-2 justify-end align-end">
            <div>
                <span class="text-muted text-xs font-semibold uppercase" style="margin-right: 8px;">Order Status:</span>
                <span class="badge <?php echo $status_badge; ?>"><?php echo $order['status']; ?></span>
            </div>
            <div>
                <span class="text-muted text-xs font-semibold uppercase" style="margin-right: 8px;">Payment Status:</span>
                <span class="badge <?php echo $pay_badge; ?>"><?php echo $order['payment_status']; ?></span>
            </div>
            <div class="text-xs text-muted" style="margin-top: 10px;">
                Registered by: <span class="font-semibold"><?php echo e($order['creator_name']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Items Table -->
    <div class="table-responsive" style="margin-bottom: 2rem; border: 1px solid var(--border-color); border-radius: 6px; overflow:hidden;">
        <table class="table" style="margin-bottom:0;">
            <thead style="background-color: var(--bg-app);">
                <tr>
                    <th style="padding: 10px 15px;">Product Item Details</th>
                    <th style="width: 100px; text-align: center; padding: 10px 15px;">Quantity</th>
                    <th style="width: 130px; text-align: right; padding: 10px 15px;">Unit Cost</th>
                    <th style="width: 130px; text-align: right; padding: 10px 15px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td style="padding: 12px 15px;">
                            <div class="font-semibold text-sm"><?php echo e($item['product_name']); ?></div>
                            <code class="text-xs" style="color: var(--text-muted);">SKU: <?php echo e($item['product_sku']); ?></code>
                        </td>
                        <td style="text-align: center; padding: 12px 15px;" class="text-sm">
                            <?php echo $item['quantity']; ?> <?php echo e($item['product_unit']); ?>
                        </td>
                        <td style="text-align: right; padding: 12px 15px;" class="text-sm font-medium">
                            <?php echo $currency; ?><?php echo number_format($item['buying_price'], 2); ?>
                        </td>
                        <td style="text-align: right; padding: 12px 15px;" class="text-sm font-semibold">
                            <?php echo $currency; ?><?php echo number_format($item['subtotal'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Totals Area -->
    <div class="d-flex justify-between align-start" style="flex-wrap: wrap; gap: 1.5rem;">
        <!-- Notes box -->
        <div style="flex: 1; min-width: 280px; max-width: 450px;">
            <?php if (!empty($order['notes'])): ?>
                <div style="background-color: var(--bg-app); padding: 1rem; border-radius: 6px; font-size: 0.8rem; border-left: 3px solid var(--border-color);">
                    <span class="font-semibold display-block mb-1 text-xs uppercase" style="color:var(--text-muted);">Order Notes</span>
                    <p style="margin:0; line-height: 1.4;"><?php echo nl2br(e($order['notes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Totals summary table -->
        <div style="width: 280px;" class="d-flex flex-column gap-2 text-sm">
            <div class="d-flex justify-between font-medium">
                <span class="text-muted">Subtotal:</span>
                <span><?php echo $currency; ?><?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
            <div class="d-flex justify-between font-medium">
                <span class="text-muted">Amount Paid:</span>
                <span><?php echo $currency; ?><?php echo number_format($order['paid_amount'], 2); ?></span>
            </div>
            
            <div class="dropdown-divider" style="margin: 6px 0;"></div>
            
            <div class="d-flex justify-between font-bold text-md" style="font-size: 1.05rem;">
                <span>Grand Total:</span>
                <span style="color:var(--primary);"><?php echo $currency; ?><?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
            
            <div class="d-flex justify-between font-bold text-sm <?php echo $due_balance > 0 ? 'text-danger' : 'text-success'; ?>" style="padding-top: 4px;">
                <span>Balance Due:</span>
                <span><?php echo $currency; ?><?php echo number_format($due_balance, 2); ?></span>
            </div>
        </div>
    </div>
    
</div>

<style>
/* Print media CSS style overrides */
@media print {
    body {
        background-color: #ffffff !important;
        padding: 0 !important;
    }
    .toolbar-area,
    .main-header,
    .main-sidebar,
    footer {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .printable-invoice {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .table-responsive {
        border-color: #000000 !important;
    }
    thead th {
        background-color: #f1f5f9 !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
<?php
include_once __DIR__ . '/../includes/footer.php';
?>
