<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E-statement (rekening koran PDF) per rekening per bulan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->string('file_path');          // relatif terhadap disk "local" (storage/app/private)
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size')->default(0); // byte
            $table->timestamps();

            // Satu e-statement per rekening per periode.
            $table->unique(['account_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statements');
    }
};
