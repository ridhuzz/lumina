<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Module;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint widget beranda & cek penerima (dipakai aplikasi Kiki)
 * (menu utama, kurs, catatan keuangan, cek nama penerima).
 */
class HomeWidgetsController extends Controller
{
    /** Warna & ikon tiap kategori transaksi untuk grafik "Catatan Keuangan". */
    private const CATEGORY_STYLE = [
        'Transfer' => ['9B5FAA', 'transfer'],
        'QRIS' => ['C2615F', 'shopping'],
        'Pembayaran' => ['C2615F', 'shopping'],
        'Tagihan' => ['F7941D', 'other'],
        'Top Up' => ['1E9BE0', 'other'],
        'Tarik Tunai' => ['5B7C99', 'other'],
        'Investasi' => ['2CB8B9', 'other'],
        'Biaya' => ['8A8F94', 'other'],
        'Pemasukan' => ['2CB8B9', 'salary'],
    ];

    /**
     * GET /api/menu — modul "Menu Utama" beranda, diatur admin (menu Modul Menu).
     * Modul nonaktif ikut dikirim dengan active=false: tampil, tetapi tidak bisa dibuka.
     */
    public function menu(): JsonResponse
    {
        return response()->json(
            Module::ordered()->get()->map->toMenuItem()->values()
        );
    }

    /** GET /api/rates — kurs contoh (IDR per 1 unit), diperbarui tiap pagi. */
    public function rates(): JsonResponse
    {
        return response()->json([
            'updated_at' => now()->startOfDay()->setTime(5, 0, 1)->toIso8601String(),
            'items' => [
                ['code' => 'USD', 'flag' => '🇺🇸', 'buy' => 18289.00, 'sell' => 18399.00],
                ['code' => 'SGD', 'flag' => '🇸🇬', 'buy' => 14099.22, 'sell' => 14327.89],
                ['code' => 'CNY', 'flag' => '🇨🇳', 'buy' => 2723.55, 'sell' => 2789.77],
                ['code' => 'EUR', 'flag' => '🇪🇺', 'buy' => 19898.91, 'sell' => 20220.03],
            ],
        ]);
    }

    /** GET /api/cashflow — pengeluaran & pemasukan bulan ini per kategori. */
    public function cashflow(Request $request): JsonResponse
    {
        $account = $request->user()->account()->firstOrFail();

        $rows = $account->transactions()
            ->where('occurred_at', '>=', now()->startOfMonth())
            ->selectRaw('category, direction, SUM(amount) as total')
            ->groupBy('category', 'direction')
            ->orderByDesc('total')
            ->get();

        $slice = function ($row): array {
            [$color, $icon] = self::CATEGORY_STYLE[$row->category] ?? ['1E9BE0', 'other'];

            return [
                'id' => strtolower(str_replace(' ', '_', $row->category)),
                'label' => $row->category,
                'amount' => (int) round((float) $row->total),
                'color' => $color,
                'icon' => $icon,
            ];
        };

        return response()->json([
            'spending' => $rows->where('direction', 'out')->map($slice)->values(),
            'income' => $rows->where('direction', 'in')->map($slice)->values(),
        ]);
    }

    /**
     * POST /api/transfer/inquiry — cek nama pemilik rekening tujuan.
     * Sesama bank (nama aplikasi): dicari di database. Bank lain: dari daftar
     * penerima tersimpan, atau nama contoh bila belum pernah disimpan.
     */
    public function inquiry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank' => ['required', 'string', 'max:50'],
            'number' => ['required', 'string', 'regex:/^\d{6,20}$/'],
        ], [
            'number.regex' => __('Nomor rekening harus 6–20 digit angka.'),
        ]);

        $user = $request->user();

        if ($data['bank'] === Setting::appName()) {
            $account = Account::with('user')->where('number', $data['number'])->first();

            if (! $account) {
                return response()->json(['message' => __('Rekening tidak ditemukan.')], 404);
            }
            if ($account->user_id === $user->id) {
                return response()->json(['message' => __('Tidak bisa transfer ke rekening sendiri.')], 422);
            }

            return response()->json(['beneficiary_name' => strtoupper($account->user->name)]);
        }

        $payee = $user->payees()
            ->where('bank', $data['bank'])
            ->where('number', $data['number'])
            ->first();

        $names = ['RIZKY HIDAYAT', 'SITI AMINAH', 'AGUS WIJAYA', 'DEWI LESTARI'];

        return response()->json([
            'beneficiary_name' => $payee
                ? strtoupper($payee->name)
                : $names[((int) substr($data['number'], -1)) % count($names)],
        ]);
    }
}
