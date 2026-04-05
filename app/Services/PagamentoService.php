<?php

namespace App\Services;

use App\Models\Abbonamento;
use App\Models\Appuntamento;
use App\Models\Pagamento;
use Carbon\Carbon;

class PagamentoService
{
    public function generaPagamentiPacchetto(
        Abbonamento $abbonamento,
        bool $registraPagamentoIniziale = false,
        ?string $metodoPagamentoIniziale = null
    ): void {
        $abbonamento->loadMissing(['servizio', 'cliente']);

        if (($abbonamento->servizio?->tipo_fatturazione ?? 'pacchetto') !== 'pacchetto') {
            return;
        }

        $prezzo = (float) $abbonamento->prezzo;
        $rate = max(1, (int) ($abbonamento->rate ?? 1));

        if ($prezzo <= 0) {
            return;
        }

        if ($rate === 1) {
            $pagamento = Pagamento::create([
                'cliente_id' => $abbonamento->cliente_id,
                'abbonamento_id' => $abbonamento->id,
                'tipo' => 'pacchetto',
                'descrizione' => 'Pagamento pacchetto',
                'importo_previsto' => $prezzo,
                'scadenza' => $abbonamento->data_inizio,
                'stato' => $registraPagamentoIniziale ? 'pagato' : 'da_pagare',
                'importo_pagato' => $registraPagamentoIniziale ? $prezzo : 0,
                'data_saldo' => $registraPagamentoIniziale ? now()->toDateString() : null,
            ]);

            if ($registraPagamentoIniziale) {
                $pagamento->movimenti()->create([
                    'data_pagamento' => now()->toDateString(),
                    'importo' => $prezzo,
                    'metodo' => $metodoPagamentoIniziale,
                    'note' => 'Pagamento iniziale registrato in creazione abbonamento',
                ]);
            }

            return;
        }

        $quota = round($prezzo / $rate, 2);
        $totaleAssegnato = 0;

        for ($i = 1; $i <= $rate; $i++) {
            $importo = $i === $rate ? round($prezzo - $totaleAssegnato, 2) : $quota;
            $totaleAssegnato += $importo;

            $pagamento = Pagamento::create([
                'cliente_id' => $abbonamento->cliente_id,
                'abbonamento_id' => $abbonamento->id,
                'tipo' => 'rata',
                'descrizione' => "Rata {$i}/{$rate}",
                'importo_previsto' => $importo,
                'scadenza' => Carbon::parse($abbonamento->data_inizio)->addMonths($i - 1)->toDateString(),
                'stato' => ($registraPagamentoIniziale && $i === 1) ? 'pagato' : 'da_pagare',
                'importo_pagato' => ($registraPagamentoIniziale && $i === 1) ? $importo : 0,
                'data_saldo' => ($registraPagamentoIniziale && $i === 1) ? now()->toDateString() : null,
                'numero_rata' => $i,
                'totale_rate' => $rate,
            ]);

            if ($registraPagamentoIniziale && $i === 1) {
                $pagamento->movimenti()->create([
                    'data_pagamento' => now()->toDateString(),
                    'importo' => $importo,
                    'metodo' => $metodoPagamentoIniziale,
                    'note' => 'Prima rata registrata in creazione abbonamento',
                ]);
            }
        }
    }

    public function generaPagamentoMensile(
        Abbonamento $abbonamento,
        Carbon $inizioPeriodo,
        Carbon $finePeriodo
    ): ?Pagamento {
        $abbonamento->loadMissing(['servizio', 'cliente']);

        if (($abbonamento->servizio?->tipo_fatturazione ?? 'pacchetto') !== 'mensile') {
            return null;
        }

        $costoOrarioCliente = (float) $abbonamento->prezzo;

        if ($costoOrarioCliente <= 0) {
            return null;
        }

        $appuntamenti = Appuntamento::query()
            ->where('abbonamento_id', $abbonamento->id)
            ->whereBetween('data_ora', [
                $inizioPeriodo->copy()->startOfDay(),
                $finePeriodo->copy()->endOfDay(),
            ])
            ->get();

        if ($appuntamenti->isEmpty()) {
            return null;
        }

        $totaleOre = $appuntamenti->sum('durata') / 60;
        $importo = round($totaleOre * $costoOrarioCliente, 2);

        if ($importo <= 0) {
            return null;
        }

        $esistente = Pagamento::query()
            ->where('abbonamento_id', $abbonamento->id)
            ->where('tipo', 'mensile')
            ->whereDate('competenza_da', $inizioPeriodo->toDateString())
            ->whereDate('competenza_a', $finePeriodo->toDateString())
            ->first();

        if ($esistente) {
            return $esistente;
        }

        return Pagamento::create([
            'cliente_id' => $abbonamento->cliente_id,
            'abbonamento_id' => $abbonamento->id,
            'tipo' => 'mensile',
            'competenza_da' => $inizioPeriodo->toDateString(),
            'competenza_a' => $finePeriodo->toDateString(),
            'descrizione' => 'Saldo mensile ' . $inizioPeriodo->translatedFormat('F Y'),
            'importo_previsto' => $importo,
            'scadenza' => $finePeriodo->toDateString(),
            'stato' => 'da_pagare',
        ]);
    }
}