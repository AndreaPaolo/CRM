<?php

namespace App\Filament\Resources\Appuntamentos\Pages;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Models\Abbonamento;
use App\Models\Appuntamento;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EditAppuntamento extends EditRecord
{
    protected static string $resource = AppuntamentoResource::class;

    protected array $partecipantiSelezionati = [];

    protected function afterFill(): void
    {
        $appuntamento = $this->record->fresh(['abbonamento.clienti']);

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
            'data_evento' => $appuntamento->evento_intera_giornata && $appuntamento->data_ora
                ? $appuntamento->data_ora->format('Y-m-d')
                : null,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->action(function () {
                    $record = $this->record->fresh();

                    if ($record->sessione_condivisa_uuid) {
                        Appuntamento::query()
                            ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                            ->get()
                            ->each
                            ->delete();
                    } else {
                        $record->delete();
                    }

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->partecipantiSelezionati = array_map('intval', $data['clienti'] ?? []);
        unset($data['clienti']);

        return $this->normalizeEventDateData($data);
    }

    protected function handleRecordUpdate($record, array $data): Appuntamento
    {
        $abbonamento = Abbonamento::query()
            ->with(['servizio', 'clienti'])
            ->find($data['abbonamento_id']);

        $isSmallGroup = $this->isSmallGroupAbbonamento($abbonamento);

        if (! $isSmallGroup) {
            if ($record->sessione_condivisa_uuid) {
                Appuntamento::query()
                    ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                    ->where('id', '!=', $record->id)
                    ->get()
                    ->each
                    ->delete();
            }

            $record->update([
                'cliente_id' => $data['cliente_id'],
                'abbonamento_id' => $data['abbonamento_id'],
                'sessione_condivisa_uuid' => null,
                'user_id' => $data['user_id'] ?? $record->user_id,
                'data_ora' => $data['data_ora'],
                'durata' => $data['durata'],
                'tipo_appuntamento' => $data['tipo_appuntamento'],
                'evento_intera_giornata' => $data['evento_intera_giornata'] ?? false,
                'descrizione' => $data['descrizione'] ?? null,
                'calendar_sync_status' => 'dirty',
                'calendar_last_error' => null,
            ]);

            return $record->fresh();
        }

        $partecipanti = $this->buildPartecipantiSmallGroup($abbonamento, $data['cliente_id'] ?? null, $this->partecipantiSelezionati);

        if ($partecipanti->count() <= 1) {
            if ($record->sessione_condivisa_uuid) {
                Appuntamento::query()
                    ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                    ->where('id', '!=', $record->id)
                    ->get()
                    ->each
                    ->delete();
            }

            $record->update([
                'cliente_id' => $partecipanti->first() ?? $data['cliente_id'],
                'abbonamento_id' => $data['abbonamento_id'],
                'sessione_condivisa_uuid' => null,
                'user_id' => $data['user_id'] ?? $record->user_id,
                'data_ora' => $data['data_ora'],
                'durata' => $data['durata'],
                'tipo_appuntamento' => $data['tipo_appuntamento'],
                'evento_intera_giornata' => $data['evento_intera_giornata'] ?? false,
                'descrizione' => $data['descrizione'] ?? null,
                'calendar_sync_status' => 'dirty',
                'calendar_last_error' => null,
            ]);

            return $record->fresh();
        }

        $uuid = $record->sessione_condivisa_uuid ?: (string) Str::uuid();

        Appuntamento::query()
            ->where(function ($query) use ($record, $uuid) {
                if ($record->sessione_condivisa_uuid) {
                    $query->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid);
                } else {
                    $query->where('id', $record->id);
                }
            })
            ->update([
                'abbonamento_id' => $data['abbonamento_id'],
                'user_id' => $data['user_id'] ?? $record->user_id,
                'data_ora' => $data['data_ora'],
                'durata' => $data['durata'],
                'tipo_appuntamento' => $data['tipo_appuntamento'],
                'evento_intera_giornata' => $data['evento_intera_giornata'] ?? false,
                'descrizione' => $data['descrizione'] ?? null,
                'sessione_condivisa_uuid' => $uuid,
                'calendar_sync_status' => 'dirty',
                'calendar_last_error' => null,
                'updated_at' => now(),
            ]);

        $esistenti = Appuntamento::query()
            ->where('sessione_condivisa_uuid', $uuid)
            ->pluck('cliente_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $daAggiungere = array_diff($partecipanti->all(), $esistenti);

        foreach ($daAggiungere as $clienteId) {
            Appuntamento::create([
                'cliente_id' => $clienteId,
                'abbonamento_id' => $data['abbonamento_id'],
                'sessione_condivisa_uuid' => $uuid,
                'user_id' => $data['user_id'] ?? $record->user_id,
                'data_ora' => $data['data_ora'],
                'durata' => $data['durata'],
                'tipo_appuntamento' => $data['tipo_appuntamento'],
                'evento_intera_giornata' => $data['evento_intera_giornata'] ?? false,
                'descrizione' => $data['descrizione'] ?? null,
                'numerazione' => $record->numerazione,
                'calendar_sync_status' => 'dirty',
                'calendar_last_error' => null,
            ]);
        }

        $daEliminare = array_diff($esistenti, $partecipanti->all());

        if (! empty($daEliminare)) {
            Appuntamento::query()
                ->where('sessione_condivisa_uuid', $uuid)
                ->whereIn('cliente_id', $daEliminare)
                ->get()
                ->each
                ->delete();
        }

        $principaleId = $partecipanti->first();

        if ($principaleId) {
            Appuntamento::query()
                ->where('sessione_condivisa_uuid', $uuid)
                ->where('cliente_id', $principaleId)
                ->update(['id' => \DB::raw('id')]);
        }

        return Appuntamento::query()->find($record->id)?->fresh() ?? $record->fresh();
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

    protected function normalizeEventDateData(array $data): array
    {
        $tipo = $data['tipo_appuntamento'] ?? 'personal';

        if ($tipo === 'consegna_programma') {
            $dataEvento = $data['data_evento'] ?? null;

            if ($dataEvento) {
                $data['data_ora'] = Carbon::parse($dataEvento)->startOfDay()->format('Y-m-d H:i:s');
            } elseif (empty($data['data_ora'])) {
                $data['data_ora'] = now()->startOfDay()->format('Y-m-d H:i:s');
            }

            $data['evento_intera_giornata'] = true;
            $data['durata'] = 1440;
        } else {
            $data['evento_intera_giornata'] = false;

            if (empty($data['durata']) || (int) $data['durata'] === 1440) {
                $data['durata'] = 60;
            }
        }

        unset($data['data_evento']);

        return $data;
    }

    protected function isSmallGroupAbbonamento(?Abbonamento $abbonamento): bool
    {
        if (! $abbonamento || ! $abbonamento->servizio) {
            return false;
        }

        $nome = mb_strtolower((string) $abbonamento->servizio->nome);

        return str_contains($nome, 'smallgroup') || str_contains($nome, 'small group');
    }

    protected function buildPartecipantiSmallGroup(?Abbonamento $abbonamento, ?int $clientePrincipaleId, array $selezionati): Collection
    {
        $idsAbilitati = collect($abbonamento?->clienti?->pluck('id')->all() ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $partecipanti = collect($selezionati)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->when($idsAbilitati->isNotEmpty(), fn (Collection $c) => $c->intersect($idsAbilitati))
            ->values();

        if ($clientePrincipaleId) {
            $clientePrincipaleId = (int) $clientePrincipaleId;

            if ($idsAbilitati->isEmpty() || $idsAbilitati->contains($clientePrincipaleId)) {
                $partecipanti->prepend($clientePrincipaleId);
            }
        }

        return $partecipanti->filter()->unique()->values();
    }
}