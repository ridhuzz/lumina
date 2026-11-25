<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Account;
use App\Models\Statement;
use App\Support\StatementPdf;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use App\Services\StatementImporter;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Throwable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * Tab "e-Statement" di detail nasabah: rekening koran PDF per periode (bulan/tahun).
 * Sumber PDF: dibuat otomatis dari mutasi, atau file PDF yang diunggah admin.
 * Nasabah melihatnya di aplikasi (menu e-Statement).
 */
class StatementsRelationManager extends RelationManager
{
    protected static string $relationship = 'statements';

    protected static ?string $title = 'e-Statement';

    protected static ?string $modelLabel = 'e-Statement';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedDocumentText;

    public function isReadOnly(): bool
    {
        return false;
    }

    /** Rekening nasabah yang sedang dibuka. */
    private function account(): Account
    {
        return $this->getOwnerRecord()->account()->with('user')->firstOrFail();
    }

    public function form(Schema $schema): Schema
    {
        $last = now()->subMonthNoOverflow();

        return $schema
            ->columns(2)
            ->components([
                Select::make('month')
                    ->label('Bulan')
                    ->options(Statement::months())
                    ->default($last->month)
                    ->required()
                    ->live()
                    ->unique(
                        table: Statement::class,
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->where('account_id', $this->account()->id)
                            ->where('year', $get('year')),
                    )
                    ->validationMessages([
                        'unique' => 'Nasabah ini sudah punya e-statement untuk periode tersebut. Pakai "Ganti / perbarui" pada data lama.',
                    ])
                    ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                        // Periode yang belum datang tidak boleh dibuat.
                        if ((int) $get('year') * 100 + (int) $value > (int) now()->format('Ym')) {
                            $fail('Periode tidak boleh di masa depan.');
                        }
                    }),
                Select::make('year')
                    ->label('Tahun')
                    ->options(collect(range(now()->year, now()->year - 5))->mapWithKeys(fn (int $y) => [$y => (string) $y])->all())
                    ->default($last->year)
                    ->required()
                    ->live(),
                ToggleButtons::make('source')
                    ->label('Sumber e-statement')
                    ->options(Statement::SOURCES)
                    ->icons(['mutasi' => Heroicon::OutlinedArrowsRightLeft, 'upload' => Heroicon::OutlinedArrowUpTray])
                    ->colors(['mutasi' => 'primary', 'upload' => 'gray'])
                    ->inline()
                    ->default('mutasi')
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                TextEntry::make('mutasi_info')
                    ->hiddenLabel()
                    ->state(fn (Get $get): string => $this->mutasiInfo($get))
                    ->visible(fn (Get $get): bool => $get('source') === 'mutasi')
                    ->columnSpanFull(),
                FileUpload::make('pdf')
                    ->label('File PDF')
                    ->disk(Statement::DISK)
                    ->directory(Statement::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(Statement::MAX_KB)
                    ->storeFileNamesIn('pdf_name')
                    ->visible(fn (Get $get): bool => $get('source') === 'upload')
                    // Wajib saat membuat baru, atau saat beralih dari "mutasi" ke "unggah".
                    ->required(fn (string $operation, ?Statement $record): bool => $operation === 'create' || $record?->source !== 'upload')
                    ->helperText(fn (string $operation, ?Statement $record): string => $operation === 'edit' && $record?->source === 'upload'
                        ? 'Kosongkan bila file tidak diganti. Hanya PDF, maks. 10 MB.'
                        : 'Wajib dilampirkan. Hanya PDF, maks. 10 MB.')
                    ->columnSpanFull(),
                Checkbox::make('import_mutasi')
                    ->label('Isi mutasi otomatis dari PDF ini')
                    ->helperText('Baris transaksi di PDF dibaca dan ditambahkan ke tab Mutasi (hanya periode yang dipilih). Mutasi yang sudah ada dilewati. Saldo rekening tidak berubah karena mutasi ini dianggap riwayat.')
                    ->default(false)
                    ->visible(fn (Get $get): bool => $get('source') === 'upload')
                    ->columnSpanFull(),
            ]);
    }

    /** Setelah simpan: bila dicentang, isi mutasi dari PDF unggahan lalu beri tahu hasilnya. */
    public static function importIfRequested(Statement $statement, array $data, ?RelationManager $livewire = null): void
    {
        if ($statement->source !== 'upload' || empty($data['import_mutasi'])) {
            return;
        }

        try {
            $r = app(StatementImporter::class)->import($statement);
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->title('E-statement tersimpan, tetapi PDF tidak bisa dibaca')
                ->body('Mutasi tidak diisi. Pastikan PDF berisi teks (bukan hasil scan/gambar).')
                ->warning()
                ->send();

            return;
        }

        $extra = array_filter([
            $r['duplicate'] ? "{$r['duplicate']} sudah ada (dilewati)" : null,
            $r['outside'] ? "{$r['outside']} di luar periode" : null,
            $r['unreadable'] ? "{$r['unreadable']} baris tidak terbaca" : null,
        ]);

        Notification::make()
            ->title($r['added'] > 0
                ? "{$r['added']} mutasi ditambahkan dari PDF"
                : 'Tidak ada mutasi baru dari PDF')
            ->body($extra ? implode(' · ', $extra) : null)
            ->{$r['added'] > 0 ? 'success' : 'warning'}()
            ->send();

        $livewire?->dispatch('nasabah-updated');
    }

    /** Keterangan di form saat sumber = mutasi. */
    private function mutasiInfo(Get $get): string
    {
        if (! $get('month') || ! $get('year')) {
            return 'PDF rekening koran akan dibuat otomatis dari mutasi saat disimpan. Pilih periode dulu.';
        }

        $n = StatementPdf::countTransactions($this->account(), (int) $get('year'), (int) $get('month'));

        return "PDF dibuat otomatis dari {$n} transaksi pada periode ini (saldo awal, mutasi, saldo akhir).";
    }

    /**
     * Simpan e-statement dari data form (buat baru atau ubah).
     * - mutasi : PDF dibuat ulang dari transaksi periode tsb.
     * - upload : PDF dari lampiran; saat ubah tanpa lampiran baru, file lama dipakai.
     * File lama dihapus otomatis oleh model bila file_path berganti.
     */
    public static function persist(Account $account, array $data, ?Statement $record = null): Statement
    {
        $attrs = [
            'account_id' => $account->id,
            'year' => (int) $data['year'],
            'month' => (int) $data['month'],
            'source' => $data['source'],
        ];

        if ($data['source'] === 'mutasi') {
            $path = Statement::DIRECTORY.'/'.Str::uuid().'.pdf';
            Storage::disk(Statement::DISK)->put($path, StatementPdf::make($account, $attrs['year'], $attrs['month']));
            $attrs['file_path'] = $path;
            $attrs['original_name'] = sprintf('eStatement-%s-%04d-%02d.pdf', $account->number, $attrs['year'], $attrs['month']);
        } elseif (filled($data['pdf'] ?? null)) {
            $attrs['file_path'] = $data['pdf'];
            $attrs['original_name'] = $data['pdf_name'] ?? basename($data['pdf']);
        }

        if ($record) {
            $record->update($attrs);

            return $record;
        }

        return Statement::create($attrs);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('year')->orderByDesc('month'))
            ->columns([
                TextColumn::make('period')
                    ->label('Periode')
                    ->state(fn (Statement $r): string => $r->periodLabel())
                    ->weight('bold'),
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'mutasi' ? 'Dari mutasi' : 'Unggahan PDF')
                    ->color(fn (string $state): string => $state === 'mutasi' ? 'primary' : 'gray'),
                TextColumn::make('original_name')
                    ->label('File')
                    ->icon(Heroicon::OutlinedDocument)
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('size')
                    ->label('Ukuran')
                    ->formatStateUsing(fn (int $state): string => $state >= 1048576
                        ? number_format($state / 1048576, 1, ',', '.').' MB'
                        : max(1, (int) round($state / 1024)).' KB')
                    ->alignEnd(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Buat e-Statement')
                    ->using(function (array $data): Statement {
                        $statement = self::persist($this->account(), $data);
                        self::importIfRequested($statement, $data, $this);

                        return $statement;
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Lihat PDF')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Statement $r): string => route('admin.statements.pdf', $r), shouldOpenInNewTab: true),
                EditAction::make()
                    ->label('Ganti / perbarui')
                    ->using(function (Statement $record, array $data): Statement {
                        $statement = self::persist($this->account(), $data, $record);
                        self::importIfRequested($statement, $data, $this);

                        return $statement;
                    }),
                DeleteAction::make(),
            ]);
    }
}
