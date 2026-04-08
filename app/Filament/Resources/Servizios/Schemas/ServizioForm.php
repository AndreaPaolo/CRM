<?php

namespace App\Filament\Resources\Servizios\Schemas;

use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ServizioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('nome')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('descrizione')
                    ->label('Descrizione')
                    ->rows(4)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('durata')
                    ->label('Durata (giorni)')
                    ->numeric()
                    ->required()
                    ->minValue(1),

                Forms\Components\TextInput::make('incontri')
                    ->label('Incontri')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->minValue(0)
                    ->helperText('Numero totale di personal, call o consegne comprese nel servizio.'),

                Select::make('tipo_fatturazione')
                    ->label('Tipo fatturazione')
                    ->options([
                        'pacchetto' => 'Pacchetto',
                        'mensile' => 'Mensile',
                    ])
                    ->default('pacchetto')
                    ->required(),

                Select::make('tipo_appuntamento_default')
                    ->label('Tipo appuntamento default')
                    ->options([
                        'personal' => 'Personal',
                        'call_google_meet' => 'Call Google Meet',
                        'consegna_programma' => 'Consegna programma',
                    ])
                    ->default('personal')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state === 'call_google_meet') {
                            $set('crea_google_meet_default', true);
                            $set('evento_intera_giornata_default', false);
                        }

                        if ($state === 'consegna_programma') {
                            $set('crea_google_meet_default', false);
                            $set('evento_intera_giornata_default', true);
                        }

                        if ($state === 'personal') {
                            $set('crea_google_meet_default', false);
                            $set('evento_intera_giornata_default', false);
                        }
                    }),

                Forms\Components\Toggle::make('evento_intera_giornata_default')
                    ->label('Evento intera giornata')
                    ->default(false),

                Forms\Components\Toggle::make('crea_google_meet_default')
                    ->label('Crea Google Meet')
                    ->default(false),

                Forms\Components\Toggle::make('prenotazione_autonoma_cliente')
                    ->label('Prenotazione autonoma cliente')
                    ->default(false)
                    ->helperText('Preparazione futura per permettere al cliente di scegliere lo slot in autonomia.'),
            ])
            ->columns(2);
    }
}