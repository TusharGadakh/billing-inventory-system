-- ========================================================
-- Billing & Inventory Management System Database Schema
-- Technologies: MySQL / MariaDB (Compatible with MySQL 5.7+ & 8.0+)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `billing_inventory_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `billing_inventory_db`;

-- Drop existing tables in reverse dependency order (if resetting)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `invoice_items`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `category_id` INT NULL,
    `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `current_stock` INT NOT NULL DEFAULT 0,
    `min_stock_alert` INT NOT NULL DEFAULT 5,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_product_sku` (`sku`),
    INDEX `idx_product_category` (`category_id`),
    CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: customers
-- --------------------------------------------------------
CREATE TABLE `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NULL,
    `phone` VARCHAR(30) NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: invoices
-- --------------------------------------------------------
CREATE TABLE `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_phone` VARCHAR(30) NULL,
    `invoice_date` DATE NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('paid', 'pending', 'cancelled') NOT NULL DEFAULT 'paid',
    `payment_method` ENUM('cash', 'card', 'upi', 'bank_transfer', 'other') NOT NULL DEFAULT 'cash',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_invoice_number` (`invoice_number`),
    INDEX `idx_invoice_date` (`invoice_date`),
    CONSTRAINT `fk_invoice_customer` FOREIGN KEY (`customer_id`) 
        REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: invoice_items
-- --------------------------------------------------------
CREATE TABLE `invoice_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    INDEX `idx_item_invoice` (`invoice_id`),
    INDEX `idx_item_product` (`product_id`),
    CONSTRAINT `fk_item_invoice` FOREIGN KEY (`invoice_id`) 
        REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stock_movements (Audit Log for Inventory Tracking)
-- --------------------------------------------------------
CREATE TABLE `stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `type` ENUM('IN', 'OUT', 'ADJUSTMENT') NOT NULL,
    `quantity` INT NOT NULL,
    `reference_id` INT NULL COMMENT 'Associated invoice ID or adjustment ref',
    `reference_type` VARCHAR(50) NOT NULL DEFAULT 'INVOICE',
    `remarks` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_movement_product` (`product_id`),
    INDEX `idx_movement_type` (`type`),
    CONSTRAINT `fk_movement_product` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- Seed Data for Demonstration & Testing
-- ========================================================

-- Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Electronics', 'Electronic gadgets, components and accessories'),
(2, 'Computer Peripherals', 'Keyboards, mice, monitors, and hubs'),
(3, 'Office Stationery', 'Paper, notebooks, desk organizers, and pens'),
(4, 'Storage Devices', 'Hard drives, SSDs, flash drives, and memory cards');

-- Products (Note: SKU-004 has stock 3 < min_stock_alert 5 to demo low stock warning)
INSERT INTO `products` (`id`, `sku`, `name`, `category_id`, `cost_price`, `selling_price`, `current_stock`, `min_stock_alert`, `unit`, `status`) VALUES
(1, 'PRD-EL-001', 'Wireless Ergonomic Mouse', 2, 18.00, 32.50, 45, 10, 'pcs', 'active'),
(2, 'PRD-EL-002', 'Mechanical Gaming Keyboard RGB', 2, 45.00, 79.99, 20, 5, 'pcs', 'active'),
(3, 'PRD-ST-001', 'Portable SSD 1TB USB 3.2', 4, 60.00, 95.00, 15, 5, 'pcs', 'active'),
(4, 'PRD-EL-003', 'USB-C Multiport Adapter 7-in-1', 2, 22.00, 39.99, 3, 5, 'pcs', 'active'),
(5, 'PRD-OF-001', 'Executive A5 Hardcover Notebook', 3, 4.50, 9.99, 80, 20, 'pcs', 'active'),
(6, 'PRD-OF-002', 'Gel Pen Pack (12-Piece Assorted)', 3, 3.00, 6.50, 65, 15, 'pack', 'active'),
(7, 'PRD-EL-004', 'Noise-Cancelling Over-Ear Headphones', 1, 75.00, 129.99, 12, 4, 'pcs', 'active'),
(8, 'PRD-ST-002', 'Ultra High-Speed MicroSD 128GB', 4, 11.50, 19.99, 0, 5, 'pcs', 'active');

-- Customers
INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`) VALUES
(1, 'Acme Solutions Inc.', 'purchasing@acmesolutions.com', '+1-555-0199', '100 Innovation Way, Suite 400, Austin, TX'),
(2, 'Sophia Miller', 'sophia.m@example.com', '+1-555-0245', '742 Evergreen Terrace, Springfield, IL'),
(3, 'TechForward Hub', 'inventory@techforward.org', '+1-555-0378', '88 Silicon Boulevard, San Jose, CA'),
(4, 'David Chen', 'david.chen@example.com', '+1-555-0412', '12 Maple Leaf Street, Boston, MA');

-- Invoices
INSERT INTO `invoices` (`id`, `invoice_number`, `customer_id`, `customer_name`, `customer_phone`, `invoice_date`, `subtotal`, `tax_rate`, `tax_amount`, `discount_amount`, `grand_total`, `payment_status`, `payment_method`, `notes`) VALUES
(1, 'INV-2026-0001', 1, 'Acme Solutions Inc.', '+1-555-0199', CURDATE() - INTERVAL 2 DAY, 317.48, 8.00, 25.40, 10.00, 332.88, 'paid', 'bank_transfer', 'Standard net-30 bulk order'),
(2, 'INV-2026-0002', 2, 'Sophia Miller', '+1-555-0245', CURDATE() - INTERVAL 1 DAY, 112.49, 8.00, 9.00, 0.00, 121.49, 'paid', 'card', 'Direct counter purchase');

-- Invoice Items
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 1, 'Wireless Ergonomic Mouse', 5, 32.50, 162.50),
(2, 1, 2, 'Mechanical Gaming Keyboard RGB', 1, 79.99, 79.99),
(3, 1, 6, 'Gel Pen Pack (12-Piece Assorted)', 11, 6.50, 71.50),
(4, 2, 1, 'Wireless Ergonomic Mouse', 1, 32.50, 32.50),
(5, 2, 2, 'Mechanical Gaming Keyboard RGB', 1, 79.99, 79.99);

-- Stock Movements
INSERT INTO `stock_movements` (`product_id`, `type`, `quantity`, `reference_id`, `reference_type`, `remarks`) VALUES
(1, 'IN', 51, NULL, 'OPENING_STOCK', 'Initial opening stock intake'),
(2, 'IN', 22, NULL, 'OPENING_STOCK', 'Initial opening stock intake'),
(3, 'IN', 15, NULL, 'OPENING_STOCK', 'Initial opening stock intake'),
(4, 'IN', 3, NULL, 'OPENING_STOCK', 'Initial opening stock intake'),
(5, 'IN', 80, NULL, 'OPENING_STOCK', 'Initial opening stock intake'),
(6, 'IN', 76, NULL, 'OPENING_STOCK', 'Initial opening stock intake'),
(7, 'IN', 12, NULL, 'OPENING_STOCK', 'Initial opening stock intake'),
(1, 'OUT', 5, 1, 'INVOICE', 'Sales deduction for invoice INV-2026-0001'),
(2, 'OUT', 1, 1, 'INVOICE', 'Sales deduction for invoice INV-2026-0001'),
(6, 'OUT', 11, 1, 'INVOICE', 'Sales deduction for invoice INV-2026-0001'),
(1, 'OUT', 1, 2, 'INVOICE', 'Sales deduction for invoice INV-2026-0002'),
(2, 'OUT', 1, 2, 'INVOICE', 'Sales deduction for invoice INV-2026-0002');
