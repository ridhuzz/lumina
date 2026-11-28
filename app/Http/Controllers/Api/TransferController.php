<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TransferController extends Controller
{
    /** Biaya transfer ke bank lain (sesama bank gratis). */
    public const EXTERNAL_FEE = '2500.00';

    /**
     * POST /api/transfers
     * Body: { payee_name, bank, number, amount, note?, pin }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payee_name' => ['required', 'string', 'max:100'],
            'bank' => ['required', 'string', 'max:50'],
            'number' => ['required', 'string', 'regex:/^\d{6,20}$/'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:100'],
            'pin' => ['required', 'digits:6'],
        ], [
            'number.regex' => __('Nomor rekening harus 6–20 digit angka.'),
            'amount.min' => __('Nominal tidak valid.'),
        ]);

        $user = $request->user();

        if (! Hash::check($data['pin'], $user->pin)) {
            return response()->json(['message' => __('PIN salah. Coba lagi.')], 422);
        }

        // Nominal sebagai string 2 desimal agar dihitung presisi dengan bcmath (bukan float).
        $amount = number_format((float) $data['amount'], 2, '.', '');

        $sender = $user->account()->firstOrFail();

        // Bank internal = nama aplikasi (menu Identitas Aplikasi): transfer ke bank ini
        // langsung mengkredit rekening tujuan.
        $internal = $data['bank'] === Setting::appName();
        $fee = $internal ? '0.00' : self::EXTERNAL_FEE;

        if ($internal && $data['number'] === $sender->number) {
            return response()->json(['message' => __('Tidak bisa transfer ke rekening sendiri.')], 422);
        }

        $result = DB::transaction(function () use ($sender, $user, $data, $amount, $fee, $internal) {
            // Rekening tujuan internal (bila ada) ikut dikunci. Urutan kunci berdasarkan id
            // supaya dua transfer berlawanan arah tidak saling menunggu (deadlock).
            $receiverId = $internal
                ? Account::where('number', $data['number'])->value('id')
                : null;

            $ids = array_values(array_filter([$sender->id, $receiverId]));
            sort($ids);
            $locked = Account::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $from = $locked[$sender->id];

            $total = bcadd($amount, $fee, 2);

            if (bccomp((string) $from->balance, $total, 2) < 0) {
                return null; // saldo tidak cukup (nominal + biaya)
            }

            $from->balance = bcsub((string) $from->balance, $total, 2);
            $from->save();

            $now = now();
            $out = $from->transactions()->create([
                'ref' => Transaction::newRef(),
                'title' => $data['payee_name'],
                'category' => 'Transfer',
                'amount' => $amount,
                'direction' => 'out',
                'note' => $data['note'] ?? null,
                'occurred_at' => $now,
            ]);

            if (bccomp($fee, '0', 2) > 0) {
                $from->transactions()->create([
                    'ref' => Transaction::newRef(),
                    'title' => 'Biaya Transfer Antarbank',
                    'category' => 'Biaya',
                    'amount' => $fee,
                    'direction' => 'out',
                    'note' => $data['payee_name'],
                    'occurred_at' => $now,
                ]);
            }

            if ($receiverId) {
                $to = $locked[$receiverId];
                $to->balance = bcadd((string) $to->balance, $amount, 2);
                $to->save();

                $to->transactions()->create([
                    'ref' => Transaction::newRef(),
                    'title' => $user->name,
                    'category' => 'Transfer',
                    'amount' => $amount,
                    'direction' => 'in',
                    'note' => $data['note'] ?? null,
                    'occurred_at' => $now,
                ]);
            }

            return $out;
        });

        if ($result === null) {
            return response()->json(['message' => __('Saldo tidak cukup.')], 422);
        }

        return (new TransactionResource($result))
            ->additional(['fee' => (float) $fee])
            ->response()
            ->setStatusCode(201);
    }
}
