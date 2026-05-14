-- Performance Optimization: Add indexes for fast customer search
-- Run this in local phpMyAdmin

CREATE INDEX idx_customers_name ON customers(name);
CREATE INDEX idx_customers_phone ON customers(phone);
