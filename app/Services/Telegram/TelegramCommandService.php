<?php

namespace App\Services\Telegram;

use App\Support\Telegram\TelegramMessage;

class TelegramCommandService
{
    public function buildReply(TelegramMessage $message): string
    {
        $text = trim((string) $message->text);

        return match (true) {
            $text === '/start' => $this->startMessage(),
            $text === '/help' => $this->helpMessage(),
            $text === '/ping' => "✅ Bot attivo.\nServer raggiungibile.",
            $text === '/whoami' => "👤 Telegram user id: <code>{$message->userId}</code>\n💬 Chat id: <code>{$message->chatId}</code>",
            blank($text) => "Ricevuto, ma per ora V0 accetta solo messaggi testuali base.\nUsa /help.",
            default => "V0 attiva.\nHo ricevuto: <code>" . e($text) . "</code>\nPer ora uso solo comandi base. Usa /help.",
        };
    }

    protected function startMessage(): string
    {
        return "🤖 Bot CRM attivo.\n\n"
            . "Questa è la V0 stabile.\n"
            . "Posso già ricevere i tuoi messaggi e verificare che il collegamento col CRM funzioni.\n\n"
            . "Usa /help per i comandi disponibili.";
    }

    protected function helpMessage(): string
    {
        return "Comandi disponibili in V0:\n"
            . "/start\n"
            . "/help\n"
            . "/ping\n"
            . "/whoami";
    }
}