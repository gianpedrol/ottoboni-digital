<?php

namespace App\Filament\Resources\AutomationRules\Pages;

use App\Filament\Pages\SimuladorAutomacao;
use App\Filament\Resources\AutomationRules\AutomationRuleResource;
use App\Filament\Resources\AutomationRules\Schemas\AutomationRuleForm;
use App\Models\AutomationRule;
use App\Support\Demonstracao;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditAutomationRule extends EditRecord
{
    protected static string $resource = AutomationRuleResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        /** @var AutomationRule $regra */
        $regra = $this->getRecord();

        return $regra->demo ? Demonstracao::selo() : null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return AutomationRuleForm::paraPreencher($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AutomationRuleForm::paraSalvar($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simular')
                ->label('Simular')
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->url(fn (): string => SimuladorAutomacao::getUrl(['regra' => $this->getRecord()->getKey()])),
            DeleteAction::make(),
        ];
    }
}
