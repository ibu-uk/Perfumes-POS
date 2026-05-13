-- Product Images Setup — run in local phpMyAdmin
ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER barcode;
