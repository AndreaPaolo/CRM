<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramPollingService;
use Illuminate\Console\Command;

class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--sleep=1 : Secondi di pausa tra i cicli}';
    protected $description = 'Avvia il polling continuo del bot Telegram in locale';

    public function handle(TelegramPollingService $polling): int
    {
        $sleep = max(1, (int) $this->option('sleep'));

        $this->info('Polling Telegram avviato. Premi CTRL+C per fermarlo.');

        while (true) {
            try {
                $offset = $polling->getNextOffset();
                $processed = $polling->pollOnce($offset, 20);

                if ($processed > 0) {
                    $this->line("Messaggi processati: {$processed}");
                }
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                sleep(2);
            }

            sleep($sleep);
        }
    }
}