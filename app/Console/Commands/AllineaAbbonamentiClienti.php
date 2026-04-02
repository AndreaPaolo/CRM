<?php

namespace App\Console\Commands;

use App\Models\Abbonamento;
use Illuminate\Console\Command;

class AllineaAbbonamentiClienti extends Command
{
    protected $signature = 'crm:allinea-abbonamenti-clienti';
    protected $description = 'Allinea la pivot abbonamento_cliente usando il vecchio cliente_id';

    public function handle(): int
    {
        $abbonamenti = Abbonamento::query()
            ->whereNotNull('cliente_id')
            ->get();

        foreach ($abbonamenti as $abbonamento) {
            if (! $abbonamento->cliente_id) {
                continue;
            }

            $abbonamento->clienti()->syncWithoutDetaching([$abbonamento->cliente_id]);
        }

        $this->info('Pivot abbonamento_cliente allineata correttamente.');

        return self::SUCCESS;
    }
}