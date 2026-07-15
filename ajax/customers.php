<?php
/**
 * AJAX Customer Management Controller
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

$can_edit = has_role(['Administrator', 'Manager', 'Cashier', 'Accountant']);

$action = clean_input($_GET['action'] ?? 'list');

switch ($action) {
    
    // --- LIST CUSTOMERS ---
    case 'list':
        $search = clean_input($_GET['search'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val, $search_val]);
            $types .= "sss";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total
        $count_sql = "SELECT COUNT(*) FROM customers WHERE $where_sql";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch Customers
        $data_sql = "SELECT * FROM customers WHERE $where_sql ORDER BY name ASC LIMIT ? OFFSET ?";
        $data_stmt = mysqli_prepare($conn, $data_sql);
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $customers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'customers' => $customers,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    // --- ADD CUSTOMER ---
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
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Customer name is required.']);
            exit();
        }
        
        // Check phone uniqueness if provided (excluding walk-ins)
        if (!empty($phone) && strtolower($name) !== 'walk-in customer') {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM customers WHERE phone = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 's', $phone);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                echo json_encode(['success' => false, 'message' => 'Phone number already registered with another customer.']);
                mysqli_stmt_close($check_stmt);
                exit();
            }
            mysqli_stmt_close($check_stmt);
        }
        
        // Insert Customer
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO customers (name, phone, email, address, credit_balance) VALUES (?, ?, ?, ?, 0.00)");
        mysqli_stmt_bind_param($ins_stmt, 'ssss', $name, $phone, $email, $address);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            log_activity($conn, 'Add Customer', "Created customer: $name");
            echo json_encode(['success' => true, 'message' => 'Customer created successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving.']);
        }
        mysqli_stmt_close($ins_stmt);
        exit();
        break;
        
    // --- EDIT CUSTOMER ---
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
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if ($id <= 0 || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Required parameters are missing.']);
            exit();
        }
        
        // Check phone uniqueness
        if (!empty($phone) && strtolower($name) !== 'walk-in customer') {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM customers WHERE phone = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 'si', $phone, $id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                echo json_encode(['success' => false, 'message' => 'Phone number already registered with another customer.']);
                mysqli_stmt_close($check_stmt);
                exit();
            }
            mysqli_stmt_close($check_stmt);
        }
        
        // Update Customer
        $up_stmt = mysqli_prepare($conn, "UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_stmt, 'ssssi', $name, $phone, $email, $address, $id);
        
        if (mysqli_stmt_execute($up_stmt)) {
            log_activity($conn, 'Update Customer', "Updated customer ID $id: $name");
            echo json_encode(['success' => true, 'message' => 'Customer details updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while updating.']);
        }
        mysqli_stmt_close($up_stmt);
        exit();
        break;
        
    // --- DELETE CUSTOMER ---
    case 'delete':
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid customer identifier.']);
            exit();
        }
        
        // Block delete if referenced in Sales
        $sales_check = mysqli_query($conn, "SELECT COUNT(*) FROM sales WHERE customer_id = $id");
        $sales_count = mysqli_fetch_row($sales_check)[0];
        
        if ($sales_count > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Cannot delete customer: This client has $sales_count transaction invoices on record."
            ]);
            exit();
        }
        
        // Fetch details for logging
        $name_q = mysqli_query($conn, "SELECT name FROM customers WHERE id = $id LIMIT 1");
        $name = mysqli_fetch_assoc($name_q)['name'] ?? 'Unknown';
        
        // Delete Customer
        $del_stmt = mysqli_prepare($conn, "DELETE FROM customers WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $id);
        
        if (mysqli_stmt_execute($del_stmt)) {
            log_activity($conn, 'Delete Customer', "Deleted customer: $name");
            echo json_encode(['success' => true, 'message' => 'Customer deleted successfully!']);
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
        
        // Fetch current customer credit balance
        $cust_stmt = mysqli_prepare($conn, "SELECT name, credit_balance FROM customers WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($cust_stmt, 'i', $id);
        mysqli_stmt_execute($cust_stmt);
        mysqli_stmt_bind_result($cust_stmt, $name, $credit_balance);
        mysqli_stmt_fetch($cust_stmt);
        mysqli_stmt_close($cust_stmt);
        
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Customer not found.']);
            exit();
        }
        
        // Apply payment amount to outstanding credit balance
        $new_balance = max(0.00, $credit_balance - $pay_amount);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Update customer balance
            $up_bal = mysqli_prepare($conn, "UPDATE customers SET credit_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_bal, 'di', $new_balance, $id);
            mysqli_stmt_execute($up_bal);
            mysqli_stmt_close($up_bal);
            
            // 2. Loop through customer's unpaid/partial sales and apply payment
            $sale_sql = "SELECT id, total_amount, paid_amount, payment_status 
                         FROM sales 
                         WHERE customer_id = ? AND payment_status IN ('Unpaid', 'Partial') 
                         ORDER BY sale_date ASC";
                         
            $sale_stmt = mysqli_prepare($conn, $sale_sql);
            mysqli_stmt_bind_param($sale_stmt, 'i', $id);
            mysqli_stmt_execute($sale_stmt);
            $sale_result = mysqli_stmt_get_result($sale_stmt);
            
            $remaining_payment = $pay_amount;
            
            while ($sale = mysqli_fetch_assoc($sale_result)) {
                if ($remaining_payment <= 0) break;
                
                $sale_id = $sale['id'];
                $total = (float)$sale['total_amount'];
                $paid = (float)$sale['paid_amount'];
                $due = $total - $paid;
                
                if ($due <= 0) continue;
                
                if ($remaining_payment >= $due) {
                    // Fully Pay this sale
                    $new_paid = $total;
                    $status = 'Paid';
                    $remaining_payment -= $due;
                } else {
                    // Partially Pay this sale
                    $new_paid = $paid + $remaining_payment;
                    $status = 'Partial';
                    $remaining_payment = 0;
                }
                
                // Update sale
                $up_sale = mysqli_prepare($conn, "UPDATE sales SET paid_amount = ?, payment_status = ? WHERE id = ?");
                mysqli_stmt_bind_param($up_sale, 'dsi', $new_paid, $status, $sale_id);
                mysqli_stmt_execute($up_sale);
                mysqli_stmt_close($up_sale);
            }
            mysqli_stmt_close($sale_stmt);
            
            // Log action
            log_activity($conn, 'Customer Payment', "Recorded payment of $" . number_format($pay_amount, 2) . " from customer $name via $method. Remaining balance: $" . number_format($new_balance, 2));
            
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
        
    // --- SAVE CUSTOMER NOTES ---
    case 'save_notes':
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Customer ID is required.']);
            exit();
        }
        
        $up_notes = mysqli_prepare($conn, "UPDATE customers SET notes = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_notes, 'si', $notes, $id);
        
        if (mysqli_stmt_execute($up_notes)) {
            echo json_encode(['success' => true, 'message' => 'Customer notes updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save customer notes.']);
        }
        mysqli_stmt_close($up_notes);
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid customer action.']);
        exit();
}
