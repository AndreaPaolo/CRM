<?php

namespace App\Filament\Resources\Appuntamentos\Schemas;

use App\Models\Abbonamento;
use App\Models\Appuntamento;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
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
                    ->afterStateUpdated(function (callable $set) {
                        $set('abbonamento_id', null);
                        $set('clienti', []);
                        $set('tipo_appuntamento', 'personal');
                        $set('evento_intera_giornata', false);
                        $set('durata', 60);
                        $set('data_ora', null);
                        $set('data_evento', null);
                    }),

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
                                $servizio = $abbonamento->servizio?->nome ?? 'Servizio';
                                $data = optional($abbonamento->data_inizio)->format('d/m/Y');
                                $stato = $abbonamento->terminato ? 'terminato' : 'attivo';

                                return [
                                    $abbonamento->id => "{$servizio} | dal {$data} | {$stato}",
                                ];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabled(fn (callable $get) => blank($get('cliente_id')))
                    ->placeholder('Seleziona prima il cliente')
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        self::applyDefaultsFromAbbonamento($state, $set, $get);
                    }),

                Select::make('clienti')
                    ->label('Partecipanti')
                    ->relationship('clienti', 'nome')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' ' . $record->cognome),

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
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state === 'consegna_programma') {
                            $set('evento_intera_giornata', true);
                            $set('durata', 1440);

                            $dataOra = $get('data_ora');
                            if ($dataOra) {
                                $set('data_evento', Carbon::parse($dataOra)->format('Y-m-d'));
                            }

                            return;
                        }

                        $set('evento_intera_giornata', false);

                        if ((int) ($get('durata') ?: 0) === 1440 || blank($get('durata'))) {
                            $set('durata', 60);
                        }

                        $dataEvento = $get('data_evento');
                        if ($dataEvento && ! $get('data_ora')) {
                            $set('data_ora', Carbon::parse($dataEvento)->setHour(9)->setMinute(0)->format('Y-m-d H:i:s'));
                        }
                    }),

                Hidden::make('evento_intera_giornata')
                    ->default(false),

                DateTimePicker::make('data_ora')
                    ->label('Data e ora')
                    ->seconds(false)
                    ->required(fn (callable $get) => $get('tipo_appuntamento') !== 'consegna_programma')
                    ->visible(fn (callable $get) => $get('tipo_appuntamento') !== 'consegna_programma')
                    ->live()
                    ->helperText('Usalo per personal e call Google Meet.'),

                DatePicker::make('data_evento')
                    ->label('Data evento')
                    ->required(fn (callable $get) => $get('tipo_appuntamento') === 'consegna_programma')
                    ->visible(fn (callable $get) => $get('tipo_appuntamento') === 'consegna_programma')
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('data_ora', Carbon::parse($state)->startOfDay()->format('Y-m-d H:i:s'));
                        }
                    })
                    ->helperText('Per consegna programma viene creato un evento giornata intera.'),

                TextInput::make('durata')
                    ->label('Durata (minuti)')
                    ->numeric()
                    ->default(60)
                    ->required()
                    ->minValue(1)
                    ->disabled(fn (callable $get) => $get('tipo_appuntamento') === 'consegna_programma')
                    ->dehydrated(),

                Placeholder::make('anteprima_numerazione')
                    ->label('Lezione')
                    ->content(function (callable $get) {
                        $abbonamentoId = $get('abbonamento_id');

                        if (! $abbonamentoId) {
                            return 'Seleziona prima un abbonamento';
                        }

                        if ($get('tipo_appuntamento') !== 'personal') {
                            return 'Non consuma una lezione del pacchetto personal.';
                        }

                        $abbonamento = Abbonamento::with('servizio')->find($abbonamentoId);

                        if (! $abbonamento || ! $abbonamento->servizio) {
                            return '-';
                        }

                        if ($abbonamento->servizio->tipo_fatturazione === 'mensile') {
                            $riferimento = $get('data_ora')
                                ? Carbon::parse($get('data_ora'))
                                : now();

                            $conteggio = Appuntamento::query()
                                ->where('abbonamento_id', $abbonamentoId)
                                ->where('tipo_appuntamento', 'personal')
                                ->whereYear('data_ora', $riferimento->year)
                                ->whereMonth('data_ora', $riferimento->month)
                                ->count() + 1;

                            return $conteggio . '/mese';
                        }

                        $prossimoNumero = (Appuntamento::query()
                            ->where('abbonamento_id', $abbonamentoId)
                            ->where('tipo_appuntamento', 'personal')
                            ->max('numerazione') ?? 0) + 1;

                        $totale = $abbonamento->servizio->incontri ?? 0;

                        return $totale > 0
                            ? "Lezione {$prossimoNumero} / {$totale}"
                            : "Lezione {$prossimoNumero}";
                    }),

                Placeholder::make('anteprima_calendar')
                    ->label('Google Calendar')
                    ->content(function (callable $get) {
                        return match ($get('tipo_appuntamento')) {
                            'call_google_meet' => 'Aggiornerà il titolo dell’evento e manterrà/creerà il Google Meet.',
                            'consegna_programma' => 'Aggiornerà il titolo e creerà un evento giornata intera.',
                            default => 'Aggiornerà il titolo come evento personal standard.',
                        };
                    }),

                Textarea::make('descrizione')
                    ->label('Descrizione / note')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected static function applyDefaultsFromAbbonamento($abbonamentoId, callable $set, callable $get): void
    {
        if (! $abbonamentoId) {
            return;
        }

        $abbonamento = Abbonamento::with('servizio')->find($abbonamentoId);
        $servizio = $abbonamento?->servizio;

        if (! $servizio) {
            return;
        }

        if (blank($get('tipo_appuntamento')) || $get('tipo_appuntamento') === 'personal') {
            $tipo = $servizio->tipo_appuntamento_default ?? 'personal';
            $set('tipo_appuntamento', $tipo);

            if ($tipo === 'consegna_programma') {
                $set('evento_intera_giornata', true);
                $set('durata', 1440);
            } else {
                $set('evento_intera_giornata', false);
                $set('durata', 60);
            }
        }
    }
}