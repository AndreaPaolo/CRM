<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;

class TelegramSelectionStateService
{
    public function put(string $userId, array $payload): void
    {
        Cache::put($this->key($userId), $payload, now()->addMinutes(10));
    }

    public function get(string $userId): ?array
    {
        return Cache::get($this->key($userId));
    }

    public function forget(string $userId): void
    {
        Cache::forget($this->key($userId));
    }

    protected function key(string $userId): string
    {
        return 'telegram_selection_state_' . $userId;
    }
}