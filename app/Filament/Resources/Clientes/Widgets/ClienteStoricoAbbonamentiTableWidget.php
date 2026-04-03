<?php

namespace App\Filament\Resources\Clientes\Widgets;

use App\Models\Abbonamento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ClienteStoricoAbbonamentiTableWidget extends TableWidget
{
    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Storico abbonamenti';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->record
                    ? $this->record->abbonamenti()->getQuery()->with(['servizio', 'clienti'])->orderByDesc('data_inizio')
                    : Abbonamento::query()->whereRaw('1 = 0')
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('servizio.nome')
                    ->label('Servizio')
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('tipo_partecipazione')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'singolo' => 'Singolo',
                        'condiviso' => 'Condiviso',
                        'gruppo' => 'Gruppo',
                        default => '-',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'singolo' => 'gray',
                        'condiviso' => 'warning',
                        'gruppo' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('data_inizio')
                    ->label('Inizio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_fine')
                    ->label('Fine')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('prezzo')
                    ->label('Prezzo')
                    ->money('EUR', locale: 'it'),

                TextColumn::make('terminato')
                    ->label('Stato')
                    ->state(fn (Abbonamento $record) => $record->terminato ? 'Terminato' : 'Attivo')
                    ->badge()
                    ->color(fn (Abbonamento $record) => $record->terminato ? 'danger' : 'success'),
            ]);
    }
}