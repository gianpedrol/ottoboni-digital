<?php

namespace App\Filament\Resources\AutomationRuns\Pages;

use App\Filament\Resources\AutomationRuns\AutomationRunResource;
use App\Models\AutomationRun;
use App\Support\Demonstracao;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewAutomationRun extends ViewRecord
{
    protected static string $resource = AutomationRunResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        /** @var AutomationRun $run */
        $run = $this->getRecord();

        return $run->demo ? Demonstracao::selo() : null;
    }
}
