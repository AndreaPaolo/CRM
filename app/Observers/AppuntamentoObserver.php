<?php

namespace App\Observers;

use App\Models\Appuntamento;
use App\Services\GoogleCalendarService;
use Throwable;

class AppuntamentoObserver
{
    public function created(Appuntamento $appuntamento): void
    {
        $this->syncPacchetto($appuntamento);
    }

    public function updated(Appuntamento $appuntamento): void
    {
        $this->syncPacchetto($appuntamento);
    }

    public function deleted(Appuntamento $appuntamento): void
    {
        try {
            app(GoogleCalendarService::class)->deleteAppuntamento(
                $appuntamento->fresh(['cliente'])
            );
        } catch (Throwable $e) {
            //
        }

        $abbonamento = $appuntamento->abbonamento?->loadMissing('servizio');

        if (! $abbonamento) {
            return;
        }

        $abbonamento->aggiornaNumerazioneAppuntamenti();
        $abbonamento->aggiornaStatoTerminato();
        $abbonamento->sincronizzaAppuntamentiSuGoogle();
    }

    public function restored(Appuntamento $appuntamento): void
    {
        try {
            app(GoogleCalendarService::class)->syncAppuntamento(
                $appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt'])
            );
        } catch (Throwable $e) {
            //
        }
    }

    public function forceDeleted(Appuntamento $appuntamento): void
    {
        try {
            app(GoogleCalendarService::class)->deleteAppuntamento(
                $appuntamento->fresh(['cliente'])
            );
        } catch (Throwable $e) {
            //
        }
    }

    protected function syncPacchetto(Appuntamento $appuntamento): void
    {
        $abbonamento = $appuntamento->abbonamento?->loadMissing('servizio');

        if (! $abbonamento) {
            return;
        }

        $abbonamento->aggiornaNumerazioneAppuntamenti();
        $abbonamento->aggiornaStatoTerminato();
        $abbonamento->sincronizzaAppuntamentiSuGoogle();
    }
}