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
            "SELECT cp.id, cp.price, c.name AS customer_name, c.code AS customer_code,
                    p.name AS product_name, p.code AS product_code, p.base_price
             FROM customer_prices cp
             JOIN customers c ON c.id = cp.customer_id
             JOIN products p  ON p.id = cp.product_id
             ORDER BY c.name, p.name"
        )->fetchAll();
        $customers = $db->query("SELECT id, code, name FROM customers WHERE active=1 ORDER BY name")->fetchAll();
        $products  = $db->query("SELECT id, code, name, base_price FROM products ORDER BY name")->fetchAll();
        Helpers::render('price_list/index', [
            'title' => 'Customer Price List',
            'rows' => $rows,
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    public static function upsert(): void
    {
        $cid = (int)Helpers::input('customer_id', 0);
        $pid = (int)Helpers::input('product_id', 0);
        $price = (float)Helpers::input('price', 0);
        if ($cid <= 0 || $pid <= 0 || $price < 0) {
            Helpers::flash('error', 'Pick customer & product and enter a non-negative price.');
            Helpers::redirect('/price-list');
        }
        $stmt = Database::pdo()->prepare(
            "INSERT INTO customer_prices (customer_id, product_id, price) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE price = VALUES(price)"
        );
        $stmt->execute([$cid, $pid, $price]);
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
