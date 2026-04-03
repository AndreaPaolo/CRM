<?php

namespace App\Filament\Resources\Clientes\Widgets;

use App\Models\Appuntamento;
use Filament\Widgets\Widget;

class ClienteAppuntamentiWidget extends Widget
{
    protected string $view = 'filament.resources.clientes.widgets.cliente-appuntamenti-widget';

    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $cliente = $this->record;

        $prossimi = Appuntamento::query()
            ->with(['abbonamento.servizio', 'pt'])
            ->where('cliente_id', $cliente?->id)
            ->where('data_ora', '>=', now())
            ->orderBy('data_ora')
            ->limit(10)
            ->get();

        $ultimi = Appuntamento::query()
            ->with(['abbonamento.servizio', 'pt'])
            ->where('cliente_id', $cliente?->id)
            ->where('data_ora', '<', now())
            ->orderByDesc('data_ora')
            ->limit(10)
            ->get();

        return [
            'prossimi' => $prossimi,
            'ultimi' => $ultimi,
        ];
    }
}