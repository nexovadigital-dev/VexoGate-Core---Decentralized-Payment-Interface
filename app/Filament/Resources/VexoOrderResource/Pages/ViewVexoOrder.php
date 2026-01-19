<?php

namespace App\Filament\Resources\VexoOrderResource\Pages;

use App\Filament\Resources\VexoOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVexoOrder extends ViewRecord
{
    protected static string $resource = VexoOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Orders')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }
}
