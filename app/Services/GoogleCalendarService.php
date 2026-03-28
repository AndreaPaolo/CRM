<?php

namespace App\Services;

use App\Models\Appuntamento;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class GoogleCalendarService
{
    protected Calendar $calendar;
    protected string $calendarId;

    public function __construct()
    {
        $client = new Client();
        $client->setApplicationName(config('app.name'));
        $client->setScopes([Calendar::CALENDAR]);
        $client->setAuthConfig(storage_path('app/credentials.json'));

        // Se usi OAuth con token salvato
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $tokenPath = storage_path('app/token.json');
        if (file_exists($tokenPath)) {
            $client->setAccessToken(json_decode(file_get_contents($tokenPath), true));
        }

        $this->calendar = new Calendar($client);
        $this->calendarId = config('services.google.calendar_id');
    }

    public function syncAppuntamento(Appuntamento $appuntamento): void
    {
        $eventId = $this->buildEventId($appuntamento);

        $start = new EventDateTime([
            'dateTime' => $appuntamento->data_ora->format('c'),
            'timeZone' => 'Europe/Rome',
        ]);

        $end = new EventDateTime([
            'dateTime' => $appuntamento->data_ora->copy()->addMinutes($appuntamento->durata)->format('c'),
            'timeZone' => 'Europe/Rome',
        ]);

        $summary = $this->buildSummary($appuntamento);
        $description = $this->buildDescription($appuntamento);

        $event = new Event([
            'id' => $eventId,
            'summary' => $summary,
            'description' => $description,
            'start' => $start,
            'end' => $end,
            'extendedProperties' => [
                'private' => [
                    'appuntamento_id' => (string) $appuntamento->id,
                ],
            ],
        ]);

        try {
            $this->calendar->events->get($this->calendarId, $eventId);

            $this->calendar->events->update(
                $this->calendarId,
                $eventId,
                $event
            );
        } catch (\Exception $e) {
            $this->calendar->events->insert(
                $this->calendarId,
                $event
            );
        }

        if ($appuntamento->google_calendar_event_id !== $eventId) {
            $appuntamento->google_calendar_event_id = $eventId;
            $appuntamento->saveQuietly();
        }
    }

    public function deleteAppuntamento(Appuntamento $appuntamento): void
    {
        $eventId = $appuntamento->google_calendar_event_id ?: $this->buildEventId($appuntamento);

        try {
            $this->calendar->events->delete($this->calendarId, $eventId);
        } catch (\Exception $e) {
            // niente: se non esiste già, evitiamo blocchi
        }
    }

    protected function buildSummary(Appuntamento $appuntamento): string
    {
        $cliente = $appuntamento->cliente;
        $pt = $appuntamento->pt?->name ?? 'PT';
        $totale = $appuntamento->abbonamento?->servizio?->incontri ?? 0;

        return sprintf(
            '%s %s: %s/%s PT %s',
            $cliente->nome,
            $cliente->cognome,
            $appuntamento->numerazione,
            $totale,
            $pt
        );
    }

    protected function buildDescription(Appuntamento $appuntamento): string
    {
        $righe = [
            'APPUNTAMENTO_ID: ' . $appuntamento->id,
            'ABBONAMENTO_ID: ' . $appuntamento->abbonamento_id,
            '',
            (string) ($appuntamento->descrizione ?? ''),
        ];

        return trim(implode("\n", $righe));
    }

    protected function buildEventId(Appuntamento $appuntamento): string
    {
        return 'app' . str_pad((string) $appuntamento->id, 10, '0', STR_PAD_LEFT);
    }
}