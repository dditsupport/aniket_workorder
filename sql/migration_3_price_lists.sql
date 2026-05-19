-- Migration 3: replace per-customer customer_prices with named price_lists
-- attached to customers. Apply AFTER migration_2_add_sales.sql.

SET NAMES utf8mb4;

-- 1) New tables
CREATE TABLE price_lists (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_price_lists_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE price_list_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    price_list_id INT UNSIGNED NOT NULL,
    item_kind     ENUM('RM','FG') NOT NULL,
    item_id       INT UNSIGNED NOT NULL,
    price         DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pli (price_list_id, item_kind, item_id),
    KEY idx_pli_item (item_kind, item_id),
    CONSTRAINT fk_pli_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Attach price list to customer
ALTER TABLE customers
    ADD COLUMN price_list_id INT UNSIGNED NULL AFTER active,
    ADD KEY idx_customers_pl (price_list_id),
    ADD CONSTRAINT fk_customers_pl FOREIGN KEY (price_list_id) REFERENCES price_lists(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- 3) Drop the old per-customer override table (data is discarded; if you
--    need to preserve it, run a SELECT + manual migration BEFORE this DROP).
DROP TABLE IF EXISTS customer_prices;
