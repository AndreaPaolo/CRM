<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

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
        'google_calendar_event_id',
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

        return 'Lezione ' . $this->numerazione . ' / ' . $totale;
    }

    protected static function booted(): void
    {
        static::creating(function (Appuntamento $appuntamento) {
            if (! $appuntamento->user_id && auth()->check()) {
                $appuntamento->user_id = auth()->id();
            }

            if (! $appuntamento->numerazione && $appuntamento->abbonamento_id) {
                $ultimaNumerazione = self::where('abbonamento_id', $appuntamento->abbonamento_id)
                    ->max('numerazione');

                $appuntamento->numerazione = ($ultimaNumerazione ?? 0) + 1;
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
            $abbonamento = $appuntamento->abbonamento;

            if (! $abbonamento || ! $abbonamento->servizio) {
                return;
            }

            $totaleIncontri = $abbonamento->servizio->incontri;

            $ultimoNumero = self::where('abbonamento_id', $abbonamento->id)
                ->max('numerazione') ?? 0;

            $terminatoPerLezioni = $ultimoNumero >= $totaleIncontri;
            $terminatoPerData = $abbonamento->data_fine && Carbon::today()->gt(Carbon::parse($abbonamento->data_fine));

            $abbonamento->terminato = $terminatoPerLezioni || $terminatoPerData;
            $abbonamento->saveQuietly();
        });

        static::deleted(function (Appuntamento $appuntamento) {
            $abbonamento = $appuntamento->abbonamento;

            if (! $abbonamento || ! $abbonamento->servizio) {
                return;
            }

            $totaleIncontri = $abbonamento->servizio->incontri;

            $ultimoNumero = self::where('abbonamento_id', $abbonamento->id)
                ->max('numerazione') ?? 0;

            $terminatoPerLezioni = $ultimoNumero >= $totaleIncontri;
            $terminatoPerData = $abbonamento->data_fine && Carbon::today()->gt(Carbon::parse($abbonamento->data_fine));

            $abbonamento->terminato = $terminatoPerLezioni || $terminatoPerData;
            $abbonamento->saveQuietly();
        });
    }
}
