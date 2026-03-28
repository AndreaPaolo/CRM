<?php

namespace App\Filament\Resources\Appuntamentos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Resources\Resource;


class AppuntamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_ora', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('cliente.nome')
                    ->label('Cliente')
                    ->formatStateUsing(fn ($state, $record) => $record->cliente->nome . ' ' . $record->cliente->cognome)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cliente', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%")
                                ->orWhere('cognome', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('abbonamento.servizio.nome')
                    ->label('Servizio')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('data_ora')
                    ->label('Data e ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('durata')
                    ->label('Durata')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('numerazione')
                    ->label('Numerazione')
                    ->formatStateUsing(function ($state, $record) {
                        $totale = $record->abbonamento?->servizio?->incontri ?? 0;
                        return 'Lezione ' . $state . ' / ' . $totale;
                    })
                    ->badge()
                    ->color(function ($state, $record) {
                        $totale = $record->abbonamento?->servizio?->incontri ?? 1;
                        $percentuale = $totale > 0 ? ($state / $totale) * 100 : 0;

                        if ($percentuale < 35) {
                            return 'success';
                        }

                        if ($percentuale < 70) {
                            return 'warning';
                        }

                        return 'danger';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('pt.name')
                    ->label('PT')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('descrizione')
                    ->label('Descrizione')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nome'),

                Tables\Filters\SelectFilter::make('abbonamento_id')
                    ->label('Abbonamento')
                    ->relationship('abbonamento', 'id'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
