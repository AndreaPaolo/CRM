<?php

namespace App\Filament\Resources\Appuntamentos\Pages;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Models\Appuntamento;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditAppuntamento extends EditRecord
{
    protected static string $resource = AppuntamentoResource::class;

    protected array $partecipantiSelezionati = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $appuntamento = $this->record->loadMissing(['abbonamento.clienti']);

        $altriPartecipanti = [];

        if ($appuntamento->sessione_condivisa_uuid) {
            $altriPartecipanti = Appuntamento::query()
                ->where('sessione_condivisa_uuid', $appuntamento->sessione_condivisa_uuid)
                ->where('id', '!=', $appuntamento->id)
                ->pluck('cliente_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $this->form->fill([
            ...$this->record->attributesToArray(),
            'cliente_id' => $appuntamento->cliente_id,
            'abbonamento_id' => $appuntamento->abbonamento_id,
            'clienti' => $altriPartecipanti,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->partecipantiSelezionati = array_map('intval', $data['clienti'] ?? []);

        unset($data['clienti']);

        return $data;
    }

    protected function handleRecordUpdate($record, array $data): Appuntamento
    {
        $partecipanti = collect($this->partecipantiSelezionati);

        if (! empty($data['cliente_id'])) {
            $partecipanti->prepend((int) $data['cliente_id']);
        }

        $partecipanti = $partecipanti
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($partecipanti) && ! empty($data['cliente_id'])) {
            $partecipanti = [(int) $data['cliente_id']];
        }

        // Caso: diventa o resta condiviso
        if (count($partecipanti) > 1) {
            $uuid = $record->sessione_condivisa_uuid ?: (string) Str::uuid();

            // aggiorno tutti gli esistenti della sessione
            if ($record->sessione_condivisa_uuid) {
                Appuntamento::query()
                    ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                    ->update([
                        'abbonamento_id' => $data['abbonamento_id'],
                        'user_id' => $data['user_id'] ?? $record->user_id,
                        'data_ora' => $data['data_ora'],
                        'durata' => $data['durata'],
                        'descrizione' => $data['descrizione'] ?? null,
                        'sessione_condivisa_uuid' => $uuid,
                        'calendar_sync_status' => 'dirty',
                        'calendar_last_error' => null,
                        'updated_at' => now(),
                    ]);
            } else {
                $record->update([
                    'abbonamento_id' => $data['abbonamento_id'],
                    'user_id' => $data['user_id'] ?? $record->user_id,
                    'data_ora' => $data['data_ora'],
                    'durata' => $data['durata'],
                    'descrizione' => $data['descrizione'] ?? null,
                    'sessione_condivisa_uuid' => $uuid,
                    'calendar_sync_status' => 'dirty',
                    'calendar_last_error' => null,
                ]);
            }

            $esistenti = Appuntamento::query()
                ->where('sessione_condivisa_uuid', $uuid)
                ->pluck('cliente_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $daAggiungere = array_diff($partecipanti, $esistenti);

            foreach ($daAggiungere as $clienteId) {
                Appuntamento::create([
                    'cliente_id' => $clienteId,
                    'abbonamento_id' => $data['abbonamento_id'],
                    'sessione_condivisa_uuid' => $uuid,
                    'user_id' => $data['user_id'] ?? $record->user_id,
                    'data_ora' => $data['data_ora'],
                    'durata' => $data['durata'],
                    'descrizione' => $data['descrizione'] ?? null,
                    'numerazione' => $record->numerazione,
                    'calendar_sync_status' => 'dirty',
                    'calendar_last_error' => null,
                ]);
            }

            $daEliminare = array_diff($esistenti, $partecipanti);

            if (! empty($daEliminare)) {
                Appuntamento::query()
                    ->where('sessione_condivisa_uuid', $uuid)
                    ->whereIn('cliente_id', $daEliminare)
                    ->delete();
            }

            return Appuntamento::query()->find($record->id)?->fresh() ?? $record->fresh();
        }

        // Caso: torna singolo
        if ($record->sessione_condivisa_uuid) {
            Appuntamento::query()
                ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                ->where('id', '!=', $record->id)
                ->delete();
        }

        $record->update([
            'cliente_id' => $partecipanti[0] ?? $data['cliente_id'],
            'abbonamento_id' => $data['abbonamento_id'],
            'sessione_condivisa_uuid' => null,
            'user_id' => $data['user_id'] ?? $record->user_id,
            'data_ora' => $data['data_ora'],
            'durata' => $data['durata'],
            'descrizione' => $data['descrizione'] ?? null,
            'calendar_sync_status' => 'dirty',
            'calendar_last_error' => null,
        ]);

        return $record->fresh();
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