<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Asal PDF e-statement: dibuat dari mutasi atau diunggah admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statements', function (Blueprint $table) {
            $table->string('source', 10)->default('upload')->after('month'); // mutasi | upload
        });
    }

    public function down(): void
    {
        Schema::table('statements', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
