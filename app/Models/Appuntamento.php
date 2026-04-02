<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appuntamento extends Model
{
    use SoftDeletes;

    protected $table = 'appuntamenti';

    protected $fillable = [
        'cliente_id',
        'abbonamento_id',
        'sessione_condivisa_uuid',
        'user_id',
        'data_ora',
        'durata',
        'descrizione',
        'numerazione',
        'google_calendar_event_id',
        'calendar_sync_status',
        'calendar_synced_at',
        'calendar_last_error',
    ];

    protected $casts = [
        'data_ora' => 'datetime',
        'calendar_synced_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function clientePrincipale()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function abbonamento()
    {
        return $this->belongsTo(Abbonamento::class);
    }

    public function pt()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function clienti()
    {
        return $this->belongsToMany(Cliente::class, 'appuntamento_cliente')
            ->withTimestamps()
            ->withPivot([
                'google_calendar_event_id',
                'calendar_sync_status',
                'calendar_synced_at',
                'calendar_last_error',
            ]);
    }

    public function getNumerazioneLabelAttribute(): string
    {
        $totale = $this->abbonamento?->servizio?->incontri ?? 0;

        return 'Lezione ' . $this->numerazione . ' / ' . $totale;
    }

    protected static function booted(): void
    {
        static::creating(function (Appuntamento $appuntamento) {
            if (! $appuntamento->user_id && auth()->check()) {
                $appuntamento->user_id = auth()->id();
            }

            if (! $appuntamento->numerazione) {
                $appuntamento->numerazione = 0;
            }

            $appuntamento->calendar_sync_status = 'dirty';
            $appuntamento->calendar_last_error = null;
        });

        static::updating(function (Appuntamento $appuntamento) {
            if ($appuntamento->isDirty([
                'cliente_id',
                'abbonamento_id',
                'data_ora',
                'durata',
                'descrizione',
                'numerazione',
            ])) {
                $appuntamento->calendar_sync_status = 'dirty';
                $appuntamento->calendar_last_error = null;
            }
        });

        static::saved(function (Appuntamento $appuntamento) {
            if ($appuntamento->cliente_id && $appuntamento->clienti()->count() === 0) {
                $appuntamento->clienti()->syncWithoutDetaching([$appuntamento->cliente_id]);
            }

            $abbonamento = $appuntamento->abbonamento?->loadMissing('servizio');

            if (! $abbonamento) {
                return;
            }

            $abbonamento->aggiornaNumerazioneAppuntamenti();
            $abbonamento->aggiornaStatoTerminato();
        });

        static::deleted(function (Appuntamento $appuntamento) {
            $abbonamento = $appuntamento->abbonamento?->loadMissing('servizio');

            if (! $abbonamento) {
                return;
            }

            $abbonamento->aggiornaNumerazioneAppuntamenti();
            $abbonamento->aggiornaStatoTerminato();
        });
    }
}