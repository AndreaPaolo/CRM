<?php

namespace App\Support\Telegram;

class TelegramIntent
{
    public function __construct(
        public readonly string $name,
        public readonly array $params = [],
        public readonly bool $requiresConfirmation = false,
        public readonly ?string $summary = null,
    ) {}
}