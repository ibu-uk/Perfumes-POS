-- Update existing promotion_products table to support size-level promotions
-- Run this in phpMyAdmin

-- Column already exists, just add foreign key
ALTER TABLE promotion_products ADD FOREIGN KEY (product_size_id) REFERENCES product_sizes(id) ON DELETE CASCADE;

-- Update unique constraint (may fail if already updated, that's OK)
-- ALTER TABLE promotion_products DROP INDEX unique_promo_product;
-- ALTER TABLE promotion_products ADD UNIQUE KEY unique_promo_product (promotion_id, product_id, product_size_id);
