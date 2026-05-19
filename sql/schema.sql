-- Inventory & WorkOrder schema
-- MySQL 5.7+/8 InnoDB utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS sale_counters;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS wo_rm_allocations;
DROP TABLE IF EXISTS work_orders;
DROP TABLE IF EXISTS wo_counters;
DROP TABLE IF EXISTS price_list_items;
DROP TABLE IF EXISTS bom;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS raw_materials;
DROP TABLE IF EXISTS receipts;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS price_lists;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50)  NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    name           VARCHAR(100) NOT NULL,
    role           ENUM('admin','operator','viewer') NOT NULL DEFAULT 'viewer',
    active         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Named price list ("10% off", "Registered Vendor", "MRP", etc.).
-- A customer is attached to ONE price list; that list provides the
-- per-(RM|FG) prices the customer sees on Sales and Work Orders.
CREATE TABLE price_lists (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_price_lists_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(30)  NOT NULL,
    name          VARCHAR(150) NOT NULL,
    gstin         VARCHAR(20)  NULL,
    phone         VARCHAR(30)  NULL,
    email         VARCHAR(120) NULL,
    address       VARCHAR(500) NULL,
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    price_list_id INT UNSIGNED NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_code (code),
    KEY idx_customers_pl (price_list_id),
    CONSTRAINT fk_customers_pl FOREIGN KEY (price_list_id) REFERENCES price_lists(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE raw_materials (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(30)  NOT NULL,
    name            VARCHAR(150) NOT NULL,
    unit            VARCHAR(20)  NOT NULL DEFAULT 'pcs',
    reorder_level   DECIMAL(12,3) NOT NULL DEFAULT 0,
    stock_qty       DECIMAL(12,3) NOT NULL DEFAULT 0,
    sale_price      DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rm_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(30)  NOT NULL,
    name        VARCHAR(150) NOT NULL,
    unit        VARCHAR(20)  NOT NULL DEFAULT 'pcs',
    base_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_qty   DECIMAL(12,3) NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bom (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id       INT UNSIGNED NOT NULL,
    raw_material_id  INT UNSIGNED NOT NULL,
    qty_per_unit     DECIMAL(12,4) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bom_product_rm (product_id, raw_material_id),
    KEY idx_bom_rm (raw_material_id),
    CONSTRAINT fk_bom_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bom_rm FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Line items inside a price list. One row per (list, kind, item).
-- item_kind = 'RM' -> item_id references raw_materials.id
-- item_kind = 'FG' -> item_id references products.id
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

CREATE TABLE wo_counters (
    year      INT          NOT NULL,
    last_seq  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE work_orders (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    wo_number     VARCHAR(20)  NOT NULL,
    customer_id   INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED NOT NULL,
    quantity      DECIMAL(12,3) NOT NULL,
    unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
    status        ENUM('draft','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
    notes         TEXT NULL,
    created_by    INT UNSIGNED NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at  DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wo_number (wo_number),
    KEY idx_wo_customer (customer_id),
    KEY idx_wo_product (product_id),
    KEY idx_wo_status (status),
    CONSTRAINT fk_wo_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_wo_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_wo_user FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wo_rm_allocations (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_id    INT UNSIGNED NOT NULL,
    raw_material_id  INT UNSIGNED NOT NULL,
    qty              DECIMAL(12,4) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_alloc_wo (work_order_id),
    KEY idx_alloc_rm (raw_material_id),
    CONSTRAINT fk_alloc_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_alloc_rm FOREIGN KEY (raw_material_id) REFERENCES raw_materials(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_movements (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_type   ENUM('RM','FG') NOT NULL,
    item_id     INT UNSIGNED NOT NULL,
    qty_in      DECIMAL(12,3) NOT NULL DEFAULT 0,
    qty_out     DECIMAL(12,3) NOT NULL DEFAULT 0,
    ref_type    ENUM('WO_RESERVE','WO_RELEASE','WO_CONSUME','WO_PRODUCE','ADJUST','SALE_OUT') NOT NULL,
    ref_id      INT UNSIGNED NULL,
    note        VARCHAR(255) NULL,
    created_by  INT UNSIGNED NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sm_item (item_type, item_id),
    KEY idx_sm_ref (ref_type, ref_id),
    CONSTRAINT fk_sm_user FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE receipts (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id   INT UNSIGNED NOT NULL,
    receipt_date  DATE         NOT NULL,
    amount        DECIMAL(14,2) NOT NULL,
    mode          ENUM('cash','bank','upi','cheque','other') NOT NULL DEFAULT 'cash',
    reference     VARCHAR(100) NULL,
    note          TEXT NULL,
    created_by    INT UNSIGNED NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_receipts_customer (customer_id),
    KEY idx_receipts_date (receipt_date),
    CONSTRAINT fk_receipts_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_receipts_user FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- item_kind: 'RM' -> raw_materials.id, 'FG' -> products.id
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

SET FOREIGN_KEY_CHECKS=1;
