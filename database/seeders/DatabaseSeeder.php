<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Statement;
use App\Models\User;
use App\Support\StatementPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Data demo, sama dengan MockApi di aplikasi Flutter.
     * Login demo: user "lumina", PIN 123456.
     */
    public function run(): void
    {
        // Admin backoffice: http://localhost/lumina/admin
        User::create([
            'name' => 'Admin Lumina',
            'email' => 'admin@lumina.test',
            'password' => 'admin12345',
            'is_admin' => true,
        ]);

        $andra = $this->makeCustomer(
            name: 'Andra Saputra',
            username: 'lumina',
            email: 'andra@lumina.test',
            pin: '123456',
            number: '8820031475',
            balance: '12750000.00',
        );

        // Nasabah kedua: tujuan transfer sesama Lumina (saldonya ikut bertambah).
        $this->makeCustomer(
            name: 'Bunga Lestari',
            username: 'bunga',
            email: 'bunga@lumina.test',
            pin: '654321',
            number: '8820049912',
            balance: '3200000.00',
        );

        $andra->payees()->createMany([
            ['name' => 'Bunga Lestari', 'bank' => 'Lumina', 'number' => '8820049912'],
            ['name' => 'Rizky Hidayat', 'bank' => 'Bank Harmoni', 'number' => '0071558820'],
            ['name' => 'Toko Sembada', 'bank' => 'Bank Sentosa', 'number' => '4455112233'],
            ['name' => 'Dewi Anjani', 'bank' => 'Lumina', 'number' => '8820077310'],
        ]);

        $account = $andra->account;
        $history = [
            ['Gaji Bulanan', 'Pemasukan', '9500000', 'in', 'Payroll PT Cahaya', now()->subDays(2)],
            ['Bunga Lestari', 'Transfer', '350000', 'out', 'Patungan kado', now()->subDays(2)->addHour()],
            ['Kopi Senja', 'QRIS', '48000', 'out', null, now()->subDay()],
            ['Top Up e-Wallet', 'Top Up', '200000', 'out', null, now()->subDay()->addHours(3)],
            ['Refund Marketplace', 'Pemasukan', '125000', 'in', null, now()->subHours(5)],
        ];

        foreach ($history as $i => [$title, $category, $amount, $direction, $note, $at]) {
            $account->transactions()->create([
                'ref' => 'TRX-'.(1001 + $i),
                'title' => $title,
                'category' => $category,
                'amount' => $amount,
                'direction' => $direction,
                'note' => $note,
                'occurred_at' => $at,
            ]);
        }

        $this->seedStatements($account);

        $this->call(BannerSeeder::class);
    }

    /**
     * Mutasi 3 bulan sebelumnya + e-statement PDF contoh untuk tiap bulan itu.
     * (Di dunia nyata PDF diunggah admin lewat panel, menu e-Statement.)
     */
    private function seedStatements(Account $account): void
    {
        $ref = 2001;
        foreach ([3, 2, 1] as $monthsAgo) {
            $base = now()->startOfMonth()->subMonthsNoOverflow($monthsAgo);
            $rows = [
                // Net per bulan sedikit positif agar saldo historis tetap wajar (tidak minus).
                ['Gaji Bulanan', 'Pemasukan', '9500000', 'in', 'Payroll PT Cahaya', 1],
                ['Sewa Kos', 'Transfer', '2500000', 'out', 'Kos Melati', 3],
                ['Listrik Prabayar', 'Tagihan', '250000', 'out', 'Token PLN', 4],
                ['Kopi Senja', 'QRIS', '52000', 'out', null, 9],
                ['Cicilan Kendaraan', 'Tagihan', '1250000', 'out', null, 10],
                ['Tarik Tunai ATM', 'Tarik Tunai', '1000000', 'out', null, 12],
                ['Bunga Lestari', 'Transfer', '400000', 'out', 'Arisan', 15],
                ['Belanja Online', 'Pembayaran', '870000', 'out', null, 18],
                ['Belanja Bulanan', 'QRIS', '1350000', 'out', null, 20],
                ['Premi Asuransi', 'Tagihan', '450000', 'out', null, 22],
                ['Top Up e-Wallet', 'Top Up', '300000', 'out', null, 26],
                ['Reksa Dana Pasar Uang', 'Investasi', '500000', 'out', null, 28],
            ];

            foreach ($rows as [$title, $category, $amount, $direction, $note, $day]) {
                $account->transactions()->create([
                    'ref' => 'TRX-'.$ref++,
                    'title' => $title,
                    'category' => $category,
                    'amount' => $amount,
                    'direction' => $direction,
                    'note' => $note,
                    'occurred_at' => $base->copy()->setDay($day)->setTime(10, 15),
                ]);
            }
        }

        foreach ([3, 2, 1] as $monthsAgo) {
            $p = now()->startOfMonth()->subMonthsNoOverflow($monthsAgo);
            $path = sprintf('%s/%s-%04d-%02d.pdf', Statement::DIRECTORY, $account->number, $p->year, $p->month);
            Storage::disk(Statement::DISK)->put($path, StatementPdf::make($account, $p->year, $p->month));

            Statement::create([
                'account_id' => $account->id,
                'year' => $p->year,
                'month' => $p->month,
                'file_path' => $path,
                'source' => 'mutasi',
                'original_name' => basename($path),
            ]);
        }
    }

    private function makeCustomer(
        string $name,
        string $username,
        string $email,
        string $pin,
        string $number,
        string $balance,
    ): User {
        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Str::random(32), // login aplikasi memakai PIN, bukan password
            'pin' => $pin,
        ]);

        Account::create([
            'user_id' => $user->id,
            'number' => $number,
            'type' => 'Tabungan Utama',
            'balance' => $balance,
        ]);

        return $user;
    }
}
