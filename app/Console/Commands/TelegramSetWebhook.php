<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Imposta il webhook Telegram sul dominio configurato';

    public function handle(TelegramBotService $bot): int
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $secret = (string) config('services.telegram.webhook_secret');

        if (blank($appUrl) || blank($secret)) {
            $this->error('APP_URL o TELEGRAM_WEBHOOK_SECRET mancanti.');
            return self::FAILURE;
        }

        $url = "{$appUrl}/telegram/webhook/{$secret}";

        $result = $bot->setWebhook($url);

        $this->info('Webhook impostato.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}