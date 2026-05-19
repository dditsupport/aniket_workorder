<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Helpers;

final class ReceiptController
{
    public static function index(): void
    {
        $db = Database::pdo();
        $cid = (int)Helpers::input('customer_id', 0);
        $from = trim((string)Helpers::input('from', ''));
        $to   = trim((string)Helpers::input('to', ''));
        $where = []; $args = [];
        if ($cid > 0) { $where[] = 'r.customer_id = ?'; $args[] = $cid; }
        if ($from !== '') { $where[] = 'r.receipt_date >= ?'; $args[] = $from; }
        if ($to   !== '') { $where[] = 'r.receipt_date <= ?'; $args[] = $to; }
        $sql = "SELECT r.*, c.name AS customer_name, c.code AS customer_code
                FROM receipts r JOIN customers c ON c.id = r.customer_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY r.receipt_date DESC, r.id DESC LIMIT 500";
        $stmt = $db->prepare($sql); $stmt->execute($args);
        $rows = $stmt->fetchAll();
        $customers = $db->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();
        Helpers::render('receipts/index', [
            'title' => 'Receipts',
            'rows' => $rows,
            'customers' => $customers,
            'filter' => ['customer_id' => $cid, 'from' => $from, 'to' => $to],
        ]);
    }

    public static function create(): void
    {
        $customers = Database::pdo()->query("SELECT id, code, name FROM customers WHERE active=1 ORDER BY name")->fetchAll();
        Helpers::render('receipts/form', ['title' => 'New Receipt', 'customers' => $customers]);
    }

    public static function store(): void
    {
        $cid    = (int)Helpers::input('customer_id', 0);
        $date   = trim((string)Helpers::input('receipt_date', ''));
        $amount = (float)Helpers::input('amount', 0);
        $mode   = trim((string)Helpers::input('mode', 'cash'));
        $ref    = trim((string)Helpers::input('reference', '')) ?: null;
        $note   = trim((string)Helpers::input('note', '')) ?: null;
        if ($cid <= 0 || $amount <= 0 || $date === '') {
            Helpers::flash('error', 'Customer, date and a positive amount are required.');
            Helpers::redirect('/receipts/new');
        }
        if (!in_array($mode, ['cash','bank','upi','cheque','other'], true)) $mode = 'cash';
        $stmt = Database::pdo()->prepare(
            "INSERT INTO receipts (customer_id, receipt_date, amount, mode, reference, note, created_by) VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->execute([$cid, $date, $amount, $mode, $ref, $note, Auth::userId()]);
        Helpers::flash('success', 'Receipt recorded.');
        Helpers::redirect('/receipts');
    }

    public static function destroy(array $p): void
    {
        Database::pdo()->prepare("DELETE FROM receipts WHERE id=?")->execute([(int)$p['id']]);
        Helpers::flash('success', 'Receipt deleted.');
        Helpers::redirect('/receipts');
    }
}
