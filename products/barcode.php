<?php
/**
 * Printable Barcode Page
 */

require_once __DIR__ . '/../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Product ID is required.");
}

// Query product
$stmt = mysqli_prepare($conn, "SELECT name, sku, barcode FROM products WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $name, $sku, $barcode);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$name) {
    die("Product not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Barcode - <?php echo e($name); ?></title>
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
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
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
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

        /* Printable Label layout card */
        .print-sheet {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 320px;
            text-align: center;
        }

        .product-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
        }

        .product-sku {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 20px;
        }

        #barcode-svg {
            max-width: 100%;
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
            .print-sheet {
                border: none;
                box-shadow: none;
                padding: 0;
                width: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Print / Close Toolbar -->
    <div class="toolbar">
        <button class="btn btn-primary" onclick="window.print()">
            Print Barcode
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            Close Window
        </button>
    </div>

    <!-- Printable Area -->
    <div class="print-sheet">
        <h2 class="product-name"><?php echo e($name); ?></h2>
        <div class="product-sku">SKU: <?php echo e($sku); ?></div>
        
        <?php if (!empty($barcode)): ?>
            <svg id="barcode-svg"></svg>
            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    JsBarcode("#barcode-svg", "<?php echo $barcode; ?>", {
                        format: "auto",
                        lineColor: "#0f172a",
                        width: 2,
                        height: 70,
                        displayValue: true,
                        fontSize: 14,
                        font: "Inter"
                    });
                });
            </script>
        <?php else: ?>
            <div style="padding: 20px; color: #dc2626; border: 1px dashed #fee2e2; background-color: #fee2e2; border-radius:6px; font-weight:600; font-size:14px;">
                No Barcode Defined for this Product
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
