<?php

namespace App\Filament\Resources\Appuntamentos\Pages;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Models\Abbonamento;
use App\Models\Appuntamento;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CreateAppuntamento extends CreateRecord
{
    protected static string $resource = AppuntamentoResource::class;

    protected function afterFill(): void
    {
        $data = [];

        if (request()->filled('cliente_id')) {
            $data['cliente_id'] = (int) request()->integer('cliente_id');
        }

        if (request()->filled('abbonamento_id')) {
            $data['abbonamento_id'] = (int) request()->integer('abbonamento_id');
        }

        if (! empty($data)) {
            $this->form->fill($data);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeEventDateData($data);
    }

    protected function handleRecordCreation(array $data): Appuntamento
    {
        $abbonamento = Abbonamento::query()
            ->with(['servizio', 'clienti'])
            ->find($data['abbonamento_id']);

        $isGruppo = $this->isAbbonamentoGruppo($abbonamento);

        if (! $isGruppo) {
            return Appuntamento::create($data);
        }

        $partecipanti = $this->buildPartecipantiGruppo(
            $abbonamento,
            $data['cliente_id'] ?? null
        );

        if ($partecipanti->isEmpty()) {
            return Appuntamento::create($data);
        }

        $uuidSessione = (string) Str::uuid();
        $recordPrincipale = null;

        foreach ($partecipanti->values() as $index => $clienteId) {
            $payload = $data;
            $payload['cliente_id'] = $clienteId;
            $payload['sessione_condivisa_uuid'] = $uuidSessione;

            $appuntamento = Appuntamento::create($payload);

            if ($index === 0) {
                $recordPrincipale = $appuntamento;
            }
        }

        return $recordPrincipale ?? Appuntamento::create($data);
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

    protected function isAbbonamentoGruppo(?Abbonamento $abbonamento): bool
    {
        if (! $abbonamento) {
            return false;
        }

        return mb_strtolower((string) $abbonamento->tipo_partecipazione) === 'gruppo';
    }

    protected function buildPartecipantiGruppo(?Abbonamento $abbonamento, ?int $clientePrincipaleId): Collection
    {
        $partecipanti = collect();

        if ($clientePrincipaleId) {
            $partecipanti->push((int) $clientePrincipaleId);
        }

        $altriPartecipanti = collect($abbonamento?->clienti?->pluck('id')->all() ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter();

        return $partecipanti
            ->merge($altriPartecipanti)
            ->filter()
            ->unique()
            ->values();
    }
}