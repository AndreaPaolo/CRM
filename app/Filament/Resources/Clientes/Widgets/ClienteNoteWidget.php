<?php

namespace App\Filament\Resources\Clientes\Widgets;

use Filament\Widgets\Widget;

class ClienteNoteWidget extends Widget
{
    protected string $view = 'filament.resources.clientes.widgets.cliente-note-widget';

    public ?\App\Models\Cliente $record = null;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'cliente' => $this->record,
        ];
    }
}