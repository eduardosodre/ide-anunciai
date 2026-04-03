<?php

declare(strict_types=1);

namespace App\Util;

final class PerfilUuid
{
    public static function generate(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /**
     * Aceita qualquer UUID no formato 8-4-4-4-12 (hex), incl. v1 do MySQL (LOWER(UUID()))
     * e v4 gerado por generate().
     */
    public static function isValid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            trim($value)
        );
    }
}
