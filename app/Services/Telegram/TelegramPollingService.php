<?php

namespace App\Services\Telegram;

use App\Models\TelegramUpdate;
use App\Support\Telegram\TelegramMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function pollOnce(?int $offset = null, int $timeout = 20): int
    {
        Log::info('Telegram pollOnce start', [
            'offset' => $offset,
            'timeout' => $timeout,
            'enabled' => $this->security->isEnabled(),
        ]);

        if (! $this->security->isEnabled()) {
            Log::warning('Telegram disabilitato da config');
            return 0;
        }

        $params = [
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'edited_message']),
        ];

        if ($offset !== null) {
            $params['offset'] = $offset;
        }

        try {
            $response = Http::connectTimeout(15)
                ->timeout($timeout + 20)
                ->retry(2, 1000)
                ->get($this->baseUrl . '/getUpdates', $params);
        } catch (ConnectionException $e) {
            Log::error('Telegram getUpdates connection error', [
                'message' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'cURL error 28')) {
                return 0;
            }

            throw $e;
        }

        Log::info('Telegram getUpdates response', [
            'successful' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Errore Telegram getUpdates: ' . $response->body());
        }

        $json = $response->json();

        if (! ($json['ok'] ?? false)) {
            throw new \RuntimeException('Risposta Telegram non valida: ' . json_encode($json));
        }

        $processed = 0;

        foreach (($json['result'] ?? []) as $update) {
            Log::info('Telegram update ricevuto', [
                'update_id' => $update['update_id'] ?? null,
            ]);

            $this->handleUpdate($update);
            $processed++;
        }

        Log::info('Telegram pollOnce end', [
            'processed' => $processed,
        ]);

        return $processed;
    }

    public function getNextOffset(): ?int
    {
        $last = TelegramUpdate::query()
            ->whereNotNull('telegram_update_id')
            ->max('telegram_update_id');

        $offset = $last ? ((int) $last + 1) : null;

        Log::info('Telegram getNextOffset', [
            'last' => $last,
            'next_offset' => $offset,
        ]);

        return $offset;
    }

    protected function handleUpdate(array $payload): void
    {
        $messagePayload = $payload['message'] ?? $payload['edited_message'] ?? null;

        if (! $messagePayload) {
            Log::warning('Update senza message payload', ['payload' => $payload]);
            return;
        }

        $message = new TelegramMessage(
            updateId: $payload['update_id'] ?? null,
            chatId: isset($messagePayload['chat']['id']) ? (string) $messagePayload['chat']['id'] : null,
            userId: isset($messagePayload['from']['id']) ? (string) $messagePayload['from']['id'] : null,
            messageId: isset($messagePayload['message_id']) ? (string) $messagePayload['message_id'] : null,
            text: $messagePayload['text'] ?? $messagePayload['caption'] ?? null,
            voiceFileId: $messagePayload['voice']['file_id'] ?? null,
            audioFileId: $messagePayload['audio']['file_id'] ?? null,
            payload: $payload,
        );

        Log::info('Telegram message parsed', [
            'update_id' => $message->updateId,
            'chat_id' => $message->chatId,
            'user_id' => $message->userId,
            'message_id' => $message->messageId,
            'text' => $message->text,
            'has_voice' => (bool) $message->voiceFileId,
            'has_audio' => (bool) $message->audioFileId,
        ]);

        $alreadyProcessed = TelegramUpdate::query()
            ->where('direction', 'inbound')
            ->where('telegram_update_id', $message->updateId)
            ->exists();

        if ($alreadyProcessed) {
            Log::warning('Update già processato', [
                'update_id' => $message->updateId,
            ]);
            return;
        }

        TelegramUpdate::create([
            'telegram_update_id' => $message->updateId,
            'direction' => 'inbound',
            'kind' => 'message',
            'chat_id' => $message->chatId,
            'telegram_user_id' => $message->userId,
            'telegram_message_id' => $message->messageId,
            'text' => $message->text ?: ($message->voiceFileId ? '[voice]' : ($message->audioFileId ? '[audio]' : null)),
            'payload' => $payload,
            'success' => true,
            'handled_at' => now(),
        ]);

        if (! $this->security->userIsAllowed($message->userId)) {
            Log::warning('Utente non autorizzato', [
                'user_id' => $message->userId,
                'allowed' => config('services.telegram.allowed_user_ids'),
            ]);

            if ($message->chatId) {
                try {
                    $this->bot->sendMessage($message->chatId, 'Utente non autorizzato.');
                } catch (\Throwable $e) {
                    Log::error('Errore invio messaggio utente non autorizzato', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
            return;
        }

        if (! $message->chatId || ! $message->userId) {
            Log::warning('Message senza chat_id o user_id');
            return;
        }

        if ($message->voiceFileId || $message->audioFileId) {
            Log::info('Messaggio audio rifiutato');

            try {
                $this->bot->sendMessage($message->chatId, 'I messaggi vocali non sono supportati. Inviami un comando testuale.');
            } catch (\Throwable $e) {
                Log::error('Errore invio risposta audio non supportato', [
                    'message' => $e->getMessage(),
                ]);
            }
            return;
        }

        try {
            $reply = $this->commands->buildReply($message);

            Log::info('Reply generata', [
                'reply' => $reply,
            ]);

            $this->bot->sendMessage($message->chatId, $reply);
        } catch (\Throwable $e) {
            Log::error('Errore handleUpdate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                $this->bot->sendMessage($message->chatId, 'Errore: ' . $e->getMessage());
            } catch (\Throwable $e2) {
                Log::error('Errore invio messaggio errore', [
                    'message' => $e2->getMessage(),
                ]);
            }
        }
    }
}