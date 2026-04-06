<?php

namespace App\Filament\Resources\Clientes\Widgets;

use App\Filament\Resources\Pagamentos\PagamentoResource;
use App\Models\Pagamento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ClientePagamentiPassatiTableWidget extends TableWidget
{
    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Pagamenti passati';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Pagamento::query()
                    ->where('cliente_id', $this->record?->id)
                    ->whereIn('stato', ['pagato', 'annullato'])
                    ->orderByDesc('scadenza')
            )
            ->recordUrl(fn (Pagamento $record): string => PagamentoResource::getUrl('edit', ['record' => $record]))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('tipo')->label('Categoria')->badge(),
                TextColumn::make('descrizione')->wrap(),
                TextColumn::make('importo_previsto')->money('EUR', locale: 'it'),
                TextColumn::make('importo_pagato')->money('EUR', locale: 'it'),
                TextColumn::make('scadenza')->date('d/m/Y'),
                TextColumn::make('data_saldo')->date('d/m/Y')->placeholder('-'),
                TextColumn::make('stato')->badge(),
            ]);
    }
}