<?php

namespace App\Filament\Resources\Clientes\Pages;

use App\Filament\Resources\Abbonamentos\AbbonamentoResource;
use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Clientes\Widgets\ClienteAbbonamentoAttivoWidget;
use App\Filament\Resources\Clientes\Widgets\ClienteAppuntamentiPassatiTableWidget;
use App\Filament\Resources\Clientes\Widgets\ClienteAppuntamentiProssimiTableWidget;
use App\Filament\Resources\Clientes\Widgets\ClienteNoteWidget;
use App\Filament\Resources\Clientes\Widgets\ClienteOverviewStats;
use App\Filament\Resources\Clientes\Widgets\ClienteStoricoAbbonamentiTableWidget;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        $cliente = $this->record;

        $abbonamentoAttivo = $cliente->abbonamenti()
            ->with('clienti')
            ->where('terminato', false)
            ->orderByDesc('data_inizio')
            ->first();

        $queryAppuntamento = [
            'cliente_id' => $cliente->id,
        ];

        if ($abbonamentoAttivo) {
            $queryAppuntamento['abbonamento_id'] = $abbonamentoAttivo->id;

            $altriPartecipanti = $abbonamentoAttivo->clienti
                ->filter(fn ($c) => (int) $c->id !== (int) $cliente->id)
                ->pluck('id')
                ->values()
                ->all();

            if (! empty($altriPartecipanti)) {
                $queryAppuntamento['clienti'] = $altriPartecipanti;
            }
        }

        $queryAbbonamento = [
            'cliente_id' => $cliente->id,
        ];

        return [
            Action::make('nuovoAppuntamento')
                ->label('Nuovo appuntamento')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->url(fn () => AppuntamentoResource::getUrl('create', ['tenant' => null]) . '?' . http_build_query($queryAppuntamento)),

            Action::make('nuovoAbbonamento')
                ->label('Nuovo abbonamento')
                ->icon('heroicon-o-rectangle-stack')
                ->color('success')
                ->url(fn () => AbbonamentoResource::getUrl('create', ['tenant' => null]) . '?' . http_build_query($queryAbbonamento)),

            Action::make('whatsapp')
                ->label('WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn () => $cliente->telefono ? 'https://wa.me/' . preg_replace('/\D+/', '', $cliente->telefono) : '#')
                ->openUrlInNewTab()
                ->visible(fn () => filled($cliente->telefono)),

            Action::make('email')
                ->label('Email')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->url(fn () => $cliente->email ? 'mailto:' . $cliente->email : '#')
                ->visible(fn () => filled($cliente->email)),

            DeleteAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ClienteOverviewStats::class,
            ClienteAbbonamentoAttivoWidget::class,
            ClienteNoteWidget::class,
            ClienteAppuntamentiProssimiTableWidget::class,
            ClienteAppuntamentiPassatiTableWidget::class,
            ClienteStoricoAbbonamentiTableWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 1;
    }
}