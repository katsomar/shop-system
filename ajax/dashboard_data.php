<?php
/**
 * AJAX Dashboard Data Provider
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// --------------------------------------------------------------------------
// Auto-Seed Demo Data if Database is Empty
// --------------------------------------------------------------------------
$check_products = mysqli_query($conn, "SELECT COUNT(*) FROM products");
$product_count = 0;
if ($check_products) {
    $row = mysqli_fetch_row($check_products);
    $product_count = (int)$row[0];
}

if ($product_count === 0) {
    // Let's seed demo data to make the system instantly interactive!
    
    // 1. Categories
    mysqli_query($conn, "INSERT IGNORE INTO categories (id, name, description) VALUES 
        (1, 'Electronics', 'Phones, computers, and visual devices'),
        (2, 'Clothing & Apparel', 'Shirts, shoes, and sportswear'),
        (3, 'Groceries & Foods', 'Fresh produce, beverages, bread'),
        (4, 'Home Appliances', 'Kettles, microwave ovens, and cooling')");
        
    // 2. Brands
    mysqli_query($conn, "INSERT IGNORE INTO brands (id, name, description) VALUES 
        (1, 'Apple', 'Premium smartphones and tablets'),
        (2, 'Samsung', 'Electronics and display panels'),
        (3, 'Nike', 'Activewear and sports footwear'),
        (4, 'Sony', 'Audio equipment and gaming')");

    // 3. Suppliers
    mysqli_query($conn, "INSERT IGNORE INTO suppliers (id, name, company_name, email, phone, address) VALUES 
        (1, 'Tech Distributors Inc', 'TechDist Inc', 'sales@techdist.com', '+1 555-900-1111', 'San Jose, California'),
        (2, 'Apex Apparel Corp', 'Apex Corp', 'orders@apexapparel.com', '+1 555-900-2222', 'Portland, Oregon'),
        (3, 'Global Harvest Foods', 'Global Foods', 'wholesale@globalharvest.com', '+1 555-900-3333', 'Chicago, Illinois')");

    // 4. Customers
    mysqli_query($conn, "INSERT IGNORE INTO customers (id, name, email, phone, address, credit_balance) VALUES 
        (1, 'Walk-in Customer', NULL, NULL, NULL, 0.00),
        (2, 'John Doe', 'john.doe@gmail.com', '+1 555-888-0001', 'New York City, NY', 120.00),
        (3, 'Jane Smith', 'jane.smith@outlook.com', '+1 555-888-0002', 'Los Angeles, CA', 0.00)");

    // 5. Products
    // Seed iPhones, Sony Headsets, Nike shoes, and bread (some low-stock, some out-of-stock, some expired)
    $today = date('Y-m-d');
    $expired_date = date('Y-m-d', strtotime('-5 days'));
    $expiry_soon = date('Y-m-d', strtotime('+3 days'));
    
    mysqli_query($conn, "INSERT IGNORE INTO products (id, barcode, sku, name, description, category_id, brand_id, supplier_id, buying_price, selling_price, wholesale_price, current_stock, minimum_stock, maximum_stock, unit, expiry_date, tax_rate, status) VALUES 
        (1, '190198000123', 'APP-IPH14', 'iPhone 14 Pro Max 256GB', 'Apple flagship device', 1, 1, 1, 800.00, 999.00, 950.00, 24, 5, 100, 'pcs', NULL, 8.25, 'Active'),
        (2, '880609000456', 'SAM-TV55', 'Samsung 55\" Crystal UHD 4K', 'Smart TV with HDR', 1, 2, 1, 350.00, 499.00, 450.00, 8, 2, 20, 'pcs', NULL, 8.25, 'Active'),
        (3, '490552000789', 'SON-WH1000', 'Sony WH-1000XM5 Headphones', 'Wireless noise cancelling headset', 1, 4, 1, 220.00, 349.00, 310.00, 3, 5, 30, 'pcs', NULL, 8.25, 'Active'), -- Low Stock (stock 3 <= min 5)
        (4, '019315000321', 'NIK-AIRMAX', 'Nike Air Max 270 Running Shoes', 'Breathable athletic shoe', 2, 3, 2, 70.00, 120.00, 110.00, 0, 3, 50, 'pairs', NULL, 8.25, 'Active'), -- Out of stock
        (5, '000000000111', 'GRO-BREAD', 'Fresh Whole Wheat Bread', 'Organic bakery product', 3, NULL, 3, 1.20, 2.50, 2.20, 15, 5, 40, 'pcs', '$expired_date', 0.00, 'Active'), -- Expired
        (6, '000000000222', 'GRO-MILK', 'Organic Whole Milk 1 Gallon', 'Dairy products', 3, NULL, 3, 2.00, 3.89, 3.50, 32, 10, 80, 'bottles', '$expiry_soon', 0.00, 'Active')");

    // 6. Seed Sales & Sale Items for the last 7 days
    $cashier_id = 3; // Seeded Cashier User ID
    
    // Day -6 sale
    $date6 = date('Y-m-d H:i:s', strtotime('-6 days 14:30:00'));
    mysqli_query($conn, "INSERT INTO sales (id, invoice_number, customer_id, user_id, sale_date, subtotal, tax_amount, discount_amount, grand_total, paid_amount, balance_amount, payment_method, payment_status, status) VALUES 
        (1, 'INV-2026-0001', 2, $cashier_id, '$date6', 1348.00, 111.21, 20.00, 1439.21, 1439.21, 0.00, 'Card', 'Paid', 'Completed')");
    mysqli_query($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, tax_amount, discount_amount, total) VALUES 
        (1, 1, 1, 999.00, 82.42, 20.00, 1061.42),
        (1, 3, 1, 349.00, 28.79, 0.00, 377.79)");
    
    // Day -4 sale
    $date4 = date('Y-m-d H:i:s', strtotime('-4 days 10:15:00'));
    mysqli_query($conn, "INSERT INTO sales (id, invoice_number, customer_id, user_id, sale_date, subtotal, tax_amount, discount_amount, grand_total, paid_amount, balance_amount, payment_method, payment_status, status) VALUES 
        (2, 'INV-2026-0002', 1, $cashier_id, '$date4', 502.89, 41.17, 0.00, 544.06, 544.06, 0.00, 'Cash', 'Paid', 'Completed')");
    mysqli_query($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, tax_amount, discount_amount, total) VALUES 
        (2, 2, 1, 499.00, 41.17, 0.00, 540.17),
        (2, 6, 1, 3.89, 0.00, 0.00, 3.89)");

    // Day -2 sale
    $date2 = date('Y-m-d H:i:s', strtotime('-2 days 18:45:00'));
    mysqli_query($conn, "INSERT INTO sales (id, invoice_number, customer_id, user_id, sale_date, subtotal, tax_amount, discount_amount, grand_total, paid_amount, balance_amount, payment_method, payment_status, status) VALUES 
        (3, 'INV-2026-0003', 2, $cashier_id, '$date2', 352.89, 28.79, 10.00, 371.68, 251.68, 120.00, 'Credit', 'Partial', 'Completed')");
    mysqli_query($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, tax_amount, discount_amount, total) VALUES 
        (3, 3, 1, 349.00, 28.79, 10.00, 367.79),
        (3, 6, 1, 3.89, 0.00, 0.00, 3.89)");
        
    // Day 0 (Today) Sales
    $date0_1 = date('Y-m-d 11:20:00');
    mysqli_query($conn, "INSERT INTO sales (id, invoice_number, customer_id, user_id, sale_date, subtotal, tax_amount, discount_amount, grand_total, paid_amount, balance_amount, payment_method, payment_status, status) VALUES 
        (4, 'INV-2026-0004', 3, $cashier_id, '$date0_1', 1002.89, 82.42, 0.00, 1085.31, 1085.31, 0.00, 'Mobile Money', 'Paid', 'Completed')");
    mysqli_query($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, tax_amount, discount_amount, total) VALUES 
        (4, 1, 1, 999.00, 82.42, 0.00, 1081.42),
        (4, 6, 1, 3.89, 0.00, 0.00, 3.89)");

    // 7. Seed Expenses
    mysqli_query($conn, "INSERT IGNORE INTO expense_categories (id, name, description) VALUES 
        (1, 'Rent', 'Monthly shop rental'),
        (2, 'Electricity', 'Power and lights'),
        (3, 'Salary', 'Employee wages'),
        (4, 'Miscellaneous', 'General operating expenses')");
        
    $prev_month = date('Y-m-d', strtotime('-20 days'));
    $today_date = date('Y-m-d');
    
    mysqli_query($conn, "INSERT INTO expenses (category_id, amount, expense_date, description, reference_no) VALUES 
        (1, 1200.00, '$prev_month', 'Shop rental fee', 'REF-RNT01'),
        (2, 280.50, '$prev_month', 'Electricity invoice', 'REF-POW01'),
        (4, 45.00, '$today_date', 'Cleaning supplies', 'REF-SUP01')");
}

// --------------------------------------------------------------------------
// Core Dashboard Calculations
// --------------------------------------------------------------------------

// Today's Date formats
$today = date('Y-m-d');
$month = date('m');
$year = date('Y');

// Stats Arrays
$stats = [];

// 1. Today's Sales
$q_sales = mysqli_query($conn, "SELECT SUM(grand_total) FROM sales WHERE DATE(sale_date) = '$today' AND status = 'Completed'");
$row_sales = mysqli_fetch_row($q_sales);
$stats['today_sales'] = (float)($row_sales[0] ?? 0.00);

// 2. Today's Profit
$q_profit = mysqli_query($conn, "
    SELECT SUM((si.selling_price - p.buying_price) * si.quantity) 
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    JOIN products p ON si.product_id = p.id 
    WHERE DATE(s.sale_date) = '$today' AND s.status = 'Completed'
");
$row_profit = mysqli_fetch_row($q_profit);
$stats['today_profit'] = (float)($row_profit[0] ?? 0.00);

// 3. Today's Expenses
$q_exp = mysqli_query($conn, "SELECT SUM(amount) FROM expenses WHERE expense_date = '$today'");
$row_exp = mysqli_fetch_row($q_exp);
$stats['today_expenses'] = (float)($row_exp[0] ?? 0.00);

// 4. Monthly Revenue
$q_m_sales = mysqli_query($conn, "SELECT SUM(grand_total) FROM sales WHERE YEAR(sale_date) = '$year' AND MONTH(sale_date) = '$month' AND status = 'Completed'");
$row_m_sales = mysqli_fetch_row($q_m_sales);
$stats['monthly_revenue'] = (float)($row_m_sales[0] ?? 0.00);

// 5. Monthly Profit
$q_m_profit = mysqli_query($conn, "
    SELECT SUM((si.selling_price - p.buying_price) * si.quantity) 
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    JOIN products p ON si.product_id = p.id 
    WHERE YEAR(s.sale_date) = '$year' AND MONTH(s.sale_date) = '$month' AND s.status = 'Completed'
");
$row_m_profit = mysqli_fetch_row($q_m_profit);
$stats['monthly_profit'] = (float)($row_m_profit[0] ?? 0.00);

// 6. Inventory Value
$q_inv = mysqli_query($conn, "SELECT SUM(current_stock * buying_price) FROM products WHERE status = 'Active'");
$row_inv = mysqli_fetch_row($q_inv);
$stats['inventory_value'] = (float)($row_inv[0] ?? 0.00);

// 7. Customers Count
$q_cust = mysqli_query($conn, "SELECT COUNT(*) FROM customers");
$row_cust = mysqli_fetch_row($q_cust);
$stats['customers_count'] = (int)($row_cust[0] ?? 0);

// 8. Suppliers Count
$q_supp = mysqli_query($conn, "SELECT COUNT(*) FROM suppliers");
$row_supp = mysqli_fetch_row($q_supp);
$stats['suppliers_count'] = (int)($row_supp[0] ?? 0);

// 9. Products Count
$q_prod = mysqli_query($conn, "SELECT COUNT(*) FROM products");
$row_prod = mysqli_fetch_row($q_prod);
$stats['products_count'] = (int)($row_prod[0] ?? 0);

// 10. Pending Credits
$q_cred = mysqli_query($conn, "SELECT SUM(balance_amount) FROM sales WHERE payment_status IN ('Partial', 'Unpaid') AND status = 'Completed'");
$row_cred = mysqli_fetch_row($q_cred);
$stats['pending_credits'] = (float)($row_cred[0] ?? 0.00);

// 11. Low Stock
$q_low = mysqli_query($conn, "SELECT COUNT(*) FROM products WHERE current_stock <= minimum_stock AND current_stock > 0");
$row_low = mysqli_fetch_row($q_low);
$stats['low_stock_count'] = (int)($row_low[0] ?? 0);

// 12. Out of Stock
$q_out = mysqli_query($conn, "SELECT COUNT(*) FROM products WHERE current_stock = 0");
$row_out = mysqli_fetch_row($q_out);
$stats['out_of_stock_count'] = (int)($row_out[0] ?? 0);

// 13. Expired Products
$q_exp_prod = mysqli_query($conn, "SELECT COUNT(*) FROM products WHERE expiry_date IS NOT NULL AND expiry_date < '$today'");
$row_exp_prod = mysqli_fetch_row($q_exp_prod);
$stats['expired_products_count'] = (int)($row_exp_prod[0] ?? 0);


// --------------------------------------------------------------------------
// Charts Data Calculations
// --------------------------------------------------------------------------
$charts = [];

// 1. Sales & Revenue Trend (Last 7 Days)
$sales_trend = ['labels' => [], 'revenue' => [], 'profit' => []];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $display_label = date('D, M d', strtotime($date));
    $sales_trend['labels'][] = $display_label;
    
    // Revenue for day
    $q_day_rev = mysqli_query($conn, "SELECT SUM(grand_total) FROM sales WHERE DATE(sale_date) = '$date' AND status = 'Completed'");
    $row_day_rev = mysqli_fetch_row($q_day_rev);
    $sales_trend['revenue'][] = (float)($row_day_rev[0] ?? 0.00);
    
    // Profit for day
    $q_day_prof = mysqli_query($conn, "
        SELECT SUM((si.selling_price - p.buying_price) * si.quantity) 
        FROM sale_items si 
        JOIN sales s ON si.sale_id = s.id 
        JOIN products p ON si.product_id = p.id 
        WHERE DATE(s.sale_date) = '$date' AND s.status = 'Completed'
    ");
    $row_day_prof = mysqli_fetch_row($q_day_prof);
    $sales_trend['profit'][] = (float)($row_day_prof[0] ?? 0.00);
}
$charts['sales_trend'] = $sales_trend;

// 2. Expense Trend (Last 6 Months)
$expense_trend = ['labels' => [], 'expenses' => []];
for ($i = 5; $i >= 0; $i--) {
    $month_ts = strtotime("-$i months");
    $m_val = date('m', $month_ts);
    $y_val = date('Y', $month_ts);
    $display_label = date('M Y', $month_ts);
    $expense_trend['labels'][] = $display_label;
    
    $q_m_exp = mysqli_query($conn, "SELECT SUM(amount) FROM expenses WHERE YEAR(expense_date) = '$y_val' AND MONTH(expense_date) = '$m_val'");
    $row_m_exp = mysqli_fetch_row($q_m_exp);
    $expense_trend['expenses'][] = (float)($row_m_exp[0] ?? 0.00);
}
$charts['expense_trend'] = $expense_trend;

// 3. Best Selling Products (Top 5)
$best_selling = ['labels' => [], 'quantities' => []];
$q_best = mysqli_query($conn, "
    SELECT p.name, SUM(si.quantity) as total_qty 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    JOIN sales s ON si.sale_id = s.id 
    WHERE s.status = 'Completed' 
    GROUP BY si.product_id 
    ORDER BY total_qty DESC 
    LIMIT 5
");
if ($q_best) {
    while ($row = mysqli_fetch_assoc($q_best)) {
        $best_selling['labels'][] = $row['name'];
        $best_selling['quantities'][] = (int)$row['total_qty'];
    }
}
$charts['best_selling'] = $best_selling;

// 4. Category Distribution
$cat_dist = ['labels' => [], 'counts' => []];
$q_cat = mysqli_query($conn, "
    SELECT c.name, COUNT(p.id) as prod_count 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    GROUP BY p.category_id
");
if ($q_cat) {
    while ($row = mysqli_fetch_assoc($q_cat)) {
        $cat_dist['labels'][] = $row['name'];
        $cat_dist['counts'][] = (int)$row['prod_count'];
    }
}
$charts['category_distribution'] = $cat_dist;

// 5. Payment Methods
$pay_methods = ['labels' => [], 'counts' => []];
$q_pay = mysqli_query($conn, "
    SELECT payment_method, COUNT(*) as count 
    FROM sales 
    WHERE status = 'Completed' 
    GROUP BY payment_method
");
if ($q_pay) {
    while ($row = mysqli_fetch_assoc($q_pay)) {
        $pay_methods['labels'][] = $row['payment_method'];
        $pay_methods['counts'][] = (int)$row['count'];
    }
}
$charts['payment_methods'] = $pay_methods;

// --------------------------------------------------------------------------
// Response Assemble
// --------------------------------------------------------------------------
echo json_encode([
    'success' => true,
    'stats' => $stats,
    'charts' => $charts
]);
exit();
