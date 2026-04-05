<?php

namespace App\Filament\Resources\Pagamentos\Pages;

use App\Filament\Resources\Pagamentos\PagamentoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPagamento extends EditRecord
{
    protected static string $resource = PagamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
