<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Csv;
use App\Database;
use App\Helpers;
use PDO;

final class CustomerController
{
    public static function exportCsv(): void
    {
        $rows = Database::pdo()->query(
            "SELECT c.code, c.name, c.gstin, c.phone, c.email, c.address, c.active,
                    COALESCE(pl.name, '') AS price_list
             FROM customers c
             LEFT JOIN price_lists pl ON pl.id = c.price_list_id
             ORDER BY c.name"
        )->fetchAll();
        Csv::download('customers.csv',
            ['code','name','gstin','phone','email','address','active','price_list'], $rows);
    }

    public static function importCsv(): void
    {
        try {
            $rows = Csv::parseUpload('file', ['code','name']);
        } catch (\Throwable $e) {
            Helpers::flash('error', 'Import failed: ' . $e->getMessage());
            Helpers::redirect('/customers');
        }
        $db = Database::pdo();
        $plMap = [];
        foreach ($db->query("SELECT id, name FROM price_lists")->fetchAll() as $pl) {
            $plMap[strtolower((string)$pl['name'])] = (int)$pl['id'];
        }
        $ins = $db->prepare("INSERT INTO customers (code,name,gstin,phone,email,address,active,price_list_id) VALUES (?,?,?,?,?,?,?,?)");
        $upd = $db->prepare("UPDATE customers SET name=?, gstin=?, phone=?, email=?, address=?, active=?, price_list_id=? WHERE code=?");
        $sel = $db->prepare("SELECT id FROM customers WHERE code=?");
        $created = $updated = 0; $errors = [];
        $db->beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $code = (string)($r['code'] ?? '');
                $name = (string)($r['name'] ?? '');
                if ($code === '' || $name === '') { $errors[] = 'Row ' . ($i + 2) . ': code and name required.'; continue; }
                $plName = strtolower(trim((string)($r['price_list'] ?? '')));
                $plId = $plName === '' ? null : ($plMap[$plName] ?? null);
                if ($plName !== '' && $plId === null) {
                    $errors[] = 'Row ' . ($i + 2) . ": price_list '{$r['price_list']}' not found.";
                    continue;
                }
                $args = [
                    $name,
                    ($r['gstin']   ?? '') ?: null,
                    ($r['phone']   ?? '') ?: null,
                    ($r['email']   ?? '') ?: null,
                    ($r['address'] ?? '') ?: null,
                    Csv::bool($r['active'] ?? '1'),
                    $plId,
                ];
                $sel->execute([$code]);
                if ($sel->fetch()) {
                    $upd->execute([...$args, $code]);
                    $updated++;
                } else {
                    $ins->execute([$code, ...$args]);
                    $created++;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Helpers::flash('error', 'Import aborted: ' . $e->getMessage());
            Helpers::redirect('/customers');
        }
        $msg = "Imported: {$created} new, {$updated} updated.";
        if ($errors) $msg .= ' ' . count($errors) . ' skipped: ' . implode(' | ', array_slice($errors, 0, 5));
        Helpers::flash($errors ? 'error' : 'success', $msg);
        Helpers::redirect('/customers');
    }

    public static function index(): void
    {
        $rows = Database::pdo()->query(
            "SELECT c.id, c.code, c.name, c.gstin, c.phone, c.email, c.active,
                    c.price_list_id, pl.name AS price_list_name
             FROM customers c
             LEFT JOIN price_lists pl ON pl.id = c.price_list_id
             ORDER BY c.name"
        )->fetchAll();
        Helpers::render('customers/index', ['title' => 'Customers', 'rows' => $rows]);
    }

    public static function create(): void
    {
        Helpers::render('customers/form', [
            'title' => 'New Customer',
            'row' => null,
            'price_lists' => self::priceLists(),
        ]);
    }

    public static function edit(array $p): void
    {
        $row = self::find((int)$p['id']);
        Helpers::render('customers/form', [
            'title' => 'Edit Customer',
            'row' => $row,
            'price_lists' => self::priceLists(),
        ]);
    }

    public static function store(): void
    {
        $data = self::collect();
        $err = self::validate($data);
        if ($err) { Helpers::flash('error', $err); Helpers::redirect('/customers/new'); }
        $stmt = Database::pdo()->prepare(
            "INSERT INTO customers (code,name,gstin,phone,email,address,active,price_list_id) VALUES (?,?,?,?,?,?,?,?)"
        );
        try {
            $stmt->execute([$data['code'], $data['name'], $data['gstin'], $data['phone'], $data['email'], $data['address'], $data['active'], $data['price_list_id']]);
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not save: ' . ($e->errorInfo[1] === 1062 ? 'code already exists' : 'database error'));
            Helpers::redirect('/customers/new');
        }
        Helpers::flash('success', 'Customer added.');
        Helpers::redirect('/customers');
    }

    public static function update(array $p): void
    {
        $id = (int)$p['id'];
        self::find($id);
        $data = self::collect();
        $err = self::validate($data);
        if ($err) { Helpers::flash('error', $err); Helpers::redirect("/customers/{$id}/edit"); }
        $stmt = Database::pdo()->prepare(
            "UPDATE customers SET code=?,name=?,gstin=?,phone=?,email=?,address=?,active=?,price_list_id=? WHERE id=?"
        );
        try {
            $stmt->execute([$data['code'], $data['name'], $data['gstin'], $data['phone'], $data['email'], $data['address'], $data['active'], $data['price_list_id'], $id]);
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not update: ' . ($e->errorInfo[1] === 1062 ? 'code already exists' : 'database error'));
            Helpers::redirect("/customers/{$id}/edit");
        }
        Helpers::flash('success', 'Customer updated.');
        Helpers::redirect('/customers');
    }

    private static function priceLists(): array
    {
        return Database::pdo()->query("SELECT id, name FROM price_lists WHERE active=1 ORDER BY name")->fetchAll();
    }

    public static function destroy(array $p): void
    {
        try {
            Database::pdo()->prepare("DELETE FROM customers WHERE id=?")->execute([(int)$p['id']]);
            Helpers::flash('success', 'Customer deleted.');
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Cannot delete: customer is referenced by other records.');
        }
        Helpers::redirect('/customers');
    }

    private static function find(int $id): array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM customers WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Helpers::abort(404, 'Customer not found');
        return $row;
    }

    private static function collect(): array
    {
        $pl = Helpers::input('price_list_id');
        return [
            'code'          => trim((string)Helpers::input('code')),
            'name'          => trim((string)Helpers::input('name')),
            'gstin'         => trim((string)Helpers::input('gstin')) ?: null,
            'phone'         => trim((string)Helpers::input('phone')) ?: null,
            'email'         => trim((string)Helpers::input('email')) ?: null,
            'address'       => trim((string)Helpers::input('address')) ?: null,
            'active'        => Helpers::input('active') ? 1 : 0,
            'price_list_id' => ($pl === null || $pl === '') ? null : (int)$pl,
        ];
    }

    private static function validate(array $d): ?string
    {
        if ($d['code'] === '' || strlen($d['code']) > 30) return 'Code is required (max 30 chars).';
        if ($d['name'] === '' || strlen($d['name']) > 150) return 'Name is required (max 150 chars).';
        if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return 'Invalid email.';
        return null;
    }
}
