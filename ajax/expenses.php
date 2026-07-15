<?php
/**
 * AJAX Expenses and Expense Categories Controller
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

$can_edit = has_role(['Administrator', 'Manager', 'Accountant']);

$action = clean_input($_GET['action'] ?? 'list');

switch ($action) {
    
    // --- LIST EXPENSES ---
    case 'list':
        $search = clean_input($_GET['search'] ?? '');
        $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $payment_method = clean_input($_GET['payment_method'] ?? '');
        $start_date = clean_input($_GET['start_date'] ?? '');
        $end_date = clean_input($_GET['end_date'] ?? '');
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(e.reference_no LIKE ? OR e.notes LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val]);
            $types .= "ss";
        }
        
        if ($category_id > 0) {
            $where_clauses[] = "e.category_id = ?";
            $params[] = $category_id;
            $types .= "i";
        }
        
        if (!empty($payment_method)) {
            $where_clauses[] = "e.payment_method = ?";
            $params[] = $payment_method;
            $types .= "s";
        }
        
        if (!empty($start_date)) {
            $where_clauses[] = "e.expense_date >= ?";
            $params[] = $start_date;
            $types .= "s";
        }
        
        if (!empty($end_date)) {
            $where_clauses[] = "e.expense_date <= ?";
            $params[] = $end_date;
            $types .= "s";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total
        $count_sql = "SELECT COUNT(*) FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE $where_sql";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch Expenses
        $data_sql = "SELECT e.*, ec.name as category_name, u.username as handler_name 
                     FROM expenses e 
                     LEFT JOIN expense_categories ec ON e.category_id = ec.id 
                     LEFT JOIN users u ON e.created_by = u.id 
                     WHERE $where_sql 
                     ORDER BY e.expense_date DESC, e.id DESC 
                     LIMIT ? OFFSET ?";
                     
        $data_stmt = mysqli_prepare($conn, $data_sql);
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $expenses = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $expenses[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'expenses' => $expenses,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    // --- ADD EXPENSE ---
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
        
        $category_id = (int)($_POST['category_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0.00);
        $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $reference_no = trim($_POST['reference_no'] ?? '');
        $payment_method = clean_input($_POST['payment_method'] ?? 'Cash');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($category_id <= 0 || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Category and Amount are required.']);
            exit();
        }
        
        // Validate Category
        $cat_check = mysqli_query($conn, "SELECT name FROM expense_categories WHERE id = $category_id LIMIT 1");
        if (mysqli_num_rows($cat_check) === 0) {
            echo json_encode(['success' => false, 'message' => 'Selected expense category does not exist.']);
            exit();
        }
        $cat_name = mysqli_fetch_assoc($cat_check)['name'];
        
        $user_id = $_SESSION['user_id'];
        
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO expenses (category_id, amount, expense_date, reference_no, payment_method, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_stmt, 'idssssi', $category_id, $amount, $expense_date, $reference_no, $payment_method, $notes, $user_id);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            log_activity($conn, 'Add Expense', "Logged expense: $cat_name ($reference_no) for $" . number_format($amount, 2));
            echo json_encode(['success' => true, 'message' => 'Expense logged successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while logging.']);
        }
        mysqli_stmt_close($ins_stmt);
        exit();
        break;
        
    // --- EDIT EXPENSE ---
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
        $category_id = (int)($_POST['category_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0.00);
        $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $reference_no = trim($_POST['reference_no'] ?? '');
        $payment_method = clean_input($_POST['payment_method'] ?? 'Cash');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($id <= 0 || $category_id <= 0 || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Required parameters are missing.']);
            exit();
        }
        
        $up_stmt = mysqli_prepare($conn, "UPDATE expenses SET category_id = ?, amount = ?, expense_date = ?, reference_no = ?, payment_method = ?, notes = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_stmt, 'idssssi', $category_id, $amount, $expense_date, $reference_no, $payment_method, $notes, $id);
        
        if (mysqli_stmt_execute($up_stmt)) {
            log_activity($conn, 'Update Expense', "Updated expense ID $id. Amount: $" . number_format($amount, 2));
            echo json_encode(['success' => true, 'message' => 'Expense updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while updating.']);
        }
        mysqli_stmt_close($up_stmt);
        exit();
        break;
        
    // --- DELETE EXPENSE ---
    case 'delete':
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid expense identifier.']);
            exit();
        }
        
        // Fetch details for logging
        $ex_q = mysqli_query($conn, "SELECT e.amount, ec.name FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id WHERE e.id = $id LIMIT 1");
        $ex = mysqli_fetch_assoc($ex_q);
        $log_desc = $ex ? "Deleted expense log: " . $ex['name'] . " ($" . number_format($ex['amount'], 2) . ")" : "Deleted expense ID $id";
        
        $del_stmt = mysqli_prepare($conn, "DELETE FROM expenses WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $id);
        
        if (mysqli_stmt_execute($del_stmt)) {
            log_activity($conn, 'Delete Expense', $log_desc);
            echo json_encode(['success' => true, 'message' => 'Expense log deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while deleting.']);
        }
        mysqli_stmt_close($del_stmt);
        exit();
        break;
        
    // --- LIST EXPENSE CATEGORIES ---
    case 'category_list':
        $search = clean_input($_GET['search'] ?? '');
        $where = "1=1";
        if (!empty($search)) {
            $where = "ec.name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
        }
        
        $sql = "SELECT ec.*, COUNT(e.id) as expense_count 
                FROM expense_categories ec 
                LEFT JOIN expenses e ON ec.id = e.category_id 
                WHERE $where 
                GROUP BY ec.id 
                ORDER BY ec.name ASC";
                
        $result = mysqli_query($conn, $sql);
        $categories = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $categories[] = $row;
            }
        }
        
        echo json_encode($categories);
        exit();
        break;
        
    // --- ADD EXPENSE CATEGORY ---
    case 'category_add':
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
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit();
        }
        
        // Uniqueness check
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM expense_categories WHERE name = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, 's', $name);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            echo json_encode(['success' => false, 'message' => 'Expense category name already exists.']);
            mysqli_stmt_close($check_stmt);
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO expense_categories (name, description) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins_stmt, 'ss', $name, $description);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            log_activity($conn, 'Add Expense Category', "Created expense category: $name");
            echo json_encode(['success' => true, 'message' => 'Category created successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving.']);
        }
        mysqli_stmt_close($ins_stmt);
        exit();
        break;
        
    // --- EDIT EXPENSE CATEGORY ---
    case 'category_edit':
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
        $description = trim($_POST['description'] ?? '');
        
        if ($id <= 0 || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Required parameters are missing.']);
            exit();
        }
        
        // Uniqueness check (excluding current)
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM expense_categories WHERE name = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, 'si', $name, $id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            echo json_encode(['success' => false, 'message' => 'Expense category name already exists.']);
            mysqli_stmt_close($check_stmt);
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        $up_stmt = mysqli_prepare($conn, "UPDATE expense_categories SET name = ?, description = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_stmt, 'ssi', $name, $description, $id);
        
        if (mysqli_stmt_execute($up_stmt)) {
            log_activity($conn, 'Update Expense Category', "Updated expense category ID $id: $name");
            echo json_encode(['success' => true, 'message' => 'Category updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while updating.']);
        }
        mysqli_stmt_close($up_stmt);
        exit();
        break;
        
    // --- DELETE EXPENSE CATEGORY ---
    case 'category_delete':
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid category identifier.']);
            exit();
        }
        
        // Block delete if referenced in Expenses
        $ex_check = mysqli_query($conn, "SELECT COUNT(*) FROM expenses WHERE category_id = $id");
        $ex_count = mysqli_fetch_row($ex_check)[0];
        
        if ($ex_count > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Cannot delete category: It contains $ex_count logged expense(s). Reassign or delete them first."
            ]);
            exit();
        }
        
        // Fetch details for logging
        $name_q = mysqli_query($conn, "SELECT name FROM expense_categories WHERE id = $id LIMIT 1");
        $name = mysqli_fetch_assoc($name_q)['name'] ?? 'Unknown';
        
        // Delete
        $del_stmt = mysqli_prepare($conn, "DELETE FROM expense_categories WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $id);
        
        if (mysqli_stmt_execute($del_stmt)) {
            log_activity($conn, 'Delete Expense Category', "Deleted expense category: $name");
            echo json_encode(['success' => true, 'message' => 'Category deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while deleting.']);
        }
        mysqli_stmt_close($del_stmt);
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid expense action.']);
        exit();
}
