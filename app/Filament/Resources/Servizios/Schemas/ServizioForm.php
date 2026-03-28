<?php

namespace App\Filament\Resources\Servizios\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Forms;

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
                    ])
                    ->columns(2);
    }
}
