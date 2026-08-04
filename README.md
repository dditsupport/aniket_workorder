# Inventory & Work-Order Tracker

A small in-house web app to manage customers, raw materials, final products,
BOM, customer-wise price lists, work orders (with BOM-driven stock movements)
and amount-received logs per customer.

Stack: **PHP 8.4** + **MySQL** + plain PDO (no framework). Designed for
shared hosting (MilesWeb / cPanel).

## Features
- Multi-user login with roles: `admin` / `operator` / `viewer`.
- Masters: Customers (each attached to one named Price List), Raw Materials (sellable, with sale price), Products (Final, sellable), BOM.
- Price Lists: named tiers ("10% Off", "Registered Vendor", etc.). One row per list in `price_lists`, one row per (list, item) in `price_list_items` — no new column per tier, just more rows. Items not listed fall back to the item's default sale price.
  - Optional **quantity-break tiers** (`price_list_tiers`): per item, set price bands like 1-5 / 6-15 / 16-25 / 26+. Applied per sale line only when its **Qty disc** checkbox is ticked.
- Work Orders with auto-generated number `WO-YYYY-NNNN`.
- Sales: multi-line bills mixing Products and Raw Materials, auto number `SALE-YYYY-NNNN`, immediate stock-out on save. Can pre-fill a line from an existing Work Order. Per-invoice pending amount shown; click pending to open a pre-filled receipt.
- Workflow: **Draft → In Progress → Completed → Cancelled**.
  - Moving to **In Progress** reserves & deducts RM stock (per BOM).
  - **Completed** records consumption, increases Final stock.
  - **Cancelled** from In Progress releases RM back to stock.
- Receipts: simple "amount received" log per customer (matched against WO + Sale totals).
- Reports:
  - RM Stock + low-stock highlight
  - RM Stock Ledger (per material, running balance with opening/closing)
  - Final Product Stock + stock value at base price
  - WO Status (filter by customer/status/date)
  - Customer Outstanding (billed vs received; billed = Sales only, Work Orders are not billed)

## Project layout
```
index.php           front controller
.htaccess           rewrite + access rules (lives next to index.php)
assets/             public static files (CSS, etc.)
src/                application code — blocked from web access by .htaccess
sql/                schema + seed + migrations — blocked from web access
config.sample.php   copy to config.php and edit (git-ignored)
```

The whole repo folder is dropped in as-is. No "public/" subfolder — the
front controller lives next to `.htaccess`, which blocks `src/`, `sql/`,
`.git/`, and the config files from direct download.

## Local development

```bash
# 1. create DB and import schema + seed
mysql -u root -p -e "CREATE DATABASE inventory_wo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p inventory_wo < sql/schema.sql
mysql -u root -p inventory_wo < sql/seed.sql

# 2. config
cp config.sample.php config.php
# edit DB credentials, set app_url=http://localhost:8000, app_env=development,
# and session_secure_cookie => false for plain HTTP

# 3. serve
php -S 127.0.0.1:8000
```

Open http://127.0.0.1:8000 and sign in with **admin / admin123**.
**Change this password immediately** from the Users page.

## Deploying to cPanel — subdirectory install (e.g. `aromen.biz/aniket`)

1. In cPanel → **MySQL Databases**, create a database + user, attach with all privileges.
2. In **phpMyAdmin**, import `sql/schema.sql`, then `sql/seed.sql`.
3. In **File Manager** (or FTP), upload the whole project into
   `public_html/aniket/` — so you end up with `public_html/aniket/index.php`,
   `public_html/aniket/.htaccess`, `public_html/aniket/src/`, etc.
4. Copy `config.sample.php` → `config.php` (still in `public_html/aniket/`) and set:
   - `app_url`  = `https://aromen.biz/aniket`   (no trailing slash)
   - `db.host`, `db.name`, `db.user`, `db.pass` = the values from step 1
   - `session_secure_cookie` = `true`
   - `app_env` = `production`
5. In **MultiPHP Manager**, select PHP 8.4 for the domain.
6. Visit `https://aromen.biz/aniket/` → log in as **admin / admin123** → go to **Users** and change the password.

For a top-level (whole-domain) install: upload to `public_html/` instead and
set `app_url` to `https://aromen.biz` (no path).

## Upgrading an existing v1 install

If you already imported the original `sql/schema.sql` and have data, do NOT
re-import it (it drops tables). Instead apply the migration:

```bash
mysql -u <user> -p <db> < sql/migration_2_add_sales.sql
```

This adds the Sales module, RM sale price, and generalises the customer
price list to cover both RM and products without losing existing data.

For v2 -> v3 (named price lists attached to customers), also run:

```bash
mysql -u <user> -p <db> < sql/migration_3_price_lists.sql
```

This drops the old per-customer `customer_prices` override table and
introduces `price_lists` + `price_list_items` with a `customers.price_list_id`
FK. Existing customer rows survive with `price_list_id = NULL` (they fall
back to default prices until you attach them to a list).

For v3 -> v4 (plain-text passwords), also run:

```bash
mysql -u <user> -p <db> < sql/migration_4_plain_text_passwords.sql
```

This renames `users.password_hash` to `users.password` and resets every
existing user's password to `admin123`. Sign in and change them on the
Users page right after import.

**Security note:** v4 onwards stores user passwords in plain text per
the owner's decision. Anyone with DB or backup access can read every
user's password. Treat the DB and its backups accordingly.

For v4 -> v5 (receipts against invoices) and v5 -> v6 (quantity tiers):

```bash
mysql -u <user> -p <db> < sql/migration_5_receipt_against_sale.sql
mysql -u <user> -p <db> < sql/migration_6_qty_tiers.sql
```

migration 5 adds `receipts.sale_id`; migration 6 adds the
`price_list_tiers` table.

## Notes / Defaults
- Default admin: `admin` / `admin123` — change immediately.
- WO numbering is per-calendar-year; the counter is stored in `wo_counters`.
- `wo_rm_allocations` snapshots BOM × WO qty at the moment a WO is started,
  so editing BOM later does not affect historical work orders.
- All stock changes write a row to `stock_movements` (full audit trail).
