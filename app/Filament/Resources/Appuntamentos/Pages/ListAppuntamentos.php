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
        ];
    }
}
