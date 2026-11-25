<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Banner promo aplikasi: di halaman login (sebelum masuk) dan di beranda. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 20);          // welcome | home
            $table->string('title', 100);              // nama internal / teks alternatif
            $table->string('image_path');              // disk "local" (privat), disajikan lewat API
            $table->string('link_url')->nullable();    // dibuka saat banner diketuk
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index(['placement', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
