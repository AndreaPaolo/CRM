<?php

namespace App\Filament\Resources\Appuntamentos\Tables;

use App\Models\Appuntamento;
use App\Services\GoogleCalendarService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AppuntamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_ora', 'desc')
            ->columns([
                TextColumn::make('data_ora')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('tipo_appuntamento')
                    ->label('Tipologia')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'personal' => 'Personal',
                        'call_google_meet' => 'Call Google Meet',
                        'consegna_programma' => 'Consegna programma',
                        default => '-',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'personal' => 'gray',
                        'call_google_meet' => 'info',
                        'consegna_programma' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->state(fn (Appuntamento $record) => trim(($record->cliente?->nome ?? '') . ' ' . ($record->cliente?->cognome ?? '')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cliente', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%")
                                ->orWhere('cognome', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                TextColumn::make('abbonamento.servizio.nome')
                    ->label('Servizio')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('numerazione_label')
                    ->label('Lezione')
                    ->state(function (Appuntamento $record) {
                        $servizio = $record->abbonamento?->servizio;

                        if (! $servizio) {
                            return '-';
                        }

                        if (($record->tipo_appuntamento ?? 'personal') !== 'personal') {
                            return '-';
                        }

                        if ($servizio->tipo_fatturazione === 'mensile') {
                            return ($record->numerazione ?: '-') . '/mese';
                        }

                        $totale = (int) ($servizio->incontri ?? 0);

                        return $totale > 0
                            ? (($record->numerazione ?: '-') . '/' . $totale)
                            : (string) ($record->numerazione ?: '-');
                    }),

                TextColumn::make('durata')
                    ->label('Durata')
                    ->formatStateUsing(fn ($state, Appuntamento $record) => $record->evento_intera_giornata ? 'Giornata intera' : ($state . ' min')),

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
                    })
                    ->tooltip('Clicca per sincronizzare questo appuntamento')
                    ->action(function (Appuntamento $record): void {
                        try {
                            app(GoogleCalendarService::class)->syncAppuntamento(
                                $record->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                            );

                            Notification::make()
                                ->title('Appuntamento sincronizzato')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            $record->updateQuietly([
                                'calendar_sync_status' => 'failed',
                                'calendar_last_error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Errore sincronizzazione')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                TextColumn::make('calendar_last_error')
                    ->label('Errore sync')
                    ->limit(40)
                    ->tooltip(fn (?string $state) => $state)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo_appuntamento')
                    ->label('Tipologia')
                    ->options([
                        'personal' => 'Personal',
                        'call_google_meet' => 'Call Google Meet',
                        'consegna_programma' => 'Consegna programma',
                    ]),

                SelectFilter::make('calendar_sync_status')
                    ->label('Sync')
                    ->options([
                        'synced' => 'Sync OK',
                        'dirty' => 'Da aggiornare',
                        'failed' => 'Errore',
                    ]),

                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nome')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome),

                Filter::make('data_range')
                    ->label('Data')
                    ->form([
                        DatePicker::make('da')->label('Da'),
                        DatePicker::make('a')->label('A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['da'] ?? null, fn (Builder $q, $date) => $q->whereDate('data_ora', '>=', $date))
                            ->when($data['a'] ?? null, fn (Builder $q, $date) => $q->whereDate('data_ora', '<=', $date));
                    }),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('sync_singolo')
                    ->label('Sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function (Appuntamento $record): void {
                        try {
                            app(GoogleCalendarService::class)->syncAppuntamento(
                                $record->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                            );

                            Notification::make()
                                ->title('Appuntamento sincronizzato')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            $record->updateQuietly([
                                'calendar_sync_status' => 'failed',
                                'calendar_last_error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Errore sincronizzazione')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('sync_bulk')
                        ->label('Sincronizza selezionati')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $ok = 0;
                            $errors = 0;

                            foreach ($records as $record) {
                                try {
                                    app(GoogleCalendarService::class)->syncAppuntamento(
                                        $record->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                                    );
                                    $ok++;
                                } catch (\Throwable $e) {
                                    $record->updateQuietly([
                                        'calendar_sync_status' => 'failed',
                                        'calendar_last_error' => $e->getMessage(),
                                    ]);
                                    $errors++;
                                }
                            }

                            Notification::make()
                                ->title('Sincronizzazione completata')
                                ->body("Sincronizzati: {$ok} · Errori: {$errors}")
                                ->success()
                                ->send();
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}