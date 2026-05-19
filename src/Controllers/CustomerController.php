<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;
use PDO;

final class CustomerController
{
    public static function index(): void
    {
        $rows = Database::pdo()->query(
            "SELECT id, code, name, gstin, phone, email, active FROM customers ORDER BY name"
        )->fetchAll();
        Helpers::render('customers/index', ['title' => 'Customers', 'rows' => $rows]);
    }

    public static function create(): void
    {
        Helpers::render('customers/form', ['title' => 'New Customer', 'row' => null]);
    }

    public static function edit(array $p): void
    {
        $row = self::find((int)$p['id']);
        Helpers::render('customers/form', ['title' => 'Edit Customer', 'row' => $row]);
    }

    public static function store(): void
    {
        $data = self::collect();
        $err = self::validate($data);
        if ($err) { Helpers::flash('error', $err); Helpers::redirect('/customers/new'); }
        $stmt = Database::pdo()->prepare(
            "INSERT INTO customers (code,name,gstin,phone,email,address,active) VALUES (?,?,?,?,?,?,?)"
        );
        try {
            $stmt->execute([$data['code'], $data['name'], $data['gstin'], $data['phone'], $data['email'], $data['address'], $data['active']]);
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
            "UPDATE customers SET code=?,name=?,gstin=?,phone=?,email=?,address=?,active=? WHERE id=?"
        );
        try {
            $stmt->execute([$data['code'], $data['name'], $data['gstin'], $data['phone'], $data['email'], $data['address'], $data['active'], $id]);
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not update: ' . ($e->errorInfo[1] === 1062 ? 'code already exists' : 'database error'));
            Helpers::redirect("/customers/{$id}/edit");
        }
        Helpers::flash('success', 'Customer updated.');
        Helpers::redirect('/customers');
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
        return [
            'code'   => trim((string)Helpers::input('code')),
            'name'   => trim((string)Helpers::input('name')),
            'gstin'  => trim((string)Helpers::input('gstin')) ?: null,
            'phone'  => trim((string)Helpers::input('phone')) ?: null,
            'email'  => trim((string)Helpers::input('email')) ?: null,
            'address'=> trim((string)Helpers::input('address')) ?: null,
            'active' => Helpers::input('active') ? 1 : 0,
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
