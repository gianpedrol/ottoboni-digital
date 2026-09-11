<?php

namespace App\Filament\Resources\AutomationRules\Tables;

use App\Enums\ModoAutomacao;
use App\Filament\Pages\SimuladorAutomacao;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Services\Automacoes\DescritorDeRegra;
use App\Services\Automacoes\NomesDoKommo;
use App\Services\Automacoes\NormalizadorDeRegra;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AutomationRulesTable
{
    public static function configure(Table $table): Table
    {
        $nomes = new NomesDoKommo;

        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('nome')
                    ->label('Regra')
                    ->weight('medium')
                    ->description(fn (AutomationRule $record): ?string => $record->descricao)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('gatilho')
                    ->label('Quando')
                    ->state(fn (AutomationRule $record): string => DescritorDeRegra::gatilho($record->gatilho))
                    ->description(fn (AutomationRule $record): ?string => DescritorDeRegra::condicoes($record->condicoes))
                    ->wrap(),
                TextColumn::make('pipelines')
                    ->label('Pipelines')
                    ->state(fn (AutomationRule $record): array => array_map(
                        fn (int $id): string => $nomes->pipeline($id),
                        NormalizadorDeRegra::pipelines((array) $record->pipeline_ids),
                    ))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('modo')
                    ->label('Modo')
                    ->badge(),
                TextColumn::make('runs_count')
                    ->label('Execuções (7 dias)')
                    ->counts(['runs' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7))])
                    ->alignCenter(),
            ])
            ->defaultSort('ordem')
            ->recordActions([
                Action::make('simular')
                    ->label('Simular')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->color('gray')
                    ->url(fn (AutomationRule $record): string => SimuladorAutomacao::getUrl(['regra' => $record->id])),
                self::acaoModo(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma regra de automação')
            ->emptyStateDescription('Crie a regra, simule com um lead e só depois mude o modo.');
    }

    private static function acaoModo(): Action
    {
        return Action::make('modo')
            ->label('Modo')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->modalHeading(fn (AutomationRule $record): string => "Modo da regra {$record->rotulo()}")
            ->modalSubmitActionLabel('Salvar modo')
            ->fillForm(fn (AutomationRule $record): array => ['modo' => $record->modo->value])
            ->schema([
                ToggleButtons::make('modo')
                    ->label('Modo')
                    ->options(ModoAutomacao::class)
                    ->inline()
                    ->required()
                    ->live(),
                Callout::make('No protótipo nada é gravado no Kommo')
                    ->description('A regra fica marcada como ativa, mas o que ela faria continua só registrado nas execuções, com o payload que seria enviado. A gravação de verdade entra depois da reestruturação do Kommo.')
                    ->warning()
                    ->visible(fn ($get): bool => NormalizadorDeRegra::escalar($get('modo')) === ModoAutomacao::Ativo->value),
            ])
            ->action(function (AutomationRule $record, array $data): void {
                $modo = ModoAutomacao::from((string) NormalizadorDeRegra::escalar($data['modo']));

                $record->update(['modo' => $modo]);

                AuditLog::registrar('alterou_modo_automacao', [
                    'rule_id' => $record->id,
                    'codigo' => $record->codigo,
                    'modo' => $modo->value,
                ]);

                Notification::make()
                    ->success()
                    ->title("{$record->rotulo()}: {$modo->getLabel()}")
                    ->body($modo === ModoAutomacao::Ativo ? 'Lembrete: no protótipo nada é gravado no Kommo.' : null)
                    ->send();
            });
    }
}
