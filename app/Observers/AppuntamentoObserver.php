<?php

namespace App\Observers;

use App\Models\Appuntamento;
use App\Services\GoogleCalendarService;

class AppuntamentoObserver
{
    public function created(Appuntamento $appuntamento): void
    {
        app(GoogleCalendarService::class)->syncAppuntamento($appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt']));
    }

    public function updated(Appuntamento $appuntamento): void
    {
        app(GoogleCalendarService::class)->syncAppuntamento($appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt']));
    }

    public function deleted(Appuntamento $appuntamento): void
    {
        app(GoogleCalendarService::class)->deleteAppuntamento($appuntamento);
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