<?php

use App\Http\Controllers\AdminBannerImageController;
use App\Http\Controllers\AdminStatementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// PDF e-statement untuk panel admin (memakai sesi login panel; is_admin dicek di controller).
Route::get('/admin/statements/{statement}/pdf', AdminStatementController::class)
    ->name('admin.statements.pdf');

// Pratinjau gambar banner untuk panel admin.
Route::get('/admin/banners/{banner}/image', AdminBannerImageController::class)
    ->name('admin.banners.image');
