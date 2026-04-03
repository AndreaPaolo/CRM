<?php

namespace App\Filament\Resources\Clientes\Widgets;

use Filament\Widgets\Widget;

class ClienteAbbonamentoAttivoWidget extends Widget
{

    protected string $view = 'filament.resources.clientes.widgets.cliente-abbonamenti-widget';

    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $cliente = $this->record;

        $abbonamento = $cliente?->abbonamenti()
            ->with(['servizio', 'clienti', 'appuntamenti'])
            ->where('terminato', false)
            ->orderByDesc('data_inizio')
            ->first();

        $sessioniUsate = 0;

        if ($abbonamento) {
            $sessioniUsate = $abbonamento->appuntamenti
                ->map(fn ($appuntamento) => $appuntamento->sessione_condivisa_uuid ?: 'single_' . $appuntamento->id)
                ->unique()
                ->count();
        }

        return [
            'cliente' => $cliente,
            'abbonamento' => $abbonamento,
            'sessioniUsate' => $sessioniUsate,
        ];
    }
}