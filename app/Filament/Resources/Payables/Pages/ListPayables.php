<?php

namespace App\Filament\Resources\Payables\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\Payables\PayableResource;
use App\Filament\Resources\Payables\Widgets\PayablesStats;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayables extends ListRecords
{
    use ComSeloDemonstracao;

    protected static string $resource = PayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova conta a pagar'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PayablesStats::class,
        ];
    }
}
