<?php

namespace App\Services;

use App\Models\Appuntamento;
use Google\Client;
use Google\Service\Calendar;
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
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $tokenPath = storage_path('app/token.json');

        if (! file_exists($tokenPath)) {
            throw new \RuntimeException('token.json non trovato. Esegui php artisan google:calendar-auth');
        }

        $token = json_decode(file_get_contents($tokenPath), true);

        if (! is_array($token)) {
            throw new \RuntimeException('token.json non valido.');
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken() ?: ($token['refresh_token'] ?? null);

            if (! $refreshToken) {
                throw new \RuntimeException('Refresh token mancante o scaduto. Esegui di nuovo php artisan google:calendar-auth');
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
                throw new \RuntimeException('Refresh token non valido o revocato: ' . json_encode($newToken));
            }

            $token = array_merge($token, $newToken);

            if (empty($token['refresh_token'])) {
                $token['refresh_token'] = $refreshToken;
            }

            file_put_contents(
                $tokenPath,
                json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $client->setAccessToken($token);
        }

        $this->calendar = new Calendar($client);
        $this->calendarId = config('services.google.calendar_id');
    }

    public function syncAppuntamento(\App\Models\Appuntamento $appuntamento): void
    {
        $appuntamento->loadMissing(['cliente', 'abbonamento.servizio', 'pt']);

        $eventId = $appuntamento->google_calendar_event_id ?: $this->buildEventId($appuntamento);
        $isAllDay = (bool) $appuntamento->evento_intera_giornata;
        $shouldCreateMeet = $this->shouldCreateMeet($appuntamento);

        $payload = [
            'summary' => $this->buildSummary($appuntamento),
            'description' => $this->buildDescription($appuntamento),
            'extendedProperties' => [
                'private' => [
                    'appuntamento_id' => (string) $appuntamento->id,
                    'crm_source' => 'crm',
                ],
            ],
        ];

        if ($isAllDay) {
            $startDate = $appuntamento->data_ora->copy()->startOfDay()->format('Y-m-d');
            $endDate = $appuntamento->data_ora->copy()->addDay()->startOfDay()->format('Y-m-d');

            $payload['start'] = ['date' => $startDate];
            $payload['end'] = ['date' => $endDate];
        } else {
            $startAt = $appuntamento->data_ora->copy()->setTimezone('Europe/Rome');
            $endAt = $startAt->copy()->addMinutes($appuntamento->durata);

            $payload['start'] = [
                'dateTime' => $startAt->format('c'),
                'timeZone' => 'Europe/Rome',
            ];
            $payload['end'] = [
                'dateTime' => $endAt->format('c'),
                'timeZone' => 'Europe/Rome',
            ];
        }

        $attendees = [];

        if (! empty($appuntamento->cliente?->email) && filter_var($appuntamento->cliente->email, FILTER_VALIDATE_EMAIL)) {
            $attendees[] = [
                'email' => $appuntamento->cliente->email,
                'displayName' => trim(($appuntamento->cliente->nome ?? '') . ' ' . ($appuntamento->cliente->cognome ?? '')),
            ];
        }

        $payload['attendees'] = $attendees;

        if ($shouldCreateMeet) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'meet-app-' . $appuntamento->id . '-' . now()->timestamp,
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ];
        }

        try {
            $event = new \Google\Service\Calendar\Event($payload);

            if ($appuntamento->google_calendar_event_id) {
                $updatedEvent = $this->calendar->events->update(
                    $this->calendarId,
                    $appuntamento->google_calendar_event_id,
                    $event,
                    [
                        'sendUpdates' => 'all',
                        'conferenceDataVersion' => $shouldCreateMeet ? 1 : 0,
                    ]
                );

                $appuntamento->updateQuietly([
                    'google_calendar_event_id' => $updatedEvent->getId(),
                    'google_meet_link' => $updatedEvent->getHangoutLink(),
                    'calendar_sync_status' => 'synced',
                    'calendar_synced_at' => now(),
                    'calendar_last_error' => null,
                ]);
            } else {
                $createdEvent = $this->calendar->events->insert(
                    $this->calendarId,
                    $event,
                    [
                        'sendUpdates' => 'all',
                        'conferenceDataVersion' => $shouldCreateMeet ? 1 : 0,
                    ]
                );

                $appuntamento->updateQuietly([
                    'google_calendar_event_id' => $createdEvent->getId(),
                    'google_meet_link' => $createdEvent->getHangoutLink(),
                    'calendar_sync_status' => 'synced',
                    'calendar_synced_at' => now(),
                    'calendar_last_error' => null,
                ]);
            }
        } catch (\Throwable $e) {
            $appuntamento->updateQuietly([
                'calendar_sync_status' => 'failed',
                'calendar_last_error' => $e->getMessage(),
            ]);

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

    protected function buildSummary(\App\Models\Appuntamento $appuntamento): string
    {
        $cliente = trim(($appuntamento->cliente?->nome ?? '') . ' ' . ($appuntamento->cliente?->cognome ?? ''));
        $servizio = $appuntamento->abbonamento?->servizio?->nome ?? 'Appuntamento';

        $prefisso = match ($appuntamento->tipo_appuntamento) {
            'call_google_meet' => 'Call',
            'consegna_programma' => 'Consegna programma',
            default => 'Personal',
        };

        return "{$cliente}: {$prefisso} ({$servizio})";
    }

    protected function buildDescription(\App\Models\Appuntamento $appuntamento): string
    {
        $righe = [
            'Cliente: ' . trim(($appuntamento->cliente?->nome ?? '') . ' ' . ($appuntamento->cliente?->cognome ?? '')),
            'Servizio: ' . ($appuntamento->abbonamento?->servizio?->nome ?? '-'),
            'Tipo: ' . match ($appuntamento->tipo_appuntamento) {
                'call_google_meet' => 'Call Google Meet',
                'consegna_programma' => 'Consegna programma',
                default => 'Personal',
            },
            'Durata: ' . ($appuntamento->evento_intera_giornata ? 'Giornata intera' : ($appuntamento->durata . ' minuti')),
        ];

        if (! empty($appuntamento->descrizione)) {
            $righe[] = 'Note: ' . $appuntamento->descrizione;
        }

        return implode("\n", $righe);
    }

    protected function shouldCreateMeet(\App\Models\Appuntamento $appuntamento): bool
    {
        $servizio = $appuntamento->abbonamento?->servizio;

        if (! $servizio) {
            return false;
        }

        if ($appuntamento->tipo_appuntamento === 'call_google_meet') {
            return true;
        }

        return (bool) ($servizio->crea_google_meet_default ?? false);
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

    public function syncPagamento(\App\Models\Pagamento $pagamento): void
    {
        if (! $pagamento->scadenza) {
            return;
        }

        $pagamento->loadMissing(['cliente', 'abbonamento.servizio']);

        $startDate = $pagamento->scadenza->format('Y-m-d');
        $endDate = $pagamento->scadenza->copy()->addDay()->format('Y-m-d');

        $payload = [
            'summary' => $this->buildPagamentoSummary($pagamento),
            'description' => $this->buildPagamentoDescription($pagamento),
            'start' => [
                'date' => $startDate,
            ],
            'end' => [
                'date' => $endDate,
            ],
            'extendedProperties' => [
                'private' => [
                    'pagamento_id' => (string) $pagamento->id,
                    'crm_source' => 'crm',
                    'crm_type' => 'pagamento',
                ],
            ],
        ];

        try {
            if ($pagamento->google_calendar_event_id) {
                $event = new \Google\Service\Calendar\Event($payload);

                $updatedEvent = $this->calendar->events->update(
                    $this->calendarId,
                    $pagamento->google_calendar_event_id,
                    $event,
                    ['sendUpdates' => 'none']
                );

                $pagamento->updateQuietly([
                    'google_calendar_event_id' => $updatedEvent->getId(),
                    'calendar_sync_status' => 'synced',
                    'calendar_last_error' => null,
                ]);
            } else {
                $event = new \Google\Service\Calendar\Event($payload);

                $createdEvent = $this->calendar->events->insert(
                    $this->calendarId,
                    $event,
                    ['sendUpdates' => 'none']
                );

                $pagamento->updateQuietly([
                    'google_calendar_event_id' => $createdEvent->getId(),
                    'calendar_sync_status' => 'synced',
                    'calendar_last_error' => null,
                ]);
            }
        } catch (\Throwable $e) {
            $pagamento->updateQuietly([
                'calendar_sync_status' => 'failed',
                'calendar_last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deletePagamento(\App\Models\Pagamento $pagamento): void
    {
        if (! $pagamento->google_calendar_event_id) {
            return;
        }

        try {
            $this->calendar->events->delete($this->calendarId, $pagamento->google_calendar_event_id);
        } catch (\Throwable $e) {
            //
        }
    }

    protected function buildPagamentoSummary(\App\Models\Pagamento $pagamento): string
    {
        $nome = trim(($pagamento->cliente?->nome ?? '') . ' ' . ($pagamento->cliente?->cognome ?? ''));
        $servizio = $pagamento->abbonamento?->servizio?->nome ?? 'Abbonamento';
        $importo = number_format((float) $pagamento->importo_previsto, 0, ',', '.');

        $bloccoTipo = match ($pagamento->tipo) {
            'rata' => $pagamento->numero_rata && $pagamento->totale_rate
                ? 'rata ' . $pagamento->numero_rata . '/' . $pagamento->totale_rate
                : 'rata',
            'mensile' => 'saldo mensile',
            'pacchetto' => 'pacchetto',
            default => 'pagamento',
        };

        return "{$nome}: {$bloccoTipo} {$importo}€ ({$servizio}) - {$pagamento->stato}";
    }

    protected function buildPagamentoDescription(\App\Models\Pagamento $pagamento): string
    {
        $righe = [
            'Cliente: ' . trim(($pagamento->cliente?->nome ?? '') . ' ' . ($pagamento->cliente?->cognome ?? '')),
            'Servizio: ' . ($pagamento->abbonamento?->servizio?->nome ?? '-'),
            'Descrizione: ' . ($pagamento->descrizione ?? '-'),
            'Importo previsto: € ' . number_format((float) $pagamento->importo_previsto, 2, ',', '.'),
            'Importo pagato: € ' . number_format((float) $pagamento->importo_pagato, 2, ',', '.'),
            'Stato: ' . $pagamento->stato,
        ];

        if ($pagamento->competenza_da && $pagamento->competenza_a) {
            $righe[] = 'Periodo: ' . $pagamento->competenza_da->format('d/m/Y') . ' - ' . $pagamento->competenza_a->format('d/m/Y');
        }

        if ($pagamento->scadenza) {
            $righe[] = 'Scadenza: ' . $pagamento->scadenza->format('d/m/Y');
        }

        return implode("\n", $righe);
    }

    protected function buildPagamentoEventId(\App\Models\Pagamento $pagamento): string
    {
        return 'pay' . strtolower(base_convert((string) $pagamento->id, 10, 32));
    }
}