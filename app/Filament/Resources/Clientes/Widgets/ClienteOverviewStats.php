<?php

namespace App\Filament\Resources\Clientes\Widgets;

use App\Models\Appuntamento;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClienteOverviewStats extends StatsOverviewWidget
{
    public ?\App\Models\Cliente $record = null;

    protected function getStats(): array
    {
        $cliente = $this->record;

        if (! $cliente) {
            return [];
        }

        $abbonamentoAttivo = $cliente->abbonamenti()
            ->where('terminato', false)
            ->orderByDesc('data_inizio')
            ->first();

        $prossimoAppuntamento = Appuntamento::query()
            ->where('cliente_id', $cliente->id)
            ->where('data_ora', '>=', now())
            ->orderBy('data_ora')
            ->first();

        $totaleAppuntamenti = Appuntamento::query()
            ->where('cliente_id', $cliente->id)
            ->count();

        $lezioniUsate = 0;

        if ($abbonamentoAttivo) {
            $lezioniUsate = Appuntamento::query()
                ->where('abbonamento_id', $abbonamentoAttivo->id)
                ->get()
                ->map(fn ($appuntamento) => $appuntamento->sessione_condivisa_uuid ?: 'single_' . $appuntamento->id)
                ->unique()
                ->count();
        }

        return [
            Stat::make(
                'Abbonamento attivo',
                $abbonamentoAttivo?->servizio?->nome ?? 'Nessuno'
            )
                ->color($abbonamentoAttivo ? 'success' : 'gray'),

            Stat::make(
                'Prossima lezione',
                $prossimoAppuntamento?->data_ora?->format('d/m/Y H:i') ?? 'Nessuna'
            )
                ->color($prossimoAppuntamento ? 'info' : 'gray'),

            Stat::make(
                'Totale appuntamenti',
                (string) $totaleAppuntamenti
            )
                ->color('warning'),

            Stat::make(
                'Lezioni usate',
                (string) $lezioniUsate
            )
                ->color('success'),
        ];
    }
}