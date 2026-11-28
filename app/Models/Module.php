<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Modul "Menu Utama" di beranda aplikasi: ikon, nama, dan ID modul.
 * Modul nonaktif tetap tampil, tetapi tidak bisa dibuka.
 */
#[Fillable(['code', 'name', 'icon', 'badge', 'sort_order', 'is_active'])]
class Module extends Model
{
    /** Ikon yang dikenal aplikasi (kunci => keterangan untuk admin). */
    public const ICONS = [
        'transfer' => 'Transfer (pesawat kertas)',
        'payment' => 'Bayar & Top Up (struk)',
        'invest' => 'Investasi (grafik naik)',
        'lifestyle' => 'Gaya Hidup (tas belanja)',
        'emoney' => 'e-Money (kartu)',
        'cardless' => 'Tarik Tunai (ATM)',
        'products' => 'Produk Bank (gedung bank)',
        'protection' => 'Proteksi (perisai)',
        'history' => 'Mutasi (riwayat)',
        'ewallet' => 'e-Wallet (dompet)',
        'qris' => 'QRIS (pindai)',
        'more' => 'Lainnya (kotak-kotak)',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    /** Bentuk untuk aplikasi (GET /api/menu). */
    public function toMenuItem(): array
    {
        return [
            'id' => $this->code,
            'label' => $this->name,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'active' => $this->is_active,
            // Untuk aplikasi versi lama yang masih membaca dua kolom ini.
            'route' => '/'.$this->code,
            'enabled' => $this->is_active,
        ];
    }
}
