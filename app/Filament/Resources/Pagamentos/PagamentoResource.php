<?php

namespace App\Filament\Resources\Pagamentos;

use App\Filament\Resources\Pagamentos\Pages\CreatePagamento;
use App\Filament\Resources\Pagamentos\Pages\EditPagamento;
use App\Filament\Resources\Pagamentos\Pages\ListPagamentos;
use App\Models\Pagamento;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagamentoResource extends Resource
{
    protected static ?string $model = Pagamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'descrizione';

    public static function form(Schema $schema): Schema
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('scadenza', 'desc')
            ->columns([
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->state(fn (Pagamento $record) => trim(($record->cliente?->nome ?? '') . ' ' . ($record->cliente?->cognome ?? '')))
                    ->searchable(),

                TextColumn::make('tipo')
                    ->badge(),

                TextColumn::make('descrizione')
                    ->wrap(),

                TextColumn::make('importo_previsto')
                    ->money('EUR', locale: 'it'),

                TextColumn::make('importo_pagato')
                    ->money('EUR', locale: 'it'),

                TextColumn::make('scadenza')
                    ->date('d/m/Y'),

                TextColumn::make('stato')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'pagato' => 'success',
                        'parziale' => 'warning',
                        'da_pagare' => 'danger',
                        'annullato' => 'gray',
                        default => 'gray',
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPagamentos::route('/'),
            'create' => CreatePagamento::route('/create'),
            'edit' => EditPagamento::route('/{record}/edit'),
        ];
    }
}