<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan key-value: minggu_acuan, geng_acuan, batas_jam_kuliah, dan lainnya.
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'minggu_acuan' => '2026-11-16',
        'geng_acuan' => '1',
        'batas_jam_kuliah' => '09:00',
    ];

    private const CACHE_KEY = 'pengaturan.semua';

    /** @return array<string, string|null> */
    public static function semua(): array
    {
        $tersimpan = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all()
        );

        return array_merge(self::DEFAULTS, $tersimpan);
    }

    public static function ambil(string $key, ?string $default = null): ?string
    {
        return self::semua()[$key] ?? $default;
    }

    public static function simpan(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }
}
