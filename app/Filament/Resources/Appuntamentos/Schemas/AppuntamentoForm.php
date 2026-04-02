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

                // Compatibilità col vecchio sistema:
                // se vuoi continuare a vedere il cliente principale puoi lasciarlo.
                Select::make('cliente_id')
                    ->label('Cliente principale')
                    ->relationship('cliente', 'nome')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Quando cambio cliente principale resetto abbonamento e partecipanti
                        $set('abbonamento_id', null);
                        $set('clienti', []);
                    })
                    ->helperText('Campo legacy: usato solo per compatibilità con i vecchi appuntamenti.'),

                Select::make('abbonamento_id')
                    ->label('Abbonamento')
                    ->options(function (callable $get) {
                        $clienteId = $get('cliente_id');

                        // Se scelgo un cliente principale, mostro i suoi abbonamenti
                        // usando sia il vecchio cliente_id sia i nuovi abbonamenti condivisi.
                        if ($clienteId) {
                            return Abbonamento::query()
                                ->where(function ($query) use ($clienteId) {
                                    $query->where('cliente_id', $clienteId)
                                        ->orWhereHas('clienti', function ($q) use ($clienteId) {
                                            $q->whereKey($clienteId);
                                        });
                                })
                                ->with('servizio')
                                ->orderByDesc('data_inizio')
                                ->orderByDesc('created_at')
                                ->get()
                                ->mapWithKeys(function ($abbonamento) {
                                    $nomeServizio = $abbonamento->servizio?->nome ?? 'Servizio';

                                    return [
                                        $abbonamento->id => $nomeServizio . ' | dal ' . optional($abbonamento->data_inizio)->format('d/m/Y'),
                                    ];
                                })
                                ->toArray();
                        }

                        // Se non c'è cliente principale, mostro tutti gli abbonamenti recenti
                        return Abbonamento::query()
                            ->with('servizio')
                            ->orderByDesc('data_inizio')
                            ->orderByDesc('created_at')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($abbonamento) {
                                $nomeServizio = $abbonamento->servizio?->nome ?? 'Servizio';

                                return [
                                    $abbonamento->id => $nomeServizio . ' | dal ' . optional($abbonamento->data_inizio)->format('d/m/Y'),
                                ];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Quando cambio abbonamento resetto i partecipanti
                        $set('clienti', []);
                    }),

                Select::make('clienti')
                    ->label('Altri partecipanti')
                    ->multiple()
                    ->options(function (callable $get) {
                        $abbonamentoId = $get('abbonamento_id');

                        if (! $abbonamentoId) {
                            return [];
                        }

                        $abbonamento = Abbonamento::with('clienti')->find($abbonamentoId);

                        if (! $abbonamento) {
                            return [];
                        }

                        return $abbonamento->clienti
                            ->mapWithKeys(function ($cliente) {
                                return [
                                    $cliente->id => $cliente->nome . ' ' . $cliente->cognome,
                                ];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required(function (callable $get) {
                        $abbonamentoId = $get('abbonamento_id');

                        if (! $abbonamentoId) {
                            return false;
                        }

                        $abbonamento = Abbonamento::find($abbonamentoId);

                        if (! $abbonamento) {
                            return false;
                        }

                        return $abbonamento->tipo_partecipazione === 'gruppo';
                    })
                    ->helperText(function (callable $get) {
                        $abbonamentoId = $get('abbonamento_id');

                        if (! $abbonamentoId) {
                            return 'Seleziona prima un abbonamento.';
                        }

                        $abbonamento = Abbonamento::find($abbonamentoId);

                        if (! $abbonamento) {
                            return null;
                        }

                        return match ($abbonamento->tipo_partecipazione) {
                            'gruppo' => 'Per lo small group seleziona tutti gli altri partecipanti della lezione.',
                            'condiviso' => 'Per il pacchetto condiviso puoi lasciarlo vuoto se stai inserendo solo il cliente principale.',
                            default => 'Per il pacchetto singolo lascia vuoto.',
                        };
                    }),

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

                        if ((int) $totale > 0) {
                            return 'Lezione ' . $prossimoNumero . ' / ' . $totale;
                        }

                        return 'Lezione ' . $prossimoNumero;
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