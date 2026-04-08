<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Abbonamentos\AbbonamentoResource;
use App\Models\Abbonamento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AbbonamentiInScadenzaWidget extends TableWidget
{
    protected static ?string $heading = 'Abbonamenti vicini alla scadenza';

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Abbonamento::query()
                    ->with(['cliente', 'servizio'])
                    ->where('terminato', false)
                    ->whereNotNull('data_fine')
                    ->whereDate('data_fine', '>=', today())
                    ->whereDate('data_fine', '<=', today()->addDays(14))
                    ->orderBy('data_fine')
            )
            ->recordUrl(fn (Abbonamento $record): string => AbbonamentoResource::getUrl('edit', ['record' => $record]))
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->state(fn (Abbonamento $record) => trim(($record->cliente?->nome ?? '') . ' ' . ($record->cliente?->cognome ?? '')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cliente', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%")
                                ->orWhere('cognome', 'like', "%{$search}%");
                        });
                    })
                    ->weight('bold'),

                TextColumn::make('servizio.nome')
                    ->label('Servizio')
                    ->wrap(),

                TextColumn::make('data_fine')
                    ->label('Scadenza')
                    ->date('d/m/Y'),

                TextColumn::make('giorni_residui')
                    ->label('Residuo')
                    ->state(fn (Abbonamento $record) => today()->diffInDays($record->data_fine, false) . ' gg')
                    ->badge()
                    ->color(fn (Abbonamento $record) => today()->diffInDays($record->data_fine, false) <= 7 ? 'danger' : 'warning'),
            ]);
    }
}