<?php

namespace App\Support;

use Smalot\PdfParser\Parser;

/**
 * Membaca baris mutasi dari PDF e-statement (rekening koran).
 *
 * Teks PDF diambil beserta posisinya, lalu disusun ulang per baris (y sama).
 * Baris diawali tanggal (dd/mm atau dd/mm/yyyy) dan berisi nominal dianggap
 * satu mutasi. Arah dibaca dari tanda "+"/"-" atau kode CR/DB (K/D) di sekitar
 * nominal. Baris tanpa tanggal tepat di bawahnya dianggap rincian
 * ("Kategori · REF" atau catatan tambahan). Baris SALDO AWAL/AKHIR dilewati.
 *
 * Cocok untuk e-statement Lumina dan format rekening koran umum yang tabelnya
 * berupa teks (bukan hasil scan/gambar).
 */
class StatementParser
{
    private const DATE = '/^(\d{1,2})[\/.-](\d{1,2})(?:[\/.-](\d{2,4}))?$/';

    /** Nominal: wajib ada "Rp", pemisah ribuan, atau 2 desimal agar tahun/angka biasa tidak ikut. */
    private const MONEY = '/^(?<pre>[+\-−]|CR|DB)?\s*(?:Rp\.?\s*)?(?<num>-?(?:\d{1,3}(?:[.,]\d{3})+|\d+)(?:[.,]\d{2})?)\s*(?<post>CR|DB|K|D)?$/i';

    /**
     * @return array{rows: list<array{date: string, title: string, note: ?string, category: ?string, ref: ?string, amount: string, direction: string}>, unreadable: int, outside: int}
     */
    public static function parse(string $absolutePath, int $year, int $month): array
    {
        $pdf = (new Parser)->parseFile($absolutePath);

        $rows = [];
        $unreadable = 0;
        $outside = 0;

        foreach ($pdf->getPages() as $page) {
            $last = null; // indeks baris mutasi terakhir (untuk baris rincian)
            $lastY = 0.0;

            foreach (self::lines($page->getDataTm()) as [$y, $cells]) {
                $first = $cells[0];

                if (! preg_match(self::DATE, $first, $d)) {
                    // Baris rincian di bawah mutasi: "Kategori · REF" atau catatan.
                    if ($last !== null && $lastY - $y <= 14 && count($cells) === 1 && ! self::isMoney($first)) {
                        if (preg_match('/^(.+?)\s*·\s*(\S+)$/u', $first, $m)) {
                            $rows[$last]['category'] = trim($m[1]);
                            $rows[$last]['ref'] = trim($m[2]);
                        } else {
                            $rows[$last]['note'] = trim(($rows[$last]['note'] ?? '').' '.$first);
                        }
                    } else {
                        $last = null;
                    }

                    continue;
                }

                $last = null;
                $rest = array_slice($cells, 1);

                $desc = [];
                $amount = null;
                $direction = null;
                $flag = null; // CR/DB yang berdiri sendiri

                foreach ($rest as $cell) {
                    if (preg_match('/^(CR|DB|K|D)$/i', $cell)) {
                        $flag ??= strtoupper($cell);
                    } elseif ($amount === null && preg_match(self::MONEY, $cell, $m) && self::isMoney($cell)) {
                        $amount = self::toNumber($m['num']);
                        $sign = strtoupper(($m['pre'] ?? '') ?: ($m['post'] ?? ''));
                        $direction = match (true) {
                            in_array($sign, ['+', 'CR', 'K'], true) => 'in',
                            in_array($sign, ['-', '−', 'DB', 'D'], true), str_starts_with($m['num'], '-') => 'out',
                            default => null,
                        };
                    } elseif (! self::isMoney($cell)) {
                        $desc[] = $cell; // kolom saldo (nominal kedua) diabaikan
                    }
                }

                $text = trim(implode(' ', $desc));
                if (preg_match('/^SALDO\b/i', $text)) {
                    continue; // saldo awal/akhir, bukan mutasi
                }

                $direction ??= match ($flag) {
                    'CR', 'K' => 'in',
                    'DB', 'D' => 'out',
                    default => null,
                };

                if ($amount === null || $direction === null || $text === '' || (float) $amount <= 0) {
                    $unreadable++;

                    continue;
                }

                $day = (int) $d[1];
                $mon = (int) $d[2];
                $yr = isset($d[3]) ? (int) (strlen($d[3]) === 2 ? '20'.$d[3] : $d[3]) : $year;
                if ($mon !== $month || $yr !== $year || ! checkdate($mon, $day, $yr)) {
                    $outside++;

                    continue;
                }

                [$title, $note] = array_pad(explode(' - ', $text, 2), 2, null);

                $rows[] = [
                    'date' => sprintf('%04d-%02d-%02d', $yr, $mon, $day),
                    'title' => mb_substr(trim($title), 0, 100),
                    'note' => $note !== null ? trim($note) : null,
                    'category' => null,
                    'ref' => null,
                    'amount' => $amount,
                    'direction' => $direction,
                ];
                $last = array_key_last($rows);
                $lastY = $y;
            }
        }

        return ['rows' => $rows, 'unreadable' => $unreadable, 'outside' => $outside];
    }

    /**
     * Kelompokkan potongan teks per baris (y hampir sama), urut atas→bawah, kiri→kanan.
     *
     * @return list<array{0: float, 1: list<string>}>  [y, sel-sel teks]
     */
    private static function lines(array $dataTm): array
    {
        $items = [];
        foreach ($dataTm as [$tm, $text]) {
            $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
            if ($text !== '') {
                $items[] = ['x' => (float) $tm[4], 'y' => (float) $tm[5], 't' => $text];
            }
        }

        usort($items, fn ($a, $b) => [$b['y'], $a['x']] <=> [$a['y'], $b['x']]);

        $lines = [];
        $currentY = null;
        foreach ($items as $it) {
            if ($currentY === null || abs($currentY - $it['y']) > 2.5) {
                $lines[] = [];
                $currentY = $it['y'];
            }
            $lines[array_key_last($lines)][] = $it;
        }

        return array_map(function (array $line): array {
            usort($line, fn ($a, $b) => $a['x'] <=> $b['x']);

            return [$line[0]['y'], array_column($line, 't')];
        }, $lines);
    }

    private static function isMoney(string $cell): bool
    {
        if (! preg_match(self::MONEY, $cell, $m)) {
            return false;
        }

        return stripos($cell, 'rp') !== false
            || preg_match('/\d{1,3}([.,])\d{3}/', $m['num'])
            || preg_match('/[.,]\d{2}$/', $m['num']);
    }

    /** "9.500.000" / "9,500,000.00" / "9.500.000,00" -> "9500000.00" */
    private static function toNumber(string $num): string
    {
        $num = ltrim($num, '-');
        if (preg_match('/^(.*)[.,](\d{2})$/', $num, $m)) {
            return preg_replace('/\D/', '', $m[1]).'.'.$m[2];
        }

        return preg_replace('/\D/', '', $num).'.00';
    }
}
