-- Migration 4: switch users.password_hash (bcrypt) to users.password (plain text).
-- Per owner decision. Anyone with DB read access can now read every user's
-- password. Apply AFTER migration_3_price_lists.sql.

SET NAMES utf8mb4;

-- The previous column held bcrypt hashes which cannot be reversed.
-- Rename the column, then reset all known passwords to a fresh known value
-- so users can log in again. Change them right after import via the Users page.
ALTER TABLE users CHANGE COLUMN password_hash password VARCHAR(255) NOT NULL;
UPDATE users SET password = 'admin123';   -- everyone's password reset; please update.
