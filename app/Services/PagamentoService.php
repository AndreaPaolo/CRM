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
        ?string $metodoPagamentoIniziale = null,
        ?float $importoPagamentoIniziale = null
    ): void {
        $abbonamento->loadMissing(['servizio', 'cliente']);

        if (($abbonamento->servizio?->tipo_fatturazione ?? 'pacchetto') !== 'pacchetto') {
            return;
        }

        $prezzo = round((float) $abbonamento->prezzo, 2);
        $rate = max(1, (int) ($abbonamento->rate ?? 1));

        if ($prezzo <= 0) {
            return;
        }

        $iniziale = $registraPagamentoIniziale
            ? round((float) ($importoPagamentoIniziale ?? 0), 2)
            : 0;

        $iniziale = min($iniziale, $prezzo);

        if ($rate === 1) {
            $pagamento = \App\Models\Pagamento::create([
                'cliente_id' => $abbonamento->cliente_id,
                'abbonamento_id' => $abbonamento->id,
                'tipo' => 'pacchetto',
                'descrizione' => 'Pagamento pacchetto',
                'importo_previsto' => $prezzo,
                'scadenza' => $abbonamento->data_inizio,
                'stato' => $iniziale >= $prezzo ? 'pagato' : ($iniziale > 0 ? 'parziale' : 'da_pagare'),
                'importo_pagato' => $iniziale,
                'data_saldo' => $iniziale >= $prezzo ? now()->toDateString() : null,
                'numero_rata' => 1,
                'totale_rate' => 1,
            ]);

            if ($iniziale > 0) {
                $pagamento->movimenti()->create([
                    'data_pagamento' => now()->toDateString(),
                    'importo' => $iniziale,
                    'metodo' => $metodoPagamentoIniziale,
                    'note' => 'Pagamento iniziale registrato in creazione abbonamento',
                ]);
            }

            return;
        }

        if ($iniziale > 0) {
            $pagamentoIniziale = \App\Models\Pagamento::create([
                'cliente_id' => $abbonamento->cliente_id,
                'abbonamento_id' => $abbonamento->id,
                'tipo' => 'rata',
                'descrizione' => 'Pagamento iniziale',
                'importo_previsto' => $iniziale,
                'scadenza' => $abbonamento->data_inizio,
                'stato' => 'pagato',
                'importo_pagato' => $iniziale,
                'data_saldo' => now()->toDateString(),
                'numero_rata' => 1,
                'totale_rate' => $rate,
            ]);

            $pagamentoIniziale->movimenti()->create([
                'data_pagamento' => now()->toDateString(),
                'importo' => $iniziale,
                'metodo' => $metodoPagamentoIniziale,
                'note' => 'Pagamento iniziale registrato in creazione abbonamento',
            ]);
        }

        $residuo = max(0, round($prezzo - $iniziale, 2));
        $rateResidue = $iniziale > 0 ? max(1, $rate - 1) : $rate;

        if ($residuo <= 0) {
            return;
        }

        $quota = round($residuo / $rateResidue, 2);
        $totaleAssegnato = 0;

        for ($i = 1; $i <= $rateResidue; $i++) {
            $importo = $i === $rateResidue
                ? round($residuo - $totaleAssegnato, 2)
                : $quota;

            $totaleAssegnato += $importo;

            $numeroRata = $iniziale > 0 ? $i + 1 : $i;

            \App\Models\Pagamento::create([
                'cliente_id' => $abbonamento->cliente_id,
                'abbonamento_id' => $abbonamento->id,
                'tipo' => 'rata',
                'descrizione' => "Rata {$numeroRata}/{$rate}",
                'importo_previsto' => $importo,
                'scadenza' => \Carbon\Carbon::parse($abbonamento->data_inizio)->addMonths($i)->toDateString(),
                'stato' => 'da_pagare',
                'importo_pagato' => 0,
                'data_saldo' => null,
                'numero_rata' => $numeroRata,
                'totale_rate' => $rate,
            ]);
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