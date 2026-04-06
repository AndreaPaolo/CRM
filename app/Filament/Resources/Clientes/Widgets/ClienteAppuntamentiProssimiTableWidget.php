<?php

namespace App\Filament\Resources\Clientes\Widgets;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Models\Appuntamento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ClienteAppuntamentiProssimiTableWidget extends TableWidget
{
    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Prossimi appuntamenti';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appuntamento::query()
                    ->with(['abbonamento.servizio', 'pt'])
                    ->where('cliente_id', $this->record?->id)
                    ->where('data_ora', '>=', now())
                    ->orderBy('data_ora')
            )
            ->recordUrl(fn (Appuntamento $record): string => AppuntamentoResource::getUrl('edit', ['record' => $record]))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('data_ora')->dateTime('d/m/Y H:i')->weight('bold'),
                TextColumn::make('abbonamento.servizio.nome')->label('Servizio')->wrap(),
                TextColumn::make('numerazione')->label('Lezione'),
                TextColumn::make('durata')->formatStateUsing(fn ($state) => $state . ' min'),
                TextColumn::make('pt.name')->label('PT')->placeholder('-'),
            ]);
    }
}