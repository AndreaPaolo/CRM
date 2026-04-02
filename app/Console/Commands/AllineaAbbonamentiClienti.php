<?php

namespace App\Console\Commands;

use App\Models\Abbonamento;
use Illuminate\Console\Command;

class AllineaAbbonamentiClienti extends Command
{
    protected $signature = 'crm:allinea-abbonamenti-clienti';
    protected $description = 'Allinea abbonamento_cliente usando cliente_id legacy';

    public function handle(): int
    {
        $abbonamenti = Abbonamento::query()->whereNotNull('cliente_id')->get();

        foreach ($abbonamenti as $abbonamento) {
            $abbonamento->clienti()->syncWithoutDetaching([$abbonamento->cliente_id]);
        }

        $this->info('Allineamento completato.');

        return self::SUCCESS;
    }
}