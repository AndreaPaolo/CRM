<?php

namespace App\Services\Telegram;

use App\Support\Telegram\TelegramIntent;
use Carbon\Carbon;

class TelegramIntentParserService
{
    public function parse(string $text): ?TelegramIntent
    {
        $text = $this->normalizeText($text);
        $lower = mb_strtolower($text);

        if ($lower === 'agenda oggi') {
            return new TelegramIntent('agenda_oggi');
        }

        if ($lower === 'agenda domani') {
            return new TelegramIntent('agenda_domani');
        }

        if ($lower === 'pagamenti aperti') {
            return new TelegramIntent('pagamenti_aperti');
        }

        if ($lower === 'renew google') {
            return new TelegramIntent('renew_google');
        }

        if ($lower === 'sync google') {
            return new TelegramIntent('sync_google_status');
        }

        if ($lower === 'aggiorna abbonamenti mensili') {
            return new TelegramIntent('aggiorna_abbonamenti_mensili');
        }

        if (preg_match('/^agenda\s+(\d{2}-\d{2}-\d{4})$/iu', $text, $m)) {
            return new TelegramIntent('agenda_data', [
                'data' => trim($m[1]),
            ]);
        }

        if (preg_match('/^appuntamenti\s+(.+)$/iu', $text, $m)) {
            return new TelegramIntent('lista_prossimi_appuntamenti_cliente', [
                'cliente' => trim($m[1]),
            ]);
        }

        if (preg_match('/^pagamenti\s+(.+)$/iu', $text, $m)) {
            return new TelegramIntent('pagamenti_cliente', [
                'cliente' => trim($m[1]),
            ]);
        }

        if (preg_match('/^(.+)\s+pagato\s+(\d+)$/iu', $text, $m)) {
            return new TelegramIntent('segna_pagato_cliente', [
                'cliente' => trim($m[1]),
                'pagamento_id' => (int) $m[2],
            ]);
        }

        if (preg_match('/^assegna\s+(.+)\s+(.+)\s+(oggi|domani|\d{2}-\d{2}-\d{4})$/iu', $text, $m)) {
            [$abbonamento, $cliente] = $this->splitAbbonamentoCliente(trim($m[1] . ' ' . $m[2]));

            return new TelegramIntent('assegna_abbonamento', [
                'servizio' => $abbonamento,
                'cliente' => $cliente,
                'data_inizio' => trim($m[3]),
            ]);
        }

        if (preg_match('/^crea\s+(personal|call|consegna|smallgroup)\s+(.+)\s+(\d{2}-\d{2}-\d{4})(?:\s+ore\s+(\d{2}:\d{2}))?$/iu', $text, $m)) {
            $tipoInput = mb_strtolower(trim($m[1]));

            return new TelegramIntent(
                match ($tipoInput) {
                    'call' => 'crea_call',
                    'consegna' => 'crea_consegna',
                    'smallgroup' => 'crea_appuntamento',
                    default => 'crea_appuntamento',
                },
                [
                    'tipo' => match ($tipoInput) {
                        'call' => 'call_google_meet',
                        'consegna' => 'consegna_programma',
                        'smallgroup' => 'personal',
                        default => 'personal',
                    },
                    'modalita_creazione' => $tipoInput === 'smallgroup' ? 'smallgroup' : 'standard',
                    'cliente' => trim($m[2]),
                    'data' => trim($m[3]),
                    'ora' => isset($m[4]) ? trim($m[4]) : null,
                ]
            );
        }

        if (preg_match('/^modifica\s+(personal|call|consegna|smallgroup)\s+(.+)\s+(\d{2}-\d{2}-\d{4})\s+descrizione\s+(.+)$/iu', $text, $m)) {
            return new TelegramIntent('modifica_appuntamento', [
                'tipo' => $this->mapTipo(trim($m[1])),
                'cliente' => trim($m[2]),
                'data' => trim($m[3]),
                'descrizione' => trim($m[4]),
            ]);
        }

        if (preg_match('/^modifica\s+(personal|call|consegna|smallgroup)\s+(.+)\s+(\d{2}-\d{2}-\d{4})\s+durata\s+(\d+)$/iu', $text, $m)) {
            return new TelegramIntent('modifica_appuntamento', [
                'tipo' => $this->mapTipo(trim($m[1])),
                'cliente' => trim($m[2]),
                'data' => trim($m[3]),
                'durata' => (int) $m[4],
            ]);
        }

        if (preg_match('/^modifica\s+(personal|call|consegna|smallgroup)\s+(.+)\s+(\d{2}-\d{2}-\d{4})\s+ora\s+(\d{2}:\d{2})$/iu', $text, $m)) {
            return new TelegramIntent('modifica_appuntamento', [
                'tipo' => $this->mapTipo(trim($m[1])),
                'cliente' => trim($m[2]),
                'data' => trim($m[3]),
                'nuova_ora' => trim($m[4]),
            ]);
        }

        if (preg_match('/^elimina\s+(personal|call|consegna|smallgroup)\s+(.+)\s+(\d{2}-\d{2}-\d{4})(?:\s+ore\s+(\d{2}:\d{2}))?$/iu', $text, $m)) {
            return new TelegramIntent('elimina_appuntamento_ctx', [
                'tipo' => $this->mapTipo(trim($m[1])),
                'cliente' => trim($m[2]),
                'data' => trim($m[3]),
                'ora' => isset($m[4]) ? trim($m[4]) : null,
            ]);
        }

        return null;
    }

    public function resolveDate(string $value): Carbon
    {
        $value = mb_strtolower(trim($value));

        return match ($value) {
            'oggi' => now(),
            'domani' => now()->addDay(),
            default => Carbon::createFromFormat('d-m-Y', $value),
        };
    }

    protected function mapTipo(string $tipo): string
    {
        return match (mb_strtolower($tipo)) {
            'call' => 'call_google_meet',
            'consegna' => 'consegna_programma',
            'smallgroup' => 'personal',
            default => 'personal',
        };
    }

    protected function normalizeText(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    protected function splitAbbonamentoCliente(string $value): array
    {
        $parts = preg_split('/\s+/', trim($value)) ?: [];

        if (count($parts) < 3) {
            return [$value, $value];
        }

        $cliente = implode(' ', array_slice($parts, -2));
        $abbonamento = implode(' ', array_slice($parts, 0, -2));

        return [$abbonamento, $cliente];
    }
}