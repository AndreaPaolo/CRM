<?php

namespace App\Services\Telegram;

use App\Models\Abbonamento;
use App\Models\Appuntamento;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Servizio;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class TelegramCrmActionService
{
    public function __construct(
        protected TelegramIntentParserService $parser,
    ) {}

    public function execute(string $intentName, array $params = []): array|string
    {
        return match ($intentName) {
            'agenda_oggi' => $this->agenda(now()),
            'agenda_domani' => $this->agenda(now()->addDay()),
            'agenda_data' => $this->agendaData($params),

            'crea_appuntamento' => $this->creaAppuntamento($params, 'personal'),
            'crea_call' => $this->creaAppuntamento($params, 'call_google_meet'),
            'crea_consegna' => $this->creaConsegna($params),
            'modifica_appuntamento' => $this->modificaAppuntamentoContext($params),
            'elimina_appuntamento_ctx' => $this->eliminaAppuntamentoContext($params),

            'lista_prossimi_appuntamenti_cliente' => $this->listaProssimiAppuntamentiCliente($params),

            'pagamenti_aperti' => $this->pagamentiAperti(),
            'pagamenti_cliente' => $this->pagamentiCliente($params),
            'segna_pagato_cliente' => $this->segnaPagatoCliente($params),

            'assegna_abbonamento' => $this->assegnaAbbonamento($params),
            'renew_google' => $this->renewGoogle(),
            'sync_google_status' => $this->syncGoogleStatus(),
            'aggiorna_abbonamenti_mensili' => $this->aggiornaAbbonamentiMensili(),

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
            return "Nessun appuntamento per il {$day->format('d/m/Y')}.";
        }

        $lines = ["Agenda {$day->format('d/m/Y')}:"];

        foreach ($items as $item) {
            $cliente = trim(($item->cliente?->nome ?? '') . ' ' . ($item->cliente?->cognome ?? ''));
            $tipo = $this->formatTipo($item->tipo_appuntamento);
            $ora = $item->evento_intera_giornata ? 'giornata intera' : $item->data_ora?->format('H:i');
            $numeroLezione = $this->formatNumeroLezione($item);

            $lines[] = "#{$item->id} · {$ora} · {$tipo} · {$cliente} · {$numeroLezione}";
        }

        return implode("\n", $lines);
    }

    protected function agendaData(array $params): string
    {
        $data = $params['data'] ?? null;

        if (! $data) {
            return 'Data mancante.';
        }

        return $this->agenda($this->parser->resolveDate($data));
    }

    protected function creaAppuntamento(array $params, string $tipo): string
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');
        $abbonamento = method_exists($cliente, 'ultimoAbbonamentoAttivo')
            ? $cliente->ultimoAbbonamentoAttivo()
            : null;

        if (! $abbonamento) {
            return "Cliente trovato, ma senza abbonamento attivo: {$cliente->nome} {$cliente->cognome}";
        }

        $data = $params['data'] ?? null;
        $ora = $params['ora'] ?? null;

        if (! $data || ! $ora) {
            return 'Data o ora mancanti.';
        }

        $giorno = $this->parser->resolveDate($data);
        [$hour, $minute] = explode(':', $ora);
        $dataOra = $giorno->copy()->setTime((int) $hour, (int) $minute);

        if ($this->isSmallGroupAbbonamento($abbonamento)) {
            $partecipanti = $this->getPartecipantiAbbonamento($abbonamento);

            if ($partecipanti->isEmpty()) {
                $partecipanti = collect([$cliente]);
            }

            $uuid = (string) Str::uuid();
            $creati = collect();

            foreach ($partecipanti as $partecipante) {
                $abbonamentoPartecipante = method_exists($partecipante, 'ultimoAbbonamentoAttivo')
                    ? ($partecipante->ultimoAbbonamentoAttivo() ?: $abbonamento)
                    : $abbonamento;

                $appuntamento = Appuntamento::create([
                    'cliente_id' => $partecipante->id,
                    'abbonamento_id' => $abbonamentoPartecipante?->id ?? $abbonamento->id,
                    'user_id' => auth()->id() ?? 1,
                    'data_ora' => $dataOra,
                    'durata' => 60,
                    'descrizione' => 'Creato da bot Telegram: smallgroup',
                    'tipo_appuntamento' => $tipo,
                    'evento_intera_giornata' => false,
                    'sessione_condivisa_uuid' => $uuid,
                ]);

                $creati->push($appuntamento);
            }

            $nomi = $partecipanti
                ->map(fn ($c) => trim(($c->nome ?? '') . ' ' . ($c->cognome ?? '')))
                ->implode(', ');

            return "Creato smallgroup {$this->formatTipo($tipo)} per {$creati->count()} partecipanti il {$dataOra->format('d/m/Y H:i')}: {$nomi}.";
        }

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

        return "Creato {$this->formatTipo($tipo)} #{$appuntamento->id} per {$cliente->nome} {$cliente->cognome} il {$dataOra->format('d/m/Y H:i')}.";
    }

    protected function creaConsegna(array $params): string
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');
        $abbonamento = method_exists($cliente, 'ultimoAbbonamentoAttivo')
            ? $cliente->ultimoAbbonamentoAttivo()
            : null;

        if (! $abbonamento) {
            return "Cliente trovato, ma senza abbonamento attivo: {$cliente->nome} {$cliente->cognome}";
        }

        $data = $params['data'] ?? null;

        if (! $data) {
            return 'Data mancante.';
        }

        $giorno = $this->parser->resolveDate($data)->startOfDay();

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

    protected function modificaAppuntamentoContext(array $params): string
    {
        $appuntamento = $this->findSingleAppointmentOrFail($params);

        $updates = [];

        if (! empty($params['descrizione'])) {
            $updates['descrizione'] = $params['descrizione'];
        }

        if (! empty($params['durata'])) {
            $updates['durata'] = (int) $params['durata'];
        }

        if (! empty($params['nuova_ora'])) {
            [$hour, $minute] = explode(':', $params['nuova_ora']);
            $updates['data_ora'] = $appuntamento->data_ora->copy()->setTime((int) $hour, (int) $minute);
        }

        if (empty($updates)) {
            return 'Nessuna modifica da applicare.';
        }

        if ($appuntamento->sessione_condivisa_uuid) {
            Appuntamento::query()
                ->where('sessione_condivisa_uuid', $appuntamento->sessione_condivisa_uuid)
                ->update($updates);

            return "Sessione smallgroup aggiornata ({$appuntamento->sessione_condivisa_uuid}).";
        }

        $appuntamento->update($updates);

        return "Appuntamento #{$appuntamento->id} modificato con successo.";
    }

    protected function eliminaAppuntamentoContext(array $params): string
    {
        $appuntamento = $this->findSingleAppointmentOrFail($params);

        if ($appuntamento->sessione_condivisa_uuid) {
            $count = Appuntamento::query()
                ->where('sessione_condivisa_uuid', $appuntamento->sessione_condivisa_uuid)
                ->count();

            Appuntamento::query()
                ->where('sessione_condivisa_uuid', $appuntamento->sessione_condivisa_uuid)
                ->get()
                ->each
                ->delete();

            return "Eliminata sessione smallgroup con {$count} appuntamenti collegati.";
        }

        $id = $appuntamento->id;
        $appuntamento->delete();

        return "Appuntamento #{$id} eliminato.";
    }

    protected function listaProssimiAppuntamentiCliente(array $params): string
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');

        $items = Appuntamento::query()
            ->with(['abbonamento.servizio'])
            ->where('cliente_id', $cliente->id)
            ->where('data_ora', '>=', now())
            ->orderBy('data_ora')
            ->get();

        if ($items->isEmpty()) {
            return "Nessun appuntamento futuro per {$cliente->nome} {$cliente->cognome}.";
        }

        $lines = ["Appuntamenti di {$cliente->nome} {$cliente->cognome}:"];

        foreach ($items as $item) {
            $numeroLezione = $this->formatNumeroLezione($item);
            $lines[] = "#{$item->id} · {$item->data_ora->format('d/m/Y H:i')} · {$this->formatTipo($item->tipo_appuntamento)} · {$numeroLezione}";
        }

        return implode("\n", $lines);
    }

    protected function pagamentiAperti(): string
    {
        $pagamenti = Pagamento::query()
            ->with(['cliente', 'abbonamento.servizio'])
            ->whereIn('stato', ['da_pagare', 'parziale', 'pagato'])
            ->orderBy('scadenza')
            ->get();

        if ($pagamenti->isEmpty()) {
            return 'Nessun pagamento trovato.';
        }

        $lines = ['Pagamenti:'];

        foreach ($pagamenti as $pagamento) {
            $cliente = trim(($pagamento->cliente?->nome ?? '') . ' ' . ($pagamento->cliente?->cognome ?? ''));
            $abbonamentoNome = $pagamento->abbonamento?->servizio?->nome ?? '-';
            $importo = number_format((float) ($pagamento->importo_previsto ?? 0), 2, ',', '.');
            $scadenza = $pagamento->scadenza?->format('d/m/Y') ?? '-';
            $stato = $pagamento->stato ?? '-';

            $lines[] = "#{$pagamento->id} · {$cliente} · {$abbonamentoNome} · € {$importo} · {$scadenza} · {$stato}";
        }

        return implode("\n", $lines);
    }

    protected function pagamentiCliente(array $params): string
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');

        $this->aggiornaImportoPersonalMensileSeNecessario($cliente);

        $pagamenti = Pagamento::query()
            ->with(['abbonamento.servizio'])
            ->where('cliente_id', $cliente->id)
            ->whereIn('stato', ['da_pagare', 'parziale', 'pagato'])
            ->orderBy('scadenza')
            ->get();

        if ($pagamenti->isEmpty()) {
            return "Nessun pagamento trovato per {$cliente->nome} {$cliente->cognome}.";
        }

        $lines = ["Pagamenti di {$cliente->nome} {$cliente->cognome}:"];

        foreach ($pagamenti as $pagamento) {
            $abbonamentoNome = $pagamento->abbonamento?->servizio?->nome ?? '-';
            $importo = number_format((float) ($pagamento->importo_previsto ?? 0), 2, ',', '.');
            $scadenza = $pagamento->scadenza?->format('d/m/Y') ?? '-';
            $stato = $pagamento->stato ?? '-';

            $lines[] = "#{$pagamento->id} · {$cliente->nome} {$cliente->cognome} · {$abbonamentoNome} · € {$importo} · {$scadenza} · {$stato}";
        }

        return implode("\n", $lines);
    }

    protected function segnaPagatoCliente(array $params): string
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');
        $pagamentoId = (int) ($params['pagamento_id'] ?? 0);

        $pagamento = Pagamento::query()
            ->where('id', $pagamentoId)
            ->where('cliente_id', $cliente->id)
            ->first();

        if (! $pagamento) {
            return "Pagamento non trovato per {$cliente->nome} {$cliente->cognome}: {$pagamentoId}";
        }

        $pagamento->update([
            'importo_pagato' => (float) $pagamento->importo_previsto,
            'stato' => 'pagato',
        ]);

        if (method_exists($pagamento, 'aggiornaStato')) {
            $pagamento->aggiornaStato();
        }

        return "Pagamento #{$pagamento->id} segnato come pagato per {$cliente->nome} {$cliente->cognome}.";
    }

    protected function assegnaAbbonamento(array $params): string
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');
        $servizioNome = trim((string) ($params['servizio'] ?? ''));
        $dataInizio = $params['data_inizio'] ?? null;

        if ($servizioNome === '' || ! $dataInizio) {
            return 'Servizio o data inizio mancanti.';
        }

        $servizio = Servizio::query()
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($servizioNome)])
            ->first();

        if (! $servizio) {
            return "Servizio non trovato: {$servizioNome}";
        }

        $inizio = $this->parser->resolveDate($dataInizio)->startOfDay();
        $fine = $servizio->durata ? $inizio->copy()->addDays((int) $servizio->durata) : null;

        $abbonamento = Abbonamento::create([
            'cliente_id' => $cliente->id,
            'servizio_id' => $servizio->id,
            'data_inizio' => $inizio->toDateString(),
            'data_fine' => $fine?->toDateString(),
            'prezzo' => 0,
            'rate' => 1,
            'terminato' => false,
        ]);

        return "Abbonamento assegnato: {$servizio->nome} a {$cliente->nome} {$cliente->cognome} dal {$inizio->format('d/m/Y')} (#{$abbonamento->id}).";
    }

    protected function renewGoogle(): string
    {
        return "Per rinnovare Google devi rieseguire l'autenticazione OAuth locale e generare un nuovo token. Questo comando è solo un promemoria operativo.";
    }

    protected function syncGoogleStatus(): string
    {
        $appuntamenti = Appuntamento::query()
            ->with(['cliente'])
            ->where(function ($query) {
                $query->whereNull('calendar_sync_status')
                    ->orWhereIn('calendar_sync_status', ['dirty', 'error', 'pending', 'not_synced']);
            })
            ->orderBy('data_ora')
            ->limit(50)
            ->get();

        $lines = ['Sync Google:'];

        if ($appuntamenti->isEmpty()) {
            $lines[] = 'Appuntamenti non sincronizzati: nessuno';
        } else {
            $lines[] = 'Appuntamenti non sincronizzati:';
            foreach ($appuntamenti as $appuntamento) {
                $cliente = trim(($appuntamento->cliente?->nome ?? '') . ' ' . ($appuntamento->cliente?->cognome ?? ''));
                $ora = $appuntamento->evento_intera_giornata ? 'giornata intera' : $appuntamento->data_ora?->format('d/m/Y H:i');
                $tipo = $this->formatTipo($appuntamento->tipo_appuntamento);
                $stato = $appuntamento->calendar_sync_status ?? 'null';

                $lines[] = "#{$appuntamento->id} · {$ora} · {$tipo} · {$cliente} · {$stato}";
            }
        }

        if (Schema::hasTable('pagamenti') && Schema::hasColumn('pagamenti', 'calendar_sync_status')) {
            $pagamenti = Pagamento::query()
                ->with(['cliente', 'abbonamento.servizio'])
                ->where(function ($query) {
                    $query->whereNull('calendar_sync_status')
                        ->orWhereIn('calendar_sync_status', ['dirty', 'error', 'pending', 'not_synced']);
                })
                ->orderBy('scadenza')
                ->limit(50)
                ->get();

            if ($pagamenti->isEmpty()) {
                $lines[] = '';
                $lines[] = 'Pagamenti non sincronizzati: nessuno';
            } else {
                $lines[] = '';
                $lines[] = 'Pagamenti non sincronizzati:';
                foreach ($pagamenti as $pagamento) {
                    $cliente = trim(($pagamento->cliente?->nome ?? '') . ' ' . ($pagamento->cliente?->cognome ?? ''));
                    $abbonamentoNome = $pagamento->abbonamento?->servizio?->nome ?? '-';
                    $importo = number_format((float) ($pagamento->importo_previsto ?? 0), 2, ',', '.');
                    $scadenza = $pagamento->scadenza?->format('d/m/Y') ?? '-';
                    $statoSync = $pagamento->calendar_sync_status ?? 'null';

                    $lines[] = "#{$pagamento->id} · {$cliente} · {$abbonamentoNome} · € {$importo} · {$scadenza} · {$statoSync}";
                }
            }
        } else {
            $lines[] = '';
            $lines[] = 'Pagamenti non sincronizzati: campo calendar_sync_status non presente';
        }

        return implode("\n", $lines);
    }

    protected function aggiornaAbbonamentiMensili(): string
    {
        $abbonamenti = Abbonamento::query()
            ->with(['cliente', 'servizio'])
            ->where('terminato', false)
            ->get()
            ->filter(function ($abbonamento) {
                $nomeServizio = mb_strtolower((string) ($abbonamento->servizio?->nome ?? ''));
                return str_contains($nomeServizio, 'mensile');
            })
            ->values();

        if ($abbonamenti->isEmpty()) {
            return 'Nessun abbonamento mensile attivo trovato.';
        }

        $lines = ['Aggiornamento abbonamenti mensili:'];

        foreach ($abbonamenti as $abbonamento) {
            $cliente = trim(($abbonamento->cliente?->nome ?? '') . ' ' . ($abbonamento->cliente?->cognome ?? ''));
            $servizioNome = $abbonamento->servizio?->nome ?? '-';

            try {
                $this->aggiornaAbbonamentoMensile($abbonamento);
                $lines[] = "#{$abbonamento->id} · {$cliente} · {$servizioNome} · aggiornato";
            } catch (\Throwable $e) {
                $lines[] = "#{$abbonamento->id} · {$cliente} · {$servizioNome} · errore: {$e->getMessage()}";
            }
        }

        return implode("\n", $lines);
    }

    protected function aggiornaAbbonamentoMensile(Abbonamento $abbonamento): void
    {
        $cliente = $abbonamento->cliente;
        $servizio = $abbonamento->servizio;

        if (! $cliente || ! $servizio) {
            return;
        }

        $nomeServizio = mb_strtolower((string) $servizio->nome);

        if (! str_contains($nomeServizio, 'mensile')) {
            return;
        }
    }

    protected function aggiornaImportoPersonalMensileSeNecessario(Cliente $cliente): void
    {
        $abbonamento = method_exists($cliente, 'ultimoAbbonamentoAttivo')
            ? $cliente->ultimoAbbonamentoAttivo()
            : null;

        if (! $abbonamento || ! $abbonamento->servizio) {
            return;
        }

        $nomeServizio = mb_strtolower((string) $abbonamento->servizio->nome);

        if (! str_contains($nomeServizio, 'personal mensile')) {
            return;
        }
    }

    protected function findSingleAppointmentOrFail(array $params): Appuntamento
    {
        $cliente = $this->findClienteOrFail($params['cliente'] ?? '');
        $data = $params['data'] ?? null;

        if (! $data) {
            throw new RuntimeException('Data mancante.');
        }

        $giorno = $this->parser->resolveDate($data)->toDateString();

        $query = Appuntamento::query()
            ->where('cliente_id', $cliente->id)
            ->whereDate('data_ora', $giorno);

        if (! empty($params['tipo'])) {
            $query->where('tipo_appuntamento', $params['tipo']);
        }

        if (! empty($params['ora'])) {
            $query->whereTime('data_ora', $params['ora']);
        }

        $matches = $query->orderBy('data_ora')->get();

        if ($matches->isEmpty()) {
            throw new RuntimeException('Appuntamento non trovato.');
        }

        if ($matches->count() > 1) {
            throw new RuntimeException("Trovati più appuntamenti. Specifica anche l'ora.");
        }

        return $matches->first();
    }

    protected function findClienteOrFail(string $query): Cliente
    {
        $query = trim($query);

        if ($query === '') {
            throw new RuntimeException('Nome cliente mancante.');
        }

        $normalizedQuery = $this->normalizeNameString($query);

        $cliente = Cliente::query()
            ->get()
            ->first(function (Cliente $cliente) use ($normalizedQuery) {
                $full = $this->normalizeNameString(trim(($cliente->nome ?? '') . ' ' . ($cliente->cognome ?? '')));
                return $full === $normalizedQuery;
            });

        if (! $cliente) {
            throw new RuntimeException("Cliente non trovato: {$query}");
        }

        return $cliente;
    }

    protected function isSmallGroupAbbonamento(?Abbonamento $abbonamento): bool
    {
        if (! $abbonamento || ! $abbonamento->servizio) {
            return false;
        }

        $nome = mb_strtolower((string) $abbonamento->servizio->nome);

        return str_contains($nome, 'smallgroup') || str_contains($nome, 'small group');
    }

    protected function getPartecipantiAbbonamento(Abbonamento $abbonamento): Collection
    {
        if (method_exists($abbonamento, 'clienti')) {
            return $abbonamento->clienti()->get();
        }

        return collect();
    }

    protected function normalizeNameString(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function formatTipo(?string $tipo): string
    {
        return match ($tipo) {
            'call_google_meet' => 'Call',
            'consegna_programma' => 'Consegna',
            default => 'Personal',
        };
    }

    protected function formatNumeroLezione(Appuntamento $appuntamento): string
    {
        $numero = $appuntamento->numerazione ?? null;
        $totale = $appuntamento->abbonamento?->servizio?->incontri ?? null;

        if ($numero && $totale) {
            return "{$numero}/{$totale}";
        }

        if ($numero) {
            return (string) $numero;
        }

        return '-';
    }
}