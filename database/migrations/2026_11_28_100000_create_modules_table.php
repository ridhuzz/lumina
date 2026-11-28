<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Menu Utama" di beranda aplikasi. Modul nonaktif tetap tampil tetapi
 * tidak bisa dibuka. Isi awal ikut dimasukkan di sini, jadi server yang sudah
 * berjalan cukup menjalankan `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();  // ID modul, dipakai aplikasi (mis. "transfer")
            $table->string('name', 40);            // label di bawah ikon
            $table->string('icon', 40);            // kunci ikon, lihat Module::ICONS
            $table->string('badge', 12)->nullable(); // mis. "BARU"
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            // [code, name, icon, badge, aktif]
            ['transfer', 'Transfer', 'transfer', null, true],
            ['payment', 'Bayar & Top Up', 'payment', 'BARU', false],
            ['invest', 'Investasi', 'invest', null, false],
            ['lifestyle', 'Gaya Hidup', 'lifestyle', 'BARU', false],
            ['emoney', 'e-Money', 'emoney', null, false],
            ['cardless', 'Tarik Tunai', 'cardless', null, false],
            ['products', 'Produk Bank', 'products', null, false],
            ['protection', 'Proteksi', 'protection', null, false],
            ['history', 'Mutasi', 'history', null, true],
            ['ewallet', 'e-Wallet', 'ewallet', null, false],
            ['qris', 'QRIS', 'qris', null, true],
            ['more', 'Lainnya', 'more', null, false],
        ];
        DB::table('modules')->insert(array_map(fn (array $r, int $i): array => [
            'code' => $r[0],
            'name' => $r[1],
            'icon' => $r[2],
            'badge' => $r[3],
            'is_active' => $r[4],
            'sort_order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows, array_keys($rows)));
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
