<?php

namespace App\Filament\Ia\Resources\IaTriggers\Pages;

use App\Filament\Ia\Resources\IaTriggers\IaTriggerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIaTriggers extends ListRecords
{
    protected static string $resource = IaTriggerResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
