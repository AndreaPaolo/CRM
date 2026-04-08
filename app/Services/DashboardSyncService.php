<?php

namespace App\Services;

use App\Models\Abbonamento;
use Carbon\Carbon;

class DashboardSyncService
{
    public function sync(): void
    {
        $inizioPeriodo = Carbon::now()->startOfMonth();
        $finePeriodo = Carbon::now()->endOfMonth();

        $abbonamenti = Abbonamento::query()
            ->with(['servizio', 'cliente'])
            ->where('terminato', false)
            ->whereHas('servizio', fn ($q) => $q->where('tipo_fatturazione', 'mensile'))
            ->get();

        $pagamentoService = app(PagamentoService::class);

        foreach ($abbonamenti as $abbonamento) {
            try {
                $abbonamento->aggiornaStatoTerminato();
                $pagamentoService->generaPagamentoMensile(
                    $abbonamento,
                    $inizioPeriodo,
                    $finePeriodo
                );
            } catch (\Throwable $e) {
                //
            }
        }
    }
}