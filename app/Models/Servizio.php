<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Servizio extends Model
{
    use SoftDeletes;

    protected $table = 'servizi';

    protected $fillable = [
        'nome',
        'descrizione',
        'durata',
        'incontri',
        'tipo_fatturazione',
        'tipo_appuntamento_default',
        'evento_intera_giornata_default',
        'crea_google_meet_default',
        'prenotazione_autonoma_cliente',
    ];

    protected $casts = [
        'durata' => 'integer',
        'incontri' => 'integer',
        'evento_intera_giornata_default' => 'boolean',
        'crea_google_meet_default' => 'boolean',
        'prenotazione_autonoma_cliente' => 'boolean',
    ];

    public function abbonamenti()
    {
        return $this->hasMany(Abbonamento::class);
    }
}