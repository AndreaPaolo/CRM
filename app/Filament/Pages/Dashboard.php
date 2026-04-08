<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AbbonamentiInScadenzaWidget;
use App\Filament\Widgets\AppuntamentiDomaniWidget;
use App\Filament\Widgets\AppuntamentiOggiWidget;
use App\Filament\Widgets\PagamentiApertiWidget;
use App\Services\DashboardSyncService;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    public function mount(): void
    {
        parent::mount();

        app(DashboardSyncService::class)->sync();
    }

    public function getColumns(): int | array
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [
            AppuntamentiOggiWidget::class,
            AppuntamentiDomaniWidget::class,
            PagamentiApertiWidget::class,
            AbbonamentiInScadenzaWidget::class,
        ];
    }
}