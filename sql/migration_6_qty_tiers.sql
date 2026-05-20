-- Migration 6: optional quantity-break pricing tiers on price lists.
-- Apply AFTER migration_5_receipt_against_sale.sql.

SET NAMES utf8mb4;

CREATE TABLE price_list_tiers (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    price_list_id INT UNSIGNED NOT NULL,
    item_kind     ENUM('RM','FG') NOT NULL,
    item_id       INT UNSIGNED NOT NULL,
    min_qty       DECIMAL(12,3) NOT NULL,
    max_qty       DECIMAL(12,3) NULL,
    price         DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_plt (price_list_id, item_kind, item_id, min_qty),
    CONSTRAINT fk_plt_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
