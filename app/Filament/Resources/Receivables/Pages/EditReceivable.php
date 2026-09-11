<?php

namespace App\Filament\Resources\Receivables\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\Receivables\ReceivableResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReceivable extends EditRecord
{
    use ComSeloDemonstracao;

    protected static string $resource = ReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
