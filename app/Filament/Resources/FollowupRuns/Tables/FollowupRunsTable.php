<?php

namespace App\Filament\Resources\FollowupRuns\Tables;

use App\Enums\FollowupRunStatus;
use App\Filament\Pages\FichaAtendimento;
use App\Models\FollowupRun;
use App\Models\User;
use App\Services\Followup\CanceladorDeSeguintes;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FollowupRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lead_id_kommo')
                    ->label('Lead')
                    ->url(fn (FollowupRun $record): string => FichaAtendimento::getUrl(['leadId' => $record->lead_id_kommo]))
                    ->description('abrir ficha'),
                TextColumn::make('plan.nome')
                    ->label('Régua'),
                TextColumn::make('step.ordem')
                    ->label('Passo'),
                TextColumn::make('doctor.nome')
                    ->label('Médico'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('agendado_para')
                    ->label('Agendado para')
                    ->dateTime('d/m/Y H:i', timezone: config('painel.timezone'))
                    ->sortable(),
                TextColumn::make('executado_em')
                    ->label('Executado em')
                    ->dateTime('d/m/Y H:i', timezone: config('painel.timezone'))
                    ->placeholder('—'),
                TextColumn::make('canal_usado')
                    ->label('Canal usado')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('motivo_cancelamento')
                    ->label('Motivo')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(FollowupRunStatus::class),
                SelectFilter::make('plan_id')
                    ->label('Régua')
                    ->relationship('plan', 'nome'),
            ])
            ->recordActions([
                Action::make('respondido')
                    ->label('Lead respondeu')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->color('success')
                    ->visible(fn (FollowupRun $record): bool => $record->status === FollowupRunStatus::Enviado
                        && self::podeGerenciar())
                    ->requiresConfirmation()
                    ->modalDescription('Marca este follow-up como respondido e cancela os passos seguintes do lead nesta régua.')
                    ->action(function (FollowupRun $record): void {
                        app(CanceladorDeSeguintes::class)->marcarRespondido($record);

                        Notification::make()
                            ->success()
                            ->title('Follow-up marcado como respondido')
                            ->body('Os passos seguintes deste lead foram cancelados.')
                            ->send();
                    }),
                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (FollowupRun $record): bool => $record->status === FollowupRunStatus::Agendado
                        && self::podeGerenciar())
                    ->requiresConfirmation()
                    ->action(function (FollowupRun $record): void {
                        $record->update([
                            'status' => FollowupRunStatus::Cancelado,
                            'motivo_cancelamento' => 'cancelado manualmente no painel',
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Follow-up cancelado')
                            ->send();
                    }),
            ])
            ->defaultSort('agendado_para', 'desc')
            ->emptyStateHeading('Nenhuma execução ainda')
            ->emptyStateDescription('As execuções aparecem aqui quando uma régua ativa agenda follow-ups.');
    }

    private static function podeGerenciar(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && ($user->isAdmin() || $user->isGestor());
    }
}
