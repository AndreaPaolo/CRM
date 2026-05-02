<?php

namespace App\Services\Telegram;

use App\Support\Telegram\TelegramMessage;

class TelegramCommandService
{
    public function __construct(
        protected TelegramIntentParserService $parser,
        protected TelegramCrmActionService $crm,
    ) {}

    public function buildReply(TelegramMessage $message): string
    {
        $text = trim((string) $message->text);

        if ($text === '') {
            return 'Invia un comando testuale.';
        }

        $normalized = mb_strtolower($text);

        if (in_array($normalized, ['/start', 'start'], true)) {
            return $this->startMessage();
        }

        if (in_array($normalized, ['/help', 'help', 'aiuto'], true)) {
            return $this->helpMessage();
        }

        $intent = $this->parser->parse($text);

        if (! $intent) {
            return "Comando non riconosciuto.\n\nUsa /help per vedere gli esempi.";
        }

        try {
            return (string) $this->crm->execute($intent->name, $intent->params ?? []);
        } catch (\Throwable $e) {
            return 'Errore: ' . $e->getMessage();
        }
    }

    protected function startMessage(): string
    {
        return "Bot CRM attivo.\n\nUsa /help per vedere i comandi disponibili.";
    }

    protected function helpMessage(): string
    {
        return "Comandi disponibili:\n\n"
            . "1) crea personal mario rossi 28-04-2026 ore 19:00\n"
            . "2) modifica personal mario rossi 28-04-2026 descrizione check tecnica\n"
            . "3) elimina personal mario rossi 28-04-2026 ore 19:00\n"
            . "4) agenda oggi\n"
            . "5) agenda domani\n"
            . "6) agenda 28-04-2026\n"
            . "7) appuntamenti mario rossi\n"
            . "8) pagamenti aperti\n"
            . "9) pagamenti mario rossi\n"
            . "10) mario rossi pagato 123\n"
            . "11) assegna personal mensile mario rossi oggi\n"
            . "12) renew google\n"
            . "13) sync google\n"
            . "14) aggiorna abbonamenti mensili";
    }
}