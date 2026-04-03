<?php

namespace App\Filament\Resources\Abbonamentos\Pages;

use App\Filament\Resources\Abbonamentos\AbbonamentoResource;
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
}