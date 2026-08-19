<?php

namespace App\Filament\Resources\FollowupPlans\Pages;

use App\Filament\Resources\FollowupPlans\FollowupPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFollowupPlan extends EditRecord
{
    protected static string $resource = FollowupPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
