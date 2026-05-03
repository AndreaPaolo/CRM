<?php

namespace App\Models;

use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abbonamento extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'servizio_id',
        'cliente_id',
        'prezzo',
        'rate',
        'data_inizio',
        'data_fine',
        'terminato',
        'terminato_manualmente',
        'tipo_partecipazione',
    ];

    protected $casts = [
        'data_inizio' => 'date',
        'data_fine' => 'date',
        'terminato' => 'boolean',
        'terminato_manualmente' => 'boolean',
        'prezzo' => 'decimal:2',
    ];

    public function servizio()
    {
        return $this->belongsTo(Servizio::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function clientePrincipale()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function clienti()
    {
        return $this->belongsToMany(Cliente::class, 'abbonamento_cliente')->withTimestamps();
    }

    public function appuntamenti()
    {
        return $this->hasMany(Appuntamento::class);
    }

    public function pagamenti()
    {
        return $this->hasMany(Pagamento::class);
    }

    protected static function booted(): void
    {
        static::saved(function (Abbonamento $abbonamento) {
            if ($abbonamento->cliente_id && $abbonamento->clienti()->count() === 0) {
                $abbonamento->clienti()->syncWithoutDetaching([$abbonamento->cliente_id]);
            }
        });

        static::saving(function (Abbonamento $abbonamento) {
            if ($abbonamento->data_inizio && $abbonamento->servizio_id) {
                $servizio = Servizio::find($abbonamento->servizio_id);

                if ($servizio) {
                    $abbonamento->data_fine = Carbon::parse($abbonamento->data_inizio)
                        ->copy()
                        ->addDays($servizio->durata);
                }
            }

            if ($abbonamento->isDirty('terminato')) {
                $abbonamento->terminato_manualmente = (bool) $abbonamento->terminato;
            }
        });

        static::deleting(function (Abbonamento $abbonamento) {
            $abbonamento->loadMissing(['appuntamenti.cliente']);

            $service = app(GoogleCalendarService::class);

            foreach ($abbonamento->appuntamenti as $appuntamento) {
                try {
                    $service->deleteAppuntamento($appuntamento);
                } catch (\Throwable $e) {
                    //
                }

                $appuntamento->delete();
            }
        });
    }

    public function aggiornaStatoTerminato(): void
    {
        if ($this->terminato_manualmente) {
            $this->updateQuietly([
                'terminato' => true,
            ]);

            return;
        }

        $this->loadMissing('servizio');

        if (! $this->servizio) {
            return;
        }

        if ($this->servizio->tipo_fatturazione === 'mensile') {
            $terminato = $this->data_fine ? $this->data_fine->isPast() : false;

            $this->updateQuietly([
                'terminato' => $terminato,
            ]);

            return;
        }

        $totalePrevisto = (int) ($this->servizio->incontri ?? 0);

        if ($totalePrevisto <= 0) {
            return;
        }

        $totalePersonalUsati = $this->appuntamenti()
            ->where('tipo_appuntamento', 'personal')
            ->count();

        $this->updateQuietly([
            'terminato' => $totalePersonalUsati >= $totalePrevisto,
        ]);
    }

    public function aggiornaNumerazioneAppuntamenti(): void
    {
        $appuntamenti = $this->appuntamenti()
            ->where('tipo_appuntamento', 'personal')
            ->orderBy('data_ora')
            ->orderBy('id')
            ->get();

        $counterPersonal = 0;
        $sessioniNumerate = [];

        foreach ($appuntamenti as $appuntamento) {
            $groupKey = $appuntamento->sessione_condivisa_uuid
                ? 'session:' . $appuntamento->sessione_condivisa_uuid
                : 'single:' . $appuntamento->id;

            if (! array_key_exists($groupKey, $sessioniNumerate)) {
                $counterPersonal++;
                $sessioniNumerate[$groupKey] = $counterPersonal;
            }

            $nuovaNumerazione = $sessioniNumerate[$groupKey];

            if ((int) $appuntamento->numerazione !== $nuovaNumerazione) {
                $appuntamento->updateQuietly([
                    'numerazione' => $nuovaNumerazione,
                ]);
            }
        }

        $this->appuntamenti()
            ->where('tipo_appuntamento', '!=', 'personal')
            ->whereNotNull('numerazione')
            ->get()
            ->each(function (Appuntamento $appuntamento) {
                $appuntamento->updateQuietly([
                    'numerazione' => null,
                ]);
            });
    }

    public function sincronizzaAppuntamentiSuGoogle(): void
    {
        $appuntamenti = $this->appuntamenti()
            ->with(['cliente', 'abbonamento.servizio', 'pt'])
            ->orderBy('data_ora')
            ->orderBy('id')
            ->get();

        foreach ($appuntamenti as $appuntamento) {
            try {
                app(GoogleCalendarService::class)
                    ->syncAppuntamento(
                        $appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                    );
            } catch (\Throwable $e) {
                //
            }
        }
    }
}