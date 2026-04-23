<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramGetMe extends Command
{
    protected $signature = 'telegram:get-me';

    protected $description = 'Verifica che il bot Telegram risponda';

    public function handle(TelegramBotService $bot): int
    {
        $result = $bot->getMe();

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}