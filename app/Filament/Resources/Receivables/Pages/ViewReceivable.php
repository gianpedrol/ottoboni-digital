<?php

namespace App\Filament\Resources\Receivables\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\Receivables\ReceivableResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReceivable extends ViewRecord
{
    use ComSeloDemonstracao;

    protected static string $resource = ReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
