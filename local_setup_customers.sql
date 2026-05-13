-- Local XAMPP Setup: Customers & Loyalty Points
-- Run this in your local phpMyAdmin first, test everything, then deploy to DigitalOcean

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

-- Add customer_id column to sales table (skip if already exists)
ALTER TABLE sales ADD COLUMN customer_id INT NULL AFTER customer_name;

-- Add points_enabled per customer (1 = enabled, 0 = disabled)
ALTER TABLE customers ADD COLUMN points_enabled TINYINT(1) DEFAULT 1 AFTER points;

-- Add redeemed_points column to sales (how many points were used)
ALTER TABLE sales ADD COLUMN redeemed_points INT DEFAULT 0 AFTER promo_discount;

-- Insert loyalty settings (safe to run multiple times)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('loyalty_enabled', '1'),
  ('loyalty_kd_per_point', '10'),
  ('loyalty_point_value', '1');
