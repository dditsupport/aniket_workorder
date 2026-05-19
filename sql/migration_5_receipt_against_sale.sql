-- Migration 5: tag each receipt to an optional sales invoice.
-- Apply AFTER migration_4_plain_text_passwords.sql.

SET NAMES utf8mb4;

ALTER TABLE receipts
    ADD COLUMN sale_id INT UNSIGNED NULL AFTER customer_id,
    ADD KEY idx_receipts_sale (sale_id),
    ADD CONSTRAINT fk_receipts_sale FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON UPDATE CASCADE ON DELETE SET NULL;
