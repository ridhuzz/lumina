<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Endpoint tambahan aplikasi Kiki & biaya transfer antarbank. */
class HomeWidgetsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function asCustomer(): void
    {
        $this->actingAs(User::where('username', 'lumina')->firstOrFail(), 'sanctum');
    }

    private function balance(string $number): string
    {
        return (string) Account::where('number', $number)->value('balance');
    }

    public function test_endpoint_beranda_butuh_login(): void
    {
        $this->getJson('/api/menu')->assertUnauthorized();
        $this->getJson('/api/cashflow')->assertUnauthorized();
    }

    public function test_menu_kurs_dan_catatan_keuangan(): void
    {
        $this->asCustomer();

        $this->getJson('/api/menu')->assertOk()
            ->assertJsonCount(12)
            ->assertJsonPath('0.id', 'transfer')
            ->assertJsonFragment(['id' => 'transfer', 'active' => true])
            ->assertJsonFragment(['id' => 'payment', 'badge' => 'BARU', 'active' => false]);

        $this->getJson('/api/rates')->assertOk()
            ->assertJsonCount(4, 'items')
            ->assertJsonPath('items.0.code', 'USD');

        // Seeder: bulan ini ada Gaji + Refund (masuk) dan Transfer, QRIS, Top Up (keluar).
        $cashflow = $this->getJson('/api/cashflow')->assertOk()->json();
        $this->assertSame(9625000, collect($cashflow['income'])->sum('amount'));
        $this->assertSame(350000 + 48000 + 200000, collect($cashflow['spending'])->sum('amount'));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{6}$/', $cashflow['spending'][0]['color']);
    }

    public function test_menu_mengikuti_modul_dari_admin(): void
    {
        $this->asCustomer();

        Module::where('code', 'invest')->update(['is_active' => true, 'name' => 'Investasi Online', 'sort_order' => 0]);
        Module::create(['code' => 'promo', 'name' => 'Promo', 'icon' => 'more', 'sort_order' => 99]);

        $menu = $this->getJson('/api/menu')->assertOk()->json();
        $this->assertCount(13, $menu);
        $this->assertSame(['invest', 'Investasi Online', true], [$menu[0]['id'], $menu[0]['label'], $menu[0]['active']]);
        // Modul baru default nonaktif: tampil, tidak bisa dibuka.
        $this->assertSame(['promo', false], [end($menu)['id'], end($menu)['active']]);
    }

    public function test_admin_bisa_membuka_halaman_modul(): void
    {
        $this->actingAs(User::where('is_admin', true)->firstOrFail())
            ->get('/admin/modules')
            ->assertOk()
            ->assertSee('Bayar &amp; Top Up', false);
    }

    public function test_cek_penerima(): void
    {
        $this->asCustomer();

        // Sesama Lumina: nama dari database.
        $this->postJson('/api/transfer/inquiry', ['bank' => 'Lumina', 'number' => '8820049912'])
            ->assertOk()->assertJson(['beneficiary_name' => 'BUNGA LESTARI']);

        // Rekening sesama Lumina yang tidak ada.
        $this->postJson('/api/transfer/inquiry', ['bank' => 'Lumina', 'number' => '1111111111'])
            ->assertNotFound();

        // Rekening sendiri ditolak.
        $this->postJson('/api/transfer/inquiry', ['bank' => 'Lumina', 'number' => '8820031475'])
            ->assertStatus(422);

        // Bank lain: dari daftar penerima tersimpan.
        $this->postJson('/api/transfer/inquiry', ['bank' => 'Bank Harmoni', 'number' => '0071558820'])
            ->assertOk()->assertJson(['beneficiary_name' => 'RIZKY HIDAYAT']);
    }

    public function test_transfer_antarbank_dipotong_biaya(): void
    {
        $this->asCustomer();
        $before = $this->balance('8820031475');

        $this->postJson('/api/transfers', [
            'payee_name' => 'RIZKY HIDAYAT',
            'bank' => 'Bank Harmoni',
            'number' => '0071558820',
            'amount' => 100000,
            'pin' => '123456',
        ])->assertCreated()->assertJsonPath('fee', 2500);

        $this->assertSame(bcsub($before, '102500', 2), $this->balance('8820031475'));
        $this->assertDatabaseHas('transactions', ['title' => 'Biaya Transfer Antarbank', 'amount' => '2500.00']);
    }

    public function test_transfer_sesama_bank_gratis_dan_masuk_ke_penerima(): void
    {
        $this->asCustomer();
        $sender = $this->balance('8820031475');
        $receiver = $this->balance('8820049912');

        $this->postJson('/api/transfers', [
            'payee_name' => 'BUNGA LESTARI',
            'bank' => 'Lumina',
            'number' => '8820049912',
            'amount' => 50000,
            'pin' => '123456',
        ])->assertCreated()->assertJsonPath('fee', 0);

        $this->assertSame(bcsub($sender, '50000', 2), $this->balance('8820031475'));
        $this->assertSame(bcadd($receiver, '50000', 2), $this->balance('8820049912'));
    }

    public function test_saldo_tidak_cukup_termasuk_biaya(): void
    {
        $this->asCustomer();
        $balance = (int) $this->balance('8820031475');

        // Pas sebesar saldo: nominal cukup, tapi biaya Rp2.500 membuat saldo kurang.
        $this->postJson('/api/transfers', [
            'payee_name' => 'RIZKY HIDAYAT',
            'bank' => 'Bank Harmoni',
            'number' => '0071558820',
            'amount' => $balance,
            'pin' => '123456',
        ], ['Accept-Language' => 'id'])->assertStatus(422)->assertJson(['message' => 'Saldo tidak cukup.']);
    }
}
