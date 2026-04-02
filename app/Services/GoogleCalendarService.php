<?php

namespace App\Services;

use App\Models\Appuntamento;
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

        $attendees = [];

        if (! empty($appuntamento->cliente?->email) && filter_var($appuntamento->cliente->email, FILTER_VALIDATE_EMAIL)) {
            $attendees[] = [
                'email' => $appuntamento->cliente->email,
                'displayName' => $appuntamento->cliente->nome . ' ' . $appuntamento->cliente->cognome,
            ];
        }

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
            'attendees' => $attendees,
            'extendedProperties' => [
                'private' => [
                    'appuntamento_id' => (string) $appuntamento->id,
                    'crm_source' => 'crm',
                ],
            ],
        ]);

        try {
            try {
                $this->calendar->events->get($this->calendarId, $eventId);

                $this->calendar->events->update($this->calendarId, $eventId, $event, [
                    'sendUpdates' => 'none',
                ]);
            } catch (Throwable $e) {
                $this->calendar->events->insert($this->calendarId, $event, [
                    'sendUpdates' => 'none',
                ]);
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
            //
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
        return $appuntamento->descrizione ?: 'Appuntamento CRM';
    }

    protected function buildEventId(Appuntamento $appuntamento): string
    {
        return 'app' . str_pad((string) $appuntamento->id, 10, '0', STR_PAD_LEFT);
    }

    public function deleteOrphanCalendarEvents(): int
    {
        $deleted = 0;
        $pageToken = null;
        $eventsToDelete = [];

        do {
            $events = $this->calendar->events->listEvents($this->calendarId, [
                'maxResults' => 2500,
                'pageToken' => $pageToken,
                'singleEvents' => true,
                'showDeleted' => false,
            ]);

            foreach ($events->getItems() as $event) {
                $private = $event->getExtendedProperties()?->getPrivate() ?? [];
                $appuntamentoId = $private['appuntamento_id'] ?? null;

                if ($appuntamentoId) {
                    $exists = \App\Models\Appuntamento::where('id', $appuntamentoId)->exists();

                    if (! $exists) {
                        $eventsToDelete[] = $event->getId();
                    }

                    continue;
                }

                $eventsToDelete[] = $event->getId();
            }

            $pageToken = $events->getNextPageToken();
        } while ($pageToken);

        foreach ($eventsToDelete as $eventId) {
            try {
                $this->calendar->events->delete($this->calendarId, $eventId);
                $deleted++;
            } catch (Throwable $e) {
                //
            }
        }

        return $deleted;
    }
}