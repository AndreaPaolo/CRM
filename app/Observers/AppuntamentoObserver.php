<?php

namespace App\Observers;

use App\Models\Appuntamento;

class AppuntamentoObserver
{
    public function created(Appuntamento $appuntamento): void
    {
        //
    }

    public function updated(Appuntamento $appuntamento): void
    {
        //
    }

    public function deleted(Appuntamento $appuntamento): void
    {
        try {
            app(\App\Services\GoogleCalendarService::class)
                ->deleteAppuntamentoPerPartecipanti(
                    $appuntamento->fresh(['clienti', 'cliente'])
                );
        } catch (\Throwable $e) {
            //
        }
    }

    public function restored(Appuntamento $appuntamento): void
    {
        //
    }

    public function forceDeleted(Appuntamento $appuntamento): void
    {
        //
    }
}