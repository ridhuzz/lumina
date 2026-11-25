<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\LedgerService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Membuat nasabah + rekening sekaligus. Setoran awal dicatat sebagai mutasi
     * lewat LedgerService (bukan langsung mengisi saldo).
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'pin' => $data['pin'],
                'password' => Str::random(32), // nasabah login pakai PIN, bukan password
                'is_admin' => false,
            ]);

            $account = $user->account()->create([
                'number' => $data['account_number'],
                'type' => $data['account_type'],
                'balance' => 0,
            ]);

            if ((float) ($data['initial_deposit'] ?? 0) > 0) {
                app(LedgerService::class)->adjust(
                    $account, 'in', $data['initial_deposit'], 'Setoran Awal', 'Setor Tunai',
                );
            }

            return $user;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
