<?php
/**
 * AJAX Supplier Management Controller
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
    
    // --- LIST SUPPLIERS ---
    case 'list':
        $search = clean_input($_GET['search'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(name LIKE ? OR company_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val, $search_val, $search_val]);
            $types .= "ssss";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total
        $count_sql = "SELECT COUNT(*) FROM suppliers WHERE $where_sql";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch Main
        $data_sql = "SELECT * FROM suppliers WHERE $where_sql ORDER BY name ASC LIMIT ? OFFSET ?";
        $data_stmt = mysqli_prepare($conn, $data_sql);
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $suppliers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $suppliers[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'suppliers' => $suppliers,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    // --- ADD SUPPLIER ---
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
        
        $name = trim($_POST['name'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if (empty($name) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Supplier Name and Phone are required.']);
            exit();
        }
        
        // Check uniqueness of supplier email if provided
        if (!empty($email)) {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM suppliers WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 's', $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                echo json_encode(['success' => false, 'message' => 'Email address already associated with another supplier.']);
                mysqli_stmt_close($check_stmt);
                exit();
            }
            mysqli_stmt_close($check_stmt);
        }
        
        // Insert
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO suppliers (name, company_name, email, phone, address, credit_balance) VALUES (?, ?, ?, ?, ?, 0.00)");
        mysqli_stmt_bind_param($ins_stmt, 'sssss', $name, $company_name, $email, $phone, $address);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            log_activity($conn, 'Add Supplier', "Created supplier: $name ($company_name)");
            echo json_encode(['success' => true, 'message' => 'Supplier created successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving.']);
        }
        mysqli_stmt_close($ins_stmt);
        exit();
        break;
        
    // --- EDIT SUPPLIER ---
    case 'edit':
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
        
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if ($id <= 0 || empty($name) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Required parameters are missing.']);
            exit();
        }
        
        // Check email uniqueness
        if (!empty($email)) {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM suppliers WHERE email = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 'si', $email, $id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                echo json_encode(['success' => false, 'message' => 'Email address already associated with another supplier.']);
                mysqli_stmt_close($check_stmt);
                exit();
            }
            mysqli_stmt_close($check_stmt);
        }
        
        // Update
        $up_stmt = mysqli_prepare($conn, "UPDATE suppliers SET name = ?, company_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_stmt, 'sssssi', $name, $company_name, $email, $phone, $address, $id);
        
        if (mysqli_stmt_execute($up_stmt)) {
            log_activity($conn, 'Update Supplier', "Updated supplier ID $id: $name");
            echo json_encode(['success' => true, 'message' => 'Supplier details updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while updating.']);
        }
        mysqli_stmt_close($up_stmt);
        exit();
        break;
        
    // --- DELETE SUPPLIER ---
    case 'delete':
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid supplier identifier.']);
            exit();
        }
        
        // Block delete if referenced in Products or Purchase Orders
        $prod_check = mysqli_query($conn, "SELECT COUNT(*) FROM products WHERE supplier_id = $id");
        $prod_count = mysqli_fetch_row($prod_check)[0];
        
        $po_check = mysqli_query($conn, "SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = $id");
        $po_count = mysqli_fetch_row($po_check)[0];
        
        if ($prod_count > 0 || $po_count > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Cannot delete supplier: Linked transaction history exists ($prod_count products, $po_count purchase order logs)."
            ]);
            exit();
        }
        
        // Fetch details for logging
        $name_q = mysqli_query($conn, "SELECT name FROM suppliers WHERE id = $id LIMIT 1");
        $name = mysqli_fetch_assoc($name_q)['name'] ?? 'Unknown';
        
        // Delete
        $del_stmt = mysqli_prepare($conn, "DELETE FROM suppliers WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $id);
        
        if (mysqli_stmt_execute($del_stmt)) {
            log_activity($conn, 'Delete Supplier', "Deleted supplier: $name");
            echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while deleting.']);
        }
        mysqli_stmt_close($del_stmt);
        exit();
        break;
        
    // --- PAY BALANCE / LOG PAYMENT ---
    case 'pay_balance':
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
        
        $id = (int)($_POST['id'] ?? 0);
        $pay_amount = (float)($_POST['amount'] ?? 0.00);
        $method = trim($_POST['payment_method'] ?? 'Cash');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($id <= 0 || $pay_amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid payment amount.']);
            exit();
        }
        
        // Fetch current supplier credit balance
        $supp_stmt = mysqli_prepare($conn, "SELECT name, credit_balance FROM suppliers WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($supp_stmt, 'i', $id);
        mysqli_stmt_execute($supp_stmt);
        mysqli_stmt_bind_result($supp_stmt, $name, $credit_balance);
        mysqli_stmt_fetch($supp_stmt);
        mysqli_stmt_close($supp_stmt);
        
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Supplier not found.']);
            exit();
        }
        
        // Apply payment amount to outstanding credit balance
        $new_balance = max(0.00, $credit_balance - $pay_amount);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Update supplier balance
            $up_bal = mysqli_prepare($conn, "UPDATE suppliers SET credit_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_bal, 'di', $new_balance, $id);
            mysqli_stmt_execute($up_bal);
            mysqli_stmt_close($up_bal);
            
            // 2. Loop through supplier's unpaid/partial purchase orders and apply payment
            $po_sql = "SELECT id, total_amount, paid_amount, payment_status 
                       FROM purchase_orders 
                       WHERE supplier_id = ? AND payment_status IN ('Unpaid', 'Partial') 
                       ORDER BY order_date ASC";
                       
            $po_stmt = mysqli_prepare($conn, $po_sql);
            mysqli_stmt_bind_param($po_stmt, 'i', $id);
            mysqli_stmt_execute($po_stmt);
            $po_result = mysqli_stmt_get_result($po_stmt);
            
            $remaining_payment = $pay_amount;
            
            while ($po = mysqli_fetch_assoc($po_result)) {
                if ($remaining_payment <= 0) break;
                
                $po_id = $po['id'];
                $total = (float)$po['total_amount'];
                $paid = (float)$po['paid_amount'];
                $due = $total - $paid;
                
                if ($due <= 0) continue;
                
                if ($remaining_payment >= $due) {
                    // Fully Pay this PO
                    $new_paid = $total;
                    $status = 'Paid';
                    $remaining_payment -= $due;
                } else {
                    // Partially Pay this PO
                    $new_paid = $paid + $remaining_payment;
                    $status = 'Partial';
                    $remaining_payment = 0;
                }
                
                // Update purchase order
                $up_po = mysqli_prepare($conn, "UPDATE purchase_orders SET paid_amount = ?, payment_status = ? WHERE id = ?");
                mysqli_stmt_bind_param($up_po, 'dsi', $new_paid, $status, $po_id);
                mysqli_stmt_execute($up_po);
                mysqli_stmt_close($up_po);
            }
            mysqli_stmt_close($po_stmt);
            
            // Log action
            log_activity($conn, 'Supplier Payment', "Recorded payment of $" . number_format($pay_amount, 2) . " to supplier $name via $method. Remaining balance: $" . number_format($new_balance, 2));
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => "Payment of $" . number_format($pay_amount, 2) . " recorded successfully. Remaining balance: $" . number_format($new_balance, 2)
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid supplier action.']);
        exit();
}
