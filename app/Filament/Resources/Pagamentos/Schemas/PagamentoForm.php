<?php

namespace App\Filament\Resources\Pagamentos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class PagamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cliente_id')
                ->label('Cliente')
                ->relationship('cliente', 'nome')
                ->searchable()
                ->preload()
                ->required()
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome),

            Select::make('abbonamento_id')
                ->label('Abbonamento')
                ->relationship('abbonamento', 'id')
                ->searchable()
                ->preload(),

            Select::make('tipo')
                ->options([
                    'pacchetto' => 'Pacchetto',
                    'rata' => 'Rata',
                    'mensile' => 'Mensile',
                ])
                ->required(),

            TextInput::make('descrizione')
                ->required(),

            TextInput::make('importo_previsto')
                ->numeric()
                ->required(),

            TextInput::make('importo_pagato')
                ->numeric()
                ->default(0)
                ->required(),

            DatePicker::make('scadenza'),

            DatePicker::make('data_saldo'),

            Select::make('stato')
                ->options([
                    'da_pagare' => 'Da pagare',
                    'parziale' => 'Parziale',
                    'pagato' => 'Pagato',
                    'annullato' => 'Annullato',
                ])
                ->required(),
        ]);
    }
}
