<?php

namespace App\Filament\Resources\Receivables\Pages;

use App\Filament\Concerns\ComSeloDemonstracao;
use App\Filament\Resources\Receivables\ReceivableResource;
use App\Filament\Resources\Receivables\Widgets\ReceivablesStats;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceivables extends ListRecords
{
    use ComSeloDemonstracao;

    protected static string $resource = ReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova conta a receber'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReceivablesStats::class,
        ];
    }
}
