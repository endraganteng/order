<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * FeatureFlagService
 *
 * DB-backed override untuk config('features.*'). Nilai di app_settings
 * (key prefix 'feature.') menimpa default config saat boot. Memungkinkan
 * toggle flag dari UI settings tanpa edit .env / deploy.
 *
 * Cache 1 jam; di-flush tiap set() supaya toggle UI langsung berlaku.
 */
class FeatureFlagService
{
    private const PREFIX = 'feature.';
    private const CACHE_KEY = 'app_feature_flags';
    private const CACHE_TTL = 3600;

    /**
     * Ambil semua flag override dari DB (key tanpa prefix => bool).
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $rows = DB::table('app_settings')
                ->where('key', 'like', self::PREFIX.'%')
                ->pluck('value', 'key');

            $flags = [];
            foreach ($rows as $key => $value) {
                $shortKey = substr($key, strlen(self::PREFIX));
                $flags[$shortKey] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            return $flags;
        });
    }

    /**
     * Set satu flag (true/false) + flush cache.
     */
    public function set(string $flag, bool $value): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => self::PREFIX.$flag],
            ['value' => $value ? '1' : '0', 'updated_at' => now()]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Hapus override satu flag (kembali ke default config/.env) + flush cache.
     */
    public function forget(string $flag): void
    {
        DB::table('app_settings')->where('key', self::PREFIX.$flag)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Terapkan override DB ke runtime config('features.*').
     * Dipanggil di service provider boot().
     */
    public function applyToConfig(): void
    {
        foreach ($this->all() as $flag => $value) {
            config(['features.'.$flag => $value]);
        }
    }
}
