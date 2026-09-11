<?php

namespace App\Filament\Ia\Resources\IaExamples\Pages;

use App\Filament\Ia\Resources\IaExamples\IaExampleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIaExamples extends ListRecords
{
    protected static string $resource = IaExampleResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
