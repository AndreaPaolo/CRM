<?php

namespace App\Console\Commands;

use App\Models\Abbonamento;
use App\Models\Pagamento;
use App\Services\PagamentoService;
use Illuminate\Console\Command;

class AllineaPagamentiAbbonamentiEsistenti extends Command
{
    protected $signature = 'pagamenti:allinea-abbonamenti-esistenti 
                            {--abbonamento_id= : Limita a un singolo abbonamento}
                            {--mensili : Include anche gli abbonamenti mensili}';

    protected $description = 'Genera i pagamenti mancanti per gli abbonamenti già esistenti';

    public function handle(PagamentoService $pagamentoService): int
    {
        $includeMensili = (bool) $this->option('mensili');

        $query = Abbonamento::query()
            ->with(['servizio', 'cliente']);

        if ($this->option('abbonamento_id')) {
            $query->where('id', (int) $this->option('abbonamento_id'));
        }

        $abbonamenti = $query->get();

        if ($abbonamenti->isEmpty()) {
            $this->warn('Nessun abbonamento trovato.');
            return self::SUCCESS;
        }

        $creati = 0;
        $saltati = 0;

        foreach ($abbonamenti as $abbonamento) {
            if (! $abbonamento->servizio) {
                $saltati++;
                $this->warn("Abbonamento {$abbonamento->id} saltato: servizio mancante.");
                continue;
            }

            $tipoFatturazione = $abbonamento->servizio->tipo_fatturazione ?? 'pacchetto';

            if ($tipoFatturazione === 'mensile' && ! $includeMensili) {
                $saltati++;
                continue;
            }

            $esistonoPagamenti = Pagamento::query()
                ->where('abbonamento_id', $abbonamento->id)
                ->exists();

            if ($esistonoPagamenti) {
                $saltati++;
                continue;
            }

            if ($tipoFatturazione === 'pacchetto') {
                $pagamentoService->generaPagamentiPacchetto(
                    $abbonamento,
                    false,
                    null,
                    null
                );

                $creati++;
                $this->info("Creati pagamenti pacchetto per abbonamento {$abbonamento->id}");
                continue;
            }

            $saltati++;
        }

        $this->newLine();
        $this->info("Allineamento completato.");
        $this->info("Abbonamenti con pagamenti creati: {$creati}");
        $this->info("Abbonamenti saltati: {$saltati}");

        return self::SUCCESS;
    }
}