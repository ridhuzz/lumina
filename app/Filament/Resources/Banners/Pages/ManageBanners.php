<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Filament\Resources\Banners\BannerResource;
use App\Models\Banner;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBanners extends ManageRecords
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah banner')
                // Banner baru ditaruh paling akhir pada urutan tayang.
                ->mutateDataUsing(function (array $data): array {
                    $data['sort_order'] = (int) Banner::where('placement', $data['placement'])->max('sort_order') + 1;

                    return $data;
                }),
        ];
    }
}
