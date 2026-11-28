<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\HomeWidgetsController;
use App\Http\Controllers\Api\StatementController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;

/*
| Lumina API — semua route berawalan /api.
| Login dibatasi 10 percobaan/menit per IP agar PIN tidak bisa ditebak beruntun.
*/

Route::get('/health', fn () => ['status' => 'ok', 'app' => config('app.name')]);

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Identitas aplikasi & banner promo (publik: tampil sebelum nasabah masuk).
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/app-config', [AppConfigController::class, 'show']);
    Route::get('/app-config/logo', [AppConfigController::class, 'logo']);

    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/banners/{id}/image', [BannerController::class, 'image'])->whereNumber('id');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/accounts/me', [AccountController::class, 'me']);
    Route::get('/payees', [AccountController::class, 'payees']);
    Route::get('/transactions', [AccountController::class, 'transactions']);

    // Widget beranda & cek penerima (dipakai aplikasi Kiki).
    Route::get('/menu', [HomeWidgetsController::class, 'menu']);
    Route::get('/rates', [HomeWidgetsController::class, 'rates']);
    Route::get('/cashflow', [HomeWidgetsController::class, 'cashflow']);
    Route::post('/transfer/inquiry', [HomeWidgetsController::class, 'inquiry'])->middleware('throttle:30,1');

    Route::get('/statements', [StatementController::class, 'index']);
    Route::get('/statements/{id}/file', [StatementController::class, 'file'])->whereNumber('id');

    Route::post('/transfers', [TransferController::class, 'store'])->middleware('throttle:30,1');
});
