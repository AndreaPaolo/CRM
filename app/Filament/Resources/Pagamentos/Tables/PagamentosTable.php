<?php

namespace App\Filament\Resources\Pagamentos\Tables;

use App\Models\Pagamento;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('scadenza', 'desc')
            ->groups([
                Group::make('tipo')
                    ->label('Categoria')
                    ->collapsible(),
            ])
            ->defaultGroup('tipo')
            ->columns([
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->state(fn (Pagamento $record) => trim(($record->cliente?->nome ?? '') . ' ' . ($record->cliente?->cognome ?? '')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cliente', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%")
                                ->orWhere('cognome', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label('Categoria')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'pacchetto' => 'info',
                        'rata' => 'warning',
                        'mensile' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('descrizione')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('importo_previsto')
                    ->money('EUR', locale: 'it')
                    ->sortable(),

                TextColumn::make('importo_pagato')
                    ->money('EUR', locale: 'it')
                    ->sortable(),

                TextColumn::make('scadenza')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_saldo')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('stato')
                    ->badge()
                    ->sortable()
                    ->color(fn (?string $state) => match ($state) {
                        'pagato' => 'success',
                        'parziale' => 'warning',
                        'da_pagare' => 'danger',
                        'annullato' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Categoria')
                    ->options([
                        'pacchetto' => 'Pacchetto',
                        'rata' => 'Rata',
                        'mensile' => 'Mensile',
                    ]),

                SelectFilter::make('stato')
                    ->label('Stato')
                    ->options([
                        'da_pagare' => 'Da pagare',
                        'parziale' => 'Parziale',
                        'pagato' => 'Pagato',
                        'annullato' => 'Annullato',
                    ]),

                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nome')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome),

                Filter::make('scadenza_range')
                    ->label('Scadenza')
                    ->form([
                        DatePicker::make('da')->label('Da'),
                        DatePicker::make('a')->label('A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['da'] ?? null, fn (Builder $q, $date) => $q->whereDate('scadenza', '>=', $date))
                            ->when($data['a'] ?? null, fn (Builder $q, $date) => $q->whereDate('scadenza', '<=', $date));
                    }),

                Filter::make('data_saldo_range')
                    ->label('Data saldo')
                    ->form([
                        DatePicker::make('da')->label('Da'),
                        DatePicker::make('a')->label('A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['da'] ?? null, fn (Builder $q, $date) => $q->whereDate('data_saldo', '>=', $date))
                            ->when($data['a'] ?? null, fn (Builder $q, $date) => $q->whereDate('data_saldo', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('segna_pagato')
                    ->label('Segna pagato')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Pagamento $record) => ! in_array($record->stato, ['pagato', 'annullato'], true))
                    ->fillForm(function (Pagamento $record): array {
                        $residuo = max(0, round((float) $record->importo_previsto - (float) $record->importo_pagato, 2));

                        return [
                            'data_pagamento' => now()->toDateString(),
                            'importo' => $residuo,
                            'metodo' => null,
                            'note' => null,
                        ];
                    })
                    ->form([
                        DatePicker::make('data_pagamento')
                            ->label('Data pagamento')
                            ->required()
                            ->default(now()),

                        TextInput::make('importo')
                            ->label('Importo pagato')
                            ->numeric()
                            ->prefix('€')
                            ->required()
                            ->minValue(0.01),

                        Select::make('metodo')
                            ->label('Metodo')
                            ->options([
                                'contanti' => 'Contanti',
                                'bonifico' => 'Bonifico',
                                'carta' => 'Carta',
                                'paypal' => 'PayPal',
                                'altro' => 'Altro',
                            ]),

                        Textarea::make('note')
                            ->label('Note')
                            ->rows(3),
                    ])
                    ->action(function (Pagamento $record, array $data): void {
                        $record->movimenti()->create([
                            'data_pagamento' => $data['data_pagamento'],
                            'importo' => $data['importo'],
                            'metodo' => $data['metodo'] ?? null,
                            'note' => $data['note'] ?? null,
                        ]);
                    })
                    ->successNotificationTitle('Pagamento registrato'),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}