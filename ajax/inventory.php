<?php
/**
 * AJAX Inventory Management Controller
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
    
    // --- LIST PRODUCTS WITH INVENTORY METRICS ---
    case 'list':
        $search = clean_input($_GET['search'] ?? '');
        $stock_status = clean_input($_GET['stock_status'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = ["status = 'Active'"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(name LIKE ? OR sku LIKE ? OR barcode LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val, $search_val]);
            $types .= "sss";
        }
        
        if ($stock_status === 'low') {
            $where_clauses[] = "current_stock <= minimum_stock AND current_stock > 0";
        } elseif ($stock_status === 'out') {
            $where_clauses[] = "current_stock <= 0";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total
        $count_sql = "SELECT COUNT(*) FROM products WHERE $where_sql";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch
        $data_sql = "SELECT id, name, sku, barcode, current_stock, minimum_stock, unit, buying_price, selling_price 
                     FROM products 
                     WHERE $where_sql 
                     ORDER BY name ASC 
                     LIMIT ? OFFSET ?";
                     
        $data_stmt = mysqli_prepare($conn, $data_sql);
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $inventory = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $inventory[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'inventory' => $inventory,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    // --- MANUAL STOCK ADJUSTMENT ---
    case 'adjust':
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
        
        $product_id = (int)($_POST['product_id'] ?? 0);
        $adj_type = clean_input($_POST['adjustment_type'] ?? 'Add');
        $qty = (int)($_POST['quantity'] ?? 0);
        $reason = clean_input($_POST['reason'] ?? 'Correction');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($product_id <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid product and quantity.']);
            exit();
        }
        
        // Check product stock levels
        $prod_stmt = mysqli_prepare($conn, "SELECT name, current_stock, unit FROM products WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($prod_stmt, 'i', $product_id);
        mysqli_stmt_execute($prod_stmt);
        mysqli_stmt_bind_result($prod_stmt, $name, $current_stock, $unit);
        mysqli_stmt_fetch($prod_stmt);
        mysqli_stmt_close($prod_stmt);
        
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit();
        }
        
        // Stock Deduction safety checks
        if ($adj_type === 'Deduct') {
            if ($current_stock < $qty) {
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot deduct $qty $unit: Only $current_stock $unit currently available in inventory."
                ]);
                exit();
            }
            $final_qty = -$qty;
        } else {
            $final_qty = $qty;
        }
        
        $new_stock = $current_stock + $final_qty;
        $user_id = $_SESSION['user_id'];
        
        // Determine Movement Type based on reason
        $movement_type = 'Adjustment';
        if ($reason === 'Damage') $movement_type = 'Damage';
        if ($reason === 'Loss') $movement_type = 'Loss';
        
        $desc = "$reason" . (!empty($notes) ? " - $notes" : "");
        
        // Start Transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Update product stock level
            $up_stmt = mysqli_prepare($conn, "UPDATE products SET current_stock = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_stmt, 'ii', $new_stock, $product_id);
            mysqli_stmt_execute($up_stmt);
            mysqli_stmt_close($up_stmt);
            
            // 2. Insert into inventory movements log
            $log_stmt = mysqli_prepare($conn, "INSERT INTO inventory_movements (product_id, type, quantity, notes, user_id) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($log_stmt, 'isisi', $product_id, $movement_type, $final_qty, $desc, $user_id);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
            
            log_activity($conn, 'Inventory Adjustment', "Manually adjusted stock of $name (${adj_type} ${qty} ${unit}). Reason: ${reason}");
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => "Inventory adjusted successfully. New stock: $new_stock $unit"
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }
        exit();
        break;
        
    // --- LIST MOVEMENTS AUDIT LEDGER LOGS ---
    case 'movements':
        $search = clean_input($_GET['search'] ?? '');
        $m_type = clean_input($_GET['movement_type'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(p.name LIKE ? OR p.sku LIKE ? OR im.notes LIKE ? OR u.username LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val, $search_val, $search_val]);
            $types .= "ssss";
        }
        
        if (!empty($m_type)) {
            $where_clauses[] = "im.type = ?";
            $params[] = $m_type;
            $types .= "s";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total
        $count_sql = "SELECT COUNT(*) FROM inventory_movements im 
                      LEFT JOIN products p ON im.product_id = p.id 
                      LEFT JOIN users u ON im.user_id = u.id 
                      WHERE $where_sql";
                      
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch movements with aliased names for compatibility with JS UI
        $data_sql = "SELECT im.id, im.product_id, im.type as movement_type, im.quantity, im.reference_id, im.notes as description, im.created_at, im.user_id, p.name as product_name, p.sku as product_sku, p.unit as product_unit, u.username as handler_name 
                     FROM inventory_movements im 
                     LEFT JOIN products p ON im.product_id = p.id 
                     LEFT JOIN users u ON im.user_id = u.id 
                     WHERE $where_sql 
                     ORDER BY im.created_at DESC, im.id DESC 
                     LIMIT ? OFFSET ?";
                     
        $data_stmt = mysqli_prepare($conn, $data_sql);
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $movements = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $movements[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'movements' => $movements,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
        exit();
}
