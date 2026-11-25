<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Support\DemoBannerImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Banner contoh (gambar dibuat otomatis). Bisa dijalankan sendiri tanpa mereset data:
 *   php artisan db:seed --class=BannerSeeder
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $blue = [[11, 74, 143], [47, 128, 237]];
        $teal = [[12, 87, 174], [44, 192, 179]];
        $sky = [[46, 139, 214], [102, 201, 236]];
        $navy = [[7, 51, 99], [28, 100, 196]];

        $banners = [
            ['welcome', 'Tabungan Emas Lumina', 'Mulai Rp10.000, aman & real-time', 'Buka Sekarang', $blue],
            ['welcome', 'Cashback QRIS 20%', 'Bayar pakai QRIS Lumina di merchant pilihan', 'Lihat Promo', $teal],
            ['welcome', 'Cicilan Instan', 'Limit langsung cair untuk belanja harian', 'Ajukan', $navy],
            ['home', 'Undian Lumina Berhadiah', 'Transaksi makin sering, peluang makin besar', 'Main & Menang', $sky],
            ['home', 'Bebas Biaya Transfer', 'Ke semua bank, gratis 25x per bulan', 'Cek Syarat', $teal],
        ];

        $order = [];
        foreach ($banners as [$placement, $title, $subtitle, $cta, [$from, $to]]) {
            $order[$placement] = ($order[$placement] ?? 0) + 1; // urutan per posisi
            [$w, $h] = $placement === 'welcome' ? [1200, 500] : [1200, 240];
            $path = Banner::DIRECTORY.'/demo-'.Str::slug($title).'.png';
            Storage::disk(Banner::DISK)->put($path, DemoBannerImage::make($w, $h, $title, $subtitle, $cta, $from, $to));

            Banner::updateOrCreate(
                ['placement' => $placement, 'title' => $title],
                ['image_path' => $path, 'sort_order' => $order[$placement], 'is_active' => true],
            );
        }
    }
}
