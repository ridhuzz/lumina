<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pengaturan aplikasi (key-value), dibaca lewat cache.
 *
 * Identitas merek (nama aplikasi, nama perusahaan, logo) disimpan di sini supaya
 * satu kode bisa dipakai banyak perusahaan: tiap server punya identitasnya sendiri,
 * dan aplikasi mobile mengambilnya dari server yang dituju.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const CACHE_KEY = 'app_settings';

    public const DISK = 'local';

    public const LOGO_DIRECTORY = 'branding';

    public const LOGO_MAX_KB = 1024;

    /** Nilai bawaan bila belum diatur admin. */
    public const DEFAULTS = [
        'app_name' => 'Lumina',
        'company_name' => 'Lumina Digital',
        'logo_path' => null,
    ];

    /** @return array<string, ?string> */
    public static function values(): array
    {
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY,
                fn () => static::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            $stored = []; // tabel belum dimigrasi
        }

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    public static function get(string $key): ?string
    {
        return static::values()[$key] ?? null;
    }

    /** @param array<string, ?string> $values */
    public static function put(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public static function appName(): string
    {
        return trim((string) static::get('app_name')) ?: self::DEFAULTS['app_name'];
    }

    public static function companyName(): string
    {
        return trim((string) static::get('company_name')) ?: static::appName();
    }

    public static function logoPath(): ?string
    {
        $path = static::get('logo_path');

        return $path && Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    /** Versi untuk cache-busting URL logo (berubah tiap logo diganti). */
    public static function logoVersion(): ?string
    {
        $path = static::logoPath();

        return $path ? substr(md5($path), 0, 8) : null;
    }
}
