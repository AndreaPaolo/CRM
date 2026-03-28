<?php

use Illuminate\Support\Facades\Route;
use App\Models\Appuntamento;
use App\Services\GoogleCalendarService;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-google-calendar', function () {

    $appuntamento = Appuntamento::with([
        'cliente',
        'abbonamento.servizio',
        'pt'
    ])->latest()->first();

    if (! $appuntamento) {
        return '❌ Nessun appuntamento nel DB';
    }

    try {
        app(GoogleCalendarService::class)
            ->syncAppuntamento($appuntamento);

        return '✅ Evento creato/aggiornato correttamente';
    } catch (\Throwable $e) {
        return '❌ ERRORE: ' . $e->getMessage();
    }
});