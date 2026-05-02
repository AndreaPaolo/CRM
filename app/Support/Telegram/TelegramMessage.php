<?php

namespace App\Support\Telegram;

class TelegramMessage
{
    public function __construct(
        public readonly ?int $updateId,
        public readonly ?string $chatId,
        public readonly ?string $userId,
        public readonly ?string $messageId,
        public readonly ?string $text,
        public readonly ?string $voiceFileId,
        public readonly ?string $audioFileId,
        public readonly array $payload,
    ) {}
}