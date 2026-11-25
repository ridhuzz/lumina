<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\Rupiah;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Header halaman detail nasabah (di atas tab Rekening · Mutasi · e-Statement). */
class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn ($record): string => $record->name)
                    ->description(fn ($record): string => '@'.$record->username.' · '.$record->email)
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->columns(['default' => 2, 'lg' => 4])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('account.number')->label('No. rekening')->copyable()->weight('bold'),
                        TextEntry::make('account.type')->label('Jenis rekening')->badge(),
                        TextEntry::make('account.balance')
                            ->label('Saldo')
                            ->formatStateUsing(fn ($state) => Rupiah::format($state))
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('created_at')->label('Terdaftar')->dateTime('d M Y'),
                    ]),
            ]);
    }
}
