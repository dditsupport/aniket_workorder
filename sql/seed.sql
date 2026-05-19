-- Fresh-install seed data.
-- Run AFTER sql/schema.sql against an empty database, e.g.:
--   mysql -u <user> -p <db> < sql/schema.sql
--   mysql -u <user> -p <db> < sql/seed.sql
--
-- Default admin login: admin / admin123  (CHANGE IT after first login!)

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------
INSERT INTO users (id, username, password_hash, name, role, active) VALUES
  (1, 'admin',  '$2y$12$cd4XwHhRz/eetYyhngUaUeiqdNOQo97qz0Mwqzux418z996o2NdoC', 'Administrator', 'admin',  1),
  -- staff user with same password hash, for demo (password: admin123)
  (2, 'staff1', '$2y$12$cd4XwHhRz/eetYyhngUaUeiqdNOQo97qz0Mwqzux418z996o2NdoC', 'Ramesh K.',     'operator', 1);

-- ---------------------------------------------------------------
-- Customers
-- ---------------------------------------------------------------
INSERT INTO customers (id, code, name, gstin, phone, email, address, active) VALUES
  (1, 'C001', 'Aakar Industries',     '27AAACA1234A1Z5', '+91 98765 11111', 'orders@aakar.example',   'Plot 14, MIDC Bhosari, Pune',         1),
  (2, 'C002', 'Bharat Castings',      '27BBBCB5678B1Z5', '+91 98765 22222', 'sales@bharatcast.example','GIDC Vatva, Ahmedabad',              1),
  (3, 'C003', 'Crescent Engineering', '29CCCDC4321C1Z5', '+91 98765 33333', 'buy@crescenteng.example','Peenya Industrial Area, Bengaluru',  1),
  (4, 'C004', 'Deccan Steel Works',   '36DDDDE9999D1Z5', '+91 98765 44444', 'po@deccansteel.example', 'Jeedimetla, Hyderabad',              1);

-- ---------------------------------------------------------------
-- Raw materials (sellable, with sale_price)
-- ---------------------------------------------------------------
INSERT INTO raw_materials (id, code, name, unit, reorder_level, stock_qty, sale_price) VALUES
  (1, 'RM01', 'Steel Bar 10mm',     'kg',  100.000,  475.000,   75.00),
  (2, 'RM02', 'Aluminium Sheet 2mm','kg',   50.000,  196.000,  220.00),
  (3, 'RM03', 'Copper Wire 4mm',    'm',    80.000,  250.000,  140.00),
  (4, 'RM04', 'Plastic Granules',   'kg',  120.000,  400.000,   90.00),
  (5, 'RM05', 'Fasteners M8',       'pcs', 300.000,  900.000,    4.00);

-- ---------------------------------------------------------------
-- Products (Final goods, sellable)
-- ---------------------------------------------------------------
INSERT INTO products (id, code, name, unit, base_price, stock_qty) VALUES
  (1, 'P01', 'Bracket Assembly', 'pcs',  850.00, 42.000),
  (2, 'P02', 'Motor Mount',      'pcs', 1200.00, 25.000),
  (3, 'P03', 'Junction Box',     'pcs',  650.00, 15.000),
  (4, 'P04', 'Cable Tray 1m',    'pcs', 1450.00, 40.000);

-- ---------------------------------------------------------------
-- BOM (raw material per 1 unit of finished product)
-- ---------------------------------------------------------------
INSERT INTO bom (product_id, raw_material_id, qty_per_unit) VALUES
  (1, 1, 0.5000),  -- P01 needs 0.5 kg Steel Bar
  (1, 5, 4.0000),  -- P01 needs 4   Fasteners
  (2, 1, 1.2000),  -- P02 needs 1.2 kg Steel Bar
  (2, 2, 0.3000),  -- P02 needs 0.3 kg Aluminium Sheet
  (2, 5, 6.0000),  -- P02 needs 6   Fasteners
  (3, 4, 0.8000),  -- P03 needs 0.8 kg Plastic Granules
  (3, 5, 2.0000),  -- P03 needs 2   Fasteners
  (4, 2, 0.9000),  -- P04 needs 0.9 kg Aluminium Sheet
  (4, 1, 0.4000);  -- P04 needs 0.4 kg Steel Bar

-- ---------------------------------------------------------------
-- Customer-specific prices (unified across RM and FG)
--   item_kind = 'FG' -> products.id, 'RM' -> raw_materials.id
-- ---------------------------------------------------------------
INSERT INTO customer_prices (customer_id, item_kind, item_id, price) VALUES
  (1, 'FG', 1,  820.00),  -- Aakar gets P01 cheaper
  (1, 'RM', 1,   72.00),  -- Aakar gets RM01 cheaper
  (2, 'FG', 2, 1150.00),  -- Bharat gets P02 cheaper
  (2, 'FG', 3,  620.00),  -- Bharat gets P03 cheaper
  (3, 'RM', 3,  132.00);  -- Crescent gets RM03 cheaper

-- ---------------------------------------------------------------
-- Work order (draft, demonstrates flow)
-- ---------------------------------------------------------------
INSERT INTO wo_counters (year, last_seq) VALUES (2026, 1);
INSERT INTO work_orders (id, wo_number, customer_id, product_id, quantity, unit_price, total_amount, status, notes, created_by, created_at)
VALUES
  (1, 'WO-2026-0001', 1, 1, 20.000, 820.00, 16400.00, 'draft', 'First demo work order', 1, '2026-05-10 10:30:00');

-- ---------------------------------------------------------------
-- Sales (auto-numbered) -- stock_qty above already reflects these deductions.
-- ---------------------------------------------------------------
INSERT INTO sale_counters (year, last_seq) VALUES (2026, 3);

INSERT INTO sales (id, sale_number, customer_id, sale_date, total_amount, notes, created_by, created_at) VALUES
  (1, 'SALE-2026-0001', 2, '2026-05-12', 12350.00, 'Mixed order: products + fasteners', 1, '2026-05-12 11:15:00'),
  (2, 'SALE-2026-0002', 3, '2026-05-14',  8475.00, 'Cash & carry RM order',             2, '2026-05-14 09:40:00'),
  (3, 'SALE-2026-0003', 1, '2026-05-17',  7440.00, 'Repeat customer',                   1, '2026-05-17 16:05:00');

-- Sale 1: Bharat -> P02 x5, P03 x10, RM05 x100
INSERT INTO sale_items (sale_id, item_kind, item_id, qty, unit_price, line_total) VALUES
  (1, 'FG', 2,   5.000, 1150.00,  5750.00),
  (1, 'FG', 3,  10.000,  620.00,  6200.00),
  (1, 'RM', 5, 100.000,    4.00,   400.00);

-- Sale 2: Crescent -> RM03 x50, RM01 x25
INSERT INTO sale_items (sale_id, item_kind, item_id, qty, unit_price, line_total) VALUES
  (2, 'RM', 3, 50.000, 132.00, 6600.00),
  (2, 'RM', 1, 25.000,  75.00, 1875.00);

-- Sale 3: Aakar -> P01 x8, RM02 x4
INSERT INTO sale_items (sale_id, item_kind, item_id, qty, unit_price, line_total) VALUES
  (3, 'FG', 1,  8.000, 820.00, 6560.00),
  (3, 'RM', 2,  4.000, 220.00,  880.00);

-- ---------------------------------------------------------------
-- Stock movement history (matches the seeded stock_qty values).
-- Opening balances + sale-out deductions only; WO is still draft.
-- ---------------------------------------------------------------
INSERT INTO stock_movements (item_type, item_id, qty_in, qty_out, ref_type, ref_id, note, created_by, created_at) VALUES
  -- Opening stock entries
  ('RM', 1, 500.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  ('RM', 2, 200.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  ('RM', 3, 300.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  ('RM', 4, 400.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  ('RM', 5,1000.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  ('FG', 1,  50.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  ('FG', 2,  30.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  ('FG', 3,  25.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  ('FG', 4,  40.000, 0.000, 'ADJUST', NULL, 'Opening stock', 1, '2026-05-01 09:00:00'),
  -- Sale 1
  ('FG', 2, 0.000,   5.000, 'SALE_OUT', 1, 'sold', 1, '2026-05-12 11:15:00'),
  ('FG', 3, 0.000,  10.000, 'SALE_OUT', 1, 'sold', 1, '2026-05-12 11:15:00'),
  ('RM', 5, 0.000, 100.000, 'SALE_OUT', 1, 'sold', 1, '2026-05-12 11:15:00'),
  -- Sale 2
  ('RM', 3, 0.000,  50.000, 'SALE_OUT', 2, 'sold', 2, '2026-05-14 09:40:00'),
  ('RM', 1, 0.000,  25.000, 'SALE_OUT', 2, 'sold', 2, '2026-05-14 09:40:00'),
  -- Sale 3
  ('FG', 1, 0.000,   8.000, 'SALE_OUT', 3, 'sold', 1, '2026-05-17 16:05:00'),
  ('RM', 2, 0.000,   4.000, 'SALE_OUT', 3, 'sold', 1, '2026-05-17 16:05:00');

-- ---------------------------------------------------------------
-- Receipts (partial collections against customers)
-- ---------------------------------------------------------------
INSERT INTO receipts (customer_id, receipt_date, amount, mode, reference, note, created_by) VALUES
  (1, '2026-05-18',  8000.00, 'upi',    'UPI/REF/8841',   'Part payment vs SALE-2026-0003', 1),
  (2, '2026-05-15', 12000.00, 'bank',   'NEFT/A12345',    'Part payment vs SALE-2026-0001', 1),
  (3, '2026-05-16',  5000.00, 'cash',   NULL,             'On account',                    2);
