<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{old: string, new: string}>
     */
    private array $settings = [
        'seo_site_title' => [
            'old' => 'Portal Yuran PIBG SK Sri Petaling',
            'new' => 'Portal Sumbangan PIBG SK Sri Petaling',
        ],
        'seo_description' => [
            'old' => 'Portal rasmi semakan dan pembayaran Yuran & Sumbangan PIBG SK Sri Petaling, didukung oleh Avante Intelligence dan Arif.my sebagai inisiatif pendigitalan pendidikan sekolah.',
            'new' => 'Portal rasmi semakan dan pembayaran Sumbangan PIBG SK Sri Petaling, didukung oleh Avante Intelligence dan Arif.my sebagai inisiatif pendigitalan pendidikan sekolah.',
        ],
        'seo_keywords' => [
            'old' => 'Portal Yuran PIBG, SK Sri Petaling, Avante Intelligence, Arif.my, digitalisasi pendidikan, pendigitalan sekolah, semakan yuran, pembayaran PIBG, portal ibu bapa, inisiatif pendidikan digital',
            'new' => 'Portal Sumbangan PIBG, SK Sri Petaling, Avante Intelligence, Arif.my, digitalisasi pendidikan, pendigitalan sekolah, semakan sumbangan, pembayaran PIBG, portal ibu bapa, inisiatif pendidikan digital',
        ],
        'seo_og_site_name' => [
            'old' => 'Portal Yuran PIBG SK Sri Petaling',
            'new' => 'Portal Sumbangan PIBG SK Sri Petaling',
        ],
    ];

    public function up(): void
    {
        $this->replaceSettings('old', 'new');
    }

    public function down(): void
    {
        $this->replaceSettings('new', 'old');
    }

    private function replaceSettings(string $from, string $to): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        foreach ($this->settings as $key => $values) {
            DB::table('site_settings')
                ->where('key', $key)
                ->where('value', $values[$from])
                ->update(['value' => $values[$to]]);
        }

        Cache::forget('site_settings.map.v1');
    }
};
