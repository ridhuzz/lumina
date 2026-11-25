<?php

namespace App\Services;

use App\Models\Statement;
use App\Models\Transaction;
use App\Support\StatementParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mengisi mutasi rekening dari PDF e-statement yang diunggah (bila admin
 * mencentang "Isi mutasi otomatis dari PDF ini").
 *
 * - Hanya baris pada periode e-statement tsb yang diambil.
 * - Mutasi yang sudah ada dilewati: ref sama, atau tanggal + nominal + arah +
 *   keterangan sama. Jadi aman bila PDF yang sama diunggah ulang.
 * - Saldo rekening TIDAK diubah: mutasi dari e-statement adalah riwayat yang
 *   sudah terjadi dan sudah tercermin di saldo saat ini.
 */
class StatementImporter
{
    /**
     * @return array{added: int, duplicate: int, unreadable: int, outside: int}
     */
    public function import(Statement $statement): array
    {
        $parsed = StatementParser::parse(
            Storage::disk(Statement::DISK)->path($statement->file_path),
            $statement->year,
            $statement->month,
        );

        $added = 0;
        $duplicate = 0;
        $accountId = $statement->account_id;
        $tz = config('app.timezone');

        DB::transaction(function () use ($parsed, $accountId, $tz, &$added, &$duplicate): void {
            foreach ($parsed['rows'] as $row) {
                $date = Carbon::parse($row['date'], $tz);

                $exists = Transaction::where('account_id', $accountId)
                    ->where(fn ($q) => $q
                        ->when($row['ref'], fn ($q) => $q->orWhere('ref', $row['ref']))
                        ->orWhere(fn ($q) => $q
                            ->whereBetween('occurred_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                            ->where('amount', $row['amount'])
                            ->where('direction', $row['direction'])
                            ->where('title', $row['title'])))
                    ->exists();

                if ($exists) {
                    $duplicate++;

                    continue;
                }

                // ref di tabel transaksi unik global; buat baru bila dipakai rekening lain.
                $ref = $row['ref'] && mb_strlen($row['ref']) <= 30 && ! Transaction::where('ref', $row['ref'])->exists()
                    ? $row['ref']
                    : Transaction::newRef();

                Transaction::create([
                    'account_id' => $accountId,
                    'ref' => $ref,
                    'title' => $row['title'],
                    'category' => $row['category'] ? mb_substr($row['category'], 0, 30) : 'Impor e-Statement',
                    'amount' => $row['amount'],
                    'direction' => $row['direction'],
                    'note' => $row['note'] ? mb_substr($row['note'], 0, 100) : null,
                    'occurred_at' => $date->setTime(12, 0),
                ]);
                $added++;
            }
        });

        return [
            'added' => $added,
            'duplicate' => $duplicate,
            'unreadable' => $parsed['unreadable'],
            'outside' => $parsed['outside'],
        ];
    }
}
