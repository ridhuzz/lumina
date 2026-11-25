<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\Account;
use App\Services\LedgerService;
use App\Support\Rupiah;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tab "Rekening" di detail nasabah. Saldo TIDAK bisa diedit langsung; perubahan
 * saldo hanya lewat aksi "Setor / Tarik" yang selalu mencatat mutasi (LedgerService).
 * Rekening dibuat bersama nasabah, jadi tidak ada tombol tambah/hapus.
 */
class AccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'accounts';

    protected static ?string $title = 'Rekening';

    protected static ?string $modelLabel = 'rekening';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCreditCard;

    public function isReadOnly(): bool
    {
        return false;
    }

    /** Form ubah: hanya jenis rekening. */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Jenis rekening')
                ->options(UserForm::ACCOUNT_TYPES)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->paginated(false)
            ->columns([
                TextColumn::make('number')->label('No. rekening')->copyable()->weight('bold'),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('updated_at')->label('Terakhir berubah')->since(),
            ])
            ->recordActions([
                self::adjustBalanceAction(),
                EditAction::make()->label('Ubah jenis'),
            ]);
    }

    /** Aksi Setor / Tarik: mengubah saldo + mencatat mutasi. */
    public static function adjustBalanceAction(): Action
    {
        return Action::make('adjustBalance')
            ->label('Setor / Tarik')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->modalHeading(fn (Account $record): string => "Setor / Tarik — {$record->number}")
            ->modalDescription(fn (Account $record): string => 'Saldo saat ini '.Rupiah::format($record->balance))
            ->modalSubmitActionLabel('Proses')
            ->schema([
                ToggleButtons::make('direction')
                    ->label('Jenis transaksi')
                    ->options(['in' => 'Setor (uang masuk)', 'out' => 'Tarik (uang keluar)'])
                    ->colors(['in' => 'success', 'out' => 'danger'])
                    ->icons(['in' => Heroicon::OutlinedArrowDownTray, 'out' => Heroicon::OutlinedArrowUpTray])
                    ->inline()
                    ->default('in')
                    ->required(),
                TextInput::make('amount')
                    ->label('Nominal')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(1)
                    ->required(),
                TextInput::make('note')
                    ->label('Catatan (opsional)')
                    ->maxLength(100),
            ])
            ->action(function (Account $record, array $data, RelationManager $livewire): void {
                $isIn = $data['direction'] === 'in';

                try {
                    app(LedgerService::class)->adjust(
                        $record,
                        $data['direction'],
                        $data['amount'],
                        $isIn ? 'Setoran Tunai' : 'Penarikan Tunai',
                        $isIn ? 'Setor Tunai' : 'Tarik Tunai',
                        $data['note'] ?? null,
                    );
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    throw new Halt; // modal tetap terbuka agar bisa diperbaiki
                }

                Notification::make()
                    ->title(($isIn ? 'Setoran ' : 'Penarikan ').Rupiah::format($data['amount']).' berhasil')
                    ->success()
                    ->send();

                // Saldo di header halaman nasabah ikut diperbarui.
                $livewire->dispatch('nasabah-updated');
            });
    }
}
