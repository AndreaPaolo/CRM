<?php

namespace App\Filament\Resources\Appuntamentos\Pages;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Services\GoogleCalendarService;
use Filament\Actions\CreateAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppuntamentos extends ListRecords
{
    protected static string $resource = AppuntamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Actions\Action::make('cleanCalendarOrphans')
                ->label('Pulisci calendario')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Eliminare gli eventi non presenti nel CRM?')
                ->modalDescription('Verranno eliminati da Google Calendar solo gli eventi creati dal CRM che non esistono più nel database.')
                ->action(function () {
                    $count = app(GoogleCalendarService::class)->deleteOrphanCalendarEvents();

                    \Filament\Notifications\Notification::make()
                        ->title('Pulizia completata')
                        ->body("Eventi eliminati dal calendario: {$count}")
                        ->success()
                        ->send();
            }),
            Actions\Action::make('syncAll')
                ->label('Sincronizza tutto')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->action(function () {
            $service = app(\App\Services\GoogleCalendarService::class);

            $appuntamenti = \App\Models\Appuntamento::with([
                'cliente',
                'abbonamento.servizio',
                'pt',
            ])
                ->where(function ($query) {
                    $query->whereIn('calendar_sync_status', ['dirty', 'failed'])
                        ->orWhere('updated_at', '>=', now()->subDays(7));
                })
                ->orderByDesc('updated_at')
                ->get();

            $abbonamentoIds = $appuntamenti
                ->pluck('abbonamento_id')
                ->filter()
                ->unique();

            $abbonamenti = \App\Models\Abbonamento::with([
                'appuntamenti.cliente',
                'appuntamenti.abbonamento.servizio',
                'appuntamenti.pt',
                'servizio',
            ])
                ->whereIn('id', $abbonamentoIds)
                ->get();

            $sincronizzati = 0;

            foreach ($abbonamenti as $abbonamento) {
                $abbonamento->aggiornaNumerazioneAppuntamenti();
                $abbonamento->aggiornaStatoTerminato();

                $appuntamentiDaSincronizzare = $abbonamento->appuntamenti
                    ->filter(function ($appuntamento) {
                        return in_array($appuntamento->calendar_sync_status, ['dirty', 'failed'])
                            || $appuntamento->updated_at >= now()->subDays(1);
                    });

                foreach ($appuntamentiDaSincronizzare as $appuntamento) {
                    try {
                        $service->syncAppuntamento(
                            $appuntamento->fresh(['cliente', 'abbonamento.servizio', 'pt'])
                        );

                        $sincronizzati++;
                    } catch (\Throwable $e) {
                        // opzionale: log errore
                    }
                }
            }

            \Filament\Notifications\Notification::make()
                ->title('Sincronizzazione completata')
                ->body("Eventi sincronizzati: {$sincronizzati}")
                ->success()
                ->send();
            })
        ];
    }
}
