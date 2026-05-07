-- Demo POS - Database Setup
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS demo_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE demo_pos;

-- Branches
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO branches (name, name_ar, address, phone) VALUES 
('Main Branch', 'الفرع الرئيسي', 'UTC Building, Maliya, Kuwait City', '66680241');

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    full_name_ar VARCHAR(100),
    role ENUM('admin','cashier') DEFAULT 'cashier',
    branch_id INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);
-- Default admin: admin / admin123
INSERT INTO users (username, password, full_name, full_name_ar, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'المدير', 'admin'),
('cashier1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier One', 'الكاشير', 'cashier');

-- Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    parent_id INT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'tag',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);
INSERT INTO categories (name, name_ar, icon) VALUES 
('Oud', 'عود', 'droplet'),
('Floral', 'زهري', 'flower'),
('Musk', 'مسك', 'wind'),
('Bakhoor', 'بخور', 'flame'),
('Gift Sets', 'مجموعات هدايا', 'gift');

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NOT NULL,
    category_id INT,
    type ENUM('piece','weight') NOT NULL DEFAULT 'piece',
    barcode VARCHAR(100) UNIQUE,
    description TEXT,
    description_ar TEXT,
    base_price DECIMAL(10,3) DEFAULT 0,
    weight_unit ENUM('gram','tola') DEFAULT 'gram',
    stock DECIMAL(10,3) DEFAULT 0,
    low_stock_threshold DECIMAL(10,3) DEFAULT 10,
    image VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_type (type),
    INDEX idx_is_active (is_active),
    INDEX idx_barcode (barcode)
);

-- Product Sizes (for piece-type products like perfumes)
CREATE TABLE product_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size_label VARCHAR(50) NOT NULL,
    barcode VARCHAR(100) UNIQUE,
    price DECIMAL(10,3) NOT NULL,
    stock INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_barcode (barcode)
);

-- Sales / Invoices
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL UNIQUE,
    user_id INT,
    branch_id INT DEFAULT 1,
    subtotal DECIMAL(10,3) DEFAULT 0,
    discount DECIMAL(10,3) DEFAULT 0,
    discount_type ENUM('fixed','percent') DEFAULT 'fixed',
    tax DECIMAL(10,3) DEFAULT 0,
    total DECIMAL(10,3) DEFAULT 0,
    paid_amount DECIMAL(10,3) DEFAULT 0,
    change_amount DECIMAL(10,3) DEFAULT 0,
    payment_method ENUM('cash','knet','credit','mixed') DEFAULT 'cash',
    status ENUM('paid','unpaid','partial','void') DEFAULT 'paid',
    customer_name VARCHAR(150),
    customer_phone VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    INDEX idx_user (user_id),
    INDEX idx_branch (branch_id),
    INDEX idx_invoice (invoice_no),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Sale Items
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT,
    product_size_id INT DEFAULT NULL,
    product_name VARCHAR(150),
    product_name_ar VARCHAR(150),
    size_label VARCHAR(50),
    qty DECIMAL(10,3) DEFAULT 1,
    unit_price DECIMAL(10,3) NOT NULL,
    discount DECIMAL(10,3) DEFAULT 0,
    total DECIMAL(10,3) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (product_size_id) REFERENCES product_sizes(id) ON DELETE SET NULL,
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id),
    INDEX idx_size (product_size_id)
);

-- Expenses
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100),
    description TEXT,
    amount DECIMAL(10,3) NOT NULL,
    paid_to VARCHAR(150),
    user_id INT,
    branch_id INT DEFAULT 1,
    expense_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_branch (branch_id),
    INDEX idx_date (expense_date)
);

-- Settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general'
);
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('shop_name', 'Demo POS', 'general'),
('shop_name_ar', 'Demo POS', 'general'),
('shop_address', 'Shop No. 16,53 Mezzanine Floor, UTC Building, Maliy, Kuwait City', 'general'),
('shop_address_ar', 'محل رقم 16-53 الدور الميزانين، مبنى يو تي سي – المالية، مدينة الكويت', 'general'),
('shop_phone', '69989060', 'general'),
('currency', 'KWD', 'general'),
('currency_ar', 'د.ك', 'general'),
('tax_rate', '0', 'finance'),
('receipt_footer', 'Thank you for your visit!', 'receipt'),
('receipt_footer_ar', 'شكراً لزيارتكم!', 'receipt'),
('low_stock_days', '7', 'inventory'),
('invoice_prefix', 'INV', 'finance');
