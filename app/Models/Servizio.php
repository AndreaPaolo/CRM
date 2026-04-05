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
    ];

    protected $casts = [
        'durata' => 'integer',
        'incontri' => 'integer',
    ];

    public function abbonamenti()
    {
        return $this->hasMany(Abbonamento::class);
    }
}