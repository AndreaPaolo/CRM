<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramPollingService;
use Illuminate\Console\Command;

class TelegramPollOnce extends Command
{
    protected $signature = 'telegram:poll-once';
    protected $description = 'Esegue un singolo ciclo di polling Telegram';

    public function handle(TelegramPollingService $polling): int
    {
        try {
            $offset = $polling->getNextOffset();
            $processed = $polling->pollOnce($offset, 1);

            $this->info("Messaggi processati: {$processed}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}