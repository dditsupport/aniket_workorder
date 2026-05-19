<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csv;
use App\Database;
use App\Helpers;

final class RawMaterialController
{
    public static function index(): void
    {
        $rows = Database::pdo()->query(
            "SELECT id, code, name, unit, reorder_level, stock_qty, sale_price FROM raw_materials ORDER BY name"
        )->fetchAll();
        Helpers::render('raw_materials/index', ['title' => 'Raw Materials', 'rows' => $rows]);
    }

    public static function create(): void
    {
        Helpers::render('raw_materials/form', ['title' => 'New Raw Material', 'row' => null]);
    }

    public static function edit(array $p): void
    {
        $row = self::find((int)$p['id']);
        Helpers::render('raw_materials/form', ['title' => 'Edit Raw Material', 'row' => $row]);
    }

    public static function store(): void
    {
        $d = self::collect();
        if ($e = self::validate($d)) { Helpers::flash('error', $e); Helpers::redirect('/raw-materials/new'); }
        try {
            $stmt = Database::pdo()->prepare(
                "INSERT INTO raw_materials (code,name,unit,reorder_level,stock_qty,sale_price) VALUES (?,?,?,?,?,?)"
            );
            $stmt->execute([$d['code'], $d['name'], $d['unit'], $d['reorder_level'], $d['stock_qty'], $d['sale_price']]);
            $id = (int)Database::pdo()->lastInsertId();
            if ($d['stock_qty'] > 0) {
                self::movement('RM', $id, $d['stock_qty'], 0, 'ADJUST', null, 'initial stock');
            }
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not save: ' . ($e->errorInfo[1] === 1062 ? 'code already exists' : 'database error'));
            Helpers::redirect('/raw-materials/new');
        }
        Helpers::flash('success', 'Raw material added.');
        Helpers::redirect('/raw-materials');
    }

    public static function update(array $p): void
    {
        $id = (int)$p['id'];
        $current = self::find($id);
        $d = self::collect();
        if ($e = self::validate($d, edit: true)) { Helpers::flash('error', $e); Helpers::redirect("/raw-materials/{$id}/edit"); }
        // On edit, do NOT touch stock_qty directly — use the adjust action.
        try {
            $stmt = Database::pdo()->prepare(
                "UPDATE raw_materials SET code=?,name=?,unit=?,reorder_level=?,sale_price=? WHERE id=?"
            );
            $stmt->execute([$d['code'], $d['name'], $d['unit'], $d['reorder_level'], $d['sale_price'], $id]);
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not update: ' . ($e->errorInfo[1] === 1062 ? 'code already exists' : 'database error'));
            Helpers::redirect("/raw-materials/{$id}/edit");
        }
        Helpers::flash('success', 'Raw material updated.');
        Helpers::redirect('/raw-materials');
    }

    public static function destroy(array $p): void
    {
        try {
            Database::pdo()->prepare("DELETE FROM raw_materials WHERE id=?")->execute([(int)$p['id']]);
            Helpers::flash('success', 'Raw material deleted.');
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Cannot delete: raw material is referenced by BOM or work orders.');
        }
        Helpers::redirect('/raw-materials');
    }

    public static function adjust(array $p): void
    {
        $id = (int)$p['id'];
        $rm = self::find($id);
        $delta = (float)Helpers::input('delta', 0);
        $note = trim((string)Helpers::input('note', '')) ?: 'manual adjustment';
        if ($delta == 0.0) {
            Helpers::flash('error', 'Adjustment quantity cannot be zero.');
            Helpers::redirect('/raw-materials');
        }
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $newQty = (float)$rm['stock_qty'] + $delta;
            if ($newQty < 0) {
                $db->rollBack();
                Helpers::flash('error', 'Adjustment would make stock negative.');
                Helpers::redirect('/raw-materials');
            }
            $db->prepare("UPDATE raw_materials SET stock_qty = stock_qty + ? WHERE id = ?")
               ->execute([$delta, $id]);
            self::movement('RM', $id, $delta > 0 ? $delta : 0, $delta < 0 ? -$delta : 0, 'ADJUST', null, $note);
            $db->commit();
            Helpers::flash('success', 'Stock adjusted.');
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Could not adjust stock.');
        }
        Helpers::redirect('/raw-materials');
    }

    public static function exportCsv(): void
    {
        $rows = Database::pdo()->query(
            "SELECT code, name, unit, reorder_level, stock_qty, sale_price FROM raw_materials ORDER BY name"
        )->fetchAll();
        Csv::download('raw_materials.csv', ['code','name','unit','reorder_level','stock_qty','sale_price'], $rows);
    }

    public static function importCsv(): void
    {
        try {
            $rows = Csv::parseUpload('file', ['code','name']);
        } catch (\Throwable $e) {
            Helpers::flash('error', 'Import failed: ' . $e->getMessage());
            Helpers::redirect('/raw-materials');
        }
        $db = Database::pdo();
        $ins = $db->prepare("INSERT INTO raw_materials (code,name,unit,reorder_level,stock_qty,sale_price) VALUES (?,?,?,?,?,?)");
        $upd = $db->prepare("UPDATE raw_materials SET name=?, unit=?, reorder_level=?, sale_price=? WHERE code=?");
        $sel = $db->prepare("SELECT id, stock_qty FROM raw_materials WHERE code=?");
        $created = $updated = 0; $errors = [];
        $db->beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $code = (string)($r['code'] ?? '');
                $name = (string)($r['name'] ?? '');
                if ($code === '' || $name === '') { $errors[] = 'Row ' . ($i + 2) . ': code and name required.'; continue; }
                $unit = trim((string)($r['unit'] ?? 'pcs')) ?: 'pcs';
                $reorder = (float)($r['reorder_level'] ?? 0);
                $stock   = (float)($r['stock_qty']     ?? 0);
                $price   = (float)($r['sale_price']    ?? 0);
                $sel->execute([$code]);
                $existing = $sel->fetch();
                if ($existing) {
                    $upd->execute([$name, $unit, $reorder, $price, $code]);
                    // Stock import does not silently move stock; use Stock Adjust import for that.
                    $updated++;
                } else {
                    $ins->execute([$code, $name, $unit, $reorder, $stock, $price]);
                    $newId = (int)$db->lastInsertId();
                    if ($stock > 0) self::movement('RM', $newId, $stock, 0, 'ADJUST', null, 'CSV import opening stock');
                    $created++;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Import aborted: ' . $e->getMessage());
            Helpers::redirect('/raw-materials');
        }
        $msg = "Imported: {$created} new, {$updated} updated.";
        if ($errors) $msg .= ' ' . count($errors) . ' row(s) skipped: ' . implode(' | ', array_slice($errors, 0, 5));
        Helpers::flash($errors ? 'error' : 'success', $msg);
        Helpers::redirect('/raw-materials');
    }

    public static function adjustImportCsv(): void
    {
        try {
            $rows = Csv::parseUpload('file', ['code','delta']);
        } catch (\Throwable $e) {
            Helpers::flash('error', 'Import failed: ' . $e->getMessage());
            Helpers::redirect('/raw-materials');
        }
        $db = Database::pdo();
        $sel = $db->prepare("SELECT id, stock_qty FROM raw_materials WHERE code=?");
        $upd = $db->prepare("UPDATE raw_materials SET stock_qty = stock_qty + ? WHERE id=?");
        $applied = 0; $errors = [];
        $db->beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $code = (string)($r['code'] ?? '');
                $delta = (float)($r['delta'] ?? 0);
                $note = trim((string)($r['note'] ?? '')) ?: 'bulk adjustment';
                if ($code === '' || $delta == 0.0) {
                    $errors[] = 'Row ' . ($i + 2) . ': code required and delta non-zero.';
                    continue;
                }
                $sel->execute([$code]);
                $rm = $sel->fetch();
                if (!$rm) { $errors[] = 'Row ' . ($i + 2) . ": code '{$code}' not found."; continue; }
                if ((float)$rm['stock_qty'] + $delta < 0) {
                    $errors[] = 'Row ' . ($i + 2) . ": '{$code}' would go below zero.";
                    continue;
                }
                $upd->execute([$delta, (int)$rm['id']]);
                self::movement('RM', (int)$rm['id'], $delta > 0 ? $delta : 0, $delta < 0 ? -$delta : 0, 'ADJUST', null, $note);
                $applied++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Import aborted: ' . $e->getMessage());
            Helpers::redirect('/raw-materials');
        }
        $msg = "Adjusted: {$applied} row(s).";
        if ($errors) $msg .= ' ' . count($errors) . ' skipped: ' . implode(' | ', array_slice($errors, 0, 5));
        Helpers::flash($errors ? 'error' : 'success', $msg);
        Helpers::redirect('/raw-materials');
    }

    public static function movement(string $itemType, int $itemId, float $in, float $out, string $refType, ?int $refId, ?string $note): void
    {
        $stmt = Database::pdo()->prepare(
            "INSERT INTO stock_movements (item_type,item_id,qty_in,qty_out,ref_type,ref_id,note,created_by)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([$itemType, $itemId, $in, $out, $refType, $refId, $note, Auth::userId()]);
    }

    private static function find(int $id): array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM raw_materials WHERE id=?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) Helpers::abort(404, 'Raw material not found');
        return $r;
    }

    private static function collect(): array
    {
        return [
            'code' => trim((string)Helpers::input('code')),
            'name' => trim((string)Helpers::input('name')),
            'unit' => trim((string)Helpers::input('unit', 'pcs')),
            'reorder_level' => (float)Helpers::input('reorder_level', 0),
            'stock_qty'     => (float)Helpers::input('stock_qty', 0),
            'sale_price'    => (float)Helpers::input('sale_price', 0),
        ];
    }

    private static function validate(array $d, bool $edit = false): ?string
    {
        if ($d['code'] === '' || strlen($d['code']) > 30) return 'Code is required (max 30).';
        if ($d['name'] === '' || strlen($d['name']) > 150) return 'Name is required (max 150).';
        if ($d['unit'] === '' || strlen($d['unit']) > 20) return 'Unit is required (max 20).';
        if ($d['reorder_level'] < 0) return 'Reorder level cannot be negative.';
        if ($d['sale_price'] < 0) return 'Sale price cannot be negative.';
        if (!$edit && $d['stock_qty'] < 0) return 'Stock qty cannot be negative.';
        return null;
    }
}
