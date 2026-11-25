<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * Detail nasabah: header data nasabah + saldo, lalu tab
 * Rekening · Mutasi · e-Statement · Penerima tersimpan.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Ubah data nasabah'),
        ];
    }

    /** Dipanggil tab Rekening setelah Setor/Tarik agar saldo di header ikut berubah. */
    #[On('nasabah-updated')]
    public function refreshHeader(): void
    {
        $this->record->refresh()->load('account');
    }
}
