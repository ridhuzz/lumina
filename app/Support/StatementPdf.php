<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Setting;
use App\Models\Statement;
use Illuminate\Support\Carbon;

/**
 * Membuat PDF e-statement (rekening koran) dari mutasi di database.
 * Dipakai saat admin memilih sumber "Buat dari mutasi", dan oleh seeder.
 * PDF ditulis manual (A4, font standar Helvetica, bisa banyak halaman)
 * supaya tidak perlu library tambahan.
 */
class StatementPdf
{
    /** @var list<list<string>> perintah gambar per halaman */
    private array $pages = [];

    private float $y = 0;

    private const MUTED = [0.4, 0.45, 0.5];

    private const BLUE = [0.043, 0.290, 0.561];

    private const WHITE = [1, 1, 1];

    /** Batas bawah area tabel sebelum pindah halaman. */
    private const BOTTOM = 80;

    public static function make(Account $account, int $year, int $month): string
    {
        return (new self)->build($account, $year, $month);
    }

    /** Jumlah transaksi rekening pada periode tsb (untuk info di form admin). */
    public static function countTransactions(Account $account, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return $account->transactions()->whereBetween('occurred_at', [$start, $start->copy()->endOfMonth()])->count();
    }

    private function build(Account $account, int $year, int $month): string
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $rows = $account->transactions()
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('occurred_at')->orderBy('id')
            ->get();

        // Saldo akhir periode = saldo sekarang dikurangi efek mutasi setelah periode.
        $after = $account->transactions()->where('occurred_at', '>', $end)->get();
        $closing = (float) $account->balance - $after->sum(fn ($t) => $this->signed($t));
        $opening = $closing - $rows->sum(fn ($t) => $this->signed($t));

        $period = Statement::months()[$month].' '.$year;

        // ---- Halaman 1: kop, data rekening, ringkasan ----
        $this->newPage($period);

        $y = 730;
        foreach ([
            'Nama' => $account->user->name,
            'No. rekening' => $account->number,
            'Jenis rekening' => $account->type,
            'Tanggal cetak' => now()->format('d/m/Y'),
        ] as $label => $value) {
            $this->text(40, $y, $label, 10, color: self::MUTED);
            $this->text(150, $y, ': '.$value, 10, bold: $label === 'Nama');
            $y -= 16;
        }

        $y -= 10;
        $in = $rows->where('direction', 'in')->sum('amount');
        $out = $rows->where('direction', 'out')->sum('amount');
        $this->rect(40, $y - 46, 515, 54, [0.93, 0.96, 1.0]);
        $x = 52;
        foreach (['Saldo awal' => $opening, 'Total masuk' => $in, 'Total keluar' => $out, 'Saldo akhir' => $closing] as $label => $value) {
            $this->text($x, $y - 12, $label, 9, color: self::MUTED);
            $this->text($x, $y - 30, Rupiah::format($value), 11, bold: true);
            $x += 128;
        }

        // ---- Tabel mutasi (bisa lanjut ke halaman berikutnya) ----
        $this->y = $y - 80;
        $this->tableHeader();

        $running = $opening;
        $this->y -= 22;
        $this->text(48, $this->y, $start->format('d/m'), 9);
        $this->text(110, $this->y, 'SALDO AWAL', 9, bold: true);
        $this->text(547, $this->y, Rupiah::format($running), 9, right: true);

        foreach ($rows as $t) {
            if ($this->y - 24 < self::BOTTOM) {
                $this->newPage($period);
                $this->y = 730;
                $this->tableHeader();
            }
            $this->y -= 18;
            $running += $this->signed($t);
            $desc = $t->title.($t->note ? ' - '.$t->note : '');
            $this->text(48, $this->y, $t->occurred_at->timezone(config('app.timezone'))->format('d/m'), 9);
            $this->text(110, $this->y, mb_strimwidth($desc, 0, 44, '...'), 9);
            $this->text(110, $this->y - 9, $t->category.' · '.$t->ref, 7, color: [0.5, 0.55, 0.6]);
            $this->text(400, $this->y, ($t->direction === 'in' ? '+ ' : '- ').Rupiah::format($t->amount), 9,
                color: $t->direction === 'in' ? [0.05, 0.55, 0.3] : [0.75, 0.15, 0.15], right: true);
            $this->text(547, $this->y, Rupiah::format($running), 9, right: true);
            $this->y -= 6;
        }

        if ($rows->isEmpty()) {
            $this->y -= 18;
            $this->text(110, $this->y, 'Tidak ada transaksi pada periode ini.', 9, color: self::MUTED);
        }

        return $this->render();
    }

    private function newPage(string $period): void
    {
        $this->pages[] = [];

        $this->rect(0, 762, 595, 80, self::BLUE);
        $this->text(40, 806, mb_strtoupper(Setting::appName()), 22, bold: true, color: self::WHITE);
        $this->text(40, 784, 'e-Statement / Rekening Koran', 11, color: self::WHITE);
        $this->text(555, 806, 'Periode', 9, color: self::WHITE, right: true);
        $this->text(555, 788, $period, 13, bold: true, color: self::WHITE, right: true);
    }

    private function tableHeader(): void
    {
        $y = $this->y;
        $this->rect(40, $y - 6, 515, 20, self::BLUE);
        $this->text(48, $y, 'Tanggal', 9, bold: true, color: self::WHITE);
        $this->text(110, $y, 'Keterangan', 9, bold: true, color: self::WHITE);
        $this->text(400, $y, 'Mutasi', 9, bold: true, color: self::WHITE, right: true);
        $this->text(547, $y, 'Saldo', 9, bold: true, color: self::WHITE, right: true);
    }

    private function signed($t): float
    {
        return $t->direction === 'in' ? (float) $t->amount : -(float) $t->amount;
    }

    private function op(string $op): void
    {
        $this->pages[array_key_last($this->pages)][] = $op;
    }

    private function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->op(sprintf('%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f', ...$rgb, ...[$x, $y, $w, $h]));
    }

    private function text(float $x, float $y, string $s, float $size, bool $bold = false, array $color = [0.1, 0.12, 0.15], bool $right = false): void
    {
        $s = iconv('UTF-8', 'CP1252//TRANSLIT', $s) ?: $s;
        if ($right) {
            // Perkiraan lebar Helvetica untuk rata kanan.
            $x -= strlen($s) * $size * ($bold ? 0.56 : 0.52);
        }
        $esc = strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $this->op(sprintf('%.3f %.3f %.3f rg BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET',
            ...$color, ...[$bold ? 'F2' : 'F1', $size, $x, $y, $esc]));
    }

    private function render(): string
    {
        $total = count($this->pages);

        // Kaki halaman: keterangan + nomor halaman.
        foreach (array_keys($this->pages) as $i) {
            $this->pages[$i][] = '0.800 0.830 0.870 rg 40.00 60.00 515.00 0.60 re f';
            $this->pages[$i][] = $this->footerText(40, 'Dibuat otomatis dari data mutasi '.Setting::companyName().' (proyek belajar), bukan dokumen bank resmi.');
            $this->pages[$i][] = $this->footerText(555 - 50, 'Hal. '.($i + 1).' / '.$total);
        }

        // Objek: 1 katalog, 2 daftar halaman, 3-4 font, lalu (halaman, konten) per halaman.
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => null,
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($this->pages as $ops) {
            $pageId = count($objects) + 1;
            $contentId = $pageId + 1;
            $content = implode("\n", $ops);
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
            $kids[] = "{$pageId} 0 R";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$total.' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function footerText(float $x, string $s): string
    {
        return sprintf('0.500 0.550 0.600 rg BT /F1 8.0 Tf %.2f 46.00 Td (%s) Tj ET', $x, $s);
    }
}
