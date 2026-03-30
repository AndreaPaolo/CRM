<?php

namespace App\Filament\Resources\Clientes\Widgets;

use Filament\Widgets\Widget;

class ClienteAbbonamentiWidget extends Widget
{
    protected string $view = 'filament.resources.clientes.widgets.cliente-abbonamenti-widget';
    protected function getViewData(): array
    {
        $cliente = $this->record?->load([
            'abbonamenti' => function ($query) {
                $query
                    ->with([
                        'servizio',
                        'appuntamenti' => function ($appuntamentiQuery) {
                            $appuntamentiQuery->orderByDesc('data_ora');
                        },
                    ])
                    ->orderByDesc('data_inizio')
                    ->orderByDesc('created_at');
            },
        ]);

        return [
            'cliente' => $cliente,
            'abbonamenti' => $cliente?->abbonamenti ?? collect(),
        ];
    }
}
