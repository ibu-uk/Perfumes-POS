-- DigitalOcean Database Update: Customers & Loyalty Points
-- Run this in phpMyAdmin on your DigitalOcean database

-- Create customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) DEFAULT '',
    email VARCHAR(100) DEFAULT '',
    birthday DATE NULL,
    points INT DEFAULT 0,
    total_spent DECIMAL(12,3) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add customer_id column to sales table
ALTER TABLE sales ADD COLUMN customer_id INT NULL AFTER customer_name;

-- Add points_enabled per customer
ALTER TABLE customers ADD COLUMN points_enabled TINYINT(1) DEFAULT 1 AFTER points;

-- Add redeemed_points to sales
ALTER TABLE sales ADD COLUMN redeemed_points INT DEFAULT 0 AFTER promo_discount;

-- Insert loyalty settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('loyalty_enabled', '1'),
  ('loyalty_kd_per_point', '10'),
  ('loyalty_point_value', '1');

-- Add product image column
ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER barcode;

-- Add shop_logo setting row
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('shop_logo', '');

-- Add foreign key (optional, run separately if it fails)
-- ALTER TABLE sales ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
