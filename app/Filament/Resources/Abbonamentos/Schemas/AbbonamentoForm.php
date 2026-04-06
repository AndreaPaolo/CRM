<?php

namespace App\Filament\Resources\Abbonamentos\Schemas;

use App\Models\Servizio;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AbbonamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'nome')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Select::make('tipo_partecipazione')
                    ->label('Tipo partecipazione')
                    ->options([
                        'singolo' => 'Singolo',
                        'condiviso' => 'Condiviso',
                        'gruppo' => 'Gruppo / Small group',
                    ])
                    ->default('singolo')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state === 'singolo') {
                            $set('clienti', []);
                        }
                    }),

                Select::make('clienti')
                    ->label('Partecipanti')
                    ->relationship('clienti', 'nome')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome)
                    ->helperText('Disponibile solo per condiviso o small group.')
                    ->disabled(fn (callable $get) => ! in_array($get('tipo_partecipazione'), ['condiviso', 'gruppo'], true))
                    ->visible(fn (callable $get) => in_array($get('tipo_partecipazione'), ['condiviso', 'gruppo'], true))
                    ->dehydrated(fn (callable $get) => in_array($get('tipo_partecipazione'), ['condiviso', 'gruppo'], true)),

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
                            $servizio = Servizio::find($state);

                            if ($servizio) {
                                $set(
                                    'data_fine',
                                    \Carbon\Carbon::parse($dataInizio)
                                        ->addDays($servizio->durata)
                                        ->format('Y-m-d')
                                );
                            }
                        }
                    }),

                TextInput::make('prezzo')
                    ->label('Prezzo cliente')
                    ->numeric()
                    ->prefix('€')
                    ->required()
                    ->live()
                    ->helperText('Per i mensili è il costo orario del cliente. Per i pacchetti è il totale del pacchetto.'),

                TextInput::make('rate')
                    ->label('Numero rate')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required()
                    ->live()
                    ->helperText('Se > 1 il CRM genera automaticamente le rate residue.'),

                Toggle::make('registra_pagamento_iniziale')
                    ->label('Registra pagamento iniziale')
                    ->default(false)
                    ->live()
                    ->dehydrated(false),

                TextInput::make('importo_pagamento_iniziale')
                    ->label('Importo pagato oggi')
                    ->numeric()
                    ->prefix('€')
                    ->minValue(0)
                    ->visible(fn (callable $get) => (bool) $get('registra_pagamento_iniziale'))
                    ->required(fn (callable $get) => (bool) $get('registra_pagamento_iniziale'))
                    ->dehydrated(false)
                    ->helperText('Esempio: prezzo 100€, 3 rate, oggi paga 50€.'),

                Select::make('metodo_pagamento_iniziale')
                    ->label('Metodo pagamento iniziale')
                    ->options([
                        'contanti' => 'Contanti',
                        'bonifico' => 'Bonifico',
                        'carta' => 'Carta',
                        'paypal' => 'PayPal',
                        'altro' => 'Altro',
                    ])
                    ->visible(fn (callable $get) => (bool) $get('registra_pagamento_iniziale'))
                    ->required(fn (callable $get) => (bool) $get('registra_pagamento_iniziale'))
                    ->dehydrated(false),

                Placeholder::make('anteprima_rate')
                    ->label('Anteprima rate')
                    ->content(function (callable $get) {
                        if (! $get('registra_pagamento_iniziale')) {
                            return 'Nessun pagamento iniziale registrato.';
                        }

                        $prezzo = (float) ($get('prezzo') ?: 0);
                        $rate = max(1, (int) ($get('rate') ?: 1));
                        $iniziale = (float) ($get('importo_pagamento_iniziale') ?: 0);

                        if ($prezzo <= 0) {
                            return 'Inserisci prima il prezzo.';
                        }

                        if ($iniziale > $prezzo) {
                            return 'L’importo iniziale non può superare il prezzo totale.';
                        }

                        if ($rate === 1) {
                            return 'Pagamento unico.';
                        }

                        $residuo = max(0, $prezzo - $iniziale);
                        $rateResidue = max(0, $rate - 1);

                        if ($rateResidue === 0) {
                            return 'Nessuna rata residua.';
                        }

                        $quota = round($residuo / $rateResidue, 2);

                        return "Residuo: € " . number_format($residuo, 2, ',', '.') .
                            " · Rate residue: {$rateResidue} · Quota indicativa: € " .
                            number_format($quota, 2, ',', '.');
                    })
                    ->visible(fn (callable $get) => (bool) $get('registra_pagamento_iniziale')),

                DatePicker::make('data_inizio')
                    ->label('Data inizio')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        $servizioId = $get('servizio_id');

                        if ($state && $servizioId) {
                            $servizio = Servizio::find($servizioId);

                            if ($servizio) {
                                $set(
                                    'data_fine',
                                    \Carbon\Carbon::parse($state)
                                        ->addDays($servizio->durata)
                                        ->format('Y-m-d')
                                );
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