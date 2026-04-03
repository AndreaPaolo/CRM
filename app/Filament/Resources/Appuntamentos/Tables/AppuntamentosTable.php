<?php

namespace App\Filament\Resources\Appuntamentos\Tables;

use App\Models\Appuntamento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppuntamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_ora', 'desc')
            ->columns([
                TextColumn::make('data_ora')
                    ->label('Data e ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('cliente_display')
                    ->label('Cliente')
                    ->state(function (Appuntamento $record) {
                        return $record->cliente
                            ? $record->cliente->nome . ' ' . $record->cliente->cognome
                            : '-';
                    })
                    ->weight(FontWeight::SemiBold)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cliente', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%")
                                ->orWhere('cognome', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),

                TextColumn::make('sessione_info')
                    ->label('Sessione')
                    ->state(function (Appuntamento $record) {
                        if (! $record->sessione_condivisa_uuid) {
                            return 'Individuale';
                        }

                        $count = Appuntamento::query()
                            ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                            ->count();

                        return $count > 1 ? "Condivisa ({$count})" : 'Condivisa';
                    })
                    ->badge()
                    ->color(function (Appuntamento $record) {
                        if (! $record->sessione_condivisa_uuid) {
                            return 'gray';
                        }

                        $count = Appuntamento::query()
                            ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                            ->count();

                        return $count > 2 ? 'info' : 'warning';
                    }),

                TextColumn::make('abbonamento.servizio.nome')
                    ->label('Servizio')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('tipo_partecipazione')
                    ->label('Tipo')
                    ->state(fn (Appuntamento $record) => $record->abbonamento?->tipo_partecipazione)
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

                TextColumn::make('numerazione_label')
                    ->label('Lezione')
                    ->state(function (Appuntamento $record) {
                        $totale = (int) ($record->abbonamento?->servizio?->incontri ?? 0);

                        if ($totale > 0) {
                            return "{$record->numerazione} / {$totale}";
                        }

                        return (string) $record->numerazione;
                    })
                    ->badge()
                    ->color(function (Appuntamento $record) {
                        $totale = (int) ($record->abbonamento?->servizio?->incontri ?? 0);

                        if ($totale <= 0) {
                            return 'gray';
                        }

                        $percentuale = ($record->numerazione / max(1, $totale)) * 100;

                        return match (true) {
                            $percentuale >= 100 => 'danger',
                            $percentuale >= 70 => 'warning',
                            default => 'success',
                        };
                    }),

                TextColumn::make('durata')
                    ->label('Durata')
                    ->formatStateUsing(fn ($state) => $state . ' min')
                    ->alignCenter(),

                TextColumn::make('pt.name')
                    ->label('PT')
                    ->placeholder('-')
                    ->toggleable(),

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

                TextColumn::make('descrizione')
                    ->label('Descrizione')
                    ->limit(35)
                    ->tooltip(fn (Appuntamento $record) => $record->descrizione)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('calendar_synced_at')
                    ->label('Ultima sync')
                    ->since()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creato')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('giorno')
                    ->label('Giorno')
                    ->getTitleFromRecordUsing(fn (Appuntamento $record) => $record->data_ora?->format('d/m/Y'))
                    ->getDescriptionFromRecordUsing(fn (Appuntamento $record) => $record->data_ora?->translatedFormat('l'))
                    ->collapsible(),

                Group::make('sessione_condivisa_uuid')
                    ->label('Sessione condivisa')
                    ->getTitleFromRecordUsing(function (Appuntamento $record) {
                        if (! $record->sessione_condivisa_uuid) {
                            return 'Sessioni individuali';
                        }

                        $count = Appuntamento::query()
                            ->where('sessione_condivisa_uuid', $record->sessione_condivisa_uuid)
                            ->count();

                        return "Sessione condivisa ({$count})";
                    })
                    ->collapsible(),

                Group::make('tipo_partecipazione_virtuale')
                    ->label('Tipologia')
                    ->getTitleFromRecordUsing(function (Appuntamento $record) {
                        return match ($record->abbonamento?->tipo_partecipazione) {
                            'singolo' => 'Singolo',
                            'condiviso' => 'Condiviso',
                            'gruppo' => 'Gruppo',
                            default => 'Altro',
                        };
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('giorno')
            ->filters([
                SelectFilter::make('tipo_partecipazione')
                    ->label('Tipologia appuntamento')
                    ->options([
                        'singolo' => 'Singolo',
                        'condiviso' => 'Condiviso',
                        'gruppo' => 'Gruppo / Small group',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('abbonamento', function (Builder $q) use ($data) {
                            $q->where('tipo_partecipazione', $data['value']);
                        });
                    }),

                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nome')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome),

                SelectFilter::make('calendar_sync_status')
                    ->label('Sync Google')
                    ->options([
                        'synced' => 'Sync OK',
                        'dirty' => 'Da aggiornare',
                        'failed' => 'Errore',
                    ]),

                Filter::make('data_ora')
                    ->label('Data')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('data_da')
                            ->label('Da'),
                        \Filament\Forms\Components\DatePicker::make('data_a')
                            ->label('A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['data_da'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('data_ora', '>=', $date)
                            )
                            ->when(
                                $data['data_a'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('data_ora', '<=', $date)
                            );
                    }),

                Filter::make('oggi')
                    ->label('Solo oggi')
                    ->query(fn (Builder $query): Builder => $query->whereDate('data_ora', now()->toDateString())),

                Filter::make('sessioni_condivise')
                    ->label('Solo sessioni condivise')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('sessione_condivisa_uuid')),
            ])
            ->recordTitleAttribute('id')
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}