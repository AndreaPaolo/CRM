<?php

namespace App\Filament\Resources\Servizios\Pages;

use App\Filament\Resources\Servizios\ServizioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServizios extends ListRecords
{
    protected static string $resource = ServizioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
