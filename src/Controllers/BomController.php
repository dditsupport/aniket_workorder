<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;

final class BomController
{
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
