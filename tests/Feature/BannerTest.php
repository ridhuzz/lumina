<?php

namespace Tests\Feature;

use App\Filament\Resources\Banners\Pages\ManageBanners;
use App\Models\Banner;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // 3 banner login + 2 banner beranda
    }

    public function test_api_publik_hanya_banner_yang_tayang_dan_urut(): void
    {
        $welcome = Banner::where('placement', 'welcome')->orderBy('sort_order')->get();
        $welcome[1]->update(['is_active' => false]);                          // nonaktif
        $welcome[2]->update(['ends_at' => now()->subDay()]);                  // periode lewat
        Banner::create([                                                       // belum mulai
            'placement' => 'welcome', 'title' => 'Nanti', 'image_path' => 'banners/x.png',
            'starts_at' => now()->addWeek(),
        ]);

        $res = $this->getJson('/api/banners?placement=welcome')->assertOk(); // tanpa token
        $this->assertSame([$welcome[0]->id], array_column($res->json('data'), 'id'));
        $this->assertStringStartsWith("banners/{$welcome[0]->id}/image?v=", $res->json('data.0.image'));

        $this->getJson('/api/banners?placement=home')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/banners?placement=lain')->assertUnprocessable();
    }

    public function test_gambar_hanya_untuk_banner_yang_tayang(): void
    {
        $b = Banner::where('placement', 'home')->firstOrFail();

        $this->get("/api/banners/{$b->id}/image")->assertOk()->assertHeader('Content-Type', 'image/png');

        $b->update(['is_active' => false]);
        $this->get("/api/banners/{$b->id}/image")->assertNotFound();
    }

    public function test_admin_tambah_ganti_dan_hapus_banner(): void
    {
        $admin = User::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(ManageBanners::class)
            ->callAction('create', data: [
                'placement' => 'home',
                'title' => 'Promo Akhir Tahun',
                'image_path' => UploadedFile::fake()->image('promo.png', 1200, 240),
                'link_url' => 'https://contoh.id/promo',
                'is_active' => true,
            ])
            ->assertHasNoFormErrors();

        $b = Banner::where('title', 'Promo Akhir Tahun')->firstOrFail();
        $this->assertSame(3, $b->sort_order); // di belakang 2 banner beranda yang ada
        Storage::disk('local')->assertExists($b->image_path);
        $this->getJson('/api/banners?placement=home')->assertJsonPath('data.2.title', 'Promo Akhir Tahun');

        // Validasi: bukan gambar, tautan tidak valid.
        Livewire::test(ManageBanners::class)
            ->callAction('create', data: [
                'placement' => 'welcome', 'title' => 'Salah',
                'image_path' => UploadedFile::fake()->create('dok.pdf', 10, 'application/pdf'),
                'link_url' => 'bukan url',
            ])
            ->assertHasFormErrors(['image_path', 'link_url']);

        $old = $b->image_path;
        Livewire::test(ManageBanners::class)
            ->mountAction(TestAction::make('edit')->table($b))
            ->fillForm(['image_path' => null])
            ->fillForm(['image_path' => [UploadedFile::fake()->image('baru.png', 1200, 240)]])
            ->callMountedAction()
            ->assertHasNoFormErrors();
        Storage::disk('local')->assertMissing($old);

        Livewire::test(ManageBanners::class)->callAction(TestAction::make('delete')->table($b->fresh()));
        $this->assertModelMissing($b);
    }

    public function test_pratinjau_admin_hanya_untuk_admin(): void
    {
        $b = Banner::firstOrFail();

        $this->get(route('admin.banners.image', $b))->assertForbidden();
        $this->actingAs(User::where('username', 'lumina')->first())
            ->get(route('admin.banners.image', $b))->assertForbidden();
        $this->actingAs(User::where('is_admin', true)->first())
            ->get(route('admin.banners.image', $b))->assertOk();
    }
}
