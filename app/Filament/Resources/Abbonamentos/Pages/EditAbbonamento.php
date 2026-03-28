<?php

namespace App\Filament\Resources\Abbonamentos\Pages;

use App\Filament\Resources\Abbonamentos\AbbonamentoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAbbonamento extends EditRecord
{
    protected static string $resource = AbbonamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
