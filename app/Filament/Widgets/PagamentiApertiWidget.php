<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Pagamentos\PagamentoResource;
use App\Models\Pagamento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PagamentiApertiWidget extends TableWidget
{
    protected static ?string $heading = 'Pagamenti aperti';

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Pagamento::query()
                    ->with(['cliente', 'abbonamento.servizio'])
                    ->whereIn('stato', ['da_pagare', 'parziale'])
                    ->orderBy('scadenza')
            )
            ->recordUrl(fn (Pagamento $record): string => PagamentoResource::getUrl('edit', ['record' => $record]))
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->state(fn (Pagamento $record) => trim(($record->cliente?->nome ?? '') . ' ' . ($record->cliente?->cognome ?? '')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cliente', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%")
                                ->orWhere('cognome', 'like', "%{$search}%");
                        });
                    })
                    ->weight('bold'),

                TextColumn::make('descrizione')
                    ->wrap(),

                TextColumn::make('importo_previsto')
                    ->label('Importo')
                    ->money('EUR', locale: 'it'),

                TextColumn::make('scadenza')
                    ->date('d/m/Y'),

                TextColumn::make('stato')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'parziale' => 'warning',
                        'da_pagare' => 'danger',
                        'pagato' => 'success',
                        default => 'gray',
                    }),
            ]);
    }
}