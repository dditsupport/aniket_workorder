<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Csv;
use App\Database;
use App\Helpers;

final class BomController
{
    public static function exportCsv(array $p): void
    {
        $pid = (int)$p['id'];
        $product = self::findProduct($pid);
        $stmt = Database::pdo()->prepare(
            "SELECT rm.code AS rm_code, b.qty_per_unit
             FROM bom b JOIN raw_materials rm ON rm.id = b.raw_material_id
             WHERE b.product_id = ? ORDER BY rm.name"
        );
        $stmt->execute([$pid]);
        $fname = 'bom_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$product['code']) . '.csv';
        Csv::download($fname, ['rm_code','qty_per_unit'], $stmt->fetchAll());
    }

    public static function importCsv(array $p): void
    {
        $pid = (int)$p['id'];
        self::findProduct($pid);
        try {
            $rows = Csv::parseUpload('file', ['rm_code','qty_per_unit']);
        } catch (\Throwable $e) {
            Helpers::flash('error', 'Import failed: ' . $e->getMessage());
            Helpers::redirect("/products/{$pid}/bom");
        }
        $db = Database::pdo();
        $rmMap = [];
        foreach ($db->query("SELECT id, code FROM raw_materials")->fetchAll() as $r) $rmMap[$r['code']] = (int)$r['id'];
        $up = $db->prepare(
            "INSERT INTO bom (product_id, raw_material_id, qty_per_unit) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE qty_per_unit = VALUES(qty_per_unit)"
        );
        $applied = 0; $errors = [];
        $db->beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $code = (string)($r['rm_code'] ?? '');
                $qty  = (float)($r['qty_per_unit'] ?? 0);
                if ($code === '' || $qty <= 0) { $errors[] = 'Row ' . ($i + 2) . ': rm_code and positive qty required.'; continue; }
                $rmId = $rmMap[$code] ?? null;
                if (!$rmId) { $errors[] = 'Row ' . ($i + 2) . ": rm_code '{$code}' not found."; continue; }
                $up->execute([$pid, $rmId, $qty]);
                $applied++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Import aborted: ' . $e->getMessage());
            Helpers::redirect("/products/{$pid}/bom");
        }
        $msg = "Applied {$applied} BOM line(s).";
        if ($errors) $msg .= ' ' . count($errors) . ' skipped: ' . implode(' | ', array_slice($errors, 0, 5));
        Helpers::flash($errors ? 'error' : 'success', $msg);
        Helpers::redirect("/products/{$pid}/bom");
    }

    public static function index(array $p): void
    {
        $pid = (int)$p['id'];
        $product = self::findProduct($pid);
        $db = Database::pdo();
        $rows = $db->prepare(
            "SELECT b.id, b.qty_per_unit, rm.id AS rm_id, rm.code, rm.name, rm.unit
             FROM bom b JOIN raw_materials rm ON rm.id = b.raw_material_id
             WHERE b.product_id = ? ORDER BY rm.name"
        );
        $rows->execute([$pid]);
        $rms = $db->query("SELECT id, code, name, unit FROM raw_materials ORDER BY name")->fetchAll();
        Helpers::render('bom/index', [
            'title' => 'BOM — ' . $product['name'],
            'product' => $product,
            'rows' => $rows->fetchAll(),
            'rms' => $rms,
        ]);
    }

    public static function store(array $p): void
    {
        $pid = (int)$p['id'];
        self::findProduct($pid);
        $rmId = (int)Helpers::input('raw_material_id', 0);
        $qty = (float)Helpers::input('qty_per_unit', 0);
        if ($rmId <= 0 || $qty <= 0) {
            Helpers::flash('error', 'Pick a raw material and enter a positive quantity.');
            Helpers::redirect("/products/{$pid}/bom");
        }
        try {
            $db = Database::pdo();
            $stmt = $db->prepare(
                "INSERT INTO bom (product_id, raw_material_id, qty_per_unit) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE qty_per_unit = VALUES(qty_per_unit)"
            );
            $stmt->execute([$pid, $rmId, $qty]);
            Helpers::flash('success', 'BOM line saved.');
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not save BOM line.');
        }
        Helpers::redirect("/products/{$pid}/bom");
    }

    public static function destroy(array $p): void
    {
        $pid = (int)$p['id'];
        $bid = (int)$p['bid'];
        Database::pdo()->prepare("DELETE FROM bom WHERE id=? AND product_id=?")->execute([$bid, $pid]);
        Helpers::flash('success', 'BOM line removed.');
        Helpers::redirect("/products/{$pid}/bom");
    }

    private static function findProduct(int $id): array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) Helpers::abort(404, 'Product not found');
        return $r;
    }
}
