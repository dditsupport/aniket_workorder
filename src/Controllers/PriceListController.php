<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Csv;
use App\Database;
use App\Helpers;

final class PriceListController
{
    public static function exportCsv(): void
    {
        $rows = Database::pdo()->query("SELECT name, description, active FROM price_lists ORDER BY name")->fetchAll();
        Csv::download('price_lists.csv', ['name','description','active'], $rows);
    }

    public static function importCsv(): void
    {
        try {
            $rows = Csv::parseUpload('file', ['name']);
        } catch (\Throwable $e) {
            Helpers::flash('error', 'Import failed: ' . $e->getMessage());
            Helpers::redirect('/price-lists');
        }
        $db = Database::pdo();
        $ins = $db->prepare("INSERT INTO price_lists (name, description, active) VALUES (?,?,?)");
        $upd = $db->prepare("UPDATE price_lists SET description=?, active=? WHERE name=?");
        $sel = $db->prepare("SELECT id FROM price_lists WHERE name=?");
        $created = $updated = 0; $errors = [];
        $db->beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $name = (string)($r['name'] ?? '');
                if ($name === '') { $errors[] = 'Row ' . ($i + 2) . ': name required.'; continue; }
                $desc = ($r['description'] ?? '') ?: null;
                $act  = Csv::bool($r['active'] ?? '1');
                $sel->execute([$name]);
                if ($sel->fetch()) { $upd->execute([$desc, $act, $name]); $updated++; }
                else               { $ins->execute([$name, $desc, $act]); $created++; }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Import aborted: ' . $e->getMessage());
            Helpers::redirect('/price-lists');
        }
        $msg = "Imported: {$created} new, {$updated} updated.";
        if ($errors) $msg .= ' ' . count($errors) . ' skipped: ' . implode(' | ', array_slice($errors, 0, 5));
        Helpers::flash($errors ? 'error' : 'success', $msg);
        Helpers::redirect('/price-lists');
    }

    /** Per-list items CSV: columns item_kind, item_code, price. */
    public static function exportItemsCsv(array $p): void
    {
        $id = (int)$p['id'];
        $list = self::find($id);
        $stmt = Database::pdo()->prepare(
            "SELECT pli.item_kind,
                    CASE WHEN pli.item_kind='FG' THEN pr.code ELSE rm.code END AS item_code,
                    pli.price
             FROM price_list_items pli
             LEFT JOIN products pr      ON pli.item_kind='FG' AND pr.id = pli.item_id
             LEFT JOIN raw_materials rm ON pli.item_kind='RM' AND rm.id = pli.item_id
             WHERE pli.price_list_id=? ORDER BY pli.item_kind, item_code"
        );
        $stmt->execute([$id]);
        $fname = 'price_list_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$list['name']) . '.csv';
        Csv::download($fname, ['item_kind','item_code','price'], $stmt->fetchAll());
    }

    public static function importItemsCsv(array $p): void
    {
        $id = (int)$p['id'];
        self::find($id);
        try {
            $rows = Csv::parseUpload('file', ['item_kind','item_code','price']);
        } catch (\Throwable $e) {
            Helpers::flash('error', 'Import failed: ' . $e->getMessage());
            Helpers::redirect('/price-lists/' . $id);
        }
        $db = Database::pdo();
        // Pre-cache code -> id maps for both kinds.
        $fg = [];
        foreach ($db->query("SELECT id, code FROM products")->fetchAll() as $r) $fg[$r['code']] = (int)$r['id'];
        $rm = [];
        foreach ($db->query("SELECT id, code FROM raw_materials")->fetchAll() as $r) $rm[$r['code']] = (int)$r['id'];

        $up = $db->prepare(
            "INSERT INTO price_list_items (price_list_id, item_kind, item_id, price) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE price = VALUES(price)"
        );
        $applied = 0; $errors = [];
        $db->beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $kind = strtoupper(trim((string)($r['item_kind'] ?? '')));
                $code = (string)($r['item_code'] ?? '');
                $price = (float)($r['price'] ?? -1);
                if (!in_array($kind, ['RM','FG'], true) || $code === '' || $price < 0) {
                    $errors[] = 'Row ' . ($i + 2) . ': bad kind/code/price.';
                    continue;
                }
                $iid = $kind === 'FG' ? ($fg[$code] ?? null) : ($rm[$code] ?? null);
                if (!$iid) { $errors[] = 'Row ' . ($i + 2) . ": {$kind} code '{$code}' not found."; continue; }
                $up->execute([$id, $kind, $iid, $price]);
                $applied++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Import aborted: ' . $e->getMessage());
            Helpers::redirect('/price-lists/' . $id);
        }
        $msg = "Applied {$applied} price(s).";
        if ($errors) $msg .= ' ' . count($errors) . ' skipped: ' . implode(' | ', array_slice($errors, 0, 5));
        Helpers::flash($errors ? 'error' : 'success', $msg);
        Helpers::redirect('/price-lists/' . $id);
    }

    public static function index(): void
    {
        $rows = Database::pdo()->query(
            "SELECT pl.id, pl.name, pl.description, pl.active,
                    (SELECT COUNT(*) FROM price_list_items WHERE price_list_id = pl.id) AS line_count,
                    (SELECT COUNT(*) FROM customers WHERE price_list_id = pl.id) AS customer_count
             FROM price_lists pl
             ORDER BY pl.name"
        )->fetchAll();
        Helpers::render('price_list/index', ['title' => 'Price Lists', 'rows' => $rows]);
    }

    public static function create(): void
    {
        Helpers::render('price_list/form', ['title' => 'New Price List', 'row' => null]);
    }

    public static function edit(array $p): void
    {
        // Edit + items management now live on one page.
        Helpers::redirect('/price-lists/' . (int)$p['id']);
    }

    public static function store(): void
    {
        $d = self::collect();
        $err = self::validate($d);
        if ($err) { Helpers::flash('error', $err); Helpers::redirect('/price-lists/new'); }
        try {
            $stmt = Database::pdo()->prepare("INSERT INTO price_lists (name, description, active) VALUES (?,?,?)");
            $stmt->execute([$d['name'], $d['description'], $d['active']]);
            $id = (int)Database::pdo()->lastInsertId();
        } catch (\PDOException $e) {
            Helpers::flash('error', $e->errorInfo[1] === 1062 ? 'A price list with that name already exists.' : 'Could not save.');
            Helpers::redirect('/price-lists/new');
        }
        Helpers::flash('success', 'Price list created.');
        Helpers::redirect('/price-lists/' . $id);
    }

    public static function update(array $p): void
    {
        $id = (int)$p['id'];
        self::find($id);
        $d = self::collect();
        $err = self::validate($d);
        if ($err) { Helpers::flash('error', $err); Helpers::redirect("/price-lists/{$id}"); }
        try {
            $stmt = Database::pdo()->prepare("UPDATE price_lists SET name=?, description=?, active=? WHERE id=?");
            $stmt->execute([$d['name'], $d['description'], $d['active'], $id]);
        } catch (\PDOException $e) {
            Helpers::flash('error', $e->errorInfo[1] === 1062 ? 'A price list with that name already exists.' : 'Could not update.');
            Helpers::redirect("/price-lists/{$id}");
        }
        Helpers::flash('success', 'Price list updated.');
        Helpers::redirect('/price-lists/' . $id);
    }

    public static function destroy(array $p): void
    {
        $id = (int)$p['id'];
        $db = Database::pdo();
        $usedBy = (int)$db->query("SELECT COUNT(*) FROM customers WHERE price_list_id = {$id}")->fetchColumn();
        if ($usedBy > 0) {
            Helpers::flash('error', "Cannot delete — {$usedBy} customer(s) are attached to this list.");
            Helpers::redirect('/price-lists');
        }
        $db->prepare("DELETE FROM price_lists WHERE id=?")->execute([$id]);
        Helpers::flash('success', 'Price list deleted.');
        Helpers::redirect('/price-lists');
    }

    /** Manage line items inside a price list. */
    public static function show(array $p): void
    {
        $id = (int)$p['id'];
        $list = self::find($id);
        $db = Database::pdo();

        $lines = $db->prepare(
            "SELECT pli.*,
                    CASE WHEN pli.item_kind='FG' THEN pr.code ELSE rm.code END AS item_code,
                    CASE WHEN pli.item_kind='FG' THEN pr.name ELSE rm.name END AS item_name,
                    CASE WHEN pli.item_kind='FG' THEN pr.base_price ELSE rm.sale_price END AS base_price
             FROM price_list_items pli
             LEFT JOIN products pr       ON pli.item_kind='FG' AND pr.id  = pli.item_id
             LEFT JOIN raw_materials rm  ON pli.item_kind='RM' AND rm.id = pli.item_id
             WHERE pli.price_list_id = ?
             ORDER BY pli.item_kind, item_name"
        );
        $lines->execute([$id]);

        $products = $db->query("SELECT id, code, name, base_price FROM products ORDER BY name")->fetchAll();
        $rms      = $db->query("SELECT id, code, name, sale_price FROM raw_materials ORDER BY name")->fetchAll();

        $tiers = $db->prepare(
            "SELECT t.*,
                    CASE WHEN t.item_kind='FG' THEN pr.code ELSE rm.code END AS item_code,
                    CASE WHEN t.item_kind='FG' THEN pr.name ELSE rm.name END AS item_name
             FROM price_list_tiers t
             LEFT JOIN products pr      ON t.item_kind='FG' AND pr.id = t.item_id
             LEFT JOIN raw_materials rm ON t.item_kind='RM' AND rm.id = t.item_id
             WHERE t.price_list_id = ?
             ORDER BY t.item_kind, item_name, t.min_qty"
        );
        $tiers->execute([$id]);

        Helpers::render('price_list/show', [
            'title' => $list['name'],
            'list' => $list,
            'lines' => $lines->fetchAll(),
            'products' => $products,
            'rms' => $rms,
            'tiers' => $tiers->fetchAll(),
        ]);
    }

    public static function addTier(array $p): void
    {
        $id = (int)$p['id'];
        self::find($id);
        $kind = (string)Helpers::input('item_kind', '');
        $iid  = (int)Helpers::input('item_id', 0);
        $min  = (float)Helpers::input('min_qty', 0);
        $maxRaw = trim((string)Helpers::input('max_qty', ''));
        $max  = $maxRaw === '' ? null : (float)$maxRaw;
        $price = (float)Helpers::input('price', -1);
        if (!in_array($kind, ['RM','FG'], true) || $iid <= 0 || $min <= 0 || $price < 0) {
            Helpers::flash('error', 'Pick item, a positive min qty, and a non-negative price.');
            Helpers::redirect('/price-lists/' . $id);
        }
        if ($max !== null && $max < $min) {
            Helpers::flash('error', 'Max qty cannot be less than min qty.');
            Helpers::redirect('/price-lists/' . $id);
        }
        Database::pdo()->prepare(
            "INSERT INTO price_list_tiers (price_list_id, item_kind, item_id, min_qty, max_qty, price) VALUES (?,?,?,?,?,?)"
        )->execute([$id, $kind, $iid, $min, $max, $price]);
        Helpers::flash('success', 'Quantity tier added.');
        Helpers::redirect('/price-lists/' . $id);
    }

    public static function removeTier(array $p): void
    {
        $id  = (int)$p['id'];
        $tid = (int)$p['tid'];
        Database::pdo()->prepare("DELETE FROM price_list_tiers WHERE id=? AND price_list_id=?")
            ->execute([$tid, $id]);
        Helpers::flash('success', 'Tier removed.');
        Helpers::redirect('/price-lists/' . $id);
    }

    public static function upsertItem(array $p): void
    {
        $id    = (int)$p['id'];
        self::find($id);
        $kind  = (string)Helpers::input('item_kind', '');
        $iid   = (int)Helpers::input('item_id', 0);
        $price = (float)Helpers::input('price', 0);
        if (!in_array($kind, ['RM','FG'], true) || $iid <= 0 || $price < 0) {
            Helpers::flash('error', 'Pick item type & item; price must be non-negative.');
            Helpers::redirect('/price-lists/' . $id);
        }
        Database::pdo()->prepare(
            "INSERT INTO price_list_items (price_list_id, item_kind, item_id, price) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE price = VALUES(price)"
        )->execute([$id, $kind, $iid, $price]);
        Helpers::flash('success', 'Price saved.');
        Helpers::redirect('/price-lists/' . $id);
    }

    public static function updateItem(array $p): void
    {
        $id  = (int)$p['id'];
        $lid = (int)$p['lid'];
        $price = (float)Helpers::input('price', -1);
        if ($price < 0) {
            Helpers::flash('error', 'Price must be a non-negative number.');
            Helpers::redirect('/price-lists/' . $id);
        }
        $stmt = Database::pdo()->prepare(
            "UPDATE price_list_items SET price=? WHERE id=? AND price_list_id=?"
        );
        $stmt->execute([$price, $lid, $id]);
        if ($stmt->rowCount() === 0) {
            Helpers::flash('error', 'No matching price entry to update.');
        } else {
            Helpers::flash('success', 'Price updated.');
        }
        Helpers::redirect('/price-lists/' . $id);
    }

    public static function destroyItem(array $p): void
    {
        $id  = (int)$p['id'];
        $lid = (int)$p['lid'];
        Database::pdo()->prepare("DELETE FROM price_list_items WHERE id=? AND price_list_id=?")
            ->execute([$lid, $id]);
        Helpers::flash('success', 'Price removed.');
        Helpers::redirect('/price-lists/' . $id);
    }

    private static function find(int $id): array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM price_lists WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Helpers::abort(404, 'Price list not found');
        return $row;
    }

    private static function collect(): array
    {
        return [
            'name'        => trim((string)Helpers::input('name')),
            'description' => trim((string)Helpers::input('description')) ?: null,
            'active'      => Helpers::input('active') ? 1 : 0,
        ];
    }

    private static function validate(array $d): ?string
    {
        if ($d['name'] === '' || strlen($d['name']) > 100) return 'Name is required (max 100 chars).';
        return null;
    }
}
