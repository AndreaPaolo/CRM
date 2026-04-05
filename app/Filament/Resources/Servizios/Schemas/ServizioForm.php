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
                    ->helperText('Numero totale di personal, check, call o schede comprese nel servizio.'),

                Select::make('tipo_fatturazione')
                    ->label('Tipo fatturazione')
                    ->options([
                        'pacchetto' => 'Pacchetto',
                        'mensile' => 'Mensile',
                    ])
                    ->default('pacchetto')
                    ->required(),
            ])
            ->columns(2);
    }
}