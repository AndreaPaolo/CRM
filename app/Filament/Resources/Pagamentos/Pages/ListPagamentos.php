<?php

namespace App\Filament\Resources\Pagamentos\Pages;

use App\Filament\Resources\Pagamentos\PagamentoResource;
use App\Models\Pagamento;
use App\Services\GoogleCalendarService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPagamentos extends ListRecords
{
    protected static string $resource = PagamentoResource::class;

    public function mount(): void {
        parent::mount();

        $this->verificaSyncPagamenti();
    }

    protected function getHeaderActions(): array {
        return [
            CreateAction::make(),
        ];
    }

    protected function verificaSyncPagamenti(): void {
        $pagamenti = Pagamento::query()
            ->with(['cliente', 'abbonamento.servizio'])
            ->whereNotNull('scadenza')
            ->where(function ($query) {
                $query->whereNull('google_calendar_event_id')
                    ->orWhereIn('calendar_sync_status', ['dirty', 'failed'])
                    ->orWhereNull('calendar_sync_status');
            })
            ->limit(100)
            ->get();

        if ($pagamenti->isEmpty()) {
            return;
        }

        $service = app(GoogleCalendarService::class);

        foreach ($pagamenti as $pagamento) {
            try {
                $service->syncPagamento($pagamento);
            } catch (\Throwable $e) {
                //
            }
        }
    }
}