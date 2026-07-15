-- Shop Management System SQL Schema
-- Fully normalized database with constraints and sample users.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `return_items`;
DROP TABLE IF EXISTS `returns`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `expense_categories`;
DROP TABLE IF EXISTS `inventory_movements`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `purchase_order_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `brands`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Administrator', 'Manager', 'Cashier', 'Store Keeper', 'Accountant') NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `status` ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
  `failed_attempts` INT DEFAULT 0,
  `lock_until` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`role`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Remember Tokens
CREATE TABLE `remember_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `selector` VARCHAR(255) NOT NULL UNIQUE,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Password Resets
CREATE TABLE `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Categories
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Brands
CREATE TABLE `brands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Suppliers
CREATE TABLE `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `company_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `credit_balance` DECIMAL(15,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Customers
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `credit_balance` DECIMAL(15,2) DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Products
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barcode` VARCHAR(50) DEFAULT NULL UNIQUE,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category_id` INT DEFAULT NULL,
  `brand_id` INT DEFAULT NULL,
  `supplier_id` INT DEFAULT NULL,
  `buying_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `current_stock` INT NOT NULL DEFAULT 0,
  `minimum_stock` INT NOT NULL DEFAULT 0,
  `maximum_stock` INT NOT NULL DEFAULT 1000,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
  `expiry_date` DATE DEFAULT NULL,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `image_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  INDEX (`status`),
  INDEX (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Purchase Orders
CREATE TABLE `purchase_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT NOT NULL,
  `order_date` DATE NOT NULL,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `tax_amount` DECIMAL(15,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) DEFAULT 0.00,
  `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('Pending', 'Received', 'Cancelled') DEFAULT 'Pending',
  `payment_status` ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  INDEX (`status`),
  INDEX (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Purchase Order Items
CREATE TABLE `purchase_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `buying_price` DECIMAL(15,2) NOT NULL,
  `total` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Sales
CREATE TABLE `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `sale_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `subtotal` DECIMAL(15,2) NOT NULL,
  `tax_amount` DECIMAL(15,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) DEFAULT 0.00,
  `grand_total` DECIMAL(15,2) NOT NULL,
  `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
  `balance_amount` DECIMAL(15,2) DEFAULT 0.00,
  `payment_method` ENUM('Cash', 'Card', 'Bank', 'Mobile Money', 'Credit', 'Split') NOT NULL DEFAULT 'Cash',
  `payment_status` ENUM('Paid', 'Partial', 'Unpaid', 'Refunded') NOT NULL DEFAULT 'Paid',
  `status` ENUM('Completed', 'Returned', 'Held') NOT NULL DEFAULT 'Completed',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX (`status`),
  INDEX (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Sale Items
CREATE TABLE `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `selling_price` DECIMAL(15,2) NOT NULL,
  `tax_amount` DECIMAL(15,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) DEFAULT 0.00,
  `total` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Inventory Movements
CREATE TABLE `inventory_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `type` ENUM('Stock In', 'Stock Out', 'Transfer', 'Damaged', 'Expired', 'Adjustment', 'Sale', 'Purchase', 'Return') NOT NULL,
  `quantity` INT NOT NULL,
  `reference_id` INT DEFAULT NULL, -- sale_id, purchase_id, return_id, etc.
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  INDEX (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Expense Categories
CREATE TABLE `expense_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Expenses
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `description` TEXT DEFAULT NULL,
  `reference_no` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Returns
CREATE TABLE `returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT DEFAULT NULL,
  `purchase_order_id` INT DEFAULT NULL,
  `type` ENUM('Customer', 'Supplier') NOT NULL,
  `return_date` DATE NOT NULL,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  INDEX (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Return Items
CREATE TABLE `return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `refund_price` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Settings
CREATE TABLE `settings` (
  `key` VARCHAR(50) NOT NULL PRIMARY KEY,
  `value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Notifications
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('Low Stock', 'Out of Stock', 'Expiry Warning', 'New Sale', 'Credit Due', 'Backup Reminder', 'System') NOT NULL DEFAULT 'System',
  `status` ENUM('Unread', 'Read') NOT NULL DEFAULT 'Unread',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`status`),
  INDEX (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Activity Log
CREATE TABLE `activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(150) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Settings
INSERT INTO `settings` (`key`, `value`) VALUES
('shop_name', 'Nexus Shop'),
('shop_email', 'info@nexusshop.com'),
('shop_phone', '+1 (555) 019-2834'),
('shop_address', '100 Silicon Valley, California, USA'),
('currency_symbol', '$'),
('currency_code', 'USD'),
('tax_rate', '8.25'),
('receipt_header', 'NEXUS RETAIL SYSTEMS\nThank you for shopping with us!'),
('receipt_footer', 'Please keep your receipt for returns.\nNo returns after 14 days.'),
('theme', 'dark');

-- Seed Standard Users (Password defaults to roles followed by '123' e.g. admin123, manager123...)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `full_name`, `phone`, `status`) VALUES
('admin', 'admin@nexusshop.com', '$2y$10$L/ApH9tR0pSC.qqHze4KYOYcqLsNsz3aRsoOP4OQut4k71nKbNK1S', 'Administrator', 'Alex Morgan', '+1 555-123-4567', 'Active'),
('manager', 'manager@nexusshop.com', '$2y$10$AFYnMSyAgxCVIwAtpUbyReRwXZfSVfoZcstzH07k7b/OBRcwRnWpC', 'Manager', 'Sarah Connor', '+1 555-234-5678', 'Active'),
('cashier', 'cashier@nexusshop.com', '$2y$10$.vm3pz7J8LR9uMd2FA/VGuy1R6g6VFIvAoAfs3/oO81a0nlyRDEXG', 'Cashier', 'John Doe', '+1 555-345-6789', 'Active'),
('storekeeper', 'keeper@nexusshop.com', '$2y$10$BV5yw5Ed1P6THW0zhL31YOzcplGW5ISrDieFNWzph3rgZXCeCAH3C', 'Store Keeper', 'Bob Builder', '+1 555-456-7890', 'Active'),
('accountant', 'accountant@nexusshop.com', '$2y$10$X9K7b7k8JeAzTR3b5b11heQI0cqFzuPvpPL4whemSIXkMnmZ9IcfW', 'Accountant', 'Jane Ledger', '+1 555-567-8901', 'Active');
