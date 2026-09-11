<?php

namespace App\Filament\Resources\Payables\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\Payables\PayableResource;
use App\Filament\Resources\Payables\Schemas\PayableForm;
use App\Support\Financeiro;
use Filament\Resources\Pages\CreateRecord;

class CreatePayable extends CreateRecord
{
    use ComSeloDemonstracao;

    protected static string $resource = PayableResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['demo'] = Financeiro::registroDemo();

        return PayableForm::ajustarStatus($data);
    }
}
