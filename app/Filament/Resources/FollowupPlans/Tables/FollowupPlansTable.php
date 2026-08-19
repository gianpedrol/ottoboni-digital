<?php

namespace App\Filament\Resources\FollowupPlans\Tables;

use App\Filament\Pages\SimuladorRegua;
use App\Jobs\AgendarFollowupsDoPlanoJob;
use App\Models\AuditLog;
use App\Models\FollowupPlan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FollowupPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Régua')
                    ->weight('medium')
                    ->description(fn (FollowupPlan $record): ?string => $record->descricao),
                TextColumn::make('doctor.nome')
                    ->label('Médico'),
                TextColumn::make('gatilho')
                    ->label('Gatilho')
                    ->badge(),
                TextColumn::make('steps_count')
                    ->label('Passos')
                    ->counts('steps'),
                TextColumn::make('runs_count')
                    ->label('Execuções')
                    ->counts('runs'),
                IconColumn::make('ativo')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('simular')
                    ->label('Simular')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->color('gray')
                    ->url(fn (FollowupPlan $record): string => SimuladorRegua::getUrl(['plan' => $record->id])),
                Action::make('ativar')
                    ->label(fn (FollowupPlan $record): string => $record->ativo ? 'Desativar' : 'Ativar')
                    ->icon(fn (FollowupPlan $record) => $record->ativo ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                    ->color(fn (FollowupPlan $record): string => $record->ativo ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (FollowupPlan $record): string => $record->ativo
                        ? 'Desativar esta régua?'
                        : 'Ativar esta régua?')
                    ->modalDescription(fn (FollowupPlan $record): string => $record->ativo
                        ? 'Os passos já agendados continuam na fila; use a tela de Execuções para cancelá-los.'
                        : 'Ao ativar, os leads que casam com o gatilho serão agendados. Simulou antes?')
                    ->action(function (FollowupPlan $record): void {
                        $record->update(['ativo' => ! $record->ativo]);

                        AuditLog::registrar($record->ativo ? 'ativou_regua' : 'desativou_regua', [
                            'plan_id' => $record->id,
                            'nome' => $record->nome,
                        ]);

                        if ($record->ativo) {
                            AgendarFollowupsDoPlanoJob::dispatch($record->id);

                            Notification::make()
                                ->success()
                                ->title('Régua ativada')
                                ->body('Os leads que casam com o gatilho estão sendo agendados em segundo plano.')
                                ->send();
                        } else {
                            Notification::make()
                                ->warning()
                                ->title('Régua desativada')
                                ->body('Passos já agendados não serão enviados (a régua desativada é verificada antes de cada envio).')
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma régua criada')
            ->emptyStateDescription('Crie a primeira régua de follow-up e simule com um lead real antes de ativar.');
    }
}
