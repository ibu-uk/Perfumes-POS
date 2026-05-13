-- Loyalty Points Setup Only (customers table already exists)
-- Run this in local phpMyAdmin

ALTER TABLE customers ADD COLUMN points_enabled TINYINT(1) DEFAULT 1 AFTER points;

ALTER TABLE sales ADD COLUMN redeemed_points INT DEFAULT 0 AFTER promo_discount;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('loyalty_enabled', '1'),
  ('loyalty_kd_per_point', '10'),
  ('loyalty_point_value', '1');
