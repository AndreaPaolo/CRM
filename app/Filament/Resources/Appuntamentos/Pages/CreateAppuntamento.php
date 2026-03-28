<?php

namespace App\Filament\Resources\Appuntamentos\Pages;

use App\Filament\Resources\Appuntamentos\AppuntamentoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppuntamento extends CreateRecord
{
    protected static string $resource = AppuntamentoResource::class;
    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return $data;
    }
}
