<?php

namespace App\Filament\Resources\Pagamentos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PagamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('cliente_id')
                    ->required()
                    ->numeric(),
                TextInput::make('abbonamento_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('tipo')
                    ->required(),
                DatePicker::make('competenza_da'),
                DatePicker::make('competenza_a'),
                TextInput::make('descrizione')
                    ->default(null),
                TextInput::make('importo_previsto')
                    ->required()
                    ->numeric(),
                TextInput::make('importo_pagato')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                DatePicker::make('scadenza'),
                DatePicker::make('data_saldo'),
                TextInput::make('stato')
                    ->required()
                    ->default('da_pagare'),
                TextInput::make('numero_rata')
                    ->numeric()
                    ->default(null),
                TextInput::make('totale_rate')
                    ->numeric()
                    ->default(null),
                TextInput::make('google_calendar_event_id')
                    ->default(null),
                TextInput::make('calendar_sync_status')
                    ->default(null),
                Textarea::make('calendar_last_error')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
