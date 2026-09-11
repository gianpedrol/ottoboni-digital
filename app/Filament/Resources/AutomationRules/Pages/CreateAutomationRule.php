<?php

namespace App\Filament\Resources\AutomationRules\Pages;

use App\Filament\Resources\AutomationRules\AutomationRuleResource;
use App\Filament\Resources\AutomationRules\Schemas\AutomationRuleForm;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomationRule extends CreateRecord
{
    protected static string $resource = AutomationRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AutomationRuleForm::paraSalvar($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
