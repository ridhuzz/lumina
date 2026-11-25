<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\AccountsRelationManager;
use App\Filament\Resources\Users\RelationManagers\PayeesRelationManager;
use App\Filament\Resources\Users\RelationManagers\StatementsRelationManager;
use App\Filament\Resources\Users\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Nasabah (user non-admin). Pintu masuk semua data: pilih nasabah, lalu di
 * halaman detailnya ada tab Rekening, Mutasi, e-Statement, dan Penerima tersimpan.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'nasabah';

    protected static ?string $pluralModelLabel = 'nasabah';

    protected static ?string $navigationLabel = 'Nasabah';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    /** Admin tidak ikut tampil di daftar nasabah. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->customers()->with('account');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AccountsRelationManager::class,
            TransactionsRelationManager::class,
            StatementsRelationManager::class,
            PayeesRelationManager::class,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'username', 'email'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
