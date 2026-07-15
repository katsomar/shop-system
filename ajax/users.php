<?php
/**
 * AJAX User Administration & Role Management Controller
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

$can_view = has_role(['Administrator', 'Manager']);
if (!$can_view) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access to user administration.']);
    exit();
}

$action = clean_input($_GET['action'] ?? 'list');

switch ($action) {
    
    // --- LIST USERS ---
    case 'list':
        $search = clean_input($_GET['search'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val, $search_val]);
            $types .= "sss";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total
        $count_sql = "SELECT COUNT(*) FROM users WHERE $where_sql";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch users details
        $data_sql = "SELECT id, username, full_name, email, role, status, created_at 
                     FROM users 
                     WHERE $where_sql 
                     ORDER BY role ASC, username ASC 
                     LIMIT ? OFFSET ?";
                     
        $data_stmt = mysqli_prepare($conn, $data_sql);
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    // --- ADD NEW USER ---
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        // Verify CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
            exit();
        }
        
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = clean_input($_POST['role'] ?? 'Cashier');
        $status = clean_input($_POST['status'] ?? 'Active');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($full_name) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username, Full Name, and Password are required fields.']);
            exit();
        }
        
        // Validate role values
        $valid_roles = ['Administrator', 'Manager', 'Store Keeper', 'Cashier', 'Accountant'];
        if (!in_array($role, $valid_roles)) {
            echo json_encode(['success' => false, 'message' => 'Invalid user role selected.']);
            exit();
        }
        
        // Check username uniqueness
        $u_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($u_stmt, 's', $username);
        mysqli_stmt_execute($u_stmt);
        mysqli_stmt_store_result($u_stmt);
        if (mysqli_stmt_num_rows($u_stmt) > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists.']);
            mysqli_stmt_close($u_stmt);
            exit();
        }
        mysqli_stmt_close($u_stmt);
        
        // Check email uniqueness if provided
        if (!empty($email)) {
            $e_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($e_stmt, 's', $email);
            mysqli_stmt_execute($e_stmt);
            mysqli_stmt_store_result($e_stmt);
            if (mysqli_stmt_num_rows($e_stmt) > 0) {
                echo json_encode(['success' => false, 'message' => 'Email address already registered.']);
                mysqli_stmt_close($e_stmt);
                exit();
            }
            mysqli_stmt_close($e_stmt);
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_stmt, 'ssssss', $username, $hashed_password, $full_name, $email, $role, $status);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            log_activity($conn, 'Create User', "Registered new user: $username ($role)");
            echo json_encode(['success' => true, 'message' => 'User account created successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving.']);
        }
        mysqli_stmt_close($ins_stmt);
        exit();
        break;
        
    // --- EDIT USER ---
    case 'edit':
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
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = clean_input($_POST['role'] ?? 'Cashier');
        $status = clean_input($_POST['status'] ?? 'Active');
        $password = $_POST['password'] ?? '';
        
        if ($id <= 0 || empty($full_name)) {
            echo json_encode(['success' => false, 'message' => 'User ID and Full Name are required fields.']);
            exit();
        }
        
        // Block suspending self
        if ($id === $_SESSION['user_id'] && $status === 'Inactive') {
            echo json_encode(['success' => false, 'message' => 'You cannot suspend your own active administrator session.']);
            exit();
        }
        
        // Validate role values
        $valid_roles = ['Administrator', 'Manager', 'Store Keeper', 'Cashier', 'Accountant'];
        if (!in_array($role, $valid_roles)) {
            echo json_encode(['success' => false, 'message' => 'Invalid user role selected.']);
            exit();
        }
        
        // Check email uniqueness if provided
        if (!empty($email)) {
            $e_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($e_stmt, 'si', $email, $id);
            mysqli_stmt_execute($e_stmt);
            mysqli_stmt_store_result($e_stmt);
            if (mysqli_stmt_num_rows($e_stmt) > 0) {
                echo json_encode(['success' => false, 'message' => 'Email address already registered to another account.']);
                mysqli_stmt_close($e_stmt);
                exit();
            }
            mysqli_stmt_close($e_stmt);
        }
        
        if (!empty($password)) {
            // Update password as well
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $up_stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, role = ?, status = ?, password = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_stmt, 'sssssi', $full_name, $email, $role, $status, $hashed, $id);
        } else {
            // Update details only
            $up_stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, role = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_stmt, 'ssssi', $full_name, $email, $role, $status, $id);
        }
        
        if (mysqli_stmt_execute($up_stmt)) {
            log_activity($conn, 'Update User', "Updated details for user ID $id. Role: $role, Status: $status");
            echo json_encode(['success' => true, 'message' => 'User profile updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while updating.']);
        }
        mysqli_stmt_close($up_stmt);
        exit();
        break;
        
    // --- TOGGLE USER STATUS (ACTIVE/INACTIVE) ---
    case 'toggle_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            exit();
        }
        
        // Prevent suspending self
        if ($id === $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot toggle status on your own active session account.']);
            exit();
        }
        
        // Get current status
        $st_q = mysqli_query($conn, "SELECT username, status FROM users WHERE id = $id LIMIT 1");
        $user = mysqli_fetch_assoc($st_q);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User profile not found.']);
            exit();
        }
        
        $new_status = $user['status'] === 'Active' ? 'Inactive' : 'Active';
        
        $up_stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_stmt, 'si', $new_status, $id);
        
        if (mysqli_stmt_execute($up_stmt)) {
            log_activity($conn, 'Toggle User Status', "Toggled status of user " . $user['username'] . " to $new_status");
            echo json_encode([
                'success' => true, 
                'message' => "User account is now " . ($new_status === 'Active' ? 'Activated' : 'Suspended') . "."
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        mysqli_stmt_close($up_stmt);
        exit();
        break;
        
    // --- DELETE USER PROFILE ---
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }
        
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user identifier.']);
            exit();
        }
        
        // 1. Self deletion protection
        if ($id === $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account while logged in.']);
            exit();
        }
        
        // 2. Relational dependencies check
        $sales_q = mysqli_query($conn, "SELECT COUNT(*) FROM sales WHERE user_id = $id");
        $sales_count = (int)mysqli_fetch_row($sales_q)[0];
        
        $po_q = mysqli_query($conn, "SELECT COUNT(*) FROM purchase_orders WHERE created_by = $id");
        $po_count = (int)mysqli_fetch_row($po_q)[0];
        
        $ex_q = mysqli_query($conn, "SELECT COUNT(*) FROM expenses WHERE created_by = $id");
        $ex_count = (int)mysqli_fetch_row($ex_q)[0];
        
        $mov_q = mysqli_query($conn, "SELECT COUNT(*) FROM inventory_movements WHERE user_id = $id");
        $mov_count = (int)mysqli_fetch_row($mov_q)[0];
        
        $total_dependencies = $sales_count + $po_count + $ex_count + $mov_count;
        
        if ($total_dependencies > 0) {
            echo json_encode([
                'success' => false,
                'message' => "Cannot delete user. This account has recorded $total_dependencies transaction history log(s) in the database (Sales, Purchases, Expenses, or Stock Movements). Please set their status to 'Inactive' instead to preserve audit logs."
            ]);
            exit();
        }
        
        // Fetch details for logging
        $name_q = mysqli_query($conn, "SELECT username FROM users WHERE id = $id LIMIT 1");
        $username = mysqli_fetch_assoc($name_q)['username'] ?? 'Unknown';
        
        // Perform deletion
        $del_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $id);
        
        if (mysqli_stmt_execute($del_stmt)) {
            log_activity($conn, 'Delete User', "Deleted user account: $username");
            echo json_encode(['success' => true, 'message' => "User account \"$username\" deleted successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while deleting.']);
        }
        mysqli_stmt_close($del_stmt);
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid user action.']);
        exit();
}
