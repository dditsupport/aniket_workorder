<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;

final class ProductController
{
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
