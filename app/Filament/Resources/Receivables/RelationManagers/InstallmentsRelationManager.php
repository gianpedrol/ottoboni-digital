<?php

namespace App\Filament\Resources\Receivables\RelationManagers;

use App\Enums\StatusFinanceiro;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Support\Financeiro;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'Parcelas';

    protected static ?string $modelLabel = 'parcela';

    protected static ?string $pluralModelLabel = 'parcelas';

    // A baixa de parcela vale também na tela de visualização.
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $receivable = $this->getOwnerRecord();
        $total = $receivable instanceof Receivable ? $receivable->installments()->count() : 0;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('bankTransactions'))
            ->defaultSort('numero')
            ->paginated(false)
            ->columns([
                TextColumn::make('numero')
                    ->label('Parcela')
                    ->formatStateUsing(fn (int $state): string => "{$state}/{$total}"),
                TextColumn::make('vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y'),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL')
                    ->alignEnd(),
                TextColumn::make('situacao')
                    ->label('Status')
                    ->state(fn (ReceivableInstallment $record): StatusFinanceiro => $record->situacao())
                    ->badge(),
                TextColumn::make('pago_em')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('valor_pago')
                    ->label('Valor pago')
                    ->money('BRL')
                    ->placeholder('—')
                    ->alignEnd(),
                IconColumn::make('conciliada')
                    ->label('No extrato')
                    ->state(fn (ReceivableInstallment $record): bool => ($record->getAttribute('bank_transactions_count') ?? 0) > 0)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedBuildingLibrary)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->falseColor('gray')
                    ->tooltip(fn (bool $state): string => $state ? 'Conciliada com o extrato bancário' : 'Ainda não conciliada'),
            ])
            ->recordActions([
                Action::make('registrarPagamento')
                    ->label('Registrar pagamento')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (ReceivableInstallment $record): bool => $record->status !== StatusFinanceiro::Pago->value
                        && $record->status !== StatusFinanceiro::Cancelado->value)
                    ->modalHeading(fn (ReceivableInstallment $record): string => "Registrar pagamento da parcela {$record->numero}/{$total}")
                    ->modalSubmitActionLabel('Registrar')
                    ->fillForm(fn (ReceivableInstallment $record): array => [
                        'pago_em' => Financeiro::hoje()->toDateString(),
                        'valor_pago' => (float) $record->valor,
                    ])
                    ->schema([
                        DatePicker::make('pago_em')
                            ->label('Data do pagamento')
                            ->displayFormat('d/m/Y')
                            ->required(),
                        TextInput::make('valor_pago')
                            ->label('Valor pago')
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('R$')
                            ->required(),
                    ])
                    ->action(function (ReceivableInstallment $record, array $data): void {
                        $record->registrarPagamento($data['pago_em'], (float) $data['valor_pago']);

                        Notification::make()
                            ->success()
                            ->title('Pagamento registrado')
                            ->body("Parcela {$record->numero} baixada: " . Financeiro::brl($data['valor_pago']) . '.')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Sem parcelas');
    }
}
