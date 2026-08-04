<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Helpers;
use PDO;

final class SaleController
{
    public static function index(): void
    {
        $db = Database::pdo();
        $cid  = (int)Helpers::input('customer_id', 0);
        $from = trim((string)Helpers::input('from', ''));
        $to   = trim((string)Helpers::input('to', ''));
        $where = []; $args = [];
        if ($cid > 0) { $where[] = 's.customer_id = ?'; $args[] = $cid; }
        if ($from !== '') { $where[] = 's.sale_date >= ?'; $args[] = $from; }
        if ($to   !== '') { $where[] = 's.sale_date <= ?'; $args[] = $to; }
        $sql = "SELECT s.*, c.name AS customer_name, c.code AS customer_code,
                       (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS line_count,
                       COALESCE((SELECT SUM(r.amount) FROM receipts r WHERE r.sale_id = s.id), 0) AS paid_amount
                FROM sales s JOIN customers c ON c.id = s.customer_id"
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                . " ORDER BY s.id DESC LIMIT 500";
        $stmt = $db->prepare($sql); $stmt->execute($args);
        $rows = $stmt->fetchAll();
        $customers = $db->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();
        Helpers::render('sales/index', [
            'title' => 'Sales',
            'rows' => $rows,
            'customers' => $customers,
            'filter' => ['customer_id' => $cid, 'from' => $from, 'to' => $to],
        ]);
    }

    public static function create(): void
    {
        $db = Database::pdo();
        $customers = $db->query("SELECT id, code, name FROM customers WHERE active=1 ORDER BY name")->fetchAll();
        $products  = $db->query("SELECT id, code, name, unit, base_price, stock_qty FROM products ORDER BY name")->fetchAll();
        $rms       = $db->query("SELECT id, code, name, unit, sale_price, stock_qty FROM raw_materials ORDER BY name")->fetchAll();
        // Non-cancelled work orders, so a sale can be pre-filled from a WO's produced item.
        $workorders = $db->query(
            "SELECT wo.id, wo.wo_number, wo.customer_id, wo.product_id, wo.quantity, wo.status,
                    p.code AS product_code, p.name AS product_name
             FROM work_orders wo JOIN products p ON p.id = wo.product_id
             WHERE wo.status <> 'cancelled'
             ORDER BY wo.id DESC"
        )->fetchAll();
        Helpers::render('sales/form', [
            'title' => 'New Sale',
            'customers' => $customers,
            'products' => $products,
            'rms' => $rms,
            'workorders' => $workorders,
        ]);
    }

    public static function store(): void
    {
        $cid  = (int)Helpers::input('customer_id', 0);
        $date = trim((string)Helpers::input('sale_date', ''));
        $notes = trim((string)Helpers::input('notes', '')) ?: null;
        $kinds  = $_POST['line_kind']     ?? [];
        $itemIds = $_POST['line_item_id'] ?? [];
        $qtys    = $_POST['line_qty']     ?? [];
        $prices  = $_POST['line_price']   ?? [];
        $discs   = $_POST['line_qtydisc'] ?? [];

        if ($cid <= 0 || $date === '') {
            Helpers::flash('error', 'Customer and date are required.');
            Helpers::redirect('/sales/new');
        }
        if (!is_array($kinds) || count($kinds) === 0) {
            Helpers::flash('error', 'Add at least one line.');
            Helpers::redirect('/sales/new');
        }

        // Build clean line set
        $lines = [];
        $n = count($kinds);
        for ($i = 0; $i < $n; $i++) {
            $k = (string)($kinds[$i] ?? '');
            $iid = (int)($itemIds[$i] ?? 0);
            $q = (float)($qtys[$i] ?? 0);
            $up = isset($prices[$i]) && $prices[$i] !== '' ? (float)$prices[$i] : null;
            $disc = (string)($discs[$i] ?? '0') === '1';
            if (!in_array($k, ['RM','FG'], true) || $iid <= 0 || $q <= 0) continue;
            $lines[] = ['kind' => $k, 'item_id' => $iid, 'qty' => $q, 'unit_price' => $up, 'qty_disc' => $disc];
        }
        if (!$lines) {
            Helpers::flash('error', 'No valid lines provided.');
            Helpers::redirect('/sales/new');
        }

        $db = Database::pdo();
        $db->beginTransaction();
        try {
            // Lock + verify each item exists, has enough stock; resolve price if needed; compute totals.
            $total = 0.0;
            $resolved = [];
            foreach ($lines as $ln) {
                if ($ln['kind'] === 'FG') {
                    $stmt = $db->prepare("SELECT id, code, name, base_price, stock_qty FROM products WHERE id=? FOR UPDATE");
                } else {
                    $stmt = $db->prepare("SELECT id, code, name, sale_price AS base_price, stock_qty FROM raw_materials WHERE id=? FOR UPDATE");
                }
                $stmt->execute([$ln['item_id']]);
                $item = $stmt->fetch();
                if (!$item) throw new \RuntimeException("Item not found ({$ln['kind']} #{$ln['item_id']}).");
                if ((float)$item['stock_qty'] < $ln['qty']) {
                    throw new \RuntimeException("Insufficient stock for {$item['code']} — need {$ln['qty']}, have {$item['stock_qty']}.");
                }
                $up = $ln['unit_price'];
                if ($ln['qty_disc']) {
                    // Discount opted in: server authoritatively applies the qty tier.
                    $up = self::resolvePrice($db, $cid, $ln['kind'], $ln['item_id'], (float)$item['base_price'], $ln['qty'], true);
                } elseif ($up === null) {
                    $up = self::resolvePrice($db, $cid, $ln['kind'], $ln['item_id'], (float)$item['base_price']);
                }
                $lt = round($up * $ln['qty'], 2);
                $total += $lt;
                $resolved[] = $ln + ['unit_price_final' => $up, 'line_total' => $lt];
            }
            $total = round($total, 2);

            // Auto sale number
            $year = (int)date('Y');
            $db->prepare("INSERT INTO sale_counters (year, last_seq) VALUES (?,1)
                          ON DUPLICATE KEY UPDATE last_seq = last_seq + 1")
               ->execute([$year]);
            $seq = (int)$db->query("SELECT last_seq FROM sale_counters WHERE year={$year}")->fetchColumn();
            $saleNumber = sprintf('SALE-%d-%04d', $year, $seq);

            $db->prepare("INSERT INTO sales (sale_number, customer_id, sale_date, total_amount, notes, created_by)
                          VALUES (?,?,?,?,?,?)")
               ->execute([$saleNumber, $cid, $date, $total, $notes, Auth::userId()]);
            $saleId = (int)$db->lastInsertId();

            $insLine = $db->prepare(
                "INSERT INTO sale_items (sale_id, item_kind, item_id, qty, unit_price, line_total) VALUES (?,?,?,?,?,?)"
            );
            $updFg = $db->prepare("UPDATE products      SET stock_qty = stock_qty - ? WHERE id = ?");
            $updRm = $db->prepare("UPDATE raw_materials SET stock_qty = stock_qty - ? WHERE id = ?");
            foreach ($resolved as $ln) {
                $insLine->execute([$saleId, $ln['kind'], $ln['item_id'], $ln['qty'], $ln['unit_price_final'], $ln['line_total']]);
                if ($ln['kind'] === 'FG') {
                    $updFg->execute([$ln['qty'], $ln['item_id']]);
                } else {
                    $updRm->execute([$ln['qty'], $ln['item_id']]);
                }
                RawMaterialController::movement(
                    $ln['kind'], (int)$ln['item_id'], 0, (float)$ln['qty'], 'SALE_OUT', $saleId, 'sold'
                );
            }

            $db->commit();
            Helpers::flash('success', "Sale {$saleNumber} recorded.");
            Helpers::redirect('/sales/' . $saleId);
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', $e->getMessage());
            Helpers::redirect('/sales/new');
        }
    }

    public static function show(array $p): void
    {
        $id = (int)$p['id'];
        $db = Database::pdo();
        $stmt = $db->prepare(
            "SELECT s.*, c.name AS customer_name, c.code AS customer_code,
                    COALESCE((SELECT SUM(r.amount) FROM receipts r WHERE r.sale_id = s.id), 0) AS paid_amount
             FROM sales s JOIN customers c ON c.id = s.customer_id
             WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        $sale = $stmt->fetch();
        if (!$sale) Helpers::abort(404, 'Sale not found');

        $lines = $db->prepare(
            "SELECT si.*,
                    CASE WHEN si.item_kind='FG' THEN p.code ELSE rm.code END AS item_code,
                    CASE WHEN si.item_kind='FG' THEN p.name ELSE rm.name END AS item_name,
                    CASE WHEN si.item_kind='FG' THEN p.unit ELSE rm.unit END AS unit
             FROM sale_items si
             LEFT JOIN products p       ON si.item_kind='FG' AND p.id  = si.item_id
             LEFT JOIN raw_materials rm ON si.item_kind='RM' AND rm.id = si.item_id
             WHERE si.sale_id = ? ORDER BY si.id"
        );
        $lines->execute([$id]);

        $receipts = $db->prepare(
            "SELECT id, receipt_date, amount, mode, reference, note
             FROM receipts WHERE sale_id = ? ORDER BY receipt_date, id"
        );
        $receipts->execute([$id]);

        Helpers::render('sales/view', [
            'title' => 'Sale ' . $sale['sale_number'],
            'sale' => $sale,
            'lines' => $lines->fetchAll(),
            'receipts' => $receipts->fetchAll(),
        ]);
    }

    /** Printable bill: three identical copies laid out on one A4 sheet. */
    public static function printBill(array $p): void
    {
        $id = (int)$p['id'];
        $db = Database::pdo();
        $stmt = $db->prepare(
            "SELECT s.*, c.name AS customer_name, c.code AS customer_code,
                    c.address AS customer_address, c.gstin AS customer_gstin,
                    c.phone AS customer_phone,
                    COALESCE((SELECT SUM(r.amount) FROM receipts r WHERE r.sale_id = s.id), 0) AS paid_amount
             FROM sales s JOIN customers c ON c.id = s.customer_id
             WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        $sale = $stmt->fetch();
        if (!$sale) Helpers::abort(404, 'Sale not found');

        $lines = $db->prepare(
            "SELECT si.*,
                    CASE WHEN si.item_kind='FG' THEN p.code ELSE rm.code END AS item_code,
                    CASE WHEN si.item_kind='FG' THEN p.name ELSE rm.name END AS item_name,
                    CASE WHEN si.item_kind='FG' THEN p.unit ELSE rm.unit END AS unit
             FROM sale_items si
             LEFT JOIN products p       ON si.item_kind='FG' AND p.id  = si.item_id
             LEFT JOIN raw_materials rm ON si.item_kind='RM' AND rm.id = si.item_id
             WHERE si.sale_id = ? ORDER BY si.id"
        );
        $lines->execute([$id]);

        Helpers::renderPlain('sales/print', [
            'sale'  => $sale,
            'lines' => $lines->fetchAll(),
            // ?autoprint=0 opens the sheet without firing the browser print dialog.
            'autoprint' => (string)Helpers::input('autoprint', '1') !== '0',
        ]);
    }

    public static function destroy(array $p): void
    {
        $id = (int)$p['id'];
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM sales WHERE id=? FOR UPDATE");
            $stmt->execute([$id]);
            $sale = $stmt->fetch();
            if (!$sale) throw new \RuntimeException('Sale not found');

            // restore stock
            $ls = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $ls->execute([$id]);
            $updFg = $db->prepare("UPDATE products      SET stock_qty = stock_qty + ? WHERE id = ?");
            $updRm = $db->prepare("UPDATE raw_materials SET stock_qty = stock_qty + ? WHERE id = ?");
            foreach ($ls->fetchAll() as $ln) {
                if ($ln['item_kind'] === 'FG') $updFg->execute([(float)$ln['qty'], (int)$ln['item_id']]);
                else                            $updRm->execute([(float)$ln['qty'], (int)$ln['item_id']]);
                RawMaterialController::movement(
                    $ln['item_kind'], (int)$ln['item_id'], (float)$ln['qty'], 0, 'ADJUST', $id, 'sale ' . $sale['sale_number'] . ' deleted'
                );
            }
            $db->prepare("DELETE FROM sales WHERE id=?")->execute([$id]);
            $db->commit();
            Helpers::flash('success', 'Sale deleted and stock restored.');
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', $e->getMessage());
        }
        Helpers::redirect('/sales');
    }

    public static function priceLookup(): void
    {
        header('Content-Type: application/json');
        $cid  = (int)Helpers::input('customer_id', 0);
        $kind = (string)Helpers::input('item_kind', '');
        $iid  = (int)Helpers::input('item_id', 0);
        $qty  = (float)Helpers::input('qty', 0);
        $tier = (string)Helpers::input('tier', '0') === '1';
        if ($cid <= 0 || !in_array($kind, ['RM','FG'], true) || $iid <= 0) {
            echo json_encode(['price' => null]); return;
        }
        $base = 0.0;
        $db = Database::pdo();
        if ($kind === 'FG') {
            $stmt = $db->prepare("SELECT base_price FROM products WHERE id=?");
        } else {
            $stmt = $db->prepare("SELECT sale_price FROM raw_materials WHERE id=?");
        }
        $stmt->execute([$iid]);
        $base = (float)$stmt->fetchColumn();
        echo json_encode(['price' => self::resolvePrice($db, $cid, $kind, $iid, $base, $qty, $tier)]);
    }

    private static function resolvePrice(PDO $db, int $cid, string $kind, int $iid, float $fallback, float $qty = 0, bool $useTier = false): float
    {
        // Customer's assigned price list id.
        $stmt = $db->prepare("SELECT price_list_id FROM customers WHERE id=?");
        $stmt->execute([$cid]);
        $plId = $stmt->fetchColumn();
        if ($plId === false || $plId === null) return $fallback;
        $plId = (int)$plId;

        // Quantity-break tier first (only when opted in and a qty is given).
        if ($useTier && $qty > 0) {
            $t = $db->prepare(
                "SELECT price FROM price_list_tiers
                 WHERE price_list_id=? AND item_kind=? AND item_id=?
                   AND min_qty <= ? AND (max_qty IS NULL OR max_qty >= ?)
                 ORDER BY min_qty DESC LIMIT 1"
            );
            $t->execute([$plId, $kind, $iid, $qty, $qty]);
            $tp = $t->fetchColumn();
            if ($tp !== false && $tp !== null) return (float)$tp;
        }

        // Flat price-list price.
        $stmt = $db->prepare(
            "SELECT price FROM price_list_items WHERE price_list_id=? AND item_kind=? AND item_id=?"
        );
        $stmt->execute([$plId, $kind, $iid]);
        $p = $stmt->fetchColumn();
        return ($p !== false && $p !== null) ? (float)$p : $fallback;
    }
}
