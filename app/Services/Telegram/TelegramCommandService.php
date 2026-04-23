<?php

namespace App\Services\Telegram;

use App\Support\Telegram\TelegramIntent;
use App\Support\Telegram\TelegramMessage;
use Throwable;

class TelegramCommandService
{
    public function __construct(
        protected TelegramIntentParserService $parser,
        protected TelegramConfirmationService $confirmation,
        protected TelegramCrmActionService $crm,
    ) {}

    public function buildReply(TelegramMessage $message): string
    {
        $text = trim((string) $message->text);
        $normalized = mb_strtolower($text);

        if ($text === '/start') {
            return $this->startMessage();
        }

        if ($text === '/help') {
            return $this->helpMessage();
        }

        if ($text === '/ping') {
            return "✅ Bot attivo.\nV1 testuale pronta.";
        }

        if ($text === '/whoami') {
            return "👤 Telegram user id: <code>{$message->userId}</code>\n💬 Chat id: <code>{$message->chatId}</code>";
        }

        if (in_array($normalized, ['si', 'conferma'], true)) {
            return $this->confirmPending($message);
        }

        if (in_array($normalized, ['annulla', 'no'], true)) {
            return $this->cancelPending($message);
        }

        $intent = $this->parser->parse($text);

        if (! $intent) {
            return "Non ho capito il comando.\nUsa /help per gli esempi.";
        }

        if ($intent->requiresConfirmation) {
            $this->confirmation->put((string) $message->userId, [
                'intent' => $intent->name,
                'params' => $intent->params,
            ]);

            return "Conferma richiesta:\n{$intent->summary}\n\nRispondi con <b>SI</b> oppure <b>annulla</b>.";
        }

        try {
            return $this->crm->execute($intent->name, $intent->params);
        } catch (Throwable $e) {
            return 'Errore: ' . $e->getMessage();
        }
    }

    protected function confirmPending(TelegramMessage $message): string
    {
        $pending = $this->confirmation->get((string) $message->userId);

        if (! $pending) {
            return 'Nessuna azione in attesa di conferma.';
        }

        $this->confirmation->forget((string) $message->userId);

        try {
            return $this->crm->execute($pending['intent'], $pending['params'] ?? []);
        } catch (Throwable $e) {
            return 'Errore: ' . $e->getMessage();
        }
    }

    protected function cancelPending(TelegramMessage $message): string
    {
        $this->confirmation->forget((string) $message->userId);

        return 'Azione annullata.';
    }

    protected function startMessage(): string
    {
        return "🤖 Bot CRM attivo.\n\n"
            . "V1 testuale disponibile.\n"
            . "Posso leggere agenda, creare/spostare appuntamenti, controllare pagamenti e preparare reminder.\n\n"
            . "Usa /help per vedere gli esempi.";
    }

    protected function helpMessage(): string
    {
        return "Comandi V1:\n\n"
            . "agenda oggi\n"
            . "agenda domani\n"
            . "cerca cliente Mario Rossi\n"
            . "pagamenti aperti\n"
            . "crea appuntamento Mario Rossi domani 15:00\n"
            . "crea call Mario Rossi 2026-04-10 18:00\n"
            . "crea consegna Mario Rossi domani\n"
            . "sposta appuntamento 123 domani 16:00\n"
            . "elimina appuntamento 123\n"
            . "segna pagato 45\n"
            . "segna pagato 45 120\n"
            . "reminder domani\n\n"
            . "Le azioni che modificano il CRM chiedono conferma con SI.";
    }
}