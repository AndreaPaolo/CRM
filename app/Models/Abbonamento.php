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
    ];

    protected $casts = [
        'data_inizio' => 'date',
        'data_fine' => 'date',
        'terminato' => 'boolean',
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

            if ($abbonamento->data_fine) {
                $abbonamento->terminato = Carbon::today()->gt(
                    Carbon::parse($abbonamento->data_fine)
                );
            }
        });
    }

    public function aggiornaStatoTerminato(): void
    {
        $totaleIncontri = (int) ($this->servizio?->incontri ?? 0);

        $ultimoNumero = $this->appuntamenti()->max('numerazione') ?? 0;

        $terminatoPerLezioni = $totaleIncontri > 0 && $ultimoNumero >= $totaleIncontri;
        $terminatoPerData = $this->data_fine
            && Carbon::today()->gt(Carbon::parse($this->data_fine));

        $this->terminato = $terminatoPerLezioni || $terminatoPerData;
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
}