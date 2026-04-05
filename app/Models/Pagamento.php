<?php

namespace App\Models;

use App\Services\GoogleCalendarService;
use Illuminate\Database\Eloquent\Model;

class Pagamento extends Model
{
    protected $table = 'pagamenti';

    protected $fillable = [
        'cliente_id',
        'abbonamento_id',
        'tipo',
        'competenza_da',
        'competenza_a',
        'descrizione',
        'importo_previsto',
        'importo_pagato',
        'scadenza',
        'data_saldo',
        'stato',
        'numero_rata',
        'totale_rate',
        'google_calendar_event_id',
        'calendar_sync_status',
        'calendar_last_error',
    ];

    protected $casts = [
        'competenza_da' => 'date',
        'competenza_a' => 'date',
        'scadenza' => 'date',
        'data_saldo' => 'date',
        'importo_previsto' => 'decimal:2',
        'importo_pagato' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function abbonamento()
    {
        return $this->belongsTo(Abbonamento::class);
    }

    public function movimenti()
    {
        return $this->hasMany(MovimentoPagamento::class, 'pagamento_id');
    }

    public function aggiornaStato(): void
    {
        if ((float) $this->importo_pagato <= 0) {
            $this->stato = 'da_pagare';
            $this->data_saldo = null;
        } elseif ((float) $this->importo_pagato < (float) $this->importo_previsto) {
            $this->stato = 'parziale';
            $this->data_saldo = null;
        } else {
            $this->stato = 'pagato';
            $this->data_saldo = now()->toDateString();
        }

        $this->saveQuietly();
    }

    protected static function booted(): void
    {
        static::saved(function (Pagamento $pagamento) {
            try {
                app(GoogleCalendarService::class)->syncPagamento(
                    $pagamento->fresh(['cliente', 'abbonamento'])
                );
            } catch (\Throwable $e) {
                $pagamento->updateQuietly([
                    'calendar_sync_status' => 'failed',
                    'calendar_last_error' => $e->getMessage(),
                ]);
            }
        });

        static::deleted(function (Pagamento $pagamento) {
            try {
                app(GoogleCalendarService::class)->deletePagamento($pagamento);
            } catch (\Throwable $e) {
                //
            }
        });
    }
}