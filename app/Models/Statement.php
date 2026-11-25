<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * E-statement PDF satu rekening untuk satu periode (bulan/tahun).
 * File disimpan di disk privat "local" (storage/app/private/statements), tidak
 * bisa dibuka langsung dari browser; hanya lewat API (pemilik) atau panel admin.
 */
#[Fillable(['account_id', 'year', 'month', 'source', 'file_path', 'original_name', 'size'])]
class Statement extends Model
{
    public const DISK = 'local';

    public const DIRECTORY = 'statements';

    /** Batas ukuran PDF, dalam KB. */
    public const MAX_KB = 10240;

    /** Sumber PDF. */
    public const SOURCES = [
        'mutasi' => 'Buat dari mutasi',
        'upload' => 'Unggah file PDF',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Ukuran file dihitung otomatis setiap file berganti.
        static::saving(function (Statement $s): void {
            if ($s->isDirty('file_path') && Storage::disk(self::DISK)->exists($s->file_path)) {
                $s->size = Storage::disk(self::DISK)->size($s->file_path);
            }
        });

        // File lama dibuang saat diganti atau saat e-statement dihapus.
        static::updated(function (Statement $s): void {
            $old = $s->getOriginal('file_path');
            if ($s->wasChanged('file_path') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });

        static::deleted(fn (Statement $s) => Storage::disk(self::DISK)->delete($s->file_path));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Daftar nama bulan, mis. [1 => 'Januari', ...]. */
    public static function months(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $m) => [$m => Carbon::create(2000, $m, 1)->locale('id')->translatedFormat('F')])
            ->all();
    }

    /** Label periode, mis. "Oktober 2026". */
    public function periodLabel(): string
    {
        return self::months()[$this->month].' '.$this->year;
    }

    /** Nama file saat diunduh, mis. "eStatement-8820031475-2026-10.pdf". */
    public function downloadName(): string
    {
        return sprintf('eStatement-%s-%04d-%02d.pdf', $this->account->number, $this->year, $this->month);
    }
}
