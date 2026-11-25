<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu untuk mengubah saldo dari backoffice.
 * Saldo tidak pernah diketik langsung: setiap perubahan = satu baris mutasi,
 * sehingga saldo selalu bisa ditelusuri dari riwayat transaksi.
 */
class LedgerService
{
    /**
     * @param  'in'|'out'  $direction  in = uang masuk (setor), out = uang keluar (tarik)
     *
     * @throws DomainException bila nominal tidak valid atau saldo tidak cukup
     */
    public function adjust(
        Account $account,
        string $direction,
        float|int|string $amount,
        string $title,
        string $category,
        ?string $note = null,
    ): Transaction {
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new DomainException(__('Arah transaksi tidak valid.'));
        }

        // Dihitung sebagai string 2 desimal dengan bcmath, bukan float.
        $amount = number_format((float) $amount, 2, '.', '');

        if (bccomp($amount, '0', 2) <= 0) {
            throw new DomainException(__('Nominal harus lebih dari 0.'));
        }

        return DB::transaction(function () use ($account, $direction, $amount, $title, $category, $note) {
            // Kunci baris rekening agar tidak bentrok dengan transfer dari aplikasi.
            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ($direction === 'out' && bccomp((string) $locked->balance, $amount, 2) < 0) {
                throw new DomainException(__('Saldo tidak cukup untuk penarikan ini.'));
            }

            $locked->balance = $direction === 'in'
                ? bcadd((string) $locked->balance, $amount, 2)
                : bcsub((string) $locked->balance, $amount, 2);
            $locked->save();

            return $locked->transactions()->create([
                'ref' => Transaction::newRef(),
                'title' => $title,
                'category' => $category,
                'amount' => $amount,
                'direction' => $direction,
                'note' => $note,
                'occurred_at' => now(),
            ]);
        });
    }
}
