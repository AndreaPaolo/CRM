<?php

namespace App\Services\Telegram;

use App\Models\TelegramUpdate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramBotService
{
    protected string $baseUrl;

    public function __construct()
    {
        $token = (string) config('services.telegram.bot_token');

        if (blank($token)) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN mancante.');
        }

        $this->baseUrl = "https://api.telegram.org/bot{$token}";
    }

    public function sendMessage(string $chatId, string $text, array $extra = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ], $extra);

        try {
            $response = Http::connectTimeout(15)
                ->timeout(45)
                ->retry(3, 1000)
                ->post($this->baseUrl . '/sendMessage', $payload);
        } catch (ConnectionException $e) {
            TelegramUpdate::create([
                'direction' => 'outbound',
                'kind' => 'message',
                'chat_id' => $chatId,
                'text' => $text,
                'payload' => $payload,
                'success' => false,
                'error' => $e->getMessage(),
                'handled_at' => now(),
            ]);

            throw new RuntimeException('Errore Telegram sendMessage: ' . $e->getMessage());
        }

        TelegramUpdate::create([
            'direction' => 'outbound',
            'kind' => 'message',
            'chat_id' => $chatId,
            'text' => $text,
            'payload' => $payload,
            'success' => $response->successful(),
            'error' => $response->successful() ? null : $response->body(),
            'handled_at' => now(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Errore Telegram sendMessage: ' . $response->body());
        }

        return $response->json();
    }

    public function getMe(): array
    {
        $response = Http::connectTimeout(15)
            ->timeout(30)
            ->retry(2, 1000)
            ->get($this->baseUrl . '/getMe');

        if (! $response->successful()) {
            throw new RuntimeException('Errore getMe: ' . $response->body());
        }

        return $response->json();
    }

    public function deleteWebhook(): array
    {
        $response = Http::connectTimeout(15)
            ->timeout(30)
            ->retry(2, 1000)
            ->post($this->baseUrl . '/deleteWebhook');

        if (! $response->successful()) {
            throw new RuntimeException('Errore deleteWebhook: ' . $response->body());
        }

        return $response->json();
    }
}