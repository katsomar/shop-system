<?php
/**
 * AJAX Product Management Controller
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce authentication
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Check role permissions for products CRUD (Admin, Manager, Store Keeper only)
$can_edit = has_role(['Administrator', 'Manager', 'Store Keeper']);

$action = clean_input($_GET['action'] ?? 'list');

// 1. Process Actions
switch ($action) {
    
    // --- LIST PRODUCTS (PAGINATION, FILTERING) ---
    case 'list':
        header('Content-Type: application/json');
        
        $search = clean_input($_GET['search'] ?? '');
        $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $brand_id = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : 0;
        $stock_status = clean_input($_GET['stock_status'] ?? '');
        $status = clean_input($_GET['status'] ?? '');
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        // Base Query
        $where_clauses = ["1=1"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_clauses[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.description LIKE ?)";
            $search_val = "%$search%";
            $params = array_merge($params, [$search_val, $search_val, $search_val, $search_val]);
            $types .= "ssss";
        }
        
        if ($category_id > 0) {
            $where_clauses[] = "p.category_id = ?";
            $params[] = $category_id;
            $types .= "i";
        }
        
        if ($brand_id > 0) {
            $where_clauses[] = "p.brand_id = ?";
            $params[] = $brand_id;
            $types .= "i";
        }
        
        if (!empty($status)) {
            $where_clauses[] = "p.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($stock_status === 'low') {
            $where_clauses[] = "p.current_stock <= p.minimum_stock AND p.current_stock > 0";
        } elseif ($stock_status === 'out') {
            $where_clauses[] = "p.current_stock = 0";
        } elseif ($stock_status === 'expired') {
            $where_clauses[] = "p.expiry_date IS NOT NULL AND p.expiry_date < CURDATE()";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Count Total Results
        $count_sql = "SELECT COUNT(*) FROM products p WHERE $where_sql";
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        mysqli_stmt_bind_result($count_stmt, $total_records);
        mysqli_stmt_fetch($count_stmt);
        mysqli_stmt_close($count_stmt);
        
        // Fetch Main Data
        $data_sql = "SELECT p.*, c.name as category_name, b.name as brand_name, s.name as supplier_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN brands b ON p.brand_id = b.id 
                     LEFT JOIN suppliers s ON p.supplier_id = s.id 
                     WHERE $where_sql 
                     ORDER BY p.id DESC 
                     LIMIT ? OFFSET ?";
                     
        $data_stmt = mysqli_prepare($conn, $data_sql);
        
        $extended_params = array_merge($params, [$limit, $offset]);
        $extended_types = $types . "ii";
        
        mysqli_stmt_bind_param($data_stmt, $extended_types, ...$extended_params);
        mysqli_stmt_execute($data_stmt);
        $result = mysqli_stmt_get_result($data_stmt);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        mysqli_stmt_close($data_stmt);
        
        echo json_encode([
            'success' => true,
            'products' => $products,
            'total_records' => $total_records,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_records / $limit)
        ]);
        exit();
        break;
        
    // --- ADD NEW PRODUCT ---
    case 'add':
        header('Content-Type: application/json');
        
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
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $barcode = trim($_POST['barcode'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        
        $buying_price = (float)($_POST['buying_price'] ?? 0.00);
        $selling_price = (float)($_POST['selling_price'] ?? 0.00);
        $wholesale_price = (float)($_POST['wholesale_price'] ?? 0.00);
        $current_stock = (int)($_POST['current_stock'] ?? 0);
        $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);
        $maximum_stock = (int)($_POST['maximum_stock'] ?? 1000);
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $tax_rate = (float)($_POST['tax_rate'] ?? 0.00);
        $status = trim($_POST['status'] ?? 'Active');
        
        // Validate Inputs
        if (empty($name) || empty($sku)) {
            echo json_encode(['success' => false, 'message' => 'Name and SKU fields are required.']);
            exit();
        }
        
        if ($selling_price < $buying_price) {
            echo json_encode(['success' => false, 'message' => 'Warning: Selling price should not be lower than buying price.']);
            exit();
        }
        
        // Check SKU uniqueness
        $sku_check = mysqli_prepare($conn, "SELECT id FROM products WHERE sku = ? LIMIT 1");
        mysqli_stmt_bind_param($sku_check, 's', $sku);
        mysqli_stmt_execute($sku_check);
        mysqli_stmt_store_result($sku_check);
        if (mysqli_stmt_num_rows($sku_check) > 0) {
            echo json_encode(['success' => false, 'message' => 'Product SKU must be unique.']);
            mysqli_stmt_close($sku_check);
            exit();
        }
        mysqli_stmt_close($sku_check);
        
        // Check Barcode uniqueness
        if (!empty($barcode)) {
            $bar_check = mysqli_prepare($conn, "SELECT id FROM products WHERE barcode = ? LIMIT 1");
            mysqli_stmt_bind_param($bar_check, 's', $barcode);
            mysqli_stmt_execute($bar_check);
            mysqli_stmt_store_result($bar_check);
            if (mysqli_stmt_num_rows($bar_check) > 0) {
                echo json_encode(['success' => false, 'message' => 'Product Barcode must be unique.']);
                mysqli_stmt_close($bar_check);
                exit();
            }
            mysqli_stmt_close($bar_check);
        }
        
        // Handle Image Upload
        $image_path = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['product_image']['tmp_name'];
            $file_name = $_FILES['product_image']['name'];
            $file_size = $_FILES['product_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($file_ext, $allowed_exts)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed formats: JPG, PNG, WEBP.']);
                exit();
            }
            
            if ($file_size > 2 * 1024 * 1024) { // 2MB
                echo json_encode(['success' => false, 'message' => 'File size exceeds maximum limit of 2MB.']);
                exit();
            }
            
            // Create uploads directory
            $upload_dir = dirname(__DIR__) . '/uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique file name
            $new_file_name = bin2hex(random_bytes(10)) . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $image_path = '/shop-system/uploads/products/' . $new_file_name;
            }
        }
        
        // Execute Insertion
        $ins_sql = "INSERT INTO products (barcode, sku, name, description, category_id, brand_id, supplier_id, buying_price, selling_price, wholesale_price, current_stock, minimum_stock, maximum_stock, unit, expiry_date, tax_rate, status, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $ins_stmt = mysqli_prepare($conn, $ins_sql);
        mysqli_stmt_bind_param($ins_stmt, 'ssssiiidddiiisdsss', 
            $barcode, $sku, $name, $description, $category_id, $brand_id, $supplier_id, 
            $buying_price, $selling_price, $wholesale_price, $current_stock, $minimum_stock, 
            $maximum_stock, $unit, $expiry_date, $tax_rate, $status, $image_path
        );
        
        if (mysqli_stmt_execute($ins_stmt)) {
            $new_product_id = mysqli_insert_id($conn);
            log_activity($conn, 'Add Product', "Created product: $name ($sku), Stock: $current_stock");
            
            // Create stock adjustment log automatically
            if ($current_stock > 0) {
                mysqli_query($conn, "INSERT INTO inventory_movements (product_id, type, quantity, reference_id, notes) VALUES ($new_product_id, 'Stock In', $current_stock, NULL, 'Initial stock on product creation')");
            }
            
            echo json_encode(['success' => true, 'message' => 'Product added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_stmt_error($ins_stmt)]);
        }
        mysqli_stmt_close($ins_stmt);
        exit();
        break;
        
    // --- EDIT PRODUCT ---
    case 'edit':
        header('Content-Type: application/json');
        
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
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $barcode = trim($_POST['barcode'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        
        $buying_price = (float)($_POST['buying_price'] ?? 0.00);
        $selling_price = (float)($_POST['selling_price'] ?? 0.00);
        $wholesale_price = (float)($_POST['wholesale_price'] ?? 0.00);
        $current_stock = (int)($_POST['current_stock'] ?? 0);
        $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);
        $maximum_stock = (int)($_POST['maximum_stock'] ?? 1000);
        
        $unit = trim($_POST['unit'] ?? 'pcs');
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $tax_rate = (float)($_POST['tax_rate'] ?? 0.00);
        $status = trim($_POST['status'] ?? 'Active');
        
        if ($id <= 0 || empty($name) || empty($sku)) {
            echo json_encode(['success' => false, 'message' => 'Required parameters are missing.']);
            exit();
        }
        
        // Fetch current product image path and current stock
        $prev_stmt = mysqli_prepare($conn, "SELECT image_path, current_stock FROM products WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($prev_stmt, 'i', $id);
        mysqli_stmt_execute($prev_stmt);
        mysqli_stmt_bind_result($prev_stmt, $old_image_path, $old_stock);
        mysqli_stmt_fetch($prev_stmt);
        mysqli_stmt_close($prev_stmt);
        
        // Check SKU uniqueness
        $sku_check = mysqli_prepare($conn, "SELECT id FROM products WHERE sku = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($sku_check, 'si', $sku, $id);
        mysqli_stmt_execute($sku_check);
        mysqli_stmt_store_result($sku_check);
        if (mysqli_stmt_num_rows($sku_check) > 0) {
            echo json_encode(['success' => false, 'message' => 'Product SKU must be unique.']);
            mysqli_stmt_close($sku_check);
            exit();
        }
        mysqli_stmt_close($sku_check);
        
        // Check Barcode uniqueness
        if (!empty($barcode)) {
            $bar_check = mysqli_prepare($conn, "SELECT id FROM products WHERE barcode = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($bar_check, 'si', $barcode, $id);
            mysqli_stmt_execute($bar_check);
            mysqli_stmt_store_result($bar_check);
            if (mysqli_stmt_num_rows($bar_check) > 0) {
                echo json_encode(['success' => false, 'message' => 'Product Barcode must be unique.']);
                mysqli_stmt_close($bar_check);
                exit();
            }
            mysqli_stmt_close($bar_check);
        }
        
        // Handle Image Upload
        $image_path = $old_image_path;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['product_image']['tmp_name'];
            $file_name = $_FILES['product_image']['name'];
            $file_size = $_FILES['product_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($file_ext, $allowed_exts)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format.']);
                exit();
            }
            
            if ($file_size > 2 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Image exceeds 2MB limit.']);
                exit();
            }
            
            // Delete old file
            if (!empty($old_image_path)) {
                $old_full_path = dirname(__DIR__) . str_replace('/shop-system', '', $old_image_path);
                if (file_exists($old_full_path)) {
                    @unlink($old_full_path);
                }
            }
            
            $upload_dir = dirname(__DIR__) . '/uploads/products/';
            $new_file_name = bin2hex(random_bytes(10)) . '.' . $file_ext;
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                $image_path = '/shop-system/uploads/products/' . $new_file_name;
            }
        }
        
        // Execute Update
        $up_sql = "UPDATE products SET barcode = ?, sku = ?, name = ?, description = ?, category_id = ?, brand_id = ?, supplier_id = ?, buying_price = ?, selling_price = ?, wholesale_price = ?, current_stock = ?, minimum_stock = ?, maximum_stock = ?, unit = ?, expiry_date = ?, tax_rate = ?, status = ?, image_path = ? WHERE id = ?";
        
        $up_stmt = mysqli_prepare($conn, $up_sql);
        mysqli_stmt_bind_param($up_stmt, 'ssssiiidddiiisdsssi', 
            $barcode, $sku, $name, $description, $category_id, $brand_id, $supplier_id, 
            $buying_price, $selling_price, $wholesale_price, $current_stock, $minimum_stock, 
            $maximum_stock, $unit, $expiry_date, $tax_rate, $status, $image_path, $id
        );
        
        if (mysqli_stmt_execute($up_stmt)) {
            log_activity($conn, 'Update Product', "Updated product: $name ($sku)");
            
            // Log inventory movement if stock manually updated
            if ($current_stock !== $old_stock) {
                $diff = $current_stock - $old_stock;
                $mov_type = $diff > 0 ? 'Stock In' : 'Stock Out';
                $abs_diff = abs($diff);
                mysqli_query($conn, "INSERT INTO inventory_movements (product_id, type, quantity, reference_id, notes) VALUES ($id, '$mov_type', $abs_diff, NULL, 'Manual stock adjustment in edit form')");
            }
            
            echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_stmt_error($up_stmt)]);
        }
        mysqli_stmt_close($up_stmt);
        exit();
        break;
        
    // --- DELETE PRODUCT ---
    case 'delete':
        header('Content-Type: application/json');
        
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product identifier.']);
            exit();
        }
        
        // Check for references in Sales and Purchase order items
        $sales_check = mysqli_query($conn, "SELECT COUNT(*) FROM sale_items WHERE product_id = $id");
        $sales_count = mysqli_fetch_row($sales_check)[0];
        
        $purch_check = mysqli_query($conn, "SELECT COUNT(*) FROM purchase_order_items WHERE product_id = $id");
        $purch_count = mysqli_fetch_row($purch_check)[0];
        
        if ($sales_count > 0 || $purch_count > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete product: It is referenced in existing transaction logs (Sales/Purchases). Set status to Inactive instead.'
            ]);
            exit();
        }
        
        // Get image path to delete file
        $img_stmt = mysqli_prepare($conn, "SELECT name, sku, image_path FROM products WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($img_stmt, 'i', $id);
        mysqli_stmt_execute($img_stmt);
        mysqli_stmt_bind_result($img_stmt, $p_name, $p_sku, $image_path);
        mysqli_stmt_fetch($img_stmt);
        mysqli_stmt_close($img_stmt);
        
        // Execute Delete
        $del_stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $id);
        
        if (mysqli_stmt_execute($del_stmt)) {
            // Delete file from storage
            if (!empty($image_path)) {
                $file_full_path = dirname(__DIR__) . str_replace('/shop-system', '', $image_path);
                if (file_exists($file_full_path)) {
                    @unlink($file_full_path);
                }
            }
            
            log_activity($conn, 'Delete Product', "Deleted product: $p_name ($p_sku)");
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while deleting.']);
        }
        mysqli_stmt_close($del_stmt);
        exit();
        break;
        
    // --- EXPORT PRODUCTS TO CSV ---
    case 'export':
        // Generate and send CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=products_export_' . date('Y-m-d_H-i-s') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Headers row
        fputcsv($output, ['SKU', 'Barcode', 'Name', 'Description', 'Category', 'Brand', 'Supplier', 'Buying Price', 'Selling Price', 'Wholesale Price', 'Stock', 'Min Stock', 'Max Stock', 'Unit', 'Expiry Date', 'Tax Rate', 'Status']);
        
        $sql = "SELECT p.*, c.name as category_name, b.name as brand_name, s.name as supplier_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN suppliers s ON p.supplier_id = s.id 
                ORDER BY p.id ASC";
                
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['sku'],
                $row['barcode'],
                $row['name'],
                $row['description'],
                $row['category_name'] ?? '',
                $row['brand_name'] ?? '',
                $row['supplier_name'] ?? '',
                $row['buying_price'],
                $row['selling_price'],
                $row['wholesale_price'],
                $row['current_stock'],
                $row['minimum_stock'],
                $row['maximum_stock'],
                $row['unit'],
                $row['expiry_date'] ?? '',
                $row['tax_rate'],
                $row['status']
            ]);
        }
        
        fclose($output);
        exit();
        break;
        
    // --- BULK IMPORT FROM CSV ---
    case 'import':
        header('Content-Type: application/json');
        
        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit();
        }
        
        // Verify CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF verification failed.']);
            exit();
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Please upload a valid CSV file.']);
            exit();
        }
        
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        
        $file_handle = fopen($file_tmp, 'r');
        if (!$file_handle) {
            echo json_encode(['success' => false, 'message' => 'Failed to open uploaded file.']);
            exit();
        }
        
        // Read header row
        $headers = fgetcsv($file_handle);
        
        // Basic columns check: must contain SKU and Name at least
        if (!$headers || !in_array('SKU', $headers) || !in_array('Name', $headers)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSV format. Missing required column headers (SKU, Name).']);
            fclose($file_handle);
            exit();
        }
        
        // Map header indices
        $map = array_flip($headers);
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        while (($row = fgetcsv($file_handle)) !== false) {
            // Skip empty rows
            if (empty($row) || count($row) < 2) continue;
            
            $sku = strtoupper(trim($row[$map['SKU']] ?? ''));
            $name = trim($row[$map['Name']] ?? '');
            
            if (empty($sku) || empty($name)) {
                $skipped++;
                continue;
            }
            
            // Check if SKU exists
            $sku_check = mysqli_query($conn, "SELECT id FROM products WHERE sku = '" . mysqli_real_escape_string($conn, $sku) . "' LIMIT 1");
            if (mysqli_num_rows($sku_check) > 0) {
                $skipped++;
                continue;
            }
            
            $barcode = trim($row[$map['Barcode'] ?? -1] ?? '');
            
            // Check if barcode exists
            if (!empty($barcode)) {
                $bar_check = mysqli_query($conn, "SELECT id FROM products WHERE barcode = '" . mysqli_real_escape_string($conn, $barcode) . "' LIMIT 1");
                if (mysqli_num_rows($bar_check) > 0) {
                    $barcode = ''; // Erase conflicting barcode, or could skip
                }
            }
            
            $description = trim($row[$map['Description'] ?? -1] ?? '');
            $category_name = trim($row[$map['Category'] ?? -1] ?? '');
            $brand_name = trim($row[$map['Brand'] ?? -1] ?? '');
            $supplier_name = trim($row[$map['Supplier'] ?? -1] ?? '');
            
            $buying_price = (float)($row[$map['Buying Price'] ?? -1] ?? 0.00);
            $selling_price = (float)($row[$map['Selling Price'] ?? -1] ?? 0.00);
            $wholesale_price = (float)($row[$map['Wholesale Price'] ?? -1] ?? 0.00);
            $current_stock = (int)($row[$map['Stock'] ?? -1] ?? 0);
            $minimum_stock = (int)($row[$map['Min Stock'] ?? -1] ?? 0);
            $maximum_stock = (int)($row[$map['Max Stock'] ?? -1] ?? 1000);
            
            $unit = trim($row[$map['Unit'] ?? -1] ?? 'pcs');
            $expiry_val = trim($row[$map['Expiry Date'] ?? -1] ?? '');
            $expiry_date = !empty($expiry_val) ? date('Y-m-d', strtotime($expiry_val)) : null;
            $tax_rate = (float)($row[$map['Tax Rate'] ?? -1] ?? 0.00);
            $status = trim($row[$map['Status'] ?? -1] ?? 'Active');
            
            // Resolve Category
            $category_id = null;
            if (!empty($category_name)) {
                $cat_q = mysqli_query($conn, "SELECT id FROM categories WHERE name = '" . mysqli_real_escape_string($conn, $category_name) . "' LIMIT 1");
                if ($cat_row = mysqli_fetch_assoc($cat_q)) {
                    $category_id = $cat_row['id'];
                } else {
                    // Create Category
                    mysqli_query($conn, "INSERT INTO categories (name) VALUES ('" . mysqli_real_escape_string($conn, $category_name) . "')");
                    $category_id = mysqli_insert_id($conn);
                }
            }
            
            // Resolve Brand
            $brand_id = null;
            if (!empty($brand_name)) {
                $brand_q = mysqli_query($conn, "SELECT id FROM brands WHERE name = '" . mysqli_real_escape_string($conn, $brand_name) . "' LIMIT 1");
                if ($brand_row = mysqli_fetch_assoc($brand_q)) {
                    $brand_id = $brand_row['id'];
                } else {
                    // Create Brand
                    mysqli_query($conn, "INSERT INTO brands (name) VALUES ('" . mysqli_real_escape_string($conn, $brand_name) . "')");
                    $brand_id = mysqli_insert_id($conn);
                }
            }
            
            // Resolve Supplier
            $supplier_id = null;
            if (!empty($supplier_name)) {
                $supp_q = mysqli_query($conn, "SELECT id FROM suppliers WHERE name = '" . mysqli_real_escape_string($conn, $supplier_name) . "' LIMIT 1");
                if ($supp_row = mysqli_fetch_assoc($supp_q)) {
                    $supplier_id = $supp_row['id'];
                } else {
                    // Create Supplier
                    mysqli_query($conn, "INSERT INTO suppliers (name, phone) VALUES ('" . mysqli_real_escape_string($conn, $supplier_name) . "', '+1 555-000-0000')");
                    $supplier_id = mysqli_insert_id($conn);
                }
            }
            
            // Insert Product
            $ins_sql = "INSERT INTO products (barcode, sku, name, description, category_id, brand_id, supplier_id, buying_price, selling_price, wholesale_price, current_stock, minimum_stock, maximum_stock, unit, expiry_date, tax_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $ins_stmt = mysqli_prepare($conn, $ins_sql);
            mysqli_stmt_bind_param($ins_stmt, 'ssssiiidddiiisdss', 
                $barcode, $sku, $name, $description, $category_id, $brand_id, $supplier_id, 
                $buying_price, $selling_price, $wholesale_price, $current_stock, $minimum_stock, 
                $maximum_stock, $unit, $expiry_date, $tax_rate, $status
            );
            
            if (mysqli_stmt_execute($ins_stmt)) {
                $new_prod_id = mysqli_insert_id($conn);
                $imported++;
                if ($current_stock > 0) {
                    mysqli_query($conn, "INSERT INTO inventory_movements (product_id, type, quantity, notes) VALUES ($new_prod_id, 'Stock In', $current_stock, 'Stock loaded via bulk CSV import')");
                }
            } else {
                $errors[] = "SKU: $sku error: " . mysqli_stmt_error($ins_stmt);
                $skipped++;
            }
            mysqli_stmt_close($ins_stmt);
        }
        
        fclose($file_handle);
        log_activity($conn, 'CSV Bulk Import', "Imported: $imported items, skipped: $skipped.");
        
        echo json_encode([
            'success' => true,
            'message' => "Import processed. successfully imported: $imported product(s), skipped: $skipped product(s).",
            'errors' => $errors
        ]);
        exit();
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid products controller action.']);
        exit();
}
