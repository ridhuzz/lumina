<?php

namespace App\Filament\Resources\Modules;

use App\Filament\Resources\Modules\Pages\ManageModules;
use App\Models\Module;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * Modul "Menu Utama" di beranda aplikasi. Urutan diatur dengan seret (reorder);
 * modul nonaktif tetap tampil di aplikasi tetapi tidak bisa dibuka.
 */
class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $modelLabel = 'modul';

    protected static ?string $pluralModelLabel = 'modul';

    protected static ?string $navigationLabel = 'Modul Menu';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')
                    ->label('ID modul')
                    ->helperText('Huruf kecil, angka, - atau _ (mis. transfer). Aplikasi membuka halaman sesuai ID ini.')
                    ->required()
                    ->maxLength(40)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama modul')
                    ->helperText('Tampil di bawah ikon.')
                    ->required()
                    ->maxLength(40),
                Select::make('icon')
                    ->label('Ikon')
                    ->options(Module::ICONS)
                    ->required()
                    ->native(false),
                TextInput::make('badge')
                    ->label('Label kecil (opsional)')
                    ->helperText('Mis. BARU.')
                    ->maxLength(12),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->helperText('Nonaktif = tetap tampil, tetapi tidak bisa dibuka.')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama modul')
                    ->searchable()
                    ->description(fn (Module $r): ?string => $r->badge ? "Label: {$r->badge}" : null),
                TextColumn::make('code')
                    ->label('ID modul')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('icon')
                    ->label('Ikon')
                    ->formatStateUsing(fn (string $state): string => Module::ICONS[$state] ?? $state)
                    ->color('gray'),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageModules::route('/'),
        ];
    }
}
