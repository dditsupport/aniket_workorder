<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;

final class ReportController
{
    public static function rmStock(): void
    {
        $rows = Database::pdo()->query(
            "SELECT id, code, name, unit, reorder_level, stock_qty,
                    CASE WHEN reorder_level > 0 AND stock_qty <= reorder_level THEN 1 ELSE 0 END AS low
             FROM raw_materials ORDER BY low DESC, name"
        )->fetchAll();
        Helpers::render('reports/rm_stock', ['title' => 'RM Stock Report', 'rows' => $rows]);
    }

    public static function fgStock(): void
    {
        $rows = Database::pdo()->query(
            "SELECT id, code, name, unit, base_price, stock_qty FROM products ORDER BY name"
        )->fetchAll();
        Helpers::render('reports/fg_stock', ['title' => 'Final Product Stock', 'rows' => $rows]);
    }

    public static function woStatus(): void
    {
        $db = Database::pdo();
        $cid = (int)Helpers::input('customer_id', 0);
        $st  = (string)Helpers::input('status', '');
        $from = trim((string)Helpers::input('from', ''));
        $to   = trim((string)Helpers::input('to', ''));
        $where = []; $args = [];
        if ($cid > 0) { $where[] = 'wo.customer_id = ?'; $args[] = $cid; }
        if (in_array($st, ['draft','in_progress','completed','cancelled'], true)) { $where[] = 'wo.status = ?'; $args[] = $st; }
        if ($from !== '') { $where[] = 'DATE(wo.created_at) >= ?'; $args[] = $from; }
        if ($to   !== '') { $where[] = 'DATE(wo.created_at) <= ?'; $args[] = $to; }
        $sql = "SELECT wo.id, wo.wo_number, wo.status, wo.quantity, wo.total_amount, wo.created_at,
                       c.name AS customer_name, p.name AS product_name
                FROM work_orders wo
                JOIN customers c ON c.id = wo.customer_id
                JOIN products p  ON p.id = wo.product_id"
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                . " ORDER BY wo.id DESC LIMIT 1000";
        $stmt = $db->prepare($sql); $stmt->execute($args);
        $rows = $stmt->fetchAll();
        $customers = $db->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();
        Helpers::render('reports/wo_status', [
            'title' => 'WO Status Report', 'rows' => $rows,
            'customers' => $customers,
            'filter' => ['customer_id' => $cid, 'status' => $st, 'from' => $from, 'to' => $to],
        ]);
    }

    public static function customerOutstanding(): void
    {
        $rows = Database::pdo()->query(
            "SELECT c.id, c.code, c.name,
                COALESCE((SELECT SUM(total_amount) FROM work_orders w
                           WHERE w.customer_id = c.id AND w.status <> 'cancelled'),0)
                + COALESCE((SELECT SUM(total_amount) FROM sales s
                             WHERE s.customer_id = c.id),0) AS billed,
                COALESCE((SELECT SUM(amount) FROM receipts r WHERE r.customer_id = c.id),0) AS received
             FROM customers c
             ORDER BY c.name"
        )->fetchAll();
        // compute outstanding in PHP for clarity
        foreach ($rows as &$r) { $r['outstanding'] = (float)$r['billed'] - (float)$r['received']; }
        Helpers::render('reports/customer_outstanding', ['title' => 'Customer Outstanding', 'rows' => $rows]);
    }
}
