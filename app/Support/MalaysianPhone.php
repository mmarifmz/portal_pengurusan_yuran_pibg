<?php

namespace App\Support;

class MalaysianPhone
{
    public static function normalize(mixed $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }

        if (! preg_match('/^601[0-9]\d{7,8}$/', $digits)) {
            return null;
        }

        return '+'.$digits;
    }

    /**
     * @return array<int, string>
     */
    public static function variants(mixed $value): array
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return [];
        }

        $digits = ltrim($normalized, '+');
        $local = '0'.substr($digits, 2);

        return array_values(array_unique([
            $normalized,
            $digits,
            $local,
        ]));
    }
}
