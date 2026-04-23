<?php

namespace App\Services\Telegram;

use App\Models\TelegramUpdate;
use App\Support\Telegram\TelegramMessage;
use Illuminate\Support\Facades\Http;

class TelegramPollingService
{
    protected string $baseUrl;

    public function __construct(
        protected TelegramSecurityService $security,
        protected TelegramBotService $bot,
        protected TelegramCommandService $commands,
    ) {
        $token = (string) config('services.telegram.bot_token');

        if (blank($token)) {
            throw new \RuntimeException('TELEGRAM_BOT_TOKEN mancante.');
        }

        $this->baseUrl = "https://api.telegram.org/bot{$token}";
    }

    public function pollOnce(?int $offset = null, int $timeout = 10): int
    {
        if (! $this->security->isEnabled()) {
            return 0;
        }

        $params = [
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'edited_message']),
        ];

        if ($offset !== null) {
            $params['offset'] = $offset;
        }

        $response = Http::timeout($timeout + 5)
            ->get($this->baseUrl . '/getUpdates', $params);

        if (! $response->successful()) {
            throw new \RuntimeException('Errore Telegram getUpdates: ' . $response->body());
        }

        $json = $response->json();

        if (! ($json['ok'] ?? false)) {
            throw new \RuntimeException('Risposta Telegram non valida: ' . json_encode($json));
        }

        $processed = 0;

        foreach (($json['result'] ?? []) as $update) {
            $this->handleUpdate($update);
            $processed++;
        }

        return $processed;
    }

    public function getNextOffset(): ?int
    {
        $last = TelegramUpdate::query()
            ->whereNotNull('telegram_update_id')
            ->max('telegram_update_id');

        return $last ? ((int) $last + 1) : null;
    }

    protected function handleUpdate(array $payload): void
    {
        $messagePayload = $payload['message'] ?? $payload['edited_message'] ?? null;

        if (! $messagePayload) {
            return;
        }

        $message = new TelegramMessage(
            updateId: $payload['update_id'] ?? null,
            chatId: isset($messagePayload['chat']['id']) ? (string) $messagePayload['chat']['id'] : null,
            userId: isset($messagePayload['from']['id']) ? (string) $messagePayload['from']['id'] : null,
            messageId: isset($messagePayload['message_id']) ? (string) $messagePayload['message_id'] : null,
            text: $messagePayload['text'] ?? null,
            payload: $payload,
        );

        $alreadyProcessed = TelegramUpdate::query()
            ->where('direction', 'inbound')
            ->where('telegram_update_id', $message->updateId)
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        TelegramUpdate::create([
            'telegram_update_id' => $message->updateId,
            'direction' => 'inbound',
            'kind' => 'message',
            'chat_id' => $message->chatId,
            'telegram_user_id' => $message->userId,
            'telegram_message_id' => $message->messageId,
            'text' => $message->text,
            'payload' => $payload,
            'success' => true,
            'handled_at' => now(),
        ]);

        if (! $this->security->userIsAllowed($message->userId)) {
            if ($message->chatId) {
                $this->bot->sendMessage($message->chatId, '⛔ Utente non autorizzato.');
            }

            return;
        }

        if ($message->chatId) {
            $reply = $this->commands->buildReply($message);
            $this->bot->sendMessage($message->chatId, $reply);
        }
    }
}