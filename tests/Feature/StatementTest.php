<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\StatementsRelationManager;
use App\Models\Account;
use App\Models\Statement;
use App\Models\User;
use App\Support\StatementPdf;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $andra;   // punya 3 e-statement dari seeder

    private Account $bunga;   // belum punya

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('is_admin', true)->firstOrFail();
        $this->andra = Account::where('number', '8820031475')->firstOrFail();
        $this->bunga = Account::where('number', '8820049912')->firstOrFail();
    }

    /** Masuk sebagai nasabah di API (setara memakai token hasil login). */
    private function asCustomer(string $username): void
    {
        Sanctum::actingAs(User::where('username', $username)->firstOrFail());
    }

    /** Tab e-Statement di halaman detail nasabah pemilik rekening. */
    private function tab(Account $account): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(StatementsRelationManager::class, [
            'ownerRecord' => $account->user,
            'pageClass' => ViewUser::class,
        ]);
    }

    private function pdf(string $name = 'estatement.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, StatementPdf::make($this->bunga, 2026, 9));
    }

    public function test_seeder_membuat_pdf_contoh_yang_valid(): void
    {
        $this->assertSame(3, $this->andra->statements()->count());

        foreach ($this->andra->statements as $s) {
            $bytes = Storage::disk('local')->get($s->file_path);
            $this->assertStringStartsWith('%PDF-1.4', $bytes);
            $this->assertStringEndsWith("%%EOF\n", $bytes);
            $this->assertSame(strlen($bytes), $s->size);
        }
    }

    public function test_api_daftar_hanya_milik_sendiri_terbaru_dulu(): void
    {
        $this->asCustomer('lumina');

        $res = $this->getJson('/api/statements')->assertOk();
        $this->assertCount(3, $res->json('data'));
        $first = $res->json('data.0');
        $last = now()->subMonthNoOverflow();
        $this->assertSame([$last->year, $last->month], [$first['year'], $first['month']]);
        $this->assertSame(Statement::months()[$last->month].' '.$last->year, $first['period']);

        $this->asCustomer('bunga');
        $this
            ->getJson('/api/statements')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_api_unduh_pdf_dan_tolak_milik_orang_lain(): void
    {
        $statement = $this->andra->statements()->firstOrFail();

        $this->asCustomer('lumina');
        $this
            ->get("/api/statements/{$statement->id}/file")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->asCustomer('bunga');
        $this
            ->getJson("/api/statements/{$statement->id}/file")
            ->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/statements', ['Authorization' => ''])->assertUnauthorized();
    }

    public function test_admin_unggah_file_pdf(): void
    {
        $this->actingAs($this->admin);

        $this->tab($this->bunga)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => 10,
                'year' => 2026,
                'source' => 'upload',
                'pdf' => $this->pdf('Okt 2026.pdf'),
            ])
            ->assertHasNoFormErrors();

        $s = $this->bunga->statements()->firstOrFail();
        $this->assertSame([2026, 10, 'upload'], [$s->year, $s->month, $s->source]);
        $this->assertSame('Okt 2026.pdf', $s->original_name);
        $this->assertStringStartsWith('statements/', $s->file_path);
        Storage::disk('local')->assertExists($s->file_path);
        $this->assertGreaterThan(0, $s->size);

        // Langsung terlihat di aplikasi nasabah.
        $this->asCustomer('bunga');
        $this->getJson('/api/statements')->assertJsonPath('data.0.period', 'Oktober 2026');
    }

    public function test_admin_buat_dari_mutasi(): void
    {
        $this->actingAs($this->admin);
        $now = now();

        // Bulan berjalan berisi transaksi demo Andra (gaji, transfer, dll.).
        $this->tab($this->andra)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => $now->month,
                'year' => $now->year,
                'source' => 'mutasi',
            ])
            ->assertHasNoFormErrors();

        $s = $this->andra->statements()->where(['year' => $now->year, 'month' => $now->month])->firstOrFail();
        $this->assertSame('mutasi', $s->source);
        $this->assertSame(sprintf('eStatement-8820031475-%04d-%02d.pdf', $now->year, $now->month), $s->original_name);

        $bytes = Storage::disk('local')->get($s->file_path);
        $this->assertStringStartsWith('%PDF-1.4', $bytes);
        $this->assertStringContainsString('Gaji Bulanan', $bytes);      // isi dari mutasi
        $this->assertStringContainsString('Saldo akhir', $bytes);
        $this->assertSame(strlen($bytes), $s->size);
    }

    public function test_pdf_dari_mutasi_bisa_banyak_halaman(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->bunga->transactions()->create([
                'ref' => 'TRX-T'.$i, 'title' => 'Transaksi '.$i, 'category' => 'QRIS',
                'amount' => 1000, 'direction' => 'out', 'occurred_at' => '2026-09-10 10:00:00',
            ]);
        }

        $bytes = StatementPdf::make($this->bunga->load('user'), 2026, 9);

        $this->assertMatchesRegularExpression('#/Type /Pages /Kids \[[^\]]+\] /Count [2-9]#', $bytes);
        $this->assertStringContainsString('Transaksi 59', $bytes); // baris terakhir tidak terpotong
    }

    public function test_validasi(): void
    {
        $this->actingAs($this->admin);
        $last = now()->subMonthNoOverflow();
        $next = now()->addMonthNoOverflow();

        // Sumber "unggah" tanpa lampiran PDF.
        $this->tab($this->bunga)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => 9, 'year' => 2026, 'source' => 'upload',
            ])
            ->assertHasFormErrors(['pdf' => 'required']);

        // Periode yang sama untuk rekening yang sama sudah ada.
        $this->tab($this->andra)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => $last->month, 'year' => $last->year, 'source' => 'mutasi',
            ])
            ->assertHasFormErrors(['month' => 'unique']);

        // Periode masa depan.
        $this->tab($this->bunga)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => $next->month, 'year' => $next->year, 'source' => 'mutasi',
            ])
            ->assertHasFormErrors(['month']);

        // Bukan PDF.
        $this->tab($this->bunga)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => 9, 'year' => 2026, 'source' => 'upload',
                'pdf' => UploadedFile::fake()->create('foto.jpg', 50, 'image/jpeg'),
            ])
            ->assertHasFormErrors(['pdf']);

        $this->assertSame(0, $this->bunga->statements()->count());
    }

    public function test_ganti_ke_unggahan_lalu_perbarui_dari_mutasi_lalu_hapus(): void
    {
        $this->actingAs($this->admin);
        $s = $this->andra->statements()->firstOrFail(); // sumber: mutasi
        $path1 = $s->file_path;

        // Beralih ke unggahan: lampiran wajib.
        $this->tab($this->andra)
            ->callAction(TestAction::make('edit')->table($s), data: ['source' => 'upload'])
            ->assertHasFormErrors(['pdf' => 'required']);

        $this->tab($this->andra)
            ->callAction(TestAction::make('edit')->table($s), data: ['source' => 'upload', 'pdf' => $this->pdf('revisi.pdf')])
            ->assertHasNoFormErrors();

        $s->refresh();
        $path2 = $s->file_path;
        $this->assertSame(['upload', 'revisi.pdf'], [$s->source, $s->original_name]);
        Storage::disk('local')->assertMissing($path1);
        Storage::disk('local')->assertExists($path2);

        // Ubah unggahan tanpa lampiran baru: file tetap.
        $this->tab($this->andra)
            ->callAction(TestAction::make('edit')->table($s), data: [])
            ->assertHasNoFormErrors();
        $this->assertSame($path2, $s->fresh()->file_path);

        // Kembali ke mutasi: PDF dibuat ulang, unggahan lama dibuang.
        $this->tab($this->andra)
            ->callAction(TestAction::make('edit')->table($s), data: ['source' => 'mutasi'])
            ->assertHasNoFormErrors();
        $s->refresh();
        $this->assertSame('mutasi', $s->source);
        Storage::disk('local')->assertMissing($path2);
        Storage::disk('local')->assertExists($s->file_path);

        $this->tab($this->andra)
            ->callAction(TestAction::make('delete')->table($s));

        $this->assertModelMissing($s);
        Storage::disk('local')->assertMissing($s->file_path);
    }

    /** PDF e-statement Oktober milik Andra (12 mutasi) sebagai file unggahan. */
    private function andraOktoberPdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('okt.pdf', StatementPdf::make($this->andra, 2026, 10));
    }

    public function test_unggah_dengan_centang_mengisi_mutasi_tanpa_mengubah_saldo(): void
    {
        $this->actingAs($this->admin);
        $saldo = $this->bunga->balance;
        $this->assertSame(0, $this->bunga->transactions()->count());

        $this->tab($this->bunga)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => 10, 'year' => 2026, 'source' => 'upload',
                'pdf' => $this->andraOktoberPdf(), 'import_mutasi' => true,
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('12 mutasi ditambahkan dari PDF');

        $txns = $this->bunga->transactions()->orderBy('occurred_at')->get();
        $this->assertCount(12, $txns);
        $gaji = $txns->first();
        $this->assertSame(['Gaji Bulanan', 'Payroll PT Cahaya', 'Pemasukan', 'in', '9500000.00'],
            [$gaji->title, $gaji->note, $gaji->category, $gaji->direction, $gaji->amount]);
        $this->assertSame('2026-10-01', $gaji->occurred_at->timezone(config('app.timezone'))->toDateString());
        $this->assertSame('out', $txns->firstWhere('title', 'Sewa Kos')->direction);
        $this->assertSame($saldo, $this->bunga->fresh()->balance); // saldo tidak berubah

        // Ref TRX-20xx sudah dipakai rekening Andra -> dibuatkan ref baru (unik).
        $this->assertFalse($txns->contains('ref', 'TRX-2025'));

        // Mutasi baru langsung terlihat di aplikasi nasabah.
        $this->asCustomer('bunga');
        $this->getJson('/api/transactions')->assertJsonCount(12, 'data');
    }

    public function test_impor_ulang_tidak_menggandakan_mutasi(): void
    {
        $this->actingAs($this->admin);
        $data = ['month' => 10, 'year' => 2026, 'source' => 'upload', 'pdf' => $this->andraOktoberPdf(), 'import_mutasi' => true];

        $this->tab($this->bunga)->callAction(TestAction::make('create')->table(), data: $data);
        $s = $this->bunga->statements()->firstOrFail();

        // Ganti tanpa file baru, centang lagi: semua dilewati.
        $this->tab($this->bunga)
            ->callAction(TestAction::make('edit')->table($s), data: ['import_mutasi' => true])
            ->assertHasNoFormErrors()
            ->assertNotified('Tidak ada mutasi baru dari PDF');

        $this->assertSame(12, $this->bunga->transactions()->count());

        // PDF Andra ke rekening Andra sendiri: semua ref/isi sudah ada -> 0 baru.
        $before = $this->andra->transactions()->count();
        $s2 = $this->andra->statements()->where('month', 10)->firstOrFail();
        $this->tab($this->andra)
            ->callAction(TestAction::make('edit')->table($s2), data: ['source' => 'upload', 'pdf' => $this->andraOktoberPdf(), 'import_mutasi' => true])
            ->assertHasNoFormErrors();
        $this->assertSame($before, $this->andra->transactions()->count());
    }

    public function test_tanpa_centang_mutasi_tidak_diisi(): void
    {
        $this->actingAs($this->admin);

        $this->tab($this->bunga)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => 10, 'year' => 2026, 'source' => 'upload', 'pdf' => $this->andraOktoberPdf(),
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(1, $this->bunga->statements()->count());
        $this->assertSame(0, $this->bunga->transactions()->count());
    }

    public function test_pdf_tidak_terbaca_tetap_tersimpan_dengan_peringatan(): void
    {
        $this->actingAs($this->admin);

        $this->tab($this->bunga)
            ->callAction(TestAction::make('create')->table(), data: [
                'month' => 10, 'year' => 2026, 'source' => 'upload', 'import_mutasi' => true,
                'pdf' => UploadedFile::fake()->createWithContent('scan.pdf', "%PDF-1.4\nbukan isi pdf yang valid\n%%EOF\n"),
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('E-statement tersimpan, tetapi PDF tidak bisa dibaca');

        $this->assertSame(1, $this->bunga->statements()->count());
        $this->assertSame(0, $this->bunga->transactions()->count());
    }

    public function test_pdf_admin_hanya_untuk_admin(): void
    {
        $s = $this->andra->statements()->firstOrFail();

        $this->get(route('admin.statements.pdf', $s))->assertForbidden();
        $this->actingAs(User::where('username', 'lumina')->first())
            ->get(route('admin.statements.pdf', $s))->assertForbidden();
        $this->actingAs($this->admin)
            ->get(route('admin.statements.pdf', $s))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
