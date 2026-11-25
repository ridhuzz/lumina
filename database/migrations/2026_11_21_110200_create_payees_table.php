<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar penerima tersimpan (untuk "transfer cepat").
     */
    public function up(): void
    {
        Schema::create('payees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('bank', 50);
            $table->string('number', 20);
            $table->timestamps();

            $table->unique(['user_id', 'bank', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payees');
    }
};
