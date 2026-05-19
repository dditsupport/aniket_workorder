<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Csv;
use App\Database;
use App\Helpers;

final class ProductController
{
    public static function exportCsv(): void
    {
        $rows = Database::pdo()->query(
            "SELECT code, name, unit, base_price, stock_qty FROM products ORDER BY name"
        )->fetchAll();
        Csv::download('products.csv', ['code','name','unit','base_price','stock_qty'], $rows);
    }

    public static function importCsv(): void
    {
        try {
            $rows = Csv::parseUpload('file', ['code','name']);
        } catch (\Throwable $e) {
            Helpers::flash('error', 'Import failed: ' . $e->getMessage());
            Helpers::redirect('/products');
        }
        $db = Database::pdo();
        $ins = $db->prepare("INSERT INTO products (code,name,unit,base_price,stock_qty) VALUES (?,?,?,?,?)");
        $upd = $db->prepare("UPDATE products SET name=?, unit=?, base_price=? WHERE code=?");
        $sel = $db->prepare("SELECT id FROM products WHERE code=?");
        $created = $updated = 0; $errors = [];
        $db->beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $code = (string)($r['code'] ?? '');
                $name = (string)($r['name'] ?? '');
                if ($code === '' || $name === '') { $errors[] = 'Row ' . ($i + 2) . ': code and name required.'; continue; }
                $unit = trim((string)($r['unit'] ?? 'pcs')) ?: 'pcs';
                $base = (float)($r['base_price'] ?? 0);
                $stock = (float)($r['stock_qty']  ?? 0);
                $sel->execute([$code]);
                if ($sel->fetch()) {
                    $upd->execute([$name, $unit, $base, $code]);
                    $updated++;
                } else {
                    $ins->execute([$code, $name, $unit, $base, $stock]);
                    $newId = (int)$db->lastInsertId();
                    if ($stock > 0) RawMaterialController::movement('FG', $newId, $stock, 0, 'ADJUST', null, 'CSV import opening stock');
                    $created++;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Import aborted: ' . $e->getMessage());
            Helpers::redirect('/products');
        }
        $msg = "Imported: {$created} new, {$updated} updated.";
        if ($errors) $msg .= ' ' . count($errors) . ' row(s) skipped: ' . implode(' | ', array_slice($errors, 0, 5));
        Helpers::flash($errors ? 'error' : 'success', $msg);
        Helpers::redirect('/products');
    }

    public static function index(): void
    {
        $rows = Database::pdo()->query(
            "SELECT id, code, name, unit, base_price, stock_qty FROM products ORDER BY name"
        )->fetchAll();
        Helpers::render('products/index', ['title' => 'Products', 'rows' => $rows]);
    }

    public static function create(): void
    {
        Helpers::render('products/form', ['title' => 'New Product', 'row' => null]);
    }

    public static function edit(array $p): void
    {
        $row = self::find((int)$p['id']);
        Helpers::render('products/form', ['title' => 'Edit Product', 'row' => $row]);
    }

    public static function store(): void
    {
        $d = self::collect();
        if ($e = self::validate($d)) { Helpers::flash('error', $e); Helpers::redirect('/products/new'); }
        try {
            $stmt = Database::pdo()->prepare(
                "INSERT INTO products (code,name,unit,base_price) VALUES (?,?,?,?)"
            );
            $stmt->execute([$d['code'], $d['name'], $d['unit'], $d['base_price']]);
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not save: ' . ($e->errorInfo[1] === 1062 ? 'code already exists' : 'database error'));
            Helpers::redirect('/products/new');
        }
        Helpers::flash('success', 'Product added.');
        Helpers::redirect('/products');
    }

    public static function update(array $p): void
    {
        $id = (int)$p['id'];
        self::find($id);
        $d = self::collect();
        if ($e = self::validate($d)) { Helpers::flash('error', $e); Helpers::redirect("/products/{$id}/edit"); }
        try {
            $stmt = Database::pdo()->prepare(
                "UPDATE products SET code=?,name=?,unit=?,base_price=? WHERE id=?"
            );
            $stmt->execute([$d['code'], $d['name'], $d['unit'], $d['base_price'], $id]);
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not update: ' . ($e->errorInfo[1] === 1062 ? 'code already exists' : 'database error'));
            Helpers::redirect("/products/{$id}/edit");
        }
        Helpers::flash('success', 'Product updated.');
        Helpers::redirect('/products');
    }

    public static function destroy(array $p): void
    {
        try {
            Database::pdo()->prepare("DELETE FROM products WHERE id=?")->execute([(int)$p['id']]);
            Helpers::flash('success', 'Product deleted.');
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Cannot delete: product is referenced by BOM, prices, or work orders.');
        }
        Helpers::redirect('/products');
    }

    private static function find(int $id): array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) Helpers::abort(404, 'Product not found');
        return $r;
    }

    private static function collect(): array
    {
        return [
            'code' => trim((string)Helpers::input('code')),
            'name' => trim((string)Helpers::input('name')),
            'unit' => trim((string)Helpers::input('unit', 'pcs')),
            'base_price' => (float)Helpers::input('base_price', 0),
        ];
    }

    private static function validate(array $d): ?string
    {
        if ($d['code'] === '' || strlen($d['code']) > 30) return 'Code is required (max 30).';
        if ($d['name'] === '' || strlen($d['name']) > 150) return 'Name is required (max 150).';
        if ($d['unit'] === '') return 'Unit is required.';
        if ($d['base_price'] < 0) return 'Base price cannot be negative.';
        return null;
    }
}
