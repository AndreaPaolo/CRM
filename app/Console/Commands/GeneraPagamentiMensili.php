<?php

namespace App\Console\Commands;

use App\Models\Abbonamento;
use App\Services\PagamentoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GeneraPagamentiMensili extends Command
{
    protected $signature = 'pagamenti:genera-mensili {--month=} {--year=}';

    protected $description = 'Genera i pagamenti mensili per gli abbonamenti a costo orario';

    public function handle(PagamentoService $pagamentoService): int
    {
        $month = (int) ($this->option('month') ?: now()->month);
        $year = (int) ($this->option('year') ?: now()->year);

        $inizioPeriodo = Carbon::create($year, $month, 1)->startOfMonth();
        $finePeriodo = Carbon::create($year, $month, 1)->endOfMonth();

        $abbonamenti = Abbonamento::query()
            ->with(['servizio', 'cliente'])
            ->whereHas('servizio', fn ($q) => $q->where('tipo_fatturazione', 'mensile'))
            ->get();

        $creatiOAggiornati = 0;

        foreach ($abbonamenti as $abbonamento) {
            $pagamento = $pagamentoService->generaPagamentoMensile(
                $abbonamento,
                $inizioPeriodo,
                $finePeriodo
            );

            if ($pagamento) {
                $creatiOAggiornati++;
            }
        }

        $this->info("Pagamenti mensili creati/aggiornati: {$creatiOAggiornati}");

        return self::SUCCESS;
    }
}