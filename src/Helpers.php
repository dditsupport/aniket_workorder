<?php
declare(strict_types=1);

namespace App;

final class Helpers
{
    public static function esc(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    public static function money(float|string|null $n): string
    {
        $n = (float)($n ?? 0);
        return Config::get('currency', '₹') . number_format($n, 2);
    }

    public static function qty(float|string|null $n): string
    {
        $n = (float)($n ?? 0);
        // strip trailing zeros for cleaner display
        $s = number_format($n, 3, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
    }

    public static function url(string $path = ''): string
    {
        $base = rtrim((string)Config::get('app_url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . self::url($path));
        exit;
    }

    public static function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
    }

    public static function takeFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        $__viewFile = $viewFile;
        ob_start();
        require $__viewFile;
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
    }

    public static function renderPlain(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/Views/' . $view . '.php';
        require $viewFile;
    }

    public static function abort(int $code, string $msg = ''): never
    {
        http_response_code($code);
        echo "<h1>{$code}</h1><p>" . self::esc($msg) . "</p>";
        exit;
    }
}
