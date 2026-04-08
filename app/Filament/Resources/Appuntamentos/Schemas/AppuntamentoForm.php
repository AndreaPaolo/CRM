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
use Filament\Forms\Components\Toggle;
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
                    ->afterStateUpdated(function (callable $set) {
                        $set('abbonamento_id', null);
                        $set('clienti', []);
                    }),

                Select::make('clienti')
                    ->label('Partecipanti')
                    ->relationship('clienti', 'nome')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome),

                Select::make('abbonamento_id')
                    ->label('Abbonamento')
                    ->options(function (callable $get) {
                        $clienteId = $get('cliente_id');

                        if (! $clienteId) {
                            return [];
                        }

                        return Abbonamento::query()
                            ->with(['servizio', 'clienti'])
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

                                return [
                                    $abbonamento->id => $nomeServizio . ' | dal ' . optional($abbonamento->data_inizio)->format('d/m/Y'),
                                ];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabled(fn (callable $get) => blank($get('cliente_id')))
                    ->placeholder('Seleziona prima il cliente')
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (! $state) {
                            return;
                        }

                        $abbonamento = Abbonamento::with('servizio')->find($state);
                        $servizio = $abbonamento?->servizio;

                        if (! $servizio) {
                            return;
                        }

                        $set('tipo_appuntamento', $servizio->tipo_appuntamento_default ?? 'personal');
                        $set('evento_intera_giornata', (bool) ($servizio->evento_intera_giornata_default ?? false));

                        if (($servizio->evento_intera_giornata_default ?? false) === true) {
                            $set('durata', 1440);
                        }
                    }),

                Select::make('tipo_appuntamento')
                    ->label('Tipo appuntamento')
                    ->options([
                        'personal' => 'Personal',
                        'call_google_meet' => 'Call Google Meet',
                        'consegna_programma' => 'Consegna programma',
                    ])
                    ->required()
                    ->default('personal')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state === 'consegna_programma') {
                            $set('evento_intera_giornata', true);
                            $set('durata', 1440);
                        }

                        if ($state === 'call_google_meet') {
                            $set('evento_intera_giornata', false);
                            $set('durata', 60);
                        }
                    }),

                Toggle::make('evento_intera_giornata')
                    ->label('Evento intera giornata')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('durata', 1440);
                        }
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

                        if ($abbonamento->servizio->tipo_fatturazione === 'mensile') {
                            $mese = now();
                            $conteggio = Appuntamento::query()
                                ->where('abbonamento_id', $abbonamentoId)
                                ->whereYear('data_ora', $mese->year)
                                ->whereMonth('data_ora', $mese->month)
                                ->count() + 1;

                            return $conteggio . '/mese';
                        }

                        $prossimoNumero = (Appuntamento::where('abbonamento_id', $abbonamentoId)->max('numerazione') ?? 0) + 1;
                        $totale = $abbonamento->servizio->incontri;

                        return 'Lezione ' . $prossimoNumero . ' / ' . $totale;
                    }),

                DateTimePicker::make('data_ora')
                    ->label('Data e ora')
                    ->seconds(false)
                    ->required()
                    ->helperText('Se l’evento è giornata intera, l’orario verrà ignorato e impostato a inizio giornata.'),

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