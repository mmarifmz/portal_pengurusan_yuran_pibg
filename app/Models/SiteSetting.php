<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    private const CACHE_KEY = 'site_settings.map.v1';
    private const DEFAULT_LOGO_ASSET = 'images/sksp-logo.png';

    /**
     * @return array<string, string>
     */
    public static function allAsMap(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function (): array {
                if (! Schema::hasTable('site_settings')) {
                    return [];
                }

                return self::query()
                    ->pluck('value', 'key')
                    ->map(fn ($value) => (string) $value)
                    ->toArray();
            });
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, string> $defaults
     * @return array<string, string>
     */
    public static function getMany(array $defaults): array
    {
        return array_merge($defaults, self::allAsMap());
    }

    /**
     * @param array<string, string|null> $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function schoolLogoUrl(): string
    {
        $settings = self::getMany([
            'school_logo_url' => asset(self::DEFAULT_LOGO_ASSET),
        ]);

        $logoUrl = trim((string) ($settings['school_logo_url'] ?? ''));

        return $logoUrl !== '' ? $logoUrl : asset(self::DEFAULT_LOGO_ASSET);
    }

    public static function faviconUrl(): string
    {
        return self::schoolLogoUrl();
    }

    public static function schoolLogoPdfSource(): string
    {
        $logoUrl = self::schoolLogoUrl();
        $path = parse_url($logoUrl, PHP_URL_PATH);

        if (is_string($path) && trim($path) !== '') {
            $resolved = public_path(ltrim($path, '/'));
            if (is_file($resolved)) {
                return $resolved;
            }
        }

        return public_path(self::DEFAULT_LOGO_ASSET);
    }
}
