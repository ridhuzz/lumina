<?php

namespace App\Filament\Resources\Banners;

use App\Filament\Resources\Banners\Pages\ManageBanners;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Banner promo aplikasi: halaman login (sebelum masuk) dan beranda.
 * Urutan tayang diatur dengan seret (reorder); banner nonaktif / di luar periode tidak tampil.
 */
class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'banner';

    protected static ?string $pluralModelLabel = 'banner';

    protected static ?string $navigationLabel = 'Banner';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('placement')
                    ->label('Posisi')
                    ->options(Banner::PLACEMENTS)
                    ->default('welcome')
                    ->required()
                    ->live(),
                TextInput::make('title')
                    ->label('Judul')
                    ->helperText('Nama banner (untuk admin & teks alternatif).')
                    ->required()
                    ->maxLength(100),
                FileUpload::make('image_path')
                    ->label('Gambar')
                    ->image()
                    ->disk(Banner::DISK)
                    ->directory(Banner::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(Banner::MAX_KB)
                    ->helperText(fn (Get $get): string => 'JPG/PNG/WEBP, maks. 2 MB. Ukuran disarankan '
                        .(Banner::SIZES[$get('placement') ?? 'welcome'] ?? Banner::SIZES['welcome']).'.')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('link_url')
                    ->label('Tautan (opsional)')
                    ->helperText('Dibuka saat banner diketuk di aplikasi.')
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at')
                    ->label('Mulai tayang (opsional)')
                    ->seconds(false),
                DateTimePicker::make('ends_at')
                    ->label('Selesai tayang (opsional)')
                    ->seconds(false)
                    ->after('starts_at'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('preview')
                    ->label('Gambar')
                    ->state(fn (Banner $r): string => route('admin.banners.image', ['banner' => $r, 'v' => $r->updated_at?->timestamp]))
                    ->imageHeight(56)
                    ->imageWidth(140),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->description(fn (Banner $r): ?string => $r->link_url),
                TextColumn::make('placement')
                    ->label('Posisi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'welcome' ? 'Halaman login' : 'Beranda')
                    ->color(fn (string $state): string => $state === 'welcome' ? 'info' : 'success'),
                TextColumn::make('period')
                    ->label('Periode')
                    ->state(fn (Banner $r): string => match (true) {
                        ! $r->starts_at && ! $r->ends_at => 'Selalu',
                        default => ($r->starts_at?->format('d M Y') ?? '…').' – '.($r->ends_at?->format('d M Y') ?? '…'),
                    })
                    ->color('gray'),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('placement')
                    ->label('Posisi')
                    ->options(Banner::PLACEMENTS),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBanners::route('/'),
        ];
    }
}
