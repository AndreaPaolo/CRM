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

    protected static function booted(): void
    {
        
        static::saving(function (Abbonamento $abbonamento) {
            if ($abbonamento->data_inizio && $abbonamento->servizio_id) {
                $servizio = Servizio::find($abbonamento->servizio_id);

                if ($servizio) {
                    $abbonamento->data_fine = Carbon::parse($abbonamento->data_inizio)
                        ->copy()
                        ->addDays($servizio->durata);
                }
            }

            // Se il toggle "terminato" è stato cambiato a mano,
            // salvo anche il flag manuale.
            if ($abbonamento->isDirty('terminato')) {
                $abbonamento->terminato_manualmente = (bool) $abbonamento->terminato;
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
        $ultimoNumero = $this->appuntamenti()->max('numerazione') ?? 0;

        // Se incontri > 0, si chiude automaticamente:
        // - per data fine passata
        // - oppure per ultime lezioni raggiunte
        if ($totaleIncontri > 0) {
            $terminatoPerLezioni = $ultimoNumero >= $totaleIncontri;
            $terminatoPerData = $this->data_fine
                && now()->startOfDay()->gt(\Carbon\Carbon::parse($this->data_fine)->startOfDay());

            $this->terminato = $terminatoPerLezioni || $terminatoPerData;
            $this->saveQuietly();

            return;
        }

        // Se incontri = 0, NON si chiude automaticamente.
        // Solo manualmente.
        $this->terminato = false;
        $this->saveQuietly();
    }

    public function appuntamenti(){
        return $this->hasMany(Appuntamento::class);
    }

    public function aggiornaNumerazioneAppuntamenti(): void
    {
        $appuntamenti = Appuntamento::query()
            ->where('abbonamento_id', $this->id)
            ->orderBy('data_ora')
            ->orderBy('id')
            ->get();

        foreach ($appuntamenti as $index => $appuntamento) {
            $nuovoNumero = $index + 1;

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