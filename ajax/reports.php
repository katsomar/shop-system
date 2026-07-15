<?php
/**
 * AJAX Reports & Business Analytics Aggregator
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

$can_view = has_role(['Administrator', 'Manager', 'Accountant']);
if (!$can_view) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized user role.']);
    exit();
}

$action = clean_input($_GET['action'] ?? 'sales');
$start_date = clean_input($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$end_date = clean_input($_GET['end_date'] ?? date('Y-m-d'));

switch ($action) {
    
    // --- SALES ANALYTICS REPORT ---
    case 'sales':
        // 1. Core aggregations
        $sales_sql = "SELECT SUM(grand_total) as total_sales, SUM(tax_amount) as total_tax, SUM(discount_amount) as total_discount, COUNT(id) as total_invoices 
                      FROM sales 
                      WHERE status = 'Completed' AND DATE(sale_date) BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sales_sql);
        mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $totals = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        
        $total_sales = (float)($totals['total_sales'] ?? 0.00);
        $total_tax = (float)($totals['total_tax'] ?? 0.00);
        $total_discount = (float)($totals['total_discount'] ?? 0.00);
        $total_invoices = (int)($totals['total_invoices'] ?? 0);
        
        // 2. Profit calculation (selling price - buying price)
        $profit_sql = "SELECT SUM(si.quantity * (si.selling_price - p.buying_price)) as net_profit 
                       FROM sale_items si 
                       JOIN products p ON si.product_id = p.id 
                       JOIN sales s ON si.sale_id = s.id 
                       WHERE s.status = 'Completed' AND DATE(s.sale_date) BETWEEN ? AND ?";
        $stmt_profit = mysqli_prepare($conn, $profit_sql);
        mysqli_stmt_bind_param($stmt_profit, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt_profit);
        $res_profit = mysqli_stmt_get_result($stmt_profit);
        $profit_row = mysqli_fetch_assoc($res_profit);
        mysqli_stmt_close($stmt_profit);
        
        $total_profit = (float)($profit_row['net_profit'] ?? 0.00);
        $margin = $total_sales > 0 ? ($total_profit / $total_sales * 100) : 0.00;
        
        // 3. Daily timeline breakdown for line chart
        $timeline_sql = "SELECT DATE(sale_date) as date, SUM(grand_total) as daily_sales 
                         FROM sales 
                         WHERE status = 'Completed' AND DATE(sale_date) BETWEEN ? AND ? 
                         GROUP BY DATE(sale_date) 
                         ORDER BY DATE(sale_date) ASC";
        $stmt_t = mysqli_prepare($conn, $timeline_sql);
        mysqli_stmt_bind_param($stmt_t, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt_t);
        $res_t = mysqli_stmt_get_result($stmt_t);
        
        $dates = [];
        $sales_data = [];
        while ($row = mysqli_fetch_assoc($res_t)) {
            $dates[] = date('M d', strtotime($row['date']));
            $sales_data[] = (float)$row['daily_sales'];
        }
        mysqli_stmt_close($stmt_t);
        
        echo json_encode([
            'success' => true,
            'summary' => [
                'total_sales' => $total_sales,
                'total_tax' => $total_tax,
                'total_discount' => $total_discount,
                'total_invoices' => $total_invoices,
                'total_profit' => $total_profit,
                'margin' => $margin
            ],
            'chart' => [
                'labels' => $dates,
                'datasets' => $sales_data
            ]
        ]);
        exit();
        break;
        
    // --- PURCHASES REPORT ---
    case 'purchases':
        $po_sql = "SELECT SUM(total_amount) as total_purchases, SUM(paid_amount) as total_paid, COUNT(id) as total_orders 
                   FROM purchase_orders 
                   WHERE status = 'Received' AND order_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $po_sql);
        mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $totals = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        
        $total_purchases = (float)($totals['total_purchases'] ?? 0.00);
        $total_paid = (float)($totals['total_paid'] ?? 0.00);
        $total_orders = (int)($totals['total_orders'] ?? 0);
        $outstanding_due = max(0.00, $total_purchases - $total_paid);
        
        // Fetch order details for table listing
        $list_sql = "SELECT po.*, s.name as supplier_name 
                     FROM purchase_orders po 
                     LEFT JOIN suppliers s ON po.supplier_id = s.id 
                     WHERE po.status = 'Received' AND po.order_date BETWEEN ? AND ? 
                     ORDER BY po.order_date DESC, po.id DESC";
        $stmt_l = mysqli_prepare($conn, $list_sql);
        mysqli_stmt_bind_param($stmt_l, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt_l);
        $res_l = mysqli_stmt_get_result($stmt_l);
        
        $orders = [];
        while ($row = mysqli_fetch_assoc($res_l)) {
            $orders[] = $row;
        }
        mysqli_stmt_close($stmt_l);
        
        echo json_encode([
            'success' => true,
            'summary' => [
                'total_purchases' => $total_purchases,
                'total_paid' => $total_paid,
                'total_orders' => $total_orders,
                'outstanding_due' => $outstanding_due
            ],
            'orders' => $orders
        ]);
        exit();
        break;
        
    // --- EXPENSES REPORT ---
    case 'expenses':
        // 1. Total expenses sum
        $ex_sql = "SELECT SUM(amount) as total_expenses, COUNT(id) as total_logs 
                   FROM expenses 
                   WHERE expense_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $ex_sql);
        mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $totals = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        
        $total_expenses = (float)($totals['total_expenses'] ?? 0.00);
        $total_logs = (int)($totals['total_logs'] ?? 0);
        
        // 2. Expenses grouped by category (for doughnut charts)
        $cat_sql = "SELECT ec.name as category_name, SUM(e.amount) as category_total 
                    FROM expenses e 
                    JOIN expense_categories ec ON e.category_id = ec.id 
                    WHERE e.expense_date BETWEEN ? AND ? 
                    GROUP BY e.category_id 
                    ORDER BY category_total DESC";
        $stmt_c = mysqli_prepare($conn, $cat_sql);
        mysqli_stmt_bind_param($stmt_c, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt_c);
        $res_c = mysqli_stmt_get_result($stmt_c);
        
        $categories = [];
        $amounts = [];
        $table_rows = [];
        while ($row = mysqli_fetch_assoc($res_c)) {
            $categories[] = $row['category_name'];
            $amounts[] = (float)$row['category_total'];
            $table_rows[] = $row;
        }
        mysqli_stmt_close($stmt_c);
        
        echo json_encode([
            'success' => true,
            'summary' => [
                'total_expenses' => $total_expenses,
                'total_logs' => $total_logs
            ],
            'chart' => [
                'labels' => $categories,
                'datasets' => $amounts
            ],
            'categories' => $table_rows
        ]);
        exit();
        break;
        
    // --- PROFIT & LOSS STATEMENT ---
    case 'pl':
        // 1. Gross Sales Revenue (Net of discounts)
        $sales_sql = "SELECT SUM(grand_total) as gross_sales 
                      FROM sales 
                      WHERE status = 'Completed' AND DATE(sale_date) BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sales_sql);
        mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $gross_sales = (float)(mysqli_fetch_assoc($res)['gross_sales'] ?? 0.00);
        mysqli_stmt_close($stmt);
        
        // 2. Customer Returns Outflows
        $returns_sql = "SELECT SUM(total_amount) as customer_returns 
                        FROM returns 
                        WHERE type = 'Customer' AND return_date BETWEEN ? AND ?";
        $stmt_r = mysqli_prepare($conn, $returns_sql);
        mysqli_stmt_bind_param($stmt_r, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt_r);
        $res_r = mysqli_stmt_get_result($stmt_r);
        $customer_returns = (float)(mysqli_fetch_assoc($res_r)['customer_returns'] ?? 0.00);
        mysqli_stmt_close($stmt_r);
        
        $net_sales = max(0.00, $gross_sales - $customer_returns);
        
        // 3. COGS (Cost of Goods Sold: quantity * products buying price)
        $cogs_sql = "SELECT SUM(si.quantity * p.buying_price) as cogs 
                     FROM sale_items si 
                     JOIN products p ON si.product_id = p.id 
                     JOIN sales s ON si.sale_id = s.id 
                     WHERE s.status = 'Completed' AND DATE(s.sale_date) BETWEEN ? AND ?";
        $stmt_c = mysqli_prepare($conn, $cogs_sql);
        mysqli_stmt_bind_param($stmt_c, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt_c);
        $res_c = mysqli_stmt_get_result($stmt_c);
        $cogs = (float)(mysqli_fetch_assoc($res_c)['cogs'] ?? 0.00);
        mysqli_stmt_close($stmt_c);
        
        $gross_profit = max(0.00, $net_sales - $cogs);
        
        // 4. Operating Expenses
        $ex_sql = "SELECT SUM(amount) as expenses_total 
                   FROM expenses 
                   WHERE expense_date BETWEEN ? AND ?";
        $stmt_e = mysqli_prepare($conn, $ex_sql);
        mysqli_stmt_bind_param($stmt_e, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt_e);
        $res_e = mysqli_stmt_get_result($stmt_e);
        $operating_expenses = (float)(mysqli_fetch_assoc($res_e)['expenses_total'] ?? 0.00);
        mysqli_stmt_close($stmt_e);
        
        $net_profit = $gross_profit - $operating_expenses;
        
        echo json_encode([
            'success' => true,
            'statement' => [
                'start_date' => date('M d, Y', strtotime($start_date)),
                'end_date' => date('M d, Y', strtotime($end_date)),
                'gross_sales' => $gross_sales,
                'customer_returns' => $customer_returns,
                'net_sales' => $net_sales,
                'cogs' => $cogs,
                'gross_profit' => $gross_profit,
                'operating_expenses' => $operating_expenses,
                'net_profit' => $net_profit
            ]
        ]);
        exit();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid report action parameter.']);
        exit();
}
