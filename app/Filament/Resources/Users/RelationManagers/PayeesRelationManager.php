<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Setting;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Penerima tersimpan milik nasabah (muncul di "transfer cepat" aplikasi). */
class PayeesRelationManager extends RelationManager
{
    protected static string $relationship = 'payees';

    protected static ?string $title = 'Penerima tersimpan';

    protected static ?string $modelLabel = 'penerima';

    /** Bank lain (contoh). Bank internal = nama aplikasi di menu Identitas Aplikasi. */
    public const OTHER_BANKS = ['Bank Harmoni', 'Bank Sentosa', 'Bank Nusantara'];

    /** @return array<string, string> */
    public static function banks(): array
    {
        $names = [Setting::appName(), ...self::OTHER_BANKS];

        return array_combine($names, $names);
    }

    public function isReadOnly(): bool
    {
        return false; // tetap bisa dikelola dari halaman detail nasabah
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama penerima')
                    ->required()
                    ->maxLength(100),
                Select::make('bank')
                    ->label('Bank')
                    ->options(fn (): array => self::banks())
                    ->required(),
                TextInput::make('number')
                    ->label('No. rekening')
                    ->required()
                    ->regex('/^\d{6,20}$/')
                    ->validationMessages(['regex' => 'Nomor rekening harus 6–20 digit angka.']),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('bank')->label('Bank')->badge(),
                TextColumn::make('number')->label('No. rekening')->copyable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
