<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandSettings;
use App\Models\Account;
use App\Models\Payee;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class BrandSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_api_publik_memberi_identitas_bawaan(): void
    {
        $this->getJson('/api/app-config')
            ->assertOk()
            ->assertExactJson(['data' => ['name' => 'Lumina', 'company' => 'Lumina Digital', 'logo' => null]]);

        $this->get('/api/app-config/logo')->assertNotFound();
    }

    public function test_admin_mengganti_nama_dan_logo(): void
    {
        $this->actingAs(User::where('is_admin', true)->firstOrFail());

        Livewire::test(BrandSettings::class)
            ->assertSchemaStateSet(['app_name' => 'Lumina'], 'form')
            ->fillForm([
                'app_name' => 'Nusa Bank',
                'company_name' => 'PT Nusa Digital',
                'logo_path' => UploadedFile::fake()->image('logo.png', 512, 512),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $logo = Setting::get('logo_path');
        Storage::disk('local')->assertExists($logo);

        $res = $this->getJson('/api/app-config')->assertOk();
        $this->assertSame('Nusa Bank', $res->json('data.name'));
        $this->assertSame('PT Nusa Digital', $res->json('data.company'));
        $this->assertStringStartsWith('app-config/logo?v=', $res->json('data.logo'));
        $this->get('/api/app-config/logo')->assertOk()->assertHeader('Content-Type', 'image/png');

        // Penerima "sesama bank" ikut berganti nama.
        $this->assertSame(0, Payee::where('bank', 'Lumina')->count());
        $this->assertGreaterThan(0, Payee::where('bank', 'Nusa Bank')->count());

        // Logo dihapus -> file lama ikut dibersihkan, kembali ke logo bawaan.
        Livewire::test(BrandSettings::class)
            ->fillForm(['logo_path' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        Storage::disk('local')->assertMissing($logo);
        $this->getJson('/api/app-config')->assertJsonPath('data.logo', null);
    }

    public function test_nama_wajib_diisi(): void
    {
        $this->actingAs(User::where('is_admin', true)->firstOrFail());

        Livewire::test(BrandSettings::class)
            ->fillForm(['app_name' => ''])
            ->call('save')
            ->assertHasFormErrors(['app_name' => 'required']);
    }

    public function test_transfer_internal_memakai_nama_aplikasi(): void
    {
        Setting::put(['app_name' => 'Nusa Bank']);
        $andra = User::where('username', 'lumina')->firstOrFail();
        $bunga = Account::where('number', '8820049912')->firstOrFail();
        $before = (float) $bunga->balance;
        Sanctum::actingAs($andra);

        $this->postJson('/api/transfers', [
            'payee_name' => 'Bunga', 'bank' => 'Nusa Bank', 'number' => '8820049912',
            'amount' => 10000, 'pin' => '123456',
        ])->assertCreated();

        $this->assertEqualsWithDelta($before + 10000, (float) $bunga->fresh()->balance, 0.001);
    }

    public function test_bukan_admin_tidak_bisa_membuka_halaman(): void
    {
        $this->actingAs(User::where('username', 'lumina')->firstOrFail());
        $this->get('/admin/identitas')->assertForbidden();
    }
}
