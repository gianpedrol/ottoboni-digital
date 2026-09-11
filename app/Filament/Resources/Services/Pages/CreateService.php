<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\Services\ServiceResource;
use App\Support\Financeiro;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    use ComSeloDemonstracao;

    protected static string $resource = ServiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['demo'] = Financeiro::registroDemo();

        return $data;
    }
}
