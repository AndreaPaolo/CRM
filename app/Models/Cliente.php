<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'cliente';

    protected $fillable = [
        'nome',
        'cognome',
        'telefono',
        'email',
    ];

    public function abbonamenti(){
        return $this->hasMany(Abbonamento::class);
    }

    public function appuntamenti(){
        return $this->hasMany(Appuntamento::class);
    }

    public function abbonamentiCondivisi()
    {
        return $this->belongsToMany(Abbonamento::class, 'abbonamento_cliente')->withTimestamps();
    }

    public function abbonamentiOrdinati()
    {
        return $this->hasMany(Abbonamento::class)
            ->orderByDesc('data_inizio')
            ->orderByDesc('created_at');
    }

    public function ultimoAbbonamentoAttivo(): ?Abbonamento
    {
        return $this->abbonamenti()
            ->where('terminato', false)
            ->orderByDesc('data_inizio')
            ->orderByDesc('created_at')
            ->first();
    }
}