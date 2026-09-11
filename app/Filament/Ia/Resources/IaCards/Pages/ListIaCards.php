<?php

namespace App\Filament\Ia\Resources\IaCards\Pages;

use App\Filament\Ia\Resources\IaCards\IaCardResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIaCards extends ListRecords
{
    protected static string $resource = IaCardResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
