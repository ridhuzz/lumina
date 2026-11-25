<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Banner promo yang dikelola admin. Gambar disimpan privat dan disajikan lewat
 * GET /api/banners/{id}/image, jadi bisa dibuka dari IP/hosting mana pun.
 */
#[Fillable(['placement', 'title', 'image_path', 'link_url', 'sort_order', 'is_active', 'starts_at', 'ends_at'])]
class Banner extends Model
{
    public const DISK = 'local';

    public const DIRECTORY = 'banners';

    /** Batas ukuran gambar, dalam KB. */
    public const MAX_KB = 2048;

    public const PLACEMENTS = [
        'welcome' => 'Halaman login (sebelum masuk)',
        'home' => 'Beranda (setelah masuk)',
    ];

    /** Ukuran gambar yang disarankan per posisi. */
    public const SIZES = [
        'welcome' => '1200 × 500 px (rasio 12:5)',
        'home' => '1200 × 240 px (rasio 5:1)',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Buang file lama saat gambar diganti atau banner dihapus.
        static::updated(function (Banner $b): void {
            $old = $b->getOriginal('image_path');
            if ($b->wasChanged('image_path') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
        static::deleted(fn (Banner $b) => Storage::disk(self::DISK)->delete($b->image_path));
    }

    /** Banner yang sedang tayang: aktif dan dalam periode (bila diisi). */
    public function scopeLive(Builder $q): Builder
    {
        $now = now();

        return $q->where('is_active', true)
            ->where(fn (Builder $w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Path gambar relatif terhadap base URL API; ?v= memaksa refresh saat diganti. */
    public function imagePath(): string
    {
        return "banners/{$this->id}/image?v=".$this->updated_at?->timestamp;
    }
}
