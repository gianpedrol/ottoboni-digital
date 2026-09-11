<?php

namespace App\Filament\Ia\Resources\IaMaterials\Pages;

use App\Filament\Ia\Resources\IaMaterials\IaMaterialResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIaMaterials extends ListRecords
{
    protected static string $resource = IaMaterialResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Adicionar material')
                ->modalHeading('Novo material')
                ->createAnother(false)
                ->mutateDataUsing(function (array $data): array {
                    $data['criado_por'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
