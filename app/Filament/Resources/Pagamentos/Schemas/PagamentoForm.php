<?php

namespace App\Filament\Resources\Pagamentos\Schemas;

use App\Models\Abbonamento;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                ->live()
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome)
                ->afterStateUpdated(fn (callable $set) => $set('abbonamento_id', null)),

            Select::make('abbonamento_id')
                ->label('Abbonamento')
                ->options(function (callable $get) {
                    $clienteId = $get('cliente_id');

                    if (! $clienteId) {
                        return [];
                    }

                    return Abbonamento::query()
                        ->with(['servizio', 'clienti'])
                        ->where('terminato', false)
                        ->where(function ($query) use ($clienteId) {
                            $query->where('cliente_id', $clienteId)
                                ->orWhereHas('clienti', function ($q) use ($clienteId) {
                                    $q->where('cliente.id', $clienteId);
                                });
                        })
                        ->orderByDesc('data_inizio')
                        ->orderByDesc('created_at')
                        ->get()
                        ->mapWithKeys(function ($abbonamento) {
                            $nomeServizio = $abbonamento->servizio?->nome ?? 'Servizio';
                            $dataInizio = $abbonamento->data_inizio?->format('d/m/Y') ?? '-';

                            return [
                                $abbonamento->id => $nomeServizio . ' | dal ' . $dataInizio,
                            ];
                        });
                })
                ->searchable()
                ->preload()
                ->live()
                ->disabled(fn (callable $get) => blank($get('cliente_id')))
                ->placeholder('Seleziona prima il cliente'),

            Select::make('tipo')
                ->label('Tipo')
                ->options([
                    'pacchetto' => 'Pacchetto',
                    'rata' => 'Rata',
                    'mensile' => 'Mensile',
                ])
                ->required(),

            TextInput::make('descrizione')
                ->label('Descrizione')
                ->required(),

            TextInput::make('importo_previsto')
                ->label('Importo previsto')
                ->numeric()
                ->required(),

            TextInput::make('importo_pagato')
                ->label('Importo pagato')
                ->numeric()
                ->default(0)
                ->required(),

            DatePicker::make('scadenza')
                ->label('Scadenza'),

            DatePicker::make('data_saldo')
                ->label('Data saldo'),

            Select::make('stato')
                ->label('Stato')
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