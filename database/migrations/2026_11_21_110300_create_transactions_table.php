<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mutasi rekening. direction: 'in' (uang masuk) / 'out' (uang keluar).
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('ref', 30)->unique();
            $table->string('title', 100);
            $table->string('category', 30);
            $table->decimal('amount', 15, 2);
            $table->enum('direction', ['in', 'out']);
            $table->string('note', 100)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['account_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
