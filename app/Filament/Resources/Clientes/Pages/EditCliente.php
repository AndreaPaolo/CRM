<?php

namespace App\Filament\Resources\Clientes\Pages;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Clientes\Widgets\ClienteAbbonamentoAttivoWidget;
use App\Filament\Resources\Clientes\Widgets\ClienteAppuntamentiWidget;
use App\Filament\Resources\Clientes\Widgets\ClienteOverviewStats;
use App\Filament\Resources\Clientes\Widgets\ClienteStoricoAbbonamentiWidget;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ClienteOverviewStats::class,
            ClienteAbbonamentoAttivoWidget::class,
            ClienteAppuntamentiWidget::class,
            ClienteStoricoAbbonamentiWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 1;
    }
}