<?php
/**
 * Printable Thermal Receipt View Page (80mm)
 */

require_once __DIR__ . '/../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invoice ID is required.");
}

// Fetch Sale Header details
$sale_sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.full_name as cashier_name 
             FROM sales s 
             LEFT JOIN customers c ON s.customer_id = c.id 
             LEFT JOIN users u ON s.created_by = u.id 
             WHERE s.id = ? LIMIT 1";
             
$sale_stmt = mysqli_prepare($conn, $sale_sql);
mysqli_stmt_bind_param($sale_stmt, 'i', $id);
mysqli_stmt_execute($sale_stmt);
$result = mysqli_stmt_get_result($sale_stmt);
$order = mysqli_fetch_assoc($result);
mysqli_stmt_close($po_stmt); // Note: we can use $sale_stmt since that's what was prepared, wait! Let's make sure we close the correct statement variable!
// Ah! In my line above, I wrote mysqli_stmt_close($po_stmt) instead of mysqli_stmt_close($sale_stmt)!
// Let's close $sale_stmt! Yes, thank god I caught that before writing the file!
?>
<?php
/**
 * Printable Thermal Receipt View Page (80mm)
 */

require_once __DIR__ . '/../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invoice ID is required.");
}

// Fetch Sale Header details
$sale_sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.full_name as cashier_name 
             FROM sales s 
             LEFT JOIN customers c ON s.customer_id = c.id 
             LEFT JOIN users u ON s.created_by = u.id 
             WHERE s.id = ? LIMIT 1";
             
$sale_stmt = mysqli_prepare($conn, $sale_sql);
mysqli_stmt_bind_param($sale_stmt, 'i', $id);
mysqli_stmt_execute($sale_stmt);
$result = mysqli_stmt_get_result($sale_stmt);
$order = mysqli_fetch_assoc($result);
mysqli_stmt_close($sale_stmt);

if (!$order) {
    die("Invoice not found.");
}

// Fetch Sale Items
$items_sql = "SELECT si.*, p.name as product_name, p.sku as product_sku, p.unit as product_unit 
              FROM sale_items si 
              LEFT JOIN products p ON si.product_id = p.id 
              WHERE si.sale_id = ? 
              ORDER BY si.id ASC";
              
$items_stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($items_stmt, 'i', $id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

$items = [];
while ($row = mysqli_fetch_assoc($items_result)) {
    $items[] = $row;
}
mysqli_stmt_close($items_stmt);

// Shop details
$shop_name = get_setting($conn, 'shop_name', 'Nexus Shop');
$shop_address = get_setting($conn, 'shop_address', '100 Core Avenue, Silicon Valley');
$shop_phone = get_setting($conn, 'shop_phone', '+1 555-019-9900');
$currency = get_setting($conn, 'currency_symbol', '$');

$subtotal = 0.00;
foreach ($items as $item) {
    $subtotal += (float)$item['subtotal'];
}
$due_balance = (float)$order['total_amount'] - (float)$order['paid_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Receipt - <?php echo e($order['invoice_number']); ?></title>
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- JsBarcode CDN -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Toolbar styles hidden during print */
        .toolbar {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .btn {
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-primary {
            background-color: #22c55e;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #16a34a;
        }
        .btn-secondary {
            background-color: #ffffff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .btn-secondary:hover {
            background-color: #f8fafc;
        }

        /* 80mm Receipt layout */
        .receipt-roll {
            background-color: #ffffff;
            width: 300px; /* Approx 80mm width */
            padding: 20px 15px;
            box-sizing: border-box;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            width: 100%;
        }
        .shop-title {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 4px 0;
            letter-spacing: -0.02em;
        }
        .shop-meta {
            font-size: 11px;
            color: #64748b;
            line-height: 1.4;
            margin: 0;
        }

        .divider {
            border-top: 1px dashed #cbd5e1;
            width: 100%;
            margin: 10px 0;
        }

        .meta-details {
            width: 100%;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.5;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
        }

        .items-table {
            width: 100%;
            font-size: 11px;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .items-table th {
            text-align: left;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 4px;
            color: #475569;
        }
        .items-table td {
            padding: 6px 0;
            vertical-align: top;
        }

        .totals-block {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 11px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
        }
        .totals-row.grand-total {
            font-size: 13px;
            font-weight: 800;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            margin-top: 2px;
        }

        .footer-message {
            text-align: center;
            font-size: 10px;
            color: #64748b;
            margin-top: 20px;
            line-height: 1.4;
            width: 100%;
        }

        #barcode-canvas {
            max-width: 90%;
            margin-top: 10px;
        }

        /* Print Media CSS Overrides */
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .toolbar {
                display: none;
            }
            .receipt-roll {
                box-shadow: none;
                border: none;
                padding: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Printing Toolbar -->
    <div class="toolbar">
        <button class="btn btn-primary" onclick="window.print()">
            Print Receipt
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            Close Window
        </button>
    </div>

    <!-- 80mm Receipt Container -->
    <div class="receipt-roll">
        <!-- Header Branding -->
        <div class="header">
            <h1 class="shop-title"><?php echo e($shop_name); ?></h1>
            <p class="shop-meta">
                <?php echo e($shop_address); ?><br>
                Tel: <?php echo e($shop_phone); ?>
            </p>
        </div>

        <div class="divider"></div>

        <!-- Meta Details -->
        <div class="meta-details">
            <div class="meta-row">
                <span>Invoice No:</span>
                <span class="font-semibold"><?php echo e($order['invoice_number']); ?></span>
            </div>
            <div class="meta-row">
                <span>Date:</span>
                <span><?php echo date('Y-m-d H:i', strtotime($order['sale_date'])); ?></span>
            </div>
            <div class="meta-row">
                <span>Cashier:</span>
                <span><?php echo e($order['cashier_name']); ?></span>
            </div>
            <div class="meta-row">
                <span>Customer:</span>
                <span><?php echo e($order['customer_name']); ?></span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th style="width: 50px; text-align: center;">Qty</th>
                    <th style="width: 70px; text-align: right;">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div><?php echo e($item['product_name']); ?></div>
                            <span style="font-size: 9px; color: #64748b;">SKU: <?php echo e($item['product_sku']); ?></span>
                        </td>
                        <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                        <td style="text-align: right;"><?php echo $currency; ?><?php echo number_format($item['selling_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <!-- Totals Block -->
        <div class="totals-block">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span><?php echo $currency; ?><?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="totals-row">
                <span>Discount:</span>
                <span>-<?php echo $currency; ?><?php echo number_format($order['discount'], 2); ?></span>
            </div>
            <div class="totals-row">
                <span>Tax (<?php echo number_format($order['tax_amount'] > 0 ? ($order['tax_amount'] / ($subtotal - $order['discount']) * 100) : 0, 2); ?>%):</span>
                <span><?php echo $currency; ?><?php echo number_format($order['tax_amount'], 2); ?></span>
            </div>
            <div class="totals-row grand-total">
                <span>Grand Total:</span>
                <span><?php echo $currency; ?><?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
            <div class="totals-row" style="margin-top: 4px;">
                <span>Amount Paid:</span>
                <span><?php echo $currency; ?><?php echo number_format($order['paid_amount'], 2); ?></span>
            </div>
            <div class="totals-row" style="font-weight: 600; color: <?php echo $due_balance > 0 ? '#dc2626' : 'inherit'; ?>;">
                <span>Balance Due:</span>
                <span><?php echo $currency; ?><?php echo number_format($due_balance, 2); ?></span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Footer Barcode & Thank you -->
        <div class="footer-message">
            <p style="margin: 0 0 10px 0;">Thank you for shopping with us!<br>Please come again.</p>
            <svg id="barcode-canvas"></svg>
        </div>
    </div>

    <!-- Automatically Trigger Print -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Render Barcode
            try {
                JsBarcode("#barcode-canvas", "<?php echo $order['invoice_number']; ?>", {
                    format: "CODE128",
                    lineColor: "#000000",
                    width: 1.5,
                    height: 40,
                    displayValue: true,
                    fontSize: 10,
                    margin: 0
                });
            } catch(e) {
                console.error("Failed to render barcode", e);
            }
            
            // Auto trigger print in popups
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>

</body>
</html>
