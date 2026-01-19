<?php

namespace App\Filament\Resources\VexoOrderResource\Pages;

use App\Filament\Resources\VexoOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVexoOrders extends ListRecords
{
    protected static string $resource = VexoOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
