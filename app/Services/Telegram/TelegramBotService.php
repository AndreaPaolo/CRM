<?php

namespace App\Services\Telegram;

use App\Models\TelegramUpdate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramBotService
{
    protected string $baseUrl;

    public function __construct()
    {
        $token = (string) config('services.telegram.bot_token');

        if (blank($token)) {
            throw new \RuntimeException('TELEGRAM_BOT_TOKEN mancante.');
        }

        $this->baseUrl = "https://api.telegram.org/bot{$token}";
    }

    public function sendMessage(string $chatId, string $text, array $extra = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $extra);

        $response = Http::timeout(15)->post($this->baseUrl . '/sendMessage', $payload);

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
            throw new \RuntimeException('Errore Telegram sendMessage: ' . $response->body());
        }

        return $response->json();
    }

    public function setWebhook(string $url): array
    {
        $response = Http::timeout(20)->post($this->baseUrl . '/setWebhook', [
            'url' => $url,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Errore setWebhook: ' . $response->body());
        }

        return $response->json();
    }

    public function deleteWebhook(): array
    {
        $response = Http::timeout(20)->post($this->baseUrl . '/deleteWebhook');

        if (! $response->successful()) {
            throw new \RuntimeException('Errore deleteWebhook: ' . $response->body());
        }

        return $response->json();
    }

    public function getMe(): array
    {
        $response = Http::timeout(15)->get($this->baseUrl . '/getMe');

        if (! $response->successful()) {
            throw new \RuntimeException('Errore getMe: ' . $response->body());
        }

        return $response->json();
    }
}