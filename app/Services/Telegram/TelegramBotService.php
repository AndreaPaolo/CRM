<?php

namespace App\Services\Telegram;

use App\Models\TelegramUpdate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramBotService
{
    protected string $baseUrl;

    protected int $maxMessageLength = 3500;

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
        $chunks = $this->splitMessage($text, $this->maxMessageLength);
        $lastResponse = [];

        foreach ($chunks as $index => $chunk) {
            $payload = array_merge([
                'chat_id' => $chatId,
                'text' => $chunk,
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
                    'text' => $chunk,
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
                'text' => $chunk,
                'payload' => $payload,
                'success' => $response->successful(),
                'error' => $response->successful() ? null : $response->body(),
                'handled_at' => now(),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Errore Telegram sendMessage: ' . $response->body());
            }

            $lastResponse = $response->json();
        }

        return $lastResponse;
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

    protected function splitMessage(string $text, int $maxLength): array
    {
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return [$text];
        }

        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [$text];
        $chunks = [];
        $current = '';

        foreach ($lines as $line) {
            $line = rtrim($line);

            if ($current === '') {
                if (mb_strlen($line) <= $maxLength) {
                    $current = $line;
                    continue;
                }

                $chunks = array_merge($chunks, $this->splitHard($line, $maxLength));
                continue;
            }

            $candidate = $current . "\n" . $line;

            if (mb_strlen($candidate) <= $maxLength) {
                $current = $candidate;
                continue;
            }

            $chunks[] = $current;

            if (mb_strlen($line) <= $maxLength) {
                $current = $line;
            } else {
                $chunks = array_merge($chunks, $this->splitHard($line, $maxLength));
                $current = '';
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    protected function splitHard(string $text, int $maxLength): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $chunks[] = mb_substr($text, $start, $maxLength);
            $start += $maxLength;
        }

        return $chunks;
    }
}