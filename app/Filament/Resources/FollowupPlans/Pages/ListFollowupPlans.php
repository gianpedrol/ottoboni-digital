<?php

namespace App\Filament\Resources\FollowupPlans\Pages;

use App\Filament\Resources\FollowupPlans\FollowupPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFollowupPlans extends ListRecords
{
    protected static string $resource = FollowupPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
