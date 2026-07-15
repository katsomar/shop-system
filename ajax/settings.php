<?php
/**
 * AJAX App Settings Management Controller
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

$can_edit = has_role(['Administrator', 'Manager']);
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

// Extract variables
$shop_name = trim($_POST['shop_name'] ?? '');
$shop_phone = trim($_POST['shop_phone'] ?? '');
$shop_email = trim($_POST['shop_email'] ?? '');
$shop_address = trim($_POST['shop_address'] ?? '');
$currency_symbol = trim($_POST['currency_symbol'] ?? '$');
$tax_rate = (float)($_POST['tax_rate'] ?? 0.00);
$low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 10);

if (empty($shop_name)) {
    echo json_encode(['success' => false, 'message' => 'Shop Name is a required field.']);
    exit();
}

$settings = [
    'shop_name' => $shop_name,
    'shop_phone' => $shop_phone,
    'shop_email' => $shop_email,
    'shop_address' => $shop_address,
    'currency_symbol' => $currency_symbol,
    'tax_rate' => $tax_rate,
    'low_stock_threshold' => $low_stock_threshold
];

// Start transaction
mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare($conn, "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
    
    foreach ($settings as $key => $value) {
        $val_str = (string)$value;
        mysqli_stmt_bind_param($stmt, 'sss', $key, $val_str, $val_str);
        mysqli_stmt_execute($stmt);
    }
    
    mysqli_stmt_close($stmt);
    
    log_activity($conn, 'Update Settings', "Modified application configurations.");
    
    mysqli_commit($conn);
    
    echo json_encode(['success' => true, 'message' => 'Application configurations updated successfully!']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Database error while saving configurations: ' . $e->getMessage()]);
}
exit();
