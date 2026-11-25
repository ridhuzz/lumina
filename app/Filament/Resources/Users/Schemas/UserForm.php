<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Account;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public const ACCOUNT_TYPES = [
        'Tabungan Utama' => 'Tabungan Utama',
        'Tabungan Pelajar' => 'Tabungan Pelajar',
        'Tabungan Bisnis' => 'Tabungan Bisnis',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data nasabah')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama lengkap')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('username')
                            ->label('User ID')
                            ->helperText('Dipakai untuk login di aplikasi. Huruf, angka, titik, atau garis bawah; disimpan sebagai huruf kecil.')
                            ->required()
                            ->maxLength(50)
                            ->regex('/^[A-Za-z0-9_.]+$/')
                            ->validationMessages(['regex' => 'User ID hanya boleh huruf, angka, titik, atau garis bawah (tanpa spasi).'])
                            ->dehydrateStateUsing(fn (?string $state) => $state ? strtolower(trim($state)) : $state)
                            ->unique(ignoreRecord: true),
                        TextInput::make('pin')
                            ->label('PIN (6 digit)')
                            ->password()
                            ->revealable()
                            ->rule('digits:6')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            // Kosong saat edit = PIN tidak diubah. PIN disimpan ter-hash oleh model.
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Kosongkan bila PIN tidak diubah.'
                                : null),
                    ]),

                // Rekening hanya diisi saat membuat nasabah baru (lihat CreateUser).
                Section::make('Rekening')
                    ->visibleOn('create')
                    ->columns(3)
                    ->schema([
                        TextInput::make('account_number')
                            ->label('No. rekening')
                            ->required()
                            ->regex('/^\d{10}$/')
                            ->validationMessages(['regex' => 'Nomor rekening harus 10 digit angka.'])
                            ->unique(table: Account::class, column: 'number')
                            ->default(fn (): string => self::newAccountNumber()),
                        Select::make('account_type')
                            ->label('Jenis rekening')
                            ->options(self::ACCOUNT_TYPES)
                            ->default('Tabungan Utama')
                            ->required(),
                        TextInput::make('initial_deposit')
                            ->label('Setoran awal')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->default(0),
                    ]),
            ]);
    }

    /** Nomor rekening unik berawalan 8820 (10 digit). */
    public static function newAccountNumber(): string
    {
        do {
            $number = '8820'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Account::where('number', $number)->exists());

        return $number;
    }
}
