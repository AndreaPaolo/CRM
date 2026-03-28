<?php

namespace App\Filament\Resources\Appuntamentos\Schemas;

use App\Models\Abbonamento;
use App\Models\Appuntamento;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AppuntamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                ->default(fn () => Auth::id()),

            Select::make('cliente_id')
                ->label('Cliente')
                ->relationship('cliente', 'nome')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome)
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('abbonamento_id', null)),

            Select::make('abbonamento_id')
                ->label('Abbonamento')
                ->options(function (callable $get) {
                    $clienteId = $get('cliente_id');

                    if (! $clienteId) {
                        return [];
                    }

                    return Abbonamento::query()
                        ->where('cliente_id', $clienteId)
                        ->with('servizio')
                        ->get()
                        ->mapWithKeys(function ($abbonamento) {
                            $nomeServizio = $abbonamento->servizio?->nome ?? 'Servizio';
                            return [
                                $abbonamento->id => $nomeServizio . ' | dal ' . optional($abbonamento->data_inizio)->format('d/m/Y'),
                            ];
                        });
                })
                ->searchable()
                ->preload()
                ->required()
                ->live(),

            Placeholder::make('anteprima_numerazione')
                ->label('Numerazione')
                ->content(function (callable $get) {
                    $abbonamentoId = $get('abbonamento_id');

                    if (! $abbonamentoId) {
                        return 'Seleziona prima un abbonamento';
                    }

                    $abbonamento = Abbonamento::with('servizio')->find($abbonamentoId);

                    if (! $abbonamento || ! $abbonamento->servizio) {
                        return '-';
                    }

                    $prossimoNumero = (Appuntamento::where('abbonamento_id', $abbonamentoId)->max('numerazione') ?? 0) + 1;
                    $totale = $abbonamento->servizio->incontri;

                    return 'Lezione ' . $prossimoNumero . ' / ' . $totale;
                }),

            DateTimePicker::make('data_ora')
                ->label('Data e ora')
                ->seconds(false)
                ->required(),

            TextInput::make('durata')
                ->label('Durata (minuti)')
                ->numeric()
                ->default(60)
                ->required()
                ->minValue(1),

            Textarea::make('descrizione')
                ->label('Descrizione')
                ->rows(4)
                ->columnSpanFull(),
            ]);
    }
}
