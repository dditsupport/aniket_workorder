# Inventory & Work-Order Tracker

A small in-house web app to manage customers, raw materials, final products,
BOM, customer-wise price lists, work orders (with BOM-driven stock movements)
and amount-received logs per customer.

Stack: **PHP 8.4** + **MySQL** + plain PDO (no framework). Designed for
shared hosting (MilesWeb / cPanel).

## Features
- Multi-user login with roles: `admin` / `operator` / `viewer`.
- Masters: Customers, Raw Materials, Products (Final), BOM, Customer-wise Price List.
- Work Orders with auto-generated number `WO-YYYY-NNNN`.
- Workflow: **Draft → In Progress → Completed → Cancelled**.
  - Moving to **In Progress** reserves & deducts RM stock (per BOM).
  - **Completed** records consumption, increases Final stock.
  - **Cancelled** from In Progress releases RM back to stock.
- Receipts: simple "amount received" log per customer.
- Reports:
  - RM Stock + low-stock highlight
  - Final Product Stock + stock value at base price
  - WO Status (filter by customer/status/date)
  - Customer Outstanding (billed vs received)

## Project layout
```
public/        document root (front controller + .htaccess)
src/           application code (controllers, views, framework files)
sql/           schema + seed
config.sample.php   copy to config.php and edit
```

## Local development

```bash
# 1. create DB and import schema + seed
mysql -u root -p -e "CREATE DATABASE inventory_wo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p inventory_wo < sql/schema.sql
mysql -u root -p inventory_wo < sql/seed.sql

# 2. config
cp config.sample.php config.php
# edit DB credentials and set app_url=http://localhost:8000 and app_env=development
# also set session_secure_cookie => false for plain HTTP

# 3. serve
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000 and sign in with **admin / admin123**.
**Change this password immediately** from the Users page.

## Deploying to MilesWeb (cPanel)

1. In cPanel → **MySQL Databases**, create a database and a user, attach the user to the DB with all privileges.
2. In **phpMyAdmin**, open that DB and import `sql/schema.sql`, then `sql/seed.sql`.
3. Upload the project files via **File Manager** or FTP.
   - Easiest: upload everything into `public_html/`.
     The included root `.htaccess` rewrites all requests into `public/`,
     and denies access to `src/`, `sql/`, `.git/`, and the config files.
   - Better (if cPanel allows changing the document root): point the domain/subdomain to the `public/` folder directly.
4. Copy `config.sample.php` → `config.php` in the project root (NOT inside `public/`) and fill in:
   - `app_url` = your site URL, no trailing slash (e.g. `https://shop.example.com`)
   - `db.host`, `db.name`, `db.user`, `db.pass` = the values from step 1
   - `session_secure_cookie` = `true` (since cPanel sites are usually HTTPS)
   - `app_env` = `production`
5. Ensure PHP 8.4 is selected in **MultiPHP Manager** for your domain.
6. Visit the site → log in as **admin / admin123** → go to **Users** and change the password.

## Notes / Defaults
- Default admin: `admin` / `admin123` — change immediately.
- WO numbering is per-calendar-year; the counter is stored in `wo_counters`.
- `wo_rm_allocations` snapshots BOM × WO qty at the moment a WO is started,
  so editing BOM later does not affect historical work orders.
- All stock changes write a row to `stock_movements` (full audit trail).
