<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Helpers;

final class UserController
{
    private const ROLES = ['admin', 'operator', 'viewer'];

    public static function index(): void
    {
        $rows = Database::pdo()->query(
            "SELECT id, username, name, role, active, created_at FROM users ORDER BY username"
        )->fetchAll();
        Helpers::render('users/index', ['title' => 'Users', 'rows' => $rows]);
    }

    public static function create(): void
    {
        Helpers::render('users/form', ['title' => 'New User', 'row' => null, 'roles' => self::ROLES]);
    }

    public static function edit(array $p): void
    {
        $row = self::find((int)$p['id']);
        Helpers::render('users/form', ['title' => 'Edit User', 'row' => $row, 'roles' => self::ROLES]);
    }

    public static function store(): void
    {
        $d = self::collect();
        if ($e = self::validate($d, isNew: true)) { Helpers::flash('error', $e); Helpers::redirect('/users/new'); }
        try {
            $stmt = Database::pdo()->prepare(
                "INSERT INTO users (username, password, name, role, active) VALUES (?,?,?,?,?)"
            );
            $stmt->execute([
                $d['username'],
                $d['password'],
                $d['name'], $d['role'], $d['active'],
            ]);
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not save: ' . ($e->errorInfo[1] === 1062 ? 'username taken' : 'database error'));
            Helpers::redirect('/users/new');
        }
        Helpers::flash('success', 'User added.');
        Helpers::redirect('/users');
    }

    public static function update(array $p): void
    {
        $id = (int)$p['id'];
        $current = self::find($id);
        $d = self::collect();
        if ($e = self::validate($d, isNew: false)) { Helpers::flash('error', $e); Helpers::redirect("/users/{$id}/edit"); }
        try {
            if ($d['password'] !== '') {
                $stmt = Database::pdo()->prepare(
                    "UPDATE users SET username=?, name=?, role=?, active=?, password=? WHERE id=?"
                );
                $stmt->execute([$d['username'], $d['name'], $d['role'], $d['active'], $d['password'], $id]);
            } else {
                $stmt = Database::pdo()->prepare(
                    "UPDATE users SET username=?, name=?, role=?, active=? WHERE id=?"
                );
                $stmt->execute([$d['username'], $d['name'], $d['role'], $d['active'], $id]);
            }
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Could not update: ' . ($e->errorInfo[1] === 1062 ? 'username taken' : 'database error'));
            Helpers::redirect("/users/{$id}/edit");
        }
        Helpers::flash('success', 'User updated.');
        Helpers::redirect('/users');
    }

    public static function destroy(array $p): void
    {
        $id = (int)$p['id'];
        if ($id === Auth::userId()) {
            Helpers::flash('error', 'You cannot delete your own account.');
            Helpers::redirect('/users');
        }
        try {
            Database::pdo()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            Helpers::flash('success', 'User deleted.');
        } catch (\PDOException $e) {
            Helpers::flash('error', 'Cannot delete: user is referenced by historical records.');
        }
        Helpers::redirect('/users');
    }

    private static function find(int $id): array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if (!$r) Helpers::abort(404, 'User not found');
        return $r;
    }

    private static function collect(): array
    {
        return [
            'username' => trim((string)Helpers::input('username')),
            'name'     => trim((string)Helpers::input('name')),
            'role'     => trim((string)Helpers::input('role', 'viewer')),
            'active'   => Helpers::input('active') ? 1 : 0,
            'password' => (string)Helpers::input('password', ''),
        ];
    }

    private static function validate(array $d, bool $isNew): ?string
    {
        if ($d['username'] === '' || strlen($d['username']) > 50) return 'Username required (max 50).';
        if ($d['name'] === '' || strlen($d['name']) > 100) return 'Name required (max 100).';
        if (!in_array($d['role'], self::ROLES, true)) return 'Invalid role.';
        if ($isNew && strlen($d['password']) < 6) return 'Password is required (min 6 chars).';
        if (!$isNew && $d['password'] !== '' && strlen($d['password']) < 6) return 'New password must be at least 6 chars.';
        return null;
    }
}
