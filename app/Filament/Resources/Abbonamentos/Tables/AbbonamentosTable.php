<?php

namespace App\Filament\Resources\Abbonamentos\Tables;

use App\Models\Abbonamento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AbbonamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_inizio', 'desc')
            ->columns([
                TextColumn::make('cliente_principale')
                    ->label('Cliente')
                    ->state(function (Abbonamento $record) {
                        if (! $record->cliente) {
                            return '-';
                        }

                        return $record->cliente->nome . ' ' . $record->cliente->cognome;
                    })
                    ->weight(FontWeight::Bold)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cliente', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%")
                                ->orWhere('cognome', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),

                TextColumn::make('partecipanti_condivisioni')
                    ->label('Condivisioni')
                    ->state(function (Abbonamento $record) {
                        $partecipanti = $record->clienti
                            ->filter(fn ($cliente) => (int) $cliente->id !== (int) $record->cliente_id)
                            ->map(fn ($cliente) => $cliente->nome . ' ' . $cliente->cognome)
                            ->values();

                        if ($partecipanti->isEmpty()) {
                            return '';
                        }

                        return $partecipanti->implode("\n");
                    })
                    ->listWithLineBreaks()
                    ->placeholder('-')
                    ->color('gray')
                    ->wrap(),

                TextColumn::make('servizio.nome')
                    ->label('Servizio')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
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

                TextColumn::make('utilizzo_dettaglio')
                    ->label('Utilizzo')
                    ->state(function (Abbonamento $record) {
                        $totale = (int) ($record->servizio?->incontri ?? 0);

                        $sessioniUsate = $record->appuntamenti
                            ->map(fn ($appuntamento) => $appuntamento->sessione_condivisa_uuid ?: 'single_' . $appuntamento->id)
                            ->unique()
                            ->count();

                        if ($totale <= 0) {
                            return "{$sessioniUsate}\nSenza limite";
                        }

                        $percentuale = min(100, (int) round(($sessioniUsate / max(1, $totale)) * 100));

                        return "{$sessioniUsate} / {$totale}\n{$percentuale}% usato";
                    })
                    ->listWithLineBreaks()
                    ->badge()
                    ->color(function (Abbonamento $record) {
                        $totale = (int) ($record->servizio?->incontri ?? 0);

                        if ($totale <= 0) {
                            return 'gray';
                        }

                        $sessioniUsate = $record->appuntamenti
                            ->map(fn ($appuntamento) => $appuntamento->sessione_condivisa_uuid ?: 'single_' . $appuntamento->id)
                            ->unique()
                            ->count();

                        $percentuale = ($sessioniUsate / max(1, $totale)) * 100;

                        return match (true) {
                            $percentuale >= 100 => 'danger',
                            $percentuale >= 75 => 'warning',
                            default => 'success',
                        };
                    }),

                TextColumn::make('stato_label')
                    ->label('Stato')
                    ->state(fn (Abbonamento $record) => $record->terminato ? 'Terminato' : 'Attivo')
                    ->badge()
                    ->color(fn (Abbonamento $record) => $record->terminato ? 'danger' : 'success')
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('periodo')
                    ->label('Periodo')
                    ->state(function (Abbonamento $record) {
                        $inizio = $record->data_inizio?->format('d/m/Y') ?? '-';
                        $fine = $record->data_fine?->format('d/m/Y') ?? '-';

                        return "Dal {$inizio}\nAl {$fine}";
                    })
                    ->listWithLineBreaks()
                    ->color(function (Abbonamento $record) {
                        if (! $record->data_fine) {
                            return 'gray';
                        }

                        if ($record->terminato) {
                            return 'danger';
                        }

                        if ($record->data_fine->isPast()) {
                            return 'danger';
                        }

                        if ($record->data_fine->lte(now()->addDays(7))) {
                            return 'warning';
                        }

                        return 'gray';
                    })
                    ->wrap(),

                TextColumn::make('prezzo')
                    ->label('Prezzo')
                    ->money('EUR', locale: 'it')
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('updated_at')
                    ->label('Aggiornato')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo_partecipazione')
                    ->label('Tipo abbonamento')
                    ->options([
                        'singolo' => 'Singolo',
                        'condiviso' => 'Condiviso',
                        'gruppo' => 'Gruppo / Small group',
                    ]),

                TernaryFilter::make('terminato')
                    ->label('Terminato')
                    ->trueLabel('Terminati')
                    ->falseLabel('Attivi')
                    ->native(false),

                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nome')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome),

                Filter::make('in_scadenza')
                    ->label('In scadenza 7 giorni')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('data_fine')
                        ->whereDate('data_fine', '>=', now()->toDateString())
                        ->whereDate('data_fine', '<=', now()->addDays(7)->toDateString())
                    ),
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