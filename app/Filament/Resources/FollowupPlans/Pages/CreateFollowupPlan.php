<?php

namespace App\Filament\Resources\FollowupPlans\Pages;

use App\Filament\Resources\FollowupPlans\FollowupPlanResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateFollowupPlan extends CreateRecord
{
    protected static string $resource = FollowupPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        AuditLog::registrar('criou_regua', [
            'plan_id' => $this->record->getKey(),
            'nome' => $this->record->getAttribute('nome'),
        ]);
    }
}
