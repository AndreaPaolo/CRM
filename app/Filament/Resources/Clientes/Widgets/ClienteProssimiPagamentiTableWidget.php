<?php

namespace App\Filament\Resources\Clientes\Widgets;

use App\Models\Pagamento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ClienteProssimiPagamentiTableWidget extends TableWidget
{
    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Prossimi pagamenti';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Pagamento::query()
                    ->where('cliente_id', $this->record?->id)
                    ->whereIn('stato', ['da_pagare', 'parziale'])
                    ->orderBy('scadenza')
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('tipo')->badge(),
                TextColumn::make('descrizione')->wrap(),
                TextColumn::make('importo_previsto')->money('EUR', locale: 'it'),
                TextColumn::make('importo_pagato')->money('EUR', locale: 'it'),
                TextColumn::make('scadenza')->date('d/m/Y'),
                TextColumn::make('stato')->badge(),
            ]);
    }
}