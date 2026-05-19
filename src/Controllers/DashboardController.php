<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;

final class DashboardController
{
    public static function index(): void
    {
        $db = Database::pdo();
        $stats = [
            'customers'    => (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
            'products'     => (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
            'raw_materials'=> (int)$db->query("SELECT COUNT(*) FROM raw_materials")->fetchColumn(),
            'open_wo'      => (int)$db->query("SELECT COUNT(*) FROM work_orders WHERE status IN ('draft','in_progress')")->fetchColumn(),
        ];
        $lowStock = $db->query(
            "SELECT id, code, name, unit, stock_qty, reorder_level
             FROM raw_materials
             WHERE reorder_level > 0 AND stock_qty <= reorder_level
             ORDER BY name"
        )->fetchAll();
        $recentWo = $db->query(
            "SELECT wo.id, wo.wo_number, wo.status, wo.total_amount, wo.created_at,
                    c.name AS customer_name, p.name AS product_name
             FROM work_orders wo
             JOIN customers c ON c.id = wo.customer_id
             JOIN products p ON p.id = wo.product_id
             ORDER BY wo.id DESC LIMIT 10"
        )->fetchAll();
        $outstanding = (float)$db->query(
            "SELECT COALESCE((SELECT SUM(total_amount) FROM work_orders WHERE status <> 'cancelled'),0)
                  - COALESCE((SELECT SUM(amount) FROM receipts),0)"
        )->fetchColumn();
        Helpers::render('dashboard/index', [
            'title'       => 'Dashboard',
            'stats'       => $stats,
            'lowStock'    => $lowStock,
            'recentWo'    => $recentWo,
            'outstanding' => $outstanding,
        ]);
    }
}
