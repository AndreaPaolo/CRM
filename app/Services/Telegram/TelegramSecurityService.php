<?php

namespace App\Services\Telegram;

class TelegramSecurityService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.telegram.enabled', false);
    }

    public function userIsAllowed(?string $userId): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! $userId) {
            return false;
        }

        $allowed = config('services.telegram.allowed_user_ids', []);

        if (empty($allowed)) {
            return false;
        }

        return in_array((string) $userId, array_map('strval', $allowed), true);
    }
}