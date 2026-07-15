<?php
/**
 * Printable Pricing Labels Page
 */

require_once __DIR__ . '/../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Product ID is required.");
}

// Query product
$stmt = mysqli_prepare($conn, "SELECT name, sku, barcode, selling_price FROM products WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $name, $sku, $barcode, $selling_price);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$name) {
    die("Product not found.");
}

// Get currency symbol and shop name from settings
$shop_name = get_setting($conn, 'shop_name', 'Nexus Shop');
$currency = get_setting($conn, 'currency_symbol', '$');

// Default label print count
$label_count = isset($_GET['count']) ? (int)$_GET['count'] : 12;
if ($label_count < 1) $label_count = 1;
if ($label_count > 50) $label_count = 50; // Cap to prevent performance hits
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Price Labels - <?php echo e($name); ?></title>
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- JsBarcode Library -->
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
            padding: 12px 24px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .btn {
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-primary {
            background-color: #2563eb;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        .btn-secondary {
            background-color: #ffffff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .btn-secondary:hover {
            background-color: #f8fafc;
        }
        
        .form-select {
            font-family: inherit;
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            outline: none;
        }

        /* Label Sheet Container */
        .labels-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            max-width: 900px;
            width: 100%;
        }

        /* Pricing Label Card */
        .price-label-card {
            background-color: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 15px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            aspect-ratio: 1.58; /* Standard price label aspect ratio */
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .label-header {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }

        .label-name {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            margin-bottom: 4px;
        }

        .label-price-tag {
            font-size: 20px;
            font-weight: 800;
            color: #2563eb;
            margin: 4px 0;
        }

        .label-barcode-svg {
            max-width: 90%;
            max-height: 45px;
        }

        /* Print Media Styles */
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .toolbar {
                display: none;
            }
            .labels-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                max-width: 100%;
            }
            .price-label-card {
                border: 1px solid #000000;
                page-break-inside: avoid;
            }
            .label-price-tag {
                color: #000000;
            }
        }
    </style>
</head>
<body>

    <!-- Configuration Toolbar -->
    <div class="toolbar">
        <span style="font-size: 14px; font-weight: 500; color: #475569;">Labels Count:</span>
        <select class="form-select" onchange="location.href='?id=<?php echo $id; ?>&count='+this.value">
            <?php for ($c = 1; $c <= 30; $c += ($c < 10 ? 1 : 2)): ?>
                <option value="<?php echo $c; ?>" <?php echo $label_count === $c ? 'selected' : ''; ?>>
                    <?php echo $c; ?> Label<?php echo $c > 1 ? 's' : ''; ?>
                </option>
            <?php endfor; ?>
        </select>
        
        <button class="btn btn-primary" onclick="window.print()">
            Print Labels
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            Close Window
        </button>
    </div>

    <!-- Printable Grid -->
    <div class="labels-grid">
        <?php for ($i = 0; $i < $label_count; $i++): ?>
            <div class="price-label-card">
                <div class="label-header"><?php echo e($shop_name); ?></div>
                <div class="label-name"><?php echo e($name); ?></div>
                
                <div class="label-price-tag">
                    <?php echo $currency; ?><?php echo number_format($selling_price, 2); ?>
                </div>
                
                <?php if (!empty($barcode)): ?>
                    <svg class="label-barcode-svg barcode-element" data-val="<?php echo $barcode; ?>"></svg>
                <?php else: ?>
                    <div style="font-size: 8px; color: #dc2626; border: 1px dashed #fee2e2; padding: 2px 6px; border-radius:3px;">
                        No Barcode
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Script to render all barcodes on load -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const elements = document.querySelectorAll(".barcode-element");
            elements.forEach((svg, index) => {
                const value = svg.getAttribute("data-val");
                try {
                    JsBarcode(svg, value, {
                        format: "auto",
                        lineColor: "#000000",
                        width: 1.5,
                        height: 35,
                        displayValue: true,
                        fontSize: 9,
                        font: "Inter",
                        margin: 0
                    });
                } catch (e) {
                    console.error("JsBarcode failed on item " + index, e);
                }
            });
        });
    </script>

</body>
</html>
