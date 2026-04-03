<?php

namespace App\Filament\Resources\Clientes\Widgets;

use App\Models\Appuntamento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ClienteAppuntamentiPassatiTableWidget extends TableWidget
{
    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Appuntamenti passati';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appuntamento::query()
                    ->with(['abbonamento.servizio', 'pt'])
                    ->where('cliente_id', $this->record?->id)
                    ->where('data_ora', '<', now())
                    ->orderByDesc('data_ora')
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('data_ora')
                    ->label('Data e ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('abbonamento.servizio.nome')
                    ->label('Servizio')
                    ->wrap(),

                TextColumn::make('numerazione')
                    ->label('Lezione')
                    ->state(function (Appuntamento $record) {
                        $totale = (int) ($record->abbonamento?->servizio?->incontri ?? 0);
                        return $totale > 0 ? "{$record->numerazione} / {$totale}" : (string) $record->numerazione;
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('durata')
                    ->label('Durata')
                    ->formatStateUsing(fn ($state) => $state . ' min'),

                TextColumn::make('pt.name')
                    ->label('PT')
                    ->placeholder('-'),
            ]);
    }
}