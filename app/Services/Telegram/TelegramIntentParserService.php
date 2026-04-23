<?php

namespace App\Services\Telegram;

use App\Support\Telegram\TelegramIntent;
use Carbon\Carbon;

class TelegramIntentParserService
{
    public function parse(string $text): ?TelegramIntent
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $normalized = mb_strtolower($text);

        if (in_array($normalized, ['agenda oggi', 'oggi'], true)) {
            return new TelegramIntent('agenda_oggi');
        }

        if (in_array($normalized, ['agenda domani', 'domani'], true)) {
            return new TelegramIntent('agenda_domani');
        }

        if ($normalized === 'pagamenti aperti') {
            return new TelegramIntent('pagamenti_aperti');
        }

        if ($normalized === 'reminder domani') {
            return new TelegramIntent(
                'reminder_domani',
                [],
                false
            );
        }

        if (preg_match('/^cerca cliente\s+(.+)$/iu', $text, $matches)) {
            return new TelegramIntent('cerca_cliente', [
                'cliente' => trim($matches[1]),
            ]);
        }

        if (preg_match('/^elimina appuntamento\s+(\d+)$/iu', $text, $matches)) {
            return new TelegramIntent(
                'elimina_appuntamento',
                ['appuntamento_id' => (int) $matches[1]],
                true,
                "Elimino l'appuntamento #{$matches[1]}"
            );
        }

        if (preg_match('/^segna pagato\s+(\d+)(?:\s+([\d\.,]+))?$/iu', $text, $matches)) {
            $importo = isset($matches[2]) ? (float) str_replace(',', '.', $matches[2]) : null;

            return new TelegramIntent(
                'segna_pagato',
                [
                    'pagamento_id' => (int) $matches[1],
                    'importo' => $importo,
                ],
                true,
                "Segno pagato il pagamento #{$matches[1]}" . ($importo ? " per € {$importo}" : '')
            );
        }

        if (preg_match('/^sposta appuntamento\s+(\d+)\s+(.+)\s+(\d{1,2}:\d{2})$/iu', $text, $matches)) {
            return new TelegramIntent(
                'sposta_appuntamento',
                [
                    'appuntamento_id' => (int) $matches[1],
                    'giorno' => trim($matches[2]),
                    'ora' => trim($matches[3]),
                ],
                true,
                "Sposto l'appuntamento #{$matches[1]} a {$matches[2]} {$matches[3]}"
            );
        }

        if (preg_match('/^crea call\s+(.+?)\s+(oggi|domani|\d{4}-\d{2}-\d{2})\s+(\d{1,2}:\d{2})$/iu', $text, $matches)) {
            return new TelegramIntent(
                'crea_call',
                [
                    'cliente' => trim($matches[1]),
                    'giorno' => trim($matches[2]),
                    'ora' => trim($matches[3]),
                ],
                true,
                "Creo una call con {$matches[1]} il {$matches[2]} alle {$matches[3]}"
            );
        }

        if (preg_match('/^crea appuntamento\s+(.+?)\s+(oggi|domani|\d{4}-\d{2}-\d{2})\s+(\d{1,2}:\d{2})$/iu', $text, $matches)) {
            return new TelegramIntent(
                'crea_appuntamento',
                [
                    'cliente' => trim($matches[1]),
                    'giorno' => trim($matches[2]),
                    'ora' => trim($matches[3]),
                ],
                true,
                "Creo un appuntamento con {$matches[1]} il {$matches[2]} alle {$matches[3]}"
            );
        }

        if (preg_match('/^crea consegna\s+(.+?)\s+(oggi|domani|\d{4}-\d{2}-\d{2})$/iu', $text, $matches)) {
            return new TelegramIntent(
                'crea_consegna',
                [
                    'cliente' => trim($matches[1]),
                    'giorno' => trim($matches[2]),
                ],
                true,
                "Creo una consegna programma per {$matches[1]} il {$matches[2]}"
            );
        }

        return null;
    }

    public function resolveDate(string $value): Carbon
    {
        $value = mb_strtolower(trim($value));

        return match ($value) {
            'oggi' => now(),
            'domani' => now()->addDay(),
            default => Carbon::parse($value),
        };
    }
}