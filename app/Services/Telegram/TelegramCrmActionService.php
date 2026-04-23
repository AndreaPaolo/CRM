<?php

namespace App\Services\Telegram;

use App\Models\Appuntamento;
use App\Models\Cliente;
use App\Models\Pagamento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TelegramCrmActionService
{
    public function __construct(
        protected TelegramIntentParserService $parser,
    ) {}

    public function execute(string $intentName, array $params = []): string
    {
        return match ($intentName) {
            'agenda_oggi' => $this->agenda(now()),
            'agenda_domani' => $this->agenda(now()->addDay()),
            'cerca_cliente' => $this->cercaCliente($params['cliente'] ?? ''),
            'pagamenti_aperti' => $this->pagamentiAperti(),
            'crea_appuntamento' => $this->creaAppuntamento($params, 'personal'),
            'crea_call' => $this->creaAppuntamento($params, 'call_google_meet'),
            'crea_consegna' => $this->creaConsegna($params),
            'sposta_appuntamento' => $this->spostaAppuntamento($params),
            'elimina_appuntamento' => $this->eliminaAppuntamento((int) ($params['appuntamento_id'] ?? 0)),
            'segna_pagato' => $this->segnaPagato($params),
            'reminder_domani' => $this->reminderDomani(),
            default => 'Comando non riconosciuto.',
        };
    }

    protected function agenda(Carbon $day): string
    {
        $items = Appuntamento::query()
            ->with(['cliente', 'abbonamento.servizio'])
            ->whereDate('data_ora', $day->toDateString())
            ->orderBy('data_ora')
            ->get();

        if ($items->isEmpty()) {
            return "Nessun appuntamento per il " . $day->format('d/m/Y') . '.';
        }

        $lines = ["Appuntamenti del " . $day->format('d/m/Y') . ':'];

        foreach ($items as $item) {
            $cliente = trim(($item->cliente?->nome ?? '') . ' ' . ($item->cliente?->cognome ?? ''));
            $servizio = $item->abbonamento?->servizio?->nome ?? '-';
            $ora = $item->evento_intera_giornata ? 'giornata intera' : $item->data_ora?->format('H:i');

            $lines[] = "#{$item->id} · {$ora} · {$cliente} · {$servizio}";
        }

        return implode("\n", $lines);
    }

    protected function cercaCliente(string $query): string
    {
        $query = trim($query);

        if ($query === '') {
            return 'Inserisci un nome cliente da cercare.';
        }

        $clienti = $this->searchClienti($query, 5);

        if ($clienti->isEmpty()) {
            return "Nessun cliente trovato per: {$query}";
        }

        $lines = ['Clienti trovati:'];

        foreach ($clienti as $cliente) {
            $abbonamento = $cliente->ultimoAbbonamentoAttivo();
            $servizio = $abbonamento?->servizio?->nome ?? 'nessun abbonamento attivo';

            $lines[] = "#{$cliente->id} · {$cliente->nome} {$cliente->cognome} · {$servizio}";
        }

        return implode("\n", $lines);
    }

    protected function pagamentiAperti(): string
    {
        $pagamenti = Pagamento::query()
            ->with(['cliente', 'abbonamento.servizio'])
            ->whereIn('stato', ['da_pagare', 'parziale'])
            ->orderBy('scadenza')
            ->limit(10)
            ->get();

        if ($pagamenti->isEmpty()) {
            return 'Nessun pagamento aperto.';
        }

        $lines = ['Pagamenti aperti:'];

        foreach ($pagamenti as $pagamento) {
            $cliente = trim(($pagamento->cliente?->nome ?? '') . ' ' . ($pagamento->cliente?->cognome ?? ''));
            $servizio = $pagamento->abbonamento?->servizio?->nome ?? '-';
            $importo = number_format((float) $pagamento->importo_previsto, 2, ',', '.');
            $scadenza = $pagamento->scadenza?->format('d/m/Y') ?? '-';

            $lines[] = "#{$pagamento->id} · {$cliente} · € {$importo} · {$scadenza} · {$servizio}";
        }

        return implode("\n", $lines);
    }

    protected function creaAppuntamento(array $params, string $tipo): string
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');
        $abbonamento = $cliente->ultimoAbbonamentoAttivo();

        if (! $abbonamento) {
            return "Cliente trovato, ma senza abbonamento attivo: {$cliente->nome} {$cliente->cognome}";
        }

        $giorno = $this->parser->resolveDate($params['giorno']);
        [$hour, $minute] = explode(':', $params['ora']);
        $dataOra = $giorno->copy()->setTime((int) $hour, (int) $minute);

        $appuntamento = Appuntamento::create([
            'cliente_id' => $cliente->id,
            'abbonamento_id' => $abbonamento->id,
            'user_id' => auth()->id() ?? 1,
            'data_ora' => $dataOra,
            'durata' => 60,
            'descrizione' => $tipo === 'call_google_meet' ? 'Creato da bot Telegram: call' : 'Creato da bot Telegram',
            'tipo_appuntamento' => $tipo,
            'evento_intera_giornata' => false,
        ]);

        return "Creato appuntamento #{$appuntamento->id} per {$cliente->nome} {$cliente->cognome} il {$dataOra->format('d/m/Y H:i')}.";
    }

    protected function creaConsegna(array $params): string
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');
        $abbonamento = $cliente->ultimoAbbonamentoAttivo();

        if (! $abbonamento) {
            return "Cliente trovato, ma senza abbonamento attivo: {$cliente->nome} {$cliente->cognome}";
        }

        $giorno = $this->parser->resolveDate($params['giorno'])->startOfDay();

        $appuntamento = Appuntamento::create([
            'cliente_id' => $cliente->id,
            'abbonamento_id' => $abbonamento->id,
            'user_id' => auth()->id() ?? 1,
            'data_ora' => $giorno,
            'durata' => 1440,
            'descrizione' => 'Creato da bot Telegram: consegna programma',
            'tipo_appuntamento' => 'consegna_programma',
            'evento_intera_giornata' => true,
        ]);

        return "Creata consegna programma #{$appuntamento->id} per {$cliente->nome} {$cliente->cognome} il {$giorno->format('d/m/Y')}.";
    }

    protected function spostaAppuntamento(array $params): string
    {
        $appuntamento = Appuntamento::query()->find((int) ($params['appuntamento_id'] ?? 0));

        if (! $appuntamento) {
            return 'Appuntamento non trovato.';
        }

        $giorno = $this->parser->resolveDate($params['giorno']);
        [$hour, $minute] = explode(':', $params['ora']);
        $dataOra = $giorno->copy()->setTime((int) $hour, (int) $minute);

        $appuntamento->update([
            'data_ora' => $dataOra,
        ]);

        return "Appuntamento #{$appuntamento->id} spostato al {$dataOra->format('d/m/Y H:i')}.";
    }

    protected function eliminaAppuntamento(int $id): string
    {
        $appuntamento = Appuntamento::query()->find($id);

        if (! $appuntamento) {
            return 'Appuntamento non trovato.';
        }

        $appuntamento->delete();

        return "Appuntamento #{$id} eliminato.";
    }

    protected function segnaPagato(array $params): string
    {
        $pagamento = Pagamento::query()->find((int) ($params['pagamento_id'] ?? 0));

        if (! $pagamento) {
            return 'Pagamento non trovato.';
        }

        $importo = $params['importo'] ?? null;
        $importoPagato = $importo ?: (float) $pagamento->importo_previsto;

        $pagamento->update([
            'importo_pagato' => $importoPagato,
        ]);

        $pagamento->aggiornaStato();

        return "Pagamento #{$pagamento->id} aggiornato. Stato: {$pagamento->stato}.";
    }

    protected function reminderDomani(): string
    {
        $items = Appuntamento::query()
            ->with(['cliente', 'abbonamento.servizio'])
            ->whereDate('data_ora', now()->addDay()->toDateString())
            ->orderBy('data_ora')
            ->get();

        if ($items->isEmpty()) {
            return 'Nessun reminder da preparare per domani.';
        }

        $lines = ['Reminder domani:'];

        foreach ($items as $item) {
            $telefono = preg_replace('/\D+/', '', (string) ($item->cliente?->telefono ?? ''));
            $cliente = trim(($item->cliente?->nome ?? '') . ' ' . ($item->cliente?->cognome ?? ''));
            $ora = $item->evento_intera_giornata ? 'giornata intera' : $item->data_ora?->format('H:i');
            $servizio = $item->abbonamento?->servizio?->nome ?? 'appuntamento';

            $messaggio = "Ciao {$cliente}, ti ricordo l'appuntamento di domani alle {$ora} per {$servizio}. A domani!";
            $url = $telefono ? 'https://wa.me/' . $telefono . '?text=' . urlencode($messaggio) : 'telefono mancante';

            $lines[] = "#{$item->id} · {$cliente} · {$url}";
        }

        return implode("\n", $lines);
    }

    protected function findClienteOrFail(string $query): Cliente
    {
        $query = trim($query);

        if ($query === '') {
            throw new \RuntimeException('Nome cliente mancante.');
        }

        $clienti = $this->searchClienti($query, 2);

        if ($clienti->isEmpty()) {
            throw new \RuntimeException("Cliente non trovato: {$query}");
        }

        if ($clienti->count() > 1) {
            $lista = $clienti
                ->take(3)
                ->map(fn (Cliente $cliente) => "#{$cliente->id} {$cliente->nome} {$cliente->cognome}")
                ->implode(' | ');

            throw new \RuntimeException("Ricerca ambigua per '{$query}'. Possibili match: {$lista}");
        }

        return $clienti->first();
    }

    protected function searchClienti(string $query, int $limit = 5): Collection
    {
        $query = trim($query);
        $tokens = collect(preg_split('/\s+/u', $query))
            ->filter()
            ->values();

        return Cliente::query()
            ->where(function (Builder $builder) use ($query, $tokens) {
                $builder
                    ->whereRaw("CONCAT(nome, ' ', cognome) LIKE ?", ['%' . $query . '%'])
                    ->orWhereRaw("CONCAT(cognome, ' ', nome) LIKE ?", ['%' . $query . '%'])
                    ->orWhere('nome', 'like', '%' . $query . '%')
                    ->orWhere('cognome', 'like', '%' . $query . '%');

                if ($tokens->isNotEmpty()) {
                    $builder->orWhere(function (Builder $sub) use ($tokens) {
                        foreach ($tokens as $token) {
                            $sub->where(function (Builder $inner) use ($token) {
                                $inner->where('nome', 'like', '%' . $token . '%')
                                    ->orWhere('cognome', 'like', '%' . $token . '%');
                            });
                        }
                    });
                }
            })
            ->orderBy('nome')
            ->orderBy('cognome')
            ->limit($limit)
            ->get();
    }
}