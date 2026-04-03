<?php

namespace App\Filament\Resources\Clientes\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('cognome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('telefono')
                    ->tel()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Textarea::make('note')
                    ->label('Note cliente')
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }
}
