<?php

namespace App\Filament\Resources\Modules\Pages;

use App\Filament\Resources\Modules\ModuleResource;
use App\Models\Module;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageModules extends ManageRecords
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah modul')
                // Modul baru ditaruh paling akhir.
                ->mutateDataUsing(function (array $data): array {
                    $data['sort_order'] = (int) Module::max('sort_order') + 1;

                    return $data;
                }),
        ];
    }
}
