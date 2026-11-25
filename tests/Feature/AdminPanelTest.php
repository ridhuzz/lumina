<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\AccountsRelationManager;
use App\Filament\Resources\Users\RelationManagers\StatementsRelationManager;
use App\Filament\Resources\Users\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Account;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('is_admin', true)->firstOrFail();
    }

    public function test_admin_bisa_masuk_panel_dan_nasabah_ditolak(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk();
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();

        $nasabah = User::where('username', 'lumina')->firstOrFail();

        // Detail nasabah: header + tab Rekening, Mutasi, e-Statement, Penerima.
        $this->actingAs($this->admin)->get("/admin/users/{$nasabah->id}")
            ->assertOk()
            ->assertSeeInOrder(['Andra Saputra', '8820031475', 'Rekening', 'Mutasi', 'e-Statement', 'Penerima tersimpan']);

        // Menu terpisah sudah dilebur ke halaman nasabah.
        $this->actingAs($this->admin)->get('/admin/accounts')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/transactions')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/statements')->assertNotFound();

        $this->actingAs($nasabah)->get('/admin')->assertForbidden();
    }

    private function rekeningTab(Account $account): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(AccountsRelationManager::class, [
            'ownerRecord' => $account->user,
            'pageClass' => ViewUser::class,
        ]);
    }

    public function test_daftar_nasabah_tidak_menampilkan_admin(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords(User::customers()->get())
            ->assertCanNotSeeTableRecords([$this->admin]);
    }

    public function test_tambah_nasabah_membuat_rekening_dan_setoran_awal(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Citra Maharani',
                'username' => 'Citra.M',
                'email' => 'citra@lumina.test',
                'pin' => '246810',
                'account_number' => '8820555001',
                'account_type' => 'Tabungan Pelajar',
                'initial_deposit' => 500000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'citra@lumina.test')->firstOrFail();
        $this->assertSame('citra.m', $user->username); // dipaksa huruf kecil
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('246810', $user->pin)); // PIN ter-hash

        $account = $user->account;
        $this->assertSame('8820555001', $account->number);
        $this->assertSame('500000.00', $account->balance);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'title' => 'Setoran Awal',
            'direction' => 'in',
            'amount' => '500000.00',
        ]);

        // Nasabah baru langsung bisa login di aplikasi.
        $this->postJson('/api/auth/login', ['username' => 'citra.m', 'pin' => '246810'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_validasi_form_nasabah(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'X',
                'username' => 'lumina',          // sudah dipakai
                'email' => 'andra@lumina.test',  // sudah dipakai
                'pin' => '12ab',                 // bukan 6 digit
                'account_number' => '8820031475',// sudah dipakai
                'account_type' => 'Tabungan Utama',
            ])
            ->call('create')
            ->assertHasFormErrors(['username', 'email', 'pin', 'account_number']);
    }

    public function test_edit_tanpa_isi_pin_tidak_mengubah_pin(): void
    {
        $this->actingAs($this->admin);
        $user = User::where('username', 'lumina')->firstOrFail();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Andra S.', 'pin' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Andra S.', $user->name);
        $this->assertTrue(Hash::check('123456', $user->pin));
    }

    public function test_setor_dan_tarik_mengubah_saldo_dan_mencatat_mutasi(): void
    {
        $this->actingAs($this->admin);
        $account = Account::where('number', '8820031475')->firstOrFail(); // saldo 12.750.000

        $this->rekeningTab($account)
            ->callAction(TestAction::make('adjustBalance')->table($account), data: [
                'direction' => 'in', 'amount' => 250000, 'note' => 'Setor di teller',
            ])
            ->assertHasNoFormErrors()
            ->assertDispatched('nasabah-updated'); // saldo di header ikut diperbarui

        $this->assertSame('13000000.00', $account->fresh()->balance);

        $this->rekeningTab($account)
            ->callAction(TestAction::make('adjustBalance')->table($account), data: [
                'direction' => 'out', 'amount' => 1000000,
            ]);

        $this->assertSame('12000000.00', $account->fresh()->balance);
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'title' => 'Setoran Tunai', 'amount' => '250000.00', 'direction' => 'in']);
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'title' => 'Penarikan Tunai', 'amount' => '1000000.00', 'direction' => 'out']);
    }

    public function test_tarik_melebihi_saldo_ditolak(): void
    {
        $this->actingAs($this->admin);
        $account = Account::where('number', '8820049912')->firstOrFail(); // saldo 3.200.000
        $before = Account::find($account->id)->transactions()->count();

        $this->rekeningTab($account)
            ->callAction(TestAction::make('adjustBalance')->table($account), data: [
                'direction' => 'out', 'amount' => 5000000,
            ])
            ->assertNotified('Saldo tidak cukup untuk penarikan ini.');

        $this->assertSame('3200000.00', $account->fresh()->balance);
        $this->assertSame($before, $account->transactions()->count());
    }

    public function test_mutasi_hanya_baca(): void
    {
        $this->actingAs($this->admin);

        $andra = Account::where('number', '8820031475')->firstOrFail();
        $bunga = Account::where('number', '8820049912')->firstOrFail();
        $tab = fn (Account $a) => Livewire::test(TransactionsRelationManager::class, [
            'ownerRecord' => $a->user, 'pageClass' => ViewUser::class,
        ]);

        // Tiap nasabah hanya melihat mutasinya sendiri (tidak bercampur).
        $tab($andra)->assertOk()
            ->assertCountTableRecords($andra->transactions()->count());
        $tab($bunga)->assertCountTableRecords(0);

        // Mutasi hanya baca; rekening tidak bisa ditambah/dihapus dari panel.
        $this->assertTrue($tab($andra)->instance()->isReadOnly());
        $this->rekeningTab($andra)
            ->assertTableActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('delete');
    }

    public function test_setiap_tab_hanya_berisi_data_nasabah_itu(): void
    {
        $this->actingAs($this->admin);
        $andra = Account::where('number', '8820031475')->firstOrFail();
        $bunga = Account::where('number', '8820049912')->firstOrFail();

        $this->rekeningTab($bunga)
            ->assertCanSeeTableRecords([$bunga])
            ->assertCanNotSeeTableRecords([$andra]);

        Livewire::test(StatementsRelationManager::class, ['ownerRecord' => $bunga->user, 'pageClass' => ViewUser::class])
            ->assertCountTableRecords(0);
        Livewire::test(StatementsRelationManager::class, ['ownerRecord' => $andra->user, 'pageClass' => ViewUser::class])
            ->assertCountTableRecords(3);
    }

    public function test_admin_tidak_bisa_login_lewat_api_aplikasi(): void
    {
        $this->postJson('/api/auth/login', ['username' => 'admin', 'pin' => '123456'])
            ->assertUnauthorized();
    }
}
