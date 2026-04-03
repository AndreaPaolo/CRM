<?php

namespace App\Filament\Resources\Clientes\Widgets;

use App\Models\Appuntamento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ClienteAppuntamentiTableWidget extends TableWidget
{
    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Appuntamenti';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appuntamento::query()
                    ->with(['abbonamento.servizio', 'pt'])
                    ->where('cliente_id', $this->record?->id)
                    ->orderByDesc('data_ora')
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
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

                        if ($totale > 0) {
                            return "{$record->numerazione} / {$totale}";
                        }

                        return (string) $record->numerazione;
                    })
                    ->badge()
                    ->color('success'),

                TextColumn::make('durata')
                    ->label('Durata')
                    ->formatStateUsing(fn ($state) => $state . ' min'),

                TextColumn::make('pt.name')
                    ->label('PT')
                    ->placeholder('-'),

                TextColumn::make('calendar_sync_status')
                    ->label('Sync')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'synced' => 'Sync OK',
                        'dirty' => 'Da aggiornare',
                        'failed' => 'Errore',
                        default => '-',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'synced' => 'success',
                        'dirty' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }
}