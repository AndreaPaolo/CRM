<?php

namespace App\Filament\Resources\Servizios\Pages;

use App\Filament\Resources\Servizios\ServizioResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditServizio extends EditRecord
{
    protected static string $resource = ServizioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
