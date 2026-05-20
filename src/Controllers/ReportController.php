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

    public static function rmLedger(): void
    {
        $db = Database::pdo();
        $rmId = (int)Helpers::input('rm_id', 0);
        $from = trim((string)Helpers::input('from', ''));
        $to   = trim((string)Helpers::input('to', ''));
        $rms = $db->query("SELECT id, code, name, unit, stock_qty FROM raw_materials ORDER BY name")->fetchAll();

        $rm = null; $opening = 0.0; $rows = []; $closing = 0.0;
        if ($rmId > 0) {
            foreach ($rms as $r) { if ((int)$r['id'] === $rmId) { $rm = $r; break; } }
            if ($rm) {
                // Opening balance = net of all movements strictly before $from (if given).
                if ($from !== '') {
                    $o = $db->prepare(
                        "SELECT COALESCE(SUM(qty_in - qty_out),0)
                         FROM stock_movements
                         WHERE item_type='RM' AND item_id=? AND DATE(created_at) < ?"
                    );
                    $o->execute([$rmId, $from]);
                    $opening = (float)$o->fetchColumn();
                }
                $where = "item_type='RM' AND item_id=?"; $args = [$rmId];
                if ($from !== '') { $where .= " AND DATE(created_at) >= ?"; $args[] = $from; }
                if ($to   !== '') { $where .= " AND DATE(created_at) <= ?"; $args[] = $to; }
                $stmt = $db->prepare(
                    "SELECT sm.created_at, sm.qty_in, sm.qty_out, sm.ref_type, sm.ref_id, sm.note,
                            u.name AS user_name
                     FROM stock_movements sm
                     LEFT JOIN users u ON u.id = sm.created_by
                     WHERE {$where}
                     ORDER BY sm.created_at, sm.id"
                );
                $stmt->execute($args);
                $balance = $opening;
                foreach ($stmt->fetchAll() as $m) {
                    $balance += (float)$m['qty_in'] - (float)$m['qty_out'];
                    $m['balance'] = $balance;
                    $rows[] = $m;
                }
                $closing = $balance;
            }
        }

        Helpers::render('reports/rm_ledger', [
            'title' => 'RM Stock Ledger',
            'rms' => $rms,
            'rm' => $rm,
            'rows' => $rows,
            'opening' => $opening,
            'closing' => $closing,
            'filter' => ['rm_id' => $rmId, 'from' => $from, 'to' => $to],
        ]);
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
