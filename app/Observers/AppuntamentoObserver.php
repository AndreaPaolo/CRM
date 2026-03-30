<?php

namespace App\Observers;

use App\Models\Appuntamento;
use App\Services\GoogleCalendarService;

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
            app(GoogleCalendarService::class)->deleteAppuntamento($appuntamento);
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
        app(GoogleCalendarService::class)->syncAppuntamento($appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt']));
    }

    public function forceDeleted(Appuntamento $appuntamento): void
    {
        app(GoogleCalendarService::class)->deleteAppuntamento($appuntamento);
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