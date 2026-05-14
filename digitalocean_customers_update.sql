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

-- NOTE: Run each ALTER TABLE block separately in phpMyAdmin.
-- If you get #1060 "Duplicate column" error, that column already exists — skip it and continue.

ALTER TABLE sales ADD COLUMN customer_id INT NULL AFTER customer_name;

ALTER TABLE customers ADD COLUMN points_enabled TINYINT(1) DEFAULT 1 AFTER points;

ALTER TABLE sales ADD COLUMN redeemed_points INT DEFAULT 0 AFTER promo_discount;

-- These are safe to run all at once (INSERT IGNORE skips duplicates automatically)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('loyalty_enabled', '1'),
  ('loyalty_kd_per_point', '10'),
  ('loyalty_point_value', '1'),
  ('shop_logo', '');

-- Add thumb column for product thumbnails
ALTER TABLE products ADD COLUMN thumb VARCHAR(255) DEFAULT NULL AFTER image;

-- Add foreign key (optional, run separately if it fails)
-- ALTER TABLE sales ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
