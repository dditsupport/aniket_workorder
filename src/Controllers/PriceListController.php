<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;

final class PriceListController
{
    public static function index(): void
    {
        $db = Database::pdo();
        $rows = $db->query(
            "SELECT cp.id, cp.item_kind, cp.item_id, cp.price,
                    c.name AS customer_name, c.code AS customer_code,
                    CASE WHEN cp.item_kind='FG' THEN p.code ELSE rm.code END AS item_code,
                    CASE WHEN cp.item_kind='FG' THEN p.name ELSE rm.name END AS item_name,
                    CASE WHEN cp.item_kind='FG' THEN p.base_price ELSE rm.sale_price END AS base_price
             FROM customer_prices cp
             JOIN customers c ON c.id = cp.customer_id
             LEFT JOIN products p       ON cp.item_kind = 'FG' AND p.id  = cp.item_id
             LEFT JOIN raw_materials rm ON cp.item_kind = 'RM' AND rm.id = cp.item_id
             ORDER BY c.name, cp.item_kind, item_name"
        )->fetchAll();
        $customers = $db->query("SELECT id, code, name FROM customers WHERE active=1 ORDER BY name")->fetchAll();
        $products  = $db->query("SELECT id, code, name, base_price FROM products ORDER BY name")->fetchAll();
        $rms       = $db->query("SELECT id, code, name, sale_price FROM raw_materials ORDER BY name")->fetchAll();
        Helpers::render('price_list/index', [
            'title' => 'Customer Price List',
            'rows' => $rows,
            'customers' => $customers,
            'products' => $products,
            'rms' => $rms,
        ]);
    }

    public static function upsert(): void
    {
        $cid  = (int)Helpers::input('customer_id', 0);
        $kind = (string)Helpers::input('item_kind', '');
        $iid  = (int)Helpers::input('item_id', 0);
        $price = (float)Helpers::input('price', 0);
        if ($cid <= 0 || !in_array($kind, ['RM','FG'], true) || $iid <= 0 || $price < 0) {
            Helpers::flash('error', 'Pick customer, item type & item; price must be non-negative.');
            Helpers::redirect('/price-list');
        }
        $stmt = Database::pdo()->prepare(
            "INSERT INTO customer_prices (customer_id, item_kind, item_id, price) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE price = VALUES(price)"
        );
        $stmt->execute([$cid, $kind, $iid, $price]);
        Helpers::flash('success', 'Price saved.');
        Helpers::redirect('/price-list');
    }

    public static function destroy(array $p): void
    {
        Database::pdo()->prepare("DELETE FROM customer_prices WHERE id=?")->execute([(int)$p['id']]);
        Helpers::flash('success', 'Price entry removed.');
        Helpers::redirect('/price-list');
    }
}
