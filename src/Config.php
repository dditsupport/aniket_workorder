<?php
declare(strict_types=1);

namespace App;

final class Config
{
    private static array $data = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Missing config file: {$path}. Copy config.sample.php to config.php and edit it.");
        }
        $cfg = require $path;
        if (!is_array($cfg)) {
            throw new \RuntimeException("Config file must return an array.");
        }
        self::$data = $cfg;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $val = self::$data;
        foreach ($parts as $p) {
            if (!is_array($val) || !array_key_exists($p, $val)) {
                return $default;
            }
            $val = $val[$p];
        }
        return $val;
    }

    public static function all(): array
    {
        return self::$data;
    }
}
