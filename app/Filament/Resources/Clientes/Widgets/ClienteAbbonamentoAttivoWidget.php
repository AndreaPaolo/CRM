<?php

namespace App\Filament\Resources\Clientes\Widgets;

use Filament\Widgets\Widget;

class ClienteAbbonamentoAttivoWidget extends Widget
{
    protected string $view = 'filament.resources.clientes.widgets.cliente-abbonamento-attivo-widget';

    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $cliente = $this->record;

        $abbonamento = $cliente?->abbonamenti()
            ->with(['servizio', 'clienti', 'appuntamenti'])
            ->orderByRaw('CASE WHEN terminato = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('data_inizio')
            ->first();

        $sessioniUsate = 0;
        $totale = 0;
        $statoLabel = 'Nessun abbonamento';
        $statoColor = 'gray';

        if ($abbonamento) {
            $sessioniUsate = $abbonamento->appuntamenti
                ->map(fn ($appuntamento) => $appuntamento->sessione_condivisa_uuid ?: 'single_' . $appuntamento->id)
                ->unique()
                ->count();

            $totale = (int) ($abbonamento->servizio?->incontri ?? 0);

            if ($abbonamento->terminato) {
                $statoLabel = 'Terminato';
                $statoColor = 'red';
            } elseif ($abbonamento->data_fine && $abbonamento->data_fine->isPast()) {
                $statoLabel = 'Scaduto';
                $statoColor = 'red';
            } elseif ($abbonamento->data_fine && $abbonamento->data_fine->lte(now()->addDays(7))) {
                $statoLabel = 'In scadenza';
                $statoColor = 'amber';
            } else {
                $statoLabel = 'Attivo';
                $statoColor = 'green';
            }
        }

        return [
            'cliente' => $cliente,
            'abbonamento' => $abbonamento,
            'sessioniUsate' => $sessioniUsate,
            'totale' => $totale,
            'statoLabel' => $statoLabel,
            'statoColor' => $statoColor,
        ];
    }
}