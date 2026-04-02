<?php

namespace App\Filament\Resources\Appuntamentos\Pages;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Models\Appuntamento;
use Filament\Resources\Pages\EditRecord;

class EditAppuntamento extends EditRecord
{
    protected static string $resource = AppuntamentoResource::class;

    protected array $partecipantiSelezionati = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->partecipantiSelezionati = $data['clienti'] ?? [];

        unset($data['clienti']);

        return $data;
    }

    protected function handleRecordUpdate($record, array $data): Appuntamento
    {
        if ($record->sessione_condivisa_uuid) {
            $partecipanti = collect($this->partecipantiSelezionati);

            if (! empty($data['cliente_id'])) {
                $partecipanti->prepend($data['cliente_id']);
            }

            $partecipanti = $partecipanti
                ->filter()
                ->unique()
                ->values()
                ->all();

            Appuntamento::query()
                ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                ->update([
                    'data_ora' => $data['data_ora'],
                    'durata' => $data['durata'],
                    'descrizione' => $data['descrizione'] ?? null,
                    'updated_at' => now(),
                ]);

            $esistenti = Appuntamento::query()
                ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                ->pluck('cliente_id')
                ->all();

            $daAggiungere = array_diff($partecipanti, $esistenti);

            foreach ($daAggiungere as $clienteId) {
                $payload = $data;
                $payload['cliente_id'] = $clienteId;
                $payload['abbonamento_id'] = $record->abbonamento_id;
                $payload['user_id'] = $record->user_id;
                $payload['sessione_condivisa_uuid'] = $record->sessione_condivisa_uuid;

                Appuntamento::create($payload);
            }

            $daEliminare = array_diff($esistenti, $partecipanti);

            if (! empty($daEliminare)) {
                Appuntamento::query()
                    ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                    ->whereIn('cliente_id', $daEliminare)
                    ->delete();
            }

            return $record->fresh();
        }

        $record->update($data);

        return $record;
    }

    protected function afterSave(): void
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
}