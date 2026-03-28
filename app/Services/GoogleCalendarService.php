<?php

namespace App\Services;

use App\Models\Appuntamento;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Throwable;

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

        $startAt = $appuntamento->data_ora->copy()->setTimezone('Europe/Rome');
        $endAt = $startAt->copy()->addMinutes($appuntamento->durata);

        $event = new Event([
            'id' => $eventId,
            'summary' => $this->buildSummary($appuntamento),
            'description' => $this->buildDescription($appuntamento),
            'start' => new EventDateTime([
                'dateTime' => $startAt->format('c'),
                'timeZone' => 'Europe/Rome',
            ]),
            'end' => new EventDateTime([
                'dateTime' => $endAt->format('c'),
                'timeZone' => 'Europe/Rome',
            ]),
            'extendedProperties' => [
                'private' => [
                    'appuntamento_id' => (string) $appuntamento->id,
                ],
            ],
        ]);

        try {
            try {
                $this->calendar->events->get($this->calendarId, $eventId);
                $this->calendar->events->update($this->calendarId, $eventId, $event);
            } catch (Throwable $e) {
                $this->calendar->events->insert($this->calendarId, $event);
            }

            $appuntamento->forceFill([
                'google_calendar_event_id' => $eventId,
                'calendar_sync_status' => 'synced',
                'calendar_synced_at' => now(),
                'calendar_last_error' => null,
            ])->saveQuietly();
        } catch (Throwable $e) {
            $appuntamento->forceFill([
                'calendar_sync_status' => 'failed',
                'calendar_last_error' => $e->getMessage(),
            ])->saveQuietly();

            throw $e;
        }
    }

    public function deleteAppuntamento(Appuntamento $appuntamento): void
    {
        $eventId = $appuntamento->google_calendar_event_id ?: $this->buildEventId($appuntamento);

        try {
            $this->calendar->events->delete($this->calendarId, $eventId);
        } catch (Throwable $e) {
            // opzionale: salva errore o ignora se evento non trovato
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
        return trim(implode("\n", [
            'APPUNTAMENTO_ID: ' . $appuntamento->id,
            'ABBONAMENTO_ID: ' . $appuntamento->abbonamento_id,
            '',
            (string) ($appuntamento->descrizione ?? ''),
        ]));
    }

    protected function buildEventId(Appuntamento $appuntamento): string
    {
        return 'app' . str_pad((string) $appuntamento->id, 10, '0', STR_PAD_LEFT);
    }
}