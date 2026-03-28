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
use Filament\Tables\Columns\IconColumn;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use App\Services\GoogleCalendarService;


class AppuntamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_ora', 'desc')
        ->columns([
            IconColumn::make('calendar_sync_status')
                ->label('Sync')
                ->icon(fn (string $state): string => match ($state) {
                    'synced' => 'heroicon-o-check-circle',
                    'dirty' => 'heroicon-o-exclamation-circle',
                    'failed' => 'heroicon-o-x-circle',
                    default => 'heroicon-o-question-mark-circle',
                })
                ->color(fn (string $state): string => match ($state) {
                    'synced' => 'success',
                    'dirty' => 'warning',
                    'failed' => 'danger',
                    default => 'gray',
                })
                ->tooltip(function ($record): string {
                    return match ($record->calendar_sync_status) {
                        'synced' => 'Sincronizzato' . ($record->calendar_synced_at
                            ? ' il ' . $record->calendar_synced_at->format('d/m/Y H:i')
                            : ''),
                        'dirty' => 'Modificato localmente, da aggiornare su Google Calendar',
                        'failed' => 'Errore sincronizzazione: ' . ($record->calendar_last_error ?: 'sconosciuto'),
                        default => 'Stato sconosciuto',
                    };
                })
                ->action(
                    Action::make('syncCalendarFromDot')
                        ->action(function ($record) {
                            app(GoogleCalendarService::class)->syncAppuntamento(
                                $record->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                            );
                        })
                ),

            TextColumn::make('cliente.nome')
                ->label('Cliente')
                ->formatStateUsing(fn ($state, $record) => $record->cliente
                    ? $record->cliente->nome . ' ' . $record->cliente->cognome
                    : '-')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('cliente', function (Builder $q) use ($search) {
                        $q->where('nome', 'like', "%{$search}%")
                            ->orWhere('cognome', 'like', "%{$search}%");
                    });
                })
                ->sortable(),

            TextColumn::make('abbonamento.servizio.nome')
                ->label('Servizio')
                ->formatStateUsing(fn ($state) => $state ?: '-')
                ->searchable()
                ->sortable(),

            TextColumn::make('data_ora')
                ->label('Data e ora')
                ->dateTime('d/m/Y H:i')
                ->sortable(),

            TextColumn::make('durata')
                ->label('Durata')
                ->suffix(' min')
                ->sortable(),

            TextColumn::make('numerazione')
                ->label('Numerazione')
                ->formatStateUsing(function ($state, $record) {
                    $totale = $record->abbonamento?->servizio?->incontri ?? 0;

                    if ($totale === 0) {
                        return 'Lezione ' . $state;
                    }

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

            TextColumn::make('pt.name')
                ->label('PT')
                ->formatStateUsing(fn ($state) => $state ?: '-')
                ->sortable()
                ->toggleable(),

            TextColumn::make('descrizione')
                ->label('Descrizione')
                ->limit(40)
                ->toggleable(),

            TextColumn::make('calendar_synced_at')
                ->label('Ultimo sync')
                ->dateTime('d/m/Y H:i')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('created_at')
                ->label('Creato il')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
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
            Action::make('syncCalendar')
                ->label('Sync')
                ->icon('heroicon-o-arrow-path')
                ->color(fn ($record) => in_array($record->calendar_sync_status, ['dirty', 'failed']) ? 'warning' : 'success')
                ->action(function ($record) {
                    app(GoogleCalendarService::class)->syncAppuntamento(
                        $record->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                    );
                }),

            EditAction::make(),

            DeleteAction::make(),
        ])
        ->bulkActions([
            DeleteBulkAction::make(),
        ]);
    }
}
