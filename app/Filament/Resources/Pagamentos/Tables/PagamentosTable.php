<?php

namespace App\Filament\Resources\Pagamentos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cliente_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('abbonamento_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tipo')
                    ->searchable(),
                TextColumn::make('competenza_da')
                    ->date()
                    ->sortable(),
                TextColumn::make('competenza_a')
                    ->date()
                    ->sortable(),
                TextColumn::make('descrizione')
                    ->searchable(),
                TextColumn::make('importo_previsto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('importo_pagato')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('scadenza')
                    ->date()
                    ->sortable(),
                TextColumn::make('data_saldo')
                    ->date()
                    ->sortable(),
                TextColumn::make('stato')
                    ->searchable(),
                TextColumn::make('numero_rata')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('totale_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('google_calendar_event_id')
                    ->searchable(),
                TextColumn::make('calendar_sync_status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
