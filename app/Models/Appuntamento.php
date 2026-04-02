<?php

namespace App\Models;

use App\Services\GoogleCalendarService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appuntamento extends Model
{
    use SoftDeletes;

    protected $table = 'appuntamenti';

    protected static bool $isDeletingSessioneCondivisa = false;

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

    public function abbonamento()
    {
        return $this->belongsTo(Abbonamento::class);
    }

    public function pt()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNumerazioneLabelAttribute(): string
    {
        $totale = $this->abbonamento?->servizio?->incontri ?? 0;

        return $totale > 0
            ? 'Lezione ' . $this->numerazione . ' / ' . $totale
            : 'Lezione ' . $this->numerazione;
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
                'sessione_condivisa_uuid',
            ])) {
                $appuntamento->calendar_sync_status = 'dirty';
                $appuntamento->calendar_last_error = null;
            }
        });

        static::deleting(function (Appuntamento $appuntamento) {
            if (self::$isDeletingSessioneCondivisa) {
                return;
            }

            if (! $appuntamento->sessione_condivisa_uuid) {
                return;
            }

            self::$isDeletingSessioneCondivisa = true;

            try {
                $siblings = self::query()
                    ->where('sessione_condivisa_uuid', $appuntamento->sessione_condivisa_uuid)
                    ->where('id', '!=', $appuntamento->id)
                    ->get();

                $service = app(GoogleCalendarService::class);

                foreach ($siblings as $sibling) {
                    try {
                        $service->deleteAppuntamento($sibling->fresh(['cliente']));
                    } catch (\Throwable $e) {
                        //
                    }
                }

                self::withoutEvents(function () use ($appuntamento) {
                    self::query()
                        ->where('sessione_condivisa_uuid', $appuntamento->sessione_condivisa_uuid)
                        ->where('id', '!=', $appuntamento->id)
                        ->delete();
                });
            } finally {
                self::$isDeletingSessioneCondivisa = false;
            }
        });

        static::saved(function (Appuntamento $appuntamento) {
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