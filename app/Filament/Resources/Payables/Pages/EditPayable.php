<?php

namespace App\Filament\Resources\Payables\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\Payables\PayableResource;
use App\Filament\Resources\Payables\Schemas\PayableForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayable extends EditRecord
{
    use ComSeloDemonstracao;

    protected static string $resource = PayableResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PayableForm::ajustarStatus($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
