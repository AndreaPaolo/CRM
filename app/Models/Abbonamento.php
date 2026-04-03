<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Abbonamento extends Model
{
    use SoftDeletes;

    protected $table = 'abbonamenti';

    protected $fillable = [
        'servizio_id',
        'cliente_id',
        'tipo_partecipazione',
        'prezzo',
        'rate',
        'data_inizio',
        'data_fine',
        'terminato',
        'terminato_manualmente',
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

            $service = app(\App\Services\GoogleCalendarService::class);

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
            $this->terminato = true;
            $this->saveQuietly();

            return;
        }

        $totaleIncontri = (int) ($this->servizio?->incontri ?? 0);

        $sessioni = $this->appuntamenti()
            ->get()
            ->map(fn ($appuntamento) => $appuntamento->sessione_condivisa_uuid ?: 'single_' . $appuntamento->id)
            ->unique()
            ->count();

        if ($totaleIncontri > 0) {
            $terminatoPerLezioni = $sessioni >= $totaleIncontri;
            $terminatoPerData = $this->data_fine
                && now()->startOfDay()->gt(Carbon::parse($this->data_fine)->startOfDay());

            $this->terminato = $terminatoPerLezioni || $terminatoPerData;
            $this->saveQuietly();

            return;
        }

        $this->terminato = false;
        $this->saveQuietly();
    }

    public function aggiornaNumerazioneAppuntamenti(): void
    {
        $appuntamenti = Appuntamento::query()
            ->where('abbonamento_id', $this->id)
            ->orderBy('data_ora')
            ->orderBy('id')
            ->get();

        $sessioni = [];
        $numeroCorrente = 0;

        foreach ($appuntamenti as $appuntamento) {
            $chiaveSessione = $appuntamento->sessione_condivisa_uuid ?: 'single_' . $appuntamento->id;

            if (! array_key_exists($chiaveSessione, $sessioni)) {
                $numeroCorrente++;
                $sessioni[$chiaveSessione] = $numeroCorrente;
            }

            $nuovoNumero = $sessioni[$chiaveSessione];

            if ((int) $appuntamento->numerazione !== $nuovoNumero) {
                $appuntamento->numerazione = $nuovoNumero;
                $appuntamento->saveQuietly();
            }
        }
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
                app(\App\Services\GoogleCalendarService::class)
                    ->syncAppuntamento(
                        $appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                    );
            } catch (\Throwable $e) {
                //
            }
        }
    }
}