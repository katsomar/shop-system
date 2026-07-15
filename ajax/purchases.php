<?php
/**
 * AJAX Purchase Orders Management Controller
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce authentication
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper', 'Accountant']);

$action = clean_input($_GET['action'] ?? 'list');

switch ($action) {
    
    // --- LIST PURCHASE ORDERS ---
    case 'list':
        $search = clean_input($_GET['search'] ?? '');
        $status = clean_input($_GET['status'] ?? '');
        $payment_status = clean_input($_GET['payment_status'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(po.invoice_number LIKE ? OR s.name LIKE ? OR s.company_name LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val, $search_val]);
            $types .= "sss";
        }
        
        if (!empty($status)) {
            $where_clauses[] = "po.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if (!empty($payment_status)) {
            $where_clauses[] = "po.payment_status = ?";
            $params[] = $payment_status;
            $types .= "s";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total
        $count_sql = "SELECT COUNT(*) FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id WHERE $where_sql";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch Main
        $data_sql = "SELECT po.*, s.name as supplier_name, s.company_name 
                     FROM purchase_orders po 
                     LEFT JOIN suppliers s ON po.supplier_id = s.id 
                     WHERE $where_sql 
                     ORDER BY po.order_date DESC, po.id DESC 
                     LIMIT ? OFFSET ?";
                     
        $data_stmt = mysqli_prepare($conn, $data_sql);
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $purchases = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $purchases[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'purchases' => $purchases,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    // --- SEARCH PRODUCTS AUTOCOMPLETE ---
    case 'search_products':
        $query = clean_input($_GET['query'] ?? '');
        if (empty($query)) {
            echo json_encode([]);
            exit();
        }
        
        $search_val = "%$query%";
        $stmt = mysqli_prepare($conn, "
            SELECT id, name, sku, barcode, buying_price, selling_price, unit 
            FROM products 
            WHERE (name LIKE ? OR sku LIKE ? OR barcode LIKE ?) AND status = 'Active' 
            LIMIT 10
        ");
        mysqli_stmt_bind_param($stmt, 'sss', $search_val, $search_val, $search_val);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        echo json_encode($products);
        exit();
        break;
        
    // --- CREATE PURCHASE ORDER ---
    case 'add':
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        // Verify CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
            exit();
        }
        
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $invoice_number = trim($_POST['invoice_number'] ?? '');
        $order_date = trim($_POST['order_date'] ?? date('Y-m-d'));
        $status = clean_input($_POST['status'] ?? 'Pending');
        $payment_method = clean_input($_POST['payment_method'] ?? 'Cash');
        $paid_amount = (float)($_POST['paid_amount'] ?? 0.00);
        $notes = trim($_POST['notes'] ?? '');
        
        // Items verification
        $product_ids = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $buying_prices = $_POST['buying_prices'] ?? [];
        
        if ($supplier_id <= 0 || empty($product_ids)) {
            echo json_encode(['success' => false, 'message' => 'Please select a supplier and add at least one product.']);
            exit();
        }
        
        // Validate supplier exists
        $supp_check = mysqli_query($conn, "SELECT name FROM suppliers WHERE id = $supplier_id LIMIT 1");
        if (mysqli_num_rows($supp_check) === 0) {
            echo json_encode(['success' => false, 'message' => 'Selected supplier does not exist.']);
            exit();
        }
        $supplier_name = mysqli_fetch_assoc($supp_check)['name'];
        
        // Auto-generate invoice number if empty
        if (empty($invoice_number)) {
            $invoice_number = "PO-" . date('Ymd') . "-" . rand(1000, 9999);
        }
        
        // Check invoice number uniqueness
        $inv_check = mysqli_query($conn, "SELECT id FROM purchase_orders WHERE invoice_number = '" . mysqli_real_escape_string($conn, $invoice_number) . "' LIMIT 1");
        if (mysqli_num_rows($inv_check) > 0) {
            echo json_encode(['success' => false, 'message' => 'Invoice number already exists.']);
            exit();
        }
        
        // Calculate Totals and prepare items array
        $items = [];
        $total_amount = 0.00;
        
        for ($i = 0; $i < count($product_ids); $i++) {
            $prod_id = (int)$product_ids[$i];
            $qty = (int)$quantities[$i];
            $price = (float)$buying_prices[$i];
            
            if ($prod_id <= 0 || $qty <= 0 || $price < 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product details in items grid.']);
                exit();
            }
            
            $subtotal = $qty * $price;
            $total_amount += $subtotal;
            
            $items[] = [
                'product_id' => $prod_id,
                'quantity' => $qty,
                'buying_price' => $price,
                'subtotal' => $subtotal
            ];
        }
        
        // Determine payment status
        if ($paid_amount >= $total_amount) {
            $paid_amount = $total_amount;
            $payment_status = 'Paid';
        } elseif ($paid_amount <= 0) {
            $paid_amount = 0.00;
            $payment_status = 'Unpaid';
        } else {
            $payment_status = 'Partial';
        }
        
        $balance = $total_amount - $paid_amount;
        $user_id = $_SESSION['user_id'];
        
        // Start Transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Insert Purchase Order Header
            $po_sql = "INSERT INTO purchase_orders (supplier_id, invoice_number, order_date, total_amount, paid_amount, payment_status, payment_method, status, notes, created_by) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $po_stmt = mysqli_prepare($conn, $po_sql);
            mysqli_stmt_bind_param($po_stmt, 'issddssssi', $supplier_id, $invoice_number, $order_date, $total_amount, $paid_amount, $payment_status, $payment_method, $status, $notes, $user_id);
            mysqli_stmt_execute($po_stmt);
            $po_id = mysqli_insert_id($conn);
            mysqli_stmt_close($po_stmt);
            
            // 2. Insert Items & update stocks if received
            $item_sql = "INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, buying_price, total) VALUES (?, ?, ?, ?, ?)";
            $item_stmt = mysqli_prepare($conn, $item_sql);
            
            $stock_stmt = mysqli_prepare($conn, "UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
            $movement_stmt = mysqli_prepare($conn, "INSERT INTO inventory_movements (product_id, type, quantity, reference_id, notes, user_id) VALUES (?, 'Purchase', ?, ?, ?, ?)");
            
            foreach ($items as $item) {
                // Insert PO item
                mysqli_stmt_bind_param($item_stmt, 'iiidd', $po_id, $item['product_id'], $item['quantity'], $item['buying_price'], $item['subtotal']);
                mysqli_stmt_execute($item_stmt);
                
                // If status is received, increment stock and log movement
                if ($status === 'Received') {
                    // Update stock
                    mysqli_stmt_bind_param($stock_stmt, 'ii', $item['quantity'], $item['product_id']);
                    mysqli_stmt_execute($stock_stmt);
                    
                    // Log movement
                    $desc = "Received Purchase Order $invoice_number";
                    mysqli_stmt_bind_param($movement_stmt, 'iiiisi', $item['product_id'], $item['quantity'], $po_id, $desc, $user_id);
                    mysqli_stmt_execute($movement_stmt);
                }
            }
            mysqli_stmt_close($item_stmt);
            mysqli_stmt_close($stock_stmt);
            mysqli_stmt_close($movement_stmt);
            
            // 3. Update Supplier credit balance if received (or ordered depending on accounting, standard is on received/invoiced)
            // We increase balance when PO is Received and has outstanding debt
            if ($status === 'Received' && $balance > 0) {
                $supp_stmt = mysqli_prepare($conn, "UPDATE suppliers SET credit_balance = credit_balance + ? WHERE id = ?");
                mysqli_stmt_bind_param($supp_stmt, 'di', $balance, $supplier_id);
                mysqli_stmt_execute($supp_stmt);
                mysqli_stmt_close($supp_stmt);
            }
            
            log_activity($conn, 'Create Purchase Order', "Recorded PO $invoice_number from supplier $supplier_name. Total: $" . number_format($total_amount, 2) . ". Status: $status");
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => 'Purchase order recorded successfully!',
                'id' => $po_id
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Failed to record purchase order: ' . $e->getMessage()]);
        }
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
        exit();
}
