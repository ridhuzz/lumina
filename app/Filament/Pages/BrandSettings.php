<?php

namespace App\Filament\Pages;

use App\Models\Payee;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Identitas aplikasi: nama, nama perusahaan, dan logo.
 * Dipakai aplikasi mobile (GET /api/app-config), panel admin, dan e-statement PDF.
 *
 * @property-read Schema $form
 */
class BrandSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Identitas Aplikasi';

    protected static ?string $title = 'Identitas Aplikasi';

    protected static ?string $slug = 'identitas';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'app_name' => Setting::appName(),
            'company_name' => Setting::get('company_name'),
            'logo_path' => Setting::logoPath(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Merek')
                    ->description('Tampil di aplikasi mobile (halaman login, beranda, judul), panel admin, dan e-statement. '
                        .'Aplikasi mengambil identitas ini dari server yang dituju, jadi tiap perusahaan cukup mengatur servernya sendiri.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('app_name')
                            ->label('Nama aplikasi')
                            ->helperText('Mis. "Lumina". Juga dipakai sebagai nama bank internal untuk transfer sesama.')
                            ->required()
                            ->maxLength(30),
                        TextInput::make('company_name')
                            ->label('Nama perusahaan (opsional)')
                            ->helperText('Mis. "PT Lumina Digital". Tampil di halaman Akun dan kaki e-statement.')
                            ->maxLength(80),
                        FileUpload::make('logo_path')
                            ->label('Logo (opsional)')
                            ->image()
                            ->disk(Setting::DISK)
                            ->directory(Setting::LOGO_DIRECTORY)
                            ->visibility('private')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(Setting::LOGO_MAX_KB)
                            ->helperText('PNG/JPG/WEBP persegi, maks. 1 MB, disarankan 512 × 512 px. '
                                .'Kosongkan untuk memakai logo bawaan.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $oldName = Setting::appName();
        $oldLogo = Setting::get('logo_path');
        $newName = trim($data['app_name']);

        DB::transaction(function () use ($data, $oldName, $newName) {
            Setting::put([
                'app_name' => $newName,
                'company_name' => trim((string) ($data['company_name'] ?? '')) ?: null,
                'logo_path' => $data['logo_path'] ?: null,
            ]);

            // Penerima "sesama bank" ikut berganti nama agar transfer internal tetap dikenali.
            if ($newName !== $oldName) {
                Payee::query()->where('bank', $oldName)->update(['bank' => $newName]);
            }
        });

        if ($oldLogo && $oldLogo !== ($data['logo_path'] ?? null)) {
            Storage::disk(Setting::DISK)->delete($oldLogo);
        }

        Notification::make()->success()->title('Identitas aplikasi tersimpan')->send();
    }
}
