<?php

namespace App\Filament\Resources\Abbonamentos\Pages;

use App\Filament\Resources\Abbonamentos\AbbonamentoResource;
use App\Services\PagamentoService;
use Filament\Resources\Pages\CreateRecord;

class CreateAbbonamento extends CreateRecord
{
    protected static string $resource = AbbonamentoResource::class;

    public function mount(): void
    {
        parent::mount();

        $data = [];

        if (request()->filled('cliente_id')) {
            $clienteId = (int) request()->integer('cliente_id');

            $data['cliente_id'] = $clienteId;
            $data['clienti'] = [$clienteId];
        }

        if (! empty($data)) {
            $this->form->fill($data);
        }
    }

    protected function afterCreate(): void
    {
        app(PagamentoService::class)->generaPagamentiPacchetto(
            $this->record,
            (bool) ($this->data['registra_pagamento_iniziale'] ?? false),
            $this->data['metodo_pagamento_iniziale'] ?? null
        );
    }
}