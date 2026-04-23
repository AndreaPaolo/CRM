<?php

namespace App\Services\Telegram;

class TelegramSecurityService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.telegram.enabled');
    }

    public function secretIsValid(?string $secret): bool
    {
        $expected = (string) config('services.telegram.webhook_secret');

        return filled($expected) && hash_equals($expected, (string) $secret);
    }

    public function userIsAllowed(?string $telegramUserId): bool
    {
        $allowed = config('services.telegram.allowed_user_ids', []);

        if (empty($allowed)) {
            return false;
        }

        return in_array((string) $telegramUserId, array_map('strval', $allowed), true);
    }
}