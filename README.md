# Inventory & Work-Order Tracker

A small in-house web app to manage customers, raw materials, final products,
BOM, customer-wise price lists, work orders (with BOM-driven stock movements)
and amount-received logs per customer.

Stack: **PHP 8.4** + **MySQL** + plain PDO (no framework). Designed for
shared hosting (MilesWeb / cPanel).

## Features
- Multi-user login with roles: `admin` / `operator` / `viewer`.
- Masters: Customers, Raw Materials (sellable, with sale price), Products (Final, sellable), BOM, unified Customer-wise Price List (covers both RM and Products).
- Work Orders with auto-generated number `WO-YYYY-NNNN`.
- Sales: multi-line bills mixing Products and Raw Materials, auto number `SALE-YYYY-NNNN`, immediate stock-out on save.
- Workflow: **Draft → In Progress → Completed → Cancelled**.
  - Moving to **In Progress** reserves & deducts RM stock (per BOM).
  - **Completed** records consumption, increases Final stock.
  - **Cancelled** from In Progress releases RM back to stock.
- Receipts: simple "amount received" log per customer (matched against WO + Sale totals).
- Reports:
  - RM Stock + low-stock highlight
  - Final Product Stock + stock value at base price
  - WO Status (filter by customer/status/date)
  - Customer Outstanding (billed vs received)

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

## Notes / Defaults
- Default admin: `admin` / `admin123` — change immediately.
- WO numbering is per-calendar-year; the counter is stored in `wo_counters`.
- `wo_rm_allocations` snapshots BOM × WO qty at the moment a WO is started,
  so editing BOM later does not affect historical work orders.
- All stock changes write a row to `stock_movements` (full audit trail).
