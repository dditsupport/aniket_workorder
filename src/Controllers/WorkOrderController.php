<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Helpers;
use PDO;

final class WorkOrderController
{
    public static function index(): void
    {
        $db = Database::pdo();
        $status = (string)Helpers::input('status', '');
        $customerId = (int)Helpers::input('customer_id', 0);
        $where = []; $args = [];
        if ($status !== '' && in_array($status, ['draft','in_progress','completed','cancelled'], true)) {
            $where[] = 'wo.status = ?'; $args[] = $status;
        }
        if ($customerId > 0) { $where[] = 'wo.customer_id = ?'; $args[] = $customerId; }
        $sql = "SELECT wo.id, wo.wo_number, wo.status, wo.quantity, wo.unit_price, wo.total_amount, wo.created_at,
                       c.name AS customer_name, p.name AS product_name, p.code AS product_code
                FROM work_orders wo
                JOIN customers c ON c.id = wo.customer_id
                JOIN products p  ON p.id = wo.product_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY wo.id DESC LIMIT 500";
        $stmt = $db->prepare($sql); $stmt->execute($args);
        $rows = $stmt->fetchAll();
        $customers = $db->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();
        Helpers::render('work_orders/index', [
            'title' => 'Work Orders', 'rows' => $rows,
            'customers' => $customers, 'filter' => ['status' => $status, 'customer_id' => $customerId],
        ]);
    }

    public static function create(): void
    {
        $db = Database::pdo();
        $customers = $db->query("SELECT id, code, name FROM customers WHERE active=1 ORDER BY name")->fetchAll();
        $products  = $db->query("SELECT id, code, name, base_price FROM products ORDER BY name")->fetchAll();
        Helpers::render('work_orders/form', [
            'title' => 'New Work Order', 'customers' => $customers, 'products' => $products,
        ]);
    }

    public static function store(): void
    {
        $cid = (int)Helpers::input('customer_id', 0);
        $pid = (int)Helpers::input('product_id', 0);
        $qty = (float)Helpers::input('quantity', 0);
        $price = Helpers::input('unit_price', '');
        $notes = trim((string)Helpers::input('notes', '')) ?: null;
        if ($cid <= 0 || $pid <= 0 || $qty <= 0) {
            Helpers::flash('error', 'Customer, product and a positive quantity are required.');
            Helpers::redirect('/work-orders/new');
        }
        $db = Database::pdo();
        // resolve unit price
        if ($price === '' || $price === null) {
            $up = self::resolvePrice($cid, $pid);
        } else {
            $up = (float)$price;
            if ($up < 0) { Helpers::flash('error', 'Unit price cannot be negative.'); Helpers::redirect('/work-orders/new'); }
        }
        $total = round($up * $qty, 2);

        $db->beginTransaction();
        try {
            $year = (int)date('Y');
            $db->prepare("INSERT INTO wo_counters (year, last_seq) VALUES (?,1)
                          ON DUPLICATE KEY UPDATE last_seq = last_seq + 1")
               ->execute([$year]);
            $seq = (int)$db->query("SELECT last_seq FROM wo_counters WHERE year={$year}")->fetchColumn();
            $woNumber = sprintf('WO-%d-%04d', $year, $seq);

            $stmt = $db->prepare(
                "INSERT INTO work_orders (wo_number, customer_id, product_id, quantity, unit_price, total_amount, status, notes, created_by)
                 VALUES (?,?,?,?,?,?, 'draft', ?, ?)"
            );
            $stmt->execute([$woNumber, $cid, $pid, $qty, $up, $total, $notes, Auth::userId()]);
            $id = (int)$db->lastInsertId();
            $db->commit();
            Helpers::flash('success', "Work order {$woNumber} created.");
            Helpers::redirect('/work-orders/' . $id);
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Could not create work order.');
            Helpers::redirect('/work-orders/new');
        }
    }

    public static function show(array $p): void
    {
        $id = (int)$p['id'];
        $db = Database::pdo();
        $stmt = $db->prepare(
            "SELECT wo.*, c.name AS customer_name, c.code AS customer_code,
                    p.name AS product_name, p.code AS product_code, p.unit AS product_unit
             FROM work_orders wo
             JOIN customers c ON c.id = wo.customer_id
             JOIN products p  ON p.id = wo.product_id
             WHERE wo.id = ?"
        );
        $stmt->execute([$id]);
        $wo = $stmt->fetch();
        if (!$wo) Helpers::abort(404, 'Work order not found');

        // BOM preview (for draft) or actual allocations (for in_progress/completed)
        if (in_array($wo['status'], ['draft','cancelled'], true)) {
            $bs = $db->prepare(
                "SELECT rm.id AS rm_id, rm.code, rm.name, rm.unit, rm.stock_qty,
                        b.qty_per_unit, (b.qty_per_unit * ?) AS required
                 FROM bom b JOIN raw_materials rm ON rm.id = b.raw_material_id
                 WHERE b.product_id = ?"
            );
            $bs->execute([$wo['quantity'], $wo['product_id']]);
            $bomRows = $bs->fetchAll();
            $allocations = null;
        } else {
            $bs = $db->prepare(
                "SELECT a.qty AS allocated, rm.id AS rm_id, rm.code, rm.name, rm.unit
                 FROM wo_rm_allocations a JOIN raw_materials rm ON rm.id = a.raw_material_id
                 WHERE a.work_order_id = ?"
            );
            $bs->execute([$id]);
            $allocations = $bs->fetchAll();
            $bomRows = null;
        }

        $movs = $db->prepare(
            "SELECT sm.*, COALESCE(rm.code, p.code) AS item_code, COALESCE(rm.name, p.name) AS item_name
             FROM stock_movements sm
             LEFT JOIN raw_materials rm ON sm.item_type='RM' AND rm.id = sm.item_id
             LEFT JOIN products p       ON sm.item_type='FG' AND p.id  = sm.item_id
             WHERE sm.ref_type IN ('WO_RESERVE','WO_RELEASE','WO_CONSUME','WO_PRODUCE') AND sm.ref_id = ?
             ORDER BY sm.id"
        );
        $movs->execute([$id]);

        Helpers::render('work_orders/view', [
            'title' => 'WO ' . $wo['wo_number'],
            'wo' => $wo, 'bom' => $bomRows, 'allocations' => $allocations, 'movements' => $movs->fetchAll(),
        ]);
    }

    public static function changeStatus(array $p): void
    {
        $id = (int)$p['id'];
        $target = (string)Helpers::input('to', '');
        if (!in_array($target, ['in_progress','completed','cancelled'], true)) {
            Helpers::flash('error', 'Invalid target status.');
            Helpers::redirect("/work-orders/{$id}");
        }
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM work_orders WHERE id=? FOR UPDATE");
            $stmt->execute([$id]);
            $wo = $stmt->fetch();
            if (!$wo) throw new \RuntimeException('Work order not found');
            $from = $wo['status'];

            $allowed = [
                'draft'       => ['in_progress','cancelled'],
                'in_progress' => ['completed','cancelled'],
            ];
            if (!isset($allowed[$from]) || !in_array($target, $allowed[$from], true)) {
                throw new \RuntimeException("Cannot move from {$from} to {$target}.");
            }

            if ($from === 'draft' && $target === 'in_progress') {
                self::reserveRm($db, (int)$wo['id'], (int)$wo['product_id'], (float)$wo['quantity']);
                $db->prepare("UPDATE work_orders SET status='in_progress' WHERE id=?")->execute([$id]);
            } elseif ($from === 'in_progress' && $target === 'completed') {
                self::consumeAndProduce($db, (int)$wo['id'], (int)$wo['product_id'], (float)$wo['quantity']);
                $db->prepare("UPDATE work_orders SET status='completed', completed_at=NOW() WHERE id=?")->execute([$id]);
            } elseif ($from === 'in_progress' && $target === 'cancelled') {
                self::releaseRm($db, (int)$wo['id']);
                $db->prepare("UPDATE work_orders SET status='cancelled' WHERE id=?")->execute([$id]);
            } elseif ($from === 'draft' && $target === 'cancelled') {
                $db->prepare("UPDATE work_orders SET status='cancelled' WHERE id=?")->execute([$id]);
            }
            $db->commit();
            Helpers::flash('success', "Status changed to {$target}.");
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', $e->getMessage());
        }
        Helpers::redirect("/work-orders/{$id}");
    }

    public static function destroy(array $p): void
    {
        $id = (int)$p['id'];
        $db = Database::pdo();
        $stmt = $db->prepare("SELECT status FROM work_orders WHERE id=?");
        $stmt->execute([$id]);
        $st = $stmt->fetchColumn();
        if ($st !== 'draft' && $st !== 'cancelled') {
            Helpers::flash('error', 'Only draft or cancelled work orders can be deleted.');
            Helpers::redirect("/work-orders/{$id}");
        }
        try {
            $db->prepare("DELETE FROM work_orders WHERE id=?")->execute([$id]);
            Helpers::flash('success', 'Work order deleted.');
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not delete work order.');
        }
        Helpers::redirect('/work-orders');
    }

    public static function priceLookup(): void
    {
        header('Content-Type: application/json');
        $cid = (int)Helpers::input('customer_id', 0);
        $pid = (int)Helpers::input('product_id', 0);
        if ($cid <= 0 || $pid <= 0) { echo json_encode(['price' => null]); return; }
        echo json_encode(['price' => self::resolvePrice($cid, $pid)]);
    }

    // ---------- helpers ----------

    private static function resolvePrice(int $cid, int $pid): float
    {
        $db = Database::pdo();
        $stmt = $db->prepare("SELECT price FROM customer_prices WHERE customer_id=? AND product_id=?");
        $stmt->execute([$cid, $pid]);
        $p = $stmt->fetchColumn();
        if ($p !== false && $p !== null) return (float)$p;
        $stmt = $db->prepare("SELECT base_price FROM products WHERE id=?");
        $stmt->execute([$pid]);
        return (float)$stmt->fetchColumn();
    }

    private static function reserveRm(PDO $db, int $woId, int $productId, float $qty): void
    {
        $stmt = $db->prepare(
            "SELECT b.raw_material_id, b.qty_per_unit, rm.code, rm.name, rm.stock_qty
             FROM bom b JOIN raw_materials rm ON rm.id = b.raw_material_id
             WHERE b.product_id = ? FOR UPDATE"
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll();
        if (!$rows) throw new \RuntimeException('No BOM defined for this product. Define BOM before starting WO.');

        $shortages = [];
        foreach ($rows as $r) {
            $req = round((float)$r['qty_per_unit'] * $qty, 4);
            if ((float)$r['stock_qty'] < $req) {
                $shortages[] = "{$r['code']} (need {$req}, have {$r['stock_qty']})";
            }
        }
        if ($shortages) throw new \RuntimeException('Insufficient RM stock: ' . implode('; ', $shortages));

        $updRm    = $db->prepare("UPDATE raw_materials SET stock_qty = stock_qty - ? WHERE id = ?");
        $insAlloc = $db->prepare("INSERT INTO wo_rm_allocations (work_order_id, raw_material_id, qty) VALUES (?,?,?)");
        foreach ($rows as $r) {
            $req = round((float)$r['qty_per_unit'] * $qty, 4);
            $updRm->execute([$req, $r['raw_material_id']]);
            $insAlloc->execute([$woId, $r['raw_material_id'], $req]);
            RawMaterialController::movement('RM', (int)$r['raw_material_id'], 0, $req, 'WO_RESERVE', $woId, 'reserved for WO');
        }
    }

    private static function consumeAndProduce(PDO $db, int $woId, int $productId, float $qty): void
    {
        $stmt = $db->prepare("SELECT raw_material_id, qty FROM wo_rm_allocations WHERE work_order_id = ?");
        $stmt->execute([$woId]);
        $allocs = $stmt->fetchAll();
        foreach ($allocs as $a) {
            // Stock already reduced at reserve. Just record consumption movement.
            RawMaterialController::movement('RM', (int)$a['raw_material_id'], 0, (float)$a['qty'], 'WO_CONSUME', $woId, 'consumed by WO');
        }
        $db->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")
           ->execute([$qty, $productId]);
        RawMaterialController::movement('FG', $productId, $qty, 0, 'WO_PRODUCE', $woId, 'produced by WO');
    }

    private static function releaseRm(PDO $db, int $woId): void
    {
        $stmt = $db->prepare("SELECT raw_material_id, qty FROM wo_rm_allocations WHERE work_order_id = ?");
        $stmt->execute([$woId]);
        $allocs = $stmt->fetchAll();
        $upd = $db->prepare("UPDATE raw_materials SET stock_qty = stock_qty + ? WHERE id = ?");
        foreach ($allocs as $a) {
            $upd->execute([(float)$a['qty'], (int)$a['raw_material_id']]);
            RawMaterialController::movement('RM', (int)$a['raw_material_id'], (float)$a['qty'], 0, 'WO_RELEASE', $woId, 'released on cancel');
        }
        $db->prepare("DELETE FROM wo_rm_allocations WHERE work_order_id = ?")->execute([$woId]);
    }
}
