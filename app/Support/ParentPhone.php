<?php

namespace App\Support;

final class ParentPhone
{
    public static function sanitizeInput(string $phone): string
    {
        $trimmed = trim($phone);

        return preg_replace('/\s+/', '', $trimmed) ?: $trimmed;
    }

    public static function normalizeForMatch(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '60')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '6'.$digits;
        }

        if (str_starts_with($digits, '1')) {
            return '60'.$digits;
        }

        return $digits;
    }

    /**
     * @return array<int, string>
     */
    public static function variants(string $phone): array
    {
        $sanitized = self::sanitizeInput($phone);
        $digits = preg_replace('/\D+/', '', $sanitized) ?? '';
        $normalized = self::normalizeForMatch($sanitized);

        $variants = [];

        foreach ([$sanitized, $digits, $normalized] as $candidate) {
            if ($candidate !== '' && ! in_array($candidate, $variants, true)) {
                $variants[] = $candidate;
            }
        }

        if ($normalized !== '' && str_starts_with($normalized, '60')) {
            $local = '0'.substr($normalized, 2);
            foreach ([$local, '+'.$normalized] as $candidate) {
                if ($candidate !== '' && ! in_array($candidate, $variants, true)) {
                    $variants[] = $candidate;
                }
            }
        }

        return $variants;
    }
}
