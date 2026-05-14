-- Product Thumbnails Setup — run in local phpMyAdmin
ALTER TABLE products ADD COLUMN thumb VARCHAR(255) DEFAULT NULL AFTER image;
