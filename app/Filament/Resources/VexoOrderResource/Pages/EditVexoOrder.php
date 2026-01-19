<?php

namespace App\Filament\Resources\VexoOrderResource\Pages;

use App\Filament\Resources\VexoOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVexoOrder extends EditRecord
{
    protected static string $resource = VexoOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
