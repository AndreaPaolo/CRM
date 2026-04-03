<?php

namespace App\Filament\Resources\Appuntamentos\Pages;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Models\Appuntamento;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateAppuntamento extends CreateRecord
{
    protected static string $resource = AppuntamentoResource::class;

    protected array $partecipantiSelezionati = [];

    public function mount(): void
    {
        parent::mount();

        $data = [];

        if (request()->filled('cliente_id')) {
            $data['cliente_id'] = (int) request()->integer('cliente_id');
        }

        if (request()->filled('abbonamento_id')) {
            $data['abbonamento_id'] = (int) request()->integer('abbonamento_id');
        }

        $clienti = request()->query('clienti');

        if (is_array($clienti)) {
            $data['clienti'] = array_map('intval', $clienti);
        }

        if (! empty($data)) {
            $this->form->fill($data);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->partecipantiSelezionati = $data['clienti'] ?? [];

        unset($data['clienti']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Appuntamento
    {
        $partecipanti = collect($this->partecipantiSelezionati);

        if (! empty($data['cliente_id'])) {
            $partecipanti->prepend($data['cliente_id']);
        }

        $partecipanti = $partecipanti
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($partecipanti) && ! empty($data['cliente_id'])) {
            $partecipanti = [$data['cliente_id']];
        }

        $uuidSessione = count($partecipanti) > 1 ? (string) Str::uuid() : null;

        $recordPrincipale = null;

        foreach ($partecipanti as $index => $clienteId) {
            $payload = $data;
            $payload['cliente_id'] = $clienteId;
            $payload['sessione_condivisa_uuid'] = $uuidSessione;

            $appuntamento = Appuntamento::create($payload);

            if ($index === 0) {
                $recordPrincipale = $appuntamento;
            }
        }

        return $recordPrincipale;
    }

    protected function afterCreate(): void
    {
        $appuntamento = $this->record->fresh([
            'cliente',
            'abbonamento.servizio',
            'pt',
        ]);

        $abbonamento = $appuntamento->abbonamento;

        if ($abbonamento) {
            $abbonamento->aggiornaNumerazioneAppuntamenti();
            $abbonamento->aggiornaStatoTerminato();
            $abbonamento->sincronizzaAppuntamentiSuGoogle();
        }
    }

    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return $data;
    }
}