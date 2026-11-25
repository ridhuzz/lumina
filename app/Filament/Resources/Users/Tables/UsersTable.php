<?php

namespace App\Filament\Resources\Users\Tables;

use App\Support\Rupiah;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn ($record): string => '@'.$record->username)
                    ->searchable(['name', 'username'])
                    ->sortable(),
                TextColumn::make('account.number')
                    ->label('No. rekening')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('account.type')
                    ->label('Jenis')
                    ->toggleable(),
                TextColumn::make('account.balance')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->alignEnd(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
