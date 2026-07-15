<?php
/**
 * AJAX POS Sales Operations Controller
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

$can_edit = has_role(['Administrator', 'Manager', 'Cashier']);

$action = clean_input($_GET['action'] ?? 'products');

switch ($action) {
    
    // --- FETCH ACTIVE PRODUCTS CATALOG ---
    case 'products':
        $search = clean_input($_GET['search'] ?? '');
        $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        
        $where_clauses = ["status = 'Active'"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(name LIKE ? OR sku LIKE ? OR barcode LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val, $search_val]);
            $types .= "sss";
        }
        
        if ($category_id > 0) {
            $where_clauses[] = "category_id = ?";
            $params[] = $category_id;
            $types .= "i";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE $where_sql 
                ORDER BY p.name ASC";
                
        $stmt = mysqli_prepare($conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
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
        
    // --- TRANSACTION CHECKOUT ---
    case 'checkout':
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
        
        $customer_id = (int)($_POST['customer_id'] ?? 1); // Defaults to Walk-in
        $discount = (float)($_POST['discount'] ?? 0.00);
        $tax_rate = (float)($_POST['tax_rate'] ?? 0.00);
        $payment_method = clean_input($_POST['payment_method'] ?? 'Cash');
        $paid_amount = (float)($_POST['paid_amount'] ?? 0.00);
        $notes = trim($_POST['notes'] ?? '');
        
        $product_ids = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $selling_prices = $_POST['selling_prices'] ?? [];
        
        if (empty($product_ids)) {
            echo json_encode(['success' => false, 'message' => 'Your shopping cart is empty.']);
            exit();
        }
        
        // Validate customer
        $cust_q = mysqli_query($conn, "SELECT name, credit_balance FROM customers WHERE id = $customer_id LIMIT 1");
        if (mysqli_num_rows($cust_q) === 0) {
            echo json_encode(['success' => false, 'message' => 'Selected customer profile does not exist.']);
            exit();
        }
        $cust = mysqli_fetch_assoc($cust_q);
        
        // Enforce credit sales block on Walk-in generic profiles
        if (($payment_method === 'Credit' || $paid_amount < 1) && strtolower($cust['name']) === 'walk-in customer') {
            if ($payment_method === 'Credit') {
                echo json_encode(['success' => false, 'message' => 'Credit sales require a registered customer profile (cannot use Walk-in Customer).']);
                exit();
            }
        }
        
        // Items verification and calculation
        $items = [];
        $subtotal = 0.00;
        
        for ($i = 0; $i < count($product_ids); $i++) {
            $p_id = (int)$product_ids[$i];
            $qty = (int)$quantities[$i];
            $price = (float)$selling_prices[$i];
            
            if ($p_id <= 0 || $qty <= 0 || $price < 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product values found in cart.']);
                exit();
            }
            
            $item_subtotal = $qty * $price;
            $subtotal += $item_subtotal;
            
            $items[] = [
                'product_id' => $p_id,
                'quantity' => $qty,
                'selling_price' => $price,
                'subtotal' => $item_subtotal
            ];
        }
        
        // Subtotal after discounts
        $discounted_total = max(0.00, $subtotal - $discount);
        $tax_amount = $discounted_total * ($tax_rate / 100);
        $grand_total = $discounted_total + $tax_amount;
        
        // Calculate payment balance and status
        if ($paid_amount >= $grand_total) {
            $paid_amount = $grand_total;
            $payment_status = 'Paid';
        } elseif ($paid_amount <= 0) {
            $paid_amount = 0.00;
            $payment_status = 'Unpaid';
        } else {
            $payment_status = 'Partial';
        }
        
        $balance = $grand_total - $paid_amount;
        $invoice_number = "INV-" . date('Ymd') . "-" . rand(1000, 9999);
        $user_id = $_SESSION['user_id'];
        
        // Start Transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Lock and verify stock first to avoid race conditions
            foreach ($items as $item) {
                $stock_q = mysqli_query($conn, "SELECT name, current_stock FROM products WHERE id = " . $item['product_id'] . " FOR UPDATE");
                $prod = mysqli_fetch_assoc($stock_q);
                
                if (!$prod) {
                    throw new Exception("Product ID " . $item['product_id'] . " not found in database.");
                }
                
                if ($prod['current_stock'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for product \"" . $prod['name'] . "\". Current available: " . $prod['current_stock'] . " " . ($prod['unit'] ?? 'pcs'));
                }
            }
            
            // 2. Insert Sale Header
            $sale_sql = "INSERT INTO sales (customer_id, invoice_number, sale_date, subtotal, tax_amount, discount_amount, grand_total, paid_amount, balance_amount, payment_method, payment_status, status, notes, user_id) 
                         VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?, ?)";
            $sale_stmt = mysqli_prepare($conn, $sale_sql);
            mysqli_stmt_bind_param($sale_stmt, 'isddddddssssi', $customer_id, $invoice_number, $subtotal, $tax_amount, $discount, $grand_total, $paid_amount, $balance, $payment_method, $payment_status, $notes, $user_id);
            mysqli_stmt_execute($sale_stmt);
            $sale_id = mysqli_insert_id($conn);
            mysqli_stmt_close($sale_stmt);
            
            // 3. Insert Sale Items, update stock and log inventory movement
            $item_stmt = mysqli_prepare($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, total) VALUES (?, ?, ?, ?, ?)");
            $stock_stmt = mysqli_prepare($conn, "UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
            $movement_stmt = mysqli_prepare($conn, "INSERT INTO inventory_movements (product_id, type, quantity, reference_id, notes, user_id) VALUES (?, 'Sale', ?, ?, ?, ?)");
            
            foreach ($items as $item) {
                // Insert item
                mysqli_stmt_bind_param($item_stmt, 'iiidd', $sale_id, $item['product_id'], $item['quantity'], $item['selling_price'], $item['subtotal']);
                mysqli_stmt_execute($item_stmt);
                
                // Deduct stock
                mysqli_stmt_bind_param($stock_stmt, 'ii', $item['quantity'], $item['product_id']);
                mysqli_stmt_execute($stock_stmt);
                
                // Log movement
                $desc = "POS Sale Invoice $invoice_number";
                mysqli_stmt_bind_param($movement_stmt, 'iiiisi', $item['product_id'], $item['quantity'], $sale_id, $desc, $user_id);
                mysqli_stmt_execute($movement_stmt);
            }
            mysqli_stmt_close($item_stmt);
            mysqli_stmt_close($stock_stmt);
            mysqli_stmt_close($movement_stmt);
            
            // 4. Update Customer credit balance if credit balance or payment status is unpaid/partial
            if ($balance > 0) {
                $cust_stmt = mysqli_prepare($conn, "UPDATE customers SET credit_balance = credit_balance + ? WHERE id = ?");
                mysqli_stmt_bind_param($cust_stmt, 'di', $balance, $customer_id);
                mysqli_stmt_execute($cust_stmt);
                mysqli_stmt_close($cust_stmt);
            }
            
            log_activity($conn, 'POS Sale', "Completed sale $invoice_number to customer ID $customer_id. Grand Total: $" . number_format($grand_total, 2));
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => 'Checkout completed successfully!',
                'sale_id' => $sale_id
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
        exit();
}
