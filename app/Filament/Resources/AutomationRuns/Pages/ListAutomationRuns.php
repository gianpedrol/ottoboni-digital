<?php

namespace App\Filament\Resources\AutomationRuns\Pages;

use App\Filament\Pages\SimuladorAutomacao;
use App\Filament\Resources\AutomationRuns\AutomationRunResource;
use App\Models\AutomationRun;
use App\Support\Demonstracao;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListAutomationRuns extends ListRecords
{
    protected static string $resource = AutomationRunResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return AutomationRun::query()->where('demo', true)->exists() ? Demonstracao::selo() : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simulador')
                ->label('Abrir simulador')
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->url(SimuladorAutomacao::getUrl()),
        ];
    }
}
