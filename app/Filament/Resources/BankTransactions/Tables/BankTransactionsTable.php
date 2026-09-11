<?php

namespace App\Filament\Resources\BankTransactions\Tables;

use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\ReceivableInstallment;
use App\Services\Financeiro\Conciliador;
use App\Support\Financeiro;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BankTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => self::comSaldo($query)->with(['account', 'conciliavel']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('data')->orderByDesc('id'))
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y'),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->description(fn (BankTransaction $record): string => $record->account->apelido),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL')
                    ->color(fn (BankTransaction $record): string => $record->ehCredito() ? 'success' : 'danger')
                    ->weight('medium')
                    ->alignEnd(),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->state(fn (BankTransaction $record): float => (float) $record->account->saldo_inicial
                        + (float) $record->getAttribute('saldo_acumulado'))
                    ->money('BRL')
                    ->color('gray')
                    ->alignEnd(),
                TextColumn::make('conciliacao')
                    ->label('Conciliação')
                    ->state(fn (BankTransaction $record): string => $record->estaConciliado() ? 'Conciliado' : 'Pendente')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Conciliado' ? 'success' : 'warning')
                    ->description(fn (BankTransaction $record): ?string => self::vinculo($record)),
                TextColumn::make('fitid')
                    ->label('FITID')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('nao_conciliados')
                    ->label('Só não conciliados')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('conciliado_em')),
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'credito' => 'Créditos',
                        'debito' => 'Débitos',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'credito' => $query->where('valor', '>', 0),
                        'debito' => $query->where('valor', '<', 0),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('conciliar')
                    ->label('Conciliar')
                    ->icon(Heroicon::OutlinedLink)
                    ->color('primary')
                    ->visible(fn (BankTransaction $record): bool => ! $record->estaConciliado())
                    ->modalHeading('Conciliar lançamento')
                    ->modalDescription(fn (BankTransaction $record): string => $record->data->format('d/m/Y') . ' · '
                        . $record->descricao . ' · ' . Financeiro::brl($record->valor))
                    ->modalSubmitActionLabel('Conciliar e dar baixa')
                    ->fillForm(fn (BankTransaction $record): array => [
                        'item' => array_key_first(app(Conciliador::class)->opcoes($record)),
                    ])
                    ->schema(fn (BankTransaction $record): array => [
                        Select::make('item')
                            ->label($record->ehCredito() ? 'Parcela a receber' : 'Conta a pagar')
                            ->options(app(Conciliador::class)->opcoes($record))
                            ->required()
                            ->placeholder('Nenhum item em aberto com esse valor')
                            ->helperText('Sugestões: itens em aberto com o mesmo valor e vencimento até '
                                . Conciliador::JANELA_DIAS . ' dias antes ou depois. Ao confirmar, o item é baixado com a data do banco.'),
                    ])
                    ->action(function (BankTransaction $record, array $data): void {
                        $conciliador = app(Conciliador::class);
                        $item = $conciliador->resolver((string) $data['item']);

                        if ($item === null) {
                            Notification::make()->danger()->title('Item não encontrado')->send();

                            return;
                        }

                        $conciliador->conciliar($record, $item);

                        Notification::make()
                            ->success()
                            ->title('Lançamento conciliado')
                            ->body($item instanceof Payable ? 'Conta marcada como paga.' : 'Parcela baixada como recebida.')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum lançamento')
            ->emptyStateDescription('Importe o OFX exportado do internet banking pelo botão "Importar OFX".');
    }

    /**
     * Saldo corrido por conta: soma dos lançamentos da mesma conta até este
     * (por data e, no mesmo dia, pela ordem de inclusão). Vale com qualquer filtro.
     *
     * @param  Builder<BankTransaction>  $query
     * @return Builder<BankTransaction>
     */
    public static function comSaldo(Builder $query): Builder
    {
        $acumulado = BankTransaction::query()
            ->from('bank_transactions as anteriores')
            ->selectRaw('COALESCE(SUM(anteriores.valor), 0)')
            ->whereColumn('anteriores.bank_account_id', 'bank_transactions.bank_account_id')
            ->where(fn (Builder $q) => $q
                ->whereColumn('anteriores.data', '<', 'bank_transactions.data')
                ->orWhere(fn (Builder $q) => $q
                    ->whereColumn('anteriores.data', 'bank_transactions.data')
                    ->whereColumn('anteriores.id', '<=', 'bank_transactions.id')));

        return $query->select('bank_transactions.*')->selectSub($acumulado, 'saldo_acumulado');
    }

    private static function vinculo(BankTransaction $record): ?string
    {
        if (! $record->estaConciliado()) {
            return null;
        }

        $item = $record->conciliavel;

        return match (true) {
            $item instanceof ReceivableInstallment => "Parcela {$item->numero} · " . ($item->receivable->patient->nome ?? $item->receivable->descricao),
            $item instanceof Payable => $item->fornecedor,
            default => 'Sem vínculo (tarifa, rendimento ou transferência)',
        };
    }
}
