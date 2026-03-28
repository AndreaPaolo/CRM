<?php

namespace App\Filament\Resources\Abbonamentos\Pages;

use App\Filament\Resources\Abbonamentos\AbbonamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAbbonamentos extends ListRecords
{
    protected static string $resource = AbbonamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
