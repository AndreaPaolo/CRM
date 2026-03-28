<?php

namespace App\Observers;

use App\Models\Appuntamento;
use App\Services\GoogleCalendarService;

class AppuntamentoObserver
{
    public function created(Appuntamento $appuntamento): void
    {
        try {
            app(GoogleCalendarService::class)->syncAppuntamento(
                $appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt'])
            );
        } catch (Throwable $e) {
            // stato già segnato come failed nel service
        }
    }

    public function updated(Appuntamento $appuntamento): void
    {
        try {
            app(GoogleCalendarService::class)->syncAppuntamento(
                $appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt'])
            );
        } catch (Throwable $e) {
            // stato già segnato come failed nel service
        }
    }

    public function deleted(Appuntamento $appuntamento): void
    {
        try {
            app(GoogleCalendarService::class)->deleteAppuntamento($appuntamento);
        } catch (Throwable $e) {
            //
        }
    }

    public function restored(Appuntamento $appuntamento): void
    {
        app(GoogleCalendarService::class)->syncAppuntamento($appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt']));
    }

    public function forceDeleted(Appuntamento $appuntamento): void
    {
        app(GoogleCalendarService::class)->deleteAppuntamento($appuntamento);
    }
}