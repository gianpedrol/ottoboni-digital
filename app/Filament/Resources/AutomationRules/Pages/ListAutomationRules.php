<?php

namespace App\Filament\Resources\AutomationRules\Pages;

use App\Filament\Pages\SimuladorAutomacao;
use App\Filament\Resources\AutomationRules\AutomationRuleResource;
use App\Models\AutomationRule;
use App\Support\Demonstracao;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListAutomationRules extends ListRecords
{
    protected static string $resource = AutomationRuleResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return AutomationRule::query()->where('demo', true)->exists() ? Demonstracao::selo() : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simulador')
                ->label('Abrir simulador')
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->url(SimuladorAutomacao::getUrl()),
            CreateAction::make(),
        ];
    }
}
