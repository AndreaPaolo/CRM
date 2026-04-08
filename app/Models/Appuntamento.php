<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appuntamento extends Model
{
    use SoftDeletes;

    protected $table = 'appuntamenti';

    protected $fillable = [
        'cliente_id',
        'abbonamento_id',
        'user_id',
        'data_ora',
        'durata',
        'descrizione',
        'numerazione',
        'tipo_appuntamento',
        'evento_intera_giornata',
        'sessione_condivisa_uuid',
        'google_calendar_event_id',
        'google_meet_link',
        'calendar_sync_status',
        'calendar_synced_at',
        'calendar_last_error',
    ];

    protected $casts = [
        'data_ora' => 'datetime',
        'evento_intera_giornata' => 'boolean',
        'calendar_synced_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
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

    public function abbonamento()
    {
        return $this->belongsTo(Abbonamento::class);
    }

    public function pt()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Appuntamento $appuntamento) {
            self::normalizeBeforeSave($appuntamento, true);
        });

        static::updating(function (Appuntamento $appuntamento) {
            self::normalizeBeforeSave($appuntamento, false);
        });

        static::saved(function (Appuntamento $appuntamento) {
            if ($appuntamento->cliente_id && $appuntamento->clienti()->count() === 0) {
                $appuntamento->clienti()->syncWithoutDetaching([$appuntamento->cliente_id]);
            }

            try {
                app(\App\Services\GoogleCalendarService::class)->syncAppuntamento(
                    $appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                );

                $appuntamento->updateQuietly([
                    'calendar_sync_status' => 'synced',
                    'calendar_synced_at' => now(),
                    'calendar_last_error' => null,
                ]);
            } catch (\Throwable $e) {
                $appuntamento->updateQuietly([
                    'calendar_sync_status' => 'failed',
                    'calendar_last_error' => $e->getMessage(),
                ]);
            }

            $abbonamento = $appuntamento->abbonamento;

            if ($abbonamento) {
                $abbonamento->aggiornaNumerazioneAppuntamenti();
                $abbonamento->aggiornaStatoTerminato();
            }
        });

        static::deleted(function (Appuntamento $appuntamento) {
            try {
                app(\App\Services\GoogleCalendarService::class)->deleteAppuntamento($appuntamento);
            } catch (\Throwable $e) {
                //
            }

            $abbonamento = $appuntamento->abbonamento;

            if ($abbonamento) {
                $abbonamento->aggiornaNumerazioneAppuntamenti();
                $abbonamento->aggiornaStatoTerminato();
            }
        });
    }

    protected static function normalizeBeforeSave(Appuntamento $appuntamento, bool $isCreating): void
    {
        if (! $appuntamento->user_id && auth()->check()) {
            $appuntamento->user_id = auth()->id();
        }

        if (blank($appuntamento->tipo_appuntamento)) {
            $appuntamento->tipo_appuntamento = 'personal';
        }

        if ($appuntamento->tipo_appuntamento === 'consegna_programma') {
            $appuntamento->evento_intera_giornata = true;
            $appuntamento->durata = 1440;
        } else {
            $appuntamento->evento_intera_giornata = false;

            if ((int) ($appuntamento->durata ?? 0) <= 0 || (int) ($appuntamento->durata ?? 0) === 1440) {
                $appuntamento->durata = 60;
            }
        }

        if ($appuntamento->evento_intera_giornata && $appuntamento->data_ora) {
            $appuntamento->data_ora = Carbon::parse($appuntamento->data_ora)->startOfDay();
        }

        if ($appuntamento->tipo_appuntamento !== 'personal') {
            $appuntamento->numerazione = null;
        }

        if ($isCreating && blank($appuntamento->numerazione) && $appuntamento->tipo_appuntamento === 'personal') {
            $appuntamento->numerazione = 0;
        }

        if ($isCreating) {
            $appuntamento->calendar_sync_status = 'dirty';
            $appuntamento->calendar_last_error = null;
        }

        if ($appuntamento->isDirty([
            'cliente_id',
            'abbonamento_id',
            'data_ora',
            'durata',
            'descrizione',
            'numerazione',
            'tipo_appuntamento',
            'evento_intera_giornata',
        ])) {
            $appuntamento->calendar_sync_status = 'dirty';
            $appuntamento->calendar_last_error = null;
        }
    }
}