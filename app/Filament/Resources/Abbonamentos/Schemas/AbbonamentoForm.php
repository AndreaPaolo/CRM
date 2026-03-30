<?php

namespace App\Filament\Resources\Abbonamentos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;

class AbbonamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->relationship('cliente', 'nome')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome)
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('servizio_id')
                            ->label('Servizio')
                            ->relationship('servizio', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $dataInizio = $get('data_inizio');

                                if ($state && $dataInizio) {
                                    $servizio = \App\Models\Servizio::find($state);

                                    if ($servizio) {
                                        $set('data_fine', \Carbon\Carbon::parse($dataInizio)->addDays($servizio->durata)->format('Y-m-d'));
                                    }
                                }
                            }),

                        TextInput::make('prezzo')
                            ->label('Prezzo')
                            ->numeric()
                            ->prefix('€')
                            ->required(),

                        TextInput::make('rate')
                            ->label('Rate')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),

                        DatePicker::make('data_inizio')
                            ->label('Data inizio')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $servizioId = $get('servizio_id');

                                if ($state && $servizioId) {
                                    $servizio = \App\Models\Servizio::find($servizioId);

                                    if ($servizio) {
                                        $set('data_fine', \Carbon\Carbon::parse($state)->addDays($servizio->durata)->format('Y-m-d'));
                                    }
                                }
                            }),

                        DatePicker::make('data_fine')
                            ->label('Data fine')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Calcolata automaticamente in base alla durata del servizio.'),

                        Toggle::make('terminato')
                            ->label('Terminato')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('terminato_manualmente', (bool) $state);
                            }),

                        Hidden::make('terminato_manualmente')
                            ->default(false),
                    ])
                    ->columns(2);
    }
}
