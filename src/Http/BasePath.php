<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Prefixo da aplicação quando não está na raiz do domínio (ex.: /public no Hostinger).
 * Detetado a partir de SCRIPT_NAME.
 */
final class BasePath
{
    private static ?string $cached = null;

    public static function get(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $script = str_replace('\\', '/', (string) $script);
        $dir = dirname($script);
        if ($dir === '/' || $dir === '.' || $dir === '') {
            self::$cached = '';
        } else {
            self::$cached = rtrim($dir, '/');
        }

        return self::$cached;
    }

    public static function url(string $path): string
    {
        if ($path === '') {
            $path = '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        $base = self::get();

        return $base === '' ? $path : $base . $path;
    }
}
