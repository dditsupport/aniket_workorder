-- Migration 2: add Sales module + RM sale_price + unified customer_prices.
-- Apply this AFTER you have already imported schema.sql v1 (which had a
-- product-only customer_prices table and no sales tables).

SET NAMES utf8mb4;

-- 1) raw_materials: add sale_price
ALTER TABLE raw_materials
    ADD COLUMN sale_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER stock_qty;

-- 2) customer_prices: generalise to (customer_id, item_kind, item_id)
ALTER TABLE customer_prices
    ADD COLUMN item_kind ENUM('RM','FG') NOT NULL DEFAULT 'FG' AFTER customer_id,
    ADD COLUMN item_id   INT UNSIGNED   NOT NULL DEFAULT 0    AFTER item_kind;
UPDATE customer_prices SET item_id = product_id WHERE item_id = 0;
ALTER TABLE customer_prices DROP FOREIGN KEY fk_cp_product;
ALTER TABLE customer_prices DROP INDEX uq_cp;
ALTER TABLE customer_prices DROP INDEX idx_cp_product;
ALTER TABLE customer_prices DROP COLUMN product_id;
ALTER TABLE customer_prices ADD UNIQUE KEY uq_cp (customer_id, item_kind, item_id);
ALTER TABLE customer_prices ADD KEY idx_cp_item (item_kind, item_id);

-- 3) stock_movements: add SALE_OUT
ALTER TABLE stock_movements
    MODIFY COLUMN ref_type ENUM('WO_RESERVE','WO_RELEASE','WO_CONSUME','WO_PRODUCE','ADJUST','SALE_OUT') NOT NULL;

-- 4) sales tables
CREATE TABLE sale_counters (
    year      INT          NOT NULL,
    last_seq  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_number   VARCHAR(20)  NOT NULL,
    customer_id   INT UNSIGNED NOT NULL,
    sale_date     DATE         NOT NULL,
    total_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes         TEXT NULL,
    created_by    INT UNSIGNED NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sale_number (sale_number),
    KEY idx_sale_customer (customer_id),
    KEY idx_sale_date (sale_date),
    CONSTRAINT fk_sale_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sale_user FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sale_items (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id     INT UNSIGNED NOT NULL,
    item_kind   ENUM('RM','FG') NOT NULL,
    item_id     INT UNSIGNED NOT NULL,
    qty         DECIMAL(12,3) NOT NULL,
    unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total  DECIMAL(14,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_si_sale (sale_id),
    KEY idx_si_item (item_kind, item_id),
    CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
