<?php

namespace App\Support;

use InvalidArgumentException;

final class JogathonAmount
{
    public static function senFromRinggit(string $amount): int
    {
        $value = trim($amount);

        if (preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid Ringgit amount.');
        }

        [$ringgit, $sen] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $ringgit * 100) + (int) str_pad($sen, 2, '0');
    }

    public static function distanceCmFromSen(int $amountSen): int
    {
        return $amountSen * (int) config('jogathon.distance_cm_per_sen', 10);
    }

    public static function ringgit(int $amountSen): string
    {
        return 'RM'.number_format($amountSen / 100, 2, '.', ',');
    }

    public static function metres(int $distanceCm): string
    {
        return number_format($distanceCm / 100, 0, '.', ',').' m';
    }

    public static function kilometres(int $distanceCm): string
    {
        return number_format($distanceCm / 100_000, 2, '.', ',').' km';
    }
}
