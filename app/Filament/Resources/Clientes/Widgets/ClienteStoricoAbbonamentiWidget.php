<?php

namespace App\Filament\Resources\Clientes\Widgets;

use Filament\Widgets\Widget;

class ClienteStoricoAbbonamentiWidget extends Widget
{
    protected string $view = 'filament.resources.clientes.widgets.cliente-storico-abbonamenti-widget';

    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $cliente = $this->record;

        $abbonamenti = $cliente?->abbonamenti()
            ->with(['servizio', 'clienti'])
            ->orderByDesc('data_inizio')
            ->orderByDesc('created_at')
            ->get() ?? collect();

        return [
            'abbonamenti' => $abbonamenti,
        ];
    }
}