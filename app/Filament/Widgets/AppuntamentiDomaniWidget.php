<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Models\Appuntamento;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AppuntamentiDomaniWidget extends TableWidget
{
    protected static ?string $heading = 'Appuntamenti di domani';

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appuntamento::query()
                    ->with(['cliente', 'abbonamento.servizio', 'pt'])
                    ->whereDate('data_ora', today()->addDay())
                    ->orderBy('data_ora')
            )
            ->recordUrl(fn (Appuntamento $record): string => AppuntamentoResource::getUrl('edit', ['record' => $record]))
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('cliente_nome')
                    ->label('Nome e cognome')
                    ->state(fn (Appuntamento $record) => trim(($record->cliente?->nome ?? '') . ' ' . ($record->cliente?->cognome ?? '')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cliente', function (Builder $q) use ($search) {
                            $q->where('nome', 'like', "%{$search}%")
                                ->orWhere('cognome', 'like', "%{$search}%");
                        });
                    })
                    ->weight('bold'),

                TextColumn::make('data_ora')
                    ->label('Ora')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('lezione_label')
                    ->label('Lezione')
                    ->state(fn (Appuntamento $record) => $this->buildLezioneLabel($record)),
            ])
            ->recordActions([
                Action::make('whatsapp_reminder')
                    ->label('Reminder WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Appuntamento $record) => $this->buildWhatsappUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Appuntamento $record) => filled($record->cliente?->telefono)),
            ]);
    }

    protected function buildLezioneLabel(Appuntamento $record): string
    {
        $servizio = $record->abbonamento?->servizio;

        if (! $servizio) {
            return '-';
        }

        if ($servizio->tipo_fatturazione === 'mensile') {
            $conteggioMese = Appuntamento::query()
                ->where('abbonamento_id', $record->abbonamento_id)
                ->whereYear('data_ora', $record->data_ora->year)
                ->whereMonth('data_ora', $record->data_ora->month)
                ->where('data_ora', '<=', $record->data_ora)
                ->count();

            return $conteggioMese . '/mese';
        }

        $totale = (int) ($servizio->incontri ?? 0);

        return $totale > 0
            ? (($record->numerazione ?? 0) . '/' . $totale)
            : (string) ($record->numerazione ?? '-');
    }

    protected function buildWhatsappUrl(Appuntamento $record): ?string
    {
        $telefono = preg_replace('/\D+/', '', (string) ($record->cliente?->telefono ?? ''));

        if (! $telefono) {
            return null;
        }

        $cliente = trim(($record->cliente?->nome ?? '') . ' ' . ($record->cliente?->cognome ?? ''));
        $ora = $record->data_ora?->format('H:i') ?? '--:--';
        $servizio = $record->abbonamento?->servizio?->nome ?? 'appuntamento';

        $messaggio = "Ciao {$cliente}, ti ricordo l'appuntamento di domani alle {$ora} per {$servizio}. A domani!";

        return 'https://wa.me/' . $telefono . '?text=' . urlencode($messaggio);
    }
}