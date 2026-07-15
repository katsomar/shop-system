<?php
/**
 * AJAX Category Management Controller
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

// User Permission Check for Category CRUD (Admin, Manager, Store Keeper only)
$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper']);

$action = clean_input($_GET['action'] ?? 'list');

switch ($action) {
    
    // --- LIST CATEGORIES ---
    case 'list':
        $search = clean_input($_GET['search'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(c.name LIKE ? OR c.description LIKE ?)";
            $search_val = "%$search%";
            $params[] = $search_val;
            $params[] = $search_val;
            $types .= "ss";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total Results
        $count_sql = "SELECT COUNT(*) FROM categories c WHERE $where_sql";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch Categories with dynamic products count in one query
        $data_sql = "SELECT c.*, COUNT(p.id) as product_count 
                     FROM categories c 
                     LEFT JOIN products p ON c.id = p.category_id 
                     WHERE $where_sql 
                     GROUP BY c.id 
                     ORDER BY c.name ASC 
                     LIMIT ? OFFSET ?";
                     
        $data_stmt = mysqli_prepare($conn, $data_sql);
        
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $categories = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'categories' => $categories,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    // --- ADD CATEGORY ---
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
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit();
        }
        
        // Check uniqueness
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE name = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, 's', $name);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            echo json_encode(['success' => false, 'message' => 'Category name already exists.']);
            mysqli_stmt_close($check_stmt);
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        // Insert Category
        $ins_stmt = mysqli_prepare($conn, "INSERT INTO categories (name, description) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins_stmt, 'ss', $name, $description);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            log_activity($conn, 'Add Category', "Created category: $name");
            echo json_encode(['success' => true, 'message' => 'Category created successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving.']);
        }
        mysqli_stmt_close($ins_stmt);
        exit();
        break;
        
    // --- UPDATE CATEGORY ---
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
        $description = trim($_POST['description'] ?? '');
        
        if ($id <= 0 || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Required parameters are missing.']);
            exit();
        }
        
        // Check uniqueness (excluding current category ID)
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE name = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, 'si', $name, $id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            echo json_encode(['success' => false, 'message' => 'Category name already exists.']);
            mysqli_stmt_close($check_stmt);
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        // Update Category
        $up_stmt = mysqli_prepare($conn, "UPDATE categories SET name = ?, description = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_stmt, 'ssi', $name, $description, $id);
        
        if (mysqli_stmt_execute($up_stmt)) {
            log_activity($conn, 'Update Category', "Updated category ID $id: $name");
            echo json_encode(['success' => true, 'message' => 'Category updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while updating.']);
        }
        mysqli_stmt_close($up_stmt);
        exit();
        break;
        
    // --- DELETE CATEGORY ---
    case 'delete':
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid category identifier.']);
            exit();
        }
        
        // Check relational dependencies
        $check_prod = mysqli_query($conn, "SELECT COUNT(*) FROM products WHERE category_id = $id");
        $prod_count = mysqli_fetch_row($check_prod)[0];
        
        if ($prod_count > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Cannot delete category: It contains $prod_count product(s). Reassign them to another category first."
            ]);
            exit();
        }
        
        // Fetch name for activity logging
        $name_q = mysqli_query($conn, "SELECT name FROM categories WHERE id = $id LIMIT 1");
        $cat_name = mysqli_fetch_assoc($name_q)['name'] ?? 'Unknown';
        
        // Delete Category
        $del_stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $id);
        
        if (mysqli_stmt_execute($del_stmt)) {
            log_activity($conn, 'Delete Category', "Deleted category: $cat_name");
            echo json_encode(['success' => true, 'message' => 'Category deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while deleting.']);
        }
        mysqli_stmt_close($del_stmt);
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
        exit();
}
