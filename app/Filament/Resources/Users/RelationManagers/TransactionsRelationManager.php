<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Transaction;
use App\Support\Rupiah;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tab "Mutasi" di detail nasabah — hanya baca. Seperti buku besar bank, mutasi
 * tidak boleh diubah/dihapus; koreksi lewat transaksi baru (Setor / Tarik).
 */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Mutasi';

    protected static ?string $modelLabel = 'mutasi';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedArrowsRightLeft;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ref')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('ref')
                    ->label('Ref')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('title')
                    ->label('Keterangan')
                    ->description(fn (Transaction $record): ?string => $record->note)
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state, Transaction $record): string => ($record->direction === 'in' ? '+ ' : '− ').Rupiah::format($state))
                    ->color(fn (Transaction $record): string => $record->direction === 'in' ? 'success' : 'danger')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Arah')
                    ->options(['in' => 'Masuk', 'out' => 'Keluar']),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(fn (): array => $this->getOwnerRecord()->transactions()
                        ->reorder()
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
                Filter::make('occurred_at')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('transactions.occurred_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('transactions.occurred_at', '<=', $date))),
            ]);
    }
}
