<?php

namespace App\Support\Telegram;

class TelegramIntent
{
    public function __construct(
        public string $name,
        public array $params = [],
    ) {}
}