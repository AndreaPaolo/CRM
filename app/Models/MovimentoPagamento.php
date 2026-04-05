<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimentoPagamento extends Model
{
    protected $table = 'movimenti_pagamento';

    protected $fillable = [
        'pagamento_id',
        'data_pagamento',
        'importo',
        'metodo',
        'note',
    ];

    protected $casts = [
        'data_pagamento' => 'date',
        'importo' => 'decimal:2',
    ];

    public function pagamento()
    {
        return $this->belongsTo(Pagamento::class);
    }

    protected static function booted(): void
    {
        static::saved(function (MovimentoPagamento $movimento) {
            $pagamento = $movimento->pagamento;

            if (! $pagamento) {
                return;
            }

            $totalePagato = $pagamento->movimenti()->sum('importo');

            $pagamento->importo_pagato = $totalePagato;
            $pagamento->aggiornaStato();
        });

        static::deleted(function (MovimentoPagamento $movimento) {
            $pagamento = $movimento->pagamento;

            if (! $pagamento) {
                return;
            }

            $totalePagato = $pagamento->movimenti()->sum('importo');

            $pagamento->importo_pagato = $totalePagato;
            $pagamento->aggiornaStato();
        });
    }
}